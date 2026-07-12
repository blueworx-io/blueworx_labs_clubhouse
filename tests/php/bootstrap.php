<?php
/**
 * PHPUnit bootstrap. Loads Composer's dev autoloader (PHPUnit), defines a
 * dummy ABSPATH so guarded plugin files load, then pulls in the runtime
 * classes and the test fakes.
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

require_once __DIR__ . '/wp-stubs.php';

require dirname( __DIR__, 2 ) . '/includes/bootstrap.php';

require_once dirname( __DIR__, 2 ) . '/includes/frontend/class-frontend.php';

require_once dirname( __DIR__, 2 ) . '/includes/collections/class-collection-mappers.php';
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-wp-collections.php';

// Test doubles.
foreach ( glob( __DIR__ . '/fakes/*.php' ) as $fake ) {
	require_once $fake;
}
