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

require dirname( __DIR__, 2 ) . '/includes/bootstrap.php';

// Test doubles.
foreach ( glob( __DIR__ . '/fakes/*.php' ) as $fake ) {
	require_once $fake;
}
