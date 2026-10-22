<?php
/**
 * Pure helpers of the health check: rating and which problems are new (for the alarm).
 *
 * @package mlab-core
 */

namespace MomentumLab\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rates check results and decides when to send an alarm.
 */
final class Health {

	/**
	 * Warn this many days before the certificate expires; below a third of it the check is red.
	 */
	public const SSL_WARN_DAYS = 21;

	/**
	 * The same problem is reported again at most once per this many seconds.
	 */
	public const REPEAT = 86400;

	/**
	 * State of the certificate by days left.
	 *
	 * @param int|null $days Days until expiry, null when unknown.
	 */
	public static function ssl_state( ?int $days ): string {
		if ( null === $days ) {
			return 'warn';
		}
		if ( $days <= intdiv( self::SSL_WARN_DAYS, 3 ) ) {
			return 'bad';
		}
		return $days <= self::SSL_WARN_DAYS ? 'warn' : 'good';
	}

	/**
	 * Checks that are red now and were not red before, or were last reported more than REPEAT ago.
	 *
	 * @param array<string,array{state:string}> $now      Current results.
	 * @param array<string,int>                 $reported Check => time of the last alarm.
	 * @param int                               $time     Now.
	 * @return list<string> Check keys to report.
	 */
	public static function to_report( array $now, array $reported, int $time ): array {
		$keys = array();
		foreach ( $now as $key => $check ) {
			if ( 'bad' === ( $check['state'] ?? '' ) && ( ! isset( $reported[ $key ] ) || $time - (int) $reported[ $key ] >= self::REPEAT ) ) {
				$keys[] = (string) $key;
			}
		}
		return $keys;
	}

	/**
	 * Overall state: the worst of all checks.
	 *
	 * @param array<string,array{state:string}> $checks Results.
	 */
	public static function overall( array $checks ): string {
		$states = array_column( $checks, 'state' );
		if ( in_array( 'bad', $states, true ) ) {
			return 'bad';
		}
		return in_array( 'warn', $states, true ) ? 'warn' : 'good';
	}

	/**
	 * Number of PHP fatal errors from the last 24 hours in the tail of a debug.log.
	 *
	 * @param string $tail Last part of the log.
	 * @param int    $time Now.
	 */
	public static function fatal_count( string $tail, int $time ): int {
		$count = 0;
		$since = $time - 86400;
		foreach ( preg_split( '/\R/', $tail ) ?: array() as $line ) { // phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- preg_split returns false only on a bad pattern.
			if ( ! preg_match( '/^\[([^\]]+)\] PHP (Fatal error|Parse error)/', $line, $m ) ) {
				continue;
			}
			$at = strtotime( $m[1] );
			if ( false !== $at && $at >= $since ) {
				++$count;
			}
		}
		return $count;
	}
}
