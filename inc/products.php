<?php
/**
 * Public API: which products the license of this site unlocks.
 *
 * @package mlab-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the license of this site unlocks a product or feature. No HTTP; safe in the frontend.
 *
 * Kept for plugins written before the shared license client; new code calls mlab_license()->unlocked().
 *
 * @param string $slug Product slug, e.g. "mlab-social-wall-pro".
 */
function mlab_core_product_unlocked( string $slug ): bool {
	return mlab_license()->unlocked( $slug );
}
