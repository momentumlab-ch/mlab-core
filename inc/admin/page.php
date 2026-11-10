<?php
/**
 * Shared helpers of the MomentumLab admin pages: page frame, notices, forms, badges and help cards.
 *
 * @package mlab-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prints the message left by the last action, once.
 */
function mlab_core_render_notice(): void {
	$key    = 'mlab_core_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	if ( ! is_array( $notice ) ) {
		return;
	}
	delete_transient( $key );
	$class = 'success' === ( $notice['type'] ?? '' ) ? 'notice-success' : 'notice-error';
	printf( '<div class="notice %s inline" role="status"><p>%s</p></div>', esc_attr( $class ), esc_html( (string) ( $notice['message'] ?? '' ) ) );
}

/**
 * Opens a form that posts to admin-post.php with its nonce.
 *
 * @param string $action Action name.
 * @param string $slug   Product slug, '' for none.
 */
function mlab_core_form_open( string $action, string $slug = '' ): void {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
	if ( '' !== $slug ) {
		echo '<input type="hidden" name="slug" value="' . esc_attr( $slug ) . '" />';
	}
	wp_nonce_field( $action );
}

/**
 * Opens a page: wrapper, title, subtitle, optional action area and the notice of the last action.
 *
 * @param string        $title    Page title.
 * @param string        $subtitle One plain sentence under the title.
 * @param callable|null $actions  Prints buttons at the right of the title.
 */
function mlab_core_page_open( string $title, string $subtitle, ?callable $actions = null ): void {
	echo '<div class="wrap"><div class="mlab-core"><header class="mlab-head"><div>';
	echo '<h1>' . esc_html( $title ) . '</h1><p class="mlab-sub">' . esc_html( $subtitle ) . '</p></div>';
	if ( null !== $actions ) {
		echo '<div class="mlab-head__actions">';
		$actions();
		echo '</div>';
	}
	echo '</header>';
	mlab_core_render_notice();
}

/**
 * Closes the page opened by mlab_core_page_open().
 */
function mlab_core_page_close(): void {
	echo '</div></div>';
}

/**
 * Prints a pill badge.
 *
 * @param string $tone  good, warn, bad, info or neutral.
 * @param string $label Text.
 */
function mlab_core_badge( string $tone, string $label ): void {
	echo '<span class="mlab-badge is-' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
}

/**
 * Prints a card with a short help text.
 *
 * @param string $title Heading.
 * @param string $text  Plain text.
 */
function mlab_core_help_card( string $title, string $text ): void {
	echo '<section class="mlab-card mlab-card--help"><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p></section>';
}
