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

// Plugin runtime constants: normally defined by the main plugin file, which the
// test bootstrap intentionally never loads (see require list below). A handful
// of classes (enqueue paths) reference them unguarded, so tests exercising those
// code paths need stand-in values.
if ( ! defined( 'BLUEWORX_LABS_CLUBHOUSE_URL' ) ) {
	define( 'BLUEWORX_LABS_CLUBHOUSE_URL', 'https://club.test/wp-content/plugins/blueworx-labs-clubhouse/' );
}
if ( ! defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ) {
	define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', 'test' );
}

require_once __DIR__ . '/wp-stubs.php';

require dirname( __DIR__, 2 ) . '/includes/bootstrap.php';

require_once dirname( __DIR__, 2 ) . '/includes/frontend/class-clubhouse-context.php';
require_once dirname( __DIR__, 2 ) . '/includes/frontend/class-frontend.php';
require_once dirname( __DIR__, 2 ) . '/includes/admin/class-setup-controller.php';
require_once dirname( __DIR__, 2 ) . '/includes/admin/class-owner-role.php';

require_once dirname( __DIR__, 2 ) . '/includes/collections/class-collection-mappers.php';
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-media.php';
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-wp-collections.php';
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-collection-types.php';
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-collection-seeder.php';
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-collection-meta-boxes.php';

// Test doubles.
foreach ( glob( __DIR__ . '/fakes/*.php' ) as $fake ) {
	require_once $fake;
}
