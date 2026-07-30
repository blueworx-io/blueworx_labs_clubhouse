<?php
// includes/content/class-shortcodes.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode-expansion seam, so other plugins' shortcodes can be placed in
 * Clubhouse content — SureCart, SureDash, LatePoint, SureForms and the like.
 *
 * The render path (Sections / Page_Renderer) is deliberately WordPress-free:
 * the same strings are rendered by WordPress, by the DB-free preview and by the
 * unit tests, and do_shortcode() exists in only one of those three. So this
 * mirrors the seam Links already uses for internal URLs — the renderer asks for
 * a value, the environment decides what it means. Frontend installs
 * do_shortcode(); everywhere else the default applies.
 *
 * The default is deliberately the SAFE one, not the useful one: an environment
 * that has not opted in gets the text escaped, never executed. Nothing runs by
 * accident because a seam was left unset.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Shortcodes {

	/** @var (callable(string):string)|null */
	private static $expander = null;

	/**
	 * Install the environment's expander — WordPress passes do_shortcode.
	 * Passing null restores the escaping default (used by tests to reset).
	 *
	 * @param (callable(string):string)|null $expander
	 */
	public static function set_expander( ?callable $expander ): void {
		self::$expander = $expander;
	}

	/** True when a real expander is installed, i.e. shortcodes will actually run. */
	public static function is_live(): bool {
		return null !== self::$expander;
	}

	/**
	 * Turn stored shortcode text into the HTML to emit.
	 *
	 * The return value is trusted HTML and is NOT escaped downstream — that is
	 * the entire point, and it is the one place in the render path where output
	 * escaping is skipped on purpose. Two things keep that honest:
	 *
	 * 1. Only values stored in a catalogue field of type 'shortcode' ever reach
	 *    here. Ordinary text and textarea fields keep going through Sections::e()
	 *    exactly as before, so this cannot widen to the rest of the content.
	 * 2. Without an expander the text is escaped, so the preview and the tests
	 *    show the shortcode literally instead of executing anything.
	 *
	 * Anyone who can edit Clubhouse content can therefore run any registered
	 * shortcode — the same trust WordPress extends to anyone who can edit a post.
	 */
	public static function expand( string $text ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}
		if ( null === self::$expander ) {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		}
		return (string) ( self::$expander )( $text );
	}
}
