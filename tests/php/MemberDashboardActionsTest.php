<?php

use PHPUnit\Framework\TestCase;

/**
 * The member area's write journeys.
 *
 * The bug: every link the shop draws inside the member area — Update billing
 * details, Add a card, Payment History, cancel a plan, open an order — came
 * back to the same read-only panel and did nothing, because the block that
 * reads the address and dispatches was the one this plugin replaced.
 */
final class MemberDashboardActionsTest extends TestCase {

	private const PAGE = 42;

	protected function setUp(): void {
		wp_stub_reset();
		$this->forget_address();
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( 'dashboard' ), self::PAGE );
		// SureCart's controller classes are not loaded in a unit test, so the
		// shop is stood in for: every controller it ships has every method.
		Blueworx_Clubhouse_Dashboard_Actions::set_check(
			static fn ( string $controller, string $action ): bool => true
		);
		// Every block answers, so a panel that is missing from the screen is
		// missing because of the routing and not because nothing rendered.
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static fn ( string $n ): ?string => '<div data-block="' . $n . '">panel</div>',
			static fn ( string $t ): ?string => '<div data-shortcode="' . $t . '">bookings</div>'
		);
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources( null, null );
		Blueworx_Clubhouse_Dashboard_Actions::set_check( null );
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		$this->forget_address();
		wp_stub_reset();
	}

	private function forget_address(): void {
		unset( $_GET['view'], $_GET['model'], $_GET['action'], $_GET['id'] );
	}

	private function screen(): string {
		return Blueworx_Clubhouse_Member_Dashboard::screen( 'https://club.test/member-dashboard/', 'https://club.test/' );
	}

	public function test_updating_billing_details_draws_the_shops_action_screen(): void {
		$_GET['model']  = 'customer';
		$_GET['action'] = 'edit';
		$_GET['id']     = '6bfdf11e-5401-4aa8-b68b-db92041cba0d';

		$html = $this->screen();
		$this->assertStringContainsString( 'data-block="' . Blueworx_Clubhouse_Dashboard_Actions::BLOCK . '"', $html );
	}

	public function test_the_action_replaces_the_panel_it_belongs_to(): void {
		// The read-only billing panel is what the member pressed Update on, so
		// showing it under the form would be the same details twice.
		$_GET['model']  = 'customer';
		$_GET['action'] = 'edit';

		$html = $this->screen();
		$this->assertStringNotContainsString( 'data-block="surecart/customer-billing-details"', $html );
		// The panels the action has nothing to do with are still there.
		$this->assertStringContainsString( 'data-block="surecart/customer-orders"', $html );
	}

	public function test_an_ordinary_address_draws_the_ordinary_panels(): void {
		$html = $this->screen();
		$this->assertStringContainsString( 'data-block="surecart/customer-billing-details"', $html );
		$this->assertStringNotContainsString( 'data-block="' . Blueworx_Clubhouse_Dashboard_Actions::BLOCK . '"', $html );
	}

	public function test_a_stale_link_is_not_a_blank_member_area(): void {
		// A model the shop has never had. The member sees their member area
		// rather than an empty frame.
		$_GET['model']  = 'nonsense';
		$_GET['action'] = 'edit';

		$html = $this->screen();
		$this->assertStringContainsString( 'data-block="surecart/customer-billing-details"', $html );
	}

	public function test_an_action_bookmarked_on_the_shops_own_page_keeps_its_action(): void {
		// Carried across whole: without the model, action and id the member
		// lands on a screen reading their details back rather than the form.
		$this->assertSame(
			'https://club.test/member-dashboard/?model=customer&action=edit&id=cus_1',
			Blueworx_Clubhouse_Member_Dashboard::redirect_to(
				self::PAGE,
				self::PAGE,
				false,
				true,
				true,
				'',
				'https://club.test/member-dashboard/',
				'https://club.test/login/',
				'customer',
				'edit',
				'cus_1'
			)
		);
	}

	public function test_an_action_and_a_panel_are_carried_across_together(): void {
		$this->assertSame(
			'https://club.test/member-dashboard/?view=plans&model=subscription&action=cancel',
			Blueworx_Clubhouse_Member_Dashboard::redirect_to(
				self::PAGE,
				self::PAGE,
				false,
				true,
				true,
				'plans',
				'https://club.test/member-dashboard/',
				'https://club.test/login/',
				'subscription',
				'cancel'
			)
		);
	}

	public function test_a_plain_bookmark_is_carried_across_unchanged(): void {
		$this->assertSame(
			'https://club.test/member-dashboard/',
			Blueworx_Clubhouse_Member_Dashboard::redirect_to(
				self::PAGE,
				self::PAGE,
				false,
				true,
				true,
				'',
				'https://club.test/member-dashboard/',
				'https://club.test/login/'
			)
		);
	}
}
