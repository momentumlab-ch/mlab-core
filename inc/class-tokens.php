<?php
/**
 * GitHub read tokens: encryption and selection per repository. No WordPress calls.
 *
 * @package mlab-core
 */

namespace MomentumLab\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts tokens for storage and finds the right token for a repository.
 *
 * A stored entry: scope ("owner" or "owner/repo"), cipher (encrypted token), hint (last 4 characters), saved (timestamp).
 */
final class Tokens {

	private const PREFIX_SODIUM  = 'sodium:';
	private const PREFIX_OPENSSL = 'gcm:';

	/**
	 * Encryption key from the site's secret keys; '' when they are missing or still the defaults.
	 *
	 * @param string $auth_key        AUTH_KEY.
	 * @param string $secure_auth_key SECURE_AUTH_KEY.
	 */
	public static function key( string $auth_key, string $secure_auth_key ): string {
		$default = 'put your unique phrase here';
		if ( strlen( $auth_key ) < 32 || strlen( $secure_auth_key ) < 32 || $default === $auth_key || $default === $secure_auth_key ) {
			return '';
		}
		return hash_hmac( 'sha256', 'mlab-core-github-tokens', $auth_key . $secure_auth_key, true );
	}

	/**
	 * Encrypts a token. Returns '' when no cipher is available.
	 *
	 * @param string $plain Token.
	 * @param string $key   32-byte key from key().
	 */
	public static function encrypt( string $plain, string $key ): string {
		if ( '' === $plain || 32 !== strlen( $key ) ) {
			return '';
		}
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return self::PREFIX_SODIUM . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary ciphertext for storage.
		}
		if ( function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			return false === $cipher ? '' : self::PREFIX_OPENSSL . base64_encode( $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary ciphertext for storage.
		}
		return '';
	}

	/**
	 * Decrypts a stored token. Returns '' when it cannot be read (other keys, damaged data).
	 *
	 * @param string $stored Value from encrypt().
	 * @param string $key    32-byte key from key().
	 */
	public static function decrypt( string $stored, string $key ): string {
		if ( 32 !== strlen( $key ) ) {
			return '';
		}
		if ( 0 === strpos( $stored, self::PREFIX_SODIUM ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$raw = base64_decode( substr( $stored, strlen( self::PREFIX_SODIUM ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary ciphertext from storage.
			if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return '';
			}
			$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
			return false === $plain ? '' : $plain;
		}
		if ( 0 === strpos( $stored, self::PREFIX_OPENSSL ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $stored, strlen( self::PREFIX_OPENSSL ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary ciphertext from storage.
			if ( false === $raw || strlen( $raw ) <= 28 ) {
				return '';
			}
			$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
			return false === $plain ? '' : $plain;
		}
		return '';
	}

	/**
	 * Normalizes a scope: "owner" or "owner/repo", lowercase; '' when invalid.
	 *
	 * Also accepts a GitHub URL such as https://github.com/owner/repo/.
	 *
	 * @param string $scope Input.
	 */
	public static function scope( string $scope ): string {
		$scope = strtolower( trim( $scope ) );
		$scope = (string) preg_replace( '#^(https?://)?(www\.)?github\.com/#', '', $scope );
		$scope = trim( (string) preg_replace( '#\.git$#', '', $scope ), '/ ' );
		return preg_match( '#^[a-z0-9](?:[a-z0-9-]{0,38})(?:/[a-z0-9._-]{1,100})?$#', $scope ) ? $scope : '';
	}

	/**
	 * Looks like a GitHub token (fine-grained, classic or app token)?
	 *
	 * @param string $token Token.
	 */
	public static function looks_valid( string $token ): bool {
		return (bool) preg_match( '/^(github_pat_[A-Za-z0-9_]{20,255}|gh[pousr]_[A-Za-z0-9]{30,255})$/', $token );
	}

	/**
	 * The entry for a repository: exact "owner/repo" first, then the owner.
	 *
	 * @param array<int,array<string,mixed>> $entries Stored entries.
	 * @param string                         $repo    "owner/repo" or a GitHub URL.
	 * @return array<string,mixed>|null
	 */
	public static function pick( array $entries, string $repo ): ?array {
		$repo = self::scope( $repo );
		if ( '' === $repo ) {
			return null;
		}
		$owner = explode( '/', $repo )[0];
		$found = null;
		foreach ( $entries as $entry ) {
			$scope = (string) ( $entry['scope'] ?? '' );
			if ( $scope === $repo ) {
				return $entry;
			}
			if ( $scope === $owner ) {
				$found = $entry;
			}
		}
		return $found;
	}

	/**
	 * Entries without the one for a scope, then with the new one appended.
	 *
	 * @param array<int,array<string,mixed>> $entries Stored entries.
	 * @param array<string,mixed>            $entry   New entry with "scope".
	 * @return array<int,array<string,mixed>>
	 */
	public static function upsert( array $entries, array $entry ): array {
		$entries   = self::without( $entries, (string) ( $entry['scope'] ?? '' ) );
		$entries[] = $entry;
		return $entries;
	}

	/**
	 * Entries without the one for a scope.
	 *
	 * @param array<int,array<string,mixed>> $entries Stored entries.
	 * @param string                         $scope   Scope to remove.
	 * @return array<int,array<string,mixed>>
	 */
	public static function without( array $entries, string $scope ): array {
		return array_values( array_filter( $entries, static fn( $e ) => is_array( $e ) && ( $e['scope'] ?? '' ) !== $scope ) );
	}

	/**
	 * Last four characters for display.
	 *
	 * @param string $token Token.
	 */
	public static function hint( string $token ): string {
		return substr( $token, -4 );
	}
}
