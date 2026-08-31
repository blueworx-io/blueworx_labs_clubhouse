<?php
// includes/admin/class-owner-role.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress glue for the two Clubhouse roles — ClubHouse - Owner and ClubHouse -
 * Content Editor: registers and removes them and the administrator cap grant on
 * activation/uninstall, locks the admin menu to each role's allowlist, takes over
 * the dashboard for owners, lends the Owner the caps the two integrations gate on,
 * and refuses every attempt by either role to act on an account that outranks it.
 *
 * All runtime hooks are gated on the role, so a full admin's experience is never
 * touched.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Owner_Role {

	public static function activate(): void {
		$editor_caps = self::role_caps( 'editor' );
		$admin_caps  = self::role_caps( 'administrator' );

		foreach ( Blueworx_Clubhouse_Owner_Capabilities::roles() as $role ) {
			remove_role( $role ); // Idempotent: re-add with the current caps.
			add_role(
				$role,
				Blueworx_Clubhouse_Owner_Capabilities::display( $role ),
				Blueworx_Clubhouse_Owner_Capabilities::capabilities_for( $role, $editor_caps )
			);
		}

		// The Owner alone carries the integration caps, read off the live
		// administrator so each plugin's own naming is followed rather than guessed.
		$owner = get_role( Blueworx_Clubhouse_Owner_Capabilities::ROLE );
		if ( null !== $owner ) {
			foreach ( Blueworx_Clubhouse_Owner_Capabilities::integration_caps_from( $admin_caps ) as $cap ) {
				$owner->add_cap( $cap );
			}
		}

		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( Blueworx_Clubhouse_Owner_Capabilities::admin_cap_grants() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	public static function uninstall(): void {
		foreach ( Blueworx_Clubhouse_Owner_Capabilities::roles() as $role ) {
			remove_role( $role );
		}
		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( Blueworx_Clubhouse_Owner_Capabilities::admin_cap_grants() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/**
	 * The live capability map of a WordPress role, or an empty array when the role
	 * is missing — which sends the catalogue to its stock fallback.
	 *
	 * @return array<string,bool>
	 */
	public static function role_caps( string $role ): array {
		$found = get_role( $role );
		if ( null === $found || ! isset( $found->capabilities ) || ! is_array( $found->capabilities ) ) {
			return array();
		}
		return $found->capabilities;
	}

	/** True iff the given WP_User-like object carries the given role. */
	public static function has_role( $user, string $role ): bool {
		return is_object( $user ) && isset( $user->roles ) && is_array( $user->roles )
			&& in_array( $role, $user->roles, true );
	}

	/** True iff the given WP_User-like object is a ClubHouse - Owner. */
	public static function is_owner( $user ): bool {
		return self::has_role( $user, Blueworx_Clubhouse_Owner_Capabilities::ROLE );
	}

	/** True iff the given WP_User-like object is a ClubHouse - Content Editor. */
	public static function is_content_editor( $user ): bool {
		return self::has_role( $user, Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE );
	}

	/** The Clubhouse role this user carries, or '' for everybody else. */
	public static function role_of( $user ): string {
		foreach ( Blueworx_Clubhouse_Owner_Capabilities::roles() as $role ) {
			if ( self::has_role( $user, $role ) ) {
				return $role;
			}
		}
		return '';
	}

	public static function register(): void {
		add_filter( 'user_has_cap', array( self::class, 'lend_caps' ), 10, 4 );
		add_filter( 'map_meta_cap', array( self::class, 'guard_user_management' ), 10, 4 );
		add_filter( 'editable_roles', array( self::class, 'limit_editable_roles' ) );
		add_action( 'admin_menu', array( self::class, 'lock_menu' ), 999 );
		add_action( 'wp_dashboard_setup', array( self::class, 'takeover_dashboard' ), 999 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'dress_dashboard' ) );
		add_action( 'admin_init', array( self::class, 'maybe_upgrade' ) );
	}

	/**
	 * Re-sync the roles and admin cap grant when the plugin version changes — covers
	 * in-place updates, where the activation hook does not run. Idempotent + cheap
	 * (one option read per admin request; a write only when the version changes).
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( 'clubhouse_role_version', '' );
		$current   = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : 'dev';
		if ( $installed !== $current ) {
			self::activate();
			update_option( 'clubhouse_role_version', $current );
		}
	}

	/**
	 * Lend the Owner the cap SureCart gates parts of its admin on, so the shop is
	 * fully theirs to run. The integration caps themselves are held on the role;
	 * manage_options is only ever lent, on admin screens that are not the core
	 * settings/plugins/themes/users screens it exists to protect. Nothing is written
	 * to the role here, so a plugin reading the role map still sees no manage_options.
	 *
	 * The Content Editor is never lent anything — this returns untouched for every
	 * user but an owner.
	 *
	 * @param array<string,bool> $allcaps
	 * @param array<int,string>  $caps
	 * @param array<int,mixed>   $args
	 * @param mixed              $user
	 * @return array<string,bool>
	 */
	public static function lend_caps( $allcaps, $caps, $args, $user ) {
		if ( ! is_array( $allcaps ) || ! self::is_owner( $user ) ) {
			return $allcaps;
		}
		if ( is_admin() && Blueworx_Clubhouse_Owner_Capabilities::may_lend( self::current_admin_script() ) ) {
			foreach ( Blueworx_Clubhouse_Owner_Capabilities::lent_caps() as $cap ) {
				$allcaps[ $cap ] = true;
			}
		}
		return $allcaps;
	}

	/**
	 * The hard stop on user management. Both Clubhouse roles hold edit_users so they
	 * can maintain members' accounts, which without this would also let them reset an
	 * administrator's password and take the site. Creating, deleting, removing and
	 * promoting are refused outright; editing is refused unless every role on the
	 * target is one this role outranks. Editing yourself is always allowed, or a user
	 * could not change their own password.
	 *
	 * Denying at map_meta_cap means the refusal holds however the check is reached —
	 * a screen, a REST route, or another plugin calling current_user_can().
	 *
	 * @param array<int,string> $caps    The primitive caps required.
	 * @param string            $cap     The meta cap being mapped.
	 * @param int               $user_id The acting user.
	 * @param array<int,mixed>  $args    $args[0] is the target user id.
	 * @return array<int,string>
	 */
	public static function guard_user_management( $caps, $cap, $user_id, $args ) {
		if ( ! is_array( $caps ) ) {
			return $caps;
		}
		$actor = self::user_by_id( (int) $user_id );
		if ( '' === self::role_of( $actor ) ) {
			return $caps;
		}
		if ( in_array( (string) $cap, Blueworx_Clubhouse_Owner_Capabilities::forbidden_meta_caps(), true ) ) {
			return array( 'do_not_allow' );
		}
		if ( ! in_array( (string) $cap, Blueworx_Clubhouse_Owner_Capabilities::guarded_meta_caps(), true ) ) {
			return $caps;
		}
		$target_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( 0 === $target_id || $target_id === (int) $user_id ) {
			return $caps; // Your own profile.
		}
		$target = self::user_by_id( $target_id );
		$roles  = is_object( $target ) && isset( $target->roles ) && is_array( $target->roles ) ? $target->roles : array();
		return Blueworx_Clubhouse_Owner_Capabilities::may_manage_user( $roles ) ? $caps : array( 'do_not_allow' );
	}

	/**
	 * Narrow the role dropdown to the roles this Clubhouse role outranks. Neither
	 * role can promote anybody — promote_user is refused above — but a screen that
	 * offers administrator invites a support request about a control that cannot
	 * work, and other plugins read this filter to decide what to show.
	 *
	 * @param array<string,mixed> $roles
	 * @return array<string,mixed>
	 */
	public static function limit_editable_roles( $roles ) {
		if ( ! is_array( $roles ) || '' === self::role_of( wp_get_current_user() ) ) {
			return $roles;
		}
		$allowed = Blueworx_Clubhouse_Owner_Capabilities::editable_roles();
		foreach ( array_keys( $roles ) as $key ) {
			if ( ! in_array( (string) $key, $allowed, true ) ) {
				unset( $roles[ $key ] );
			}
		}
		return $roles;
	}

	/** A WP_User-like object for an id, or null. Wrapped so tests can stand in one user store. */
	public static function user_by_id( int $id ) {
		if ( $id <= 0 || ! function_exists( 'get_userdata' ) ) {
			return null;
		}
		$user = get_userdata( $id );
		return false === $user ? null : $user;
	}

	/** The wp-admin script handling this request, e.g. 'options-general.php'. */
	public static function current_admin_script(): string {
		$self = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		return '' !== $self ? $self : basename( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- basename of a server-set path, compared against a fixed list.
	}

	/** Remove every top-level menu this role is not allowed. Gated on the Clubhouse roles. */
	public static function lock_menu(): void {
		$role = self::role_of( wp_get_current_user() );
		if ( '' === $role ) {
			return;
		}
		$menu    = isset( $GLOBALS['menu'] ) && is_array( $GLOBALS['menu'] ) ? $GLOBALS['menu'] : array();
		$current = array();
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) ) {
				$current[] = (string) $item[2];
			}
		}
		$allowed = Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist_for( $role );
		foreach ( self::removable_menu_slugs( $current, $allowed ) as $slug ) {
			remove_menu_page( $slug );
		}
	}

	/**
	 * @param array<int,string> $current
	 * @param array<int,string> $allowlist
	 * @return array<int,string>
	 */
	public static function removable_menu_slugs( array $current, array $allowlist ): array {
		return array_values( array_diff( $current, $allowlist ) );
	}

	/** For owners only: clear the default dashboard widgets and mount the Setup screen. */
	public static function takeover_dashboard(): void {
		if ( ! self::is_owner( wp_get_current_user() ) ) {
			return;
		}
		$GLOBALS['wp_meta_boxes']['dashboard'] = array();
		// Titled for what it is rather than what it was: this stopped being the
		// Setup form in v0.100.0. The id stays — it is what the browser tests
		// find the widget by, and renaming it would only lose an owner's
		// collapsed/expanded state for nothing.
		wp_add_dashboard_widget( 'clubhouse_setup_dashboard', 'Clubhouse', array( self::class, 'render_dashboard' ) );
	}

	/**
	 * The welcome widget is written in the design system's own markup, so the
	 * dashboard has to carry the design system for it to be anything but bare
	 * text — which is what it was until issue #307. As a guest, though: the
	 * full-bleed chrome overrides belong to a screen that is entirely ours,
	 * and on the dashboard they would move WordPress's own widgets.
	 *
	 * @param mixed $hook The screen WordPress is about to draw.
	 */
	public static function dress_dashboard( $hook = '' ): void {
		if ( 'index.php' !== (string) $hook || ! self::is_owner( wp_get_current_user() ) ) {
			return;
		}
		Blueworx_Clubhouse_Admin_Assets::enqueue_as_a_guest();
	}

	/** Dashboard widget body: where an owner starts. Setup itself is a page editor screen and mounts on its own page. */
	public static function render_dashboard(): void {
		echo Blueworx_Clubhouse_Owner_Welcome::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Owner_Welcome.
	}
}
