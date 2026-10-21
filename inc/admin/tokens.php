<?php
/**
 * Settings box "GitHub-Zugang": add, test and remove read tokens. Administrators only.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Tokens;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_mlab_core_token_save', 'mlab_core_action_token_save' );
add_action( 'admin_post_mlab_core_token_remove', 'mlab_core_action_token_remove' );
add_action( 'admin_post_mlab_core_token_test', 'mlab_core_action_token_test' );

/**
 * Prints the box. The token itself is never printed, only its last four characters.
 */
function mlab_core_render_token_box(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="mlab-core-box"><h2>' . esc_html__( 'GitHub-Zugang für Updates', 'mlab-core' ) . '</h2>';
	echo '<p>' . esc_html__( 'Für private Repositories von MomentumLab braucht WordPress ein Lese-Token von GitHub. Es wird verschlüsselt gespeichert und nie angezeigt.', 'mlab-core' ) . '</p>';

	if ( mlab_core_github_token_locked() ) {
		echo '<p><strong>' . esc_html__( 'Das Token ist in der wp-config.php festgelegt (MLAB_GITHUB_TOKEN) und gilt für alle Repositories.', 'mlab-core' ) . '</strong></p></div>';
		return;
	}
	if ( '' === mlab_core_github_token_key() ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Die Website hat keine eigenen Sicherheitsschlüssel (AUTH_KEY, SECURE_AUTH_KEY) in der wp-config.php. Ohne sie kann kein Token sicher gespeichert werden.', 'mlab-core' ) . '</p></div></div>';
		return;
	}

	$entries = mlab_core_github_token_entries();
	if ( $entries ) {
		echo '<table class="widefat striped mlab-core-tokens"><thead><tr><th>' . esc_html__( 'Gilt für', 'mlab-core' ) . '</th><th>' . esc_html__( 'Token', 'mlab-core' ) . '</th><th>' . esc_html__( 'Letzter Test', 'mlab-core' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $entries as $entry ) {
			mlab_core_render_token_row( $entry );
		}
		echo '</tbody></table>';
	}

	mlab_core_form_open( 'mlab_core_token_save' );
	echo '<p><label for="mlab-core-token-scope">' . esc_html__( 'Gilt für (Organisation oder Organisation/Repository)', 'mlab-core' ) . '</label><br />';
	echo '<input type="text" id="mlab-core-token-scope" name="scope" class="regular-text mlab-core-input" value="momentumlab-ch" autocomplete="off" spellcheck="false" /></p>';
	echo '<p><label for="mlab-core-token">' . esc_html__( 'Token von GitHub', 'mlab-core' ) . '</label><br />';
	echo '<input type="password" id="mlab-core-token" name="token" class="regular-text mlab-core-input" autocomplete="off" spellcheck="false" placeholder="github_pat_…" /></p>';
	echo '<p class="mlab-core-buttons"><button type="submit" class="button button-primary mlab-core-button">' . esc_html__( 'Speichern und testen', 'mlab-core' ) . '</button></p></form></div>';
}

/**
 * One table row with test and remove buttons.
 *
 * @param array<string,mixed> $entry Stored entry.
 */
function mlab_core_render_token_row( array $entry ): void {
	$scope   = (string) ( $entry['scope'] ?? '' );
	$tested  = (int) ( $entry['tested'] ?? 0 );
	$expires = (int) ( $entry['expires'] ?? 0 );
	$status  = $tested ? (string) ( $entry['status'] ?? '' ) : __( 'noch nicht getestet', 'mlab-core' );
	if ( $expires ) {
		/* translators: %s: date */
		$status .= ' ' . sprintf( __( 'Läuft ab am %s.', 'mlab-core' ), wp_date( get_option( 'date_format' ), $expires ) );
	}
	echo '<tr><td><code>' . esc_html( $scope ) . '</code></td><td>…' . esc_html( (string) ( $entry['hint'] ?? '' ) ) . '</td><td>' . esc_html( $status ) . '</td><td class="mlab-core-buttons">';
	mlab_core_form_open( 'mlab_core_token_test' );
	echo '<input type="hidden" name="scope" value="' . esc_attr( $scope ) . '" /><button type="submit" class="button mlab-core-button">' . esc_html__( 'Verbindung testen', 'mlab-core' ) . '</button></form>';
	mlab_core_form_open( 'mlab_core_token_remove' );
	echo '<input type="hidden" name="scope" value="' . esc_attr( $scope ) . '" /><button type="submit" class="button mlab-core-button">' . esc_html__( 'Entfernen', 'mlab-core' ) . '</button></form>';
	echo '</td></tr>';
}

/**
 * Posted scope, normalized. Ends the request when invalid.
 */
function mlab_core_posted_token_scope(): string {
	// Nonce was checked in mlab_core_guard().
	$scope = Tokens::scope( isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' === $scope ) {
		mlab_core_finish( 'error', __( 'Bitte eine Organisation (z. B. momentumlab-ch) oder Organisation/Repository eintragen.', 'mlab-core' ) );
	}
	return $scope;
}

/**
 * Tests a token and stores the result in its entry.
 *
 * @param array<string,mixed> $entry Entry.
 * @param string              $token Token.
 * @return array{0:array<string,mixed>,1:array{ok:bool,message:string,expires:string}}
 */
function mlab_core_token_test_entry( array $entry, string $token ): array {
	$check            = mlab_core_github_token_check( $token, (string) $entry['scope'] );
	$entry['tested']  = time();
	$entry['status']  = $check['message'];
	$entry['expires'] = '' !== $check['expires'] ? (int) $check['expires'] : 0;
	return array( $entry, $check );
}

/**
 * Saves a new token (encrypted) and tests it right away.
 */
function mlab_core_action_token_save(): void {
	mlab_core_guard( 'mlab_core_token_save', array( 'manage_options' ) );
	if ( mlab_core_github_token_locked() ) {
		mlab_core_finish( 'error', __( 'Das Token ist in der wp-config.php festgelegt und kann hier nicht geändert werden.', 'mlab-core' ) );
	}
	$scope = mlab_core_posted_token_scope();
	// Nonce was checked in mlab_core_guard(). The token is not sanitized as text on purpose: it must stay exactly as GitHub issued it.
	$token = isset( $_POST['token'] ) ? trim( (string) wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! Tokens::looks_valid( $token ) ) {
		mlab_core_finish( 'error', __( 'Das sieht nicht nach einem GitHub-Token aus. Ein feingranulares Token beginnt mit github_pat_.', 'mlab-core' ) );
	}
	$cipher = Tokens::encrypt( $token, mlab_core_github_token_key() );
	if ( '' === $cipher ) {
		mlab_core_finish( 'error', __( 'Das Token konnte nicht verschlüsselt werden. Auf dem Server fehlen sodium und openssl.', 'mlab-core' ) );
	}
	list( $entry, $check ) = mlab_core_token_test_entry(
		array(
			'scope'  => $scope,
			'cipher' => $cipher,
			'hint'   => Tokens::hint( $token ),
			'saved'  => time(),
		),
		$token
	);
	mlab_core_github_token_save_entries( Tokens::upsert( mlab_core_github_token_entries(), $entry ) );
	/* translators: %s: result of the connection test */
	mlab_core_finish( $check['ok'] ? 'success' : 'error', sprintf( __( 'Token gespeichert. Test: %s', 'mlab-core' ), $check['message'] ) );
}

/**
 * Tests a stored token.
 */
function mlab_core_action_token_test(): void {
	mlab_core_guard( 'mlab_core_token_test', array( 'manage_options' ) );
	$scope   = mlab_core_posted_token_scope();
	$entries = mlab_core_github_token_entries();
	foreach ( $entries as $i => $entry ) {
		if ( ( $entry['scope'] ?? '' ) !== $scope ) {
			continue;
		}
		$token = Tokens::decrypt( (string) ( $entry['cipher'] ?? '' ), mlab_core_github_token_key() );
		if ( '' === $token ) {
			mlab_core_finish( 'error', __( 'Das gespeicherte Token lässt sich nicht mehr lesen (die Sicherheitsschlüssel der Website wurden geändert). Bitte neu eintragen.', 'mlab-core' ) );
		}
		list( $entries[ $i ], $check ) = mlab_core_token_test_entry( $entry, $token );
		mlab_core_github_token_save_entries( $entries );
		mlab_core_finish( $check['ok'] ? 'success' : 'error', $check['message'] );
	}
	mlab_core_finish( 'error', __( 'Dieses Token gibt es nicht mehr.', 'mlab-core' ) );
}

/**
 * Removes a stored token.
 */
function mlab_core_action_token_remove(): void {
	mlab_core_guard( 'mlab_core_token_remove', array( 'manage_options' ) );
	$scope = mlab_core_posted_token_scope();
	mlab_core_github_token_save_entries( Tokens::without( mlab_core_github_token_entries(), $scope ) );
	mlab_core_finish( 'success', __( 'Das Token wurde entfernt.', 'mlab-core' ) );
}
