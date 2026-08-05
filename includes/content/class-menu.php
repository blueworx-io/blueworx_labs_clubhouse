<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The header navigation an owner has arranged: order, labels and one level of
 * children, each item pointing at a Link_Catalogue target.
 *
 * Absence and emptiness mean different things here. A site that has never
 * opened the menu editor has no stored value, and gets DEFAULTS — the nav this
 * plugin has always rendered, so upgrading changes nothing. A site that deleted
 * every row has a stored empty array, and gets an empty nav, because that is
 * what it asked for.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Menu {

	private const KEY = 'menu';

	/**
	 * The nav before anyone edits it — the list shell_header() used to hardcode.
	 * Availability and visibility still filter it at render time, so a site
	 * without LatePoint never sees "Bookings" here either.
	 *
	 * @var array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	public const DEFAULTS = array(
		array( 'label' => 'Home',         'target' => 'page:home',       'children' => array() ),
		array( 'label' => 'About',        'target' => 'page:about',      'children' => array() ),
		array( 'label' => 'Sports',       'target' => 'page:sports',     'children' => array() ),
		array( 'label' => 'Teams',        'target' => 'page:teams',      'children' => array() ),
		array( 'label' => 'Membership',   'target' => 'page:membership', 'children' => array() ),
		array( 'label' => 'Events',       'target' => 'page:events',     'children' => array() ),
		array( 'label' => 'Calendar',     'target' => 'page:calendar',   'children' => array() ),
		array( 'label' => 'Bookings', 'target' => 'page:booking',    'children' => array() ),
		array( 'label' => 'Contact',      'target' => 'page:contact',    'children' => array() ),
	);

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/** @var (callable():Blueworx_Clubhouse_Menu)|null */
	private static $provider = null;

	/**
	 * Where the renderer gets its menu. WordPress installs one backed by real
	 * options (see Frontend); the preview and the tests leave it unset and get
	 * the defaults, which is what keeps the two renders identical for a site
	 * that has not edited its nav.
	 *
	 * @param (callable():Blueworx_Clubhouse_Menu)|null $provider
	 */
	public static function set_provider( ?callable $provider ): void {
		self::$provider = $provider;
	}

	public static function current(): Blueworx_Clubhouse_Menu {
		if ( null !== self::$provider ) {
			return ( self::$provider )();
		}
		return new self( new Blueworx_Clubhouse_Null_Storage() );
	}

	/**
	 * The stored tree, or the defaults when nothing was ever written. A stored
	 * empty array is returned as-is — see the class comment.
	 *
	 * @return array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	public function tree(): array {
		$stored = $this->storage->get( self::KEY, null );
		if ( ! is_array( $stored ) ) {
			return self::DEFAULTS;
		}
		return self::sanitise( $stored );
	}

	/** @param array<int,mixed> $tree */
	public function save( array $tree ): void {
		$this->storage->set( self::KEY, self::sanitise( $tree ) );
	}

	/**
	 * Coerce arbitrary input — a form post, or an option written by an older
	 * version — into the stored shape. Rows without a label or target are
	 * dropped, and a third level is truncated rather than rejected: an owner
	 * who over-indented should lose the nesting, not the item.
	 *
	 * @param array<int,mixed> $tree
	 * @return array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	private static function sanitise( array $tree ): array {
		$out = array();
		foreach ( $tree as $row ) {
			$item = self::sanitise_row( $row );
			if ( null === $item ) {
				continue;
			}
			$children = array();
			$raw      = is_array( $row ) && isset( $row['children'] ) && is_array( $row['children'] ) ? $row['children'] : array();
			foreach ( $raw as $child_row ) {
				$child = self::sanitise_row( $child_row );
				if ( null !== $child ) {
					$children[] = $child; // Note: no 'children' key — one level only.
				}
			}
			$item['children'] = $children;
			$out[]            = $item;
		}
		return $out;
	}

	/**
	 * One row, label and target only. Returns null when it is not usable.
	 *
	 * @param mixed $row
	 * @return array{label:string,target:string}|null
	 */
	private static function sanitise_row( $row ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$label  = trim( (string) ( $row['label'] ?? '' ) );
		$target = trim( (string) ( $row['target'] ?? '' ) );
		if ( '' === $label || '' === $target ) {
			return null;
		}
		return array( 'label' => $label, 'target' => $target );
	}

	/**
	 * The tree as the header renders it: resolved hrefs, gone items dropped.
	 *
	 * A parent whose own target has died but which still has living children
	 * survives as a heading with an empty href, because dropping it would take
	 * its children with it and silently shrink the nav.
	 *
	 * @return array<int,array{label:string,href:string,children:array<int,array{label:string,href:string}>}>
	 */
	public function items( Blueworx_Clubhouse_Collections $collections, Blueworx_Clubhouse_Visibility $visibility ): array {
		$out = array();
		foreach ( $this->tree() as $row ) {
			$children = array();
			foreach ( $row['children'] as $child ) {
				$href = self::href( $child['target'], $collections, $visibility );
				if ( '' !== $href ) {
					$children[] = array( 'label' => $child['label'], 'href' => $href );
				}
			}
			$href = self::href( $row['target'], $collections, $visibility );
			if ( '' === $href && array() === $children ) {
				continue;
			}
			$out[] = array( 'label' => $row['label'], 'href' => $href, 'children' => $children );
		}
		return $out;
	}

	/**
	 * A target's href once both gates have run: can this site serve it at all
	 * (Link_Catalogue, which already excludes pages whose integration is
	 * missing), and has the owner hidden the page it lands on.
	 */
	private static function href(
		string $target,
		Blueworx_Clubhouse_Collections $collections,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$page = self::page_key( $target );
		if ( '' !== $page && ! $visibility->is_page_visible( $page ) ) {
			return '';
		}
		return Blueworx_Clubhouse_Link_Catalogue::resolve( $target, $collections );
	}

	/** The visibility key a target lands on — '' for a custom URL, which has none. */
	private static function page_key( string $target ): string {
		if ( 0 === strpos( $target, 'page:' ) ) {
			return substr( $target, 5 );
		}
		if ( 0 === strpos( $target, 'anchor:' ) ) {
			$rest = substr( $target, 7 );
			$dot  = strpos( $rest, '.' );
			return false === $dot ? $rest : substr( $rest, 0, $dot );
		}
		if ( 0 === strpos( $target, 'filter:' ) ) {
			$rest  = substr( $target, 7 );
			$colon = strpos( $rest, ':' );
			return false === $colon ? $rest : substr( $rest, 0, $colon );
		}
		return '';
	}
}
