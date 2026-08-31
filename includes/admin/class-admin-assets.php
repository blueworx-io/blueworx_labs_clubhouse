<?php
// includes/admin/class-admin-assets.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The three files a Clubhouse admin screen built from the design system needs
 * — the system's stylesheet, its icon set, and our own chrome overrides — plus
 * the body class those overrides hang on.
 *
 * One place, so a screen cannot half-load the system. A screen with the
 * stylesheet but not the icon module draws every [data-lucide] element as an
 * empty box, which reads as a layout bug rather than a missing file.
 *
 * Two ways in: enqueue() for a screen that is entirely ours, and
 * enqueue_as_a_guest() for a panel of ours on somebody else's screen.
 *
 * The body class is added here rather than being written into the stylesheet
 * as a page hook, because a submenu's hook is named after its parent and this
 * plugin's pages have been reparented once already (issue #145). A class we
 * add ourselves cannot go stale that way.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Admin_Assets {

	public const STYLE_HANDLE  = 'blueworx-admin-design';
	public const CHROME_HANDLE = 'blueworx-admin-chrome';
	public const ICONS_HANDLE  = 'blueworx-admin-icons';

	/** The class the chrome overrides are scoped to. */
	public const BODY_CLASS = 'clubhouse-bw';

	/**
	 * Call from a screen's own enqueue(), once past its hook guard.
	 *
	 * Safe to call from more than one screen in a request: wp_enqueue_style
	 * de-duplicates by handle, and the body-class filter is added at most once.
	 */
	public static function enqueue(): void {
		self::enqueue_as_a_guest();

		wp_enqueue_style(
			self::CHROME_HANDLE,
			BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/admin-chrome.css',
			array( self::STYLE_HANDLE ),
			BLUEWORX_LABS_CLUBHOUSE_VERSION
		);

		// admin_enqueue_scripts fires before admin-header.php prints <body>, so
		// a filter added here still reaches that class list.
		if ( ! has_filter( 'admin_body_class', array( self::class, 'body_class' ) ) ) {
			add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
		}
	}

	/**
	 * The system's stylesheet and icons, and nothing else.
	 *
	 * For a Clubhouse panel sitting inside somebody else's screen — the
	 * owner's dashboard widget is the one. The chrome overrides enqueue()
	 * adds take the padding off the content column and hide the footer, which
	 * is right for a screen that is entirely ours and wrong for one we are a
	 * guest on: on the dashboard they would move WordPress's own widgets.
	 *
	 * The icons go with the stylesheet either way. A panel with the styles but
	 * not the icons draws every [data-lucide] element as an empty box, which
	 * reads as a layout bug rather than a missing file.
	 */
	public static function enqueue_as_a_guest(): void {
		$url = BLUEWORX_LABS_CLUBHOUSE_URL;
		$ver = BLUEWORX_LABS_CLUBHOUSE_VERSION;

		wp_enqueue_style( self::STYLE_HANDLE, $url . 'assets/blueworx-admin-design.css', array(), $ver );

		// A module, because the icon file is one: it upgrades every
		// [data-lucide] element in place and watches for new ones.
		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( self::ICONS_HANDLE, $url . 'assets/blueworx-admin-icons.js', array(), $ver );
			return;
		}
		// WordPress below 6.5 has no module API. A plain script tag still
		// runs it, once the type is corrected on the way out.
		wp_enqueue_script( self::ICONS_HANDLE, $url . 'assets/blueworx-admin-icons.js', array(), $ver, true );
		add_filter( 'script_loader_tag', array( self::class, 'as_module' ), 10, 2 );
	}

	/**
	 * @param mixed $classes The space-separated list WordPress has built.
	 */
	public static function body_class( $classes ): string {
		return trim( (string) $classes . ' ' . self::BODY_CLASS );
	}

	/**
	 * @param mixed $tag    The script tag WordPress built.
	 * @param mixed $handle The handle it is for.
	 */
	public static function as_module( $tag, $handle = '' ): string {
		return self::ICONS_HANDLE === $handle
			? str_replace( '<script ', '<script type="module" ', (string) $tag )
			: (string) $tag;
	}
}
