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
}
