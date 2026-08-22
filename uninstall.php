<?php
/**
 * Uninstall handler — removes the clubhouse_owner role and the administrator cap
 * grant. Runs only on plugin delete (kept on deactivate, per the design).
 *
 * @package BlueworxLabsClubhouse
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin/class-owner-role.php';
require_once __DIR__ . '/includes/frontend/class-frontend.php';

Blueworx_Clubhouse_Owner_Role::uninstall();
// The upgrade stamp is ours; a reinstall must run the upgrade again rather
// than find a stale stamp claiming this site is already current.
delete_option( Blueworx_Clubhouse_Frontend::UPGRADE_VERSION_OPTION );
// Left by the withdrawn block builder. Harmless, but ours to clear up.
delete_option( 'clubhouse_blocks' );
delete_option( 'clubhouse_page_composition' );
