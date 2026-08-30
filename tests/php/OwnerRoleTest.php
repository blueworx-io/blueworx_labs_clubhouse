<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OwnerRoleTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		unset( $_GET['page'], $_REQUEST['action'], $GLOBALS['pagenow'] );
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
	 * WordPress's own Pages menu is kept for everyone now. Administrators always
	 * needed it, to reach pages other plugins own (SureCart's customer dashboard,
	 * and the like). An owner needs it because it is the way into a club page:
	 * the fourteen page editors have no menu item of their own, and pressing Edit
	 * on a page in this list is what opens one.
	 *
	 * It is not raw page editing. A club page's row has no Quick Edit and no
	 * Trash, and its Edit link — and a typed post.php address — land in that
	 * page's own editor rather than the block editor.
	 */
	public function test_the_pages_menu_is_left_for_owners_and_administrators_alike(): void {
		$GLOBALS['menu'] = array(
			array( '', 'read', 'index.php' ),
			array( '', 'edit_pages', 'edit.php?post_type=page' ),
		);

		foreach ( array( 'administrator', 'clubhouse_owner', 'clubhouse_content_editor' ) as $role ) {
			$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( $role ) );
			Blueworx_Clubhouse_Owner_Role::lock_menu();
			$removed = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'remove_menu_page' ) );
			$this->assertNotContains( 'edit.php?post_type=page', $removed, $role . ' lost the way into a club page' );
		}
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
	 * The two integrations are the owner's to run, so their own caps are written
	 * onto the role at activation rather than lent per request — SureCart and
	 * LatePoint both read caps outside wp-admin.
	 */
	public function test_activate_writes_the_integration_caps_onto_the_owner(): void {
		Blueworx_Clubhouse_Owner_Role::activate();
		$caps = $GLOBALS['wp_stub_roles']['clubhouse_owner']['caps'];
		$this->assertTrue( $caps['manage_sc_shop_settings'] );
		$this->assertTrue( $caps['manage_latepoint'] );
		$this->assertArrayNotHasKey( 'manage_options', $caps );
	}

	/**
	 * The reason LatePoint was unreachable: its menu is gated on a capability of its
	 * own, not manage_options. Whatever it grants the administrator on activation is
	 * copied to the owner, so a renamed cap is followed rather than guessed at.
	 */
	public function test_activate_copies_unknown_integration_caps_off_the_administrator(): void {
		$GLOBALS['wp_stub_roles']['administrator']['caps']['latepoint_manage_bookings'] = true;
		$GLOBALS['wp_stub_roles']['administrator']['caps']['surecart_manage_shop']      = true;
		$GLOBALS['wp_stub_roles']['administrator']['caps']['manage_woocommerce']        = true;

		Blueworx_Clubhouse_Owner_Role::activate();
		$caps = $GLOBALS['wp_stub_roles']['clubhouse_owner']['caps'];

		$this->assertTrue( $caps['latepoint_manage_bookings'] );
		$this->assertTrue( $caps['surecart_manage_shop'] );
		$this->assertArrayNotHasKey( 'manage_woocommerce', $caps, 'only the two integrations are copied' );
	}

	public function test_activate_never_copies_a_denied_cap_off_the_administrator(): void {
		$GLOBALS['wp_stub_roles']['administrator']['caps']['manage_options'] = true;
		Blueworx_Clubhouse_Owner_Role::activate();
		$this->assertArrayNotHasKey( 'manage_options', $GLOBALS['wp_stub_roles']['clubhouse_owner']['caps'] );
	}

	public function test_lend_caps_hands_owners_nothing_outside_wp_admin(): void {
		$owner = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		$caps  = Blueworx_Clubhouse_Owner_Role::lend_caps( array( 'read' => true ), array(), array(), $owner );
		$this->assertArrayNotHasKey( 'manage_options', $caps );
	}

	public function test_content_editor_is_registered_alongside_the_owner(): void {
		Blueworx_Clubhouse_Owner_Role::activate();
		$this->assertArrayHasKey( 'clubhouse_content_editor', $GLOBALS['wp_stub_roles'] );
		$editor = $GLOBALS['wp_stub_roles']['clubhouse_content_editor'];
		$this->assertSame( 'ClubHouse - Content Editor', $editor['display'] );
		$this->assertArrayNotHasKey( 'manage_clubhouse', $editor['caps'], 'the editor never runs Setup' );
		$this->assertArrayNotHasKey( 'manage_sc_shop_settings', $editor['caps'], 'nor SureCart' );
		$this->assertArrayNotHasKey( 'manage_latepoint', $editor['caps'], 'nor LatePoint' );
		$this->assertTrue( $editor['caps']['edit_users'] );
		$this->assertTrue( $editor['caps']['edit_others_posts'] );
	}

	public function test_the_content_editor_is_never_lent_anything(): void {
		$GLOBALS['wp_stub_is_admin'] = true;
		$GLOBALS['pagenow']          = 'admin.php';
		$editor                      = (object) array( 'roles' => array( 'clubhouse_content_editor' ) );
		$caps                        = Blueworx_Clubhouse_Owner_Role::lend_caps( array( 'read' => true ), array(), array(), $editor );
		$this->assertArrayNotHasKey( 'manage_options', $caps );
		unset( $GLOBALS['pagenow'] );
	}

	public function test_uninstall_removes_both_roles(): void {
		Blueworx_Clubhouse_Owner_Role::activate();
		Blueworx_Clubhouse_Owner_Role::uninstall();
		$this->assertArrayNotHasKey( 'clubhouse_owner', $GLOBALS['wp_stub_roles'] );
		$this->assertArrayNotHasKey( 'clubhouse_content_editor', $GLOBALS['wp_stub_roles'] );
	}

	/** The escalation this whole guard exists to stop. */
	public function test_neither_role_may_edit_an_administrator(): void {
		$GLOBALS['wp_stub_users'] = array(
			1 => (object) array( 'roles' => array( 'administrator' ) ),
			2 => (object) array( 'roles' => array( 'clubhouse_owner' ) ),
			3 => (object) array( 'roles' => array( 'clubhouse_content_editor' ) ),
			4 => (object) array( 'roles' => array( 'author' ) ),
		);
		foreach ( array( 2, 3 ) as $actor ) {
			$this->assertSame(
				array( 'do_not_allow' ),
				Blueworx_Clubhouse_Owner_Role::guard_user_management( array( 'edit_users' ), 'edit_user', $actor, array( 1 ) ),
				'an administrator is never editable'
			);
		}
	}

	public function test_a_content_editor_may_not_edit_an_owner(): void {
		$GLOBALS['wp_stub_users'] = array(
			2 => (object) array( 'roles' => array( 'clubhouse_owner' ) ),
			3 => (object) array( 'roles' => array( 'clubhouse_content_editor' ) ),
		);
		$this->assertSame(
			array( 'do_not_allow' ),
			Blueworx_Clubhouse_Owner_Role::guard_user_management( array( 'edit_users' ), 'edit_user', 3, array( 2 ) ),
			'the owner runs the shop — an editor must not be able to reset their password'
		);
	}

	public function test_ordinary_members_stay_editable(): void {
		$GLOBALS['wp_stub_users'] = array(
			3 => (object) array( 'roles' => array( 'clubhouse_content_editor' ) ),
			4 => (object) array( 'roles' => array( 'author' ) ),
		);
		$this->assertSame(
			array( 'edit_users' ),
			Blueworx_Clubhouse_Owner_Role::guard_user_management( array( 'edit_users' ), 'edit_user', 3, array( 4 ) )
		);
	}

	public function test_editing_your_own_profile_is_always_allowed(): void {
		$GLOBALS['wp_stub_users'] = array( 2 => (object) array( 'roles' => array( 'clubhouse_owner' ) ) );
		$this->assertSame(
			array( 'edit_users' ),
			Blueworx_Clubhouse_Owner_Role::guard_user_management( array( 'edit_users' ), 'edit_user', 2, array( 2 ) )
		);
	}

	public function test_creating_deleting_and_promoting_are_refused_outright(): void {
		$GLOBALS['wp_stub_users'] = array(
			2 => (object) array( 'roles' => array( 'clubhouse_owner' ) ),
			4 => (object) array( 'roles' => array( 'author' ) ),
		);
		foreach ( array( 'delete_user', 'promote_user', 'remove_user', 'create_users' ) as $cap ) {
			$this->assertSame(
				array( 'do_not_allow' ),
				Blueworx_Clubhouse_Owner_Role::guard_user_management( array( 'edit_users' ), $cap, 2, array( 4 ) ),
				"{$cap} must be refused"
			);
		}
	}

	public function test_the_guard_leaves_administrators_alone(): void {
		$GLOBALS['wp_stub_users'] = array(
			1 => (object) array( 'roles' => array( 'administrator' ) ),
			4 => (object) array( 'roles' => array( 'author' ) ),
		);
		$this->assertSame(
			array( 'edit_users' ),
			Blueworx_Clubhouse_Owner_Role::guard_user_management( array( 'edit_users' ), 'edit_user', 1, array( 4 ) )
		);
		$this->assertSame(
			array( 'promote_users' ),
			Blueworx_Clubhouse_Owner_Role::guard_user_management( array( 'promote_users' ), 'promote_user', 1, array( 4 ) )
		);
	}

	public function test_the_role_dropdown_is_narrowed_for_clubhouse_roles(): void {
		$all = array( 'administrator' => 'Admin', 'editor' => 'Editor', 'author' => 'Author', 'clubhouse_owner' => 'Owner' );

		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'clubhouse_content_editor' ) );
		$this->assertSame(
			array( 'editor' => 'Editor', 'author' => 'Author' ),
			Blueworx_Clubhouse_Owner_Role::limit_editable_roles( $all )
		);

		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'administrator' ) );
		$this->assertSame( $all, Blueworx_Clubhouse_Owner_Role::limit_editable_roles( $all ) );
	}

	/**
	 * Clubhouse stays on this role's menu — the menu builder lives there now
	 * (issue #144) — while both integrations are removed.
	 */
	public function test_the_content_editor_menu_drops_both_integrations(): void {
		$GLOBALS['menu'] = array(
			array( '', 'read', 'index.php' ),
			array( '', 'manage_clubhouse', 'clubhouse-setup' ),
			array( '', 'edit_pages', 'edit.php?post_type=page' ),
			array( '', 'edit_posts', 'clubhouse-content' ),
			array( '', 'manage_options', 'sc-dashboard' ),
			array( '', 'manage_options', 'latepoint' ),
			array( '', 'edit_posts', 'edit.php' ),
		);
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'clubhouse_content_editor' ) );
		Blueworx_Clubhouse_Owner_Role::lock_menu();
		$removed = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'remove_menu_page' ) );
		$this->assertContains( 'sc-dashboard', $removed );
		$this->assertContains( 'latepoint', $removed );
		$this->assertNotContains( 'clubhouse-setup', $removed );
		$this->assertNotContains( 'edit.php?post_type=page', $removed );
		$this->assertNotContains( 'clubhouse-content', $removed );
		$this->assertNotContains( 'edit.php', $removed );
	}

	/**
	 * The role-name mask that shipped in 0.47.0–0.47.1 is gone. It never satisfied
	 * LatePoint — which decides earlier than any hook available here — and until it
	 * was removed it handed owners a pseudo-administrator capability for nothing.
	 * This pins the removal: no owner is ever an administrator by any measure.
	 */
	public function test_owners_are_never_given_the_administrator_role_or_its_pseudo_cap(): void {
		$owner                           = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		$GLOBALS['wp_stub_current_user'] = $owner;
		$GLOBALS['wp_stub_is_admin']     = true;

		foreach ( array( 'admin.php', 'index.php', 'edit.php' ) as $screen ) {
			$GLOBALS['pagenow'] = $screen;
			$caps               = Blueworx_Clubhouse_Owner_Role::lend_caps( array( 'read' => true ), array(), array(), $owner );
			$this->assertArrayNotHasKey( 'administrator', $caps, "no pseudo-cap on {$screen}" );
		}
		$_GET['page'] = 'latepoint';
		$caps         = Blueworx_Clubhouse_Owner_Role::lend_caps( array( 'read' => true ), array(), array(), $owner );
		$this->assertArrayNotHasKey( 'administrator', $caps, 'not even on a LatePoint request' );
		$this->assertSame( array( 'clubhouse_owner' ), $owner->roles, 'the roles array is left alone' );

		unset( $_GET['page'], $GLOBALS['pagenow'] );
	}

	public function test_the_dashboard_takeover_is_owner_only(): void {
		$GLOBALS['wp_meta_boxes']        = array( 'dashboard' => array( 'normal' => array( 'core' => array( 'x' => array() ) ) ) );
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'clubhouse_content_editor' ) );
		Blueworx_Clubhouse_Owner_Role::takeover_dashboard();
		$this->assertSame( array(), wp_stub_calls( 'wp_add_dashboard_widget' ) );
		$this->assertNotSame( array(), $GLOBALS['wp_meta_boxes']['dashboard'] );
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
