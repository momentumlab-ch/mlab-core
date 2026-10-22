<?php
/**
 * Tests for the health rating and alarm decision.
 */

use MomentumLab\Core\Health;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/inc/class-health.php';

final class HealthTest extends TestCase {

	public function test_ssl_state(): void {
		$this->assertSame( 'good', Health::ssl_state( 60 ) );
		$this->assertSame( 'warn', Health::ssl_state( 14 ) );
		$this->assertSame( 'bad', Health::ssl_state( 5 ) );
		$this->assertSame( 'warn', Health::ssl_state( null ) );
	}

	public function test_report_only_new_or_repeated_after_a_day(): void {
		$now    = array(
			'home' => array( 'state' => 'bad' ),
			'ssl'  => array( 'state' => 'bad' ),
			'upd'  => array( 'state' => 'warn' ),
		);
		$result = Health::to_report( $now, array( 'ssl' => 1000 ), 1000 + 3600 );
		$this->assertSame( array( 'home' ), $result );
		$this->assertSame( array( 'home', 'ssl' ), Health::to_report( $now, array( 'ssl' => 1000 ), 1000 + Health::REPEAT ) );
	}

	public function test_overall(): void {
		$this->assertSame( 'good', Health::overall( array( array( 'state' => 'good' ) ) ) );
		$this->assertSame( 'warn', Health::overall( array( array( 'state' => 'good' ), array( 'state' => 'warn' ) ) ) );
		$this->assertSame( 'bad', Health::overall( array( array( 'state' => 'warn' ), array( 'state' => 'bad' ) ) ) );
	}

	public function test_fatal_count_last_day_only(): void {
		$time = strtotime( '2026-10-22 08:00:00 UTC' );
		$log  = "[20-Oct-2026 10:00:00 UTC] PHP Fatal error:  old\n"
			. "[22-Oct-2026 07:00:00 UTC] PHP Warning:  harmless\n"
			. "[22-Oct-2026 07:30:00 UTC] PHP Fatal error:  Uncaught Error\n"
			. "[21-Oct-2026 09:00:00 UTC] PHP Parse error:  syntax\n";
		$this->assertSame( 2, Health::fatal_count( $log, $time ) );
	}
}
