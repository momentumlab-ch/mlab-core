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
		'catalog_ttl'  => 43200,
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
 * Base URL of the license server without trailing slash.
 */
function mlab_core_server_url(): string {
	$url = defined( 'MLAB_CORE_SERVER_URL' ) ? (string) constant( 'MLAB_CORE_SERVER_URL' ) : 'https://momentumlab.ch';
	return rtrim( $url, '/' );
}

/**
 * License key: the constant from wp-config.php wins over the stored option.
 */
function mlab_core_license_key(): string {
	if ( defined( 'MLAB_CORE_LICENSE_KEY' ) && '' !== (string) constant( 'MLAB_CORE_LICENSE_KEY' ) ) {
		return (string) constant( 'MLAB_CORE_LICENSE_KEY' );
	}
	return (string) get_option( 'mlab_core_license_key', '' );
}
