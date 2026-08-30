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
				'tabs'       => $spec['tabs'],
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

	/**
	 * Offer every link this site can already make, on every url field.
	 *
	 * The same list the menu editor offers, resolved to real addresses — a
	 * menu target like "shop:dashboard" is a token this plugin understands and
	 * a browser does not, and these go into a free-text box that has to hold a
	 * link. The field stays free text: plenty of links point somewhere the
	 * plugin does not own.
	 *
	 * Called from declare_screens(), not screens(): screens() is documented
	 * and relied on (PageEditorsTest, task 4) as pure — no hooks, no
	 * WordPress. link_suggestions() below issues real queries through
	 * Blueworx_Clubhouse_WP_Collections, so it belongs at the one place that
	 * already runs at a known time on init, not baked into the definitions
	 * themselves.
	 */
	private static function with_suggestions( array $tabs ): array {
		$suggestions = self::link_suggestions();
		if ( array() === $suggestions ) {
			return $tabs;
		}
		foreach ( $tabs as &$tab ) {
			foreach ( $tab['panels'] as &$panel ) {
				foreach ( $panel['fields'] as &$field ) {
					self::apply_suggestions( $field, $suggestions );
				}
				unset( $field );
			}
			unset( $panel );
		}
		unset( $tab );
		return $tabs;
	}

	/**
	 * Stamps suggestions onto a url field, and walks into a repeater's own
	 * cells — a quick tile's href is as much a link as the hero's, and the
	 * library honours `format`/`suggestions` on a cell the same way.
	 *
	 * @param array<string,mixed> $field
	 * @param array<int,array{value:string,label:string}> $suggestions
	 */
	private static function apply_suggestions( array &$field, array $suggestions ): void {
		if ( 'url' === ( $field['format'] ?? '' ) ) {
			$field['suggestions'] = $suggestions;
		}
		if ( 'repeater' === ( $field['kind'] ?? '' ) && isset( $field['fields'] ) ) {
			foreach ( $field['fields'] as &$cell ) {
				self::apply_suggestions( $cell, $suggestions );
			}
			unset( $cell );
		}
	}

	/**
	 * @return array<int,array{value:string,label:string}>
	 *
	 * A suggestion has to hold a real address a browser can follow, never a
	 * target tag. Resolving one requires a real resolver already installed on
	 * Blueworx_Clubhouse_Links — register() below installs it once, at boot,
	 * deliberately; this method only ever reads through it.
	 */
	private static function link_suggestions(): array {
		if ( ! class_exists( 'Blueworx_Clubhouse_Link_Catalogue' ) ) {
			return array();
		}
		$out = array();
		foreach ( Blueworx_Clubhouse_Link_Catalogue::targets( new Blueworx_Clubhouse_WP_Collections() ) as $target ) {
			$url = (string) ( $target['url'] ?? '' );
			if ( '' === $url ) {
				continue;
			}
			$out[] = array(
				'value' => $url,
				'label' => ( '' !== (string) ( $target['group'] ?? '' ) )
					? $target['group'] . ' · ' . $target['label']
					: (string) $target['label'],
			);
		}
		return $out;
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
	 * installed — a real regression against the old Club Pages screen, which
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
	 *
	 * Priority 20, not the default: whether shortcode_exists( 'latepoint_calendar' )
	 * sees LatePoint's shortcode by the time this runs depends on two separate
	 * plugins' relative init registration order, which WordPress does not
	 * guarantee — LatePoint could register after this plugin's default-priority
	 * callback and Bookings would drop out again, the very bug the move to init
	 * was meant to fix. A later priority can only see more integrations that
	 * have registered on init, never fewer.
	 */
	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		// Installed once, here, at boot — a decision, not a side effect of
		// building a screen. This class's own link-field suggestions
		// (declare_screens(), below) read a link's URL through
		// Blueworx_Clubhouse_Links, and nothing installed a resolver before
		// this, so they silently got the preview server's '?page=<key>' query
		// form. Only the *callable* is stored — plugins_loaded runs before
		// $wp_rewrite exists, and resolving now would fatal (see
		// Frontend::register()'s own comment on Checkout::set_resolver() for
		// the identical reason).
		if ( class_exists( 'Blueworx_Clubhouse_Frontend' ) ) {
			Blueworx_Clubhouse_Links::set_resolver( array( Blueworx_Clubhouse_Frontend::class, 'link_url' ) );
		}
		add_action( 'init', array( self::class, 'declare_screens' ), 20 );
		add_action( 'admin_head', array( self::class, 'hide_record_editors' ) );
	}

	/** Suggestions are applied here, not in screens(): see with_suggestions()'s own docblock. */
	public static function declare_screens(): void {
		foreach ( self::screens( Blueworx_Clubhouse_Products_Source::get() ) as $screen ) {
			$screen['tabs'] = self::with_suggestions( $screen['tabs'] );
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
