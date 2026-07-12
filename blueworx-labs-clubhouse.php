<?php
/**
 * Plugin Name:       Blueworx Labs Clubhouse
 * Plugin URI:        https://github.com/blueworx-io/blueworx_labs_clubhouse
 * Description:        Blueworx Labs Clubhouse WordPress plugin.
 * Version:           0.12.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Blueworx
 * Author URI:        https://babyblue.info
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blueworx-labs-clubhouse
 * Domain Path:       /languages
 *
 * @package BlueworxLabsClubhouse
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.12.0' );
define( 'BLUEWORX_LABS_CLUBHOUSE_FILE', __FILE__ );
define( 'BLUEWORX_LABS_CLUBHOUSE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLUEWORX_LABS_CLUBHOUSE_URL', plugin_dir_url( __FILE__ ) );

require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/bootstrap.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-frontend.php';

require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-collection-mappers.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-wp-collections.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-collection-types.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-collection-seeder.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function blueworx_labs_clubhouse_init() {
	Blueworx_Clubhouse_Frontend::register();
}
add_action( 'plugins_loaded', 'blueworx_labs_clubhouse_init' );

register_activation_hook(
	__FILE__,
	static function () {
		Blueworx_Clubhouse_Frontend::register_rewrites();
		Blueworx_Clubhouse_Collection_Types::register();
		Blueworx_Clubhouse_Collection_Seeder::seed();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
