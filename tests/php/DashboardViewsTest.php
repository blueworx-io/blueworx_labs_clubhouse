<?php

use PHPUnit\Framework\TestCase;

final class DashboardViewsTest extends TestCase {

	/** @return array<int,string> */
	private function keys( array $views ): array {
		return array_column( $views, 'key' );
	}

	public function test_the_views_are_in_the_order_the_design_draws_them(): void {
		$this->assertSame(
			array( 'dashboard', 'bookings', 'orders', 'invoices', 'billing', 'plans', 'profile', 'account' ),
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
			array( 'dashboard', 'bookings', 'orders', 'invoices', 'billing', 'plans', 'profile', 'account' ),
			$this->keys( $views )
		);
	}

	public function test_a_club_with_no_shop_is_not_offered_shop_only_views(): void {
		// Orders, Invoices, Plans, Billing and Account are all built from the
		// shop's own blocks, so none of them is offered without it.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, true );
		$this->assertSame( array( 'dashboard', 'bookings' ), $this->keys( $views ) );
	}

	public function test_a_club_with_no_bookings_is_not_offered_bookings(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, false );
		$this->assertNotContains( 'bookings', $this->keys( $views ) );
	}

	public function test_a_club_with_neither_still_has_somewhere_to_land(): void {
		// The welcome pack lives here, and a club that has not set up a shop
		// should still have a member area that greets a member — with nothing
		// else offered, because there is nothing behind it.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$this->assertSame( array( 'dashboard' ), $this->keys( $views ) );
	}

	public function test_account_is_not_offered_with_no_shop_at_all(): void {
		// Every panel it carries is the shop's, so without the shop it would be
		// a nav item leading to an empty screen.
		$views   = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$account = Blueworx_Clubhouse_Dashboard_Views::find( 'account', $views );
		$this->assertNull( $account );
	}

	public function test_side_offers_the_desktop_sidebars_views(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame(
			array( 'dashboard', 'bookings', 'orders', 'invoices', 'plans', 'profile', 'account' ),
			$this->keys( Blueworx_Clubhouse_Dashboard_Views::side( $views ) )
		);
	}

	public function test_side_does_not_offer_billing(): void {
		// Billing is a phone-only grouping — the owner's explicit choice.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertNotContains( 'billing', $this->keys( Blueworx_Clubhouse_Dashboard_Views::side( $views ) ) );
	}

	public function test_bar_offers_the_curated_list_on_a_full_club(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame(
			array( 'dashboard', 'bookings', 'billing', 'profile' ),
			$this->keys( Blueworx_Clubhouse_Dashboard_Views::bar( $views ) )
		);
	}

	public function test_bar_drops_billing_and_account_on_a_club_with_no_shop(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$this->assertSame(
			array( 'dashboard' ),
			$this->keys( Blueworx_Clubhouse_Dashboard_Views::bar( $views ) )
		);
	}

	public function test_bar_does_not_offer_orders_invoices_or_plans(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$bar   = $this->keys( Blueworx_Clubhouse_Dashboard_Views::bar( $views ) );
		$this->assertNotContains( 'orders', $bar );
		$this->assertNotContains( 'invoices', $bar );
		$this->assertNotContains( 'plans', $bar );
	}

	public function test_bar_drops_bookings_when_latepoint_is_absent(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, false );
		$this->assertNotContains( 'bookings', $this->keys( Blueworx_Clubhouse_Dashboard_Views::bar( $views ) ) );
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

	public function test_profile_holds_the_members_own_details_and_a_card_of_ours(): void {
		// Their name, email and password, then whatever the club has chosen to
		// keep about them.
		$views   = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$profile = Blueworx_Clubhouse_Dashboard_Views::find( 'profile', $views );
		$this->assertNotNull( $profile );
		$this->assertSame( array( 'surecart/wordpress-account' ), $profile['blocks'] );
		$this->assertSame( 'profile', $profile['panel'] );
	}

	public function test_account_is_left_with_how_the_member_pays(): void {
		$views   = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$account = Blueworx_Clubhouse_Dashboard_Views::find( 'account', $views );
		$this->assertSame(
			array( 'surecart/customer-billing-details', 'surecart/customer-payment-methods' ),
			$account['blocks']
		);
	}

	public function test_account_keeps_its_key_so_an_old_bookmark_still_lands(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame( 'account', Blueworx_Clubhouse_Dashboard_Views::resolve( 'account', $views ) );
	}

	public function test_the_name_and_password_block_lives_in_exactly_one_view(): void {
		// Two views drawing it would be two places to change the same thing,
		// and SureCart's own form posting from whichever the member found first.
		$holders = array();
		foreach ( Blueworx_Clubhouse_Dashboard_Views::all() as $view ) {
			if ( in_array( 'surecart/wordpress-account', (array) $view['blocks'], true ) ) {
				$holders[] = $view['key'];
			}
		}
		$this->assertSame( array( 'profile' ), $holders );
	}

	public function test_only_profile_declares_a_panel_of_our_own(): void {
		$declared = array();
		foreach ( Blueworx_Clubhouse_Dashboard_Views::all() as $view ) {
			$this->assertIsString( $view['panel'] );
			if ( '' !== $view['panel'] ) {
				$declared[] = $view['key'];
			}
		}
		$this->assertSame( array( 'profile' ), $declared );
	}

	public function test_profile_is_not_offered_with_no_shop_at_all(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$this->assertNull( Blueworx_Clubhouse_Dashboard_Views::find( 'profile', $views ) );
	}
}
