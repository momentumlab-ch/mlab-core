<?php
/**
 * Central defaults and helpers for server URL and license key.
 *
 * @package mlab-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the default values of all settings.
 *
 * @return array<string,int|string>
 */
function mlab_core_option_defaults(): array {
	return array(
		'http_timeout' => 5,
		'contact_url'  => 'mailto:support@momentumlab.ch',
	);
}

/**
 * Returns one setting, falling back to its default.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function mlab_core_option( string $key ) {
	$defaults = mlab_core_option_defaults();
	return $defaults[ $key ] ?? null;
}

/**
 * Base URL of the license server without trailing slash (from the shared license client).
 */
function mlab_core_server_url(): string {
	return mlab_license()->server_url();
}

/**
 * True when wp-config.php sets the license key, so no form can change it.
 */
function mlab_core_license_key_locked(): bool {
	return mlab_license()->key_locked();
}

/**
 * License key of this site, shared by all MomentumLab plugins.
 */
function mlab_core_license_key(): string {
	return mlab_license()->key();
}

/**
 * URL of a MomentumLab admin page.
 *
 * @param string $page Page slug, e.g. "mlab-core-plugins".
 */
function mlab_core_page_url( string $page = 'mlab-core' ): string {
	return admin_url( 'admin.php?page=' . $page );
}
