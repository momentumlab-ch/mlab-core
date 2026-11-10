<?php
/**
 * Boots the newest copy of the library. Required once by MLAB_License_Client_Registry::client().
 *
 * @package mlab-license-client
 */

namespace MomentumLab\LicenseClient\V1;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-state.php';
require_once __DIR__ . '/class-client.php';
require_once __DIR__ . '/class-admin.php';

$mlab_license_locale = function_exists( 'determine_locale' ) ? determine_locale() : 'en_US';
load_textdomain( 'mlab-license-client', dirname( __DIR__ ) . '/languages/mlab-license-client-' . $mlab_license_locale . '.mo' );

$mlab_license_client = new Client();
$mlab_license_client->hooks();
if ( is_admin() ) {
	( new Admin( $mlab_license_client ) )->hooks();
}

return $mlab_license_client;
