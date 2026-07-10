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

// Core primitives. Interface must load before its implementor.
require_once __DIR__ . '/core/interface-storage.php';
require_once __DIR__ . '/core/class-options-storage.php';
require_once __DIR__ . '/core/class-registry.php';

// Content
require_once __DIR__ . '/content/class-content-store.php';
require_once __DIR__ . '/content/class-visibility.php';

// Theme
require_once __DIR__ . '/theme/interface-base-look.php';
require_once __DIR__ . '/theme/class-base-look-registry.php';
require_once __DIR__ . '/theme/class-color-engine.php';
require_once __DIR__ . '/theme/class-branding.php';
require_once __DIR__ . '/theme/class-theme-css.php';
