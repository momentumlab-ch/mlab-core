<?php
/**
 * License client: one key and one license state per website, shared by all MomentumLab plugins.
 *
 * @package mlab-license-client
 */

namespace MomentumLab\LicenseClient\V1;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the key, asks the license server and answers which products are unlocked.
 *
 * Requests run only in the admin, in cron or in WP-CLI, never in the frontend.
 */
final class Client {

	public const VERSION = '1.1.0';

	private const OPTION_KEY   = 'mlab_license_key';
	private const OPTION_STATE = 'mlab_license_state';
	private const CRON         = 'mlab_license_refresh';
	private const TIMEOUT      = 5;
	private const DEFAULT_URL  = 'https://momentumlab.ch';
	private const OPTION_PING  = 'mlab_license_ping_at';
	private const CRON_PING    = 'mlab_license_ping_refresh';

	/**
	 * State memoised for this request.
	 *
	 * @var array|null
	 */
	private ?array $state = null;

	/**
	 * True only while the refresh route runs, because REST requests are not admin requests.
	 *
	 * @var bool
	 */
	private bool $in_refresh_route = false;

	/**
	 * Registers cron and the one-time takeover of keys stored by older plugin versions.
	 */
	public function hooks(): void {
		add_action( self::CRON, array( $this, 'refresh' ) );
		add_action( 'admin_init', array( $this, 'schedule' ) );
		add_action( 'admin_init', array( $this, 'adopt_legacy_key' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( self::CRON_PING, array( $this, 'refresh_now' ) );
	}

	/**
	 * Re-check without cache, used by the delayed check after a throttled ping.
	 */
	public function refresh_now(): void {
		$this->refresh( true );
	}

	/**
	 * Registers POST mlab-license/v1/refresh. It only triggers a re-check and never returns data.
	 */
	public function register_routes(): void {
		register_rest_route(
			'mlab-license/v1',
			'/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_refresh_route' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Route callback: a throttled re-check. The answer is always just { "ok": true }.
	 */
	public function handle_refresh_route(): array {
		if ( '' === $this->key() ) {
			return array( 'ok' => true );
		}
		$now  = time();
		$last = get_option( self::OPTION_PING, null );
		if ( ! State::ping_allowed( $last, $now ) ) {
			// A second change within the interval must not get lost: check again once the interval is over.
			if ( false === wp_next_scheduled( self::CRON_PING ) ) {
				wp_schedule_single_event( (int) $last + State::PING_INTERVAL, self::CRON_PING );
			}
			return array( 'ok' => true );
		}
		update_option( self::OPTION_PING, $now, false );
		$this->in_refresh_route = true;
		try {
			$this->refresh( true );
		} finally {
			$this->in_refresh_route = false;
		}
		return array( 'ok' => true );
	}

	/**
	 * Keeps the twice-daily check scheduled.
	 */
	public function schedule(): void {
		if ( false === wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON );
		}
	}

	/**
	 * License key: a constant in wp-config.php wins over the stored option.
	 */
	public function key(): string {
		foreach ( array( 'MLAB_LICENSE_KEY', 'MLAB_CORE_LICENSE_KEY' ) as $constant ) {
			if ( defined( $constant ) && '' !== (string) constant( $constant ) ) {
				return trim( (string) constant( $constant ) );
			}
		}
		return (string) get_option( self::OPTION_KEY, '' );
	}

	/**
	 * Whether wp-config.php sets the key, so no form may change it.
	 */
	public function key_locked(): bool {
		return defined( 'MLAB_LICENSE_KEY' ) || defined( 'MLAB_CORE_LICENSE_KEY' );
	}

	/**
	 * Key for display: only the last four characters.
	 */
	public function masked_key(): string {
		$key = $this->key();
		return '' === $key ? '' : '••••' . substr( $key, -4 );
	}

	/**
	 * Saves a new key ('' removes it) and checks it at once.
	 *
	 * @param string $key Key as typed by the user.
	 * @return bool False when the key has an impossible format; nothing is stored then.
	 */
	public function save_key( string $key ): bool {
		$key = strtoupper( trim( $key ) );
		if ( '' !== $key && 1 !== preg_match( '/^[A-Z0-9-]{8,64}$/', $key ) ) {
			return false;
		}
		update_option( self::OPTION_KEY, $key, false );
		if ( '' === $key ) {
			$this->write( State::blank() );
			return true;
		}
		$this->refresh( true );
		return true;
	}

	/**
	 * Current state, read from the database once per request.
	 */
	public function state(): array {
		if ( null === $this->state ) {
			$this->state = State::normalize( get_option( self::OPTION_STATE, array() ) );
		}
		return $this->state;
	}

	/**
	 * Whether the license unlocks a product or feature. No HTTP; safe in the frontend.
	 *
	 * @param string $slug Slug on the license server, e.g. "mlab-seo-pro".
	 */
	public function unlocked( string $slug ): bool {
		return State::unlocked( $this->state(), $slug, time() );
	}

	/**
	 * Products of the last catalog. No HTTP.
	 *
	 * @return array[]
	 */
	public function products(): array {
		return $this->state()['products'];
	}

	/**
	 * Asks the server when the stored answer is old, or always with $force.
	 *
	 * @param bool $force Ignore the cache.
	 */
	public function refresh( bool $force = false ): array {
		if ( ! $this->may_request() || ( ! $force && ! State::due( $this->state(), time() ) ) ) {
			return $this->state();
		}
		$before = $this->state();
		$result = $this->get_json( $this->server_url() . '/wp-json/mlab-ls/v1/catalog' );
		$state  = State::after_response( $before, $result['code'], $result['body'], time(), $this->unreachable_message() );
		$this->write( $state );
		if ( $this->unlocked_slugs( $before ) !== $this->unlocked_slugs( $state ) ) {
			/**
			 * Fires when the set of unlocked products changed, e.g. after a renewal or an expiry.
			 *
			 * @param array $state New license state.
			 */
			do_action( 'mlab_license_changed', $state );
		}
		return $state;
	}

	/**
	 * Prints the shared license box. Plugins call this on their settings page.
	 *
	 * @param string $product Slug whose state the box explains, e.g. "mlab-seo-pro"; '' for none.
	 */
	public function render_box( string $product = '' ): void {
		( new Admin( $this ) )->render_box( $product );
	}

	/**
	 * Base URL of the license server, without trailing slash.
	 */
	public function server_url(): string {
		foreach ( array( 'MLAB_LICENSE_SERVER_URL', 'MLAB_CORE_SERVER_URL' ) as $constant ) {
			if ( defined( $constant ) ) {
				return rtrim( (string) constant( $constant ), '/' );
			}
		}
		return self::DEFAULT_URL;
	}

	/**
	 * Headers and timeout for every request to the license server (also for update checks).
	 *
	 * @return array{timeout:int,headers:array<string,string>}
	 */
	public function request_args(): array {
		$headers = array( 'X-MLAB-Site' => home_url( '/' ) );
		if ( '' !== $this->key() ) {
			$headers['X-MLAB-License'] = $this->key();
		}
		return array(
			'timeout' => self::TIMEOUT,
			'headers' => $headers,
		);
	}

	/**
	 * Takes over a key that MomentumLab Core or MomentumLab SEO stored before this library existed.
	 */
	public function adopt_legacy_key(): void {
		if ( '' !== (string) get_option( self::OPTION_KEY, '' ) || $this->key_locked() ) {
			return;
		}
		$seo    = get_option( 'mlab_seo_license', array() );
		$legacy = array( (string) get_option( 'mlab_core_license_key', '' ), is_array( $seo ) ? (string) ( $seo['key'] ?? '' ) : '' );
		foreach ( $legacy as $key ) {
			if ( '' !== $key && $this->save_key( $key ) ) {
				return;
			}
		}
	}

	/**
	 * Admin page where the key is managed: MomentumLab Core registers its page here.
	 *
	 * @return string URL, '' when no central page exists (each plugin shows its own box).
	 */
	public function admin_url(): string {
		/**
		 * Filters the URL of the central license page.
		 *
		 * @param string $url URL or ''.
		 */
		return (string) apply_filters( 'mlab_license_admin_url', '' );
	}

	/**
	 * Requests only where a slow server cannot slow down visitors.
	 */
	private function may_request(): bool {
		return $this->in_refresh_route || is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI );
	}

	/**
	 * GET with the license headers.
	 *
	 * @param string $url Absolute URL.
	 * @return array{code:int,body:mixed} Code 0 when the server was not reached.
	 */
	private function get_json( string $url ): array {
		$response = wp_remote_get( $url, $this->request_args() );
		if ( is_wp_error( $response ) ) {
			return array(
				'code' => 0,
				'body' => null,
			);
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => json_decode( (string) wp_remote_retrieve_body( $response ), true ),
		);
	}

	/**
	 * Saves the state and resets the memo.
	 *
	 * @param array $state New state.
	 */
	private function write( array $state ): void {
		update_option( self::OPTION_STATE, $state, false );
		$this->state = State::normalize( $state );
	}

	/**
	 * Sorted unlocked slugs of a state, to detect changes.
	 *
	 * @param array $state State.
	 * @return string[]
	 */
	private function unlocked_slugs( array $state ): array {
		$slugs = array();
		foreach ( State::normalize( $state )['products'] as $product ) {
			if ( $product['unlocked'] ) {
				$slugs[] = $product['slug'];
			}
		}
		sort( $slugs );
		return $slugs;
	}

	/**
	 * Message when the server could not be reached.
	 */
	private function unreachable_message(): string {
		return __( 'The MomentumLab server cannot be reached right now. Unlocked features keep working for up to 7 days.', 'mlab-license-client' );
	}
}
