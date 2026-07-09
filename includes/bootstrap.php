<?php
/**
 * Runtime class loader. Requires engine classes in dependency order.
 * No hooks or instantiation here — loading only, so it is safe to include
 * from both the plugin runtime and the PHPUnit bootstrap.
 *
 * @package BlueworxLabsClubhouse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Core primitives (added by later tasks), e.g.:
require_once __DIR__ . '/core/class-registry.php';
