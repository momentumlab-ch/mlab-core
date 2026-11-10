<?php
/**
 * Pure rules of the license state: no WordPress calls, no HTTP.
 *
 * @package mlab-license-client
 */

namespace MomentumLab\LicenseClient\V1;

defined( 'ABSPATH' ) || exit;

/**
 * Turns server answers into a stored state and answers "is this product unlocked?".
 */
final class State {

	public const NONE        = 'none';
	public const VALID       = 'valid';
	public const INVALID     = 'invalid';
	public const UNREACHABLE = 'unreachable';

	/**
	 * A server outage keeps the last good answer this long.
	 */
	public const GRACE = 7 * 86400;

	/**
	 * Age after which the catalog is fetched again.
	 */
	public const TTL = 43200;

	/**
	 * Wait after a failed request before the next try.
	 */
	public const RETRY = 300;

	/**
	 * Minimum seconds between two checks triggered by the refresh route.
	 */
	public const PING_INTERVAL = 60;

	/**
	 * Empty state with all keys.
	 *
	 * @return array{status:string,message:string,products:array,fetched_at:int,failed_at:int,last_ok:int}
	 */
	public static function blank(): array {
		return array(
			'status'     => self::NONE,
			'message'    => '',
			'products'   => array(),
			'fetched_at' => 0,
			'failed_at'  => 0,
			'last_ok'    => 0,
		);
	}

	/**
	 * Stored value with all keys present and the right types.
	 *
	 * @param mixed $stored Value from the database.
	 */
	public static function normalize( $stored ): array {
		$state = array_merge( self::blank(), is_array( $stored ) ? $stored : array() );
		foreach ( array( 'fetched_at', 'failed_at', 'last_ok' ) as $time ) {
			$state[ $time ] = (int) $state[ $time ];
		}
		$state['products'] = is_array( $state['products'] ) ? $state['products'] : array();
		return $state;
	}

	/**
	 * New state after an answer of GET /catalog.
	 *
	 * @param array  $state   Current state.
	 * @param int    $code    HTTP status, 0 when the server was not reached.
	 * @param mixed  $body    Decoded JSON body.
	 * @param int    $now     Current Unix time.
	 * @param string $fallback Message when the server sent none.
	 */
	public static function after_response( array $state, int $code, $body, int $now, string $fallback ): array {
		$message = is_array( $body ) && isset( $body['message'] ) ? (string) $body['message'] : '';
		if ( 200 === $code && is_array( $body ) && isset( $body['products'] ) && is_array( $body['products'] ) ) {
			return array_merge(
				$state,
				array(
					'status'     => self::VALID,
					'message'    => '',
					'products'   => self::clean_products( $body['products'] ),
					'fetched_at' => $now,
					'failed_at'  => 0,
					'last_ok'    => $now,
				)
			);
		}
		$state['failed_at'] = $now;
		$state['message']   = '' !== $message ? $message : $fallback;
		if ( 401 === $code || 403 === $code ) {
			// The list stays visible (e.g. "on request" cards), but nothing is unlocked any more.
			$state['status']   = self::INVALID;
			$state['products'] = array_map(
				static fn( array $product ): array => array_merge( $product, array( 'unlocked' => false ) ),
				$state['products']
			);
			return $state;
		}
		$state['status'] = self::UNREACHABLE;
		return $state;
	}

	/**
	 * Whether the license unlocks a product. Never makes a request.
	 *
	 * During an outage the last good answer counts for GRACE seconds.
	 *
	 * @param array  $state State.
	 * @param string $slug  Product or unlock slug, e.g. "mlab-seo-pro".
	 * @param int    $now   Current Unix time.
	 */
	public static function unlocked( array $state, string $slug, int $now ): bool {
		$usable = self::VALID === $state['status']
			|| ( self::UNREACHABLE === $state['status'] && $state['last_ok'] > $now - self::GRACE );
		if ( ! $usable ) {
			return false;
		}
		foreach ( $state['products'] as $product ) {
			if ( $slug === $product['slug'] ) {
				return $product['unlocked'];
			}
		}
		return false;
	}

	/**
	 * Whether the catalog should be fetched again.
	 *
	 * @param array $state State.
	 * @param int   $now   Current Unix time.
	 */
	public static function due( array $state, int $now ): bool {
		if ( $state['failed_at'] > $state['fetched_at'] ) {
			return $now - $state['failed_at'] >= self::RETRY;
		}
		return $now - $state['fetched_at'] >= self::TTL;
	}

	/**
	 * Whether the refresh route may trigger a check: at most one per PING_INTERVAL.
	 *
	 * @param mixed $last Stored time of the last trigger; anything but a positive number counts as never.
	 * @param int   $now  Current Unix time.
	 */
	public static function ping_allowed( $last, int $now ): bool {
		if ( ! is_numeric( $last ) || (int) $last <= 0 ) {
			return true;
		}
		return $now - (int) $last >= self::PING_INTERVAL;
	}

	/**
	 * Keeps only well-formed products with plain values.
	 *
	 * @param array $products Products from the server.
	 * @return array<int,array{slug:string,name:string,description:string,version:string,main_file:string,unlocked:bool}>
	 */
	private static function clean_products( array $products ): array {
		$clean = array();
		foreach ( $products as $product ) {
			if ( ! is_array( $product ) || 1 !== preg_match( '/^[a-z0-9-]{1,100}$/', (string) ( $product['slug'] ?? '' ) ) ) {
				continue;
			}
			$clean[] = array(
				'slug'        => (string) $product['slug'],
				'name'        => (string) ( $product['name'] ?? '' ),
				'description' => (string) ( $product['description'] ?? '' ),
				'version'     => (string) ( $product['version'] ?? '' ),
				'main_file'   => (string) ( $product['main_file'] ?? '' ),
				'unlocked'    => ! empty( $product['unlocked'] ),
			);
		}
		return $clean;
	}
}
