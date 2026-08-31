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
 * content store and are edited on the page's own editor; a body here would be
 * a second, contradictory copy.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Club_Pages {

	/** A published page is reachable to a visitor; a private one only to a signed-in member. */
	private const PUBLIC_STATUS  = 'publish';
	private const PRIVATE_STATUS = 'private';

	/**
	 * The status of a page an owner has switched off in Setup. A draft is out
	 * of the sitemap, out of search, and 404s to a visitor — which is what the
	 * visibility flag always meant, said in a way WordPress itself understands.
	 */
	private const HIDDEN_STATUS = 'draft';

	/**
	 * The post status a club page should be in. Pure.
	 *
	 * Switched on is published, switched off is a draft. No club page is ever a
	 * WordPress private post: the member area's page is published and does its
	 * own sign-in check, because a private page 404s for every ordinary member.
	 */
	public static function status_for( bool $visible ): string {
		return $visible ? self::PUBLIC_STATUS : self::HIDDEN_STATUS;
	}

	/**
	 * The Setup/Visibility key for a slug — Home's slug is '' and its key is
	 * 'home', the same rename option_name() makes. Pure.
	 */
	public static function page_key( string $slug ): string {
		return '' === $slug ? 'home' : $slug;
	}

	/**
	 * The slug a Setup/Visibility key names, or null when it names no club
	 * page. Pure. Never a truthiness check — Home's answer is the empty string.
	 */
	public static function slug_for_page_key( string $page ): ?string {
		$slug = 'home' === $page ? '' : $page;
		return Blueworx_Clubhouse_Page_Map::has( $slug ) ? $slug : null;
	}

	/**
	 * Whether an owner has this club page switched on. Defaults to on, as
	 * Visibility itself does, when there is no WordPress runtime to read.
	 */
	public static function is_visible( string $slug ): bool {
		if ( ! function_exists( 'get_option' ) || ! class_exists( 'Blueworx_Clubhouse_Visibility' ) ) {
			return true;
		}
		$visibility = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Options_Storage() );
		return $visibility->is_page_visible( self::page_key( $slug ) );
	}

	/**
	 * Put a club page's status back in step with the visibility flag
	 * Visibility::set_page_visible() has just written.
	 *
	 * The flag stays the record an owner edits and resolve_slug() reads; the
	 * status is the same answer in WordPress's own terms, so a switched-off
	 * page leaves the sitemap and search rather than only declining to render.
	 */
	public static function sync_status( string $page, bool $visible ): void {
		$slug = self::slug_for_page_key( $page );
		if ( null === $slug || ! function_exists( 'wp_update_post' ) ) {
			return;
		}
		$post_id = self::post_id( $slug );
		$status  = self::current_status( $post_id );
		$wanted  = self::status_for( $visible );
		if ( '' === $status || $status === $wanted ) {
			return;
		}
		self::set_status( $post_id, $wanted );
	}

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
	 * A club page is published while it is switched on and a draft once an owner
	 * switches it off, the member area included — never a WordPress private
	 * post, whatever the page map says. The page map's
	 * 'private' flag means "keep this out of the SEO report", not "make this a
	 * WordPress private post" — and the SEO layer reads that flag itself, via
	 * Page_Map::is_private(). A private post is filtered out of WordPress's own
	 * page query for anyone without read_private_pages, which is every ordinary
	 * member, so a private member area 404s for exactly the people it is for.
	 * Publishing it widens nothing: the member area route is already public and
	 * does its own sign-in check, sending a signed-out visitor to /login/.
	 *
	 * @return array<string,string>
	 */
	public static function desired( string $slug, string $label, bool $visible = true ): array {
		return array(
			'post_type'    => 'page',
			'post_name'    => '' === $slug ? 'home' : $slug,
			'post_title'   => $label,
			'post_status'  => self::status_for( $visible ),
			'post_content' => '',
		);
	}

	/**
	 * Create or repair the real page behind every club page.
	 *
	 * Walks Page_Map::pages() — the full list, not available() — so a club
	 * that installs an integration later already has the page waiting for it.
	 * For each one: a stored id naming a published page of type 'page' is left
	 * alone; a stored id naming a page in any other status — trashed, or left
	 * 'private' by an earlier version of this plugin — is republished rather
	 * than duplicated; anything else gets a fresh page and its id stored.
	 * Idempotent — running it twice creates nothing.
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
			self::ensure_one( $page['slug'], $page['label'] );
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

	/**
	 * The create-or-repair for a single club page, its status included.
	 *
	 * One rule decides the status — status_for() on the owner's visibility flag
	 * — so creating a page, saving Setup, and this repair can never disagree.
	 * The repair is also what carries an existing site across: a page whose
	 * status has drifted from its flag (left 'private' by an earlier version of
	 * this plugin, trashed by an admin, or still published after being switched
	 * off) is put back here, with no separate migration to run.
	 */
	private static function ensure_one( string $slug, string $label ): void {
		$post_id = self::post_id( $slug );
		$status  = self::current_status( $post_id );
		$visible = self::is_visible( $slug );
		$wanted  = self::status_for( $visible );

		if ( $status === $wanted ) {
			return;
		}

		// The page is there but is in the wrong status. Repaired in place rather
		// than duplicated, so the club keeps the page and everything pointing at it.
		if ( '' !== $status ) {
			self::set_status( $post_id, $wanted );
			return;
		}

		$inserted = wp_insert_post( self::desired( $slug, $label, $visible ) );
		if ( is_numeric( $inserted ) && (int) $inserted > 0 && function_exists( 'update_option' ) ) {
			update_option( self::option_name( $slug ), (int) $inserted );
		}
	}

	/** Move an existing page to the status its visibility flag calls for. */
	private static function set_status( int $post_id, string $status ): void {
		if ( ! function_exists( 'wp_update_post' ) ) {
			return;
		}
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
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
