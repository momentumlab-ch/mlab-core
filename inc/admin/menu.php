<?php
/**
 * Admin menu "MomentumLab": overview, plugins, health and settings.
 *
 * Small MomentumLab plugins add their page below it with mlab_core_add_page();
 * large plugins with daily use (MomentumLab SEO) keep their own top-level menu right below.
 *
 * @package mlab-core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'mlab_core_register_menu', 9 );
add_action( 'admin_enqueue_scripts', 'mlab_core_enqueue_assets' );
add_filter( 'mlab_license_admin_url', 'mlab_core_license_admin_url' );

/**
 * Menu position: after the content menus, before Appearance. MomentumLab SEO uses the next slot.
 */
const MLAB_CORE_MENU_POSITION = '58.1';

/**
 * Parent slug other MomentumLab plugins use for their submenu page.
 */
const MLAB_CORE_MENU_SLUG = 'mlab-core';

/**
 * Pages of the menu: slug => [title, capability, render callback].
 *
 * @return array<string,array{0:string,1:string,2:callable-string}>
 */
function mlab_core_pages(): array {
	return array(
		'mlab-core'          => array( __( 'Overview', 'mlab-core' ), 'activate_plugins', 'mlab_core_render_overview' ),
		'mlab-core-plugins'  => array( __( 'Plugins', 'mlab-core' ), 'activate_plugins', 'mlab_core_render_plugins' ),
		'mlab-core-health'   => array( __( 'Health', 'mlab-core' ), 'manage_options', 'mlab_core_render_health' ),
		'mlab-core-settings' => array( __( 'Settings', 'mlab-core' ), 'manage_options', 'mlab_core_render_settings' ),
	);
}

/**
 * Registers the top-level menu and its pages.
 */
function mlab_core_register_menu(): void {
	add_menu_page( 'MomentumLab', 'MomentumLab', 'activate_plugins', MLAB_CORE_MENU_SLUG, 'mlab_core_render_overview', mlab_core_menu_icon(), MLAB_CORE_MENU_POSITION );
	foreach ( mlab_core_pages() as $slug => $page ) {
		add_submenu_page( MLAB_CORE_MENU_SLUG, $page[0] . ' ‹ MomentumLab', $page[0], $page[1], $slug, $page[2] );
	}
}

/**
 * Adds a page of another MomentumLab plugin below "MomentumLab".
 *
 * Call it on "admin_menu" (priority 10 or later). Without MomentumLab Core this function
 * does not exist; the plugin then registers its own menu.
 *
 * @param string   $title      Menu title.
 * @param string   $capability Capability.
 * @param string   $slug       Page slug, prefixed with the plugin's prefix.
 * @param callable $render     Render callback.
 * @return string|false Hook suffix.
 */
function mlab_core_add_page( string $title, string $capability, string $slug, callable $render ) {
	return add_submenu_page( MLAB_CORE_MENU_SLUG, $title . ' ‹ MomentumLab', $title, $capability, $slug, $render );
}

/**
 * Menu icon: the MomentumLab ring with "M", one colour so WordPress can tint it.
 */
function mlab_core_menu_icon(): string {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M18.40 6.26A9.2 9.2 0 0 0 1.60 6.26L3.24 6.99A7.4 7.4 0 0 1 16.76 6.99ZM1.60 13.74A9.2 9.2 0 0 0 18.40 13.74L16.76 13.01A7.4 7.4 0 0 1 3.24 13.01ZM6.3 13.6V6.4H7.675L10 9.6L12.325 6.4H13.7V13.6H12.45V8.7L10.55 11.2H9.45L7.55 8.7V13.6Z"/></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- data URI of the menu icon, as WordPress expects.
}

/**
 * Central license page for the shared license client: every MomentumLab plugin links here.
 *
 * @param string $url Current value.
 */
function mlab_core_license_admin_url( $url ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- filter signature; Core always provides the page.
	return mlab_core_page_url( 'mlab-core-settings' );
}

/**
 * Loads styles and script on the MomentumLab pages only.
 *
 * @param string $hook Current admin page hook.
 */
function mlab_core_enqueue_assets( $hook ): void {
	if ( false === strpos( (string) $hook, 'mlab-core' ) ) {
		return;
	}
	wp_enqueue_style( 'mlab-core-admin', plugins_url( 'assets/css/admin.css', MLAB_CORE_FILE ), array(), MLAB_CORE_VERSION );
}
