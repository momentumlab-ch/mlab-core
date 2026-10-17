<?php
/**
 * Mock license server for the end-to-end check. Router script for `php -S`.
 *
 * Env: MOCK_ZIP = path of the demo plugin ZIP, MOCK_BASE = base URL of this server.
 */

$path = (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
$base = getenv( 'MOCK_BASE' ) ?: 'http://127.0.0.1:8898';
$pre  = '/wp-json/mlab-ls/v1';

function mock_json( int $status, array $data ): void {
	http_response_code( $status );
	header( 'Content-Type: application/json' );
	header( 'Cache-Control: no-store' );
	echo json_encode( $data );
	exit;
}

if ( $pre . '/download/demo-plugin' === $path ) {
	header( 'Content-Type: application/zip' );
	header( 'Content-Length: ' . filesize( getenv( 'MOCK_ZIP' ) ) );
	readfile( getenv( 'MOCK_ZIP' ) );
	exit;
}

if ( ( $_SERVER['HTTP_X_MLAB_LICENSE'] ?? '' ) !== 'MLAB-GOOD' ) {
	mock_json(
		401,
		array(
			'code'    => 'mlab_ls_invalid_license',
			'message' => 'Der Lizenzschlüssel ist ungültig. Bitte prüfe ihn oder melde dich bei MomentumLab.',
			'data'    => array( 'status' => 401 ),
		)
	);
}

if ( $pre . '/catalog' === $path ) {
	mock_json(
		200,
		array(
			'products' => array(
				array(
					'slug'        => 'demo-plugin',
					'name'        => 'Demo Plugin',
					'description' => 'Ein kleines Demo-Plugin.',
					'version'     => '1.0.0',
					'main_file'   => 'demo-plugin/demo-plugin.php',
					'unlocked'    => true,
				),
				array(
					'slug'        => 'other-plugin',
					'name'        => 'Other Plugin',
					'description' => 'Nicht freigeschaltet.',
					'version'     => '2.0.0',
					'main_file'   => 'other-plugin/other-plugin.php',
					'unlocked'    => false,
				),
			),
		)
	);
}

if ( $pre . '/update/demo-plugin' === $path ) {
	mock_json(
		200,
		array(
			'name'         => 'Demo Plugin',
			'slug'         => 'demo-plugin',
			'version'      => '9.9.9',
			'download_url' => $base . $pre . '/download/demo-plugin?t=mock',
			'requires'     => '6.4',
			'requires_php' => '8.0',
			'tested'       => '6.8',
			'sections'     => array( 'changelog' => 'Mock' ),
		)
	);
}

mock_json( 404, array( 'code' => 'mlab_ls_not_found', 'message' => 'Nicht gefunden.', 'data' => array( 'status' => 404 ) ) );
