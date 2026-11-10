<?php
use MomentumLab\Core\Client;
use PHPUnit\Framework\TestCase;

/**
 * Stand-in for mlab_license(): a fixed state, records refresh calls.
 */
final class FakeLicense {

	public array $state;
	public array $refreshes = array();

	public function __construct( array $state ) {
		$this->state = $state;
	}

	public function refresh( bool $force = false ): array {
		$this->refreshes[] = $force;
		return $this->state;
	}

	public function products(): array {
		return $this->state['products'];
	}

	public function server_url(): string {
		return 'https://momentumlab.ch';
	}

	public function request_args(): array {
		return array(
			'timeout' => 5,
			'headers' => array( 'X-MLAB-Site' => 'https://site.test/', 'X-MLAB-License' => 'MLAB-TEST' ),
		);
	}
}

final class ClientTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['mlab_core_http_queue'] = array();
		$GLOBALS['mlab_core_http_log']   = array();
	}

	private function product( string $slug, string $main_file, bool $unlocked = true ): array {
		return array(
			'slug'        => $slug,
			'name'        => $slug,
			'description' => '',
			'version'     => '1.0.0',
			'main_file'   => $main_file,
			'unlocked'    => $unlocked,
		);
	}

	private function client( string $status = 'valid', string $message = '' ): array {
		$license = new FakeLicense(
			array(
				'status'     => $status,
				'message'    => $message,
				'fetched_at' => 100,
				'products'   => array(
					$this->product( 'mlab-seo', 'mlab-seo/mlab-seo.php' ),
					$this->product( 'mlab-seo-pro', '' ),
					$this->product( 'evil', '../../wp-config.php' ),
					$this->product( 'mlab-hub', 'mlab-hub/mlab-hub.php', false ),
				),
			)
		);
		return array( new Client( $license ), $license );
	}

	public function test_catalog_lists_only_installable_plugins(): void {
		list( $client ) = $this->client();
		$catalog        = $client->catalog();
		$this->assertSame( array( 'mlab-seo', 'mlab-hub' ), array_column( $catalog['products'], 'slug' ) );
		$this->assertSame( '', $catalog['error'] );
		$this->assertSame( 100, $catalog['fetched_at'] );
	}

	public function test_unlocks_are_unlocked_products_without_plugin(): void {
		list( $client ) = $this->client();
		$this->assertSame( array( 'mlab-seo-pro' ), array_column( $client->unlocks(), 'slug' ) );
	}

	public function test_refresh_is_passed_through(): void {
		list( $client, $license ) = $this->client();
		$client->catalog();
		$client->catalog( true );
		$this->assertSame( array( false, true ), $license->refreshes );
	}

	public function test_invalid_license_shows_server_message(): void {
		list( $client ) = $this->client( 'invalid', 'Expired.' );
		$this->assertSame( 'Expired.', $client->catalog()['error'] );
	}

	public function test_product_lookup_never_returns_unsafe_entries(): void {
		list( $client ) = $this->client();
		$this->assertNotNull( $client->product( 'mlab-seo' ) );
		$this->assertNull( $client->product( 'evil' ) );
		$this->assertNull( $client->product( 'mlab-seo-pro' ) );
	}

	public function test_update_url_encodes_the_slug(): void {
		list( $client ) = $this->client();
		$this->assertSame( 'https://momentumlab.ch/wp-json/mlab-ls/v1/update/a%2Fb', $client->update_url( 'a/b' ) );
	}

	public function test_update_info_sends_license_headers_and_decodes_json(): void {
		list( $client )                  = $this->client();
		$GLOBALS['mlab_core_http_queue'][] = array( 'code' => 200, 'body' => '{"download_url":"https://x"}' );
		$this->assertSame( 'https://x', $client->update_info( 'mlab-seo' )['download_url'] );
		$this->assertSame( 'MLAB-TEST', $GLOBALS['mlab_core_http_log'][0]['args']['headers']['X-MLAB-License'] );
	}

	public function test_update_info_error_uses_server_message(): void {
		list( $client )                  = $this->client();
		$GLOBALS['mlab_core_http_queue'][] = array( 'code' => 403, 'body' => '{"message":"Not unlocked."}' );
		$this->assertSame( 'Not unlocked.', $client->update_info( 'mlab-seo' )->message );
	}

	public function test_apply_request_args_merges_headers(): void {
		list( $client ) = $this->client();
		$args           = $client->apply_request_args( array( 'headers' => array( 'Accept' => 'json' ) ) );
		$this->assertSame( 5, $args['timeout'] );
		$this->assertSame( 'json', $args['headers']['Accept'] );
		$this->assertSame( 'MLAB-TEST', $args['headers']['X-MLAB-License'] );
	}
}
