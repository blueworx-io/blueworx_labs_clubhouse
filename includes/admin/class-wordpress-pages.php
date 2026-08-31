<?php
// includes/admin/class-wordpress-pages.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress's own Pages screen, with club pages read-only on it.
 *
 * Club pages are real WordPress pages, so they turn up in WordPress's own
 * Pages list alongside a club's own pages, and the screen is on the menu for
 * that reason. It is somewhere to see them — to know they exist, and which
 * ones are ours — not somewhere to edit them. A club's words live in the
 * content store and are written on the page's own editor; the page body behind
 * each one is deliberately empty, and the plugin depends on these pages
 * existing at these slugs.
 *
 * So every row action that could change one is taken away: quick edit, which
 * renames and retitles inline, and trash. Edit stays, and Club_Page_Editing
 * has already pointed it at that editor. Deleting is refused for
 * real as well as hidden — a row action missing from a list is a courtesy, not
 * a guarantee, and a bulk action or another plugin reaches the same place.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Wordpress_Pages {

	/** WordPress's own top-level menu for pages. */
	public const MENU_SLUG = 'edit.php?post_type=page';

	/** The list column that says which rows are ours. */
	public const COLUMN = 'clubhouse_club_page';

	/**
	 * Row actions that could rename, retitle or bin a page. Edit and view only
	 * ever look at one, so they are the two that survive.
	 */
	private const SAFE_ACTIONS = array( 'edit', 'view' );

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) || ! function_exists( 'add_action' ) ) {
			return;
		}
		add_filter( 'page_row_actions', array( self::class, 'on_page_row_actions' ), 10, 2 );
		add_filter( 'manage_pages_columns', array( self::class, 'on_manage_pages_columns' ) );
		add_action( 'manage_pages_custom_column', array( self::class, 'on_manage_pages_custom_column' ), 10, 2 );
		add_action( 'wp_trash_post', array( self::class, 'refuse_deletion' ) );
		add_action( 'before_delete_post', array( self::class, 'refuse_deletion' ) );
		add_filter( 'wp_insert_post_data', array( self::class, 'on_insert_post_data' ), 10, 2 );
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
	 * The row actions a page keeps. Pure.
	 *
	 * An allowlist rather than an unset() list: a plugin can add a row action
	 * of its own, and anything we have not thought about should not be offered
	 * on a page the site depends on.
	 *
	 * @param array<string,string> $actions      What WordPress offered.
	 * @param bool                 $is_club_page Whether this row is one of ours.
	 * @return array<string,string>
	 */
	public static function row_actions( array $actions, bool $is_club_page ): array {
		if ( ! $is_club_page ) {
			return $actions;
		}
		return array_intersect_key( $actions, array_flip( self::SAFE_ACTIONS ) );
	}

	/**
	 * The Pages list's columns, with ours added beside the title rather than
	 * after the date, where nobody reads it. Pure.
	 *
	 * @param array<string,string> $columns WordPress's columns.
	 * @return array<string,string>
	 */
	public static function columns( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out[ self::COLUMN ] = 'Club page';
			}
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = 'Club page';
		}
		return $out;
	}

	/** What that column reads on a row. Pure. */
	public static function column_text( bool $is_club_page ): string {
		return $is_club_page ? 'Club page' : '';
	}

	/** Whether a post is one the plugin depends on, and so must not go. Pure-ish. */
	public static function blocks_deletion( int $post_id ): bool {
		return Blueworx_Clubhouse_Club_Pages::is_club_page( $post_id );
	}

	/**
	 * @param mixed $actions Row actions WordPress built.
	 * @param mixed $post    The page the row is for.
	 * @return array<string,string>
	 */
	public static function on_page_row_actions( $actions, $post = null ): array {
		return self::row_actions(
			is_array( $actions ) ? $actions : array(),
			Blueworx_Clubhouse_Club_Pages::is_club_page( self::post_id_of( $post ) )
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

	/**
	 * @param mixed $columns WordPress's columns.
	 * @return array<string,string>
	 */
	public static function on_manage_pages_columns( $columns ): array {
		return self::columns( is_array( $columns ) ? $columns : array() );
	}

	/**
	 * @param mixed $column  Which column is being printed.
	 * @param mixed $post_id The page the row is for.
	 */
	public static function on_manage_pages_custom_column( $column, $post_id = 0 ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}
		echo esc_html( self::column_text( Blueworx_Clubhouse_Club_Pages::is_club_page( self::post_id_of( $post_id ) ) ) );
	}

	/**
	 * Refuses to trash or delete a club page, whoever asked.
	 *
	 * Both hooks fire before WordPress does the deed, so stopping the request
	 * here stops the deletion. Blunt, deliberately: there is no correct way to
	 * carry on once a page the site routes through has gone.
	 *
	 * @param mixed $post_id The page about to go.
	 */
	public static function refuse_deletion( $post_id = 0 ): void {
		if ( ! self::blocks_deletion( self::post_id_of( $post_id ) ) ) {
			return;
		}
		if ( function_exists( 'wp_die' ) ) {
			wp_die(
				esc_html__( 'This is a club page. The site is served from it, so it cannot be deleted. Open it to edit its words instead.', 'blueworx-labs-clubhouse' ),
				esc_html__( 'Club page', 'blueworx-labs-clubhouse' ),
				array( 'response' => 403 )
			);
		}
	}

	/** The post id behind a WP_Post, an id, or anything else. */
	private static function post_id_of( $post ): int {
		if ( is_object( $post ) && isset( $post->ID ) ) {
			return (int) $post->ID;
		}
		return is_numeric( $post ) ? (int) $post : 0;
	}
}
