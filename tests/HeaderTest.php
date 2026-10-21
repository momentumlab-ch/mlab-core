<?php
use PHPUnit\Framework\TestCase;

final class HeaderTest extends TestCase {

	/**
	 * Version aus dem Plugin-Header; die anderen Angaben müssen dazu passen.
	 */
	private function header_version(): string {
		$main = file_get_contents( dirname( __DIR__ ) . '/mlab-core.php' );
		$this->assertSame( 1, preg_match( '/^[ *]*Version:\s*(\S+)/m', $main, $header ) );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $header[1] );
		return $header[1];
	}

	public function test_header_version_matches_constant(): void {
		$main = file_get_contents( dirname( __DIR__ ) . '/mlab-core.php' );
		$this->assertSame( 1, preg_match( "/define\( 'MLAB_CORE_VERSION', '([^']+)' \)/", $main, $constant ) );
		$this->assertSame( $this->header_version(), $constant[1] );
	}

	public function test_readme_stable_tag_matches(): void {
		$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
		$this->assertStringContainsString( 'Stable tag: ' . $this->header_version(), $readme );
	}

	public function test_changelog_has_current_version(): void {
		$changelog = file_get_contents( dirname( __DIR__ ) . '/CHANGELOG.md' );
		$this->assertStringContainsString( '## ' . $this->header_version(), $changelog );
	}
}
