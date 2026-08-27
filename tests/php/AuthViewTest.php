<?php
// tests/php/AuthViewTest.php

use PHPUnit\Framework\TestCase;

/**
 * The two decisions that decide where a member ends up: which account screen
 * renders, and whether a redirect target may be honoured.
 *
 * safe_target() is the security-relevant one. A login page that forwards to
 * whatever ?redirect_to says is an open redirect — a phishing link that starts
 * on the club's real domain — so the off-site cases here are the point of the
 * test, not edge-case padding.
 */
final class AuthViewTest extends TestCase {

	private const HOME = 'https://club.example/';

	protected function tearDown(): void {
		Blueworx_Clubhouse_Auth_View::reset();
	}



	public function test_a_blank_setting_sends_members_to_the_shop_dashboard(): void {
		// A club with a shop has somewhere useful to send someone who has just
		// signed in; the front page is a dead end for them.
		$this->assertSame(
			'https://club.example/customer-dashboard/',
			Blueworx_Clubhouse_Auth_View::post_login_target( '', 'https://club.example/customer-dashboard/' )
		);
	}

	public function test_what_the_owner_typed_always_wins(): void {
		$this->assertSame(
			'/membership/',
			Blueworx_Clubhouse_Auth_View::post_login_target( '/membership/', 'https://club.example/customer-dashboard/' )
		);
	}

	public function test_a_club_with_no_shop_keeps_the_front_page(): void {
		// '' here falls through safe_target() to the home URL, unchanged from
		// how every club without a shop already behaves.
		$this->assertSame( '', Blueworx_Clubhouse_Auth_View::post_login_target( '', '' ) );
		$this->assertSame(
			self::HOME,
			Blueworx_Clubhouse_Auth_View::safe_target( '', Blueworx_Clubhouse_Auth_View::post_login_target( '', '' ), self::HOME )
		);
	}

	public function test_a_setting_of_only_spaces_counts_as_blank(): void {
		$this->assertSame(
			'https://club.example/customer-dashboard/',
			Blueworx_Clubhouse_Auth_View::post_login_target( '   ', 'https://club.example/customer-dashboard/' )
		);
	}

	public function test_where_a_member_was_heading_still_beats_the_dashboard(): void {
		// Signing in to reach a specific page must still land on that page.
		$target = Blueworx_Clubhouse_Auth_View::post_login_target( '', 'https://club.example/customer-dashboard/' );
		$this->assertSame(
			'https://club.example/events/',
			Blueworx_Clubhouse_Auth_View::safe_target( '/events/', $target, self::HOME )
		);
	}

	public function test_requested_path_beats_the_configured_default(): void {
		$this->assertSame(
			'https://club.example/members/',
			Blueworx_Clubhouse_Auth_View::safe_target( '/members/', '/membership/', self::HOME )
		);
	}

	public function test_configured_default_is_used_when_nothing_was_requested(): void {
		$this->assertSame(
			'https://club.example/membership/',
			Blueworx_Clubhouse_Auth_View::safe_target( '', '/membership/', self::HOME )
		);
	}

	public function test_home_is_the_last_resort(): void {
		$this->assertSame( self::HOME, Blueworx_Clubhouse_Auth_View::safe_target( '', '', self::HOME ) );
	}

	public function test_offsite_request_falls_through_to_the_configured_default(): void {
		$this->assertSame(
			'https://club.example/membership/',
			Blueworx_Clubhouse_Auth_View::safe_target( 'https://evil.example/steal', '/membership/', self::HOME )
		);
	}

	public function test_offsite_request_and_no_default_lands_on_home(): void {
		$this->assertSame(
			self::HOME,
			Blueworx_Clubhouse_Auth_View::safe_target( 'https://evil.example/steal', '', self::HOME )
		);
	}

	/**
	 * '//evil.example/x' reads as a path to a "does it start with a slash" check
	 * but navigates off-site. It is the case that check exists to catch.
	 */
	public function test_protocol_relative_target_is_refused(): void {
		$this->assertSame(
			self::HOME,
			Blueworx_Clubhouse_Auth_View::safe_target( '//evil.example/x', '', self::HOME )
		);
	}

	public function test_absolute_url_on_the_same_site_is_kept_as_typed(): void {
		$this->assertSame(
			'https://club.example/members/renew/',
			Blueworx_Clubhouse_Auth_View::safe_target( 'https://club.example/members/renew/', '', self::HOME )
		);
	}

	public function test_javascript_target_is_refused(): void {
		$this->assertSame(
			self::HOME,
			Blueworx_Clubhouse_Auth_View::safe_target( 'javascript:alert(1)', '', self::HOME )
		);
	}

	public function test_nobody_is_signed_in_until_wordpress_says_so(): void {
		// The preview and the unit tests leave the seam unset, and get the
		// signed-out header a first-time visitor sees.
		$state = Blueworx_Clubhouse_Auth_View::state();
		$this->assertSame( '', $state['logged_in'] );
		$this->assertSame( '', $state['logout_url'] );
	}

	public function test_the_session_published_by_wordpress_is_what_the_header_reads(): void {
		Blueworx_Clubhouse_Auth_View::set_state(
			array( 'logged_in' => 'Luke McFarland', 'logout_url' => 'https://club.example/?clubhouse_logout=1' )
		);
		$state = Blueworx_Clubhouse_Auth_View::state();
		$this->assertSame( 'Luke McFarland', $state['logged_in'] );
		$this->assertSame( 'https://club.example/?clubhouse_logout=1', $state['logout_url'] );
	}
}
