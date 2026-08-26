<?php

use PHPUnit\Framework\TestCase;

/** Which member-area addresses mean "do something" rather than "show me". */
final class DashboardActionsTest extends TestCase {

	/** A shop where every controller and method SureCart ships is present. */
	private function everything(): callable {
		return static fn ( string $controller, string $action ): bool => true;
	}

	/** A shop that is not installed at all. */
	private function nothing(): callable {
		return static fn ( string $controller, string $action ): bool => false;
	}

	public function test_updating_billing_details_is_an_action(): void {
		// The address from the bug report.
		$this->assertTrue( Blueworx_Clubhouse_Dashboard_Actions::is_action( 'customer', 'edit', $this->everything() ) );
	}

	public function test_editing_your_own_account_is_an_action(): void {
		$this->assertTrue( Blueworx_Clubhouse_Dashboard_Actions::is_action( 'user', 'edit', $this->everything() ) );
	}

	public function test_every_model_surecart_routes_is_carried(): void {
		$models = array( 'subscription', 'payment_method', 'charge', 'order', 'user', 'customer', 'download', 'invoice', 'license' );
		foreach ( $models as $model ) {
			$this->assertTrue(
				Blueworx_Clubhouse_Dashboard_Actions::is_action( $model, 'index', $this->everything() ),
				$model . ' is one of the shop own models and must route'
			);
		}
	}

	public function test_a_plain_panel_address_is_not_an_action(): void {
		$this->assertFalse( Blueworx_Clubhouse_Dashboard_Actions::is_action( '', '', $this->everything() ) );
		$this->assertFalse( Blueworx_Clubhouse_Dashboard_Actions::is_action( 'customer', '', $this->everything() ) );
		$this->assertFalse( Blueworx_Clubhouse_Dashboard_Actions::is_action( '', 'edit', $this->everything() ) );
	}

	public function test_a_model_surecart_does_not_know_is_not_an_action(): void {
		// An old bookmark or a hand-typed address draws the normal panels
		// rather than an empty screen.
		$this->assertFalse( Blueworx_Clubhouse_Dashboard_Actions::is_action( 'nonsense', 'edit', $this->everything() ) );
	}

	public function test_an_action_the_controller_cannot_do_is_not_an_action(): void {
		// Exactly the shop own guard: it dispatches only where the controller
		// has a method by that name, so a made-up action routes nowhere.
		$this->assertFalse( Blueworx_Clubhouse_Dashboard_Actions::is_action( 'customer', 'edit', $this->nothing() ) );
	}

	public function test_an_action_keeps_the_member_on_the_right_panel(): void {
		// SureCart's links drop our view arg, so without this a member pressing
		// Update on their billing details would watch the nav jump to Dashboard.
		$this->assertSame( 'account', Blueworx_Clubhouse_Dashboard_Actions::view_for( 'customer' ) );
		$this->assertSame( 'account', Blueworx_Clubhouse_Dashboard_Actions::view_for( 'user' ) );
		$this->assertSame( 'account', Blueworx_Clubhouse_Dashboard_Actions::view_for( 'payment_method' ) );
		$this->assertSame( 'plans', Blueworx_Clubhouse_Dashboard_Actions::view_for( 'subscription' ) );
		$this->assertSame( 'orders', Blueworx_Clubhouse_Dashboard_Actions::view_for( 'order' ) );
		$this->assertSame( 'invoices', Blueworx_Clubhouse_Dashboard_Actions::view_for( 'invoice' ) );
	}

	public function test_a_model_with_no_panel_of_ours_names_none(): void {
		// Downloads and licences: no club sells either, and the member area
		// offers no panel for them.
		$this->assertSame( '', Blueworx_Clubhouse_Dashboard_Actions::view_for( 'download' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Dashboard_Actions::view_for( 'license' ) );
	}
}
