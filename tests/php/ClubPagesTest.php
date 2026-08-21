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
		$args = Blueworx_Clubhouse_Club_Pages::desired( 'about', 'About' );
		$this->assertSame( 'page', $args['post_type'] );
		$this->assertSame( 'about', $args['post_name'] );
		$this->assertSame( 'About', $args['post_title'] );
		$this->assertSame( 'publish', $args['post_status'] );
	}

	/**
	 * The member area's page is published like every other club page.
	 *
	 * The page map's 'private' flag means "keep this out of the SEO report",
	 * which is the SEO layer's own job via Page_Map::is_private() — it never
	 * meant "make this a WordPress private post". A private post is filtered
	 * out of WordPress's own page query for anyone without read_private_pages,
	 * which is every ordinary member, so a private member area 404s for exactly
	 * the people it exists for. Publishing widens nothing: the route is already
	 * public, and the page does its own sign-in check, sending a signed-out
	 * visitor to /login/.
	 */
	public function test_the_member_area_page_is_published_like_the_rest(): void {
		$args = Blueworx_Clubhouse_Club_Pages::desired( 'member-dashboard', 'Member area' );
		$this->assertSame( 'publish', $args['post_status'] );
	}

	/**
	 * A page left 'private' by an earlier version of this plugin is republished
	 * in place, not duplicated — otherwise every site upgraded from that version
	 * keeps a member area no member can open.
	 */
	public function test_ensure_republishes_a_page_left_private_by_an_earlier_version(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'member-dashboard' ), 60 );
		$GLOBALS['wp_stub_post_status'][60] = 'private';

		Blueworx_Clubhouse_Club_Pages::ensure();

		$updates = array_values(
			array_filter(
				wp_stub_calls( 'wp_update_post' ),
				static fn( array $call ): bool => 60 === ( $call['args'][0]['ID'] ?? 0 )
			)
		);
		$this->assertCount( 1, $updates, 'the stored page is repaired, not replaced' );
		$this->assertSame( 'publish', $updates[0]['args'][0]['post_status'] );
		$this->assertSame( 60, Blueworx_Clubhouse_Club_Pages::post_id( 'member-dashboard' ) );
	}

	/** A trashed page is still brought back rather than duplicated. */
	public function test_ensure_republishes_a_trashed_page(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$GLOBALS['wp_stub_post_status'][42] = 'trash';

		Blueworx_Clubhouse_Club_Pages::ensure();

		$updates = array_values(
			array_filter(
				wp_stub_calls( 'wp_update_post' ),
				static fn( array $call ): bool => 42 === ( $call['args'][0]['ID'] ?? 0 )
			)
		);
		$this->assertCount( 1, $updates );
		$this->assertSame( 'publish', $updates[0]['args'][0]['post_status'] );
		$this->assertSame( 42, Blueworx_Clubhouse_Club_Pages::post_id( 'about' ) );
	}

	/** A page that is already published is left completely alone. */
	public function test_ensure_leaves_a_published_page_alone(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$GLOBALS['wp_stub_post_status'][42] = 'publish';

		Blueworx_Clubhouse_Club_Pages::ensure();

		$touched = array_filter(
			wp_stub_calls( 'wp_update_post' ),
			static fn( array $call ): bool => 42 === ( $call['args'][0]['ID'] ?? 0 )
		);
		$this->assertCount( 0, $touched );
		$this->assertSame( 42, Blueworx_Clubhouse_Club_Pages::post_id( 'about' ) );
	}

	public function test_the_body_is_left_empty(): void {
		// The club's words stay in the content store and are still edited in
		// Club Pages. A body here would be a second, contradictory copy.
		$this->assertSame( '', Blueworx_Clubhouse_Club_Pages::desired( 'about', 'About' )['post_content'] );
	}

	/**
	 * Home's slug is '' — the front page — so its real page must also be the
	 * site's static front page, or '/' never reaches it. A fresh site (posts on
	 * front, or nothing chosen at all) is switched over automatically.
	 */
	public function test_ensure_makes_home_the_front_page_when_none_is_set(): void {
		Blueworx_Clubhouse_Club_Pages::ensure();

		$home_id = Blueworx_Clubhouse_Club_Pages::post_id( '' );
		$this->assertGreaterThan( 0, $home_id );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( $home_id, get_option( 'page_on_front' ) );
	}

	/**
	 * A club that switched show_on_front to a page of its own is left alone —
	 * the fresh-install branch above must never override a deliberate choice.
	 */
	public function test_ensure_leaves_a_deliberately_chosen_front_page_alone(): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 4242 );
		$GLOBALS['wp_stub_post_status'][4242] = 'publish';

		Blueworx_Clubhouse_Club_Pages::ensure();

		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( 4242, get_option( 'page_on_front' ) );
	}

	/**
	 * page_on_front naming a page that no longer exists (deleted, or never
	 * really there) is treated the same as "nothing chosen" — Home takes over
	 * rather than leaving the site pointed at a dangling id.
	 */
	public function test_ensure_takes_over_when_the_chosen_front_page_no_longer_exists(): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 9999 );

		Blueworx_Clubhouse_Club_Pages::ensure();

		$home_id = Blueworx_Clubhouse_Club_Pages::post_id( '' );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( $home_id, get_option( 'page_on_front' ) );
	}
}
