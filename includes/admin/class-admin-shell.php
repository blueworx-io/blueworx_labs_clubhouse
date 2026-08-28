<?php
// includes/admin/class-admin-shell.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The opening of every Clubhouse admin screen built from the BlueWorx admin
 * design system: the full-bleed wrapper, and the page header.
 *
 * The system fixes this skeleton — page header, then panels, then (on an
 * editor) a save bar. Building it in one place is what stops five screens each
 * inventing their own version of it, which is how the bespoke screens this
 * replaces drifted apart in the first place.
 *
 * Pure: it builds a string, makes no WordPress calls and reads no request
 * data, so it is testable without WordPress loaded.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Admin_Shell {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Open a screen.
	 *
	 * open() leaves three elements standing — the wrapper, the page, and the
	 * body — and closes the page header itself. close() shuts the two that
	 * remain.
	 *
	 * @param string $eyebrow Where the reader is, e.g. "Clubhouse · Site access".
	 * @param string $title   The screen's name.
	 * @param string $lede    One sentence, or ''.
	 * @param string $actions Markup for the top-right actions, or ''. Built by
	 *                        the calling screen from its own strings — never
	 *                        from anything a club typed — so it is inserted as
	 *                        markup rather than escaped.
	 */
	public static function open( string $eyebrow, string $title, string $lede = '', string $actions = '' ): string {
		$out  = '<div class="wrap bw-wrap"><div class="bw-admin bw-page">';
		$out .= '<div class="bw-pagehead"><div class="bw-pagehead__titles">';
		$out .= '<p class="bw-pagehead__eyebrow">' . self::esc( $eyebrow ) . '</p>';
		$out .= '<h1 class="bw-pagehead__h1">' . self::esc( $title ) . '</h1>';
		if ( '' !== $lede ) {
			$out .= '<p class="bw-pagehead__lede">' . self::esc( $lede ) . '</p>';
		}
		$out .= '</div>';
		if ( '' !== $actions ) {
			$out .= '<div class="bw-pagehead__actions">' . $actions . '</div>';
		}
		$out .= '</div>';
		return $out . '<div class="bw-page__body bw-page__body--single">';
	}

	/** Close the body, the page and the wrapper left open by open(). */
	public static function close(): string {
		return '</div></div></div>';
	}

	/**
	 * A panel. Every screen in this plugin is a stack of these, so the card's
	 * head/body structure is built once rather than by hand five times.
	 *
	 * @param string $eyebrow Where on the screen this sits, or ''.
	 * @param string $title   The panel's name.
	 * @param string $note    One sentence saying what the panel is for, or ''.
	 * @param string $body    The panel's contents, as markup.
	 */
	public static function card( string $eyebrow, string $title, string $note, string $body ): string {
		$out = '<div class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">';
		if ( '' !== $eyebrow ) {
			$out .= '<p class="bw-card__eyebrow">' . self::esc( $eyebrow ) . '</p>';
		}
		$out .= '<h2 class="bw-card__title">' . self::esc( $title ) . '</h2>';
		if ( '' !== $note ) {
			$out .= '<p class="bw-card__note">' . self::esc( $note ) . '</p>';
		}
		$out .= '</div></div><div class="bw-card__body">' . $body . '</div></div>';
		return $out;
	}
}
