<?php
/**
 * Website-Gesundheit: einmal täglich im Hintergrund prüfen und bei einem neuen Problem eine E-Mail senden.
 *
 * Geprüft wird: Startseite antwortet, SSL-Zertifikat, ausstehende Updates, PHP-Fehler (debug.log), Backup
 * (UpdraftPlus oder Jetpack) und auffällig viele 404 (MomentumLab SEO). Im Frontend läuft nichts.
 *
 *     $health = mlab_core_health(); // array( 'time' => …, 'state' => good|warn|bad, 'checks' => … )
 *
 * @package mlab-core
 */

use MomentumLab\Core\Health;

defined( 'ABSPATH' ) || exit;

const MLAB_CORE_HEALTH_CRON = 'mlab_core_health_daily';

add_action( MLAB_CORE_HEALTH_CRON, 'mlab_core_health_run' );
if ( is_admin() || wp_doing_cron() ) {
	add_action( 'init', 'mlab_core_health_schedule' );
}

/**
 * Plant die tägliche Prüfung (06:00 Ortszeit).
 */
function mlab_core_health_schedule(): void {
	if ( ! wp_next_scheduled( MLAB_CORE_HEALTH_CRON ) ) {
		$local = strtotime( 'tomorrow 06:00', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Ortszeit für «06:00».
		wp_schedule_event( $local - (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', MLAB_CORE_HEALTH_CRON );
	}
}

/**
 * Einstellungen des Alarms: Empfänger (leer = aus).
 *
 * @return array{alarm_to: string}
 */
function mlab_core_health_settings(): array {
	$s = get_option( 'mlab_core_health_settings', array() );
	$s = is_array( $s ) ? $s : array();
	return array( 'alarm_to' => (string) ( $s['alarm_to'] ?? '' ) );
}

/**
 * Gültige Empfänger, höchstens 5.
 *
 * @param string $value Liste (Komma, Semikolon, Zeilenumbruch).
 * @return list<string>
 */
function mlab_core_health_recipients( string $value ): array {
	$parts = preg_split( '/[\s,;]+/', $value );
	$mails = array_filter( array_map( 'sanitize_email', is_array( $parts ) ? $parts : array() ), 'is_email' );
	return array_slice( array_values( array_unique( $mails ) ), 0, 5 );
}

/**
 * Letztes Ergebnis.
 *
 * @return array{time:int,state:string,checks:array<string,array{label:string,state:string,text:string}>,reported:array<string,int>}
 */
function mlab_core_health(): array {
	$h = get_option( 'mlab_core_health', array() );
	$h = is_array( $h ) ? $h : array();
	return array(
		'time'     => (int) ( $h['time'] ?? 0 ),
		'state'    => (string) ( $h['state'] ?? '' ),
		'checks'   => (array) ( $h['checks'] ?? array() ),
		'reported' => (array) ( $h['reported'] ?? array() ),
	);
}

/**
 * Ein Ergebnis.
 *
 * @param string $label Bezeichnung.
 * @param string $state good, warn oder bad.
 * @param string $text  Was los ist, und was zu tun ist.
 * @return array{label:string,state:string,text:string}
 */
function mlab_core_health_item( string $label, string $state, string $text ): array {
	return array(
		'label' => $label,
		'state' => $state,
		'text'  => $text,
	);
}

/**
 * Startseite: antwortet sie mit 200, und wie schnell?
 *
 * @return array{label:string,state:string,text:string}
 */
function mlab_core_health_check_home(): array {
	$label = __( 'Website erreichbar', 'mlab-core' );
	$start = microtime( true );
	$res   = wp_remote_get(
		add_query_arg( 'mlab-health', time(), home_url( '/' ) ),
		array(
			'timeout'     => 15,
			'redirection' => 2,
		)
	);
	$secs  = round( microtime( true ) - $start, 1 );
	if ( is_wp_error( $res ) ) {
		/* translators: %s: Fehlermeldung */
		return mlab_core_health_item( $label, 'bad', sprintf( __( 'Die Startseite antwortet nicht: %s', 'mlab-core' ), $res->get_error_message() ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( 200 !== $code ) {
		/* translators: %d: HTTP-Status */
		return mlab_core_health_item( $label, 'bad', sprintf( __( 'Die Startseite antwortet mit Fehler %d.', 'mlab-core' ), $code ) );
	}
	/* translators: %s: Sekunden */
	$text = sprintf( __( 'Antwortet in %s s.', 'mlab-core' ), number_format_i18n( $secs, 1 ) );
	return mlab_core_health_item( $label, $secs > 5 ? 'warn' : 'good', $secs > 5 ? $text . ' ' . __( 'Das ist langsam: Caching und Server prüfen.', 'mlab-core' ) : $text );
}

/**
 * SSL-Zertifikat: Tage bis zum Ablauf.
 *
 * @return array{label:string,state:string,text:string}
 */
function mlab_core_health_check_ssl(): array {
	$label = __( 'SSL-Zertifikat', 'mlab-core' );
	$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) || '' === $host ) {
		return mlab_core_health_item( $label, 'bad', __( 'Die Website läuft ohne HTTPS.', 'mlab-core' ) );
	}
	$days    = null;
	$context = stream_context_create( array( 'ssl' => array( 'capture_peer_cert' => true, 'SNI_enabled' => true, 'peer_name' => $host ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- kurze Optionsliste.
	$socket  = @stream_socket_client( 'ssl://' . $host . ':443', $errno, $error, 10, STREAM_CLIENT_CONNECT, $context ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Fehler werden als Ergebnis gemeldet.
	if ( $socket ) {
		$params = stream_context_get_params( $socket );
		$cert   = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] ?? '' );
		if ( is_array( $cert ) && ! empty( $cert['validTo_time_t'] ) ) {
			$days = (int) floor( ( (int) $cert['validTo_time_t'] - time() ) / DAY_IN_SECONDS );
		}
		fclose( $socket ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Socket, keine Datei.
	}
	$state = Health::ssl_state( $days );
	if ( null === $days ) {
		return mlab_core_health_item( $label, $state, __( 'Das Ablaufdatum liess sich nicht lesen.', 'mlab-core' ) );
	}
	/* translators: 1: Tage, 2: Datum */
	$text = sprintf( __( 'Gültig noch %1$d Tage (bis %2$s).', 'mlab-core' ), $days, wp_date( 'j.n.Y', time() + $days * DAY_IN_SECONDS ) );
	return mlab_core_health_item( $label, $state, 'good' === $state ? $text : $text . ' ' . __( 'Erneuert sich das Zertifikat nicht von selbst? Beim Hoster (z. B. Plesk, Let’s Encrypt) prüfen.', 'mlab-core' ) );
}

/**
 * Ausstehende Updates von WordPress, Plugins und Themes.
 *
 * @return array{label:string,state:string,text:string}
 */
function mlab_core_health_check_updates(): array {
	$label   = __( 'Updates', 'mlab-core' );
	$plugins = get_site_transient( 'update_plugins' );
	$themes  = get_site_transient( 'update_themes' );
	$core    = get_site_transient( 'update_core' );
	$p       = is_object( $plugins ) && ! empty( $plugins->response ) ? count( (array) $plugins->response ) : 0;
	$t       = is_object( $themes ) && ! empty( $themes->response ) ? count( (array) $themes->response ) : 0;
	$c       = 0;
	if ( is_object( $core ) && ! empty( $core->updates ) ) {
		foreach ( (array) $core->updates as $update ) {
			if ( 'upgrade' === ( $update->response ?? '' ) ) {
				$c = 1;
			}
		}
	}
	if ( 0 === $p + $t + $c ) {
		return mlab_core_health_item( $label, 'good', __( 'Alles aktuell.', 'mlab-core' ) );
	}
	$parts = array();
	if ( $p ) {
		/* translators: %d: Anzahl */
		$parts[] = sprintf( _n( '%d Plugin-Update', '%d Plugin-Updates', $p, 'mlab-core' ), $p );
	}
	if ( $t ) {
		/* translators: %d: Anzahl */
		$parts[] = sprintf( _n( '%d Theme-Update', '%d Theme-Updates', $t, 'mlab-core' ), $t );
	}
	if ( $c ) {
		$parts[] = __( 'WordPress-Update', 'mlab-core' );
	}
	$text = implode( ', ', $parts ) . ' ' . __( 'ausstehend.', 'mlab-core' );
	return mlab_core_health_item( $label, 'warn', $text );
}

/**
 * PHP-Fehler der letzten 24 Stunden aus der debug.log (nur die letzten 256 KB werden gelesen).
 *
 * @return array{label:string,state:string,text:string}
 */
function mlab_core_health_check_errors(): array {
	$label = __( 'PHP-Fehler', 'mlab-core' );
	$file  = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
	if ( ! WP_DEBUG_LOG || ! is_readable( $file ) ) {
		return mlab_core_health_item( $label, 'good', __( 'Kein Fehlerprotokoll aktiv.', 'mlab-core' ) );
	}
	$size   = (int) filesize( $file );
	$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- nur das Ende der Datei lesen.
	if ( ! $handle ) {
		return mlab_core_health_item( $label, 'good', __( 'Kein Fehlerprotokoll aktiv.', 'mlab-core' ) );
	}
	fseek( $handle, max( 0, $size - 262144 ) );
	$tail = (string) stream_get_contents( $handle );
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- siehe fopen.
	$count = Health::fatal_count( $tail, time() );
	if ( 0 === $count ) {
		return mlab_core_health_item( $label, 'good', __( 'Keine schweren Fehler in den letzten 24 Stunden.', 'mlab-core' ) );
	}
	/* translators: %d: Anzahl */
	return mlab_core_health_item( $label, 'bad', sprintf( _n( '%d schwerer PHP-Fehler in den letzten 24 Stunden. Details in wp-content/debug.log.', '%d schwere PHP-Fehler in den letzten 24 Stunden. Details in wp-content/debug.log.', $count, 'mlab-core' ), $count ) );
}

/**
 * Backup: letztes UpdraftPlus-Backup oder Jetpack Backup aktiv.
 *
 * @return array{label:string,state:string,text:string}|null Null, wenn kein bekanntes Backup-Plugin läuft.
 */
function mlab_core_health_check_backup(): ?array {
	$label = __( 'Backup', 'mlab-core' );
	$last  = get_option( 'updraft_last_backup' );
	if ( is_array( $last ) && ! empty( $last['backup_time'] ) ) {
		$days = (int) floor( ( time() - (int) $last['backup_time'] ) / DAY_IN_SECONDS );
		/* translators: %d: Tage */
		$text = sprintf( _n( 'Letztes Backup (UpdraftPlus) vor %d Tag.', 'Letztes Backup (UpdraftPlus) vor %d Tagen.', $days, 'mlab-core' ), $days );
		return mlab_core_health_item( $label, $days > 8 ? 'bad' : 'good', $days > 8 ? $text . ' ' . __( 'Bitte den Zeitplan in UpdraftPlus prüfen.', 'mlab-core' ) : $text );
	}
	if ( class_exists( 'Jetpack' ) && method_exists( 'Jetpack', 'is_connection_ready' ) && \Jetpack::is_connection_ready() ) {
		return mlab_core_health_item( $label, 'good', __( 'Jetpack ist verbunden. Den Stand der Backups zeigt Jetpack › VaultPress Backup.', 'mlab-core' ) );
	}
	return null;
}

/**
 * Auffällig viele 404 in den letzten 24 Stunden (aus MomentumLab SEO).
 *
 * @return array{label:string,state:string,text:string}|null Null ohne MomentumLab SEO.
 */
function mlab_core_health_check_404(): ?array {
	if ( ! class_exists( '\\MomentumLab\\SEO\\Redirects' ) ) {
		return null;
	}
	$label = __( 'Adressen ins Leere (404)', 'mlab-core' );
	$since = time() - DAY_IN_SECONDS;
	$new   = array_filter( \MomentumLab\SEO\Redirects::log(), static fn( $row ) => (int) $row['last'] >= $since );
	$count = count( $new );
	/* translators: %d: Anzahl Adressen */
	$text = sprintf( _n( '%d Adresse in den letzten 24 Stunden.', '%d Adressen in den letzten 24 Stunden.', $count, 'mlab-core' ), $count );
	return mlab_core_health_item( $label, $count >= 20 ? 'warn' : 'good', $count >= 20 ? $text . ' ' . __( 'Unter SEO › Weiterleitungen prüfen.', 'mlab-core' ) : $text );
}

/**
 * Führt alle Prüfungen aus, speichert das Ergebnis und meldet neue Probleme per E-Mail.
 *
 * @return array{time:int,state:string,checks:array<string,array{label:string,state:string,text:string}>,reported:array<string,int>}
 */
function mlab_core_health_run(): array {
	$checks = array_filter(
		array(
			'home'    => mlab_core_health_check_home(),
			'ssl'     => mlab_core_health_check_ssl(),
			'updates' => mlab_core_health_check_updates(),
			'errors'  => mlab_core_health_check_errors(),
			'backup'  => mlab_core_health_check_backup(),
			'404'     => mlab_core_health_check_404(),
		)
	);

	/**
	 * Eigene Prüfungen ergänzen: Schlüssel => array( label, state (good|warn|bad), text ).
	 *
	 * @param array $checks Ergebnisse.
	 */
	$checks   = (array) apply_filters( 'mlab_core_health_checks', $checks );
	$old      = mlab_core_health();
	$reported = $old['reported'];
	$report   = Health::to_report( $checks, $reported, time() );
	if ( $report && mlab_core_health_alarm( $checks, $report ) ) {
		foreach ( $report as $key ) {
			$reported[ $key ] = time();
		}
	}
	// Erledigte Probleme vergessen, damit sie beim nächsten Mal wieder sofort gemeldet werden.
	foreach ( array_keys( $reported ) as $key ) {
		if ( 'bad' !== ( $checks[ $key ]['state'] ?? '' ) ) {
			unset( $reported[ $key ] );
		}
	}
	$health = array(
		'time'     => time(),
		'state'    => Health::overall( $checks ),
		'checks'   => $checks,
		'reported' => $reported,
	);
	update_option( 'mlab_core_health', $health, false );
	return $health;
}

/**
 * Sendet den Alarm an die Empfänger.
 *
 * @param array<string,array{label:string,state:string,text:string}> $checks Ergebnisse.
 * @param string[]                                                   $keys   Zu meldende Prüfungen.
 */
function mlab_core_health_alarm( array $checks, array $keys ): bool {
	$to = mlab_core_health_recipients( mlab_core_health_settings()['alarm_to'] );
	if ( ! $to ) {
		return false;
	}
	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$lines = array();
	foreach ( $keys as $key ) {
		$lines[] = '• ' . $checks[ $key ]['label'] . ': ' . $checks[ $key ]['text'];
	}
	/* translators: %s: Name der Website */
	$subject = sprintf( __( 'Problem auf %s', 'mlab-core' ), $site );
	$body    = implode( "\n", $lines ) . "\n\n" . __( 'Übersicht:', 'mlab-core' ) . ' ' . admin_url( 'admin.php?page=mlab-core' ) . "\n";
	return wp_mail( $to, $subject, $body );
}
