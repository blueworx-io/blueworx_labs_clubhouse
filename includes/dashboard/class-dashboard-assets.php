<?php
// includes/dashboard/class-dashboard-assets.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member area's stylesheet: the BlueWorx admin design system, vendored.
 *
 * Loaded on the three pages this plugin takes over and nowhere else. The club's
 * public site is styled by assets/looks/ and the two systems never meet — see
 * assets/bw/README.md.
 *
 * Registered rather than enqueued at load: whether a request is one of ours is
 * decided per request by Member_Dashboard, which calls enqueue() once it knows.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Dashboard_Assets {

	private const HANDLE = 'blueworx-clubhouse-bw';
	private const PATH   = 'assets/bw/bw.css';

	public static function handle(): string {
		return self::HANDLE;
	}

	/** Where the stylesheet is, relative to the plugin root. */
	public static function relative_path(): string {
		return self::PATH;
	}

	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( self::class, 'declare_style' ) );
	}

	/**
	 * Tell WordPress the stylesheet exists. Nothing is put on the page here —
	 * enqueue() does that, and only for a request we are rendering.
	 */
	public static function declare_style(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'BLUEWORX_LABS_CLUBHOUSE_URL' ) ) {
			return;
		}
		wp_register_style(
			self::HANDLE,
			BLUEWORX_LABS_CLUBHOUSE_URL . self::PATH,
			array(),
			defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : null
		);
	}

	/** Put it on this page. Safe to call more than once. */
	public static function enqueue(): void {
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( self::HANDLE );
		}
	}
}
