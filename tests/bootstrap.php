<?php
/**
 * Test bootstrap: no WordPress, pure classes plus small function stubs.
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

require_once dirname( __DIR__ ) . '/inc/options.php';

foreach ( glob( dirname( __DIR__ ) . '/inc/class-*.php' ) as $class_file ) {
	require_once $class_file;
}
