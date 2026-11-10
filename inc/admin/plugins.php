<?php
/**
 * Plugins page: one card per MomentumLab plugin with a switch that installs, activates or deactivates it.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Client;
use MomentumLab\Core\Row;

defined( 'ABSPATH' ) || exit;

/**
 * The plugins of the catalog with everything the pages need to show them.
 *
 * @param array $catalog Result of Client::catalog().
 * @return array<int,array<string,mixed>>
 */
function mlab_core_plugin_rows( array $catalog ): array {
	$installed_plugins = get_plugins();
	$rows              = array();
	foreach ( $catalog['products'] as $product ) {
		$file      = Row::safe_main_file( (string) ( $product['main_file'] ?? '' ) );
		$installed = isset( $installed_plugins[ $file ] );
		$active    = $installed && is_plugin_active( $file );
		$have      = $installed ? (string) ( $installed_plugins[ $file ]['Version'] ?? '' ) : '';
		$rows[]    = array(
			'product'   => $product,
			'installed' => $installed,
			'active'    => $active,
			'state'     => Row::state( $product, $installed, $active ),
			'have'      => $have,
			'update'    => $installed && ! empty( $product['unlocked'] ) && '' !== $have && version_compare( (string) ( $product['version'] ?? '' ), $have, '>' ),
			'expired'   => Row::loses_updates( $product, $installed ),
		);
	}
	return $rows;
}

/**
 * Filter from the address: all, active or available (not installed yet).
 */
function mlab_core_plugins_filter(): string {
	$filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
	return in_array( $filter, array( 'active', 'available' ), true ) ? $filter : 'all';
}

/**
 * Whether a row belongs to the chosen filter.
 *
 * @param array  $row    Row from mlab_core_plugin_rows().
 * @param string $filter all, active or available.
 */
function mlab_core_plugin_matches( array $row, string $filter ): bool {
	if ( 'active' === $filter ) {
		return (bool) $row['active'];
	}
	return 'available' !== $filter || ! $row['installed'];
}

/**
 * Prints the filter links.
 *
 * @param string $current Current filter.
 */
function mlab_core_render_filters( string $current ): void {
	$filters = array(
		'all'       => __( 'All', 'mlab-core' ),
		'active'    => __( 'Active', 'mlab-core' ),
		'available' => __( 'Available', 'mlab-core' ),
	);
	echo '<nav class="mlab-filters" aria-label="' . esc_attr__( 'Filter plugins', 'mlab-core' ) . '">';
	foreach ( $filters as $key => $label ) {
		$url = 'all' === $key ? mlab_core_page_url( 'mlab-core-plugins' ) : add_query_arg( 'filter', $key, mlab_core_page_url( 'mlab-core-plugins' ) );
		printf( '<a href="%s"%s>%s</a>', esc_url( $url ), $key === $current ? ' aria-current="page"' : '', esc_html( $label ) );
	}
	echo '</nav>';
}

/**
 * Plain words for the state of a plugin.
 *
 * @param array $row Row from mlab_core_plugin_rows().
 */
function mlab_core_plugin_status_text( array $row ): string {
	if ( $row['active'] ) {
		return __( 'Active', 'mlab-core' );
	}
	if ( $row['installed'] ) {
		return __( 'Installed, switched off', 'mlab-core' );
	}
	return 'request' === $row['state'] ? __( 'Not included in your license', 'mlab-core' ) : __( 'Not installed yet', 'mlab-core' );
}

/**
 * Prints the switch: a submit button inside the form of its action.
 *
 * @param array $row Row from mlab_core_plugin_rows().
 */
function mlab_core_render_switch( array $row ): void {
	$actions              = array(
		'install'    => array( 'mlab_core_install', 'install_plugins' ),
		'activate'   => array( 'mlab_core_activate', 'activate_plugins' ),
		'deactivate' => array( 'mlab_core_deactivate', 'activate_plugins' ),
	);
	list( $action, $cap ) = $actions[ $row['state'] ];
	$name                 = (string) $row['product']['name'];
	/* translators: %s: plugin name */
	$label = 'deactivate' === $row['state'] ? sprintf( __( 'Switch off %s', 'mlab-core' ), $name ) : sprintf( __( 'Switch on %s', 'mlab-core' ), $name );
	mlab_core_form_open( $action, (string) $row['product']['slug'] );
	printf(
		'<button type="submit" class="mlab-switch" role="switch" aria-checked="%s" aria-label="%s"%s></button></form>',
		$row['active'] ? 'true' : 'false',
		esc_attr( $label ),
		current_user_can( $cap ) ? '' : ' disabled'
	);
}

/**
 * Prints the strips under the description: update ready, or no more updates.
 *
 * @param array $row Row from mlab_core_plugin_rows().
 */
function mlab_core_render_strips( array $row ): void {
	if ( $row['update'] ) {
		/* translators: %s: version number */
		$text = sprintf( __( 'Update to %s ready', 'mlab-core' ), (string) $row['product']['version'] );
		echo '<p class="mlab-strip is-info"><span>' . esc_html( $text ) . '</span> <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">' . esc_html__( 'Update now', 'mlab-core' ) . '</a></p>';
	}
	if ( $row['expired'] ) {
		echo '<p class="mlab-strip is-warn"><span>' . esc_html__( 'No more updates: this unlock has expired. The plugin keeps running.', 'mlab-core' ) . '</span> <a href="' . esc_url( (string) mlab_core_option( 'contact_url' ) ) . '">' . esc_html__( 'Renew', 'mlab-core' ) . '</a></p>';
	}
}

/**
 * Prints the switch, or the "On request" badge with its button.
 *
 * @param array $row Row from mlab_core_plugin_rows().
 */
function mlab_core_render_plugin_control( array $row ): void {
	if ( 'request' !== $row['state'] ) {
		mlab_core_render_switch( $row );
		return;
	}
	mlab_core_badge( 'neutral', __( 'On request', 'mlab-core' ) );
}

/**
 * Prints one plugin card.
 *
 * @param array $row Row from mlab_core_plugin_rows().
 */
function mlab_core_render_plugin_card( array $row ): void {
	$product = $row['product'];
	$version = '' !== $row['have'] ? $row['have'] : (string) ( $product['version'] ?? '' );
	$status  = mlab_core_plugin_status_text( $row );
	/* translators: 1: version number, 2: status such as "Active" */
	$meta = '' === $version ? $status : sprintf( __( 'Version %1$s · %2$s', 'mlab-core' ), $version, $status );
	echo '<article class="mlab-card mlab-plugin"><div class="mlab-plugin__head"><h2>' . esc_html( (string) $product['name'] ) . '</h2>';
	mlab_core_render_plugin_control( $row );
	echo '</div><p class="mlab-muted">' . esc_html( $meta ) . '</p>';
	echo '<p>' . esc_html( (string) ( $product['description'] ?? '' ) ) . '</p>';
	mlab_core_render_strips( $row );
	if ( 'request' === $row['state'] ) {
		echo '<p><a class="button" href="' . esc_url( (string) mlab_core_option( 'contact_url' ) ) . '">' . esc_html__( 'Ask MomentumLab', 'mlab-core' ) . '</a></p>';
	}
	echo '</article>';
}

/**
 * Prints the "Reload list" button.
 */
function mlab_core_render_reload_button(): void {
	mlab_core_form_open( 'mlab_core_refresh' );
	echo '<button type="submit" class="button">' . esc_html__( 'Reload list', 'mlab-core' ) . '</button></form>';
}

/**
 * Prints the plugins page.
 */
function mlab_core_render_plugins(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$catalog = ( new Client() )->catalog();
	$filter  = mlab_core_plugins_filter();
	mlab_core_page_open( __( 'Plugins', 'mlab-core' ), __( 'Switch on installs and activates. Switching off only deactivates; nothing is deleted.', 'mlab-core' ), 'mlab_core_render_reload_button' );
	if ( '' !== $catalog['error'] ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( $catalog['error'] ) . '</p></div>';
	}
	mlab_core_render_filters( $filter );
	echo '<div class="mlab-grid">';
	foreach ( mlab_core_plugin_rows( $catalog ) as $row ) {
		if ( mlab_core_plugin_matches( $row, $filter ) ) {
			mlab_core_render_plugin_card( $row );
		}
	}
	echo '</div>';
	mlab_core_page_close();
}
