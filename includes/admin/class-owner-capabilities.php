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

	/** Top-level admin menus owned by the two integrations the owner runs day to day. */
	public const SURECART_MENU  = 'sc-dashboard';
	public const LATEPOINT_MENU = 'latepoint';

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

	/**
	 * Capabilities SureCart and LatePoint register for their own screens. They only
	 * ever unlock those two plugins, so the owner holds them outright — this is what
	 * "the owner runs the shop and the diary" means in capability terms.
	 *
	 * @return array<int,string>
	 */
	public static function integration_caps(): array {
		return array(
			// SureCart.
			'edit_sc_products', 'edit_others_sc_products', 'publish_sc_products',
			'delete_sc_products', 'read_private_sc_products',
			'manage_sc_shop_settings', 'view_sc_shop_reports',
			// LatePoint.
			'manage_latepoint', 'edit_latepoint_bookings',
		);
	}

	/**
	 * Caps that are lent for the length of an admin request rather than held. Both
	 * plugins gate parts of their own admin on manage_options, so an owner cannot
	 * reach them without it — but it is never written onto the role, and it is
	 * withheld on the core screens where manage_options *is* the lock (below).
	 *
	 * @return array<int,string>
	 */
	public static function lent_caps(): array {
		return array( 'manage_options' );
	}

	/**
	 * Core admin screens where the lent caps are refused outright: these are the
	 * screens manage_options exists to protect, and the owner is not an administrator.
	 *
	 * @return array<int,string>
	 */
	public static function lending_denied_screens(): array {
		return array(
			'options.php', 'options-general.php', 'options-writing.php', 'options-reading.php',
			'options-discussion.php', 'options-media.php', 'options-permalink.php', 'options-privacy.php',
			'plugins.php', 'plugin-install.php', 'plugin-editor.php',
			'themes.php', 'theme-install.php', 'theme-editor.php', 'customize.php',
			'update-core.php', 'update.php', 'tools.php', 'import.php', 'export.php',
			'site-health.php', 'export-personal-data.php', 'erase-personal-data.php',
		);
	}

	/**
	 * True when the lent caps apply to this admin request: any admin screen except
	 * the ones above. The integration screens are the point of the lending, but the
	 * menu is built on every admin request, so the window has to be that wide or
	 * SureCart and LatePoint never register a menu for the owner to click.
	 */
	public static function may_lend( string $script ): bool {
		return ! in_array( $script, self::lending_denied_screens(), true );
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

	/**
	 * Top-level admin-menu slugs the owner keeps, in the order they are read down
	 * the menu; everything else is removed. Profile is kept whatever else changes —
	 * a user who cannot reach their own profile cannot change their own password.
	 *
	 * @return array<int,string>
	 */
	public static function menu_allowlist(): array {
		return array(
			'index.php',              // Dashboard.
			'clubhouse-setup',        // Clubhouse.
			'clubhouse-site-content', // Club Content.
			'clubhouse-content',      // Collections.
			self::SURECART_MENU,      // SureCart.
			self::LATEPOINT_MENU,     // LatePoint.
			'edit.php',               // Posts.
			'upload.php',             // Media.
			'users.php',              // Users.
			'profile.php',
		);
	}
}
