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
		foreach ( array( 'read', 'manage_clubhouse', 'upload_files', 'list_users', 'edit_posts', 'edit_others_posts', 'publish_posts', 'delete_posts', 'read_private_posts' ) as $cap ) {
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
			array(
				'index.php',
				'clubhouse-setup',
				'clubhouse-site-content',
				'clubhouse-content',
				'sc-dashboard',
				'latepoint',
				'edit.php',
				'upload.php',
				'users.php',
				'profile.php',
			),
			Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist()
		);
	}

	public function test_comments_are_no_longer_an_owner_surface(): void {
		$this->assertNotContains( 'edit-comments.php', Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist() );
		$this->assertArrayNotHasKey( 'moderate_comments', Blueworx_Clubhouse_Owner_Capabilities::capabilities() );
	}

	public function test_integration_caps_are_plugin_scoped_only(): void {
		$caps = Blueworx_Clubhouse_Owner_Capabilities::integration_caps();
		$this->assertContains( 'manage_sc_shop_settings', $caps );
		$this->assertContains( 'manage_latepoint', $caps );
		foreach ( $caps as $cap ) {
			$this->assertNotContains( $cap, Blueworx_Clubhouse_Owner_Capabilities::denied(), "{$cap} must not be a denied cap" );
		}
	}

	public function test_manage_options_is_lent_never_held(): void {
		$this->assertContains( 'manage_options', Blueworx_Clubhouse_Owner_Capabilities::lent_caps() );
		$this->assertContains( 'manage_options', Blueworx_Clubhouse_Owner_Capabilities::denied() );
		$this->assertArrayNotHasKey( 'manage_options', Blueworx_Clubhouse_Owner_Capabilities::capabilities() );
	}

	public function test_lending_is_refused_on_the_core_screens_it_protects(): void {
		foreach ( array( 'options-general.php', 'options.php', 'plugins.php', 'themes.php', 'update-core.php', 'tools.php' ) as $screen ) {
			$this->assertFalse( Blueworx_Clubhouse_Owner_Capabilities::may_lend( $screen ), "{$screen} must refuse the lent caps" );
		}
	}

	public function test_lending_is_allowed_on_the_screens_the_owner_works_in(): void {
		foreach ( array( 'admin.php', 'index.php', 'edit.php', 'upload.php', 'admin-ajax.php' ) as $screen ) {
			$this->assertTrue( Blueworx_Clubhouse_Owner_Capabilities::may_lend( $screen ), "{$screen} must allow the lent caps" );
		}
	}
}
