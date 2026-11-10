<?php
/**
 * MomentumLab License Client – loader.
 *
 * Every MomentumLab plugin ships its own copy in lib/mlab-license-client/ and requires this file.
 * All copies on a site register themselves; the newest one is booted once, the others stay idle.
 * This file must stay backwards compatible: an old copy may define the registry first.
 *
 * @package mlab-license-client
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'MLAB_License_Client_Registry', false ) ) {

	/**
	 * Collects the copies of the library and boots the newest.
	 */
	final class MLAB_License_Client_Registry {

		/**
		 * Version => directory of each registered copy.
		 *
		 * @var array<string,string>
		 */
		private static array $copies = array();

		/**
		 * The booted client, null until first use.
		 *
		 * @var object|null
		 */
		private static ?object $client = null;

		/**
		 * Registers one copy.
		 *
		 * @param string $version Semantic version of the copy.
		 * @param string $dir     Directory that holds the copy.
		 */
		public static function add( string $version, string $dir ): void {
			if ( ! isset( self::$copies[ $version ] ) ) {
				self::$copies[ $version ] = $dir;
			}
		}

		/**
		 * Version of the newest registered copy.
		 */
		public static function newest(): string {
			$versions = array_keys( self::$copies );
			usort( $versions, 'version_compare' );
			return (string) end( $versions );
		}

		/**
		 * The client of the newest copy, booted on first call.
		 */
		public static function client(): object {
			if ( null === self::$client ) {
				self::$client = require self::$copies[ self::newest() ] . '/src/bootstrap.php';
			}
			return self::$client;
		}
	}

	/**
	 * The license client shared by all MomentumLab plugins on this site.
	 *
	 * Use it from "plugins_loaded" on, so every copy has registered.
	 *
	 * @return \MomentumLab\LicenseClient\V1\Client
	 */
	function mlab_license() {
		return MLAB_License_Client_Registry::client();
	}

	add_action( 'plugins_loaded', 'mlab_license', 0 );
}

MLAB_License_Client_Registry::add( '1.0.0', __DIR__ );
