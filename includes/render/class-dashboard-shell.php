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

	private static function e( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** The address of one view, relative to the page the member area is on. */
	public static function view_url( string $key ): string {
		return '?' . self::VIEW_ARG . '=' . rawurlencode( $key );
	}

	/**
	 * The whole member area: nav down the side, one view in the middle.
	 *
	 * @param array<int,array<string,mixed>> $views   From Dashboard_Views::available().
	 * @param string                         $current The key of the view being read.
	 * @param string                         $body    Already-rendered markup for that view.
	 */
	public static function page( array $views, string $current, string $title, string $lede, string $body, string $home_url, string $club_name ): string {
		return '<div class="bw-admin bw-page clubhouse-member">'
			. self::head( $title, $lede, $home_url, $club_name )
			. '<div class="bw-page__body">'
			. self::nav( $views, $current )
			. '<main class="bw-panels" id="clubhouse-member-view">' . $body . '</main>'
			. '</div></div>';
	}

	/**
	 * The same look with no nav — checkout and order confirmation.
	 *
	 * A member on the checkout page is mid-purchase and should not be offered
	 * six places to wander off to.
	 */
	public static function bare( string $title, string $lede, string $body, string $home_url, string $club_name ): string {
		return '<div class="bw-admin bw-page clubhouse-member">'
			. self::head( $title, $lede, $home_url, $club_name )
			. '<div class="bw-page__body">'
			. '<main class="bw-panels">' . $body . '</main>'
			. '</div></div>';
	}

	private static function head( string $title, string $lede, string $home_url, string $club_name ): string {
		$out = '<header class="bw-pagehead"><div class="bw-pagehead__titles">';
		if ( '' !== trim( $club_name ) ) {
			$out .= '<p class="bw-pagehead__eyebrow">' . self::e( $club_name ) . '</p>';
		}
		$out .= '<h1 class="bw-pagehead__h1">' . self::e( $title ) . '</h1>';
		if ( '' !== trim( $lede ) ) {
			$out .= '<p class="bw-pagehead__lede">' . self::e( $lede ) . '</p>';
		}
		$out .= '</div><div class="bw-pagehead__actions">'
			. '<a class="bw-btn bw-btn--secondary" href="' . self::e( $home_url ) . '">'
			. self::icon( 'arrow-left' ) . 'Back to the club site</a>'
			. '</div></header>';
		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $views
	 */
	private static function nav( array $views, string $current ): string {
		$out = '<nav class="bw-secnav" aria-label="Your account">';
		foreach ( $views as $view ) {
			$key    = (string) $view['key'];
			$active = $key === $current;
			$out   .= '<a class="bw-secnav__item' . ( $active ? ' is-active' : '' ) . '"'
				. ' href="' . self::e( self::view_url( $key ) ) . '"'
				. ( $active ? ' aria-current="page"' : '' ) . '>'
				. '<span class="clubhouse-member__navlabel">'
				. self::icon( (string) $view['icon'] )
				. self::e( (string) $view['label'] )
				. '</span></a>';
		}
		return $out . '</nav>';
	}

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
