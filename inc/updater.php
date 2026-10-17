<?php
/**
 * Updates über GitHub Releases (Plugin Update Checker v5).
 *
 * Platzhalter beim Anlegen im Projekt ersetzen:
 * mlab_core        Kleinbuchstaben-Präfix, z. B. mlab_base
 * MLAB_CORE  Grossbuchstaben-Präfix, z. B. MLAB_BASE
 * mlab-core          Repo-Name = Ordnername, z. B. mlab-base
 * https://github.com/momentumlab-ch/mlab-core/      GitHub-Repo-URL mit Schluss-Slash
 * mlab-core.php     Hauptdatei relativ zum Projektordner: mein-plugin.php oder style.css
 * false       true für private Repos, sonst false
 *
 * Die Quelle steht nur hier. Der Header "Update URI" in der Hauptdatei nennt dieselbe URL.
 *
 * @package mlab-core
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

add_action( 'init', 'mlab_core_register_updater' );

/**
 * Registers the update checker. Private repos need the token constant in wp-config.php.
 */
function mlab_core_register_updater(): void {
	$private = false;
	$token   = defined( 'MLAB_CORE_GITHUB_TOKEN' ) ? (string) constant( 'MLAB_CORE_GITHUB_TOKEN' ) : '';

	if ( $private && '' === $token ) {
		add_action( 'admin_notices', 'mlab_core_missing_token_notice' );
		return;
	}

	$checker = PucFactory::buildUpdateChecker(
		'https://github.com/momentumlab-ch/mlab-core/',
		dirname( __DIR__ ) . '/mlab-core.php',
		'mlab-core'
	);
	$checker->setBranch( 'main' );

	$api = $checker->getVcsApi();
	// 2 = Api::REQUIRE_RELEASE_ASSETS: ohne passendes ZIP kein Update, nie der GitHub-Quellcode.
	$api->enableReleaseAssets( '/^mlab-core\.zip$/', 2 );
	if ( $private ) {
		$api->setAuthentication( $token );
	}
}

/**
 * Admin-only hint, shown when a private repo has no token constant.
 */
function mlab_core_missing_token_notice(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'Updates sind nicht aktiv: In wp-config.php fehlt die Konstante MLAB_CORE_GITHUB_TOKEN.', 'mlab-core' )
	);
}
