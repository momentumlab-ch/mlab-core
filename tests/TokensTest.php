<?php
use MomentumLab\Core\Tokens;
use PHPUnit\Framework\TestCase;

final class TokensTest extends TestCase {

	private function key(): string {
		return Tokens::key( str_repeat( 'a', 64 ), str_repeat( 'b', 64 ) );
	}

	public function test_key_needs_real_salts(): void {
		$this->assertSame( '', Tokens::key( 'put your unique phrase here', str_repeat( 'b', 64 ) ) );
		$this->assertSame( '', Tokens::key( 'short', str_repeat( 'b', 64 ) ) );
		$this->assertSame( 32, strlen( $this->key() ) );
	}

	public function test_roundtrip_and_no_plaintext(): void {
		$token  = 'github_pat_' . str_repeat( 'X1', 20 );
		$cipher = Tokens::encrypt( $token, $this->key() );
		$this->assertNotSame( '', $cipher );
		$this->assertStringNotContainsString( $token, $cipher );
		$this->assertSame( $token, Tokens::decrypt( $cipher, $this->key() ) );
		$this->assertNotSame( $cipher, Tokens::encrypt( $token, $this->key() ), 'Jede Verschlüsselung hat eine eigene Nonce.' );
	}

	public function test_other_key_cannot_decrypt(): void {
		$cipher = Tokens::encrypt( 'github_pat_' . str_repeat( 'Y', 40 ), $this->key() );
		$this->assertSame( '', Tokens::decrypt( $cipher, Tokens::key( str_repeat( 'c', 64 ), str_repeat( 'd', 64 ) ) ) );
		$this->assertSame( '', Tokens::decrypt( 'sodium:kaputt', $this->key() ) );
	}

	public function test_scope_normalizes(): void {
		$this->assertSame( 'momentumlab-ch/sid-events', Tokens::scope( ' https://github.com/MomentumLab-ch/sid-events.git ' ) );
		$this->assertSame( 'momentumlab-ch', Tokens::scope( 'momentumlab-ch' ) );
		$this->assertSame( '', Tokens::scope( '../etc' ) );
		$this->assertSame( '', Tokens::scope( 'a/b/c' ) );
	}

	public function test_pick_prefers_repo_over_owner(): void {
		$entries = array(
			array( 'scope' => 'momentumlab-ch', 'cipher' => 'org' ),
			array( 'scope' => 'momentumlab-ch/sid-events', 'cipher' => 'repo' ),
		);
		$this->assertSame( 'repo', Tokens::pick( $entries, 'momentumlab-ch/sid-events' )['cipher'] );
		$this->assertSame( 'org', Tokens::pick( $entries, 'momentumlab-ch/mlab-core' )['cipher'] );
		$this->assertNull( Tokens::pick( $entries, 'andere-firma/repo' ) );
	}

	public function test_upsert_replaces_same_scope(): void {
		$entries = Tokens::upsert( array( array( 'scope' => 'momentumlab-ch', 'hint' => 'alt1' ) ), array( 'scope' => 'momentumlab-ch', 'hint' => 'neu1' ) );
		$this->assertCount( 1, $entries );
		$this->assertSame( 'neu1', $entries[0]['hint'] );
		$this->assertSame( array(), Tokens::without( $entries, 'momentumlab-ch' ) );
	}

	public function test_looks_valid(): void {
		$this->assertTrue( Tokens::looks_valid( 'github_pat_' . str_repeat( 'A', 30 ) ) );
		$this->assertTrue( Tokens::looks_valid( 'ghp_' . str_repeat( 'a', 36 ) ) );
		$this->assertFalse( Tokens::looks_valid( 'hallo welt' ) );
	}
}
