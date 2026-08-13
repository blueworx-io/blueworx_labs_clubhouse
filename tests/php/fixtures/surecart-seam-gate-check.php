<?php
declare(strict_types=1);
// tests/php/fixtures/surecart-seam-gate-check.php
//
// Run as its own PHP process (see SureCartProductsTest) to prove
// set_raw_fetcher() and set_active_for_tests() are no-ops when
// BLUEWORX_CLUBHOUSE_RUNNING_TESTS is not defined. The main test suite
// defines that constant once, process-wide, in tests/php/bootstrap.php, so
// proving the gate actually gates anything needs a process that never
// defines it — this file deliberately does not, and deliberately does not
// load the WordPress stubs either, so no other function here can quietly
// supply the missing plumbing.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__, 3 ) . '/includes/membership/interface-products.php';
require dirname( __DIR__, 3 ) . '/includes/membership/class-surecart-products.php';

Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher(
	static fn(): array => array( array( 'id' => 'injected' ) )
);

// is_active() needs nothing else to answer honestly; it is the seam-free
// ground truth for whether set_active_for_tests() took effect.
$is_active = Blueworx_Clubhouse_SureCart_Products::is_active();

// The raw fetcher has no public reader, so reflection is the only way to see
// whether set_raw_fetcher() actually assigned it, without pulling in the rest
// of prices()'s WordPress dependencies.
$prop = new ReflectionProperty( Blueworx_Clubhouse_SureCart_Products::class, 'raw_fetcher' );
$prop->setAccessible( true );

echo json_encode(
	array(
		'is_active'        => $is_active,
		'raw_fetcher_set'  => null !== $prop->getValue(),
	)
);
