<?php

use PHPUnit\Framework\TestCase;

/**
 * The Setup screen as the library receives it.
 */
final class SetupEditorTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_the_slug_is_the_one_every_link_already_uses(): void {
		$this->assertSame( 'clubhouse-setup', Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG );
	}

	public function test_the_screen_is_reachable_by_a_content_editor(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			Blueworx_Clubhouse_Setup_Editor::screen()['capability']
		);
	}

	public function test_the_screen_supplies_its_own_read_and_write(): void {
		$screen = Blueworx_Clubhouse_Setup_Editor::screen();
		$this->assertIsCallable( $screen['read'] );
		$this->assertIsCallable( $screen['write'] );
	}

	public function test_the_library_accepts_the_real_screen(): void {
		$this->assertIsArray( \Blueworx\PageEditor\v1\Schema::validate( Blueworx_Clubhouse_Setup_Editor::screen() ) );
	}

	public function test_every_look_the_site_can_wear_is_offered(): void {
		$looks = Blueworx_Clubhouse_Setup_Editor::looks( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertArrayHasKey( 'court-side', $looks );
		$this->assertNotSame( '', $looks['court-side']['description'] );
	}

	public function test_home_is_offered_under_the_key_visibility_is_stored_by(): void {
		$this->assertContains( 'home', array_column( Blueworx_Clubhouse_Setup_Editor::pages(), 'page' ) );
		$this->assertNotContains( '', array_column( Blueworx_Clubhouse_Setup_Editor::pages(), 'page' ) );
	}

	// ---- the one rule the library cannot know ----

	public function test_an_illegible_main_colour_is_refused_on_the_field(): void {
		// Mid-grey: neither the shell's near-black ink nor white clears AA on
		// it, so a look that paints text on the accent cannot use it.
		$errors = Blueworx_Clubhouse_Setup_Editor::validate( array( 'look' => 'court-side', 'accent' => '#7a7a7a' ) );
		$this->assertArrayHasKey( 'accent', $errors );
	}

	public function test_a_colour_that_is_not_a_colour_is_refused(): void {
		$errors = Blueworx_Clubhouse_Setup_Editor::validate( array( 'look' => 'court-side', 'accent' => 'greenish' ) );
		$this->assertArrayHasKey( 'accent', $errors );
	}

	public function test_a_legible_main_colour_saves(): void {
		$this->assertSame(
			array(),
			Blueworx_Clubhouse_Setup_Editor::validate( array( 'look' => 'court-side', 'accent' => '#c6f24e' ) )
		);
	}

	public function test_a_low_contrast_second_colour_is_not_refused(): void {
		$errors = Blueworx_Clubhouse_Setup_Editor::validate(
			array( 'look' => 'court-side', 'accent' => '#c6f24e', 'secondary' => '#8a8a8a' )
		);
		$this->assertArrayNotHasKey( 'secondary', $errors );
	}

	public function test_a_save_that_carries_no_colour_is_not_judged_on_one(): void {
		$this->assertSame( array(), Blueworx_Clubhouse_Setup_Editor::validate( array( 'menu' => array() ) ) );
	}

	// ---- the sidebar ----

	public function test_the_clubhouse_item_is_moved_back_up_the_sidebar(): void {
		$GLOBALS['menu'] = array(
			2   => array( 'Dashboard', 'read', 'index.php' ),
			80  => array( 'Settings', 'manage_options', 'options-general.php' ),
			101 => array( 'Clubhouse', 'edit_clubhouse_content', 'clubhouse-setup' ),
		);

		Blueworx_Clubhouse_Setup_Editor::place_menu();

		$slugs = array_column( $GLOBALS['menu'], 2 );
		$this->assertSame( array( 'index.php', 'clubhouse-setup', 'options-general.php' ), $slugs );
	}

	public function test_an_occupied_position_is_stepped_past_rather_than_overwritten(): void {
		$GLOBALS['menu'] = array(
			2   => array( 'Dashboard', 'read', 'index.php' ),
			3   => array( 'Someone else', 'read', 'other.php' ),
			101 => array( 'Clubhouse', 'edit_clubhouse_content', 'clubhouse-setup' ),
		);

		Blueworx_Clubhouse_Setup_Editor::place_menu();

		$this->assertSame(
			array( 'index.php', 'other.php', 'clubhouse-setup' ),
			array_column( $GLOBALS['menu'], 2 )
		);
	}

	public function test_it_does_nothing_when_there_is_no_menu(): void {
		$GLOBALS['menu'] = array();
		Blueworx_Clubhouse_Setup_Editor::place_menu();
		$this->assertSame( array(), $GLOBALS['menu'] );
	}
}
