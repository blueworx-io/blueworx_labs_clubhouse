<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every place inside this site an owner can point a link, as a flat list of
 * target tags. One catalogue serves the menu editor and every URL field on the
 * block editor, so the two can never offer different destinations.
 *
 * A target is a tagged string rather than a URL, because a URL cannot say what
 * it meant: a stored '/about' does not know it was "the About page" and so
 * cannot follow a rename or disappear with the page. The tags are:
 *
 *   page:<key>              a plugin page          → /about
 *   anchor:<page>.<block>   a block on a page      → /about#ch-about-history
 *   filter:<page>:<slug>    a filtered list view   → /sports?clubhouse_filter=netball
 *   shop:<key>              a page the shop owns   → /shop
 *   url:<href>              anything else          → itself
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Link_Catalogue {

	/**
	 * The id a block's markup carries and an anchor target points at. Anchor
	 * keys are snake_case ('quick_tiles') and hyphenated in markup, so this is
	 * the one place the two spellings meet.
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
	 * The club's blocks, or null when nothing has told this catalogue where they
	 * are — the DB-free preview and the pure tests, which get the blocks the
	 * plugin ships instead.
	 */
	private static ?Blueworx_Clubhouse_Page_Composer $composer = null;

	/**
	 * Point the catalogue at this site's own blocks. Set by the front end and by
	 * each admin screen that offers a link picker, in the same way the link
	 * resolver and the menu provider are installed.
	 */
	public static function set_composer( ?Blueworx_Clubhouse_Page_Composer $composer ): void {
		self::$composer = $composer;
	}

	/**
	 * One target per block on a page, labelled "Page → Block" so a long list
	 * stays scannable.
	 *
	 * The blocks a page shows, in the order it shows them, are the anchors it
	 * emits — so this offers exactly what the markup carries and nothing else. A
	 * block an owner removes from a page stops being offered there; a block they
	 * add starts. Pages this site cannot serve are skipped, as are blocks with
	 * no anchor of their own.
	 *
	 * Off a composed site — the preview, the pure tests, a site mid-upgrade —
	 * the blocks the plugin ships stand in, which is what such a site renders.
	 *
	 * @return array<int,array{target:string,label:string,group:string,url:string}>
	 */
	private static function anchors(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $page ) {
			$key = Blueworx_Clubhouse_Page_Map::page_key( (string) $page['slug'] );
			foreach ( self::blocks_on( $key ) as $block ) {
				$out[] = array(
					'target' => 'anchor:' . $key . '.' . $block['key'],
					'label'  => (string) $page['label'] . ' → ' . $block['label'],
					'group'  => 'Sections',
					'url'    => Blueworx_Clubhouse_Links::url( $key ) . '#' . self::anchor_id( $key, $block['key'] ),
				);
			}
		}
		return $out;
	}

	/**
	 * The anchor key and owner-facing name of every block on a page that carries
	 * an anchor.
	 *
	 * @return array<int,array{key:string,label:string}>
	 */
	private static function blocks_on( string $page ): array {
		$blocks = null !== self::$composer
			? self::$composer->blocks_for( $page )
			: self::shipped_blocks( $page );

		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Composer::anchor_keys( $blocks ) as $index => $key ) {
			if ( '' === (string) $key ) {
				continue;
			}
			// Seeded blocks are named "Home · Hero", and this list already says
			// which page it is on, so the page half is dropped rather than read
			// twice.
			$name  = (string) ( $blocks[ $index ]['name'] ?? $key );
			$parts = explode( ' · ', $name, 2 );
			$out[] = array( 'key' => (string) $key, 'label' => 1 === count( $parts ) ? $name : $parts[1] );
		}
		return $out;
	}

	/**
	 * A page's blocks as the plugin ships them, shaped like the library's own so
	 * anchor_keys() cannot tell the difference.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function shipped_blocks( string $page ): array {
		$folds = Blueworx_Clubhouse_Block_Addresses::folds();
		$out   = array();
		foreach ( Blueworx_Clubhouse_Block_Addresses::map() as $address => $entry ) {
			$address = (string) $address;
			if ( isset( $folds[ $address ] ) || ! str_starts_with( $address, $page . '/' ) ) {
				continue;
			}
			$section = explode( '/', $address, 2 )[1];
			if ( ! Blueworx_Clubhouse_Integrations::section_available( $page, $section ) ) {
				continue;
			}
			$out[] = array(
				'defaults_key' => $address,
				'type'         => (string) $entry['type'],
				'name'         => ucfirst( str_replace( '_', ' ', $section ) ),
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
