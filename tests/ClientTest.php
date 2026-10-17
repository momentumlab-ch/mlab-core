<?php
use MomentumLab\Core\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['mlab_core_http_queue'] = array();
		$GLOBALS['mlab_core_http_log']   = array();
		$GLOBALS['mlab_core_options']    = array();
		Client::flush_cache();
	}

	private function queue_catalog( array $products ): void {
		$GLOBALS['mlab_core_http_queue'][] = array(
			'code' => 200,
			'body' => wp_json_encode( array( 'products' => $products ) ),
		);
	}

	private function product( string $slug = 'mlab-seo', string $main_file = 'mlab-seo/mlab-seo.php' ): array {
		return array(
			'slug'        => $slug,
			'name'        => 'SEO',
			'description' => 'Beschreibung',
			'version'     => '1.0.0',
			'main_file'   => $main_file,
			'unlocked'    => true,
		);
	}

	public function test_success_stores_and_returns_products(): void {
		$this->queue_catalog( array( $this->product() ) );
		$result = ( new Client() )->catalog();
		$this->assertCount( 1, $result['products'] );
		$this->assertSame( '', $result['error'] );
		$this->assertGreaterThan( 0, $result['fetched_at'] );
		$this->assertSame( 'https://momentumlab.ch/wp-json/mlab-ls/v1/catalog', $GLOBALS['mlab_core_http_log'][0]['url'] );
		$this->assertArrayHasKey( 'mlab_core_catalog', $GLOBALS['mlab_core_options'] );
	}

	public function test_second_call_within_ttl_makes_no_request(): void {
		$this->queue_catalog( array( $this->product() ) );
		$client = new Client();
		$client->catalog();
		$again = $client->catalog();
		$this->assertCount( 1, $GLOBALS['mlab_core_http_log'] );
		$this->assertCount( 1, $again['products'] );
	}

	public function test_refresh_forces_a_request(): void {
		$this->queue_catalog( array( $this->product() ) );
		$this->queue_catalog( array( $this->product(), $this->product( 'mlab-two', 'mlab-two/mlab-two.php' ) ) );
		$client = new Client();
		$client->catalog();
		$result = $client->catalog( true );
		$this->assertCount( 2, $GLOBALS['mlab_core_http_log'] );
		$this->assertCount( 2, $result['products'] );
	}

	public function test_invalid_license_discards_catalog_and_shows_server_message(): void {
		$this->queue_catalog( array( $this->product() ) );
		$client = new Client();
		$client->catalog();
		$GLOBALS['mlab_core_http_queue'][] = array(
			'code' => 401,
			'body' => wp_json_encode(
				array(
					'code'    => 'mlab_ls_invalid_license',
					'message' => 'Der Lizenzschlüssel ist ungültig.',
					'data'    => array( 'status' => 401 ),
				)
			),
		);
		$result = $client->catalog( true );
		$this->assertSame( array(), $result['products'] );
		$this->assertSame( 'Der Lizenzschlüssel ist ungültig.', $result['error'] );
		$this->assertNull( $client->product( 'mlab-seo' ) );
	}

	public function test_timeout_keeps_old_products_with_default_message(): void {
		$this->queue_catalog( array( $this->product() ) );
		$client = new Client();
		$client->catalog();
		$result = $client->catalog( true );
		$this->assertCount( 1, $result['products'] );
		$this->assertStringContainsString( 'nicht erreichbar', $result['error'] );
		$this->assertNotNull( $client->product( 'mlab-seo' ) );
	}

	public function test_failed_refresh_is_not_retried_on_every_call(): void {
		$client = new Client();
		$client->catalog();
		$client->catalog();
		$this->assertCount( 1, $GLOBALS['mlab_core_http_log'] );
	}

	public function test_server_error_without_message_uses_default_text(): void {
		$GLOBALS['mlab_core_http_queue'][] = array(
			'code' => 500,
			'body' => '<html>boom</html>',
		);
		$result = ( new Client() )->catalog();
		$this->assertStringContainsString( 'nicht erreichbar', $result['error'] );
	}

	public function test_headers_without_key_have_no_license_header(): void {
		$args = ( new Client() )->request_args();
		$this->assertSame( 5, $args['timeout'] );
		$this->assertArrayNotHasKey( 'X-MLAB-License', $args['headers'] );
		$this->assertSame( 'https://site.test', $args['headers']['X-MLAB-Site'] );
	}

	public function test_headers_with_key_send_license(): void {
		$GLOBALS['mlab_core_options']['mlab_core_license_key'] = 'MLAB-GOOD';
		$args = ( new Client() )->request_args();
		$this->assertSame( 'MLAB-GOOD', $args['headers']['X-MLAB-License'] );
	}

	public function test_product_with_unsafe_main_file_is_dropped(): void {
		$this->queue_catalog( array( $this->product(), $this->product( 'evil', '../x/y.php' ) ) );
		$result = ( new Client() )->catalog();
		$this->assertSame( array( 'mlab-seo' ), array_column( $result['products'], 'slug' ) );
		$this->assertNull( ( new Client() )->product( 'evil' ) );
	}

	public function test_update_info_returns_decoded_json(): void {
		$GLOBALS['mlab_core_http_queue'][] = array(
			'code' => 200,
			'body' => wp_json_encode( array( 'download_url' => 'https://momentumlab.ch/dl' ) ),
		);
		$info = ( new Client() )->update_info( 'mlab-seo' );
		$this->assertSame( 'https://momentumlab.ch/dl', $info['download_url'] );
		$this->assertSame( 'https://momentumlab.ch/wp-json/mlab-ls/v1/update/mlab-seo', $GLOBALS['mlab_core_http_log'][0]['url'] );
	}

	public function test_update_info_returns_error_on_failure(): void {
		$GLOBALS['mlab_core_http_queue'][] = array(
			'code' => 403,
			'body' => wp_json_encode( array( 'message' => 'Zu viele Websites.' ) ),
		);
		$info = ( new Client() )->update_info( 'mlab-seo' );
		$this->assertInstanceOf( WP_Error::class, $info );
		$this->assertSame( 'Zu viele Websites.', $info->message );
	}

	public function test_update_url_encodes_the_slug(): void {
		$this->assertSame( 'https://momentumlab.ch/wp-json/mlab-ls/v1/update/mlab-seo', ( new Client() )->update_url( 'mlab-seo' ) );
		$this->assertSame( 'https://momentumlab.ch/wp-json/mlab-ls/v1/update/a%2Fb', ( new Client() )->update_url( 'a/b' ) );
	}

	public function test_apply_request_args_merges_timeout_and_headers(): void {
		$GLOBALS['mlab_core_options']['mlab_core_license_key'] = 'MLAB-GOOD';
		$args = ( new Client() )->apply_request_args(
			array(
				'timeout' => 99,
				'sslverify' => true,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		$this->assertSame( 5, $args['timeout'] );
		$this->assertTrue( $args['sslverify'] );
		$this->assertSame( 'application/json', $args['headers']['Accept'] );
		$this->assertSame( 'MLAB-GOOD', $args['headers']['X-MLAB-License'] );
		$this->assertSame( 'https://site.test', $args['headers']['X-MLAB-Site'] );
	}

	public function test_stored_option_is_memoised_until_the_client_writes(): void {
		$this->queue_catalog( array( $this->product() ) );
		$client = new Client();
		$client->catalog();
		$this->assertNotNull( $client->product( 'mlab-seo' ) );
		$GLOBALS['mlab_core_options']['mlab_core_catalog']['products'] = array();
		$this->assertNotNull( $client->product( 'mlab-seo' ) );

		$this->queue_catalog( array( $this->product( 'mlab-two', 'mlab-two/mlab-two.php' ) ) );
		$client->catalog( true );
		$this->assertNull( $client->product( 'mlab-seo' ) );
		$this->assertNotNull( $client->product( 'mlab-two' ) );
	}
}
