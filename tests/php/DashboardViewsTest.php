<?php

use PHPUnit\Framework\TestCase;

final class DashboardViewsTest extends TestCase {

	/** @return array<int,string> */
	private function keys( array $views ): array {
		return array_column( $views, 'key' );
	}

	public function test_the_views_are_in_the_order_the_design_draws_them(): void {
		$this->assertSame(
			array( 'dashboard', 'bookings', 'orders', 'invoices', 'plans', 'account' ),
			$this->keys( Blueworx_Clubhouse_Dashboard_Views::all() )
		);
	}

	public function test_every_view_is_fully_described(): void {
		foreach ( Blueworx_Clubhouse_Dashboard_Views::all() as $view ) {
			$this->assertNotSame( '', $view['label'], 'a nav item with no label cannot be clicked' );
			$this->assertNotSame( '', $view['title'] );
			$this->assertNotSame( '', $view['lede'] );
			$this->assertNotSame( '', $view['icon'] );
			$this->assertIsArray( $view['blocks'] );
			$this->assertIsString( $view['shortcode'] );
		}
	}

	public function test_a_club_with_both_plugins_gets_every_view(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame(
			array( 'dashboard', 'bookings', 'orders', 'invoices', 'plans', 'account' ),
			$this->keys( $views )
		);
	}

	public function test_a_club_with_no_shop_is_not_offered_shop_views(): void {
		// A nav item that cannot render is worse than an absent one.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, true );
		$this->assertSame( array( 'dashboard', 'bookings' ), $this->keys( $views ) );
	}

	public function test_a_club_with_no_bookings_is_not_offered_bookings(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, false );
		$this->assertNotContains( 'bookings', $this->keys( $views ) );
	}

	public function test_a_club_with_neither_still_has_somewhere_to_land(): void {
		// The welcome pack lives here, and a club that has not set up a shop
		// should still have a member area that greets a member.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$this->assertSame( array( 'dashboard' ), $this->keys( $views ) );
	}

	public function test_an_address_naming_a_real_view_lands_on_it(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame( 'invoices', Blueworx_Clubhouse_Dashboard_Views::resolve( 'invoices', $views ) );
	}

	public function test_an_empty_address_lands_on_the_dashboard(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame( 'dashboard', Blueworx_Clubhouse_Dashboard_Views::resolve( '', $views ) );
	}

	public function test_a_made_up_address_lands_on_the_dashboard(): void {
		// Rather than an empty frame, or a fatal from a missing key.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame( 'dashboard', Blueworx_Clubhouse_Dashboard_Views::resolve( 'nonsense', $views ) );
		$this->assertSame( 'dashboard', Blueworx_Clubhouse_Dashboard_Views::resolve( '../../etc/passwd', $views ) );
	}

	public function test_an_address_for_a_view_this_club_does_not_have_lands_on_the_dashboard(): void {
		// A bookmark kept from before the club removed a plugin.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$this->assertSame( 'dashboard', Blueworx_Clubhouse_Dashboard_Views::resolve( 'orders', $views ) );
	}

	public function test_find_returns_the_named_view_and_null_for_anything_else(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$found = Blueworx_Clubhouse_Dashboard_Views::find( 'plans', $views );
		$this->assertIsArray( $found );
		$this->assertSame( 'Plans', $found['label'] );
		$this->assertNull( Blueworx_Clubhouse_Dashboard_Views::find( 'nope', $views ) );
	}

	public function test_bookings_is_the_one_view_a_shortcode_fills(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$bookings = Blueworx_Clubhouse_Dashboard_Views::find( 'bookings', $views );
		$this->assertSame( 'latepoint_customer_dashboard', $bookings['shortcode'] );
		$this->assertSame( array(), $bookings['blocks'] );
	}

	public function test_account_holds_both_of_the_shops_account_panels(): void {
		$views   = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$account = Blueworx_Clubhouse_Dashboard_Views::find( 'account', $views );
		$this->assertSame(
			array( 'surecart/customer-billing-details', 'surecart/customer-payment-methods' ),
			$account['blocks']
		);
	}
}
