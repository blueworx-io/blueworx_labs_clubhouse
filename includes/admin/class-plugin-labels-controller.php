<?php
// includes/admin/class-plugin-labels-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress glue for the plain-English plugin names: installs Plugin_Labels'
 * transforms on the three places an admin actually reads a plugin's name — the
 * plugins list, the admin menu, and the page title in the browser tab.
 *
 * Filters and globals only. No vendor file is edited, so a vendor update
 * reinstates its own name and this reapplies on the very next request; and
 * nothing here is persisted, so deactivating Clubhouse restores every original
 * name with no migration.
 *
 * Admin-only: registered on admin requests, and the front end never sees it.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Plugin_Labels_Controller {

	/**
	 * Late enough that every plugin has registered its menu, and late enough to
	 * win against a plugin that rewrites its own label on admin_menu. Nothing
	 * downstream reads the title, so running last costs nothing.
	 */
	private const MENU_PRIORITY = 9999;

	public static function register(): void {
		add_filter( 'all_plugins', array( self::class, 'filter_plugins' ) );
		add_action( 'admin_menu', array( self::class, 'rename_menus' ), self::MENU_PRIORITY );
		add_action( 'network_admin_menu', array( self::class, 'rename_menus' ), self::MENU_PRIORITY );
		add_filter( 'admin_title', array( self::class, 'filter_admin_title' ) );
	}

	/**
	 * The plugins list's Name column.
	 *
	 * @param mixed $plugins
	 * @return mixed
	 */
	public static function filter_plugins( $plugins ) {
		return is_array( $plugins ) ? Blueworx_Clubhouse_Plugin_Labels::rename_plugins( $plugins ) : $plugins;
	}

	/**
	 * Admin menu and submenu labels. WordPress builds both as globals rather than
	 * passing them through a filter, so they are rewritten in place — which is
	 * also why the transform is idempotent: another plugin hooking after this one
	 * and rebuilding the menu is simply re-rewritten next request.
	 */
	public static function rename_menus(): void {
		if ( isset( $GLOBALS['menu'] ) && is_array( $GLOBALS['menu'] ) ) {
			$GLOBALS['menu'] = Blueworx_Clubhouse_Plugin_Labels::rename_menu( $GLOBALS['menu'] );
		}
		if ( isset( $GLOBALS['submenu'] ) && is_array( $GLOBALS['submenu'] ) ) {
			$GLOBALS['submenu'] = Blueworx_Clubhouse_Plugin_Labels::rename_submenu( $GLOBALS['submenu'] );
		}
	}

	/**
	 * The <title> of an admin page, which is also what the "You are here" wording
	 * and browser history read.
	 *
	 * @param mixed $title
	 * @return mixed
	 */
	public static function filter_admin_title( $title ) {
		return is_string( $title ) ? Blueworx_Clubhouse_Plugin_Labels::label_for_title( $title ) : $title;
	}
}
