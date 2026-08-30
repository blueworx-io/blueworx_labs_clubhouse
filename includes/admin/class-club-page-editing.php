<?php
// includes/admin/class-club-page-editing.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends Edit on a club page to the page's own editor screen, and keeps the
 * block editor shut.
 *
 * Club pages are real WordPress pages now, which is what gives the site its
 * sitemap, canonicals and search. It also hands a club a second, contradictory
 * place to write its words: WordPress's own Edit link, over a page body that
 * is deliberately empty. Anything typed there would be invisible on the front
 * end, because the club's words come from the content store and are rendered
 * by the plugin's own template.
 *
 * So editing keeps feeling exactly as it did. Edit goes to the record editor
 * behind that page; the block editor is switched off for these posts; and
 * typing the editor's address directly redirects there too, so there is no
 * way in rather than merely no link in.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Club_Page_Editing {

	/**
	 * The area a Page_Map slug is edited under — Page_Fields' own key, and
	 * what Page_Editors::editor_url() takes.
	 *
	 * Every area is named after its Page_Map slug — except Home, whose slug is
	 * '' and whose area Page_Fields names 'home'. An empty value would ask
	 * Page_Editors for a screen keyed by '', which does not exist.
	 */
	public static function tab_for( string $slug ): string {
		return '' === $slug ? 'home' : $slug;
	}

	/** The page's own editor screen, on the record behind it. */
	public static function editor_url( string $slug ): string {
		return Blueworx_Clubhouse_Page_Editors::editor_url( self::tab_for( $slug ) );
	}

	/**
	 * Whether a post should open in the block editor. Pure.
	 *
	 * Runs for every post in wp-admin, so it answers only for club pages and
	 * hands back whatever WordPress had already decided for anything else.
	 */
	public static function wants_block_editor( bool $default, int $post_id ): bool {
		return Blueworx_Clubhouse_Club_Pages::is_club_page( $post_id ) ? false : $default;
	}

	/** A club page's edit link points at its own editor screen; every other link stands. */
	public static function filter_edit_link( string $link, int $post_id ): string {
		return Blueworx_Clubhouse_Club_Pages::is_club_page( $post_id )
			? self::editor_url( Blueworx_Clubhouse_Club_Pages::slug_for( $post_id ) )
			: $link;
	}

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) || ! function_exists( 'add_action' ) ) {
			return;
		}
		add_filter( 'use_block_editor_for_post', array( self::class, 'on_use_block_editor_for_post' ), 10, 2 );
		add_filter( 'get_edit_post_link', array( self::class, 'on_get_edit_post_link' ), 10, 2 );
		add_action( 'load-post.php', array( self::class, 'redirect_to_club_pages' ) );
	}

	/**
	 * WordPress passes the post as a WP_Post (or an id, depending on the
	 * caller), so this shell only turns that into an id and defers to the pure
	 * decision above.
	 *
	 * @param mixed $default Whatever WordPress decided.
	 * @param mixed $post    WP_Post or post id.
	 */
	public static function on_use_block_editor_for_post( $default, $post = null ): bool {
		return self::wants_block_editor( (bool) $default, self::post_id_of( $post ) );
	}

	/**
	 * @param mixed $link    The edit link WordPress built.
	 * @param mixed $post_id The post it is for.
	 */
	public static function on_get_edit_post_link( $link, $post_id = 0 ): string {
		return self::filter_edit_link( (string) $link, self::post_id_of( $post_id ) );
	}

	/**
	 * Typing post.php?post=<id>&action=edit for a club page lands on its own
	 * editor screen too — otherwise switching the block editor off would only
	 * hide the door rather than close it, and the classic editor would open on
	 * an empty body instead.
	 */
	public static function redirect_to_club_pages(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which post is being opened, not acting on a request.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		if ( ! Blueworx_Clubhouse_Club_Pages::is_club_page( $post_id ) ) {
			return;
		}
		wp_safe_redirect( self::editor_url( Blueworx_Clubhouse_Club_Pages::slug_for( $post_id ) ) );
		exit;
	}

	/** The post id behind a WP_Post, an id, or anything else. */
	private static function post_id_of( $post ): int {
		if ( is_object( $post ) && isset( $post->ID ) ) {
			return (int) $post->ID;
		}
		return is_numeric( $post ) ? (int) $post : 0;
	}
}
