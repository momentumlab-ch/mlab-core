<?php
use MomentumLab\Core\Client;
use PHPUnit\Framework\TestCase;

final class ProductsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['mlab_core_http_queue'] = array();
		$GLOBALS['mlab_core_http_log']   = array();
		$GLOBALS['mlab_core_options']    = array();
		Client::flush_cache();
	}

	private function store( array $products ): void {
		$GLOBALS['mlab_core_options']['mlab_core_catalog'] = array(
			'products'   => $products,
			'error'      => '',
			'fetched_at' => time(),
		);
	}

	private function product( string $slug, $unlocked ): array {
		return array(
			'slug'      => $slug,
			'main_file' => $slug . '/' . $slug . '.php',
			'unlocked'  => $unlocked,
		);
	}

	public function test_unlocked_product_is_unlocked(): void {
		$this->store( array( $this->product( 'mlab-social-wall-pro', true ) ) );
		$this->assertTrue( mlab_core_product_unlocked( 'mlab-social-wall-pro' ) );
	}

	public function test_product_in_catalog_but_locked_is_not_unlocked(): void {
		$this->store( array( $this->product( 'mlab-social-wall-pro', false ) ) );
		$this->assertFalse( mlab_core_product_unlocked( 'mlab-social-wall-pro' ) );
	}

	public function test_product_without_flag_is_not_unlocked(): void {
		$this->store( array( array( 'slug' => 'mlab-social-wall-pro' ) ) );
		$this->assertFalse( mlab_core_product_unlocked( 'mlab-social-wall-pro' ) );
	}

	public function test_unknown_product_or_empty_catalog_is_not_unlocked(): void {
		$this->assertFalse( mlab_core_product_unlocked( 'mlab-social-wall-pro' ) );
		Client::flush_cache();
		$this->store( array( $this->product( 'mlab-seo', true ) ) );
		$this->assertFalse( mlab_core_product_unlocked( 'mlab-social-wall-pro' ) );
	}

	public function test_check_makes_no_request(): void {
		$this->store( array( $this->product( 'mlab-social-wall-pro', true ) ) );
		mlab_core_product_unlocked( 'mlab-social-wall-pro' );
		mlab_core_product_unlocked( 'mlab-seo' );
		$this->assertSame( array(), $GLOBALS['mlab_core_http_log'] );
	}

	public function test_invalid_license_locks_after_next_fetch(): void {
		$this->store( array( $this->product( 'mlab-social-wall-pro', true ) ) );
		$GLOBALS['mlab_core_http_queue'][] = array(
			'code' => 401,
			'body' => wp_json_encode( array( 'message' => 'Der Lizenzschlüssel ist ungültig.' ) ),
		);
		( new Client() )->catalog( true );
		$this->assertFalse( mlab_core_product_unlocked( 'mlab-social-wall-pro' ) );
	}
}
