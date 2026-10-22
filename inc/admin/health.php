<?php
/**
 * Box «Website-Gesundheit» auf der Seite MomentumLab: letztes Ergebnis, «Jetzt prüfen» und Empfänger des Alarms.
 *
 * @package mlab-core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_mlab_core_health_check', 'mlab_core_action_health_check' );
add_action( 'admin_post_mlab_core_health_save', 'mlab_core_action_health_save' );

/**
 * Zeichen für einen Zustand.
 *
 * @param string $state good, warn oder bad.
 */
function mlab_core_health_badge( string $state ): string {
	$labels = array(
		'good' => __( 'In Ordnung', 'mlab-core' ),
		'warn' => __( 'Hinweis', 'mlab-core' ),
		'bad'  => __( 'Problem', 'mlab-core' ),
	);
	return '<span class="mlab-core-health is-' . esc_attr( $state ) . '">' . esc_html( $labels[ $state ] ?? $state ) . '</span>';
}

/**
 * Gibt die Box aus.
 */
function mlab_core_render_health_box(): void {
	$health = mlab_core_health();
	echo '<div class="mlab-core-box"><h2>' . esc_html__( 'Website-Gesundheit', 'mlab-core' ) . '</h2>';
	if ( $health['time'] ) {
		/* translators: %s: Datum und Zeit */
		echo '<p>' . wp_kses_post( mlab_core_health_badge( $health['state'] ) ) . ' ' . esc_html( sprintf( __( 'Geprüft am %s. Die Prüfung läuft jeden Morgen um 06:00.', 'mlab-core' ), wp_date( 'j.n.Y H:i', $health['time'] ) ) ) . '</p>';
		echo '<table class="widefat striped"><tbody>';
		foreach ( $health['checks'] as $check ) {
			echo '<tr><td>' . wp_kses_post( mlab_core_health_badge( (string) $check['state'] ) ) . '</td><th scope="row">' . esc_html( (string) $check['label'] ) . '</th><td>' . esc_html( (string) $check['text'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p>' . esc_html__( 'Noch nicht geprüft. Die Prüfung läuft jeden Morgen um 06:00, oder jetzt per Knopf.', 'mlab-core' ) . '</p>';
	}
	mlab_core_form_open( 'mlab_core_health_check' );
	echo '<p><button type="submit" class="button">' . esc_html__( 'Jetzt prüfen', 'mlab-core' ) . '</button></p></form>';

	$to = mlab_core_health_settings()['alarm_to'];
	echo '<h3>' . esc_html__( 'Alarm per E-Mail', 'mlab-core' ) . '</h3>';
	echo '<p>' . esc_html__( 'Bei einem neuen Problem geht eine E-Mail an diese Adressen, dasselbe Problem höchstens einmal pro Tag. Leer lassen, um den Alarm auszuschalten.', 'mlab-core' ) . '</p>';
	mlab_core_form_open( 'mlab_core_health_save' );
	echo '<p><textarea name="alarm_to" rows="2" class="large-text" aria-label="' . esc_attr__( 'Empfänger des Alarms', 'mlab-core' ) . '">' . esc_textarea( $to ) . '</textarea></p>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Speichern', 'mlab-core' ) . '</button></p></form></div>';
}

/**
 * Prüft sofort.
 */
function mlab_core_action_health_check(): void {
	mlab_core_guard( 'mlab_core_health_check', array( 'activate_plugins' ) );
	$health = mlab_core_health_run();
	mlab_core_finish( 'bad' === $health['state'] ? 'error' : 'success', 'bad' === $health['state'] ? __( 'Geprüft: Es gibt ein Problem, Details unten.', 'mlab-core' ) : __( 'Geprüft: Die Website ist in Ordnung.', 'mlab-core' ) );
}

/**
 * Speichert die Empfänger.
 */
function mlab_core_action_health_save(): void {
	mlab_core_guard( 'mlab_core_health_save', array( 'activate_plugins' ) );
	// Nonce was checked in mlab_core_guard().
	$list = isset( $_POST['alarm_to'] ) ? sanitize_textarea_field( wp_unslash( $_POST['alarm_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$to   = mlab_core_health_recipients( $list );
	update_option( 'mlab_core_health_settings', array( 'alarm_to' => implode( "\n", $to ) ), false );
	mlab_core_finish( 'success', $to ? __( 'Gespeichert. Der Alarm ist eingeschaltet.', 'mlab-core' ) : __( 'Gespeichert. Der Alarm ist ausgeschaltet.', 'mlab-core' ) );
}
