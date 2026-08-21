<?php

use PHPUnit\Framework\TestCase;

/**
 * Editing a club page.
 *
 * Club pages are real WordPress pages now, so WordPress offers an Edit link
 * into the block editor for each one — a second, contradictory place to write
 * a club's words, over a body that is deliberately empty. Editing has to keep
 * feeling exactly as it did: Edit lands on the Club Pages screen, on the tab
 * for that page, and the block editor is never reached.
 */
final class ClubPageEditingTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_the_editor_url_points_at_the_right_page_in_club_pages(): void {
		$this->assertStringContainsString( 'page=clubhouse-site-content', Blueworx_Clubhouse_Club_Page_Editing::editor_url( 'about' ) );
		$this->assertStringContainsString( 'tab=about', Blueworx_Clubhouse_Club_Page_Editing::editor_url( 'about' ) );
	}

	/**
	 * Home's Page_Map slug is '', but the Club Pages screen names its tab
	 * 'home' — Content_Catalogue's Home page entry. An empty tab would land on
	 * whichever tab happened to be first (Global), which is not Home's words.
	 */
	public function test_home_lands_on_the_tab_the_screen_actually_emits(): void {
		$this->assertStringContainsString( 'tab=home', Blueworx_Clubhouse_Club_Page_Editing::editor_url( '' ) );
	}

	public function test_a_club_page_never_uses_the_block_editor(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$this->assertFalse( Blueworx_Clubhouse_Club_Page_Editing::wants_block_editor( true, 42 ) );
	}

	public function test_every_other_page_keeps_whatever_editor_it_had(): void {
		// This filter runs for every post in wp-admin. A club page is the only
		// thing we have an opinion about.
		$this->assertTrue( Blueworx_Clubhouse_Club_Page_Editing::wants_block_editor( true, 999 ) );
		$this->assertFalse( Blueworx_Clubhouse_Club_Page_Editing::wants_block_editor( false, 999 ) );
	}

	/** Home is a club page too, and its slug is '' — never a truthiness check. */
	public function test_home_never_uses_the_block_editor_either(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 52 );
		$this->assertFalse( Blueworx_Clubhouse_Club_Page_Editing::wants_block_editor( true, 52 ) );
	}

	public function test_the_edit_link_for_a_club_page_goes_to_club_pages(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$this->assertSame(
			Blueworx_Clubhouse_Club_Page_Editing::editor_url( 'about' ),
			Blueworx_Clubhouse_Club_Page_Editing::filter_edit_link( 'https://club.test/wp-admin/post.php?post=42&action=edit', 42 )
		);
	}

	public function test_the_edit_link_for_anything_else_is_left_alone(): void {
		$link = 'https://club.test/wp-admin/post.php?post=999&action=edit';
		$this->assertSame( $link, Blueworx_Clubhouse_Club_Page_Editing::filter_edit_link( $link, 999 ) );
	}
}
