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
}
