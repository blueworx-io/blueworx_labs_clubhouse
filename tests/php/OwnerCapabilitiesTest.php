<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OwnerCapabilitiesTest extends TestCase {

	public function test_role_and_cap_names(): void {
		$this->assertSame( 'clubhouse_owner', Blueworx_Clubhouse_Owner_Capabilities::ROLE );
		$this->assertSame( 'ClubHouse - Owner', Blueworx_Clubhouse_Owner_Capabilities::DISPLAY );
		$this->assertSame( 'clubhouse_content_editor', Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE );
		$this->assertSame( 'ClubHouse - Content Editor', Blueworx_Clubhouse_Owner_Capabilities::EDITOR_DISPLAY );
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
				'clubhouse-pages',
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

	/**
	 * manage_options is what WordPress checks before one account may edit another's
	 * role, so lending it on the user screens would hand back the escalation the
	 * map_meta_cap guard exists to prevent.
	 */
	public function test_lending_is_refused_on_the_user_screens(): void {
		foreach ( array( 'users.php', 'user-new.php', 'user-edit.php', 'profile.php' ) as $screen ) {
			$this->assertFalse( Blueworx_Clubhouse_Owner_Capabilities::may_lend( $screen ), "{$screen} must refuse the lent caps" );
		}
	}

	public function test_both_roles_are_cloned_from_the_live_editor(): void {
		$live = array( 'edit_posts' => true, 'wpseo_manage_options' => true, 'moderate_comments' => true, 'edit_pages' => true );
		foreach ( array( Blueworx_Clubhouse_Owner_Capabilities::ROLE, Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE ) as $role ) {
			$caps = Blueworx_Clubhouse_Owner_Capabilities::capabilities_for( $role, $live );
			$this->assertTrue( $caps['wpseo_manage_options'], 'a site-specific editor cap travels with the clone' );
			$this->assertArrayNotHasKey( 'moderate_comments', $caps, 'comments are not a Clubhouse surface' );
			$this->assertArrayNotHasKey( 'edit_pages', $caps, 'pages are served by Clubhouse routing' );
		}
	}

	/** Clone-then-subtract: a site that has over-powered its editor must not leak that through. */
	public function test_a_denied_cap_on_the_live_editor_is_stripped_from_the_clone(): void {
		$live = array( 'edit_posts' => true, 'manage_options' => true, 'activate_plugins' => true, 'promote_users' => true );
		$caps = Blueworx_Clubhouse_Owner_Capabilities::capabilities( $live );
		foreach ( array( 'manage_options', 'activate_plugins', 'promote_users' ) as $cap ) {
			$this->assertArrayNotHasKey( $cap, $caps, "{$cap} must never survive the clone" );
		}
	}

	public function test_the_two_roles_differ_by_exactly_manage_clubhouse(): void {
		$owner  = Blueworx_Clubhouse_Owner_Capabilities::capabilities();
		$editor = Blueworx_Clubhouse_Owner_Capabilities::editor_capabilities();
		$this->assertSame( array( 'manage_clubhouse' ), array_values( array_diff( array_keys( $owner ), array_keys( $editor ) ) ) );
		$this->assertSame( array(), array_diff( array_keys( $editor ), array_keys( $owner ) ) );
	}

	public function test_both_roles_can_edit_users_but_never_promote_them(): void {
		foreach ( array( Blueworx_Clubhouse_Owner_Capabilities::capabilities(), Blueworx_Clubhouse_Owner_Capabilities::editor_capabilities() ) as $caps ) {
			$this->assertTrue( $caps['list_users'] );
			$this->assertTrue( $caps['edit_users'] );
			foreach ( array( 'promote_users', 'create_users', 'delete_users', 'remove_users' ) as $cap ) {
				$this->assertArrayNotHasKey( $cap, $caps );
			}
		}
	}

	public function test_privileged_roles_are_never_manageable(): void {
		foreach ( array( 'administrator', 'clubhouse_owner', 'blueworx_client_owner', 'blueworx_client_dev', 'blueworx_support' ) as $role ) {
			$this->assertFalse( Blueworx_Clubhouse_Owner_Capabilities::may_manage_user( array( $role ) ), "{$role} must be out of reach" );
		}
	}

	public function test_ordinary_roles_are_manageable(): void {
		foreach ( array( 'subscriber', 'contributor', 'author', 'editor', 'clubhouse_content_editor' ) as $role ) {
			$this->assertTrue( Blueworx_Clubhouse_Owner_Capabilities::may_manage_user( array( $role ) ) );
		}
		$this->assertTrue( Blueworx_Clubhouse_Owner_Capabilities::may_manage_user( array() ), 'a user with no role carries no power' );
		$this->assertFalse(
			Blueworx_Clubhouse_Owner_Capabilities::may_manage_user( array( 'author', 'administrator' ) ),
			'one privileged role among several is still out of reach'
		);
	}

	/**
	 * Clubhouse is on this list so the role can reach the menu builder, which
	 * moved onto that screen (issue #144). It still holds no manage_clubhouse,
	 * so the screen shows it the Menu tab and nothing else. Both integrations
	 * stay off the list.
	 */
	public function test_the_content_editor_menu_excludes_both_integrations(): void {
		$allowed = Blueworx_Clubhouse_Owner_Capabilities::editor_menu_allowlist();
		$this->assertSame(
			array( 'index.php', 'clubhouse-setup', 'clubhouse-pages', 'clubhouse-content', 'edit.php', 'upload.php', 'users.php', 'profile.php' ),
			$allowed
		);
		$this->assertNotContains( 'sc-dashboard', $allowed );
		$this->assertNotContains( 'latepoint', $allowed );
		$this->assertArrayNotHasKey(
			Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP,
			Blueworx_Clubhouse_Owner_Capabilities::editor_capabilities(),
			'reaching the screen is not the same as being able to configure it'
		);
	}

	public function test_integration_caps_are_read_off_the_administrator(): void {
		$found = Blueworx_Clubhouse_Owner_Capabilities::integration_caps_from(
			array( 'latepoint_agent' => true, 'sc_manage_orders' => true, 'manage_options' => true, 'edit_posts' => true, 'switched_off' => false )
		);
		$this->assertContains( 'latepoint_agent', $found );
		$this->assertContains( 'sc_manage_orders', $found );
		$this->assertContains( 'manage_latepoint', $found, 'the hard-coded floor is kept' );
		$this->assertNotContains( 'manage_options', $found );
		$this->assertNotContains( 'edit_posts', $found );
	}
}
