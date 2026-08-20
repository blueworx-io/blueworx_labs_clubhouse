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
	// Checkout no longer reads its own title and lede — it draws its heading
	// from Dashboard_Shell::checkout() instead — but the entry stays: dress()
	// gates on isset( self::PAGES[ $key ] ) to decide whether a post is one of
	// ours at all, and removing it would stop the page being dressed.
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

			if ( 'checkout' === $key ) {
				// Resolved once and closed over, rather than called from inside the
				// visibility callback: context() rebuilds options storage, the full
				// look registry and the demo lookup on every call, and the callback
				// below runs once per candidate link. On the page where a buyer is
				// waiting to pay, that would mean building it up to three times.
				$visibility = Blueworx_Clubhouse_Frontend::context()->visibility;
				return Blueworx_Clubhouse_Dashboard_Shell::checkout(
					array(
						'club_name'  => Blueworx_Clubhouse_Member_Dashboard::club_name(),
						'logo_url'   => '',
						'home_url'   => function_exists( 'home_url' ) ? (string) home_url( '/' ) : '/',
						'home_label' => self::back_label( Blueworx_Clubhouse_Member_Dashboard::club_name() ),
						'body'       => $content,
						'footnote'   => '',
						'links'      => self::footer_links(
							static fn ( string $slug ): bool => $visibility->is_page_visible( $slug ),
							static fn ( string $slug ): string => Blueworx_Clubhouse_Frontend::link_url( $slug )
						),
					)
				);
			}

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

	/**
	 * The club pages a buyer is entitled to read before paying, and their
	 * addresses. Pure — the callers hand in the two questions this cannot
	 * answer itself.
	 *
	 * Contact is here as well as the two legal pages: someone who has hit a
	 * problem halfway through paying needs a way to ask about it, and the
	 * header offers none.
	 *
	 * @param callable(string):bool   $visible
	 * @param callable(string):string $url
	 * @return array<int,array{label:string,href:string}>
	 */
	public static function footer_links( callable $visible, callable $url ): array {
		$out = array();
		foreach ( array(
			'terms'   => 'Terms and conditions',
			'privacy' => 'Privacy notice',
			'contact' => 'Contact the club',
		) as $slug => $label ) {
			if ( ! $visible( $slug ) ) {
				continue;
			}
			$href = trim( $url( $slug ) );
			if ( '' === $href ) {
				continue;
			}
			$out[] = array(
				'label' => $label,
				'href'  => $href,
			);
		}
		return $out;
	}

	/**
	 * "Back to Crewe Vagrants", or the generic wording when a club has not
	 * named itself yet. Pure.
	 */
	public static function back_label( string $club_name ): string {
		$club = trim( $club_name );
		return '' !== $club ? 'Back to ' . $club : 'Back to the club site';
	}
}
