<?php
// includes/admin/class-owner-capabilities.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for the clubhouse_owner role: the exact capability map,
 * the capabilities that must never be granted (asserted in tests + used nowhere
 * else), the caps administrators also receive, and the admin-menu allowlist the
 * owner keeps. Pure — no WordPress. Consumed by Owner_Role and asserted directly.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Owner_Capabilities {

	public const ROLE      = 'clubhouse_owner';
	public const DISPLAY   = 'Clubhouse Owner';
	public const SETUP_CAP = 'manage_clubhouse';

	/**
	 * The exact capability map for the role. The post caps cover both the six
	 * collection CPTs (default 'post' capability type) and the native blog.
	 *
	 * @return array<string,bool>
	 */
	public static function capabilities(): array {
		return array(
			'read'                   => true,
			self::SETUP_CAP          => true,
			'upload_files'           => true,
			'list_users'             => true,
			'moderate_comments'      => true,
			'edit_posts'             => true,
			'edit_others_posts'      => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'delete_posts'           => true,
			'delete_others_posts'    => true,
			'delete_published_posts' => true,
			'read_private_posts'     => true,
		);
	}

	/** Capabilities the owner must never hold. @return array<int,string> */
	public static function denied(): array {
		return array(
			'manage_options', 'edit_theme_options', 'switch_themes', 'activate_plugins',
			'install_plugins', 'install_themes', 'update_core', 'update_plugins', 'update_themes',
			'edit_pages', 'edit_others_pages', 'publish_pages',
			'create_users', 'edit_users', 'delete_users', 'promote_users',
		);
	}

	/** Caps added to the administrator role on activation (removed on uninstall). @return array<int,string> */
	public static function admin_cap_grants(): array {
		return array( self::SETUP_CAP );
	}

	/** Top-level admin-menu slugs the owner keeps; everything else is removed. @return array<int,string> */
	public static function menu_allowlist(): array {
		return array( 'index.php', 'clubhouse-content', 'clubhouse-site-content', 'upload.php', 'edit.php', 'edit-comments.php', 'users.php', 'profile.php' );
	}
}
