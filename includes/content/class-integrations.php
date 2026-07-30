<?php
// includes/content/class-integrations.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which third-party integrations this site actually has, so pages built around
 * another plugin can disappear entirely when that plugin is not installed —
 * from the front end, from the visibility toggles and from the content editor,
 * rather than leaving an owner configuring a page that renders nothing.
 *
 * A seam, for the same reason Links and Shortcodes are: the catalogue and the
 * visibility inventory are pure, and are read by the DB-free preview and by the
 * unit tests, neither of which has WordPress to ask. Frontend installs the real
 * detector.
 *
 * Detection is by shortcode tag rather than by plugin file or class name,
 * because the question being asked is "will this shortcode render?" — not "does
 * a directory exist?". A plugin present but not activated registers nothing,
 * and would otherwise leave a page of empty slots.
 *
 * The default is absent. An environment that has not opted in shows no
 * integration pages at all, which is the safe way round: a missing page is
 * obvious and recoverable, a page of broken shortcodes is neither.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Integrations {

	/** The shortcode LatePoint registers, taken as proof LatePoint is live. */
	public const LATEPOINT_TAG = 'latepoint_calendar';

	/**
	 * Individual sections that exist only when an integration does, keyed
	 * "page.section" => the shortcode tag they need. Whole pages declare this on
	 * Page_Map instead; this is for a section sitting on a page that stands on its
	 * own — the booking calendar on Calendar, which has fixtures to show either way.
	 *
	 * Declared once here because three places must agree: the renderer, the
	 * visibility inventory and the content catalogue. Two of those are held in
	 * lockstep by a test, and a third copy of the rule is how they drift.
	 *
	 * @var array<string,string>
	 */
	private const SECTION_REQUIREMENTS = array(
		'calendar.booking' => self::LATEPOINT_TAG,
	);

	/** @var (callable(string):bool)|null */
	private static $detector = null;

	/**
	 * Install the environment's detector — WordPress passes shortcode_exists.
	 * Null restores the "nothing is installed" default (used by tests to reset).
	 *
	 * @param (callable(string):bool)|null $detector
	 */
	public static function set_detector( ?callable $detector ): void {
		self::$detector = $detector;
	}

	/** True when the given shortcode tag is registered on this site. */
	public static function provides( string $shortcode_tag ): bool {
		if ( null === self::$detector ) {
			return false;
		}
		return (bool) ( self::$detector )( $shortcode_tag );
	}

	/** True when LatePoint is live and its pages should be offered. */
	public static function has_latepoint(): bool {
		return self::provides( self::LATEPOINT_TAG );
	}

	/**
	 * True when this section can be offered — either it needs no integration, or
	 * the one it needs is live.
	 */
	public static function section_available( string $page, string $section ): bool {
		$requires = self::SECTION_REQUIREMENTS[ $page . '.' . $section ] ?? '';
		return '' === $requires || self::provides( $requires );
	}
}
