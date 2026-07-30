<?php
// includes/admin/class-owner-role.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress glue for the clubhouse_owner role: registers/removes the role and the
 * administrator cap grant on activation/uninstall, and (in later steps) locks the
 * admin menu and takes over the dashboard for owners. All runtime hooks are gated
 * on is_owner() so a full admin's experience is never touched.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Owner_Role {

	public static function activate(): void {
		remove_role( Blueworx_Clubhouse_Owner_Capabilities::ROLE ); // idempotent: re-add with the current caps.
		add_role(
			Blueworx_Clubhouse_Owner_Capabilities::ROLE,
			Blueworx_Clubhouse_Owner_Capabilities::DISPLAY,
			Blueworx_Clubhouse_Owner_Capabilities::capabilities()
		);
		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( Blueworx_Clubhouse_Owner_Capabilities::admin_cap_grants() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	public static function uninstall(): void {
		remove_role( Blueworx_Clubhouse_Owner_Capabilities::ROLE );
		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( Blueworx_Clubhouse_Owner_Capabilities::admin_cap_grants() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/** True iff the given WP_User-like object carries the owner role. */
	public static function is_owner( $user ): bool {
		return is_object( $user ) && isset( $user->roles ) && is_array( $user->roles )
			&& in_array( Blueworx_Clubhouse_Owner_Capabilities::ROLE, $user->roles, true );
	}

	public static function register(): void {
		add_filter( 'user_has_cap', array( self::class, 'lend_caps' ), 10, 4 );
		add_action( 'admin_menu', array( self::class, 'lock_menu' ), 999 );
		add_action( 'wp_dashboard_setup', array( self::class, 'takeover_dashboard' ), 999 );
		add_action( 'admin_init', array( self::class, 'maybe_upgrade' ) );
	}

	/**
	 * Re-sync the role and admin cap grant when the plugin version changes — covers
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
	 * Hand the owner the caps SureCart and LatePoint gate their own screens on, so
	 * both plugins are fully theirs to run. The integration caps are held outright;
	 * manage_options is only ever lent, on admin screens that are not the core
	 * settings/plugins/themes screens it exists to protect. Nothing is written to
	 * the role itself, so a plugin reading the role map still sees no manage_options.
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
		foreach ( Blueworx_Clubhouse_Owner_Capabilities::integration_caps() as $cap ) {
			$allcaps[ $cap ] = true;
		}
		if ( is_admin() && Blueworx_Clubhouse_Owner_Capabilities::may_lend( self::current_admin_script() ) ) {
			foreach ( Blueworx_Clubhouse_Owner_Capabilities::lent_caps() as $cap ) {
				$allcaps[ $cap ] = true;
			}
		}
		return $allcaps;
	}

	/** The wp-admin script handling this request, e.g. 'options-general.php'. */
	public static function current_admin_script(): string {
		$self = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		return '' !== $self ? $self : basename( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- basename of a server-set path, compared against a fixed list.
	}

	/** Remove every top-level menu the owner is not allowed. Gated on the owner role. */
	public static function lock_menu(): void {
		if ( ! self::is_owner( wp_get_current_user() ) ) {
			return;
		}
		$menu    = isset( $GLOBALS['menu'] ) && is_array( $GLOBALS['menu'] ) ? $GLOBALS['menu'] : array();
		$current = array();
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) ) {
				$current[] = (string) $item[2];
			}
		}
		foreach ( self::removable_menu_slugs( $current, Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist() ) as $slug ) {
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
		wp_add_dashboard_widget( 'clubhouse_setup_dashboard', 'Clubhouse Setup', array( self::class, 'render_dashboard' ) );
	}

	/** Dashboard widget body: the reused Setup screen (its form posts to the Setup page). */
	public static function render_dashboard(): void {
		echo Blueworx_Clubhouse_Setup_Controller::screen_html( new Blueworx_Clubhouse_Options_Storage(), array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Setup_Screen.
	}
}
