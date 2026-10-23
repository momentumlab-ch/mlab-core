<?php
/**
 * Removes the plugin's options on uninstall.
 *
 * @package mlab-core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'mlab_core_license_key' );
delete_option( 'mlab_core_catalog' );
delete_option( 'mlab_core_github_tokens' );
delete_option( 'mlab_core_secrets' );
delete_option( 'mlab_core_health' );
delete_option( 'mlab_core_health_settings' );
wp_clear_scheduled_hook( 'mlab_core_health_daily' );
