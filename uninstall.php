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

Blueworx_Clubhouse_Owner_Role::uninstall();
