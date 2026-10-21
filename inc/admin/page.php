<?php
/**
 * Settings page "MomentumLab": license key and one card per product.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Client;
use MomentumLab\Core\Row;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'mlab_core_register_menu' );
add_action( 'admin_enqueue_scripts', 'mlab_core_enqueue_assets' );

/**
 * Adds the top-level menu entry.
 */
function mlab_core_register_menu(): void {
	add_menu_page(
		__( 'MomentumLab', 'mlab-core' ),
		__( 'MomentumLab', 'mlab-core' ),
		'activate_plugins',
		'mlab-core',
		'mlab_core_render_page',
		'dashicons-screenoptions',
		81
	);
}

/**
 * Loads the stylesheet on this page only.
 *
 * @param string $hook Current admin page hook.
 */
function mlab_core_enqueue_assets( $hook ): void {
	if ( 'toplevel_page_mlab-core' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'mlab-core-admin', plugins_url( 'assets/css/admin.css', MLAB_CORE_FILE ), array(), MLAB_CORE_VERSION );
}

/**
 * Prints the message left by the last action, once.
 */
function mlab_core_render_notice(): void {
	$key    = 'mlab_core_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	if ( ! is_array( $notice ) ) {
		return;
	}
	delete_transient( $key );
	$class = 'success' === ( $notice['type'] ?? '' ) ? 'notice-success' : 'notice-error';
	printf( '<div class="notice %s"><p>%s</p></div>', esc_attr( $class ), esc_html( (string) ( $notice['message'] ?? '' ) ) );
}

/**
 * Opens a form that posts to admin-post.php with its nonce.
 *
 * @param string $action Action name.
 * @param string $slug   Product slug, '' for none.
 */
function mlab_core_form_open( string $action, string $slug = '' ): void {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
	if ( '' !== $slug ) {
		echo '<input type="hidden" name="slug" value="' . esc_attr( $slug ) . '" />';
	}
	wp_nonce_field( $action );
}

/**
 * Prints the license key form and the reload button.
 */
function mlab_core_render_key_box(): void {
	$locked = mlab_core_license_key_locked();
	$key    = mlab_core_license_key();
	echo '<div class="mlab-core-box"><h2>' . esc_html__( 'Lizenzschlüssel', 'mlab-core' ) . '</h2>';
	if ( $locked ) {
		echo '<p>' . esc_html__( 'Der Lizenzschlüssel ist in der wp-config.php festgelegt.', 'mlab-core' ) . '</p>';
	} elseif ( current_user_can( 'manage_options' ) ) {
		mlab_core_form_open( 'mlab_core_save_key' );
		if ( '' !== $key ) {
			/* translators: %s: last 4 characters of the license key */
			echo '<p>' . esc_html( sprintf( __( 'Gespeichert: …%s', 'mlab-core' ), substr( $key, -4 ) ) ) . '</p>';
		}
		echo '<p><label for="mlab-core-key">' . esc_html__( 'Neuer Schlüssel', 'mlab-core' ) . '</label><br />';
		echo '<input type="password" id="mlab-core-key" name="license_key" class="regular-text mlab-core-input" autocomplete="off" /></p>';
		echo '<p class="mlab-core-buttons"><button type="submit" class="button button-primary mlab-core-button">' . esc_html__( 'Speichern', 'mlab-core' ) . '</button>';
		if ( '' !== $key ) {
			echo '<button type="submit" name="remove" value="1" class="button mlab-core-button">' . esc_html__( 'Schlüssel entfernen', 'mlab-core' ) . '</button>';
		}
		echo '</p></form>';
	}
	mlab_core_form_open( 'mlab_core_refresh' );
	echo '<button type="submit" class="button mlab-core-button">' . esc_html__( 'Liste neu laden', 'mlab-core' ) . '</button></form></div>';
}

/**
 * Prints the action button or the "on request" text for one product.
 *
 * @param array  $product Catalog entry.
 * @param string $state   Row state.
 */
function mlab_core_render_action( array $product, string $state ): void {
	if ( 'request' === $state ) {
		printf(
			'<a class="button mlab-core-button" href="%s">%s</a>',
			esc_url( (string) mlab_core_option( 'contact_url' ) ),
			esc_html__( 'Auf Anfrage', 'mlab-core' )
		);
		return;
	}
	$labels                       = array(
		'install'    => array( 'mlab_core_install', __( 'Installieren und aktivieren', 'mlab-core' ), 'install_plugins' ),
		'activate'   => array( 'mlab_core_activate', __( 'Aktivieren', 'mlab-core' ), 'activate_plugins' ),
		'deactivate' => array( 'mlab_core_deactivate', __( 'Deaktivieren', 'mlab-core' ), 'activate_plugins' ),
	);
	list( $action, $label, $cap ) = $labels[ $state ];
	if ( ! current_user_can( $cap ) ) {
		return;
	}
	mlab_core_form_open( $action, (string) $product['slug'] );
	$class = 'deactivate' === $state ? 'button' : 'button button-primary';
	echo '<button type="submit" class="' . esc_attr( $class ) . ' mlab-core-button">' . esc_html( $label ) . '</button></form>';
}

/**
 * Prints one product card.
 *
 * @param array $product Catalog entry.
 */
function mlab_core_render_card( array $product ): void {
	$file      = Row::safe_main_file( (string) ( $product['main_file'] ?? '' ) );
	$installed = file_exists( WP_PLUGIN_DIR . '/' . $file );
	$active    = $installed && is_plugin_active( $file );
	$state     = Row::state( $product, $installed, $active );
	echo '<div class="mlab-core-card"><h3>' . esc_html( (string) ( $product['name'] ?? '' ) ) . '</h3>';
	echo '<p>' . esc_html( (string) ( $product['description'] ?? '' ) ) . '</p>';
	if ( ! empty( $product['version'] ) ) {
		/* translators: %s: version number */
		echo '<p class="mlab-core-meta">' . esc_html( sprintf( __( 'Version %s', 'mlab-core' ), (string) $product['version'] ) ) . '</p>';
	}
	if ( Row::loses_updates( $product, $installed ) ) {
		echo '<p class="mlab-core-warning">' . esc_html__( 'Keine Updates mehr. Bitte melde dich bei MomentumLab, um deine Lizenz zu verlängern.', 'mlab-core' ) . '</p>';
	}
	mlab_core_render_action( $product, $state );
	echo '</div>';
}

/**
 * Prints the whole page.
 */
function mlab_core_render_page(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$catalog = ( new Client() )->catalog();
	echo '<div class="wrap mlab-core"><h1>' . esc_html__( 'MomentumLab', 'mlab-core' ) . '</h1>';
	mlab_core_render_notice();
	if ( '' !== $catalog['error'] ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( $catalog['error'] ) . '</p></div>';
	}
	mlab_core_render_key_box();
	mlab_core_render_token_box();
	echo '<div class="mlab-core-grid">';
	foreach ( $catalog['products'] as $product ) {
		mlab_core_render_card( $product );
	}
	echo '</div></div>';
}
