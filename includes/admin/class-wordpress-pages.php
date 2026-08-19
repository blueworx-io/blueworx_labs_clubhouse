<?php
// includes/admin/class-wordpress-pages.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Takes WordPress's own Pages screen off the menu.
 *
 * A club manages its site in one place — Club Pages for the words, Setup for
 * what is shown — and every page this plugin serves is a route of its own with
 * no WordPress page under it. The Pages screen only ever offered a club a
 * second, contradictory place to look.
 *
 * Nothing is deleted. The pages SureCart and LatePoint rely on are still there,
 * still published and still served; they are simply not on the menu. Anyone who
 * needs one can still type its address. Removing the menu is the reversible half
 * of the change, which is the whole reason it is the half we do.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Wordpress_Pages {

	/** WordPress's own top-level menu for pages. */
	public const MENU_SLUG = 'edit.php?post_type=page';

	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		// 999, after every plugin has added its menus, so ours is the last word.
		add_action( 'admin_menu', array( self::class, 'hide_menu' ), 999 );
	}

	public static function hide_menu(): void {
		if ( function_exists( 'remove_menu_page' ) ) {
			remove_menu_page( self::MENU_SLUG );
		}
	}
}
