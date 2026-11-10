<?php
/**
 * Plugin Name:       MomentumLab Core
 * Description:       Shows the MomentumLab plugins with your license key, installs and activates them with one click, and keeps them up to date. Manages the GitHub read tokens for updates from private repositories.
 * Version:           0.5.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            MomentumLab
 * Author URI:        https://momentumlab.ch
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mlab-core
 * Domain Path:       /languages
 * Update URI:        https://github.com/momentumlab-ch/mlab-core/
 *
 * @package mlab-core
 */

defined( 'ABSPATH' ) || exit;

define( 'MLAB_CORE_VERSION', '0.5.0' );
define( 'MLAB_CORE_FILE', __FILE__ );
define( 'MLAB_CORE_DIR', plugin_dir_path( __FILE__ ) );

require_once MLAB_CORE_DIR . 'lib/mlab-license-client/load.php';
require_once MLAB_CORE_DIR . 'inc/options.php';
require_once MLAB_CORE_DIR . 'inc/class-row.php';
require_once MLAB_CORE_DIR . 'inc/class-client.php';
require_once MLAB_CORE_DIR . 'inc/class-tokens.php';
require_once MLAB_CORE_DIR . 'inc/github-tokens.php';
require_once MLAB_CORE_DIR . 'inc/secrets.php';
require_once MLAB_CORE_DIR . 'inc/updater.php';
require_once MLAB_CORE_DIR . 'inc/managed-updates.php';
require_once MLAB_CORE_DIR . 'inc/products.php';
require_once MLAB_CORE_DIR . 'inc/class-health.php';
require_once MLAB_CORE_DIR . 'inc/health.php';

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'mlab-core', false, dirname( plugin_basename( MLAB_CORE_FILE ) ) . '/languages' );
	}
);

if ( is_admin() ) {
	require_once MLAB_CORE_DIR . 'inc/admin/installer.php';
	require_once MLAB_CORE_DIR . 'inc/admin/actions.php';
	require_once MLAB_CORE_DIR . 'inc/admin/menu.php';
	require_once MLAB_CORE_DIR . 'inc/admin/page.php';
	require_once MLAB_CORE_DIR . 'inc/admin/overview.php';
	require_once MLAB_CORE_DIR . 'inc/admin/plugins.php';
	require_once MLAB_CORE_DIR . 'inc/admin/settings.php';
	require_once MLAB_CORE_DIR . 'inc/admin/tokens.php';
	require_once MLAB_CORE_DIR . 'inc/admin/health.php';
}

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'mlab_core_health_daily' );
	}
);
