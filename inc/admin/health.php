<?php
/**
 * Health page: last result, "Check now" and the recipients of the alarm.
 *
 * @package mlab-core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_mlab_core_health_check', 'mlab_core_action_health_check' );
add_action( 'admin_post_mlab_core_health_save', 'mlab_core_action_health_save' );

/**
 * Prints the pill for a check state.
 *
 * @param string $state good, warn or bad.
 */
function mlab_core_health_badge( string $state ): void {
	$labels = array(
		'good' => __( 'Fine', 'mlab-core' ),
		'warn' => __( 'Warning', 'mlab-core' ),
		'bad'  => __( 'Problem', 'mlab-core' ),
	);
	mlab_core_badge( isset( $labels[ $state ] ) ? $state : 'neutral', $labels[ $state ] ?? $state );
}

/**
 * Prints the "Check now" button.
 */
function mlab_core_render_check_button(): void {
	mlab_core_form_open( 'mlab_core_health_check' );
	echo '<button type="submit" class="button button-primary">' . esc_html__( 'Check now', 'mlab-core' ) . '</button></form>';
}

/**
 * Prints the list of checks.
 *
 * @param array $health Result of mlab_core_health().
 */
function mlab_core_render_health_checks( array $health ): void {
	echo '<section class="mlab-card"><h2>' . esc_html__( 'Checks', 'mlab-core' ) . '</h2>';
	if ( ! $health['checks'] ) {
		echo '<p class="mlab-empty">' . esc_html__( 'Not checked yet. Press "Check now" to see the result.', 'mlab-core' ) . '</p></section>';
		return;
	}
	echo '<ul class="mlab-checks">';
	foreach ( $health['checks'] as $check ) {
		echo '<li class="is-' . esc_attr( (string) $check['state'] ) . '">';
		mlab_core_health_badge( (string) $check['state'] );
		echo '<span class="mlab-todo__text"><strong>' . esc_html( (string) $check['label'] ) . '</strong><span class="mlab-muted">' . esc_html( (string) $check['text'] ) . '</span></span></li>';
	}
	echo '</ul></section>';
}

/**
 * Prints the card with the alarm form.
 */
function mlab_core_render_health_alarm(): void {
	echo '<section class="mlab-card"><h2>' . esc_html__( 'Alarm by e-mail', 'mlab-core' ) . '</h2>';
	echo '<p>' . esc_html__( 'If a new problem appears, an e-mail goes to these addresses, at most once a day for the same problem. Write one address per line. Leave it empty to switch the alarm off.', 'mlab-core' ) . '</p>';
	mlab_core_form_open( 'mlab_core_health_save' );
	echo '<p><textarea name="alarm_to" rows="3" class="mlab-input" aria-label="' . esc_attr__( 'Addresses for the alarm, one per line', 'mlab-core' ) . '">' . esc_textarea( mlab_core_health_settings()['alarm_to'] ) . '</textarea></p>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'mlab-core' ) . '</button></p></form></section>';
}

/**
 * Prints the health page.
 */
function mlab_core_render_health(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$health = mlab_core_health();
	$when   = $health['time'] ? mlab_core_health_when( $health['time'] ) : __( 'Not checked yet', 'mlab-core' );
	/* translators: %s: for example "Checked today at 06:00" */
	$sub = sprintf( __( '%s. Checked automatically every morning at 06:00.', 'mlab-core' ), $when );
	mlab_core_page_open( __( 'Health', 'mlab-core' ), $sub, 'mlab_core_render_check_button' );
	echo '<div class="mlab-layout">';
	mlab_core_render_health_checks( $health );
	mlab_core_render_health_alarm();
	echo '</div>';
	mlab_core_page_close();
}

/**
 * Runs the check now.
 */
function mlab_core_action_health_check(): void {
	mlab_core_guard( 'mlab_core_health_check', array( 'activate_plugins' ) );
	$health = mlab_core_health_run();
	mlab_core_finish( 'bad' === $health['state'] ? 'error' : 'success', 'bad' === $health['state'] ? __( 'Checked: there is a problem. See the list for what to do.', 'mlab-core' ) : __( 'Checked: your website is fine.', 'mlab-core' ) );
}

/**
 * Saves the recipients.
 */
function mlab_core_action_health_save(): void {
	mlab_core_guard( 'mlab_core_health_save', array( 'activate_plugins' ) );
	// Nonce was checked in mlab_core_guard().
	$list = isset( $_POST['alarm_to'] ) ? sanitize_textarea_field( wp_unslash( $_POST['alarm_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$to   = mlab_core_health_recipients( $list );
	update_option( 'mlab_core_health_settings', array( 'alarm_to' => implode( "\n", $to ) ), false );
	mlab_core_finish( 'success', $to ? __( 'Saved. The alarm is on.', 'mlab-core' ) : __( 'Saved. The alarm is off.', 'mlab-core' ) );
}
