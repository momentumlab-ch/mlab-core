<?php
/**
 * Catalog of MomentumLab plugins, read from the shared MomentumLab License Client.
 *
 * @package mlab-core
 */

namespace MomentumLab\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Adapter over mlab_license(): the key, the catalog cache and the license state live in the
 * shared client (lib/mlab-license-client), so every MomentumLab plugin on the site sees the same.
 */
final class Client {

	/**
	 * License client, normally mlab_license(); tests pass a fake.
	 *
	 * @var object
	 */
	private object $license;

	/**
	 * Wires the adapter.
	 *
	 * @param object|null $license License client; null for mlab_license().
	 */
	public function __construct( ?object $license = null ) {
		$this->license = $license ?? mlab_license();
	}

	/**
	 * Kept for older callers; the shared client memoises by itself.
	 */
	public static function flush_cache(): void {
	}

	/**
	 * Catalog for the plugin list. Asks the server only when the stored answer is old, or with $refresh.
	 *
	 * @param bool $refresh Ignore the cache.
	 * @return array{products:array,error:string,fetched_at:int,status:string}
	 */
	public function catalog( bool $refresh = false ): array {
		$state = $this->license->refresh( $refresh );
		return array(
			'products'   => $this->plugins( $state['products'] ),
			'error'      => 'valid' === $state['status'] || 'none' === $state['status'] ? '' : (string) $state['message'],
			'fetched_at' => (int) $state['fetched_at'],
			'status'     => (string) $state['status'],
		);
	}

	/**
	 * Update info for one product from the server (contains the signed download URL).
	 *
	 * @param string $slug Product slug.
	 * @return array|\WP_Error
	 */
	public function update_info( string $slug ) {
		$response = wp_remote_get( $this->update_url( $slug ), $this->request_args() );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'mlab_core_unreachable', $this->unreachable() );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) ) {
			$message = is_array( $data ) ? (string) ( $data['message'] ?? '' ) : '';
			return new \WP_Error( 'mlab_core_server_error', '' !== $message ? $message : $this->unreachable() );
		}
		return $data;
	}

	/**
	 * Update endpoint of one product on the license server.
	 *
	 * @param string $slug Product slug.
	 */
	public function update_url( string $slug ): string {
		return $this->license->server_url() . '/wp-json/mlab-ls/v1/update/' . rawurlencode( $slug );
	}

	/**
	 * Headers and timeout for every request to the license server.
	 *
	 * @return array{timeout:int,headers:array<string,string>}
	 */
	public function request_args(): array {
		return $this->license->request_args();
	}

	/**
	 * Merges timeout and headers into HTTP request arguments, e.g. of an update checker.
	 *
	 * @param array $args Request arguments.
	 */
	public function apply_request_args( array $args ): array {
		$core            = $this->request_args();
		$args['timeout'] = $core['timeout'];
		$args['headers'] = array_merge( (array) ( $args['headers'] ?? array() ), $core['headers'] );
		return $args;
	}

	/**
	 * One installable product of the stored catalog, no HTTP.
	 *
	 * @param string $slug Product slug.
	 */
	public function product( string $slug ): ?array {
		foreach ( $this->stored_products() as $product ) {
			if ( $product['slug'] === $slug ) {
				return $product;
			}
		}
		return null;
	}

	/**
	 * Installable products (plugins) of the stored catalog, no HTTP.
	 *
	 * @return array[]
	 */
	public function stored_products(): array {
		return $this->plugins( $this->license->products() );
	}

	/**
	 * Unlocks without a download (e.g. "mlab-seo-pro") that this license includes, no HTTP.
	 *
	 * @return array[]
	 */
	public function unlocks(): array {
		return array_values(
			array_filter(
				$this->license->products(),
				static fn( array $product ): bool => '' === $product['main_file'] && $product['unlocked']
			)
		);
	}

	/**
	 * Only products that ship a plugin with a plain "folder/file.php" main file.
	 *
	 * @param array $products Products of the license state.
	 */
	private function plugins( array $products ): array {
		return array_values(
			array_filter(
				$products,
				static fn( array $product ): bool => '' !== Row::safe_main_file( (string) $product['main_file'] )
			)
		);
	}

	/**
	 * Text when the server cannot be reached.
	 */
	private function unreachable(): string {
		return __( 'The MomentumLab server cannot be reached right now. Please try again in a few minutes.', 'mlab-core' );
	}
}
