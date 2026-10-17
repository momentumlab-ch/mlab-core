<?php
/**
 * Updates for the plugins managed by the core, served by the license server.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Client;
use MomentumLab\Core\Row;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'mlab_core_register_managed_updates' );
add_filter( 'mlab_core_manages_plugin', 'mlab_core_manages_plugin', 10, 2 );

/**
 * Registers one update checker per installed product of the stored catalog. No HTTP here.
 */
function mlab_core_register_managed_updates(): void {
	$client = new Client();
	foreach ( $client->stored_products() as $product ) {
		$slug = (string) ( $product['slug'] ?? '' );
		$file = Row::safe_main_file( (string) ( $product['main_file'] ?? '' ) );
		if ( '' === $slug || '' === $file || ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
			continue;
		}
		$checker = PucFactory::buildUpdateChecker(
			mlab_core_server_url() . '/wp-json/mlab-ls/v1/update/' . rawurlencode( $slug ),
			WP_PLUGIN_DIR . '/' . $file,
			$slug
		);
		$checker->addHttpRequestArgFilter(
			static function ( $args ) use ( $client ) {
				$core            = $client->request_args();
				$args            = is_array( $args ) ? $args : array();
				$args['timeout'] = $core['timeout'];
				$args['headers'] = array_merge( (array) ( $args['headers'] ?? array() ), $core['headers'] );
				return $args;
			}
		);
	}
}

/**
 * Tells a managed plugin that the core delivers its updates.
 *
 * @param bool   $manages Current answer.
 * @param string $slug    Plugin slug.
 */
function mlab_core_manages_plugin( $manages, $slug ): bool {
	if ( $manages ) {
		return true;
	}
	return null !== ( new Client() )->product( (string) $slug );
}
