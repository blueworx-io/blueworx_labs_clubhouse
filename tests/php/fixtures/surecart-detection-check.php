<?php
declare(strict_types=1);
// tests/php/fixtures/surecart-detection-check.php
//
// Run as its own PHP process (see SureCartProductsTest) to exercise the real
// SureCart detection rather than the test override. The suite defines
// BLUEWORX_CLUBHOUSE_RUNNING_TESTS and every other test sets
// set_active_for_tests(), so nothing in-process ever reaches the branch that
// decides whether a real shop is present — which is exactly how that branch
// came to check two symbols SureCart does not have.
//
// The argument selects which of SureCart's real signals to simulate.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$signal = $argv[1] ?? 'none';

if ( 'constant' === $signal ) {
	// Defined at the top of surecart.php, before anything that could fail.
	define( 'SURECART_PLUGIN_FILE', __FILE__ );
}

if ( 'class' === $signal ) {
	// SureCart's application class is global and in no namespace — verified
	// against SureCart 4.6.3, app/src/SureCart.php.
	class SureCart {} // phpcs:ignore
}

require dirname( __DIR__, 3 ) . '/includes/membership/interface-products.php';
require dirname( __DIR__, 3 ) . '/includes/membership/class-surecart-products.php';

echo json_encode( array( 'is_active' => Blueworx_Clubhouse_SureCart_Products::is_active() ) );
