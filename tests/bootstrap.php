<?php
/**
 * Test bootstrap: no WordPress, pure classes plus small function stubs.
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Fake HTTP and options, driven by globals the tests reset in setUp().
$GLOBALS['mlab_core_http_queue'] = array();
$GLOBALS['mlab_core_http_log']   = array();
$GLOBALS['mlab_core_options']    = array();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error { // phpcs:ignore
		public function __construct( public $code = '', public $message = '' ) {}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		$GLOBALS['mlab_core_http_log'][] = array(
			'url'  => $url,
			'args' => $args,
		);
		$next                            = array_shift( $GLOBALS['mlab_core_http_queue'] );
		return $next ?? new WP_Error( 'http_request_failed', 'timeout' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) ? ( $response['code'] ?? '' ) : '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return is_array( $response ) ? ( $response['body'] ?? '' ) : '';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default_value = false ) {
		return $GLOBALS['mlab_core_options'][ $key ] ?? $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['mlab_core_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url() {
		return 'https://site.test';
	}
}

require_once dirname( __DIR__ ) . '/inc/options.php';
require_once dirname( __DIR__ ) . '/inc/products.php';

foreach ( glob( dirname( __DIR__ ) . '/inc/class-*.php' ) as $class_file ) {
	require_once $class_file;
}
