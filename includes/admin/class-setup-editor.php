<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clubhouse Setup, declared to the page editor library.
 *
 * The slug is the one the hand-built screen used, so every link, every submenu
 * that names it as a parent, and every redirect still lands where it did.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Editor {

	public const PAGE_SLUG = 'clubhouse-setup';

	/** Where the Clubhouse item sits in the admin sidebar, as it always has. */
	private const MENU_POSITION = 3;

	/**
	 * Declared on init at priority 20, the same hook and priority the fourteen
	 * club page editors use, and added first — this plugin registers this
	 * class before Page_Editors, and WordPress runs same-priority callbacks in
	 * the order they were added. That order matters: those fourteen name this
	 * screen's slug as their parent, and Screen::menu() walks Editor::all() in
	 * registration order, so the parent has to be registered before a child
	 * asks WordPress to hang off it.
	 *
	 * Not earlier than init 20 for the same reason Page_Editors is not: the
	 * Visibility tab's switches come from Page_Map::available(), which decides
	 * whether Bookings exists by asking whether LatePoint has registered its
	 * shortcode — and a plugin registers shortcodes on init. Declared on
	 * plugins_loaded this screen would offer a club with LatePoint installed no
	 * Bookings switch at all.
	 */
	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'init', array( self::class, 'declare_screen' ), 20 );
		// After Screen::menu() (default priority) has added the item, and after
		// every other plugin's own admin_menu work — including the collection
		// lists, which hang off this screen and have to be there before their
		// order can be corrected.
		add_action( 'admin_menu', array( self::class, 'ensure_own_submenu' ), 99 );
		add_action( 'admin_menu', array( self::class, 'place_menu' ), 100 );
	}

	/**
	 * Put Setup itself back on the menu.
	 *
	 * add_menu_page() makes the top-level Clubhouse item, and WordPress points
	 * that item at the first thing hung underneath it. Nothing was hung
	 * underneath it until the six collection lists moved there in v0.101.0 —
	 * and from that release on, "Clubhouse" opened the Sports list and Setup
	 * was on the menu nowhere at all. An owner could still reach it from the
	 * panel on their dashboard; an administrator had only the address bar.
	 *
	 * WordPress's own menus answer this with a submenu entry pointing back at
	 * the parent — "All Posts" under Posts. This is that entry, first in the
	 * list because it is the screen the menu is named after.
	 */
	public static function ensure_own_submenu(): void {
		if ( ! function_exists( 'add_submenu_page' ) ) {
			return;
		}
		$items = $GLOBALS['submenu'][ self::PAGE_SLUG ] ?? array();
		$has   = false;
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( is_array( $item ) && ( $item[2] ?? '' ) === self::PAGE_SLUG ) {
				$has = true;
				break;
			}
		}
		if ( ! $has ) {
			add_submenu_page(
				self::PAGE_SLUG,
				'Clubhouse Setup',
				'Setup',
				// The same capability the screen itself declares, so the menu
				// can never offer a door that will not open.
				Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
				self::PAGE_SLUG
			);
		}
		self::name_own_submenu_and_put_it_first();
	}

	/**
	 * Exactly one entry for this screen, called Setup, at the top of the list.
	 *
	 * WordPress names a menu's own first entry after the menu, so this one
	 * arrived as a second row reading "Clubhouse" directly under the item
	 * already reading "Clubhouse". It is the Setup screen, so it says Setup.
	 */
	private static function name_own_submenu_and_put_it_first(): void {
		$items = $GLOBALS['submenu'][ self::PAGE_SLUG ] ?? null;
		if ( ! is_array( $items ) ) {
			return;
		}
		$ours = null;
		$rest = array();
		foreach ( $items as $item ) {
			if ( null === $ours && is_array( $item ) && ( $item[2] ?? '' ) === self::PAGE_SLUG ) {
				$item[0] = 'Setup';
				$ours    = $item;
				continue;
			}
			// A second entry for the same screen is a duplicate row, not a
			// second door — dropped rather than renamed.
			if ( is_array( $item ) && ( $item[2] ?? '' ) === self::PAGE_SLUG ) {
				continue;
			}
			$rest[] = $item;
		}
		$GLOBALS['submenu'][ self::PAGE_SLUG ] = null === $ours ? $rest : array_merge( array( $ours ), $rest );
	}

	public static function declare_screen(): void {
		\Blueworx\PageEditor\v1\Editor::register( self::screen() );
	}

	/** @return array<string,mixed> */
	public static function screen(): array {
		$storage = new Blueworx_Clubhouse_Options_Storage();
		$bridge  = new Blueworx_Clubhouse_Setup_Storage( $storage );

		return array(
			'slug'       => self::PAGE_SLUG,
			'title'      => 'Clubhouse Setup',
			'menu_title' => 'Clubhouse',
			'icon'       => Blueworx_Clubhouse_Admin_Menu_Icons::data_uri( self::PAGE_SLUG ),
			'eyebrow'    => 'Clubhouse',
			'lede'       => self::lede(),
			// The lower of the two capabilities, deliberately. Editing the menu
			// is a Content Editor's job and the menu lives on this screen, so
			// they have to be able to reach it; every field outside the Menu tab
			// carries the setup capability, and Setup_Fields leaves the tabs
			// they cannot touch out of their copy of the screen altogether.
			'capability' => Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			// Declared, and never used: Store::for() sends a screen with its own
			// read and write to those instead. Schema::validate() defaults an
			// undeclared store to 'post' and would then demand a post_type.
			'store'      => 'option',
			'read'       => static fn( int $id ): array => $bridge->read(),
			'write'      => static fn( array $values, int $id ): bool => $bridge->write( $values ),
			'validate'   => array( self::class, 'validate' ),
			'tabs'       => self::with_suggestions(
				Blueworx_Clubhouse_Setup_Fields::tabs( self::can(), self::looks( $storage ), self::pages() )
			),
		);
	}

	/**
	 * What the screen says under its title — and, for an administrator, who can
	 * open it.
	 *
	 * Every other Clubhouse screen puts that in chips in its top bar. A page
	 * editor screen's header is the library's, built from a title, an eyebrow
	 * and a line of text, with nowhere to put markup of ours — so the fact is
	 * said in words instead. An administrator is still told; nobody else sees
	 * it, exactly as before.
	 */
	private static function lede(): string {
		$lede = 'How your site looks, which pages it shows, and what it asks your members.';
		if ( ! class_exists( 'Blueworx_Clubhouse_Access_Controller' )
			|| ! Blueworx_Clubhouse_Access_Controller::may_see_role_tags() ) {
			return $lede;
		}
		$labels = Blueworx_Clubhouse_Admin_Pages::access_labels( self::PAGE_SLUG );
		if ( array() === $labels ) {
			return $lede;
		}
		return $lede . ' Who can open this screen: ' . implode( ', ', $labels ) . '.';
	}

	/**
	 * Who is looking. Read here rather than in Setup_Fields so that class stays
	 * pure and its whole output can be held against Schema::validate().
	 *
	 * @return array{setup:bool,menu:bool,demo:bool}
	 */
	private static function can(): array {
		if ( ! function_exists( 'current_user_can' ) ) {
			return array( 'setup' => true, 'menu' => true, 'demo' => true );
		}
		return array(
			'setup' => (bool) current_user_can( Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP ),
			'menu'  => (bool) current_user_can( Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP ),
			'demo'  => (bool) current_user_can( 'manage_options' ),
		);
	}

	/**
	 * Every look this site can wear, as the radio's choices.
	 *
	 * @return array<string,array{name:string,description:string}>
	 */
	public static function looks( Blueworx_Clubhouse_Storage $storage ): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Frontend::registry( $storage )->all() as $look ) {
			$out[ $look->slug() ] = array(
				'name'        => $look->name(),
				'description' => $look->description(),
			);
		}
		return $out;
	}

	/**
	 * The pages the Visibility tab offers a switch for: every page this site
	 * can actually serve, in Page_Map's own order. A page whose integration is
	 * absent is not offered at all — an owner should not be given a switch for
	 * a page that cannot render — and its stored state is left alone, so
	 * installing the integration later brings the page back exactly as it was.
	 *
	 * Home's slug is '' everywhere in Page_Map and 'home' everywhere
	 * visibility is stored; that one remap is why this is a method rather than
	 * a call to Page_Map::available() at each site.
	 *
	 * @return array<int,array{page:string,label:string}>
	 */
	public static function pages(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $page ) {
			$slug  = '' === $page['slug'] ? 'home' : (string) $page['slug'];
			$out[] = array( 'page' => $slug, 'label' => (string) $page['label'] );
		}
		return $out;
	}

	/**
	 * The one rule the library cannot know: an accent has to stay legible on
	 * the look it sits on.
	 *
	 * Refused for the main colour, which carries every call to action — that
	 * refusal has been in place since the colour engine shipped. The second
	 * colour is left to the club, said once in the field's own help rather
	 * than on save: it is spent on second actions and section marks, where a
	 * club that insists on its real brand colour should be told, not
	 * overruled, and every derived token is legibility-clamped anyway.
	 *
	 * @param array<string,mixed> $values
	 * @return array<string,string>
	 */
	public static function validate( array $values ): array {
		if ( ! array_key_exists( 'accent', $values ) ) {
			return array();
		}
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		$look     = $registry->get( (string) ( $values['look'] ?? '' ) )
			?? $registry->active()
			?? new Blueworx_Clubhouse_Court_Side();

		$accent = sanitize_hex_color( (string) $values['accent'] );
		if ( '' === (string) $accent ) {
			return array( 'accent' => 'The main colour must be a 6-digit hex value like #c6f24e.' );
		}
		if ( ! Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $look, (string) $accent ) ) {
			return array( 'accent' => 'That colour is too low in contrast for the look you have chosen, so text on it would be hard to read. Pick a stronger one.' );
		}
		return array();
	}

	/**
	 * Put the Clubhouse item back where it has always been.
	 *
	 * add_menu_page() takes a position and the library does not pass one, so
	 * the item is appended to the bottom of the sidebar — below Settings, past
	 * every other plugin. For a club owner this is the main item on the screen,
	 * so it is moved back to third, under Dashboard.
	 */
	public static function place_menu(): void {
		if ( empty( $GLOBALS['menu'] ) || ! is_array( $GLOBALS['menu'] ) ) {
			return;
		}
		foreach ( $GLOBALS['menu'] as $position => $item ) {
			if ( ( $item[2] ?? '' ) !== self::PAGE_SLUG ) {
				continue;
			}
			unset( $GLOBALS['menu'][ $position ] );
			$target = self::MENU_POSITION;
			while ( isset( $GLOBALS['menu'][ $target ] ) ) {
				++$target;
			}
			$GLOBALS['menu'][ $target ] = $item;
			ksort( $GLOBALS['menu'], SORT_NUMERIC );
			return;
		}
	}

	/**
	 * Offer every link this site can already make, on every url field — which
	 * on this screen means the Menu tab's "Links to".
	 *
	 * Stamped on here rather than in Setup_Fields: resolving a target to a real
	 * address issues real queries, so it belongs at the one place that runs at
	 * a known time, not baked into a definition the tests hold as pure. The
	 * field stays free text — plenty of links point somewhere this plugin does
	 * not own.
	 *
	 * @param array<int,array<string,mixed>> $tabs
	 * @return array<int,array<string,mixed>>
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
	 * The same list the club page editors offer, resolved to real addresses: a
	 * link an owner can pick in the menu is a link they can pick anywhere.
	 *
	 * @return array<int,array{value:string,label:string}>
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
}
