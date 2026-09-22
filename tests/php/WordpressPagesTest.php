<?php
// tests/php/WordpressPagesTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * WordPress's own Pages screen.
 *
 * Club pages are real pages now, so the screen is on the menu again and they
 * are listed on it. It is somewhere to see them, not somewhere to edit them.
 * BlueWorx Labs' Source column names them and keeps them read-only; what is
 * left here is the club's own status guard.
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

	/**
	 * The Source column is BlueWorx Labs', and so is the protection a source
	 * brings — view and edit only, and no deleting. Ours are named through its
	 * filter, so club pages and the shop's pages are guarded the same way.
	 */
	public function test_a_club_page_is_named_club_page_for_the_source_column(): void {
		$this->assertSame( 'Club page', Blueworx_Clubhouse_Wordpress_Pages::source( '', true ) );
		$this->assertSame( '', Blueworx_Clubhouse_Wordpress_Pages::source( '', false ) );
	}

	/** A page is one plugin's: a label given first is kept. */
	public function test_a_label_another_plugin_gave_first_is_kept(): void {
		$this->assertSame( 'Commerce page', Blueworx_Clubhouse_Wordpress_Pages::source( 'Commerce page', true ) );
	}

	public function test_the_filter_finds_the_page_from_its_id(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$this->assertSame( 'Club page', Blueworx_Clubhouse_Wordpress_Pages::on_page_source( '', 42 ) );
		$this->assertSame( '', Blueworx_Clubhouse_Wordpress_Pages::on_page_source( '', 999 ) );
		// A label that is not a string counts as no label.
		$this->assertSame( 'Club page', Blueworx_Clubhouse_Wordpress_Pages::on_page_source( array( 'junk' ), 42 ) );
	}

	/** Home is a club page too, and its slug is '' — never a truthiness check. */
	public function test_home_is_named_too(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 52 );
		$this->assertSame( 'Club page', Blueworx_Clubhouse_Wordpress_Pages::on_page_source( '', 52 ) );
	}

	public function test_it_answers_labs_source_filter_and_nothing_labs_now_does_itself(): void {
		Blueworx_Clubhouse_Wordpress_Pages::register();

		$filters = array_column( array_map( static fn( array $c ): array => $c['args'], wp_stub_calls( 'add_filter' ) ), 0 );
		$actions = array_column( array_map( static fn( array $c ): array => $c['args'], wp_stub_calls( 'add_action' ) ), 0 );

		$this->assertContains( 'blueworx_page_source', $filters );
		foreach ( array( 'page_row_actions', 'manage_pages_columns' ) as $hook ) {
			$this->assertNotContains( $hook, $filters );
		}
		foreach ( array( 'manage_pages_custom_column', 'wp_trash_post', 'before_delete_post' ) as $hook ) {
			$this->assertNotContains( $hook, $actions );
		}
	}

	/**
	 * Bulk Edit in the Pages list can set a status on every selected row, and
	 * it goes nowhere near the trash and delete hooks. A club page's status now
	 * means "switched on", so letting the list change it would switch a page
	 * off behind the Setup screen's back and leave the flag and the page
	 * disagreeing. The status is put back to whatever the flag calls for.
	 */
	public function test_a_bulk_status_change_cannot_switch_a_club_page_off(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );

		$guarded = Blueworx_Clubhouse_Wordpress_Pages::guard_status(
			array(
				'post_status' => 'draft',
				'post_title'  => 'About',
			),
			42
		);

		$this->assertSame( 'publish', $guarded['post_status'] );
		$this->assertSame( 'About', $guarded['post_title'] );
	}

	/** And a club page an owner has switched off is not published from the list either. */
	public function test_a_bulk_status_change_cannot_switch_a_club_page_back_on(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		update_option( 'clubhouse_visibility', array( 'pages' => array( 'about' => false ) ) );

		$guarded = Blueworx_Clubhouse_Wordpress_Pages::guard_status(
			array( 'post_status' => 'publish' ),
			42
		);

		$this->assertSame( 'draft', $guarded['post_status'] );
	}

	/** The same bulk change on an ordinary page takes effect, untouched. */
	public function test_a_bulk_status_change_on_an_ordinary_page_still_works(): void {
		$given   = array(
			'post_status' => 'draft',
			'post_title'  => 'Sponsors',
		);
		$guarded = Blueworx_Clubhouse_Wordpress_Pages::guard_status( $given, 999 );

		$this->assertSame( $given, $guarded );
	}

	/** Home is a club page too, and its slug is '' — never a truthiness check. */
	public function test_home_cannot_have_its_status_changed_from_the_list(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 52 );

		$guarded = Blueworx_Clubhouse_Wordpress_Pages::guard_status( array( 'post_status' => 'draft' ), 52 );

		$this->assertSame( 'publish', $guarded['post_status'] );
	}

	/**
	 * Bulk Edit and Quick Edit both go through wp_update_post(), which runs
	 * every save through wp_insert_post_data. Hooking that is what makes the
	 * guard catch the Pages list rather than only the row actions.
	 */
	public function test_it_hooks_the_filter_every_save_goes_through(): void {
		Blueworx_Clubhouse_Wordpress_Pages::register();

		$filters = array_map( static fn( array $c ): array => $c['args'], wp_stub_calls( 'add_filter' ) );
		$this->assertContains( 'wp_insert_post_data', array_column( $filters, 0 ) );
	}

	/** The submitted array is where a bulk edit's post ID is, not the data. */
	public function test_the_filter_finds_the_page_from_the_submitted_array(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );

		$guarded = Blueworx_Clubhouse_Wordpress_Pages::on_insert_post_data(
			array( 'post_status' => 'draft' ),
			array( 'ID' => 42 )
		);

		$this->assertSame( 'publish', $guarded['post_status'] );
	}
}
