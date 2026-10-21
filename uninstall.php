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
