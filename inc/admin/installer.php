<?php
/**
 * Installs a product from the license server with the WordPress upgrader.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Client;
use MomentumLab\Core\Row;

defined( 'ABSPATH' ) || exit;

/**
 * Standard error for a failed installation, with an optional detail.
 *
 * @param string $detail Technical detail, may be empty.
 */
function mlab_core_install_error( string $detail = '' ): WP_Error {
	$message = __( 'Die Installation hat nicht geklappt. Bitte versuche es erneut oder melde dich bei MomentumLab.', 'mlab-core' );
	if ( '' !== $detail ) {
		$message .= ' (' . $detail . ')';
	}
	return new WP_Error( 'mlab_core_install_failed', $message );
}

/**
 * Loads the WordPress files the upgrader needs.
 */
function mlab_core_load_upgrader(): void {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
}

/**
 * Checks that installing is possible: file changes allowed, folder still free.
 *
 * @param string $main_file Safe main file "folder/file.php".
 * @return true|WP_Error
 */
function mlab_core_can_install( string $main_file ) {
	if ( ! wp_is_file_mod_allowed( 'install_plugins' ) ) {
		return mlab_core_install_error( __( 'Änderungen an Dateien sind auf dieser Website gesperrt', 'mlab-core' ) );
	}
	if ( file_exists( WP_PLUGIN_DIR . '/' . dirname( $main_file ) ) ) {
		return new WP_Error( 'mlab_core_exists', __( 'Das Plugin ist bereits vorhanden. Bitte aktiviere es in der Liste.', 'mlab-core' ) );
	}
	return true;
}

/**
 * Runs the upgrader for a download URL and returns the first error, if any.
 *
 * @param string $url Download URL.
 * @return true|WP_Error
 */
function mlab_core_run_upgrader( string $url ) {
	mlab_core_load_upgrader();
	$skin     = new WP_Ajax_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->install( $url );
	if ( is_wp_error( $result ) ) {
		return mlab_core_install_error( $result->get_error_message() );
	}
	if ( $skin->get_errors()->has_errors() ) {
		return mlab_core_install_error( (string) $skin->get_error_messages() );
	}
	return true === $result ? true : mlab_core_install_error();
}

/**
 * Installs and activates one product from the stored catalog.
 *
 * @param array $product Catalog entry.
 * @return true|WP_Error
 */
function mlab_core_install_product( array $product ) {
	$main_file = Row::safe_main_file( (string) ( $product['main_file'] ?? '' ) );
	$slug      = (string) ( $product['slug'] ?? '' );
	if ( '' === $main_file || '' === $slug ) {
		return mlab_core_install_error();
	}
	$check = mlab_core_can_install( $main_file );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	$info = ( new Client() )->update_info( $slug );
	if ( is_wp_error( $info ) ) {
		return $info;
	}
	$url = (string) ( $info['download_url'] ?? '' );
	if ( '' === $url ) {
		return mlab_core_install_error();
	}
	$done = mlab_core_run_upgrader( $url );
	if ( is_wp_error( $done ) ) {
		return $done;
	}
	if ( ! file_exists( WP_PLUGIN_DIR . '/' . $main_file ) ) {
		return mlab_core_install_error( __( 'Die Hauptdatei fehlt im Paket', 'mlab-core' ) );
	}
	$activated = activate_plugin( $main_file );
	return is_wp_error( $activated ) ? mlab_core_install_error( $activated->get_error_message() ) : true;
}
