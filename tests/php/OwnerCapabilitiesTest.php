<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OwnerCapabilitiesTest extends TestCase {

	public function test_role_and_cap_names(): void {
		$this->assertSame( 'clubhouse_owner', Blueworx_Clubhouse_Owner_Capabilities::ROLE );
		$this->assertSame( 'Clubhouse Owner', Blueworx_Clubhouse_Owner_Capabilities::DISPLAY );
		$this->assertSame( 'manage_clubhouse', Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP );
	}

	public function test_grants_the_essential_capabilities(): void {
		$caps = Blueworx_Clubhouse_Owner_Capabilities::capabilities();
		foreach ( array( 'read', 'manage_clubhouse', 'upload_files', 'list_users', 'moderate_comments', 'edit_posts', 'edit_others_posts', 'publish_posts', 'delete_posts', 'read_private_posts' ) as $cap ) {
			$this->assertArrayHasKey( $cap, $caps );
			$this->assertTrue( $caps[ $cap ] );
		}
	}

	public function test_never_grants_a_denied_capability(): void {
		$caps = Blueworx_Clubhouse_Owner_Capabilities::capabilities();
		foreach ( Blueworx_Clubhouse_Owner_Capabilities::denied() as $cap ) {
			$this->assertArrayNotHasKey( $cap, $caps, "owner must not be granted {$cap}" );
		}
	}

	public function test_denied_list_covers_the_dangerous_caps(): void {
		$denied = Blueworx_Clubhouse_Owner_Capabilities::denied();
		foreach ( array( 'manage_options', 'activate_plugins', 'edit_theme_options', 'install_plugins', 'update_core', 'edit_pages', 'create_users', 'promote_users', 'delete_users' ) as $cap ) {
			$this->assertContains( $cap, $denied );
		}
	}

	public function test_admin_cap_grants_include_the_setup_cap(): void {
		$this->assertContains( 'manage_clubhouse', Blueworx_Clubhouse_Owner_Capabilities::admin_cap_grants() );
	}

	public function test_menu_allowlist_is_exactly_the_owner_surfaces(): void {
		$this->assertSame(
			array( 'index.php', 'clubhouse-content', 'clubhouse-site-content', 'upload.php', 'edit.php', 'edit-comments.php', 'users.php', 'profile.php' ),
			Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist()
		);
	}
}
