<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The fifteen editor screens, declared to the page editor library.
 *
 * Fourteen edit a club page as a record, on the page's own post — which is
 * what gives them revisions, a slug, and the library's Publish and settings
 * tab. The fifteenth is global content, which has no page behind it and so
 * stores to an option.
 *
 * The fourteen have no menu item. A club page is reached from WordPress's own
 * Pages list, which is where somebody looking for a page looks; a second list
 * beside it is how an owner ends up on the wrong one. They are still
 * registered under the Clubhouse menu so that menu stays highlighted while one
 * is open — the item itself is hidden straight afterwards (hide_record_editors()).
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Editors {

	public const GLOBAL_SLUG = 'clubhouse-global-content';

	public static function slug_for( string $area ): string {
		return Blueworx_Clubhouse_Page_Content::GLOBAL_AREA === $area
			? self::GLOBAL_SLUG
			: 'clubhouse-page-' . $area;
	}

	/** The address of an area's editor, carrying the record it edits. */
	public static function editor_url( string $area ): string {
		$url = 'admin.php?page=' . self::slug_for( $area );
		if ( Blueworx_Clubhouse_Page_Content::GLOBAL_AREA !== $area ) {
			$slug = Blueworx_Clubhouse_Club_Pages::slug_for_page_key( $area );
			$id   = null === $slug ? 0 : Blueworx_Clubhouse_Club_Pages::post_id( $slug );
			$url .= '&id=' . $id;
		}
		return function_exists( 'admin_url' ) ? admin_url( $url ) : $url;
	}

	/**
	 * The screen definitions. Pure — no hooks, no WordPress — so PageEditorsTest
	 * can hold every one of them against Schema::validate() and a mistake is a
	 * red test rather than a live screen saying it is not ready.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function screens( ?Blueworx_Clubhouse_Products $products = null ): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Fields::areas( $products ) as $area => $spec ) {
			$global = Blueworx_Clubhouse_Page_Content::GLOBAL_AREA === $area;
			$screen = array(
				'slug'       => self::slug_for( $area ),
				'title'      => $global ? 'Global content' : $spec['label'],
				'menu_title' => $global ? 'Global content' : $spec['label'],
				'eyebrow'    => $global ? 'Clubhouse' : 'Club page',
				'lede'       => $global
					? 'The header, footer, welcome pack and cookie notice — the parts that appear on every page.'
					: sprintf( 'The words on your %s page. Nothing changes on the site until you save.', strtolower( $spec['label'] ) ),
				'capability' => Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
				'parent'     => Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
				'tabs'       => self::with_suggestions( $spec['tabs'] ),
			);
			if ( $global ) {
				$screen['store']       = 'option';
				$screen['option_name'] = 'clubhouse_global_content';
			} else {
				$screen['store']     = 'post';
				$screen['post_type'] = 'page';
			}
			$out[] = $screen;
		}
		return $out;
	}

	/** Task 5 fills this in. Until then it is the identity. */
	private static function with_suggestions( array $tabs ): array {
		return $tabs;
	}

	/**
	 * declare_screens() is deferred to init, not called synchronously here and
	 * not hooked any earlier. Page_Fields::areas() decides whether Bookings
	 * exists by asking Integrations::provides(), whose real detector is
	 * shortcode_exists( 'latepoint_calendar' ) — and LatePoint, like every
	 * plugin, registers its shortcodes on init (see Frontend::register()'s own
	 * comment on why do_shortcode() is wrapped in a closure for the same
	 * reason). plugins_loaded runs before init, so declaring here would always
	 * see LatePoint as absent, on every site, dropping Bookings' menu item and
	 * its Pages → Edit route regardless of whether LatePoint is actually
	 * installed — a real regression against the old Content_Controller, which
	 * built its catalogue on admin_menu, safely after init.
	 *
	 * Worse than a wrong answer once: Page_Fields::areas() memoises per
	 * products instance, so a pre-init answer would be cached and handed to
	 * every later reader in the same request — including the front end once
	 * Page_Content is wired to it.
	 *
	 * init fires once, on every request type (admin and REST included), so
	 * this still runs well before Screen::menu() (admin_menu) or a REST call
	 * to Editor::load()/save() could ask Editor::all() a question it can't
	 * yet answer.
	 */
	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'init', array( self::class, 'declare_screens' ) );
		add_action( 'admin_head', array( self::class, 'hide_record_editors' ) );
	}

	public static function declare_screens(): void {
		foreach ( self::screens( Blueworx_Clubhouse_Products_Source::get() ) as $screen ) {
			\Blueworx\PageEditor\v1\Editor::register( $screen );
		}
	}

	/**
	 * Hides the fourteen record editors' menu items. Global content keeps its
	 * item: it is the one area with no page in the Pages list standing for it,
	 * so without an item there would be no way to reach it.
	 *
	 * remove_submenu_page(), on admin_head. An earlier version of this method
	 * avoided remove_submenu_page() entirely, having found it broke a direct
	 * visit to a hidden screen when called on admin_menu (priority 11): that
	 * call erased the $submenu entry get_admin_page_parent() relies on to find
	 * a page's parent, and WordPress recomputed a different — unregistered —
	 * hook name for it than the one Screen::menu() had just registered.
	 * admin_head runs later than that, from inside admin-header.php — by which
	 * point wp-admin/admin.php has already resolved $page_hook and already
	 * called user_can_access_admin_page() (both happen while building
	 * wp-admin/menu.php, well before admin-header.php requires
	 * wp-admin/menu-header.php to actually draw the sidebar). Removing the
	 * entry here is too late to affect either of those for the current
	 * request, and exactly in time to keep it out of the row menu-header.php
	 * is about to draw. Verified in the harness: a direct visit to a hidden
	 * screen still 200s, and its row is gone from the rendered menu.
	 */
	public static function hide_record_editors(): void {
		foreach ( array_keys( \Blueworx\PageEditor\v1\Editor::all() ) as $slug ) {
			if ( self::GLOBAL_SLUG === $slug ) {
				continue;
			}
			remove_submenu_page( Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG, $slug );
		}
	}
}
