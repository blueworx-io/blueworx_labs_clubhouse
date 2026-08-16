<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Who can open which ClubHouse admin page. Previously only knowable by reading
 * three things at once — the page's capability constant, the role capability
 * maps and the per-role menu allowlist — and only ever confirmable by logging
 * in as somebody. These pin the answer, and pin that BOTH gates are applied:
 * a page a role holds the capability for but which is stripped from its menu is
 * not a page that role can reach.
 */
final class AdminPagesTest extends TestCase {

	/** The registry names every ClubHouse page, and takes each cap from the page that enforces it. */
	public function test_the_registry_reads_its_caps_from_the_pages_themselves(): void {
		$by_slug = array();
		foreach ( Blueworx_Clubhouse_Admin_Pages::all() as $page ) {
			$by_slug[ $page['slug'] ] = $page;
		}
		$this->assertCount( 6, $by_slug );

		// The Clubhouse screen is REGISTERED with the content capability, because
		// the menu builder on it belongs to the Content Editor (issue #144). The
		// setup tabs themselves are still gated on CAPABILITY inside the screen.
		$this->assertSame(
			Blueworx_Clubhouse_Setup_Controller::MENU_CAPABILITY,
			$by_slug[ Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG ]['cap']
		);
		$this->assertSame(
			Blueworx_Clubhouse_Content_Controller::CAPABILITY,
			$by_slug[ Blueworx_Clubhouse_Content_Controller::PAGE_SLUG ]['cap']
		);
		$this->assertSame(
			Blueworx_Clubhouse_Import_Controller::CAPABILITY,
			$by_slug[ Blueworx_Clubhouse_Import_Controller::PAGE_SLUG ]['cap']
		);
		$this->assertSame(
			Blueworx_Clubhouse_Collection_Types::CONTENT_CAP,
			$by_slug[ Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG ]['cap']
		);
	}

	/**
	 * Import is a submenu of Club Pages, so the allowlist that decides whether
	 * it is reachable is its PARENT's. Keyed to the parent slug or a role with
	 * Club Pages on its menu would be reported as unable to reach Import.
	 */
	public function test_import_hangs_off_its_parents_menu(): void {
		$import = Blueworx_Clubhouse_Admin_Pages::find( Blueworx_Clubhouse_Import_Controller::PAGE_SLUG );
		$this->assertNotNull( $import );
		$this->assertSame( Blueworx_Clubhouse_Content_Controller::PAGE_SLUG, $import['menu'] );
	}

	public function test_an_unknown_slug_is_not_a_clubhouse_page(): void {
		$this->assertNull( Blueworx_Clubhouse_Admin_Pages::find( 'options-general.php' ) );
		$this->assertFalse( Blueworx_Clubhouse_Admin_Pages::role_can( 'administrator', 'options-general.php' ) );
		$this->assertSame( array(), Blueworx_Clubhouse_Admin_Pages::roles_with_access( 'nope' ) );
	}

	/** An administrator reaches every ClubHouse page — nothing is removed from an administrator's menu. */
	public function test_an_administrator_reaches_every_page(): void {
		foreach ( Blueworx_Clubhouse_Admin_Pages::all() as $page ) {
			$this->assertTrue(
				Blueworx_Clubhouse_Admin_Pages::role_can( Blueworx_Clubhouse_Admin_Pages::ADMINISTRATOR, $page['slug'] ),
				$page['slug']
			);
		}
		$this->assertCount( 6, Blueworx_Clubhouse_Admin_Pages::pages_for_role( 'administrator' ) );
	}

	/**
	 * The Owner holds manage_clubhouse and carries every page on its menu
	 * allowlist, so it reaches all of them.
	 */
	public function test_the_owner_reaches_every_page(): void {
		$this->assertCount(
			6,
			Blueworx_Clubhouse_Admin_Pages::pages_for_role( Blueworx_Clubhouse_Owner_Capabilities::ROLE )
		);
	}

	/**
	 * The Content Editor can edit content — Club Pages and Collections — and
	 * nothing that configures the site.
	 *
	 * Club Pages used to be shut to it, because the page was locked with
	 * manage_clubhouse, the single capability separating the two roles. That made
	 * the Content Editor unable to edit content, which is the only job the role
	 * exists for. It is now locked with edit_clubhouse_content, which both roles
	 * hold.
	 *
	 * The Clubhouse screen itself opens to this role since the menu builder moved
	 * onto it (issue #144) — but only the Menu tab renders, because every setup
	 * tab is gated on manage_clubhouse, which this role does not hold. Import
	 * stays shut — it replaces every page's content wholesale AND can switch
	 * sections off, which is a Visibility change this role is not allowed to make
	 * by hand. Search & sharing, which also hangs off the Clubhouse menu, stays
	 * shut on its own capability.
	 */
	public function test_the_content_editor_can_edit_content_but_not_configure_the_site(): void {
		$editor = Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE;

		$this->assertTrue( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Content_Controller::PAGE_SLUG ), 'Club Pages' );
		$this->assertTrue( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG ), 'Collections' );
		$this->assertTrue( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG ), 'Clubhouse — for the Menu tab' );

		$this->assertFalse( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Import_Controller::PAGE_SLUG ), 'Import' );
		$this->assertFalse( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Seo_Controller::PAGE_SLUG ), 'Search & sharing' );

		// The screen opening is not the same as the screen configuring: setup
		// itself is still behind the capability the role does not hold.
		$caps = Blueworx_Clubhouse_Owner_Capabilities::editor_capabilities();
		$this->assertArrayNotHasKey( Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP, $caps );
	}

	/**
	 * The guide hangs off Club Pages rather than Setup, and this is why: the
	 * Content Editor holds the capability it is locked with, but Setup is stripped
	 * from that role's menu — parented there, the guide would be invisible to the
	 * role most likely to need it.
	 */
	public function test_the_content_editor_can_open_the_user_guide(): void {
		$guide = Blueworx_Clubhouse_Admin_Pages::find( Blueworx_Clubhouse_Guide_Controller::PAGE_SLUG );
		$this->assertNotNull( $guide );
		$this->assertSame( Blueworx_Clubhouse_Content_Controller::PAGE_SLUG, $guide['menu'] );
		$this->assertTrue(
			Blueworx_Clubhouse_Admin_Pages::role_can(
				Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE,
				Blueworx_Clubhouse_Guide_Controller::PAGE_SLUG
			)
		);
	}

	/**
	 * The two roles still differ by exactly one capability, and it is still
	 * manage_clubhouse. Splitting content editing out of it must not have widened
	 * the gap or narrowed it to nothing.
	 */
	public function test_the_two_roles_still_differ_by_manage_clubhouse_alone(): void {
		$owner  = Blueworx_Clubhouse_Owner_Capabilities::capabilities();
		$editor = Blueworx_Clubhouse_Owner_Capabilities::editor_capabilities();

		$this->assertSame(
			array( Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP ),
			array_keys( array_diff_key( $owner, $editor ) )
		);
		$this->assertSame( array(), array_keys( array_diff_key( $editor, $owner ) ) );

		// And both hold the content key.
		$this->assertTrue( $owner[ Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP ] ?? false );
		$this->assertTrue( $editor[ Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP ] ?? false );
	}

	/**
	 * Holding the capability is not enough on its own. A role given
	 * manage_clubhouse but no Clubhouse Setup on its menu cannot open it, and the
	 * report must say so rather than reporting the capability alone.
	 */
	public function test_the_menu_allowlist_is_the_second_gate(): void {
		// A role holding a page's capability but without that page's top-level
		// menu still cannot reach it. Club Pages is on no allowlist here.
		$caps = array(
			Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE => array(
				Blueworx_Clubhouse_Content_Controller::CAPABILITY => true,
			),
		);
		$this->assertTrue(
			Blueworx_Clubhouse_Admin_Pages::role_can(
				Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE,
				Blueworx_Clubhouse_Content_Controller::PAGE_SLUG,
				$caps
			),
			'on the menu and holding the cap — reachable'
		);

		// Search & sharing hangs off the Clubhouse menu, which this role now has
		// (the Menu tab lives there), and it is STILL out of reach: the
		// capability gate has not been weakened by opening the menu item.
		$this->assertFalse(
			Blueworx_Clubhouse_Admin_Pages::role_can(
				Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE,
				Blueworx_Clubhouse_Seo_Controller::PAGE_SLUG
			)
		);
		$this->assertContains(
			Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
			Blueworx_Clubhouse_Owner_Capabilities::editor_menu_allowlist(),
			'the menu item itself is open, for the Menu tab'
		);
	}

	/** Tags are role display labels, in seniority order — administrator first. */
	public function test_access_labels_are_display_names_senior_first(): void {
		// All three reach the Clubhouse screen now — the Content Editor for the
		// Menu tab on it, and nothing else.
		$labels = Blueworx_Clubhouse_Admin_Pages::access_labels( Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG );
		$this->assertSame(
			array( 'Administrator', Blueworx_Clubhouse_Owner_Capabilities::DISPLAY, Blueworx_Clubhouse_Owner_Capabilities::EDITOR_DISPLAY ),
			$labels
		);

		// Search & sharing, on the same menu, stays owner-and-above.
		$this->assertSame(
			array( 'Administrator', Blueworx_Clubhouse_Owner_Capabilities::DISPLAY ),
			Blueworx_Clubhouse_Admin_Pages::access_labels( Blueworx_Clubhouse_Seo_Controller::PAGE_SLUG )
		);

		$collections = Blueworx_Clubhouse_Admin_Pages::access_labels( Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG );
		$this->assertSame(
			array( 'Administrator', Blueworx_Clubhouse_Owner_Capabilities::DISPLAY, Blueworx_Clubhouse_Owner_Capabilities::EDITOR_DISPLAY ),
			$collections
		);
	}

	/** The reported roles are exactly the administrator plus the two ClubHouse roles. */
	public function test_the_reported_roles_are_the_administrator_and_the_clubhouse_roles(): void {
		$this->assertSame(
			array( 'administrator', Blueworx_Clubhouse_Owner_Capabilities::ROLE, Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE ),
			Blueworx_Clubhouse_Admin_Pages::roles()
		);
	}

	/**
	 * The ClubHouse roles are judged against the same map that registers them, so
	 * this cannot report access the site does not actually grant.
	 */
	public function test_clubhouse_roles_are_judged_against_the_map_that_registers_them(): void {
		$maps = Blueworx_Clubhouse_Admin_Pages::role_capabilities();
		$this->assertSame(
			Blueworx_Clubhouse_Owner_Capabilities::capabilities(),
			$maps[ Blueworx_Clubhouse_Owner_Capabilities::ROLE ]
		);
		$this->assertSame(
			Blueworx_Clubhouse_Owner_Capabilities::editor_capabilities(),
			$maps[ Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE ]
		);
		// The administrator model is a floor, and must at least clear every cap the
		// registry gates on or the report understates an administrator's access.
		foreach ( Blueworx_Clubhouse_Admin_Pages::all() as $page ) {
			$this->assertTrue( $maps['administrator'][ $page['cap'] ] ?? false, $page['cap'] );
		}
	}
}
