<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The six collection editors, declared to the page editor library, and the
 * routing that sends each list's Edit to them.
 *
 * A collection is reached from its own WordPress list — the place somebody
 * looking for a fixture looks — exactly as a club page is reached from the
 * Pages list. The screens are registered under the Clubhouse menu so that menu
 * stays highlighted while one is open, and their items are hidden straight
 * afterwards; a second list beside WordPress's own is how an owner ends up on
 * the wrong one.
 *
 * The block editor stays shut for these types and a typed post.php redirects,
 * for the same reason as a club page: the record's body is not rendered
 * anywhere, so anything written there would silently never appear.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Editors {

	/** True when this post type is one of the six. */
	public static function is_collection( string $type ): bool {
		return in_array( $type, Blueworx_Clubhouse_Collection_Meta::types(), true );
	}

	/** The editor screen for a record, carrying the record it edits. */
	public static function editor_url( string $type, int $post_id ): string {
		$url = 'admin.php?page=' . Blueworx_Clubhouse_Collection_Fields::slug_for( $type ) . '&id=' . $post_id;
		return function_exists( 'admin_url' ) ? (string) admin_url( $url ) : $url;
	}

	/**
	 * Declared on init at priority 20, for the same reason the club pages are:
	 * these screens name the Clubhouse menu as their parent, and WordPress only
	 * resolves a submenu item's own hook name once its parent exists.
	 */
	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'init', array( self::class, 'declare_screens' ), 20 );
		add_action( 'admin_head', array( self::class, 'hide_menu_items' ) );
		add_action( 'admin_init', array( self::class, 'maybe_migrate' ) );
		add_filter( 'use_block_editor_for_post', array( self::class, 'on_use_block_editor_for_post' ), 10, 2 );
		add_filter( 'get_edit_post_link', array( self::class, 'on_get_edit_post_link' ), 10, 2 );
		add_action( 'load-post.php', array( self::class, 'redirect_to_editor' ) );
		add_action( 'load-post-new.php', array( self::class, 'redirect_new_to_editor' ) );
	}

	/**
	 * Move a club's collection fields to their new addresses, once.
	 *
	 * One option read per admin request while it has already run, which is the
	 * same price Owner_Role::maybe_upgrade() pays and for the same reason: an
	 * in-place plugin update never fires the activation hook, so a club that
	 * updates rather than reinstalls has to be caught here or not at all.
	 */
	public static function maybe_migrate(): void {
		$storage = new Blueworx_Clubhouse_Options_Storage();
		if ( Blueworx_Clubhouse_Collection_Migration::has_run( $storage ) ) {
			return;
		}
		Blueworx_Clubhouse_Collection_Migration::run( $storage );
	}

	public static function declare_screens(): void {
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			\Blueworx\PageEditor\v1\Editor::register( Blueworx_Clubhouse_Collection_Fields::screen( $type ) );
		}
	}

	/**
	 * Hides the six menu items. On admin_head rather than admin_menu, for the
	 * reason Page_Editors::hide_record_editors() documents at length: removing
	 * the entry any earlier erases what WordPress uses to resolve the page's
	 * own hook name, and a direct visit then lands on "Sorry, you are not
	 * allowed to access this page."
	 */
	public static function hide_menu_items(): void {
		if ( ! function_exists( 'remove_submenu_page' ) ) {
			return;
		}
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			remove_submenu_page(
				Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG,
				Blueworx_Clubhouse_Collection_Fields::slug_for( $type )
			);
		}
	}

	/** Whether a post should open in the block editor. Pure. */
	public static function wants_block_editor( bool $default, string $type ): bool {
		return self::is_collection( $type ) ? false : $default;
	}

	/** A collection's edit link points at its own screen; every other link stands. */
	public static function filter_edit_link( string $link, string $type, int $post_id ): string {
		return self::is_collection( $type ) ? self::editor_url( $type, $post_id ) : $link;
	}

	/**
	 * @param mixed $default Whatever WordPress decided.
	 * @param mixed $post    WP_Post or post id.
	 */
	public static function on_use_block_editor_for_post( $default, $post = null ): bool {
		return self::wants_block_editor( (bool) $default, self::type_of( $post ) );
	}

	/**
	 * @param mixed $link    The edit link WordPress built.
	 * @param mixed $post_id The post it is for.
	 */
	public static function on_get_edit_post_link( $link, $post_id = 0 ): string {
		$id = self::post_id_of( $post_id );
		return self::filter_edit_link( (string) $link, self::type_of( $id ), $id );
	}

	/**
	 * Typing post.php?post=<id>&action=edit for a collection lands on its own
	 * editor too — otherwise switching the block editor off would only hide the
	 * door rather than close it, and the classic editor would open on a record
	 * whose fields are not there.
	 */
	public static function redirect_to_editor(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which post is being opened, not acting on a request.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		$type    = self::type_of( $post_id );
		if ( ! self::is_collection( $type ) ) {
			return;
		}
		wp_safe_redirect( self::editor_url( $type, $post_id ) );
		exit;
	}

	/**
	 * Add New goes to the same editor, on a record that does not exist yet —
	 * the library creates it on the first save, which is what keeps a list free
	 * of empty drafts somebody opened and thought better of.
	 */
	public static function redirect_new_to_editor(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which type is being created, not acting on a request.
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( ! self::is_collection( $type ) ) {
			return;
		}
		wp_safe_redirect( self::editor_url( $type, 0 ) );
		exit;
	}

	/** The post type of a WP_Post, an id, or anything else. */
	private static function type_of( $post ): string {
		if ( is_object( $post ) && isset( $post->post_type ) ) {
			return (string) $post->post_type;
		}
		$id = self::post_id_of( $post );
		if ( $id <= 0 || ! function_exists( 'get_post_type' ) ) {
			return '';
		}
		$type = get_post_type( $id );
		return is_string( $type ) ? $type : '';
	}

	private static function post_id_of( $post ): int {
		if ( is_object( $post ) && isset( $post->ID ) ) {
			return (int) $post->ID;
		}
		return is_numeric( $post ) ? (int) $post : 0;
	}
}
