<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every place inside this site an owner can point a link, as a flat list of
 * target tags. One catalogue serves both the menu editor and the URL fields in
 * Club Content, so the two can never offer different destinations.
 *
 * A target is a tagged string rather than a URL, because a URL cannot say what
 * it meant: a stored '/about' does not know it was "the About page" and so
 * cannot follow a rename or disappear with the page. The tags are:
 *
 *   page:<key>              a plugin page          → /about
 *   anchor:<page>.<section> a section of a page    → /about#ch-about-history
 *   filter:<page>:<slug>    a filtered list view   → /sports?clubhouse_filter=netball
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
		return array_merge( self::pages(), self::anchors(), self::filters( $collections ) );
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
	 * Sections with no root of their own to carry an id, so an anchor here
	 * would point at nothing:
	 *  - 'linkout'/'auto' catalogue types only tell the owner where to edit
	 *    content that lives (and renders) elsewhere — the CPT, or another
	 *    section's own root — never a markup section in their own right.
	 *  - home.quick_tiles renders inside home.hero's foot (Page_Renderer::home()),
	 *    and home.info shares home.social's closing_band() root — neither has a
	 *    root of its own to stamp a second id onto.
	 *
	 * @param array{key:string,type:string} $section
	 */
	private static function has_no_anchor( string $tab, array $section ): bool {
		if ( in_array( (string) $section['type'], array( 'linkout', 'auto' ), true ) ) {
			return true;
		}
		$shared_root = array(
			'home' => array( 'quick_tiles', 'info' ),
		);
		return in_array( (string) $section['key'], $shared_root[ $tab ] ?? array(), true );
	}

	/**
	 * One target per editable section, labelled "Page → Section" so a long list
	 * stays scannable. Sections of a page the site cannot serve are skipped —
	 * the catalogue's tabs and Page_Map's slugs share their spelling except for
	 * Home, whose slug is ''. Sections with no root of their own (see
	 * has_no_anchor()) are skipped too — the catalogue must never offer an
	 * anchor the markup does not emit.
	 *
	 * @return array<int,array{target:string,label:string,group:string,url:string}>
	 */
	private static function anchors(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$tab  = (string) $page['tab'];
			$slug = 'home' === $tab ? '' : $tab;
			if ( 'global' === $tab || ! Blueworx_Clubhouse_Page_Map::is_available( $slug ) ) {
				continue;
			}
			foreach ( $page['sections'] as $section ) {
				if ( self::has_no_anchor( $tab, $section ) ) {
					continue;
				}
				$key   = (string) $section['key'];
				$out[] = array(
					'target' => 'anchor:' . $tab . '.' . $key,
					'label'  => (string) $page['label'] . ' → ' . (string) $section['label'],
					'group'  => 'Sections',
					'url'    => Blueworx_Clubhouse_Links::url( $tab ) . '#' . self::anchor_id( $tab, $key ),
				);
			}
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
