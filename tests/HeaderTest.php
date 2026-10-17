<?php
use PHPUnit\Framework\TestCase;

final class HeaderTest extends TestCase {

	public function test_header_version_matches_constant(): void {
		$main = file_get_contents( dirname( __DIR__ ) . '/mlab-core.php' );
		$this->assertSame( 1, preg_match( '/^[ *]*Version:\s*(\S+)/m', $main, $header ) );
		$this->assertSame( 1, preg_match( "/define\( 'MLAB_CORE_VERSION', '([^']+)' \)/", $main, $constant ) );
		$this->assertSame( '0.1.0', $header[1] );
		$this->assertSame( '0.1.0', $constant[1] );
	}

	public function test_readme_stable_tag_matches(): void {
		$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
		$this->assertStringContainsString( 'Stable tag: 0.1.0', $readme );
	}
}
