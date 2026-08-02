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

	public function test_known_views_are_honoured(): void {
		$this->assertSame( 'forgot', Blueworx_Clubhouse_Auth_View::view( 'forgot' ) );
		$this->assertSame( 'reset', Blueworx_Clubhouse_Auth_View::view( 'RESET' ) );
		$this->assertSame( 'signedout', Blueworx_Clubhouse_Auth_View::view( ' signedout ' ) );
	}

	public function test_unknown_or_absent_view_is_the_sign_in_form(): void {
		$this->assertSame( 'signin', Blueworx_Clubhouse_Auth_View::view( 'nonsense' ) );
		$this->assertSame( 'signin', Blueworx_Clubhouse_Auth_View::view( '' ) );
		$this->assertSame( 'signin', Blueworx_Clubhouse_Auth_View::view( null ) );
		$this->assertSame( 'signin', Blueworx_Clubhouse_Auth_View::view( array( 'forgot' ) ) );
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

	public function test_state_defaults_to_a_plain_sign_in_form(): void {
		$state = Blueworx_Clubhouse_Auth_View::state();
		$this->assertSame( 'signin', $state['view'] );
		$this->assertSame( '', $state['error'] );
		$this->assertSame( '', $state['logged_in'] );
	}

	public function test_published_state_is_returned_with_an_unknown_view_normalised(): void {
		Blueworx_Clubhouse_Auth_View::set_state( array( 'view' => 'rubbish', 'error' => 'Nope.' ) );
		$state = Blueworx_Clubhouse_Auth_View::state();
		$this->assertSame( 'signin', $state['view'] );
		$this->assertSame( 'Nope.', $state['error'] );
	}
}
