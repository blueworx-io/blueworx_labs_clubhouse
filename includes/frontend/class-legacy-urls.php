<?php
// includes/frontend/class-legacy-urls.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forwards the addresses the plugin's own rewrite rules used to answer.
 *
 * Club pages were routes with nothing in the database behind them, reached
 * either through a rewrite rule ('/news/', '/sports/rugby/') or through the
 * query var that rule filled in ('?clubhouse_page=news'). Each one is a real
 * WordPress page now and is found the way any page is, so the rules are gone —
 * but the old addresses are in bookmarks, in newsletters, and in whatever
 * links to the club from elsewhere. Left alone they would 404.
 *
 * Two shapes are forwarded:
 *
 *   - '?clubhouse_page=news' and friends, whatever they are hung off.
 *   - A path whose first segment names a club page, when nothing else has
 *     claimed it — which covers '/sports/rugby/', the one address that never
 *     had a page of its own, and a club page whose real page was given a
 *     different slug because the site already had one by that name.
 *
 * The decisions are pure functions, so both are unit-tested without a
 * WordPress runtime.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Legacy_Urls {

	/** The two pages that ever had a sport or team hung off their address. */
	private const ITEM_PAGES = array( 'sports', 'teams' );

	/**
	 * The query params worth carrying across a redirect — the ones that change
	 * what the destination shows. Anything else on the old address is dropped
	 * rather than passed through blind.
	 *
	 * @return array<int,string>
	 */
	private static function carried_params(): array {
		return array(
			Blueworx_Clubhouse_Links::ITEM_PARAM,
			Blueworx_Clubhouse_Links::FILTER_PARAM,
			Blueworx_Clubhouse_News::PAGE_PARAM,
			Blueworx_Clubhouse_Auth_View::ACTION,
		);
	}

	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		// Priority 1: ahead of Member_Dashboard::route() at 5 and Frontend's own
		// 404 pass at 10, so an old address is forwarded to the page it named
		// before anything else decides what to make of the request.
		add_action( 'template_redirect', array( self::class, 'redirect' ), 1 );
	}

	/**
	 * The request path with the subdirectory WordPress is installed in taken
	 * off, so '/club/sports/rugby/' reads as 'sports/rugby' on a site that is
	 * not at the domain root. Pure.
	 */
	public static function relative_path( string $request_uri, string $home_path ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure helper, unit-tested without a WordPress runtime.
		$path = (string) parse_url( $request_uri, PHP_URL_PATH );
		$path = trim( $path, '/' );
		$home = trim( $home_path, '/' );
		if ( '' !== $home && ( $path === $home || str_starts_with( $path, $home . '/' ) ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		return $path;
	}

	/**
	 * Which club page an old address was asking for, and the sport or team it
	 * named, or null when it was not one of ours. Pure.
	 *
	 * The query var is answered wherever it appears, because that form never
	 * depended on the path. The path is only read when nothing else claimed the
	 * request: a real page sitting at that address has already answered, and
	 * stepping in front of it would redirect a page to itself.
	 *
	 * @param mixed  $page_param The old clubhouse_page value, if the request carried one.
	 * @param string $path       The request path, relative to the WordPress install.
	 * @param bool   $is_404     Whether WordPress found nothing for this request.
	 *
	 * @return array{slug:string,item:string}|null
	 */
	public static function target( mixed $page_param, string $path, bool $is_404 ): ?array {
		if ( is_string( $page_param ) && '' !== $page_param ) {
			// 'home' is the literal the query form always used for Home, whose
			// real slug is the empty string.
			$slug = 'home' === $page_param ? '' : $page_param;
			return Blueworx_Clubhouse_Page_Map::has( $slug ) ? array(
				'slug' => $slug,
				'item' => '',
			) : null;
		}
		if ( ! $is_404 || '' === trim( $path, '/' ) ) {
			return null;
		}
		$parts = explode( '/', trim( $path, '/' ) );
		// One segment is the page, two is a sport or team on it. Anything deeper
		// was never an address this plugin produced.
		if ( count( $parts ) > 2 ) {
			return null;
		}
		$slug = strtolower( $parts[0] );
		if ( '' === $slug || ! Blueworx_Clubhouse_Page_Map::has( $slug ) ) {
			return null;
		}
		$item = 1 < count( $parts ) ? $parts[1] : '';
		// Sports and Teams are the only two pages that ever hung an item off
		// their address. '/news/anything/' named nothing then and names nothing
		// now, so it is left to 404 rather than bounced to the News page
		// carrying a selector that matches nothing.
		if ( '' !== $item && ! in_array( $slug, self::ITEM_PAGES, true ) ) {
			return null;
		}
		return array(
			'slug' => $slug,
			'item' => $item,
		);
	}

	/**
	 * Whether a request can be forwarded at all. Pure.
	 *
	 * Only a GET or a HEAD. A redirect answers a POST by asking the browser to
	 * repeat it as a GET, which throws the submission away — a sign-in with the
	 * wrong password came back as a blank login form rather than an error,
	 * because WordPress reports 404 for a page number that does not exist and
	 * this stepped in front of the form handler. WordPress's own canonical
	 * redirect declines a POST for the same reason.
	 */
	public static function forwards( string $method ): bool {
		return in_array( strtoupper( $method ), array( 'GET', 'HEAD' ), true );
	}

	/** Forward an old address to the page that answers for it now. */
	public static function redirect(): void {
		if ( ! function_exists( 'wp_safe_redirect' ) || ! function_exists( 'home_url' ) ) {
			return;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( ! self::forwards( $method ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing of a GET address, no state change.
		$page_param = $_GET[ Blueworx_Clubhouse_Frontend::QUERY_VAR ] ?? null;
		$page_param = is_string( $page_param ) ? wp_unslash( $page_param ) : null;
		// WordPress found the club page for this address by itself, and the 404
		// is about something else on the request. Forwarding it to the address
		// it is already on would be a loop. The old query form is still answered
		// here, because it can be carried on any address including this one.
		if ( ( null === $page_param || '' === $page_param )
			&& function_exists( 'get_queried_object_id' )
			&& Blueworx_Clubhouse_Club_Pages::is_club_page( (int) get_queried_object_id() ) ) {
			return;
		}
		$target = self::target(
			$page_param,
			self::current_path(),
			function_exists( 'is_404' ) && is_404()
		);
		if ( null === $target ) {
			return;
		}
		$url = self::destination( $target['slug'], $target['item'] );
		if ( '' === $url ) {
			return;
		}
		// 301: these addresses are not coming back, and a search engine that
		// already has one should be told where the page moved to rather than
		// asked again on every crawl.
		wp_safe_redirect( $url, 301 );
		exit;
	}

	/** This request's path, relative to the WordPress install. */
	private static function current_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return self::relative_path( $uri, (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	}

	/**
	 * The address a club page answers at now, or '' when there is nothing worth
	 * forwarding to.
	 *
	 * Only a published page counts. A club page an owner has switched off is a
	 * draft, and 301ing a visitor to an address that 404s anyway is worse than
	 * letting the one they typed 404 on its own — it also permanently teaches a
	 * search engine the wrong address for a page that may come back.
	 */
	private static function destination( string $slug, string $item ): string {
		$post_id = Blueworx_Clubhouse_Club_Pages::post_id( $slug );
		if ( $post_id <= 0 || ! function_exists( 'get_post_status' ) || ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return '';
		}
		$url = get_permalink( $post_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		$args = array();
		foreach ( self::carried_params() as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing of a GET address, no state change.
			$value = $_GET[ $param ] ?? '';
			if ( is_string( $value ) && '' !== $value ) {
				$args[ $param ] = wp_unslash( $value );
			}
		}
		if ( '' !== $item ) {
			$args[ Blueworx_Clubhouse_Links::ITEM_PARAM ] = $item;
		}
		// add_query_arg() encodes what it is given, so the values go in raw.
		return array() === $args ? $url : add_query_arg( $args, $url );
	}
}
