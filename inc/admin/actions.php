<?php
/**
 * Admin actions of the MomentumLab pages. Each one checks nonce and capability.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Client;
use MomentumLab\Core\Row;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_mlab_core_refresh', 'mlab_core_action_refresh' );
add_action( 'admin_post_mlab_core_install', 'mlab_core_action_install' );
add_action( 'admin_post_mlab_core_activate', 'mlab_core_action_activate' );
add_action( 'admin_post_mlab_core_deactivate', 'mlab_core_action_deactivate' );

/**
 * Stops the request unless nonce and all capabilities are fine.
 *
 * @param string   $action Action name, also the nonce action.
 * @param string[] $caps   Required capabilities.
 */
function mlab_core_guard( string $action, array $caps ): void {
	check_admin_referer( $action );
	foreach ( $caps as $cap ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You are not allowed to do this. Ask an administrator.', 'mlab-core' ), '', array( 'response' => 403 ) );
		}
	}
}

/**
 * Remembers a message for the next page view and goes back to the page.
 *
 * @param string $type    "success" or "error".
 * @param string $message Message text.
 */
function mlab_core_finish( string $type, string $message ): void {
	set_transient(
		'mlab_core_notice_' . get_current_user_id(),
		array(
			'type'    => $type,
			'message' => $message,
		),
		MINUTE_IN_SECONDS
	);
	$back = wp_get_referer();
	wp_safe_redirect( $back ? $back : mlab_core_page_url() );
	exit;
}

/**
 * Finishes with the result of a WP_Error-or-true call.
 *
 * @param true|WP_Error $result  Result.
 * @param string        $success Message on success.
 */
function mlab_core_finish_result( $result, string $success ): void {
	if ( is_wp_error( $result ) ) {
		mlab_core_finish( 'error', $result->get_error_message() );
	}
	mlab_core_finish( 'success', $success );
}

/**
 * Reloads the catalog from the server, then finishes with its error or the success message.
 *
 * @param string $success Message when the reload worked.
 */
function mlab_core_reload_and_finish( string $success ): void {
	$catalog = ( new Client() )->catalog( true );
	if ( '' !== $catalog['error'] ) {
		mlab_core_finish( 'error', $catalog['error'] );
	}
	mlab_core_finish( 'success', $success );
}

/**
 * The catalog product named by the posted slug. Never a path from the request.
 *
 * @return array Product with a safe main file; ends the request when unknown.
 */
function mlab_core_posted_product(): array {
	// Nonce was checked in mlab_core_guard().
	$slug    = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$product = '' === $slug ? null : ( new Client() )->product( $slug );
	if ( null === $product || '' === Row::safe_main_file( (string) ( $product['main_file'] ?? '' ) ) ) {
		mlab_core_finish( 'error', __( 'This plugin is not in the list. Press "Reload list" and try again.', 'mlab-core' ) );
	}
	return $product;
}

/**
 * Reloads the catalog from the server.
 */
function mlab_core_action_refresh(): void {
	mlab_core_guard( 'mlab_core_refresh', array( 'activate_plugins' ) );
	mlab_core_reload_and_finish( __( 'The list is up to date.', 'mlab-core' ) );
}

/**
 * Installs and activates a product.
 */
function mlab_core_action_install(): void {
	mlab_core_guard( 'mlab_core_install', array( 'install_plugins', 'activate_plugins' ) );
	$product = mlab_core_posted_product();
	if ( empty( $product['unlocked'] ) ) {
		mlab_core_finish( 'error', __( 'This plugin is not part of your license. Please contact MomentumLab.', 'mlab-core' ) );
	}
	mlab_core_finish_result( mlab_core_install_product( $product ), __( 'The plugin is installed and active.', 'mlab-core' ) );
}

/**
 * Activates an installed product.
 */
function mlab_core_action_activate(): void {
	mlab_core_guard( 'mlab_core_activate', array( 'activate_plugins' ) );
	$product = mlab_core_posted_product();
	$file    = Row::safe_main_file( (string) $product['main_file'] );
	if ( ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
		mlab_core_finish( 'error', __( 'The plugin is not installed. Switch it on to install it.', 'mlab-core' ) );
	}
	$result = activate_plugin( $file );
	mlab_core_finish_result( $result, __( 'The plugin is active.', 'mlab-core' ) );
}

/**
 * Deactivates a product. Files stay.
 */
function mlab_core_action_deactivate(): void {
	mlab_core_guard( 'mlab_core_deactivate', array( 'activate_plugins' ) );
	$product = mlab_core_posted_product();
	deactivate_plugins( Row::safe_main_file( (string) $product['main_file'] ) );
	mlab_core_finish( 'success', __( 'The plugin is switched off. It stays installed.', 'mlab-core' ) );
}
