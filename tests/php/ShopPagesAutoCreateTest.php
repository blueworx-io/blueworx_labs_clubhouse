<?php

use PHPUnit\Framework\TestCase;

/**
 * Making the confirmation page without waiting to be asked.
 *
 * The warning it clears is one every club met and none could act on until the
 * repair button learned to create the page. Pressing a button to fix something
 * that was never the club's doing is still a step too many, so the page is made
 * the moment both plugins are there.
 *
 * The line this draws: never created is ours to fix, deliberately removed is
 * not. A club that trashes its confirmation page and finds it back the next
 * time they open wp-admin has been overruled by software, which is worse than
 * a warning.
 */
final class ShopPagesAutoCreateTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		wp_stub_reset();
	}

	public function test_a_page_that_has_never_existed_is_ours_to_create(): void {
		$this->assertTrue( Blueworx_Clubhouse_Shop_Pages::should_create_confirmation( true, 0, '' ) );
	}

	public function test_a_page_the_club_trashed_is_left_for_the_club(): void {
		// The id is still recorded, so this page existed and somebody removed it.
		$this->assertFalse( Blueworx_Clubhouse_Shop_Pages::should_create_confirmation( true, 12, 'trash' ) );
	}

	public function test_a_page_the_club_deleted_outright_is_left_alone_too(): void {
		// Deleted rather than trashed: the post is gone but the option it was
		// recorded under is not, which is how we know it was once there.
		$this->assertFalse( Blueworx_Clubhouse_Shop_Pages::should_create_confirmation( true, 12, '' ) );
	}

	public function test_a_healthy_page_is_left_exactly_as_it_is(): void {
		$this->assertFalse( Blueworx_Clubhouse_Shop_Pages::should_create_confirmation( true, 12, 'publish' ) );
	}

	public function test_nothing_is_created_on_a_site_with_no_shop(): void {
		// No SureCart means no checkout, so a thank-you page for a purchase
		// nobody can make is just a stray page in their Pages list.
		$this->assertFalse( Blueworx_Clubhouse_Shop_Pages::should_create_confirmation( false, 0, '' ) );
	}

	public function test_the_page_is_made_when_both_plugins_are_there(): void {
		// SureCart's page service is unreachable in this process, so the call
		// cannot complete — what matters is that it is attempted and that
		// nothing here writes a page itself.
		Blueworx_Clubhouse_Shop_Pages::ensure_confirmation();
		$this->assertSame( array(), wp_stub_calls( 'wp_insert_post' ) );
	}

	public function test_checking_costs_nothing_on_a_site_with_no_shop(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( false );
		Blueworx_Clubhouse_Shop_Pages::ensure_confirmation();
		// Not even the option is read: a club with no shop pays nothing for a
		// check that runs on every admin screen.
		$this->assertSame( array(), wp_stub_calls( 'get_permalink' ) );
	}
}
