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
}
