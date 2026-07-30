<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OwnerRoleTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_activate_registers_the_role_with_the_capability_map(): void {
		Blueworx_Clubhouse_Owner_Role::activate();
		$this->assertArrayHasKey( 'clubhouse_owner', $GLOBALS['wp_stub_roles'] );
		$caps = $GLOBALS['wp_stub_roles']['clubhouse_owner']['caps'];
		$this->assertTrue( $caps['manage_clubhouse'] );
		$this->assertTrue( $caps['edit_posts'] );
		$this->assertArrayNotHasKey( 'manage_options', $caps );
	}

	public function test_activate_grants_the_setup_cap_to_administrator(): void {
		Blueworx_Clubhouse_Owner_Role::activate();
		$this->assertTrue( $GLOBALS['wp_stub_roles']['administrator']['caps']['manage_clubhouse'] );
	}

	public function test_uninstall_removes_the_role_and_the_admin_grant(): void {
		Blueworx_Clubhouse_Owner_Role::activate();
		Blueworx_Clubhouse_Owner_Role::uninstall();
		$this->assertArrayNotHasKey( 'clubhouse_owner', $GLOBALS['wp_stub_roles'] );
		$this->assertArrayNotHasKey( 'manage_clubhouse', $GLOBALS['wp_stub_roles']['administrator']['caps'] );
	}

	public function test_is_owner_true_only_for_a_user_with_the_role(): void {
		$owner = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		$admin = (object) array( 'roles' => array( 'administrator' ) );
		$this->assertTrue( Blueworx_Clubhouse_Owner_Role::is_owner( $owner ) );
		$this->assertFalse( Blueworx_Clubhouse_Owner_Role::is_owner( $admin ) );
		$this->assertFalse( Blueworx_Clubhouse_Owner_Role::is_owner( null ) );
	}

	public function test_removable_menu_slugs_is_current_minus_allowlist(): void {
		$this->assertSame(
			array( 'themes.php', 'plugins.php', 'tools.php' ),
			Blueworx_Clubhouse_Owner_Role::removable_menu_slugs(
				array( 'index.php', 'themes.php', 'plugins.php', 'tools.php', 'upload.php' ),
				Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist()
			)
		);
	}

	public function test_lock_menu_removes_disallowed_only_for_owners(): void {
		$GLOBALS['menu'] = array(
			array( '', 'read', 'index.php' ),
			array( '', 'edit_theme_options', 'themes.php' ),
			array( '', 'activate_plugins', 'plugins.php' ),
			array( '', 'upload_files', 'upload.php' ),
		);
		// Not an owner → no removals.
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'administrator' ) );
		Blueworx_Clubhouse_Owner_Role::lock_menu();
		$this->assertSame( array(), wp_stub_calls( 'remove_menu_page' ) );

		// Owner → themes.php + plugins.php removed, index.php + upload.php kept.
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		Blueworx_Clubhouse_Owner_Role::lock_menu();
		$removed = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'remove_menu_page' ) );
		$this->assertContains( 'themes.php', $removed );
		$this->assertContains( 'plugins.php', $removed );
		$this->assertNotContains( 'index.php', $removed );
		$this->assertNotContains( 'upload.php', $removed );
	}

	/**
	 * WordPress's own Pages menu is no longer hidden from everyone — administrators
	 * need it to reach pages other plugins own (SureCart's customer dashboard, and
	 * the like), which the Clubhouse routing never served and never will.
	 *
	 * Owners are a different matter: they stay inside the Clubhouse screens, and
	 * the allowlist is the single thing keeping them there. This pins both halves,
	 * because the two are now enforced by one mechanism instead of two.
	 */
	public function test_pages_menu_is_hidden_from_owners_but_left_for_administrators(): void {
		$GLOBALS['menu'] = array(
			array( '', 'read', 'index.php' ),
			array( '', 'edit_pages', 'edit.php?post_type=page' ),
		);

		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'administrator' ) );
		Blueworx_Clubhouse_Owner_Role::lock_menu();
		$this->assertSame( array(), wp_stub_calls( 'remove_menu_page' ), 'administrators keep the Pages menu' );

		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		Blueworx_Clubhouse_Owner_Role::lock_menu();
		$removed = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'remove_menu_page' ) );
		$this->assertContains( 'edit.php?post_type=page', $removed, 'owners still do not get raw page editing' );
	}

	public function test_takeover_dashboard_replaces_widgets_only_for_owners(): void {
		$GLOBALS['wp_meta_boxes'] = array( 'dashboard' => array( 'normal' => array( 'core' => array( 'dashboard_activity' => array() ) ) ) );

		// Admin → untouched.
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'administrator' ) );
		Blueworx_Clubhouse_Owner_Role::takeover_dashboard();
		$this->assertSame( array(), wp_stub_calls( 'wp_add_dashboard_widget' ) );
		$this->assertNotSame( array(), $GLOBALS['wp_meta_boxes']['dashboard'] );

		// Owner → default widgets cleared + our Setup widget added.
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		Blueworx_Clubhouse_Owner_Role::takeover_dashboard();
		$this->assertSame( array(), $GLOBALS['wp_meta_boxes']['dashboard'] );
		$added = wp_stub_calls( 'wp_add_dashboard_widget' );
		$this->assertSame( 'clubhouse_setup_dashboard', $added[0]['args'][0] );
	}

	/**
	 * The two integrations are the owner's to run, so their own caps are handed over
	 * outright — on the front end as well, since SureCart reads caps outside wp-admin.
	 */
	public function test_lend_caps_hands_owners_the_integration_caps(): void {
		$owner = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		$caps  = Blueworx_Clubhouse_Owner_Role::lend_caps( array( 'read' => true ), array(), array(), $owner );
		$this->assertTrue( $caps['manage_sc_shop_settings'] );
		$this->assertTrue( $caps['manage_latepoint'] );
		$this->assertArrayNotHasKey( 'manage_options', $caps, 'not lent outside wp-admin' );
	}

	public function test_lend_caps_leaves_everyone_else_alone(): void {
		$admin = (object) array( 'roles' => array( 'administrator' ) );
		$this->assertSame( array( 'read' => true ), Blueworx_Clubhouse_Owner_Role::lend_caps( array( 'read' => true ), array(), array(), $admin ) );
		$this->assertSame( array( 'read' => true ), Blueworx_Clubhouse_Owner_Role::lend_caps( array( 'read' => true ), array(), array(), null ) );
	}

	public function test_manage_options_is_lent_on_an_integration_screen(): void {
		$GLOBALS['wp_stub_is_admin'] = true;
		$GLOBALS['pagenow']          = 'admin.php';
		$owner                       = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		$caps                        = Blueworx_Clubhouse_Owner_Role::lend_caps( array( 'read' => true ), array(), array(), $owner );
		$this->assertTrue( $caps['manage_options'] );
		unset( $GLOBALS['pagenow'] );
	}

	public function test_manage_options_is_refused_on_the_core_settings_screens(): void {
		$GLOBALS['wp_stub_is_admin'] = true;
		$owner                       = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		foreach ( array( 'options-general.php', 'plugins.php', 'themes.php', 'update-core.php' ) as $screen ) {
			$GLOBALS['pagenow'] = $screen;
			$caps               = Blueworx_Clubhouse_Owner_Role::lend_caps( array( 'read' => true ), array(), array(), $owner );
			$this->assertArrayNotHasKey( 'manage_options', $caps, "manage_options must not be lent on {$screen}" );
		}
		unset( $GLOBALS['pagenow'] );
	}

	public function test_lend_caps_never_grants_the_user_management_caps(): void {
		$GLOBALS['wp_stub_is_admin'] = true;
		$GLOBALS['pagenow']          = 'admin.php';
		$owner                       = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		$caps                        = Blueworx_Clubhouse_Owner_Role::lend_caps( array( 'read' => true ), array(), array(), $owner );
		foreach ( array( 'edit_users', 'promote_users', 'delete_users', 'create_users', 'activate_plugins', 'edit_theme_options', 'edit_pages' ) as $cap ) {
			$this->assertArrayNotHasKey( $cap, $caps, "{$cap} must never be lent" );
		}
		unset( $GLOBALS['pagenow'] );
	}

	public function test_maybe_upgrade_resyncs_when_version_differs(): void {
		update_option( 'clubhouse_role_version', '0.0.0' );
		Blueworx_Clubhouse_Owner_Role::maybe_upgrade();
		$this->assertArrayHasKey( 'clubhouse_owner', $GLOBALS['wp_stub_roles'] );
		$this->assertTrue( $GLOBALS['wp_stub_roles']['administrator']['caps']['manage_clubhouse'] );
	}

	public function test_maybe_upgrade_is_noop_when_version_matches(): void {
		$current = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : 'dev';
		update_option( 'clubhouse_role_version', $current );
		wp_stub_reset(); // clears recorded calls but keeps option? re-set below to be explicit
		update_option( 'clubhouse_role_version', $current );
		Blueworx_Clubhouse_Owner_Role::maybe_upgrade();
		$this->assertSame( array(), wp_stub_calls( 'add_role' ) );
	}
}
