<?php
/**
 * GitHub read tokens for the updates of private MomentumLab plugins and themes.
 *
 * Order: constant MLAB_GITHUB_TOKEN in wp-config.php (wins), then the encrypted tokens from the settings page.
 * Other plugins and themes ask through the filter "mlab_github_token" and keep their own constant as fallback:
 *
 *     $token = (string) apply_filters( 'mlab_github_token', '', 'momentumlab-ch/<repo>' );
 *
 * @package mlab-core
 */

use MomentumLab\Core\Tokens;

defined( 'ABSPATH' ) || exit;

add_filter( 'mlab_github_token', 'mlab_core_filter_github_token', 10, 2 );

/**
 * True when wp-config.php sets MLAB_GITHUB_TOKEN.
 */
function mlab_core_github_token_locked(): bool {
	return defined( 'MLAB_GITHUB_TOKEN' ) && '' !== (string) constant( 'MLAB_GITHUB_TOKEN' );
}

/**
 * Encryption key from AUTH_KEY and SECURE_AUTH_KEY; '' when the site has no real keys.
 */
function mlab_core_github_token_key(): string {
	return Tokens::key(
		defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : '',
		defined( 'SECURE_AUTH_KEY' ) ? (string) SECURE_AUTH_KEY : ''
	);
}

/**
 * Stored entries (encrypted). Never sent to the browser or the REST API.
 *
 * @return array<int,array<string,mixed>>
 */
function mlab_core_github_token_entries(): array {
	$entries = get_option( 'mlab_core_github_tokens', array() );
	return is_array( $entries ) ? array_values( array_filter( $entries, 'is_array' ) ) : array();
}

/**
 * Saves the entries without autoload.
 *
 * @param array<int,array<string,mixed>> $entries Entries.
 */
function mlab_core_github_token_save_entries( array $entries ): void {
	update_option( 'mlab_core_github_tokens', array_values( $entries ), false );
}

/**
 * Token for a repository, '' when none is set or it cannot be decrypted.
 *
 * @param string $repo "owner/repo" or a GitHub URL.
 */
function mlab_core_github_token( string $repo ): string {
	if ( mlab_core_github_token_locked() ) {
		return (string) constant( 'MLAB_GITHUB_TOKEN' );
	}
	$entry = Tokens::pick( mlab_core_github_token_entries(), $repo );
	return null === $entry ? '' : Tokens::decrypt( (string) ( $entry['cipher'] ?? '' ), mlab_core_github_token_key() );
}

/**
 * Filter "mlab_github_token": keeps a token another source already found.
 *
 * @param mixed $token Token so far.
 * @param mixed $repo  "owner/repo" or a GitHub URL.
 */
function mlab_core_filter_github_token( $token, $repo = '' ): string {
	if ( is_string( $token ) && '' !== $token ) {
		return $token;
	}
	return mlab_core_github_token( is_string( $repo ) ? $repo : '' );
}

/**
 * Checks a token against GitHub without showing it. For a repository scope the repository must be readable.
 *
 * @param string $token Token.
 * @param string $scope "owner" or "owner/repo".
 * @return array{ok:bool,message:string,expires:string}
 */
function mlab_core_github_token_check( string $token, string $scope ): array {
	$url      = false !== strpos( $scope, '/' ) ? 'https://api.github.com/repos/' . $scope : 'https://api.github.com/rate_limit';
	$response = wp_remote_get(
		$url,
		array(
			'timeout' => (int) mlab_core_option( 'http_timeout' ),
			'headers' => array(
				'Authorization'        => 'Bearer ' . $token,
				'Accept'               => 'application/vnd.github+json',
				'X-GitHub-Api-Version' => '2022-11-28',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return array(
			'ok'      => false,
			'message' => __( 'GitHub ist gerade nicht erreichbar. Bitte später nochmals testen.', 'mlab-core' ),
			'expires' => '',
		);
	}
	$code     = (int) wp_remote_retrieve_response_code( $response );
	$expires  = (string) wp_remote_retrieve_header( $response, 'github-authentication-token-expiration' );
	$messages = array(
		200 => __( 'Verbindung klappt.', 'mlab-core' ),
		401 => __( 'GitHub lehnt das Token ab: falsch, abgelaufen oder widerrufen. Bitte ein neues Token eintragen.', 'mlab-core' ),
		403 => __( 'Das Token hat keinen Lesezugriff. Bitte bei GitHub «Contents: Read» freigeben.', 'mlab-core' ),
		404 => __( 'Das Repository ist mit diesem Token nicht sichtbar. Bitte bei GitHub den Zugriff auf dieses Repository erlauben.', 'mlab-core' ),
	);
	return array(
		'ok'      => 200 === $code,
		/* translators: %d: HTTP status code */
		'message' => $messages[ $code ] ?? sprintf( __( 'Unerwartete Antwort von GitHub (Status %d).', 'mlab-core' ), $code ),
		'expires' => '' !== $expires ? (string) strtotime( $expires ) : '',
	);
}
