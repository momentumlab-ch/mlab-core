<?php
use MomentumLab\Core\Row;
use PHPUnit\Framework\TestCase;

final class RowTest extends TestCase {

	/**
	 * @return array<string,array{bool,bool,bool,string}>
	 */
	public function state_provider(): array {
		return array(
			'unlocked, not installed' => array( true, false, false, 'install' ),
			'installed, inactive'     => array( true, true, false, 'activate' ),
			'active'                  => array( true, true, true, 'deactivate' ),
			'locked, not installed'   => array( false, false, false, 'request' ),
			'locked, installed'       => array( false, true, false, 'activate' ),
			'locked, active'          => array( false, true, true, 'deactivate' ),
		);
	}

	/**
	 * @dataProvider state_provider
	 */
	public function test_state( bool $unlocked, bool $installed, bool $active, string $expected ): void {
		$product = array( 'unlocked' => $unlocked );
		$this->assertSame( $expected, Row::state( $product, $installed, $active ) );
	}

	public function test_loses_updates_only_for_installed_locked_products(): void {
		$this->assertTrue( Row::loses_updates( array( 'unlocked' => false ), true ) );
		$this->assertFalse( Row::loses_updates( array( 'unlocked' => true ), true ) );
		$this->assertFalse( Row::loses_updates( array( 'unlocked' => false ), false ) );
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public function main_file_provider(): array {
		return array(
			'valid'          => array( 'mlab-seo/mlab-seo.php', 'mlab-seo/mlab-seo.php' ),
			'underscore'     => array( 'a_b-1/c_d.php', 'a_b-1/c_d.php' ),
			'parent'         => array( '../evil/x.php', '' ),
			'absolute'       => array( '/abs/x.php', '' ),
			'too deep'       => array( 'a/b/c.php', '' ),
			'no folder'      => array( 'x.php', '' ),
			'wrong ext'      => array( 'a/b.txt', '' ),
			'dots in folder' => array( '../x.php', '' ),
			'uppercase'      => array( 'A/b.php', '' ),
			'empty'          => array( '', '' ),
			'trailing nl'    => array( "a/b.php\n", '' ),
		);
	}

	/**
	 * @dataProvider main_file_provider
	 */
	public function test_safe_main_file( string $input, string $expected ): void {
		$this->assertSame( $expected, Row::safe_main_file( $input ) );
	}

	/**
	 * @return array<string,array{int,bool}>
	 */
	public function error_provider(): array {
		return array(
			'401'     => array( 401, true ),
			'403'     => array( 403, true ),
			'500'     => array( 500, false ),
			'429'     => array( 429, false ),
			'503'     => array( 503, false ),
			'offline' => array( 0, false ),
		);
	}

	/**
	 * @dataProvider error_provider
	 */
	public function test_catalog_after_error( int $status, bool $discarded ): void {
		$cached = array( array( 'slug' => 'mlab-seo' ) );
		$this->assertSame( $discarded ? array() : $cached, Row::catalog_after_error( $cached, $status ) );
	}
}
