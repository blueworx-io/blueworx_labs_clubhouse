<?php
// tests/php/WordpressPagesTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * WordPress's own Pages screen.
 *
 * Club pages are real pages now, so the screen is on the menu again and they
 * are listed on it. It is somewhere to see them, not somewhere to edit them:
 * every row action that could rename, retitle or bin one is taken away, and
 * the deletion is refused for real rather than only hidden.
 */
final class WordpressPagesTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_the_menu_slug_is_wordpress_own_pages_screen(): void {
		$this->assertSame( 'edit.php?post_type=page', Blueworx_Clubhouse_Wordpress_Pages::MENU_SLUG );
	}

	/** The screen used to be taken off the menu. It is a real place to look now. */
	public function test_it_no_longer_takes_the_pages_menu_away(): void {
		Blueworx_Clubhouse_Wordpress_Pages::register();

		$hooked = array_map( static fn( array $c ): array => $c['args'], wp_stub_calls( 'add_action' ) );
		foreach ( $hooked as $args ) {
			$this->assertNotSame( 'admin_menu', $args[0] ?? '' );
		}
		$this->assertSame( array(), wp_stub_calls( 'remove_menu_page' ) );
	}

	public function test_a_club_page_offers_no_row_action_that_could_change_it(): void {
		// The plugin depends on these pages existing at these slugs. Trash,
		// quick edit and rename are all ways to break the site from a screen
		// that looks harmless.
		$actions = Blueworx_Clubhouse_Wordpress_Pages::row_actions(
			array(
				'edit'                => 'E',
				'inline hide-if-no-js' => 'Q',
				'trash'               => 'T',
				'view'                => 'V',
			),
			true
		);
		$this->assertArrayNotHasKey( 'inline hide-if-no-js', $actions );
		$this->assertArrayNotHasKey( 'trash', $actions );
		$this->assertArrayHasKey( 'edit', $actions );
		$this->assertArrayHasKey( 'view', $actions );
	}

	public function test_any_other_page_keeps_all_of_its_actions(): void {
		$given = array(
			'edit'                 => 'E',
			'inline hide-if-no-js' => 'Q',
			'trash'                => 'T',
		);
		$this->assertSame( $given, Blueworx_Clubhouse_Wordpress_Pages::row_actions( $given, false ) );
	}

	/** Whatever else the row offers — Untrash, Delete Permanently — goes too. */
	public function test_a_club_page_loses_the_permanent_delete_as_well(): void {
		$actions = Blueworx_Clubhouse_Wordpress_Pages::row_actions(
			array( 'untrash' => 'U', 'delete' => 'D' ),
			true
		);
		$this->assertSame( array(), $actions );
	}

	public function test_the_list_gains_a_column_naming_ours(): void {
		$columns = Blueworx_Clubhouse_Wordpress_Pages::columns(
			array( 'cb' => '', 'title' => 'Title', 'date' => 'Date' )
		);
		$this->assertArrayHasKey( Blueworx_Clubhouse_Wordpress_Pages::COLUMN, $columns );
		$this->assertSame( 'Club page', $columns[ Blueworx_Clubhouse_Wordpress_Pages::COLUMN ] );
		// Beside the title, not shoved on the end after the date.
		$this->assertSame(
			array( 'cb', 'title', Blueworx_Clubhouse_Wordpress_Pages::COLUMN, 'date' ),
			array_keys( $columns )
		);
	}

	public function test_the_column_reads_club_page_for_ours_and_nothing_for_anything_else(): void {
		$this->assertSame( 'Club page', Blueworx_Clubhouse_Wordpress_Pages::column_text( true ) );
		$this->assertSame( '', Blueworx_Clubhouse_Wordpress_Pages::column_text( false ) );
	}

	/**
	 * A hidden row action is a UI change, not a guarantee. Anything that
	 * reaches wp_trash_post() or wp_delete_post() by another route — a bulk
	 * action, another plugin, WP-CLI — has to be refused too.
	 */
	public function test_a_club_page_cannot_be_trashed_or_deleted(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$this->assertTrue( Blueworx_Clubhouse_Wordpress_Pages::blocks_deletion( 42 ) );
	}

	/** Home is a club page too, and its slug is '' — never a truthiness check. */
	public function test_home_cannot_be_deleted_either(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 52 );
		$this->assertTrue( Blueworx_Clubhouse_Wordpress_Pages::blocks_deletion( 52 ) );
	}

	public function test_any_other_page_can_still_be_deleted(): void {
		$this->assertFalse( Blueworx_Clubhouse_Wordpress_Pages::blocks_deletion( 999 ) );
	}

	public function test_it_hooks_the_list_and_both_ways_a_page_gets_deleted(): void {
		Blueworx_Clubhouse_Wordpress_Pages::register();

		$filters = array_map( static fn( array $c ): array => $c['args'], wp_stub_calls( 'add_filter' ) );
		$actions = array_map( static fn( array $c ): array => $c['args'], wp_stub_calls( 'add_action' ) );

		$this->assertContains( 'page_row_actions', array_column( $filters, 0 ) );
		$this->assertContains( 'manage_pages_columns', array_column( $filters, 0 ) );
		$this->assertContains( 'manage_pages_custom_column', array_column( $actions, 0 ) );
		$this->assertContains( 'wp_trash_post', array_column( $actions, 0 ) );
		$this->assertContains( 'before_delete_post', array_column( $actions, 0 ) );
	}
}
