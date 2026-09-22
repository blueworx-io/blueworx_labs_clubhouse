<?php
// includes/admin/class-wordpress-pages.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress's own Pages screen, with club pages named and read-only on it.
 *
 * Club pages are real WordPress pages, so they turn up in WordPress's own
 * Pages list alongside a club's own pages, and the screen is on the menu for
 * that reason. It is somewhere to see them — to know they exist, and which
 * ones are ours — not somewhere to edit them. A club's words live in the
 * content store and are written on the page's own editor; the page body behind
 * each one is deliberately empty, and the plugin depends on these pages
 * existing at these slugs.
 *
 * BlueWorx Labs owns the list's Source column and the protection that goes
 * with it: a page with a source keeps only the View and Edit row actions, and
 * trashing or deleting it is refused whichever route the request takes. This
 * class answers Labs' filter with "Club page" for ours and leaves the rest to
 * Labs, so club pages and the shop's pages are protected the same way.
 *
 * What stays here is club-specific: a club page's status means "switched on",
 * which the Setup screen decides, so a status change from the list is undone.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Wordpress_Pages {

	/** WordPress's own top-level menu for pages. */
	public const MENU_SLUG = 'edit.php?post_type=page';

	/** What the Source column calls one of ours. */
	public const SOURCE = 'Club page';

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'blueworx_page_source', array( self::class, 'on_page_source' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( self::class, 'on_insert_post_data' ), 10, 2 );
	}

	/**
	 * What the Source column reads on a row. Pure.
	 *
	 * A label another plugin gave first is kept: a page is one plugin's, and
	 * ours are only the ones the club-page options name.
	 *
	 * @param string $label        The label so far.
	 * @param bool   $is_club_page Whether this row is one of ours.
	 */
	public static function source( string $label, bool $is_club_page ): string {
		if ( '' !== $label ) {
			return $label;
		}
		return $is_club_page ? self::SOURCE : '';
	}

	/**
	 * The post data a save is allowed to write. Pure.
	 *
	 * A club page's status is not the Pages list's to change. Switched on or
	 * off is decided on the Setup screen, which writes the visibility flag and
	 * moves the page to match; a status changed from anywhere else would switch
	 * a page off behind that screen's back, leaving the flag saying one thing
	 * and the page another. So the status is put back to whatever the flag
	 * calls for, and everything else in the save is left alone.
	 *
	 * @param array<string,mixed> $data    The post data about to be written.
	 * @param int                 $post_id The page being saved.
	 * @return array<string,mixed>
	 */
	public static function guard_status( array $data, int $post_id ): array {
		if ( ! Blueworx_Clubhouse_Club_Pages::is_club_page( $post_id ) ) {
			return $data;
		}
		$slug                = Blueworx_Clubhouse_Club_Pages::slug_for( $post_id );
		$data['post_status'] = Blueworx_Clubhouse_Club_Pages::status_for(
			Blueworx_Clubhouse_Club_Pages::is_visible( $slug )
		);
		return $data;
	}

	/**
	 * @param mixed $label   The label so far.
	 * @param mixed $post_id The page the row is for.
	 */
	public static function on_page_source( $label, $post_id = 0 ): string {
		return self::source(
			is_string( $label ) ? $label : '',
			Blueworx_Clubhouse_Club_Pages::is_club_page( self::post_id_of( $post_id ) )
		);
	}

	/**
	 * Bulk Edit and Quick Edit both reach wp_update_post(), which runs every
	 * save through wp_insert_post_data — the one hook a status change cannot
	 * get past, whether it came from the Pages list, the REST API or WP-CLI.
	 *
	 * @param mixed $data    The post data WordPress is about to write.
	 * @param mixed $postarr The submitted post array, which is where the ID is.
	 * @return array<string,mixed>
	 */
	public static function on_insert_post_data( $data, $postarr = array() ): array {
		$id = is_array( $postarr ) ? ( $postarr['ID'] ?? 0 ) : 0;
		return self::guard_status(
			is_array( $data ) ? $data : array(),
			self::post_id_of( $id )
		);
	}

	/** The post id behind a WP_Post, an id, or anything else. */
	private static function post_id_of( $post ): int {
		if ( is_object( $post ) && isset( $post->ID ) ) {
			return (int) $post->ID;
		}
		return is_numeric( $post ) ? (int) $post : 0;
	}
}
