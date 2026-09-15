<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Issue #145: Import moved off Club Pages and onto the Clubhouse menu,
 * following the menu builder (#144). The User guide made the same move and
 * has since left the plugin altogether — its guides sit on the Enhancements
 * plugin's Guides page now (see GuidesTest).
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
		$this->assertSame( Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG, $args[0] );
		$this->assertSame( Blueworx_Clubhouse_Import_Controller::PAGE_SLUG, $args[4] );
	}

	/** Who can open it is unchanged: Import is owner-and-above. */
	public function test_access_is_unchanged(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP,
			Blueworx_Clubhouse_Import_Controller::CAPABILITY
		);

		$editor = Blueworx_Clubhouse_Owner_Capabilities::EDITOR_ROLE;
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

	/** The import prompt's own words point at the new place. */
	public function test_the_import_prompt_names_the_new_location(): void {
		$prompt = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/import/class-import-prompt.php' );
		$this->assertStringContainsString( 'Clubhouse → Import', $prompt );
		$this->assertStringNotContainsString( 'Club Pages → Import', $prompt );
	}
}
