<?php
/**
 * Settings page: license key, included unlocks and the advanced GitHub access.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the list of unlocks the license includes.
 */
function mlab_core_render_unlocks(): void {
	$unlocks = ( new Client() )->unlocks();
	if ( ! $unlocks ) {
		return;
	}
	echo '<section class="mlab-card"><h2>' . esc_html__( 'Included unlocks', 'mlab-core' ) . '</h2><ul class="mlab-list">';
	foreach ( $unlocks as $unlock ) {
		echo '<li>' . esc_html( (string) $unlock['name'] ) . '</li>';
	}
	echo '</ul></section>';
}

/**
 * Prints the collapsed card with the GitHub access.
 */
function mlab_core_render_advanced(): void {
	echo '<details class="mlab-card mlab-details"><summary>' . esc_html__( 'Advanced: GitHub access', 'mlab-core' ) . '</summary><div class="mlab-details__body">';
	echo '<p>' . esc_html__( 'Only needed if MomentumLab asks you to. With a license, updates arrive without GitHub.', 'mlab-core' ) . '</p>';
	mlab_core_render_token_box();
	echo '</div></details>';
}

/**
 * Prints the settings page.
 */
function mlab_core_render_settings(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	mlab_core_page_open( __( 'Settings', 'mlab-core' ), __( 'Usually you only enter the license key here once.', 'mlab-core' ) );
	mlab_license()->render_box();
	mlab_core_render_unlocks();
	mlab_core_render_advanced();
	mlab_core_help_card( __( 'What happens when the license expires?', 'mlab-core' ), __( 'Your website keeps running. Updates and Pro features pause until you renew. If the MomentumLab server is briefly unreachable, everything stays as it is for 7 days.', 'mlab-core' ) );
	mlab_core_page_close();
}
