<?php
/**
 * Public API: which products the license of this site unlocks.
 *
 * @package mlab-core
 */

use MomentumLab\Core\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the license of this site unlocks a product. Reads the stored catalog, no HTTP.
 *
 * @param string $slug Product slug, e.g. "mlab-social-wall-pro".
 */
function mlab_core_product_unlocked( string $slug ): bool {
	$product = ( new Client() )->product( $slug );
	return ! empty( $product['unlocked'] );
}
