<?php

use PHPUnit\Framework\TestCase;

/**
 * A club with no shop has no way in, because there is nothing behind the door.
 *
 * Decided 26 August 2026 (issue #261): the member area is SureCart-only. A club
 * running the booking plugin but no shop has no member sign-in — that is the
 * accepted cost of not maintaining a second sign-in beside the shop's own.
 */
final class NoShopNoSignInTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( false );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_Auth_View::reset();
		wp_stub_reset();
	}

	/** @return array<int,string> */
	private function slugs(): array {
		return array_column( Blueworx_Clubhouse_Page_Map::available(), 'slug' );
	}

	public function test_a_club_with_no_shop_serves_no_login_page(): void {
		$this->assertNotContains( 'login', $this->slugs() );
		$this->assertFalse( Blueworx_Clubhouse_Page_Map::is_available( 'login' ) );
	}

	public function test_a_club_with_no_shop_serves_no_member_area(): void {
		$this->assertNotContains( 'member-dashboard', $this->slugs() );
	}

	public function test_the_pages_a_club_site_is_actually_about_are_untouched(): void {
		// Only the two members-only pages go. A club with no shop is still a
		// club site.
		foreach ( array( '', 'about', 'membership', 'contact', 'news', 'sports', 'teams', 'events' ) as $slug ) {
			$this->assertContains( $slug, $this->slugs(), $slug . ' has nothing to do with a shop' );
		}
	}

	public function test_a_shop_brings_both_pages_back(): void {
		// Nothing is thrown away — a club that adds a shop next month gets its
		// login page and member area with no further step.
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		$this->assertContains( 'login', $this->slugs() );
		$this->assertContains( 'member-dashboard', $this->slugs() );
	}

	public function test_the_header_offers_no_way_in_that_goes_nowhere(): void {
		// A "Log in" button on a site with no login page is a link into a 404.
		$this->assertSame( array( '', '' ), Blueworx_Clubhouse_Page_Renderer::header_account( false, false, '', false ) );
	}

	public function test_somebody_signed_in_still_gets_their_way_out(): void {
		// Staff arriving from wp-admin. Signing out is not signing in, and a
		// signed-in person with no way out is worse than no button at all.
		$this->assertSame(
			array( 'Log out', 'https://club.test/?clubhouse_logout=1' ),
			Blueworx_Clubhouse_Page_Renderer::header_account( true, false, 'https://club.test/?clubhouse_logout=1', false )
		);
	}

	public function test_the_rendered_header_carries_no_account_button(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name' => 'Crewe Vagrants', 'banner' => '', 'banner_href' => '',
			'nav' => array(), 'active' => '',
			'login' => '', 'login_href' => '',
			'join' => 'Join', 'join_href' => '/membership/',
		) );
		$this->assertStringNotContainsString( 'ch-nav__drawer-login', $html );
		$this->assertStringNotContainsString( 'Log in', $html );
		// The rest of the header is still a header.
		$this->assertStringContainsString( 'Join', $html );
		$this->assertStringContainsString( 'Skip to content', $html );
	}
}
