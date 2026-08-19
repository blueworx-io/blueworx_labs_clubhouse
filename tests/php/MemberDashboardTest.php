<?php

use PHPUnit\Framework\TestCase;

final class MemberDashboardTest extends TestCase {

	protected function tearDown(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources( null, null );
	}

	/** Both plugins present and answering. */
	private function everything_installed(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static fn ( string $n ): ?string => '<div data-block="' . $n . '">panel</div>',
			static fn ( string $t ): ?string => '<div data-shortcode="' . $t . '">bookings</div>'
		);
	}

	/** @return array<string,mixed> */
	private function view( string $key ): array {
		$found = Blueworx_Clubhouse_Dashboard_Views::find( $key, Blueworx_Clubhouse_Dashboard_Views::all() );
		$this->assertIsArray( $found );
		return $found;
	}

	public function test_a_block_view_renders_the_shops_own_panel(): void {
		$this->everything_installed();
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body( $this->view( 'orders' ), '', 'https://club.test/' );
		$this->assertStringContainsString( 'data-block="surecart/customer-orders"', $html );
	}

	public function test_a_view_with_two_panels_renders_both_in_order(): void {
		$this->everything_installed();
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body( $this->view( 'account' ), '', 'https://club.test/' );
		$billing = strpos( $html, 'surecart/customer-billing-details' );
		$cards   = strpos( $html, 'surecart/customer-payment-methods' );
		$this->assertIsInt( $billing );
		$this->assertIsInt( $cards );
		$this->assertLessThan( $cards, $billing );
	}

	public function test_the_bookings_view_hands_the_whole_panel_to_the_booking_plugin(): void {
		// LatePoint brings its own tabs; boxing them inside a card of ours
		// would be two sets of tabs on one screen.
		$this->everything_installed();
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body( $this->view( 'bookings' ), '', 'https://club.test/' );
		$this->assertStringContainsString( 'data-shortcode="latepoint_customer_dashboard"', $html );
		$this->assertStringNotContainsString( 'bw-card__head', $html );
	}

	public function test_a_panel_whose_plugin_says_nothing_shows_the_honest_empty_state(): void {
		// Never a blank frame.
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body( $this->view( 'orders' ), '', 'https://club.test/' );
		$this->assertStringContainsString( 'bw-empty', $html );
		$this->assertStringContainsString( 'href="https://club.test/"', $html );
	}

	public function test_the_overview_greets_a_member_with_the_clubs_welcome_pack(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '<section class="clubhouse-welcome">hi</section>', $views, 'https://club.test/' );
		$this->assertStringContainsString( 'clubhouse-welcome', $html );
	}

	public function test_the_welcome_pack_comes_before_anything_else_on_the_overview(): void {
		// It greets a member, so it goes where a greeting goes.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '<section class="clubhouse-welcome">hi</section>', $views, 'https://club.test/' );
		$this->assertLessThan( strpos( $html, '?view=orders' ), strpos( $html, 'clubhouse-welcome' ) );
	}

	public function test_the_overview_links_into_every_other_view(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '', $views, 'https://club.test/' );
		foreach ( array( 'bookings', 'orders', 'invoices', 'plans', 'account' ) as $key ) {
			$this->assertStringContainsString( '?view=' . $key, $html );
		}
		// Not to itself: a link to the page you are on is a dead control.
		$this->assertStringNotContainsString( '?view=dashboard', $html );
	}

	public function test_an_overview_with_nothing_to_link_to_still_says_something(): void {
		// A club with neither plugin: the pack, and no empty grid of links.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '<section class="clubhouse-welcome">hi</section>', $views, 'https://club.test/' );
		$this->assertStringContainsString( 'clubhouse-welcome', $html );
		$this->assertStringNotContainsString( '?view=', $html );
	}

	public function test_an_overview_with_no_pack_written_is_not_an_empty_page(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '', $views, 'https://club.test/' );
		$this->assertStringContainsString( '?view=orders', $html );
		$this->assertNotSame( '', trim( $html ) );
	}
}
