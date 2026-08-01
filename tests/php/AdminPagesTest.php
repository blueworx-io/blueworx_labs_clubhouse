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

	/** The registry names the four ClubHouse pages, and takes each cap from the page that enforces it. */
	public function test_the_registry_reads_its_caps_from_the_pages_themselves(): void {
		$by_slug = array();
		foreach ( Blueworx_Clubhouse_Admin_Pages::all() as $page ) {
			$by_slug[ $page['slug'] ] = $page;
		}
		$this->assertCount( 4, $by_slug );

		$this->assertSame(
			Blueworx_Clubhouse_Setup_Controller::CAPABILITY,
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
	 * Import is a submenu of Club Content, so the allowlist that decides whether
	 * it is reachable is its PARENT's. Keyed to the parent slug or a role with
	 * Club Content on its menu would be reported as unable to reach Import.
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
		$this->assertCount( 4, Blueworx_Clubhouse_Admin_Pages::pages_for_role( 'administrator' ) );
	}

	/**
	 * The Owner holds manage_clubhouse and carries all four pages on its menu
	 * allowlist, so it reaches all four.
	 */
	public function test_the_owner_reaches_every_page(): void {
		$this->assertCount(
			4,
			Blueworx_Clubhouse_Admin_Pages::pages_for_role( Blueworx_Clubhouse_Owner_Capabilities::ROLE )
		);
	}

	/**
	 * The Content Editor is the whole reason both gates are checked. It holds
	 * edit_posts and has Collections on its menu, so Collections is open to it;
	 * it does NOT hold manage_clubhouse, which is what Setup, Club Content and
	 * Import are each registered with, so all three are shut — regardless of Club
	 * Content sitting on its menu allowlist.
	 */
	public function test_the_content_editor_reaches_only_what_its_capabilities_allow(): void {
		$editor = Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE;

		$this->assertTrue( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG ) );
		$this->assertFalse( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG ) );
		$this->assertFalse( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Content_Controller::PAGE_SLUG ) );
		$this->assertFalse( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Import_Controller::PAGE_SLUG ) );
	}

	/**
	 * Holding the capability is not enough on its own. A role given
	 * manage_clubhouse but no Clubhouse Setup on its menu cannot open it, and the
	 * report must say so rather than reporting the capability alone.
	 */
	public function test_the_menu_allowlist_is_the_second_gate(): void {
		// The Content Editor's allowlist has no clubhouse-setup entry.
		$this->assertNotContains(
			Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
			Blueworx_Clubhouse_Owner_Capabilities::editor_menu_allowlist()
		);

		// Hand it the capability anyway; the allowlist still refuses.
		$caps = array(
			Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE => array(
				Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP => true,
			),
		);
		$this->assertFalse(
			Blueworx_Clubhouse_Admin_Pages::role_can(
				Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE,
				Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
				$caps
			)
		);
	}

	/** Tags are role display labels, in seniority order — administrator first. */
	public function test_access_labels_are_display_names_senior_first(): void {
		$labels = Blueworx_Clubhouse_Admin_Pages::access_labels( Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG );
		$this->assertSame(
			array( 'Administrator', Blueworx_Clubhouse_Owner_Capabilities::DISPLAY ),
			$labels
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
