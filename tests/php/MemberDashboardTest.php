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

	public function test_a_view_with_several_panels_renders_them_all_in_order(): void {
		$this->everything_installed();
		$html    = Blueworx_Clubhouse_Member_Dashboard::view_body( $this->view( 'account' ), '', 'https://club.test/' );
		$account = strpos( $html, 'surecart/wordpress-account' );
		$billing = strpos( $html, 'surecart/customer-billing-details' );
		$cards   = strpos( $html, 'surecart/customer-payment-methods' );
		$this->assertIsInt( $account );
		$this->assertIsInt( $billing );
		$this->assertIsInt( $cards );
		$this->assertLessThan( $billing, $account );
		$this->assertLessThan( $cards, $billing );
	}

	public function test_a_member_can_see_their_own_name_and_sign_in_email(): void {
		// It used to be missing entirely: the account view showed the details
		// the shop keeps for billing and nothing about the member themselves,
		// so there was no way in to changing a name, an email or a password.
		$this->everything_installed();
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body( $this->view( 'account' ), '', 'https://club.test/' );
		$this->assertStringContainsString( 'data-block="surecart/wordpress-account"', $html );
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
		// Only the dashboard itself on offer: the pack, and no empty grid of
		// links. (available() no longer produces this shape on its own — Billing
		// and Account are offered even with no shop at all, see Dashboard_Views.)
		$views = array( array( 'key' => 'dashboard', 'label' => 'Dashboard', 'title' => '', 'lede' => '', 'icon' => 'layout-dashboard' ) );
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

	public function test_a_signed_in_member_gets_the_member_area_and_a_way_out(): void {
		$this->on_the_account_page();
		$GLOBALS['wp_stub_logged_in'] = true;
		$html                         = Blueworx_Clubhouse_Member_Dashboard::screen( '/member-dashboard/', '/' );
		$this->assertStringContainsString( 'bw-admin', $html );
		$this->assertStringContainsString( 'Sign out', $html );
		$this->assertStringContainsString( 'clubhouse_logout=1', $html );
		// And every nav link keeps the page it is on.
		$this->assertStringContainsString( '/member-dashboard/?view=', $html );
	}

	public function test_the_welcome_packs_own_rules_are_only_printed_where_a_pack_is_drawn(): void {
		$this->on_the_account_page();
		$GLOBALS['wp_stub_logged_in'] = true;

		// No pack written: nothing to style, so nothing is printed.
		$this->assertStringNotContainsString( '<style>', Blueworx_Clubhouse_Member_Dashboard::screen( '/member-dashboard/', '/' ) );

		$store = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Options_Storage() );
		$store->set( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION, 'body', 'Park behind the clubhouse.' );
		$this->assertStringContainsString( '<style>', Blueworx_Clubhouse_Member_Dashboard::screen( '/member-dashboard/', '/' ) );

		// screen() now renders every panel into the page, hidden but present, so
		// the overview's pack — and its style block — is on the page even while
		// a different view is the one being read.
		$_GET['view'] = 'orders';
		$this->assertStringContainsString( '<style>', Blueworx_Clubhouse_Member_Dashboard::screen( '/member-dashboard/', '/' ) );
	}

	public function test_an_overview_with_neither_pack_nor_views_shows_the_honest_empty_state(): void {
		// No pack written, and no other views to link to. A member must never
		// meet a blank frame. (available() no longer produces this shape on its
		// own — Billing and Account are offered even with no shop at all.)
		$views = array( array( 'key' => 'dashboard', 'label' => 'Dashboard', 'title' => '', 'lede' => '', 'icon' => 'layout-dashboard' ) );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '', $views, 'https://club.test/' );
		$this->assertNotSame( '', trim( $html ) );
		$this->assertStringContainsString( 'bw-empty', $html );
		$this->assertStringContainsString( 'href="https://club.test/"', $html );
	}

	/** The frame, addressed at a URL of ours, with no WordPress page under it. */
	public function test_screen_renders_the_frame_at_a_given_address(): void {
		$html = Blueworx_Clubhouse_Member_Dashboard::screen( '/member-dashboard/', '/' );
		$this->assertStringContainsString( 'bw-admin', $html );
		$this->assertStringContainsString( '/member-dashboard/', $html );
	}

	private function redirect( array $over = array() ): string {
		$args = array_merge(
			array(
				'queried'    => 0,
				'dashboard'  => 0,
				'on_member'  => false,
				'serving'    => true,
				'signed_in'  => false,
				'view'       => '',
				'member_url' => '/member-dashboard/',
				'login_url'  => '/login/',
			),
			$over
		);
		return Blueworx_Clubhouse_Member_Dashboard::redirect_to(
			$args['queried'],
			$args['dashboard'],
			$args['on_member'],
			$args['serving'],
			$args['signed_in'],
			$args['view'],
			$args['member_url'],
			$args['login_url']
		);
	}

	public function test_the_shops_old_account_page_is_carried_across(): void {
		$this->assertSame( '/member-dashboard/', $this->redirect( array( 'queried' => 42, 'dashboard' => 42 ) ) );
	}

	public function test_the_panel_asked_for_is_carried_across_with_it(): void {
		$this->assertSame(
			'/member-dashboard/?view=orders',
			$this->redirect( array( 'queried' => 42, 'dashboard' => 42, 'view' => 'orders' ) )
		);
	}

	public function test_every_other_page_is_left_alone(): void {
		$this->assertSame( '', $this->redirect( array( 'queried' => 7, 'dashboard' => 42 ) ) );
		$this->assertSame( '', $this->redirect( array( 'queried' => 0, 'dashboard' => 0 ) ) );
		// A shop with no dashboard page recorded must not swallow post id 0.
		$this->assertSame( '', $this->redirect( array( 'queried' => 0, 'dashboard' => 0, 'view' => 'orders' ) ) );
	}

	public function test_a_signed_out_visitor_to_the_member_area_is_sent_to_log_in(): void {
		$this->assertSame( '/login/', $this->redirect( array( 'on_member' => true ) ) );
	}

	public function test_a_signed_in_member_stays_on_the_member_area(): void {
		$this->assertSame( '', $this->redirect( array( 'on_member' => true, 'signed_in' => true ) ) );
	}

	/** Nothing is worth redirecting to an address we could not build. */
	public function test_no_redirect_when_the_target_address_is_unknown(): void {
		$this->assertSame( '', $this->redirect( array( 'queried' => 42, 'dashboard' => 42, 'member_url' => '' ) ) );
		$this->assertSame( '', $this->redirect( array( 'on_member' => true, 'login_url' => '' ) ) );
	}

	/**
	 * A club that has switched the member area off must not have SureCart's own
	 * account page redirected into it — that page would 404, taking the shop's
	 * account links, every member's bookmark, and the post-login default with it.
	 */
	public function test_the_shops_old_account_page_is_left_alone_when_the_member_area_is_off(): void {
		$this->assertSame(
			'',
			$this->redirect( array( 'queried' => 42, 'dashboard' => 42, 'serving' => false ) )
		);
		$this->assertSame(
			'',
			$this->redirect( array( 'queried' => 42, 'dashboard' => 42, 'view' => 'orders', 'serving' => false ) )
		);
	}

	/** With the member area on, the existing behaviour is unchanged. */
	public function test_the_shops_old_account_page_still_carries_across_when_the_member_area_is_on(): void {
		$this->assertSame(
			'/member-dashboard/',
			$this->redirect( array( 'queried' => 42, 'dashboard' => 42, 'serving' => true ) )
		);
	}

	/**
	 * Billing is the phone's one money screen: it carries the subscriptions,
	 * orders and invoices panels itself, and no link rows to the sidebar-only
	 * views hang off it — or off any other panel.
	 */
	public function test_billing_carries_the_money_panels_and_no_link_rows(): void {
		$this->on_the_account_page();
		$this->everything_installed();
		$html = Blueworx_Clubhouse_Member_Dashboard::screen( '/member-dashboard/', 'https://club.test/' );
		$this->assertStringNotContainsString( 'clubhouse-member__more', $html );

		$billing = Blueworx_Clubhouse_Dashboard_Views::find( 'billing', Blueworx_Clubhouse_Dashboard_Views::all() );
		$this->assertSame(
			array( 'surecart/customer-subscriptions', 'surecart/customer-orders', 'surecart/customer-invoices' ),
			$billing['blocks']
		);
	}

	/** Store a club's brand marks the way the setup screen does. */
	private function brand_marks( string $logo, string $favicon ): void {
		update_option( 'clubhouse_branding', array( 'logo' => $logo, 'favicon' => $favicon ) );
	}

	public function test_the_favicon_is_the_brand_mark_when_one_is_set(): void {
		$this->on_the_account_page();
		$this->everything_installed();
		$this->brand_marks( 'https://club.test/logo.png', 'https://club.test/icon.png' );
		$html = Blueworx_Clubhouse_Member_Dashboard::screen( '/member-dashboard/', 'https://club.test/' );
		$this->assertStringContainsString( 'https://club.test/icon.png', $html );
		$this->assertStringNotContainsString( 'https://club.test/logo.png', $html );
	}

	public function test_the_logo_is_the_brand_mark_when_no_favicon_is_set(): void {
		$this->on_the_account_page();
		$this->everything_installed();
		$this->brand_marks( 'https://club.test/logo.png', '' );
		$html = Blueworx_Clubhouse_Member_Dashboard::screen( '/member-dashboard/', 'https://club.test/' );
		$this->assertStringContainsString( 'https://club.test/logo.png', $html );
	}
}
