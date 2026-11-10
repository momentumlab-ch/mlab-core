<?php
/**
 * GitHub access (advanced settings): add, test and remove read tokens. Administrators only.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Tokens;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_mlab_core_token_save', 'mlab_core_action_token_save' );
add_action( 'admin_post_mlab_core_token_remove', 'mlab_core_action_token_remove' );
add_action( 'admin_post_mlab_core_token_test', 'mlab_core_action_token_test' );

/**
 * Prints the token settings: list of saved tokens and the form. The token itself is never printed, only its last four characters.
 */
function mlab_core_render_token_box(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<p>' . esc_html__( 'For private MomentumLab repositories, WordPress needs a read token from GitHub. It is stored encrypted and never shown.', 'mlab-core' ) . '</p>';
	if ( mlab_core_github_token_locked() ) {
		echo '<p><strong>' . esc_html__( 'The token is set in wp-config.php (MLAB_GITHUB_TOKEN) and applies to all repositories.', 'mlab-core' ) . '</strong></p>';
		return;
	}
	if ( '' === mlab_core_github_token_key() ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Your website has no security keys (AUTH_KEY, SECURE_AUTH_KEY) in wp-config.php. Without them a token cannot be stored safely. Ask your host or developer to add them.', 'mlab-core' ) . '</p></div>';
		return;
	}
	mlab_core_render_token_table( mlab_core_github_token_entries() );
	mlab_core_render_token_form();
}

/**
 * Prints the table of saved tokens.
 *
 * @param array $entries Stored entries.
 */
function mlab_core_render_token_table( array $entries ): void {
	if ( ! $entries ) {
		return;
	}
	echo '<div class="mlab-scroll"><table class="widefat striped mlab-core-tokens"><thead><tr><th>' . esc_html__( 'Applies to', 'mlab-core' ) . '</th><th>' . esc_html__( 'Token', 'mlab-core' ) . '</th><th>' . esc_html__( 'Last test', 'mlab-core' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'mlab-core' ) . '</span></th></tr></thead><tbody>';
	foreach ( $entries as $entry ) {
		mlab_core_render_token_row( $entry );
	}
	echo '</tbody></table></div>';
}

/**
 * Prints the form for a new token.
 */
function mlab_core_render_token_form(): void {
	mlab_core_form_open( 'mlab_core_token_save' );
	echo '<p><label for="mlab-core-token-scope">' . esc_html__( 'Applies to (organization, or organization/repository)', 'mlab-core' ) . '</label>';
	echo '<input type="text" id="mlab-core-token-scope" name="scope" class="mlab-input" value="momentumlab-ch" autocomplete="off" spellcheck="false" /></p>';
	echo '<p><label for="mlab-core-token">' . esc_html__( 'Token from GitHub', 'mlab-core' ) . '</label>';
	echo '<input type="password" id="mlab-core-token" name="token" class="mlab-input" autocomplete="off" spellcheck="false" placeholder="github_pat_…" /></p>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save and test', 'mlab-core' ) . '</button></p></form>';
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
	$status  = $tested ? (string) ( $entry['status'] ?? '' ) : __( 'not tested yet', 'mlab-core' );
	if ( $expires ) {
		/* translators: %s: date */
		$status .= ' ' . sprintf( __( 'Expires on %s.', 'mlab-core' ), wp_date( get_option( 'date_format' ), $expires ) );
	}
	echo '<tr><td><code>' . esc_html( $scope ) . '</code></td><td>…' . esc_html( (string) ( $entry['hint'] ?? '' ) ) . '</td><td>' . esc_html( $status ) . '</td><td class="mlab-actions">';
	mlab_core_form_open( 'mlab_core_token_test' );
	echo '<input type="hidden" name="scope" value="' . esc_attr( $scope ) . '" /><button type="submit" class="button mlab-core-button">' . esc_html__( 'Test connection', 'mlab-core' ) . '</button></form>';
	mlab_core_form_open( 'mlab_core_token_remove' );
	echo '<input type="hidden" name="scope" value="' . esc_attr( $scope ) . '" /><button type="submit" class="button mlab-core-button">' . esc_html__( 'Remove', 'mlab-core' ) . '</button></form>';
	echo '</td></tr>';
}

/**
 * Posted scope, normalized. Ends the request when invalid.
 */
function mlab_core_posted_token_scope(): string {
	// Nonce was checked in mlab_core_guard().
	$scope = Tokens::scope( isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' === $scope ) {
		mlab_core_finish( 'error', __( 'Please enter an organization (for example momentumlab-ch) or organization/repository.', 'mlab-core' ) );
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
		mlab_core_finish( 'error', __( 'The token is set in wp-config.php and cannot be changed here.', 'mlab-core' ) );
	}
	$scope = mlab_core_posted_token_scope();
	// Nonce was checked in mlab_core_guard(). The token is not sanitized as text on purpose: it must stay exactly as GitHub issued it.
	$token = isset( $_POST['token'] ) ? trim( (string) wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! Tokens::looks_valid( $token ) ) {
		mlab_core_finish( 'error', __( 'This does not look like a GitHub token. A fine-grained token starts with github_pat_. Please copy it again from GitHub.', 'mlab-core' ) );
	}
	$cipher = Tokens::encrypt( $token, mlab_core_github_token_key() );
	if ( '' === $cipher ) {
		mlab_core_finish( 'error', __( 'The token could not be encrypted because the server has neither sodium nor openssl. Ask your host to enable one of them.', 'mlab-core' ) );
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
	mlab_core_finish( $check['ok'] ? 'success' : 'error', sprintf( __( 'Token saved. Test: %s', 'mlab-core' ), $check['message'] ) );
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
			mlab_core_finish( 'error', __( 'The saved token can no longer be read because the security keys of your website have changed. Please enter the token again.', 'mlab-core' ) );
		}
		list( $entries[ $i ], $check ) = mlab_core_token_test_entry( $entry, $token );
		mlab_core_github_token_save_entries( $entries );
		mlab_core_finish( $check['ok'] ? 'success' : 'error', $check['message'] );
	}
	mlab_core_finish( 'error', __( 'This token no longer exists. Reload the page.', 'mlab-core' ) );
}

/**
 * Removes a stored token.
 */
function mlab_core_action_token_remove(): void {
	mlab_core_guard( 'mlab_core_token_remove', array( 'manage_options' ) );
	$scope = mlab_core_posted_token_scope();
	mlab_core_github_token_save_entries( Tokens::without( mlab_core_github_token_entries(), $scope ) );
	mlab_core_finish( 'success', __( 'The token has been removed.', 'mlab-core' ) );
}
