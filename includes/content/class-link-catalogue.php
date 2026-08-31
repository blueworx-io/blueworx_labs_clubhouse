<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every place inside this site an owner can point a link, as a flat list of
 * target tags. One catalogue serves both the menu editor and the URL fields in
 * Club Pages, so the two can never offer different destinations.
 *
 * A target is a tagged string rather than a URL, because a URL cannot say what
 * it meant: a stored '/about' does not know it was "the About page" and so
 * cannot follow a rename or disappear with the page. The tags are:
 *
 *   page:<key>              a plugin page          → /about
 *   anchor:<page>.<section> a section of a page    → /about#ch-about-history
 *   filter:<page>:<slug>    a filtered list view   → /sports?clubhouse_filter=netball
 *   shop:<key>              a page the shop owns   → /shop
 *   url:<href>              anything else          → itself
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Link_Catalogue {

	/**
	 * The id a section's markup carries and an anchor target points at. Section
	 * keys are snake_case in the catalogue ('quick_tiles') and hyphenated in
	 * markup, so this is the one place the two spellings meet.
	 */
	public static function anchor_id( string $page, string $section ): string {
		return 'ch-' . str_replace( '_', '-', $page ) . '-' . str_replace( '_', '-', $section );
	}

	/**
	 * Everything linkable, in group order. Collections are passed in rather than
	 * constructed so the preview and the tests can offer fixture content.
	 *
	 * @return array<int,array{target:string,label:string,group:string,url:string}>
	 */
	public static function targets( Blueworx_Clubhouse_Collections $collections ): array {
		return array_merge( self::pages(), self::shop(), self::anchors(), self::filters( $collections ) );
	}

	/**
	 * The shop's own pages, offered only when they exist and can be reached.
	 *
	 * Without these a club with a shop had no way to link to it: eleven products
	 * were in the sitemap and findable from a search engine, while nothing on
	 * the site pointed at the shop and a member browsing the club could never
	 * get to it (issue #131). The customer dashboard is here for the same
	 * reason — a member who has paid needs a way back to it that is not a URL
	 * they were sent once (#170).
	 *
	 * SureCart owns these pages; this only offers them as somewhere a link can
	 * point. A club with no shop, or one whose shop pages are missing, sees
	 * nothing here and a menu item pointing at one resolves to '' and is
	 * dropped — the same way Bookings disappears without LatePoint.
	 *
	 * Memoised: resolve() rebuilds the whole catalogue for every menu item, and
	 * each entry here costs an option read and a permalink lookup.
	 *
	 * @return array<int,array{target:string,label:string,group:string,url:string}>
	 */
	private static function shop(): array {
		if ( null !== self::$shop_cache ) {
			return self::$shop_cache;
		}
		$out = array();
		foreach ( array( 'shop' => 'Shop', 'dashboard' => 'My account' ) as $key => $label ) {
			$url = Blueworx_Clubhouse_Shop_Pages::url( $key );
			if ( '' === $url ) {
				continue;
			}
			$out[] = array(
				'target' => 'shop:' . $key,
				'label'  => $label,
				'group'  => 'Shop',
				'url'    => $url,
			);
		}
		self::$shop_cache = $out;
		return $out;
	}

	/**
	 * Memoised shop targets, for this request only. Reset by the repair that
	 * creates the pages, so an owner who has just pressed the button does not
	 * have to reload twice to see the Shop link appear.
	 *
	 * @var array<int,array{target:string,label:string,group:string,url:string}>|null
	 */
	private static ?array $shop_cache = null;

	public static function forget_shop_targets(): void {
		self::$shop_cache = null;
	}

	/** @return array<int,array{target:string,label:string,group:string,url:string}> */
	private static function pages(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $page ) {
			$key   = '' === $page['slug'] ? 'home' : $page['slug'];
			$out[] = array(
				'target' => 'page:' . $key,
				'label'  => (string) $page['label'],
				'group'  => 'Pages',
				'url'    => Blueworx_Clubhouse_Links::url( $key ),
			);
		}
		return $out;
	}

	/**
	 * Sections that share another section's rendered root and so have no root
	 * of their own to carry an id — an anchor here would point at nothing.
	 * Having no fields of its own is NOT a signal for this: a section that shows
	 * a collection (about.committee, sports.directory, home.activity) still
	 * renders its own markup, it only sources its *content* from elsewhere — it
	 * needs an anchor like any other section.
	 *
	 * Empty today: home.quick_tiles and home.info used to live here, sharing
	 * home.hero's and home.social's roots respectively, until Page_Renderer
	 * started passing 'tiles_id'/'cols_id' so Sections::home_hero() and
	 * Sections::closing_band() stamp a second, distinct id onto each one's own
	 * inner element. Kept as an explicit, named list (not a type check) so a
	 * future case that genuinely has no root of its own has one place to be
	 * declared — and SectionAnchorTest fails loudly if this list and the
	 * rendered markup ever disagree.
	 */
	private static function has_no_anchor( string $tab, string $key ): bool {
		$shared_root = array();
		if ( in_array( $key, $shared_root[ $tab ] ?? array(), true ) ) {
			return true;
		}
		// The social feed is off until a club opts in, and renders nothing until
		// posts are pasted, so on most sites there is no root to point at. A menu
		// item leading to an anchor that is not on the page is worse than not
		// offering it — a club that wants one can link to the page itself.
		$opt_in = array( 'home' => array( 'social_feed' ) );
		return in_array( $key, $opt_in[ $tab ] ?? array(), true );
	}

	/**
	 * One target per editable section, labelled "Page → Section" so a long list
	 * stays scannable. Sections of a page the site cannot serve are skipped —
	 * Page_Fields drops those areas itself, and its area keys and Page_Map's
	 * slugs share their spelling except for Home, whose slug is ''. Sections
	 * with no root of their own (see has_no_anchor()) are skipped too — the
	 * catalogue must never offer an anchor the markup does not emit.
	 *
	 * @return array<int,array{target:string,label:string,group:string,url:string}>
	 */
	private static function anchors(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Fields::sections() as $section ) {
			$tab = $section['area'];
			if ( 'global' === $tab ) {
				continue;
			}
			$key = $section['section'];
			if ( self::has_no_anchor( $tab, $key ) ) {
				continue;
			}
			$out[] = array(
				'target' => 'anchor:' . $tab . '.' . $key,
				'label'  => $section['area_label'] . ' → ' . $section['section_label'],
				'group'  => 'Sections',
				'url'    => Blueworx_Clubhouse_Links::url( $tab ) . '#' . self::anchor_id( $tab, $key ),
			);
		}
		return $out;
	}

	/**
	 * Filtered list views, one per distinct pill the page would render. Read the
	 * same field each page's pill row reads — /teams filters by the team's sport,
	 * not its name — so every target here corresponds to a pill that exists and
	 * a list that has something in it.
	 *
	 * @return array<int,array{target:string,label:string,group:string,url:string}>
	 */
	private static function filters( Blueworx_Clubhouse_Collections $collections ): array {
		$groups = array(
			array( 'page' => 'sports', 'group' => 'Sports', 'rows' => $collections->sports(), 'field' => 'title' ),
			array( 'page' => 'teams',  'group' => 'Teams',  'rows' => $collections->teams(),  'field' => 'sport' ),
			array( 'page' => 'events', 'group' => 'Events', 'rows' => $collections->events(), 'field' => 'tag' ),
		);
		$out = array();
		foreach ( $groups as $g ) {
			if ( ! Blueworx_Clubhouse_Page_Map::is_available( $g['page'] ) ) {
				continue;
			}
			$seen = array();
			foreach ( $g['rows'] as $row ) {
				$label = trim( (string) ( $row[ $g['field'] ] ?? '' ) );
				$slug  = Blueworx_Clubhouse_Page_Renderer::slugify( $label );
				if ( '' === $slug || in_array( $slug, $seen, true ) ) {
					continue;
				}
				$seen[] = $slug;
				$out[]  = array(
					'target' => 'filter:' . $g['page'] . ':' . $slug,
					'label'  => $g['group'] . ' → ' . $label,
					'group'  => $g['group'],
					'url'    => Blueworx_Clubhouse_Links::filtered_url( $g['page'], $slug ),
				);
			}
		}
		return $out;
	}

	/**
	 * A target tag's href, or '' when it no longer names anything this site can
	 * serve. Callers treat '' as "drop this link" — a link that goes nowhere is
	 * worse than one that is not shown.
	 */
	public static function resolve( string $target, Blueworx_Clubhouse_Collections $collections ): string {
		if ( 0 === strpos( $target, 'url:' ) ) {
			return self::safe_url( substr( $target, 4 ) );
		}
		foreach ( self::targets( $collections ) as $entry ) {
			if ( $entry['target'] === $target ) {
				return $entry['url'];
			}
		}
		return '';
	}

	/**
	 * Reject every scheme but http, https, mailto, tel and site-relative — a
	 * stored 'javascript:' must never reach an href, however it got in.
	 */
	private static function safe_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, '//' ) ) {
			return '';
		}
		if ( '/' === $url[0] || '#' === $url[0] || '?' === $url[0] ) {
			return $url;
		}
		if ( (bool) preg_match( '#^(https?://|mailto:|tel:)#i', $url ) ) {
			return $url;
		}
		return '';
	}
}
