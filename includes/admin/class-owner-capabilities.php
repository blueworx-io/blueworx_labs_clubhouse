<?php
// includes/admin/class-owner-capabilities.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for the two Clubhouse roles — ClubHouse - Owner and
 * ClubHouse - Content Editor: how their capability maps are derived, the caps
 * that must never be granted, the users they may and may not manage, the caps
 * administrators also receive, and the admin-menu allowlist each role keeps.
 *
 * Both roles are cloned from the *live* editor role at registration rather than
 * hard-coded, so a site that has adjusted its editor keeps that adjustment. The
 * clone is then filtered: the excluded caps come off, the denied caps come off,
 * and the additions go on. Subtract-after-clone matters — a site that has added
 * manage_options to its editor must not hand it to a Clubhouse role.
 *
 * The two roles differ by exactly one capability. The Owner holds manage_clubhouse
 * and the Content Editor does not, which is what keeps Clubhouse Setup, SureCart
 * and LatePoint out of the editor's reach.
 *
 * Pure — no WordPress. Consumed by Owner_Role and asserted directly.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Owner_Capabilities {

	public const ROLE      = 'clubhouse_owner';
	public const DISPLAY   = 'ClubHouse - Owner';
	public const SETUP_CAP = 'manage_clubhouse';

	/**
	 * The key to Club Pages — the club's own words and images, and the header
	 * menu. Held by BOTH Clubhouse roles.
	 *
	 * A capability of its own rather than manage_clubhouse, which is what Club
	 * Content used to be locked with. That was the one capability separating the
	 * two roles, so gating content editing on it made the Content Editor unable to
	 * edit content — the whole job the role exists for. Splitting the two means
	 * "may configure the site" and "may edit its content" can differ, which is
	 * exactly the distinction the two roles are meant to draw.
	 */
	public const CONTENT_CAP = 'edit_clubhouse_content';

	public const EDITOR_ROLE    = 'clubhouse_content_editor';
	public const EDITOR_DISPLAY = 'ClubHouse - Content Editor';

	/** Top-level admin menus owned by the two integrations the owner runs day to day. */
	public const SURECART_MENU  = 'sc-dashboard';
	public const LATEPOINT_MENU = 'latepoint';

	/** Both Clubhouse role keys, senior first. @return array<int,string> */
	public static function roles(): array {
		return array( self::ROLE, self::EDITOR_ROLE );
	}

	/** The display label for a Clubhouse role key. */
	public static function display( string $role ): string {
		return self::EDITOR_ROLE === $role ? self::EDITOR_DISPLAY : self::DISPLAY;
	}

	/**
	 * WordPress's stock editor map, used only when the live editor role is missing
	 * (deleted, or a role-manager plugin mid-migration). Includes the page and
	 * comment caps the filter then strips, so the fallback and a real clone travel
	 * the same path rather than diverging.
	 *
	 * @return array<string,bool>
	 */
	public static function stock_editor_caps(): array {
		return array(
			'read'                   => true,
			'upload_files'           => true,
			'manage_categories'      => true,
			'manage_links'           => true,
			'unfiltered_html'        => true,
			'moderate_comments'      => true,
			'edit_posts'             => true,
			'edit_others_posts'      => true,
			'edit_published_posts'   => true,
			'edit_private_posts'     => true,
			'publish_posts'          => true,
			'delete_posts'           => true,
			'delete_others_posts'    => true,
			'delete_published_posts' => true,
			'delete_private_posts'   => true,
			'read_private_posts'     => true,
			'edit_pages'             => true,
			'edit_others_pages'      => true,
			'edit_published_pages'   => true,
			'edit_private_pages'     => true,
			'publish_pages'          => true,
			'delete_pages'           => true,
			'delete_others_pages'    => true,
			'delete_published_pages' => true,
			'delete_private_pages'   => true,
			'read_private_pages'     => true,
		);
	}

	/**
	 * Caps stripped off the editor clone. Pages are served by Clubhouse's own
	 * routing and edited under Club Pages, so the raw Pages editor is not a
	 * Clubhouse surface; comments are not a Clubhouse surface either.
	 *
	 * @return array<int,string>
	 */
	public static function excluded_caps(): array {
		return array(
			'edit_pages', 'edit_others_pages', 'edit_published_pages', 'edit_private_pages',
			'publish_pages', 'delete_pages', 'delete_others_pages', 'delete_published_pages',
			'delete_private_pages', 'read_private_pages',
			'moderate_comments',
		);
	}

	/**
	 * Caps added to every Clubhouse role on top of the filtered clone. edit_users
	 * is deliberate, and is only safe because may_manage_user() below refuses every
	 * account that outranks the holder — without that guard it is a way to reset an
	 * administrator's password and take the site.
	 *
	 * CONTENT_CAP is here — in the SHARED additions — rather than beside
	 * SETUP_CAP, because editing the club's content is the one thing both roles
	 * are for. It is what makes the Content Editor able to do its job.
	 *
	 * @return array<int,string>
	 */
	public static function added_caps(): array {
		return array( 'read', 'list_users', 'edit_users', self::CONTENT_CAP );
	}

	/**
	 * The shared capability map: the live editor role, minus the excluded caps,
	 * minus anything denied, plus the additions.
	 *
	 * @param array<string,bool> $editor_caps The live editor role's map.
	 * @return array<string,bool>
	 */
	public static function base_caps( array $editor_caps = array() ): array {
		$source = array();
		foreach ( ( array() === $editor_caps ? self::stock_editor_caps() : $editor_caps ) as $cap => $granted ) {
			if ( $granted ) {
				$source[ (string) $cap ] = true;
			}
		}
		foreach ( array_merge( self::excluded_caps(), self::denied() ) as $cap ) {
			unset( $source[ $cap ] );
		}
		foreach ( self::added_caps() as $cap ) {
			$source[ $cap ] = true;
		}
		return $source;
	}

	/**
	 * The Owner's map: the shared base plus manage_clubhouse, which is the single
	 * capability separating the two roles.
	 *
	 * @param array<string,bool> $editor_caps
	 * @return array<string,bool>
	 */
	public static function capabilities( array $editor_caps = array() ): array {
		$caps                    = self::base_caps( $editor_caps );
		$caps[ self::SETUP_CAP ] = true;
		return $caps;
	}

	/**
	 * The Content Editor's map: the shared base, and nothing else. No
	 * manage_clubhouse, so Clubhouse Setup, SureCart and LatePoint stay shut.
	 *
	 * @param array<string,bool> $editor_caps
	 * @return array<string,bool>
	 */
	public static function editor_capabilities( array $editor_caps = array() ): array {
		return self::base_caps( $editor_caps );
	}

	/**
	 * The capability map for either Clubhouse role key.
	 *
	 * @param array<string,bool> $editor_caps
	 * @return array<string,bool>
	 */
	public static function capabilities_for( string $role, array $editor_caps = array() ): array {
		return self::EDITOR_ROLE === $role
			? self::editor_capabilities( $editor_caps )
			: self::capabilities( $editor_caps );
	}

	/** Capabilities neither Clubhouse role may ever hold. @return array<int,string> */
	public static function denied(): array {
		return array(
			'manage_options', 'edit_theme_options', 'switch_themes', 'activate_plugins',
			'install_plugins', 'install_themes', 'update_core', 'update_plugins', 'update_themes',
			'edit_files', 'edit_plugins', 'edit_themes', 'import', 'export',
			'edit_pages', 'edit_others_pages', 'publish_pages',
			'create_users', 'delete_users', 'remove_users', 'promote_users',
		);
	}

	/**
	 * The roles a Clubhouse role may act on through edit_users. An allowlist rather
	 * than a denylist of administrators: a privileged role added later by another
	 * plugin is refused by default, instead of slipping through because nobody
	 * remembered to add it here.
	 *
	 * @return array<int,string>
	 */
	public static function editable_roles(): array {
		return array( 'subscriber', 'contributor', 'author', 'editor', self::EDITOR_ROLE );
	}

	/**
	 * True when a Clubhouse role may edit an account holding these roles. A user
	 * with no roles carries no power and is editable; anything holding a role
	 * outside the allowlist — administrator, ClubHouse - Owner, any blueworx_*
	 * role — is refused.
	 *
	 * @param array<int,string> $target_roles
	 */
	public static function may_manage_user( array $target_roles ): bool {
		foreach ( $target_roles as $role ) {
			if ( ! in_array( (string) $role, self::editable_roles(), true ) ) {
				return false;
			}
		}
		return true;
	}

	/** Meta caps refused outright for both roles, whatever the target. @return array<int,string> */
	public static function forbidden_meta_caps(): array {
		return array( 'delete_user', 'promote_user', 'remove_user', 'create_users' );
	}

	/** Meta caps allowed only against an editable target. @return array<int,string> */
	public static function guarded_meta_caps(): array {
		return array( 'edit_user' );
	}

	/**
	 * Capabilities SureCart and LatePoint register for their own screens. They only
	 * ever unlock those two plugins, so the Owner holds them outright. This list is
	 * the floor — integration_caps_from() widens it with whatever the live
	 * administrator role actually carries, which is how LatePoint's own caps are
	 * picked up without hard-coding a name that moves between versions.
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
	 * LatePoint does not use capabilities at all. Its Role Manager maps LatePoint
	 * roles onto *named* WordPress roles, and its stock entry names the administrator
	 * role, so an owner is invisible to it however many capabilities they hold.
	 *
	 * Two attempts to satisfy that check from here — lending manage_options, then
	 * presenting the owner under the administrator name — both failed against a real
	 * site, because LatePoint decides earlier or elsewhere than any hook available to
	 * us. The supported route is a custom LatePoint role bound to clubhouse_owner,
	 * created once per site under LatePoint → Settings → Roles.
	 *
	 * LATEPOINT_MENU stays in the owner's menu allowlist: the slug is correct, and
	 * the menu appears the moment that custom role exists.
	 *
	 * @return array<int,string> Name fragments marking a capability as an integration's.
	 */
	public static function integration_cap_patterns(): array {
		return array( '/latepoint/i', '/surecart/i', '/(^|_)sc_/i' );
	}

	/**
	 * Every integration capability the live administrator role holds, merged with
	 * the floor above. Reading them off the administrator is what makes this
	 * survive a plugin renaming its caps: whatever LatePoint grants the admin on
	 * activation, the Owner is given the same. A denied cap is never picked up,
	 * however it happens to be spelled.
	 *
	 * @param array<string,bool> $admin_caps
	 * @return array<int,string>
	 */
	public static function integration_caps_from( array $admin_caps ): array {
		$found = self::integration_caps();
		foreach ( $admin_caps as $cap => $granted ) {
			$cap = (string) $cap;
			if ( ! $granted || in_array( $cap, self::denied(), true ) || in_array( $cap, $found, true ) ) {
				continue;
			}
			foreach ( self::integration_cap_patterns() as $pattern ) {
				if ( 1 === preg_match( $pattern, $cap ) ) {
					$found[] = $cap;
					break;
				}
			}
		}
		return $found;
	}

	/**
	 * Caps lent for the length of an admin request rather than held. SureCart gates
	 * parts of its admin on manage_options, so the Owner cannot reach it without —
	 * but it is never written onto the role, and it is withheld on the core screens
	 * where manage_options *is* the lock (below). Owner only: the Content Editor is
	 * never lent anything.
	 *
	 * @return array<int,string>
	 */
	public static function lent_caps(): array {
		return array( 'manage_options' );
	}

	/**
	 * Core admin screens where the lent caps are refused outright: the screens
	 * manage_options exists to protect, and the Owner is not an administrator. The
	 * user screens are on the list too — manage_options is what WordPress checks
	 * before letting one account edit another's role, and that decision belongs to
	 * may_manage_user(), not to a lent cap.
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
			'users.php', 'user-new.php', 'user-edit.php', 'profile.php',
		);
	}

	/**
	 * True when the lent caps apply to this admin request: any admin screen except
	 * the ones above. The integration screens are the point of the lending, but the
	 * menu is built on every admin request, so the window has to be that wide or
	 * SureCart never registers a menu for the Owner to click.
	 */
	public static function may_lend( string $script ): bool {
		return ! in_array( $script, self::lending_denied_screens(), true );
	}

	/** Caps added to the administrator role on activation (removed on uninstall). @return array<int,string> */
	public static function admin_cap_grants(): array {
		return array( self::SETUP_CAP, self::CONTENT_CAP );
	}

	/**
	 * Top-level admin-menu slugs the Owner keeps, in the order they are read down
	 * the menu; everything else is removed. Profile is kept whatever else changes —
	 * a user who cannot reach their own profile cannot change their own password.
	 *
	 * @return array<int,string>
	 */
	public static function menu_allowlist(): array {
		return array(
			'index.php',              // Dashboard.
			'clubhouse-setup',        // Clubhouse.
			'edit.php?post_type=page', // Pages — where a club page is opened for editing.
			'clubhouse-content',      // Collections.
			self::SURECART_MENU,      // SureCart.
			self::LATEPOINT_MENU,     // LatePoint.
			'edit.php',               // Posts.
			'upload.php',             // Media.
			'users.php',              // Users.
			'profile.php',
		);
	}

	/**
	 * The Content Editor's menu: the Owner's, minus both integrations. Removing
	 * the menus is presentation only — the reason the editor cannot reach
	 * SureCart or LatePoint is that it holds neither manage_clubhouse nor any
	 * integration cap, and is never lent manage_options.
	 *
	 * Clubhouse is on the list because the menu builder moved onto that screen
	 * (issue #144) and arranging the menu is this role's job. It still holds no
	 * manage_clubhouse, so the screen shows it the Menu tab and nothing else.
	 *
	 * @return array<int,string>
	 */
	public static function editor_menu_allowlist(): array {
		return array(
			'index.php',              // Dashboard.
			'clubhouse-setup',        // Clubhouse — the Menu tab only, for this role.
			'edit.php?post_type=page', // Pages — where a club page is opened for editing.
			'clubhouse-content',      // Collections.
			'edit.php',               // Posts.
			'upload.php',             // Media.
			'users.php',              // Users.
			'profile.php',
		);
	}

	/** The menu allowlist for either Clubhouse role key. @return array<int,string> */
	public static function menu_allowlist_for( string $role ): array {
		return self::EDITOR_ROLE === $role ? self::editor_menu_allowlist() : self::menu_allowlist();
	}
}
