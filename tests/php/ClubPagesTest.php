<?php

use PHPUnit\Framework\TestCase;

/**
 * The WordPress page behind each club page.
 *
 * Club pages have been rewrite-rule routes with nothing in the database behind
 * them. That cost the site everything WordPress gives a real page — the
 * sitemap, canonicals, search, and anything an SEO plugin would do. These
 * assert the mapping only; serving from it is a later task.
 */
final class ClubPagesTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_the_option_key_is_stable_and_slug_scoped(): void {
		// Stored per page, so one missing page never hides another.
		$this->assertSame( 'clubhouse_page_id_about', Blueworx_Clubhouse_Club_Pages::option_name( 'about' ) );
	}

	public function test_home_has_a_key_of_its_own_despite_an_empty_slug(): void {
		// Home's slug is '' — the front page. Without this it would collide with
		// every other empty lookup and the front page would point at nothing.
		$this->assertSame( 'clubhouse_page_id_home', Blueworx_Clubhouse_Club_Pages::option_name( '' ) );
	}

	public function test_a_page_that_is_not_ours_maps_to_nothing(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Club_Pages::slug_for( 999 ) );
		$this->assertFalse( Blueworx_Clubhouse_Club_Pages::is_club_page( 999 ) );
		$this->assertFalse( Blueworx_Clubhouse_Club_Pages::is_club_page( 0 ) );
	}

	public function test_a_stored_page_maps_both_ways(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$this->assertSame( 42, Blueworx_Clubhouse_Club_Pages::post_id( 'about' ) );
		$this->assertSame( 'about', Blueworx_Clubhouse_Club_Pages::slug_for( 42 ) );
		$this->assertTrue( Blueworx_Clubhouse_Club_Pages::is_club_page( 42 ) );
	}

	public function test_home_maps_back_to_an_empty_slug(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 7 );
		$this->assertSame( '', Blueworx_Clubhouse_Club_Pages::slug_for( 7 ) );
		$this->assertTrue( Blueworx_Clubhouse_Club_Pages::is_club_page( 7 ) );
	}

	public function test_the_page_args_carry_the_right_slug_title_and_status(): void {
		$args = Blueworx_Clubhouse_Club_Pages::desired( 'about', 'About', false );
		$this->assertSame( 'page', $args['post_type'] );
		$this->assertSame( 'about', $args['post_name'] );
		$this->assertSame( 'About', $args['post_title'] );
		$this->assertSame( 'publish', $args['post_status'] );
	}

	public function test_the_member_area_page_is_never_public(): void {
		// Nothing on it is for a signed-out visitor, and a published page would
		// put it in the sitemap and in search results.
		$args = Blueworx_Clubhouse_Club_Pages::desired( 'member-dashboard', 'Member area', true );
		$this->assertSame( 'private', $args['post_status'] );
	}

	public function test_the_body_is_left_empty(): void {
		// The club's words stay in the content store and are still edited in
		// Club Pages. A body here would be a second, contradictory copy.
		$this->assertSame( '', Blueworx_Clubhouse_Club_Pages::desired( 'about', 'About', false )['post_content'] );
	}
}
