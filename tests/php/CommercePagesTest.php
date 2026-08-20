<?php

use PHPUnit\Framework\TestCase;

final class CommercePagesTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	protected function tearDown(): void {
		wp_stub_reset();
	}

	/** Put the request on the shop's checkout page, as WordPress renders it. */
	private function on_the_checkout_page(): void {
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( 'checkout' ), 43 );
		wp_stub_render_page( 43 );
	}

	public function test_a_guest_buyer_is_not_turned_away(): void {
		// Unlike the member area, nobody has to be signed in here: a club sells
		// to guests, and paying without an account is an ordinary sale.
		$this->on_the_checkout_page();
		$GLOBALS['wp_stub_logged_in'] = false;
		$html                         = Blueworx_Clubhouse_Commerce_Pages::dress( '<form id="sc-checkout"></form>' );
		$this->assertStringContainsString( '<form id="sc-checkout"></form>', $html );
		$this->assertStringContainsString( 'bw-admin', $html );
	}

	public function test_the_checkout_is_never_left_to_render_unstyled(): void {
		$this->on_the_checkout_page();
		Blueworx_Clubhouse_Commerce_Pages::dress( '<form></form>' );
		$handles = array_map(
			static fn ( array $c ): string => (string) ( $c['args'][0] ?? '' ),
			wp_stub_calls( 'wp_enqueue_style' )
		);
		$this->assertContains( Blueworx_Clubhouse_Dashboard_Assets::handle(), $handles );
	}

	public function test_the_guard_against_recursion_lifts_again_after_each_render(): void {
		// The checkout renders the shop's blocks, and any of them may apply
		// the_content itself, which unguarded recurses until the request runs
		// out of memory. The guard must not outlive the render that set it, or
		// the second page a request draws arrives undressed.
		$this->on_the_checkout_page();
		Blueworx_Clubhouse_Commerce_Pages::dress( '<form></form>' );
		$this->assertStringContainsString( 'bw-admin', Blueworx_Clubhouse_Commerce_Pages::dress( '<form></form>' ) );
	}

	public function test_a_page_that_is_neither_is_left_alone(): void {
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( 'checkout' ), 43 );
		wp_stub_render_page( 99 );
		$this->assertSame( '<p>a news post</p>', Blueworx_Clubhouse_Commerce_Pages::dress( '<p>a news post</p>' ) );
	}

	public function test_the_checkout_page_is_recognised(): void {
		$this->assertSame( 'checkout', Blueworx_Clubhouse_Commerce_Pages::page_key( 12, 12, 34 ) );
	}

	public function test_the_confirmation_page_is_recognised(): void {
		$this->assertSame( 'order-confirmation', Blueworx_Clubhouse_Commerce_Pages::page_key( 34, 12, 34 ) );
	}

	public function test_any_other_page_is_left_alone(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Commerce_Pages::page_key( 99, 12, 34 ) );
	}

	public function test_a_shop_with_no_pages_set_up_dresses_nothing(): void {
		// 0 means "no page recorded". Without this, every page on the site whose
		// id happened to be 0 — and any post on a broken query — would be dressed.
		$this->assertSame( '', Blueworx_Clubhouse_Commerce_Pages::page_key( 0, 0, 0 ) );
	}

	public function test_both_pages_are_described(): void {
		foreach ( Blueworx_Clubhouse_Commerce_Pages::PAGES as $key => $page ) {
			$this->assertNotSame( '', $page['title'], $key . ' has no title' );
			$this->assertNotSame( '', $page['lede'], $key . ' has no lede' );
		}
		$this->assertSame( array( 'checkout', 'order-confirmation' ), array_keys( Blueworx_Clubhouse_Commerce_Pages::PAGES ) );
	}

	public function test_the_page_keeps_its_own_content_inside_our_frame(): void {
		// We do not build checkouts. The shop's form is rendered by the shop and
		// passed through untouched.
		$html = Blueworx_Clubhouse_Dashboard_Shell::bare(
			Blueworx_Clubhouse_Commerce_Pages::PAGES['checkout']['title'],
			Blueworx_Clubhouse_Commerce_Pages::PAGES['checkout']['lede'],
			'<form id="sc-checkout"></form>',
			'https://club.test/',
			'Club'
		);
		$this->assertStringContainsString( '<form id="sc-checkout"></form>', $html );
		$this->assertStringNotContainsString( 'bw-secnav', $html );
	}

	public function test_the_footer_offers_only_pages_the_club_has_switched_on(): void {
		// A dead link on a payment page is the worst place for one. Terms and
		// privacy are switchable like every other club page, so the footer has
		// to ask rather than assume.
		$visible = static fn ( string $slug ): bool => 'privacy' === $slug;
		$url     = static fn ( string $slug ): string => 'https://club.test/' . $slug . '/';

		$links = Blueworx_Clubhouse_Commerce_Pages::footer_links( $visible, $url );

		$this->assertSame(
			array( array( 'label' => 'Privacy notice', 'href' => 'https://club.test/privacy/' ) ),
			$links
		);
	}

	public function test_a_club_with_nothing_switched_on_gets_no_links(): void {
		$this->assertSame(
			array(),
			Blueworx_Clubhouse_Commerce_Pages::footer_links(
				static fn ( string $slug ): bool => false,
				static fn ( string $slug ): string => 'https://club.test/' . $slug . '/'
			)
		);
	}

	public function test_checkout_gets_the_checkout_frame_and_confirmation_keeps_the_bare_one(): void {
		// Two different pages with two different jobs. The confirmation page is
		// a receipt, so it keeps the heading-and-panel shell it has always had.
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( 'checkout' ), 7 );
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( 'order-confirmation' ), 9 );

		wp_stub_render_page( 7 );
		$checkout = Blueworx_Clubhouse_Commerce_Pages::dress( '<form></form>' );
		$this->assertStringContainsString( 'clubhouse-checkout', $checkout );
		$this->assertStringNotContainsString( 'bw-pagehead', $checkout );

		wp_stub_render_page( 9 );
		$confirmation = Blueworx_Clubhouse_Commerce_Pages::dress( '<p>Thank you</p>' );
		$this->assertStringContainsString( 'bw-pagehead', $confirmation );
		$this->assertStringNotContainsString( 'clubhouse-checkout', $confirmation );
	}

	public function test_the_way_back_names_the_club_when_it_has_a_name(): void {
		$this->assertSame( 'Back to Crewe Vagrants', Blueworx_Clubhouse_Commerce_Pages::back_label( 'Crewe Vagrants' ) );
		$this->assertSame( 'Back to the club site', Blueworx_Clubhouse_Commerce_Pages::back_label( '  ' ) );
	}
}
