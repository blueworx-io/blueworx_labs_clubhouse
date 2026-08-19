<?php

use PHPUnit\Framework\TestCase;

final class MemberDashboardTest extends TestCase {

	private const PAGE = 42;

	protected function setUp(): void {
		wp_stub_reset();
		unset( $_GET['view'] );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources( null, null );
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		wp_stub_reset();
		unset( $_GET['view'] );
	}

	/** Put the request on the club's account page of a club with a shop, as WordPress renders it. */
	private function on_the_account_page(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( 'dashboard' ), self::PAGE );
		wp_stub_render_page( self::PAGE );
		$GLOBALS['wp_stub_permalinks'][ self::PAGE ] = 'https://club.test/?page_id=' . self::PAGE;
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

	public function test_the_quick_links_are_built_on_the_pages_own_address(): void {
		// On a club with plain permalinks the account page is '?page_id=42', and
		// a bare '?view=orders' would drop the page and land on the front page.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '', $views, 'https://club.test/', 'https://club.test/?page_id=42' );
		$this->assertStringContainsString( 'https://club.test/?page_id=42&amp;view=orders', $html );
	}

	public function test_a_visitor_who_is_not_signed_in_keeps_the_shops_own_sign_in_form(): void {
		// The page's own content is SureCart's dashboard block, which shows a
		// sign-in form to a stranger. Our frame would tell them the club had set
		// nothing up, which is not true.
		$this->on_the_account_page();
		$GLOBALS['wp_stub_logged_in'] = false;
		$this->assertSame( '[sign-in form]', Blueworx_Clubhouse_Member_Dashboard::take_over( '[sign-in form]' ) );
	}

	public function test_a_signed_in_member_gets_the_member_area_and_a_way_out(): void {
		$this->on_the_account_page();
		$GLOBALS['wp_stub_logged_in'] = true;
		$html                         = Blueworx_Clubhouse_Member_Dashboard::take_over( '[sign-in form]' );
		$this->assertStringContainsString( 'bw-admin', $html );
		$this->assertStringNotContainsString( '[sign-in form]', $html );
		$this->assertStringContainsString( 'Sign out', $html );
		$this->assertStringContainsString( 'clubhouse_logout=1', $html );
		// And every nav link keeps the page it is on.
		$this->assertStringContainsString( 'page_id=42&amp;view=', $html );
	}

	public function test_the_welcome_packs_own_rules_are_only_printed_where_a_pack_is_drawn(): void {
		$this->on_the_account_page();
		$GLOBALS['wp_stub_logged_in'] = true;

		// No pack written: nothing to style, so nothing is printed.
		$this->assertStringNotContainsString( '<style>', Blueworx_Clubhouse_Member_Dashboard::take_over( '' ) );

		$store = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Options_Storage() );
		$store->set( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION, 'body', 'Park behind the clubhouse.' );
		$this->assertStringContainsString( '<style>', Blueworx_Clubhouse_Member_Dashboard::take_over( '' ) );

		// And not on a view that draws no pack at all.
		$_GET['view'] = 'orders';
		$this->assertStringNotContainsString( '<style>', Blueworx_Clubhouse_Member_Dashboard::take_over( '' ) );
	}

	public function test_the_member_areas_stylesheet_is_asked_for_while_the_page_is_drawn(): void {
		// A second belt: the early enqueue on wp_enqueue_scripts is what stops
		// the flash of unstyled page, and this proves the filter still asks too.
		$this->on_the_account_page();
		$GLOBALS['wp_stub_logged_in'] = true;
		Blueworx_Clubhouse_Member_Dashboard::take_over( '' );
		$handles = array_map( static fn ( array $c ): string => (string) ( $c['args'][0] ?? '' ), wp_stub_calls( 'wp_enqueue_style' ) );
		$this->assertContains( Blueworx_Clubhouse_Dashboard_Assets::handle(), $handles );
	}

	public function test_a_plugin_panel_that_renders_the_page_again_cannot_recurse(): void {
		// Anything we render is free to apply the_content itself. Unguarded,
		// that comes straight back in here and recurses until the request runs
		// out of memory — a white screen on the member's account page.
		$this->on_the_account_page();
		$GLOBALS['wp_stub_logged_in'] = true;
		$_GET['view']                 = 'orders';
		$inner                        = null;
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static function ( string $n ) use ( &$inner ): ?string {
				$inner = Blueworx_Clubhouse_Member_Dashboard::take_over( '[inner]' );
				return '<div data-block="' . $n . '">panel</div>';
			},
			null
		);
		$html = Blueworx_Clubhouse_Member_Dashboard::take_over( '' );
		$this->assertSame( '[inner]', $inner, 'the second pass must hand its content straight back' );
		$this->assertStringContainsString( 'data-block="surecart/customer-orders"', $html );
	}

	public function test_an_overview_with_neither_pack_nor_views_shows_the_honest_empty_state(): void {
		// No pack written, and no other views to link to — e.g. a club whose
		// shop plugin is inactive. A member must never meet a blank frame.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '', $views, 'https://club.test/' );
		$this->assertNotSame( '', trim( $html ) );
		$this->assertStringContainsString( 'bw-empty', $html );
		$this->assertStringContainsString( 'href="https://club.test/"', $html );
	}
}
