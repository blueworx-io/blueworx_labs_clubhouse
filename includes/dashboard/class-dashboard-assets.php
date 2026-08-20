<?php
// includes/dashboard/class-dashboard-assets.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member area's stylesheet: the BlueWorx admin design system, vendored.
 *
 * Loaded on the two commerce pages this plugin dresses, plus the member area's
 * own route, and nowhere else. The club's public site is styled by
 * assets/looks/ and the two systems never meet — see assets/bw/README.md.
 *
 * Registered rather than enqueued at load: whether a request is one of ours is
 * decided per request, by page_key() below.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Dashboard_Assets {

	private const HANDLE = 'blueworx-clubhouse-bw';
	private const PATH   = 'assets/bw/bw.css';

	private const SURECART_HANDLE = 'blueworx-clubhouse-surecart';
	private const SURECART_PATH   = 'assets/bw/surecart.css';

	public static function handle(): string {
		return self::HANDLE;
	}

	/** Where the stylesheet is, relative to the plugin root. */
	public static function relative_path(): string {
		return self::PATH;
	}

	/** Where the SureCart token mapping is, relative to the plugin root. */
	public static function surecart_relative_path(): string {
		return self::SURECART_PATH;
	}

	/**
	 * Whether this page needs the SureCart token mapping. Pure.
	 *
	 * Checkout alone. The order confirmation page renders SureCart's
	 * confirmation blocks, which are read-only text rather than fields, and
	 * loading a field theme there would be dead weight on the one page a buyer
	 * lands on straight after paying.
	 */
	public static function wants_surecart_style( string $page_key ): bool {
		return 'checkout' === $page_key;
	}

	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( self::class, 'declare_style' ) );
	}

	/**
	 * Which page this plugin dresses a post is — 'checkout' or
	 * 'order-confirmation' — and '' for every other post on the site.
	 *
	 * The member area is not here any more: it is a Clubhouse route with no
	 * WordPress post under it, and its stylesheet is queued by Frontend.
	 */
	public static function page_key( int $post_id ): string {
		return Blueworx_Clubhouse_Commerce_Pages::page_key(
			$post_id,
			Blueworx_Clubhouse_Shop_Pages::page_id( 'checkout' ),
			Blueworx_Clubhouse_Shop_Pages::page_id( 'order-confirmation' )
		);
	}

	/**
	 * Tell WordPress the stylesheet exists, and put it on the page when this
	 * request is one of ours.
	 *
	 * The decision is made here, on wp_enqueue_scripts, rather than left to the
	 * content filters that draw the page: by the time those run the head has
	 * already been printed, so the stylesheet arrives in the footer and the
	 * member watches the page snap into shape after it has loaded. On checkout
	 * that flash lands on a payment form, which is the worst place for it.
	 * The queried object is known this early, which is all the decision needs.
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
		wp_register_style(
			self::SURECART_HANDLE,
			BLUEWORX_LABS_CLUBHOUSE_URL . self::SURECART_PATH,
			array( self::HANDLE ),
			defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : null
		);
		if ( ! function_exists( 'get_queried_object_id' ) ) {
			return;
		}
		$key = self::page_key( (int) get_queried_object_id() );
		if ( '' !== $key ) {
			self::enqueue();
			if ( self::wants_surecart_style( $key ) ) {
				wp_enqueue_style( self::SURECART_HANDLE );
			}
		}
	}

	/** Put it on this page. Safe to call more than once. */
	public static function enqueue(): void {
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( self::HANDLE );
		}
	}
}
