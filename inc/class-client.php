<?php
/**
 * Talks to the MomentumLab license server and caches the catalog.
 *
 * @package mlab-core
 */

namespace MomentumLab\Core;

defined( 'ABSPATH' ) || exit;

/**
 * License server client. Only called from the admin or the WordPress update check.
 */
final class Client {

	private const OPTION    = 'mlab_core_catalog';
	private const RETRY_GAP = 300;

	/**
	 * Stored catalog memoised for the current request, null when not loaded.
	 *
	 * @var array|null
	 */
	private static ?array $memo = null;

	/**
	 * Forgets the memoised stored catalog.
	 */
	public static function flush_cache(): void {
		self::$memo = null;
	}

	/**
	 * Catalog for the list, fetched again when older than the cache time or on refresh.
	 *
	 * @param bool $refresh Ignore the cache.
	 * @return array{products:array,error:string,fetched_at:int}
	 */
	public function catalog( bool $refresh = false ): array {
		$stored = $this->stored();
		if ( $refresh || $this->is_stale( $stored ) ) {
			$stored = $this->fetch( $stored );
			$this->write( $stored );
		}
		return array(
			'products'   => $stored['products'],
			'error'      => $stored['error'],
			'fetched_at' => $stored['fetched_at'],
		);
	}

	/**
	 * Update info for one product from the server (contains the download URL).
	 *
	 * @param string $slug Product slug.
	 * @return array|\WP_Error
	 */
	public function update_info( string $slug ) {
		$result = $this->get_json( $this->update_url( $slug ) );
		if ( 0 === $result['status'] ) {
			return new \WP_Error( 'mlab_core_unreachable', $this->default_error() );
		}
		if ( 200 !== $result['status'] || ! is_array( $result['data'] ) ) {
			return new \WP_Error( 'mlab_core_server_error', $this->error_message( $result['data'] ) );
		}
		return $result['data'];
	}

	/**
	 * Update endpoint of one product on the license server.
	 *
	 * @param string $slug Product slug.
	 */
	public function update_url( string $slug ): string {
		return mlab_core_server_url() . '/wp-json/mlab-ls/v1/update/' . rawurlencode( $slug );
	}

	/**
	 * Arguments for every server request.
	 *
	 * @return array{timeout:int,headers:array<string,string>}
	 */
	public function request_args(): array {
		$headers = array( 'X-MLAB-Site' => home_url() );
		$key     = mlab_core_license_key();
		if ( '' !== $key ) {
			$headers['X-MLAB-License'] = $key;
		}
		return array(
			'timeout' => (int) mlab_core_option( 'http_timeout' ),
			'headers' => $headers,
		);
	}

	/**
	 * Merges timeout and headers of the core into HTTP request arguments.
	 *
	 * @param array $args Request arguments, e.g. from an update checker.
	 */
	public function apply_request_args( array $args ): array {
		$core            = $this->request_args();
		$args['timeout'] = $core['timeout'];
		$args['headers'] = array_merge( (array) ( $args['headers'] ?? array() ), $core['headers'] );
		return $args;
	}

	/**
	 * One product from the stored catalog, no HTTP.
	 *
	 * @param string $slug Product slug.
	 */
	public function product( string $slug ): ?array {
		foreach ( $this->stored_products() as $product ) {
			if ( ( $product['slug'] ?? '' ) === $slug ) {
				return $product;
			}
		}
		return null;
	}

	/**
	 * Products of the stored catalog, no HTTP.
	 *
	 * @return array[]
	 */
	public function stored_products(): array {
		return $this->stored()['products'];
	}

	/**
	 * Stored catalog with all keys present.
	 *
	 * @return array{products:array,error:string,fetched_at:int,failed_at:int}
	 */
	private function stored(): array {
		if ( null === self::$memo ) {
			$stored     = get_option( self::OPTION, array() );
			self::$memo = array(
				'products'   => (array) ( $stored['products'] ?? array() ),
				'error'      => (string) ( $stored['error'] ?? '' ),
				'fetched_at' => (int) ( $stored['fetched_at'] ?? 0 ),
				'failed_at'  => (int) ( $stored['failed_at'] ?? 0 ),
			);
		}
		return self::$memo;
	}

	/**
	 * Saves the stored catalog and drops the memo.
	 *
	 * @param array $stored New stored state.
	 */
	private function write( array $stored ): void {
		update_option( self::OPTION, $stored, false );
		self::flush_cache();
	}

	/**
	 * GET request with the core arguments, status code and decoded JSON body.
	 *
	 * @param string $url Absolute URL.
	 * @return array{status:int,data:mixed} Status 0 and no data when the server was not reached.
	 */
	private function get_json( string $url ): array {
		$response = wp_remote_get( $url, $this->apply_request_args( array() ) );
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 0,
				'data'   => null,
			);
		}
		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'data'   => json_decode( (string) wp_remote_retrieve_body( $response ), true ),
		);
	}

	/**
	 * Fetch is due when the cache expired; after a failure wait a few minutes first.
	 *
	 * @param array $stored Stored catalog.
	 */
	private function is_stale( array $stored ): bool {
		$last = max( $stored['fetched_at'], $stored['failed_at'] );
		$wait = '' === $stored['error'] ? (int) mlab_core_option( 'catalog_ttl' ) : self::RETRY_GAP;
		return time() - $last >= $wait;
	}

	/**
	 * Requests the catalog and returns the new stored state.
	 *
	 * @param array $stored Current stored state, kept on soft errors.
	 */
	private function fetch( array $stored ): array {
		$result = $this->get_json( mlab_core_server_url() . '/wp-json/mlab-ls/v1/catalog' );
		$status = $result['status'];
		$data   = $result['data'];
		if ( 200 === $status && is_array( $data ) && isset( $data['products'] ) && is_array( $data['products'] ) ) {
			return array(
				'products'   => $this->safe_products( $data['products'] ),
				'error'      => '',
				'fetched_at' => time(),
				'failed_at'  => 0,
			);
		}
		$stored['products']  = Row::catalog_after_error( $stored['products'], $status );
		$stored['error']     = $this->error_message( $data );
		$stored['failed_at'] = time();
		return $stored;
	}

	/**
	 * Drops products whose main file is not a plain "folder/file.php".
	 *
	 * @param array $products Products from the server.
	 */
	private function safe_products( array $products ): array {
		return array_values(
			array_filter(
				$products,
				static fn( $product ) => is_array( $product ) && '' !== Row::safe_main_file( (string) ( $product['main_file'] ?? '' ) )
			)
		);
	}

	/**
	 * Server message if there is one, else the standard text.
	 *
	 * @param mixed $data Decoded response body.
	 */
	private function error_message( $data ): string {
		$message = is_array( $data ) ? (string) ( $data['message'] ?? '' ) : '';
		return '' !== $message ? $message : $this->default_error();
	}

	/**
	 * Standard text when the server cannot be reached.
	 */
	private function default_error(): string {
		return __( 'Der MomentumLab-Server ist gerade nicht erreichbar. Die Liste zeigt den letzten bekannten Stand.', 'mlab-core' );
	}
}
