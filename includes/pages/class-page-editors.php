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
	 * The screen definitions. Pure — no hooks, no WordPress — so the test
	 * above can hold every one of them against Schema::validate() and a
	 * mistake is a red test rather than a live screen saying it is not ready.
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
	 * declare_screens() runs synchronously here rather than through another
	 * plugins_loaded hook. register() is itself only ever called from the
	 * plugin's own plugins_loaded callback, by which point the vendored
	 * library's loader — hooked at plugins_loaded priority 0 — has already
	 * required Editor and its dependents: there is nothing left to wait for.
	 * A lower plugins_loaded priority would look like the right way to run
	 * "as early as possible", but WordPress does not rewind a hook it has
	 * already started dispatching to reach a priority added mid-dispatch —
	 * declare_screens() would never run, and the fourteen record editors and
	 * Global content would 404 with no error anywhere to say why.
	 */
	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		self::declare_screens();
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
	 * A CSS rule, not remove_submenu_page(). That looked like the obvious
	 * tool, but it does more than hide a row: it erases the $submenu entry
	 * get_admin_page_parent() relies on to find a page's parent on a direct
	 * visit. Once gone, WordPress recomputes a different hook name than the
	 * one Screen::menu() actually registered the page under a moment earlier
	 * — and admin.php refuses the visit, "Cannot load …", for every one of
	 * these fourteen the instant its item disappeared. Leaving the
	 * registration alone and only hiding the row sidesteps that entirely.
	 */
	public static function hide_record_editors(): void {
		$selectors = array();
		foreach ( self::screens() as $screen ) {
			if ( self::GLOBAL_SLUG === $screen['slug'] ) {
				continue;
			}
			$selectors[] = '#adminmenu li:has(> a[href$="page=' . esc_attr( $screen['slug'] ) . '"])';
		}
		if ( array() === $selectors ) {
			return;
		}
		printf( '<style>%s{display:none}</style>', implode( ',', $selectors ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built only from esc_attr()'d slugs above, never from a request.
	}
}
