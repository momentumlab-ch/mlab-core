<?php
/**
 * Verschlüsselter Speicher für Zugangsdaten anderer MomentumLab-Plugins (z. B. Google-Dienstkonto von MomentumLab Insights).
 *
 * Gleiche Verschlüsselung wie bei den GitHub-Tokens (Schlüssel aus AUTH_KEY und SECURE_AUTH_KEY).
 * Die Werte stehen nie im Klartext in der Datenbank, im Frontend, in der REST-API oder in Logs.
 *
 *     mlab_core_secret_set( 'mlab-insights/google', $json );
 *     $json = mlab_core_secret_get( 'mlab-insights/google' );
 *
 * @package mlab-core
 */

use MomentumLab\Core\Tokens;

defined( 'ABSPATH' ) || exit;

/**
 * Gültiger Name: «<plugin-slug>/<name>», Kleinbuchstaben, Ziffern, Bindestrich, Unterstrich.
 *
 * @param string $name Name.
 */
function mlab_core_secret_name( string $name ): string {
	$name = strtolower( trim( $name ) );
	return preg_match( '#^[a-z0-9_-]{2,64}/[a-z0-9_-]{1,64}$#', $name ) ? $name : '';
}

/**
 * Alle gespeicherten Geheimnisse (verschlüsselt).
 *
 * @return array<string,string> Name => verschlüsselter Wert.
 */
function mlab_core_secrets(): array {
	$secrets = get_option( 'mlab_core_secrets', array() );
	return is_array( $secrets ) ? array_filter( $secrets, 'is_string' ) : array();
}

/**
 * Kann die Website Geheimnisse speichern (eigene Sicherheitsschlüssel und sodium oder openssl)?
 */
function mlab_core_secrets_available(): bool {
	$key = mlab_core_github_token_key();
	return '' !== $key && '' !== Tokens::encrypt( 'test', $key );
}

/**
 * Speichert ein Geheimnis verschlüsselt. Ein leerer Wert löscht es.
 *
 * @param string $name  Name, z. B. «mlab-insights/google».
 * @param string $value Wert.
 */
function mlab_core_secret_set( string $name, string $value ): bool {
	$name = mlab_core_secret_name( $name );
	if ( '' === $name ) {
		return false;
	}
	$secrets = mlab_core_secrets();
	if ( '' === $value ) {
		unset( $secrets[ $name ] );
		update_option( 'mlab_core_secrets', $secrets, false );
		return true;
	}
	$cipher = Tokens::encrypt( $value, mlab_core_github_token_key() );
	if ( '' === $cipher ) {
		return false;
	}
	$secrets[ $name ] = $cipher;
	update_option( 'mlab_core_secrets', $secrets, false );
	return true;
}

/**
 * Liest ein Geheimnis; '' wenn es fehlt oder nicht mehr lesbar ist.
 *
 * @param string $name Name.
 */
function mlab_core_secret_get( string $name ): string {
	$name    = mlab_core_secret_name( $name );
	$secrets = mlab_core_secrets();
	return '' !== $name && isset( $secrets[ $name ] ) ? Tokens::decrypt( $secrets[ $name ], mlab_core_github_token_key() ) : '';
}

/**
 * Gibt es ein Geheimnis mit diesem Namen (ohne es zu entschlüsseln)?
 *
 * @param string $name Name.
 */
function mlab_core_secret_exists( string $name ): bool {
	$name = mlab_core_secret_name( $name );
	return '' !== $name && isset( mlab_core_secrets()[ $name ] );
}
