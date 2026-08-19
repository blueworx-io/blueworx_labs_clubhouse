<?php
// includes/render/class-dashboard-shell.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member area's markup: page head, left nav, cards.
 *
 * Pure and escaped, in the same way Sections is — every rule about what this
 * page looks like is decided here and testable without WordPress or a shop.
 *
 * The classes are the BlueWorx admin design system's, not the club look's. The
 * two never meet: assets/looks/ is not loaded on this page, and every rule in
 * assets/bw/bw.css is scoped to .bw-admin, which only this markup carries.
 *
 * The nav is links rather than buttons because each view is its own address —
 * openable in a new tab, bookmarkable, and working with no JavaScript at all.
 *
 * Icons are inline SVG rather than an icon font or a script. The design draws
 * Lucide glyphs loaded by a JavaScript module; six paths inlined here cost
 * nothing and put no script on a page where a member is paying.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Dashboard_Shell {

	/** The query argument each view is addressed by. */
	public const VIEW_ARG = 'view';

	/**
	 * Escape, the same way Sections does — decoding first so escaping twice
	 * changes nothing. Some of what arrives here is built by WordPress's own
	 * helpers, which hand back a URL with its ampersands already written as
	 * entities; escaping that again would turn &amp; into &amp;amp; and quietly
	 * rename the query argument behind it.
	 */
	private static function e( string $v ): string {
		return htmlspecialchars( html_entity_decode( $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * The address of one view.
	 *
	 * Built on the page's own address, handed in by whoever knows it, because a
	 * bare '?view=orders' replaces the whole query rather than adding to it: on
	 * a club with permalinks set to Plain the dashboard lives at '/?page_id=42'
	 * and that link would land the member on the front page. With no address to
	 * build on it falls back to the bare form, which is right wherever the page
	 * carries no query of its own.
	 */
	public static function view_url( string $key, string $base = '' ): string {
		$arg = self::VIEW_ARG . '=' . rawurlencode( $key );
		if ( '' === trim( $base ) ) {
			return '?' . $arg;
		}
		return $base . ( false !== strpos( $base, '?' ) ? '&' : '?' ) . $arg;
	}

	/**
	 * The whole member area: the club and the member down the left, the view
	 * being read to the right of them.
	 *
	 * Takes one array rather than a row of positional arguments — the design
	 * needs the club's logo, the member's name and every panel's markup, and
	 * eleven positional strings is a signature nobody can call correctly.
	 *
	 * Every panel is rendered, and every one but the current carries `hidden`.
	 * The panels are other plugins' web components and shortcodes: they come
	 * alive when the page loads, so a panel fetched later would render as an
	 * empty box. Showing and hiding what is already there costs one attribute.
	 *
	 * @param array{
	 *   views:array<int,array<string,mixed>>,
	 *   current:string,
	 *   panels:array<string,string>,
	 *   home_url?:string, club_name?:string, logo_url?:string, base?:string,
	 *   logout_url?:string, member_name?:string, member_email?:string
	 * } $args
	 */
	public static function page( array $args ): string {
		$views   = isset( $args['views'] ) && is_array( $args['views'] ) ? $args['views'] : array();
		$current = (string) ( $args['current'] ?? '' );
		$panels  = isset( $args['panels'] ) && is_array( $args['panels'] ) ? $args['panels'] : array();
		$base    = (string) ( $args['base'] ?? '' );

		return '<div class="bw-admin bw-page clubhouse-member" data-clubhouse-member data-view-initial="' . self::e( $current ) . '">'
			. '<div class="clubhouse-member__shell">'
			. self::sidebar( $views, $current, $base, $args )
			. '<div class="clubhouse-member__main">'
			. self::head( $views, $current, $args )
			. '<main class="bw-panels" id="clubhouse-member-view">'
			. self::panels( $views, $current, $panels )
			. '</main>'
			. '</div>'
			. self::tabbar( $views, $current, $base )
			. '</div></div>';
	}

	/**
	 * Every panel, with all but one hidden. See page() for why they are all
	 * drawn. A view with nothing rendered for it is skipped rather than drawn
	 * empty.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @param array<string,string>           $panels
	 */
	private static function panels( array $views, string $current, array $panels ): string {
		$out = '';
		foreach ( $views as $view ) {
			$key  = (string) $view['key'];
			$body = (string) ( $panels[ $key ] ?? '' );
			if ( '' === $body ) {
				continue;
			}
			$out .= '<div class="clubhouse-member__panel" data-view="' . self::e( $key ) . '"'
				. ' role="tabpanel" aria-labelledby="clubhouse-member-tab-' . self::e( $key ) . '"'
				. ( $key === $current ? '' : ' hidden' ) . '>'
				. $body . '</div>';
		}
		return $out;
	}

	/**
	 * The left column: who the club is, where a member can go, and who they are
	 * signed in as. Full height, as the design draws it.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @param array<string,mixed>            $args
	 */
	private static function sidebar( array $views, string $current, string $base, array $args ): string {
		$club  = trim( (string) ( $args['club_name'] ?? '' ) );
		$logo  = trim( (string) ( $args['logo_url'] ?? '' ) );
		$home  = trim( (string) ( $args['home_url'] ?? '' ) );
		$name  = trim( (string) ( $args['member_name'] ?? '' ) );
		$email = trim( (string) ( $args['member_email'] ?? '' ) );

		$out = '<aside class="clubhouse-member__side">';

		// The brand block. A club with no logo set gets its initials in the same
		// box, so the corner is never empty.
		$out .= '<div class="clubhouse-member__brand">';
		if ( '' !== $logo ) {
			$out .= '<span class="clubhouse-member__brandmark"><img src="' . self::e( $logo ) . '" alt=""></span>';
		} elseif ( '' !== $club ) {
			$out .= '<span class="clubhouse-member__brandmark">' . self::e( self::initials( $club ) ) . '</span>';
		}
		if ( '' !== $club ) {
			$out .= '<span class="clubhouse-member__brandtext">'
				. '<span class="clubhouse-member__brandname">' . self::e( $club ) . '</span>'
				. '<span class="clubhouse-member__brandsub">Member area</span>'
				. '</span>';
		}
		$out .= '</div>';

		$out .= self::nav( $views, $current, $base );

		if ( '' !== $home ) {
			$out .= '<a class="clubhouse-member__back" href="' . self::e( $home ) . '">'
				. self::icon( 'arrow-left' ) . 'Back to the club site</a>';
		}

		// Who is signed in. The design shows a membership number here; nothing in
		// Clubhouse holds one, so the address they signed in with does the job of
		// telling a member which account they are looking at.
		if ( '' !== $name ) {
			$out .= '<div class="clubhouse-member__person"><div class="bw-person">'
				. '<span class="bw-avatar clubhouse-member__avatar">' . self::e( self::initials( $name ) ) . '</span>'
				. '<span class="clubhouse-member__persontext">'
				. '<span class="bw-person__name">' . self::e( $name ) . '</span>';
			if ( '' !== $email ) {
				$out .= '<span class="bw-person__sub">' . self::e( $email ) . '</span>';
			}
			$out .= '</span></div></div>';
		}

		return $out . '</aside>';
	}

	/**
	 * Up to two letters for an avatar: the first letter of the first word and of
	 * the last. Pure, and safe on a single word, on extra whitespace, and on
	 * nothing at all.
	 */
	public static function initials( string $name ): string {
		$words = preg_split( '/\s+/', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) || array() === $words ) {
			return '';
		}
		$first = mb_substr( (string) $words[0], 0, 1 );
		$last  = count( $words ) > 1 ? mb_substr( (string) $words[ count( $words ) - 1 ], 0, 1 ) : '';
		return mb_strtoupper( $first . $last );
	}

	/**
	 * The top bar: what this view is, and the two things a member does from
	 * anywhere — leave, or sign out.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @param array<string,mixed>            $args
	 */
	private static function head( array $views, string $current, array $args ): string {
		$view   = self::view( $views, $current );
		$title  = (string) ( $view['title'] ?? '' );
		$lede   = (string) ( $view['lede'] ?? '' );
		$logout = trim( (string) ( $args['logout_url'] ?? '' ) );

		$out = '<header class="bw-pagehead clubhouse-member__head"><div class="bw-pagehead__titles">'
			. '<h1 class="bw-pagehead__h1" data-member-title>' . self::e( $title ) . '</h1>';
		if ( '' !== trim( $lede ) ) {
			$out .= '<p class="bw-pagehead__lede" data-member-lede>' . self::e( $lede ) . '</p>';
		} else {
			$out .= '<p class="bw-pagehead__lede" data-member-lede hidden></p>';
		}
		$out .= '</div><div class="bw-pagehead__actions">';
		// Nothing is drawn when there is no address to sign out to — a dead link
		// is worse than no link.
		if ( '' !== $logout ) {
			$out .= '<a class="bw-btn bw-btn--secondary bw-btn--sm" href="' . self::e( $logout ) . '">Sign out</a>';
		}
		return $out . '</div></header>';
	}

	/**
	 * One view's entry, or an empty array.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @return array<string,mixed>
	 */
	private static function view( array $views, string $key ): array {
		foreach ( $views as $view ) {
			if ( (string) $view['key'] === $key ) {
				return $view;
			}
		}
		return array();
	}

	/**
	 * The same look with no nav — checkout and order confirmation.
	 *
	 * A member on the checkout page is mid-purchase and should not be offered
	 * six places to wander off to.
	 */
	public static function bare( string $title, string $lede, string $body, string $home_url, string $club_name ): string {
		$head = '<header class="bw-pagehead"><div class="bw-pagehead__titles">';
		if ( '' !== trim( $club_name ) ) {
			$head .= '<p class="bw-pagehead__eyebrow">' . self::e( $club_name ) . '</p>';
		}
		$head .= '<h1 class="bw-pagehead__h1">' . self::e( $title ) . '</h1>';
		if ( '' !== trim( $lede ) ) {
			$head .= '<p class="bw-pagehead__lede">' . self::e( $lede ) . '</p>';
		}
		$head .= '</div><div class="bw-pagehead__actions">'
			. '<a class="bw-btn bw-btn--secondary" href="' . self::e( $home_url ) . '">'
			. self::icon( 'arrow-left' ) . 'Back to the club site</a>'
			. '</div></header>';
		return '<div class="bw-admin bw-page clubhouse-member">' . $head
			. '<div class="bw-page__body"><main class="bw-panels">' . $body . '</main></div></div>';
	}

	/**
	 * The side nav. Links, not buttons: each view is its own address, openable
	 * in a new tab and working with no JavaScript at all. The script upgrades
	 * these in place — see assets/js/member-area.js.
	 *
	 * @param array<int,array<string,mixed>> $views
	 */
	private static function nav( array $views, string $current, string $base = '' ): string {
		$out = '<nav class="bw-secnav clubhouse-member__nav" aria-label="Your account">';
		foreach ( $views as $view ) {
			$key    = (string) $view['key'];
			$active = $key === $current;
			$out   .= '<a class="bw-secnav__item' . ( $active ? ' is-active' : '' ) . '"'
				. ' id="clubhouse-member-tab-' . self::e( $key ) . '"'
				. ' data-view-link="' . self::e( $key ) . '"'
				. ' data-view-title="' . self::e( (string) ( $view['title'] ?? '' ) ) . '"'
				. ' data-view-lede="' . self::e( (string) ( $view['lede'] ?? '' ) ) . '"'
				. ' href="' . self::e( self::view_url( $key, $base ) ) . '"'
				. ( $active ? ' aria-current="page"' : '' ) . '>'
				. '<span class="clubhouse-member__navlabel">'
				. self::icon( (string) $view['icon'] )
				. self::e( (string) $view['label'] )
				. '</span></a>';
		}
		return $out . '</nav>';
	}

	/**
	 * The bottom tab bar, which is what the sidebar becomes on a phone.
	 *
	 * Five at most, which is what the design draws and as many as fits a phone.
	 * Anything past the fifth is reached from the last panel — see
	 * Member_Dashboard::overflow_links().
	 *
	 * @param array<int,array<string,mixed>> $views
	 */
	private static function tabbar( array $views, string $current, string $base = '' ): string {
		$shown = array_slice( $views, 0, self::TABBAR_MAX );
		if ( count( $shown ) < 2 ) {
			return '';
		}
		$out = '<nav class="clubhouse-member__tabbar" aria-label="Your account">';
		foreach ( $shown as $view ) {
			$key    = (string) $view['key'];
			$active = $key === $current;
			$out   .= '<a class="clubhouse-member__tab' . ( $active ? ' is-active' : '' ) . '"'
				. ' data-view-link="' . self::e( $key ) . '"'
				. ' data-view-title="' . self::e( (string) ( $view['title'] ?? '' ) ) . '"'
				. ' data-view-lede="' . self::e( (string) ( $view['lede'] ?? '' ) ) . '"'
				. ' href="' . self::e( self::view_url( $key, $base ) ) . '"'
				. ( $active ? ' aria-current="page"' : '' ) . '>'
				. self::icon( (string) $view['icon'] )
				. '<span class="clubhouse-member__tablabel">' . self::e( (string) $view['label'] ) . '</span>'
				. '</a>';
		}
		return $out . '</nav>';
	}

	/** How many views the phone's bottom bar can carry. The design draws five. */
	public const TABBAR_MAX = 5;

	/** One panel. A card with no title is a card with no head, not an empty one. */
	public static function card( string $title, string $body ): string {
		$out = '<section class="bw-card">';
		if ( '' !== trim( $title ) ) {
			$out .= '<div class="bw-card__head"><div class="bw-card__titles">'
				. '<h2 class="bw-card__title">' . self::e( $title ) . '</h2>'
				. '</div></div>';
		}
		return $out . '<div class="bw-card__body">' . $body . '</div></section>';
	}

	/**
	 * What a member sees where a panel would be if the club has not set that
	 * part up. Never a blank frame: it says so plainly and offers the way back.
	 */
	public static function empty_state( string $title, string $text, string $href, string $label ): string {
		$out = '<div class="bw-empty">'
			. '<p class="bw-empty__title">' . self::e( $title ) . '</p>'
			. '<p class="bw-empty__text">' . self::e( $text ) . '</p>';
		if ( '' !== trim( $href ) && '' !== trim( $label ) ) {
			$out .= '<div class="bw-empty__actions">'
				. '<a class="bw-btn bw-btn--secondary" href="' . self::e( $href ) . '">' . self::e( $label ) . '</a>'
				. '</div>';
		}
		return $out . '</div>';
	}

	/**
	 * One glyph, or '' for a name nothing draws — a missing icon must never be
	 * a fatal or a broken image.
	 *
	 * The paths are Lucide's, the set the design is drawn with.
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
			'calendar'         => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
			'shopping-cart'    => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
			'file-spreadsheet' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/>',
			'refresh-cw'       => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/>',
			'users'            => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
			'arrow-left'       => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="bw-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
			. ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. $paths[ $name ] . '</svg>';
	}
}
