<?php
/**
 * Plugin Name:       Blueworx Labs | Clubhouse
 * Plugin URI:        https://github.com/blueworx-io/blueworx_labs_clubhouse
 * Description:        Blueworx Labs Clubhouse WordPress plugin.
 * Version:           0.25.0
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

define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.25.0' );
define( 'BLUEWORX_LABS_CLUBHOUSE_FILE', __FILE__ );
define( 'BLUEWORX_LABS_CLUBHOUSE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLUEWORX_LABS_CLUBHOUSE_URL', plugin_dir_url( __FILE__ ) );

require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/bootstrap.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-clubhouse-context.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-frontend.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-setup-controller.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-content-controller.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-demo-controller.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-owner-role.php';

require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-collection-mappers.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-media.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-wp-collections.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-collection-types.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-collection-seeder.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-collection-meta-boxes.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function blueworx_labs_clubhouse_init() {
	Blueworx_Clubhouse_Frontend::register();
	Blueworx_Clubhouse_Setup_Controller::register();
	Blueworx_Clubhouse_Content_Controller::register();
	Blueworx_Clubhouse_Demo_Controller::register();
	Blueworx_Clubhouse_Collection_Meta_Boxes::register();
	Blueworx_Clubhouse_Owner_Role::register();
	add_action( 'admin_menu', array( Blueworx_Clubhouse_Collection_Types::class, 'register_content_menu' ) );
}
add_action( 'plugins_loaded', 'blueworx_labs_clubhouse_init' );

register_activation_hook(
	__FILE__,
	static function () {
		Blueworx_Clubhouse_Frontend::register_rewrites();
		Blueworx_Clubhouse_Collection_Types::register();
		Blueworx_Clubhouse_Collection_Seeder::seed();
		Blueworx_Clubhouse_Owner_Role::activate();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
