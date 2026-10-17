<?php
/**
 * Admin actions of the settings page. Each one checks nonce and capability.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Client;
use MomentumLab\Core\Row;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_mlab_core_save_key', 'mlab_core_action_save_key' );
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
			wp_die( esc_html__( 'Dafür fehlt dir die Berechtigung.', 'mlab-core' ), '', array( 'response' => 403 ) );
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
	wp_safe_redirect( admin_url( 'admin.php?page=mlab-core' ) );
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
 * The catalog product named by the posted slug. Never a path from the request.
 *
 * @return array Product with a safe main file; ends the request when unknown.
 */
function mlab_core_posted_product(): array {
	// Nonce was checked in mlab_core_guard().
	$slug    = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$product = '' === $slug ? null : ( new Client() )->product( $slug );
	if ( null === $product || '' === Row::safe_main_file( (string) ( $product['main_file'] ?? '' ) ) ) {
		mlab_core_finish( 'error', __( 'Dieses Plugin ist nicht in der Liste. Bitte lade die Liste neu.', 'mlab-core' ) );
	}
	return $product;
}

/**
 * Saves or removes the license key and reloads the catalog.
 */
function mlab_core_action_save_key(): void {
	mlab_core_guard( 'mlab_core_save_key', array( 'manage_options' ) );
	if ( defined( 'MLAB_CORE_LICENSE_KEY' ) && '' !== (string) constant( 'MLAB_CORE_LICENSE_KEY' ) ) {
		mlab_core_finish( 'error', __( 'Der Lizenzschlüssel ist in der wp-config.php festgelegt und kann hier nicht geändert werden.', 'mlab-core' ) );
	}
	// Nonce was checked in mlab_core_guard().
	if ( isset( $_POST['remove'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_option( 'mlab_core_license_key', '', false );
		( new Client() )->catalog( true );
		mlab_core_finish( 'success', __( 'Der Lizenzschlüssel wurde entfernt.', 'mlab-core' ) );
	}
	mlab_core_store_key( isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

/**
 * Stores a new key, reloads the catalog and reports the result.
 *
 * @param string $key Sanitized key from the form.
 */
function mlab_core_store_key( string $key ): void {
	if ( '' === $key ) {
		mlab_core_finish( 'error', __( 'Bitte gib einen Lizenzschlüssel ein.', 'mlab-core' ) );
	}
	update_option( 'mlab_core_license_key', $key, false );
	$catalog = ( new Client() )->catalog( true );
	if ( '' !== $catalog['error'] ) {
		mlab_core_finish( 'error', $catalog['error'] );
	}
	mlab_core_finish( 'success', __( 'Der Lizenzschlüssel wurde gespeichert und die Liste neu geladen.', 'mlab-core' ) );
}

/**
 * Reloads the catalog from the server.
 */
function mlab_core_action_refresh(): void {
	mlab_core_guard( 'mlab_core_refresh', array( 'activate_plugins' ) );
	$catalog = ( new Client() )->catalog( true );
	if ( '' !== $catalog['error'] ) {
		mlab_core_finish( 'error', $catalog['error'] );
	}
	mlab_core_finish( 'success', __( 'Die Liste ist aktuell.', 'mlab-core' ) );
}

/**
 * Installs and activates a product.
 */
function mlab_core_action_install(): void {
	mlab_core_guard( 'mlab_core_install', array( 'install_plugins', 'activate_plugins' ) );
	$product = mlab_core_posted_product();
	if ( empty( $product['unlocked'] ) ) {
		mlab_core_finish( 'error', __( 'Dieses Plugin ist für deine Lizenz nicht freigeschaltet. Bitte melde dich bei MomentumLab.', 'mlab-core' ) );
	}
	mlab_core_finish_result( mlab_core_install_product( $product ), __( 'Das Plugin ist installiert und aktiv.', 'mlab-core' ) );
}

/**
 * Activates an installed product.
 */
function mlab_core_action_activate(): void {
	mlab_core_guard( 'mlab_core_activate', array( 'activate_plugins' ) );
	$product = mlab_core_posted_product();
	$file    = Row::safe_main_file( (string) $product['main_file'] );
	if ( ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
		mlab_core_finish( 'error', __( 'Das Plugin ist nicht installiert.', 'mlab-core' ) );
	}
	$result = activate_plugin( $file );
	mlab_core_finish_result( $result, __( 'Das Plugin ist aktiv.', 'mlab-core' ) );
}

/**
 * Deactivates a product. Files stay.
 */
function mlab_core_action_deactivate(): void {
	mlab_core_guard( 'mlab_core_deactivate', array( 'activate_plugins' ) );
	$product = mlab_core_posted_product();
	deactivate_plugins( Row::safe_main_file( (string) $product['main_file'] ) );
	mlab_core_finish( 'success', __( 'Das Plugin ist ausgeschaltet. Es bleibt installiert.', 'mlab-core' ) );
}
