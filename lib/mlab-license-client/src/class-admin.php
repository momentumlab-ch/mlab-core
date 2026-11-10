<?php
/**
 * License box for the admin: one form, used by every MomentumLab plugin.
 *
 * @package mlab-license-client
 */

namespace MomentumLab\LicenseClient\V1;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the license box and handles its form.
 */
final class Admin {

	private const ACTION = 'mlab_license_save';

	/**
	 * Client.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Wires the admin to a client.
	 *
	 * @param Client $client Client.
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
	}

	/**
	 * Registers the form handler.
	 */
	public function hooks(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Prints the license box.
	 *
	 * When a central page exists (MomentumLab Core) and this is not it, only a link is shown,
	 * so the key is entered in one place.
	 *
	 * @param string $product Slug whose state the box explains, e.g. "mlab-seo-pro"; '' for none.
	 */
	public function render_box( string $product = '' ): void {
		echo '<div class="mlab-license-box">';
		echo '<h2>' . esc_html__( 'License', 'mlab-license-client' ) . '</h2>';
		$this->render_notice();
		$this->render_status( $product );
		$central = $this->client->admin_url();
		if ( '' !== $central && ! $this->is_current_page( $central ) ) {
			/* translators: %s: link to the MomentumLab page */
			$text = sprintf( __( 'The license key for all MomentumLab plugins is managed on the %s page.', 'mlab-license-client' ), '<a href="' . esc_url( $central ) . '">' . esc_html__( 'MomentumLab', 'mlab-license-client' ) . '</a>' );
			echo '<p>' . wp_kses( $text, array( 'a' => array( 'href' => array() ) ) ) . '</p></div>';
			return;
		}
		$this->render_form();
		echo '</div>';
	}

	/**
	 * Form handler: saves, removes or re-checks the key.
	 */
	public function handle(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change the license.', 'mlab-license-client' ), '', array( 'response' => 403 ) );
		}
		if ( $this->client->key_locked() ) {
			$this->finish( 'error', __( 'The license key is set in wp-config.php and cannot be changed here.', 'mlab-license-client' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		if ( isset( $_POST['mlab_license_check'] ) ) {
			$this->client->refresh( true );
			$this->finish_with_state( __( 'The license has been checked.', 'mlab-license-client' ) );
		}
		$key = isset( $_POST['mlab_license_remove'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['mlab_license_key'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $this->client->save_key( $key ) ) {
			$this->finish( 'error', __( 'This does not look like a license key. Please copy it completely from the e-mail, e.g. MLAB-XXXX-XXXX-XXXX-XXXX.', 'mlab-license-client' ) );
		}
		if ( '' === $key ) {
			$this->finish( 'success', __( 'The license key has been removed.', 'mlab-license-client' ) );
		}
		$this->finish_with_state( __( 'The license key is saved and active.', 'mlab-license-client' ) );
	}

	/**
	 * Status line: active, not active (with the server message) or no key yet.
	 *
	 * @param string $product Slug to explain, '' for none.
	 */
	private function render_status( string $product ): void {
		$state = $this->client->state();
		if ( '' === $this->client->key() ) {
			echo '<p class="mlab-license-status">' . esc_html__( 'No license key yet. Free features work without one.', 'mlab-license-client' ) . '</p>';
			return;
		}
		$active = '' === $product ? State::VALID === $state['status'] : $this->client->unlocked( $product );
		$label  = $active ? __( 'Active', 'mlab-license-client' ) : __( 'Not active', 'mlab-license-client' );
		echo '<p class="mlab-license-status"><code>' . esc_html( $this->client->masked_key() ) . '</code> ';
		echo '<span class="mlab-license-badge is-' . ( $active ? 'good' : 'bad' ) . '">' . esc_html( $label ) . '</span>';
		if ( '' !== $state['message'] ) {
			echo ' ' . esc_html( $state['message'] );
		}
		echo '</p>';
	}

	/**
	 * Key form, or a note when wp-config.php sets the key.
	 */
	private function render_form(): void {
		if ( $this->client->key_locked() ) {
			echo '<p>' . esc_html__( 'The license key is set in wp-config.php.', 'mlab-license-client' ) . '</p>';
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		wp_nonce_field( self::ACTION );
		echo '<p><label for="mlab-license-key">' . esc_html__( 'License key', 'mlab-license-client' ) . '</label><br />';
		echo '<input type="text" id="mlab-license-key" name="mlab_license_key" class="regular-text code" autocomplete="off" spellcheck="false" placeholder="MLAB-XXXX-XXXX-XXXX-XXXX" /></p>';
		echo '<p class="mlab-license-buttons"><button type="submit" class="button button-primary">' . esc_html__( 'Save and check', 'mlab-license-client' ) . '</button>';
		if ( '' !== $this->client->key() ) {
			echo ' <button type="submit" name="mlab_license_check" value="1" class="button">' . esc_html__( 'Check again', 'mlab-license-client' ) . '</button>';
			echo ' <button type="submit" name="mlab_license_remove" value="1" class="button">' . esc_html__( 'Remove key', 'mlab-license-client' ) . '</button>';
		}
		echo '</p><p class="description">' . esc_html__( 'You find the key in the e-mail from MomentumLab. One key covers all MomentumLab plugins on this website.', 'mlab-license-client' ) . '</p></form>';
	}

	/**
	 * Prints and forgets the result of the last form action.
	 */
	private function render_notice(): void {
		$key    = 'mlab_license_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( $key );
		$type = 'success' === $notice['type'] ? 'success' : 'error';
		echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
	}

	/**
	 * Finishes with the server message when the key is not valid, else with $success.
	 *
	 * @param string $success Message for a valid license.
	 */
	private function finish_with_state( string $success ): void {
		$state = $this->client->state();
		if ( State::VALID === $state['status'] ) {
			$this->finish( 'success', $success );
		}
		$this->finish( 'error', '' !== $state['message'] ? $state['message'] : __( 'The license could not be checked. Please try again later.', 'mlab-license-client' ) );
	}

	/**
	 * Remembers a message for the next page view and goes back.
	 *
	 * @param string $type    "success" or "error".
	 * @param string $message Text.
	 */
	private function finish( string $type, string $message ): void {
		set_transient(
			'mlab_license_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Whether the current admin URL is the given page.
	 *
	 * @param string $url Admin page URL.
	 */
	private function is_current_page( string $url ): bool {
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $args );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page check.
		return isset( $args['page'], $_GET['page'] ) && sanitize_key( wp_unslash( $_GET['page'] ) ) === $args['page'];
	}
}
