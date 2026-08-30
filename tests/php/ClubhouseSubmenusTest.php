<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Issue #145: Import and the User guide moved off Club Pages and onto the
 * Clubhouse menu, following the menu builder (#144).
 *
 * The guide could not have gone there before: Clubhouse was stripped from the
 * Content Editor's menu, so a guide parented there would have been invisible to
 * the role most likely to need it. That menu is open to the role now, which is
 * what makes the move safe — so these pin the reachability, not just the parent.
 */
final class ClubhouseSubmenusTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	/**
	 * WordPress escapes a menu title itself, so one passed in pre-escaped is
	 * escaped twice and the entity shows in the sidebar (issue #291).
	 */
	public function test_the_seo_screen_gives_its_menu_a_plain_title(): void {
		Blueworx_Clubhouse_Seo_Controller::add_menu();
		$args = wp_stub_calls( 'add_submenu_page' )[0]['args'];
		$this->assertSame( 'Search & sharing', $args[1] );
		$this->assertSame( 'Search & sharing', $args[2] );
	}

	public function test_import_is_registered_under_clubhouse(): void {
		Blueworx_Clubhouse_Import_Controller::add_menu();
		$args = wp_stub_calls( 'add_submenu_page' )[0]['args'];
		$this->assertSame( Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG, $args[0] );
		$this->assertSame( Blueworx_Clubhouse_Import_Controller::PAGE_SLUG, $args[4] );
	}

	public function test_the_guide_is_registered_under_clubhouse(): void {
		Blueworx_Clubhouse_Guide_Controller::add_menu();
		$args = wp_stub_calls( 'add_submenu_page' )[0]['args'];
		$this->assertSame( Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG, $args[0] );
		$this->assertSame( Blueworx_Clubhouse_Guide_Controller::PAGE_SLUG, $args[4] );
	}

	/*
	 * A case stood here asserting that neither screen still hung off Club
	 * Pages. It searched both files for a reference to a class that no longer
	 * exists, so it could only ever pass — the two cases above, which assert
	 * what each screen actually registers under, are what hold this now.
	 */

	/** Who can open them is unchanged: Import owner-and-above, the guide both roles. */
	public function test_access_is_unchanged(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP,
			Blueworx_Clubhouse_Import_Controller::CAPABILITY
		);
		$this->assertSame(
			Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			Blueworx_Clubhouse_Guide_Controller::CAPABILITY
		);

		$editor = Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE;
		$this->assertTrue( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Guide_Controller::PAGE_SLUG ), 'the guide' );
		$this->assertFalse( Blueworx_Clubhouse_Admin_Pages::role_can( $editor, Blueworx_Clubhouse_Import_Controller::PAGE_SLUG ), 'Import' );
	}

	/**
	 * A submenu's hook is named after its parent, so an enqueue matched against
	 * the old parent's name would silently stop loading the stylesheet — the
	 * screen would still work, unstyled.
	 */
	public function test_import_still_loads_its_stylesheet_under_the_new_parent(): void {
		Blueworx_Clubhouse_Import_Controller::enqueue( 'clubhouse_page_' . Blueworx_Clubhouse_Import_Controller::PAGE_SLUG );
		$this->assertNotSame( array(), wp_stub_calls( 'wp_enqueue_style' ) );
	}

	public function test_import_loads_nothing_on_an_unrelated_screen(): void {
		Blueworx_Clubhouse_Import_Controller::enqueue( 'edit.php' );
		$this->assertSame( array(), wp_stub_calls( 'wp_enqueue_style' ) );
	}

	/** The guide's own words point at the new places. */
	public function test_the_guide_names_the_new_locations(): void {
		$php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/class-guide.php' );
		$this->assertStringContainsString( 'Open Clubhouse, then Import.', $php );

		$prompt = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/import/class-import-prompt.php' );
		$this->assertStringContainsString( 'Clubhouse → Import', $prompt );
		$this->assertStringNotContainsString( 'Club Pages → Import', $prompt );
	}
}
