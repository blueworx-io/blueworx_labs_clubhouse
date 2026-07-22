<?php
// includes/admin/class-native-pages.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hides WordPress's native "Pages" from the admin UI. The plugin serves every
 * site page through its own routing (clubhouse_page query var + Club Content),
 * so the built-in Pages editor is redundant and only invites editing content
 * that never renders.
 *
 * UI-only: the `page` post type stays registered, so the static-front-page
 * setting, the privacy-policy page and any other plugin that uses pages keep
 * working. Removing this class restores the menu — nothing is deleted.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Native_Pages {

	/** The top-level admin menu slug WordPress registers for the Pages post type. */
	public const PAGES_MENU_SLUG = 'edit.php?post_type=page';

	/** The admin-bar node id for "+ New → Page". */
	public const NEW_PAGE_NODE = 'new-page';

	public static function register(): void {
		// Priority 999: run after core has built the menu / admin bar, so the item exists to remove.
		add_action( 'admin_menu', array( self::class, 'hide_pages_menu' ), 999 );
		add_action( 'admin_bar_menu', array( self::class, 'hide_new_page_node' ), 999 );
	}

	/** Drop the "Pages" item from the wp-admin sidebar. */
	public static function hide_pages_menu(): void {
		remove_menu_page( self::PAGES_MENU_SLUG );
	}

	/**
	 * Drop the "+ New → Page" item from the admin bar. Takes the bar object
	 * (injected by the admin_bar_menu hook) so it stays unit-testable.
	 *
	 * @param object $wp_admin_bar A WP_Admin_Bar-like object exposing remove_node().
	 */
	public static function hide_new_page_node( $wp_admin_bar ): void {
		if ( is_object( $wp_admin_bar ) && method_exists( $wp_admin_bar, 'remove_node' ) ) {
			$wp_admin_bar->remove_node( self::NEW_PAGE_NODE );
		}
	}
}
