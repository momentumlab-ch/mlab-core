<?php
/**
 * Overview page: three summary cards, a to-do list and a short help text.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Prints one linked summary card.
 *
 * @param string   $url   Target page.
 * @param string   $title Card title.
 * @param string   $tone  Badge tone.
 * @param string   $badge Badge text.
 * @param string[] $lines Short lines.
 */
function mlab_core_summary_card( string $url, string $title, string $tone, string $badge, array $lines ): void {
	echo '<a class="mlab-card mlab-card--link" href="' . esc_url( $url ) . '"><span class="mlab-card__head"><h2>' . esc_html( $title ) . '</h2>';
	mlab_core_badge( $tone, $badge );
	echo '</span>';
	foreach ( $lines as $line ) {
		echo '<span class="mlab-card__line">' . esc_html( $line ) . '</span>';
	}
	echo '</a>';
}

/**
 * License card.
 *
 * @param array $catalog Result of Client::catalog().
 */
function mlab_core_overview_license_card( array $catalog ): void {
	$names = wp_list_pluck( ( new Client() )->unlocks(), 'name' );
	if ( '' === mlab_license()->key() ) {
		$args = array( 'neutral', __( 'No key', 'mlab-core' ), __( 'No license yet – free plugins work without one', 'mlab-core' ) );
	} elseif ( 'valid' === $catalog['status'] ) {
		$args = array( 'good', __( 'Active', 'mlab-core' ), $names ? implode( ', ', $names ) : __( 'Your license is active.', 'mlab-core' ) );
	} else {
		$args = array( 'bad', __( 'Not active', 'mlab-core' ), __( 'Open the settings to check your license key.', 'mlab-core' ) );
	}
	mlab_core_summary_card( mlab_core_page_url( 'mlab-core-settings' ), __( 'License', 'mlab-core' ), $args[0], $args[1], array( $args[2] ) );
}

/**
 * Text "Checked today at 06:00" for a check time.
 *
 * @param int $time Unix time of the last check.
 */
function mlab_core_health_when( int $time ): string {
	if ( wp_date( 'Y-m-d', $time ) === wp_date( 'Y-m-d' ) ) {
		/* translators: %s: time, e.g. 06:00 */
		return sprintf( __( 'Checked today at %s', 'mlab-core' ), wp_date( 'H:i', $time ) );
	}
	/* translators: %s: date and time */
	return sprintf( __( 'Checked on %s', 'mlab-core' ), wp_date( 'j M Y, H:i', $time ) );
}

/**
 * Checks that are not fine.
 *
 * @param array $health Result of mlab_core_health().
 * @return array<string,array{label:string,state:string,text:string}>
 */
function mlab_core_health_issues( array $health ): array {
	return array_filter(
		$health['checks'],
		static fn( $check ): bool => in_array( $check['state'] ?? '', array( 'warn', 'bad' ), true )
	);
}

/**
 * Website health card.
 */
function mlab_core_overview_health_card(): void {
	$health = mlab_core_health();
	$url    = current_user_can( 'manage_options' ) ? mlab_core_page_url( 'mlab-core-health' ) : mlab_core_page_url();
	$title  = __( 'Website health', 'mlab-core' );
	if ( ! $health['time'] ) {
		mlab_core_summary_card( $url, $title, 'neutral', __( 'Not checked yet', 'mlab-core' ), array( __( 'The first check runs tomorrow morning.', 'mlab-core' ) ) );
		return;
	}
	$issues = count( mlab_core_health_issues( $health ) );
	$total  = count( $health['checks'] );
	$tone   = 'bad' === $health['state'] ? 'bad' : ( $issues ? 'warn' : 'good' );
	$badge  = mlab_core_health_badge_text( $health, $issues );
	/* translators: 1: number of fine checks, 2: number of all checks */
	$count = sprintf( __( '%1$d of %2$d fine', 'mlab-core' ), $total - $issues, $total );
	mlab_core_summary_card( $url, $title, $tone, $badge, array( $count, mlab_core_health_when( $health['time'] ) ) );
}

/**
 * Badge text of the health card: problems count as problems, else warnings.
 *
 * @param array $health Stored health result.
 * @param int   $issues Number of checks that are not fine.
 */
function mlab_core_health_badge_text( array $health, int $issues ): string {
	if ( ! $issues ) {
		return __( 'All good', 'mlab-core' );
	}
	$bad = count( array_filter( $health['checks'], static fn( $check ) => 'bad' === ( $check['state'] ?? '' ) ) );
	if ( $bad ) {
		/* translators: %d: number of checks with a problem */
		return sprintf( _n( '%d problem', '%d problems', $bad, 'mlab-core' ), $bad );
	}
	/* translators: %d: number of checks with a warning */
	return sprintf( _n( '%d warning', '%d warnings', $issues, 'mlab-core' ), $issues );
}

/**
 * Plugins card.
 *
 * @param array $rows Result of mlab_core_plugin_rows().
 */
function mlab_core_overview_plugins_card( array $rows ): void {
	$updates = count( array_filter( array_column( $rows, 'update' ) ) );
	$active  = count( array_filter( array_column( $rows, 'active' ) ) );
	$more    = count( mlab_core_ready_to_install( $rows ) );
	/* translators: %d: number of updates */
	$badge = $updates ? sprintf( _n( '%d update', '%d updates', $updates, 'mlab-core' ), $updates ) : __( 'Up to date', 'mlab-core' );
	/* translators: %d: number of active plugins */
	$lines = array( sprintf( __( '%d active', 'mlab-core' ), $active ) );
	if ( $more ) {
		/* translators: %d: number of unlocked plugins that are not installed */
		$lines[] = sprintf( __( '%d more unlocked', 'mlab-core' ), $more );
	}
	mlab_core_summary_card( mlab_core_page_url( 'mlab-core-plugins' ), __( 'Plugins', 'mlab-core' ), $updates ? 'info' : 'good', $badge, $lines );
}

/**
 * Plugins the license includes that are not installed yet.
 *
 * @param array $rows Result of mlab_core_plugin_rows().
 * @return array[]
 */
function mlab_core_ready_to_install( array $rows ): array {
	return array_filter( $rows, static fn( array $row ): bool => 'install' === $row['state'] );
}

/**
 * One to-do entry.
 *
 * @param string $tone  warn, bad or info.
 * @param string $title Short title.
 * @param string $hint  One line that says what to do.
 * @param string $url   Where the action leads.
 * @param string $cap   Capability needed to open that page.
 * @param string $label Action text.
 * @return array<string,string>
 */
function mlab_core_todo_item( string $tone, string $title, string $hint, string $url, string $cap, string $label ): array {
	return array(
		'tone'  => $tone,
		'title' => $title,
		'hint'  => $hint,
		'url'   => $url,
		'cap'   => $cap,
		'label' => $label,
	);
}

/**
 * Everything that needs a look, most important first.
 *
 * @param array $catalog Result of Client::catalog().
 * @param array $rows    Result of mlab_core_plugin_rows().
 * @return array<int,array<string,string>>
 */
function mlab_core_todo_items( array $catalog, array $rows ): array {
	$items = array();
	if ( '' !== $catalog['error'] ) {
		$items[] = mlab_core_todo_item( 'bad', __( 'License problem', 'mlab-core' ), $catalog['error'], mlab_core_page_url( 'mlab-core-settings' ), 'manage_options', __( 'Open settings', 'mlab-core' ) );
	}
	foreach ( mlab_core_health_issues( mlab_core_health() ) as $check ) {
		$items[] = mlab_core_todo_item( (string) $check['state'], (string) $check['label'], (string) $check['text'], mlab_core_page_url( 'mlab-core-health' ), 'manage_options', __( 'View', 'mlab-core' ) );
	}
	foreach ( $rows as $row ) {
		if ( $row['update'] ) {
			/* translators: %s: plugin name */
			$title = sprintf( __( 'Update %s', 'mlab-core' ), (string) $row['product']['name'] );
			/* translators: 1: new version, 2: installed version */
			$hint    = sprintf( __( 'Version %1$s is ready. You have %2$s.', 'mlab-core' ), (string) $row['product']['version'], (string) $row['have'] );
			$items[] = mlab_core_todo_item( 'info', $title, $hint, admin_url( 'update-core.php' ), 'update_plugins', __( 'Update', 'mlab-core' ) );
		}
	}
	foreach ( mlab_core_ready_to_install( $rows ) as $row ) {
		/* translators: %s: plugin name */
		$title   = sprintf( __( '%s is ready to install', 'mlab-core' ), (string) $row['product']['name'] );
		$items[] = mlab_core_todo_item( 'info', $title, __( 'Your license includes it. Switch it on to install it.', 'mlab-core' ), mlab_core_page_url( 'mlab-core-plugins' ), 'activate_plugins', __( 'Install', 'mlab-core' ) );
	}
	return $items;
}

/**
 * Prints the to-do card.
 *
 * @param array<int,array<string,string>> $items Result of mlab_core_todo_items().
 */
function mlab_core_render_todo( array $items ): void {
	$tones = array(
		'bad'  => __( 'Problem', 'mlab-core' ),
		'warn' => __( 'Warning', 'mlab-core' ),
		'info' => __( 'Notice', 'mlab-core' ),
	);
	echo '<section class="mlab-card"><h2>' . esc_html__( 'To do', 'mlab-core' ) . '</h2>';
	if ( ! $items ) {
		echo '<p class="mlab-empty">' . esc_html__( 'Nothing to do. Everything is up to date.', 'mlab-core' ) . '</p></section>';
		return;
	}
	echo '<ul class="mlab-todo">';
	foreach ( $items as $item ) {
		echo '<li><span class="mlab-dot is-' . esc_attr( $item['tone'] ) . '" aria-hidden="true"></span><span class="mlab-todo__text"><strong><span class="screen-reader-text">' . esc_html( $tones[ $item['tone'] ] ?? '' ) . ': </span>' . esc_html( $item['title'] ) . '</strong><span class="mlab-muted">' . esc_html( $item['hint'] ) . '</span></span>';
		if ( current_user_can( $item['cap'] ) ) {
			echo '<a class="button" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		}
		echo '</li>';
	}
	echo '</ul></section>';
}

/**
 * Prints the overview page.
 */
function mlab_core_render_overview(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$catalog = ( new Client() )->catalog();
	$rows    = mlab_core_plugin_rows( $catalog );
	mlab_core_page_open( 'MomentumLab', __( 'Everything important about your website at a glance.', 'mlab-core' ) );
	echo '<div class="mlab-cards">';
	mlab_core_overview_license_card( $catalog );
	mlab_core_overview_health_card();
	mlab_core_overview_plugins_card( $rows );
	echo '</div>';
	mlab_core_render_todo( mlab_core_todo_items( $catalog, $rows ) );
	mlab_core_help_card( __( 'How MomentumLab works', 'mlab-core' ), __( 'One license key covers all MomentumLab plugins on this website. Updates arrive through WordPress as usual. If the license expires, the plugins keep running – only updates and Pro features pause until you renew.', 'mlab-core' ) );
	mlab_core_page_close();
}
