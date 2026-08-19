<?php
/**
 * Plugin Name:       Blueworx Labs | Clubhouse
 * Plugin URI:        https://github.com/blueworx-io/blueworx_labs_clubhouse
 * Description:        Blueworx Labs Clubhouse WordPress plugin.
 * Version:           0.81.0
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

define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.81.0' );
define( 'BLUEWORX_LABS_CLUBHOUSE_FILE', __FILE__ );
define( 'BLUEWORX_LABS_CLUBHOUSE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLUEWORX_LABS_CLUBHOUSE_URL', plugin_dir_url( __FILE__ ) );

require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/bootstrap.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-clubhouse-context.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-frontend.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-external-chrome.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-auth.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-seo-head.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-admin-menu-icons.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-setup-controller.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-content-controller.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-demo-controller.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-owner-role.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-access-controller.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-seo-controller.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-guide-controller.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-shop-pages-controller.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-wordpress-pages.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/import/class-import-applier.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/import/class-import-controller.php';

require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-collection-mappers.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-media.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-wp-collections.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-wp-posts.php';
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
	Blueworx_Clubhouse_External_Chrome::register();
	Blueworx_Clubhouse_Welcome_Pack::register();
	Blueworx_Clubhouse_Member_Dashboard::register();
	Blueworx_Clubhouse_Commerce_Pages::register();
	Blueworx_Clubhouse_Auth::register();
	Blueworx_Clubhouse_Seo_Head::register();
	Blueworx_Clubhouse_Admin_Menu_Icons::register();
	Blueworx_Clubhouse_Setup_Controller::register();
	Blueworx_Clubhouse_Content_Controller::register();
	Blueworx_Clubhouse_Demo_Controller::register();
	Blueworx_Clubhouse_Collection_Meta_Boxes::register();
	Blueworx_Clubhouse_Owner_Role::register();
	Blueworx_Clubhouse_Import_Controller::register();
	Blueworx_Clubhouse_Access_Controller::register();
	Blueworx_Clubhouse_Seo_Controller::register();
	Blueworx_Clubhouse_Guide_Controller::register();
	Blueworx_Clubhouse_SureCart_Products::register();
	Blueworx_Clubhouse_Shop_Pages_Controller::register();
	Blueworx_Clubhouse_Wordpress_Pages::register();
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
