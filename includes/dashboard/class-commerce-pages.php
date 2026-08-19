<?php
// includes/dashboard/class-commerce-pages.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout and order confirmation, in the member area's look.
 *
 * The same frame as the member dashboard, minus the nav: someone mid-purchase
 * should not be offered six places to wander off to. The page's own content is
 * passed through untouched — the shop renders the shop, exactly as everywhere
 * else in this plugin.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Commerce_Pages {

	/** After SureCart has expanded its own blocks into the content. */
	private const PRIORITY = 30;

	/**
	 * The pages taken over, keyed the way Shop_Pages keys them.
	 *
	 * @var array<string,array{title:string,lede:string}>
	 */
	public const PAGES = array(
		'checkout'           => array(
			'title' => 'Checkout',
			'lede'  => 'A few details and you are done.',
		),
		'order-confirmation' => array(
			'title' => 'Thank you',
			'lede'  => 'Your order is confirmed. A receipt is on its way by email.',
		),
	);

	/**
	 * Guards against this filter re-entering itself.
	 *
	 * The page's own content is the shop's checkout, and a block or shortcode
	 * inside it is free to apply the_content itself. On the same post that would
	 * come straight back in here and recurse until the request ran out of
	 * memory — a white screen where someone is trying to pay. The shop does not
	 * do it today; the guard costs a boolean and makes it impossible.
	 */
	private static bool $rendering = false;

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'the_content', array( self::class, 'dress' ), self::PRIORITY );
	}

	/**
	 * Which of these pages a post is, or '' for anything else. Pure.
	 *
	 * An id of 0 means the shop has not recorded that page, and must never
	 * match — 0 would otherwise dress whatever a broken query returned.
	 */
	public static function page_key( int $post_id, int $checkout_id, int $confirmation_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		if ( $post_id === $checkout_id ) {
			return 'checkout';
		}
		if ( $post_id === $confirmation_id ) {
			return 'order-confirmation';
		}
		return '';
	}

	/**
	 * @param string $content
	 */
	public static function dress( $content ): string {
		$content = (string) $content;
		if ( self::$rendering ) {
			return $content;
		}
		if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$key = Blueworx_Clubhouse_Dashboard_Assets::page_key( (int) get_the_ID() );
		if ( ! isset( self::PAGES[ $key ] ) ) {
			return $content;
		}
		// No check on whether anyone is signed in, unlike the member dashboard:
		// a club sells to guests, and someone paying without an account is an
		// ordinary sale rather than a mistake. Nothing here needs to know who
		// they are — the page's own content is the shop's, and it decides.

		self::$rendering = true;
		try {
			Blueworx_Clubhouse_Dashboard_Assets::enqueue();

			return Blueworx_Clubhouse_Dashboard_Shell::bare(
				self::PAGES[ $key ]['title'],
				self::PAGES[ $key ]['lede'],
				Blueworx_Clubhouse_Dashboard_Shell::card( '', $content ),
				function_exists( 'home_url' ) ? (string) home_url( '/' ) : '/',
				Blueworx_Clubhouse_Member_Dashboard::club_name()
			);
		} finally {
			self::$rendering = false;
		}
	}
}
