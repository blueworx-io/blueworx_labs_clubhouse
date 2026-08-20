<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The WordPress page behind each club page.
 *
 * Club pages have been rewrite-rule routes with nothing in the database
 * behind them. That cost the site everything WordPress gives a real page —
 * the sitemap, canonicals, search, and anything an SEO plugin would do. This
 * class owns the mapping between a club page's slug (as Page_Map names it)
 * and the id of the real page standing in for it, and creates or repairs that
 * page. Nothing serves from it yet — that is a later task.
 *
 * The page body it creates is always empty. A club's words live in the
 * content store and are still edited on the Club Pages screens; a body here
 * would be a second, contradictory copy.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Club_Pages {

	/** A published page is reachable to a visitor; a private one only to a signed-in member. */
	private const PUBLIC_STATUS  = 'publish';
	private const PRIVATE_STATUS = 'private';

	/**
	 * The option a page's id is stored under. Scoped per slug, so one missing
	 * page never hides another. Home's slug is '' — the option still needs a
	 * key of its own, or it would collide with every other empty lookup.
	 */
	public static function option_name( string $slug ): string {
		return 'clubhouse_page_id_' . ( '' === $slug ? 'home' : $slug );
	}

	/** The stored id for a club page, or 0 when the option is absent or junk. */
	public static function post_id( string $slug ): int {
		if ( ! function_exists( 'get_option' ) ) {
			return 0;
		}
		$stored = get_option( self::option_name( $slug ), 0 );
		return is_numeric( $stored ) ? max( 0, (int) $stored ) : 0;
	}

	/**
	 * The Page_Map slug a post id stands in for, or '' when it is not one of
	 * ours — which is also Home's own answer. Callers wanting the yes/no
	 * question use is_club_page() instead, so the two cases are never confused.
	 */
	public static function slug_for( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			if ( self::post_id( $page['slug'] ) === $post_id ) {
				return $page['slug'];
			}
		}
		return '';
	}

	/** True when a post id is the real page behind one of the club pages. */
	public static function is_club_page( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			if ( self::post_id( $page['slug'] ) === $post_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The wp_insert_post() args for a club page. Pure. Home's slug is ''; the
	 * page it describes always gets the post_name 'home'. The body is always
	 * empty — the club's words live in the content store, not here.
	 *
	 * @return array<string,string>
	 */
	public static function desired( string $slug, string $label, bool $private ): array {
		return array(
			'post_type'    => 'page',
			'post_name'    => '' === $slug ? 'home' : $slug,
			'post_title'   => $label,
			'post_status'  => $private ? self::PRIVATE_STATUS : self::PUBLIC_STATUS,
			'post_content' => '',
		);
	}

	/**
	 * Create or repair the real page behind every club page.
	 *
	 * Walks Page_Map::pages() — the full list, not available() — so a club
	 * that installs an integration later already has the page waiting for it.
	 * For each one: a stored id naming a published or private page of type
	 * 'page' is left alone; a stored id naming a trashed page is restored
	 * rather than duplicated; anything else gets a fresh page and its id
	 * stored. Idempotent — running it twice creates nothing.
	 *
	 * Also makes Home the site's static front page — Home's slug is '', so
	 * without this the site root would never reach it. Only when the front
	 * page is unset (posts on front, the fresh-install default), zero, or
	 * names a page that no longer exists: a club that has deliberately chosen
	 * a different front page keeps it.
	 */
	public static function ensure(): void {
		if ( ! function_exists( 'wp_insert_post' ) ) {
			return;
		}
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			self::ensure_one( $page['slug'], $page['label'], (bool) ( $page['private'] ?? false ) );
		}
		self::ensure_home_is_front_page();
	}

	/**
	 * Point show_on_front/page_on_front at Home, unless a club has deliberately
	 * chosen its own front page. Pure decision in should_take_over_front_page();
	 * this is only the WordPress-coupled shell around it.
	 */
	private static function ensure_home_is_front_page(): void {
		if ( ! function_exists( 'update_option' ) || ! function_exists( 'get_option' ) ) {
			return;
		}
		$home_id = self::post_id( '' );
		if ( $home_id <= 0 ) {
			return;
		}
		$show_on_front = (string) get_option( 'show_on_front', 'posts' );
		$page_on_front = (int) get_option( 'page_on_front', 0 );
		if ( ! self::should_take_over_front_page( $show_on_front, $page_on_front, self::current_status( $page_on_front ) ) ) {
			return;
		}
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
	}

	/**
	 * Whether Home should become the front page. Pure, so the guard is
	 * unit-testable without a WordPress runtime.
	 *
	 * A club that has already set show_on_front to 'page' with a
	 * page_on_front naming a page that still exists has made a deliberate
	 * choice, and keeps it. Anything else — posts on front, no page chosen,
	 * or a chosen page that has since been trashed or deleted — is treated
	 * as "nothing chosen" and Home takes over.
	 */
	public static function should_take_over_front_page( string $show_on_front, int $page_on_front, string $chosen_page_status ): bool {
		if ( 'page' !== $show_on_front ) {
			return true;
		}
		if ( $page_on_front <= 0 ) {
			return true;
		}
		return self::PUBLIC_STATUS !== $chosen_page_status && self::PRIVATE_STATUS !== $chosen_page_status;
	}

	/** The create-or-repair for a single club page. */
	private static function ensure_one( string $slug, string $label, bool $private ): void {
		$post_id = self::post_id( $slug );
		$status  = self::current_status( $post_id );

		if ( self::PUBLIC_STATUS === $status || self::PRIVATE_STATUS === $status ) {
			return;
		}

		if ( 'trash' === $status ) {
			self::restore( $post_id, $private );
			return;
		}

		$inserted = wp_insert_post( self::desired( $slug, $label, $private ) );
		if ( is_numeric( $inserted ) && (int) $inserted > 0 && function_exists( 'update_option' ) ) {
			update_option( self::option_name( $slug ), (int) $inserted );
		}
	}

	/** Bring a trashed page back to the status a club page needs. */
	private static function restore( int $post_id, bool $private ): void {
		if ( ! function_exists( 'wp_update_post' ) ) {
			return;
		}
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $private ? self::PRIVATE_STATUS : self::PUBLIC_STATUS,
			)
		);
	}

	/**
	 * The status of the stored id, or '' when it names nothing or is not a
	 * page — which ensure_one() treats the same as "nothing stored".
	 */
	private static function current_status( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_status' ) ) {
			return '';
		}
		if ( function_exists( 'get_post_type' ) && 'page' !== get_post_type( $post_id ) ) {
			return '';
		}
		$status = get_post_status( $post_id );
		return is_string( $status ) ? $status : '';
	}
}
