<?php
/**
 * Pure catalog logic: row states and input checks. No WordPress calls.
 *
 * @package mlab-core
 */

namespace MomentumLab\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Derives what the list shows for one product.
 */
final class Row {

	/**
	 * State of a product: install, activate, deactivate or request.
	 *
	 * An installed plugin is never forced off, even when it is no longer unlocked.
	 *
	 * @param array $product   Catalog entry.
	 * @param bool  $installed Plugin files are on this site.
	 * @param bool  $active    Plugin is active.
	 */
	public static function state( array $product, bool $installed, bool $active ): string {
		if ( $active ) {
			return 'deactivate';
		}
		if ( $installed ) {
			return 'activate';
		}
		return empty( $product['unlocked'] ) ? 'request' : 'install';
	}

	/**
	 * True when the plugin is installed but its license no longer covers updates.
	 *
	 * @param array $product   Catalog entry.
	 * @param bool  $installed Plugin files are on this site.
	 */
	public static function loses_updates( array $product, bool $installed ): bool {
		return $installed && empty( $product['unlocked'] );
	}

	/**
	 * Returns the main file only if it is exactly "folder/file.php" of [a-z0-9_-], else ''.
	 *
	 * @param string $main_file Value from the server.
	 */
	public static function safe_main_file( string $main_file ): string {
		return 1 === preg_match( '/\A[a-z0-9_-]+\/[a-z0-9_-]+\.php\z/', $main_file ) ? $main_file : '';
	}

	/**
	 * Catalog to keep after a failed request: nothing on 401/403, else the cached one.
	 *
	 * @param array $cached Stored products.
	 * @param int   $status HTTP status, 0 if the server was unreachable.
	 */
	public static function catalog_after_error( array $cached, int $status ): array {
		return in_array( $status, array( 401, 403 ), true ) ? array() : $cached;
	}
}
