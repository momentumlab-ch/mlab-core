<?php
/**
 * Plugin Name:       MomentumLab Core
 * Description:       Zeigt die MomentumLab-Plugins mit deinem Lizenzschlüssel an, installiert und aktiviert sie mit einem Klick und hält sie aktuell.
 * Version:           0.1.0
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

define( 'MLAB_CORE_VERSION', '0.1.0' );
define( 'MLAB_CORE_FILE', __FILE__ );
define( 'MLAB_CORE_DIR', plugin_dir_path( __FILE__ ) );

require_once MLAB_CORE_DIR . 'inc/options.php';
require_once MLAB_CORE_DIR . 'inc/class-row.php';
require_once MLAB_CORE_DIR . 'inc/class-client.php';
require_once MLAB_CORE_DIR . 'inc/updater.php';
require_once MLAB_CORE_DIR . 'inc/managed-updates.php';
