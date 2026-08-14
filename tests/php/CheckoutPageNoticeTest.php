<?php

use PHPUnit\Framework\TestCase;

/** What the owner is told when the shop has no checkout page. */
final class CheckoutPageNoticeTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		wp_stub_reset();
	}

	public function test_a_healthy_site_is_told_nothing(): void {
		$this->assertNull(
			Blueworx_Clubhouse_Checkout_Page_Controller::message( Blueworx_Clubhouse_Checkout_Page::STATUS_OK )
		);
		$this->assertSame( '', Blueworx_Clubhouse_Checkout_Page_Controller::notice_html( null, 'https://club.test/' ) );
	}

	public function test_a_site_with_no_shop_is_told_nothing(): void {
		$this->assertNull(
			Blueworx_Clubhouse_Checkout_Page_Controller::message( Blueworx_Clubhouse_Checkout_Page::STATUS_NO_SHOP )
		);
	}

	public function test_a_missing_page_is_explained_in_terms_of_what_the_visitor_sees(): void {
		$message = Blueworx_Clubhouse_Checkout_Page_Controller::message( Blueworx_Clubhouse_Checkout_Page::STATUS_MISSING );
		$this->assertNotNull( $message );
		$this->assertStringContainsString( 'contact page', $message['text'] );
		$this->assertSame( 'Create the checkout page', $message['button'] );
	}

	public function test_an_unpublished_page_offers_to_publish_rather_than_create(): void {
		$message = Blueworx_Clubhouse_Checkout_Page_Controller::message( Blueworx_Clubhouse_Checkout_Page::STATUS_UNPUBLISHED );
		$this->assertNotNull( $message );
		$this->assertSame( 'Publish the checkout page', $message['button'] );
	}

	public function test_a_shop_with_no_form_gets_no_button(): void {
		// There is nothing this plugin can do about it, and a button that
		// published an empty checkout would be worse than none.
		$message = Blueworx_Clubhouse_Checkout_Page_Controller::message( Blueworx_Clubhouse_Checkout_Page::STATUS_NO_FORM );
		$this->assertNotNull( $message );
		$this->assertSame( '', $message['button'] );
		$html = Blueworx_Clubhouse_Checkout_Page_Controller::notice_html( $message, 'https://club.test/repair' );
		$this->assertStringNotContainsString( '<a', $html );
	}

	public function test_the_notice_carries_the_repair_link(): void {
		$html = Blueworx_Clubhouse_Checkout_Page_Controller::notice_html(
			Blueworx_Clubhouse_Checkout_Page_Controller::message( Blueworx_Clubhouse_Checkout_Page::STATUS_MISSING ),
			'https://club.test/wp-admin/admin-post.php?action=clubhouse_checkout_page_repair&_wpnonce=abc'
		);
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'clubhouse_checkout_page_repair', $html );
	}

	public function test_the_repair_link_is_escaped(): void {
		$html = Blueworx_Clubhouse_Checkout_Page_Controller::notice_html(
			Blueworx_Clubhouse_Checkout_Page_Controller::message( Blueworx_Clubhouse_Checkout_Page::STATUS_MISSING ),
			'https://club.test/"><script>alert(1)</script>'
		);
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_nothing_is_printed_on_a_site_without_surecart(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( false );
		ob_start();
		Blueworx_Clubhouse_Checkout_Page_Controller::render_notice();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	public function test_the_notice_is_printed_when_the_page_is_missing(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		update_option( Blueworx_Clubhouse_Checkout_Page::FORM_OPTION, 7 );
		ob_start();
		Blueworx_Clubhouse_Checkout_Page_Controller::render_notice();
		$this->assertStringContainsString( 'Create the checkout page', (string) ob_get_clean() );
	}

	public function test_the_repair_is_reachable_only_through_an_admin_post_action(): void {
		// handle_repair() itself ends in exit(), so it is not callable from a
		// unit test; what is worth pinning is that nothing else can reach it —
		// it hangs off admin_post_, which is authenticated, and the handler
		// checks the nonce and the capability before it writes anything.
		Blueworx_Clubhouse_Checkout_Page_Controller::register();
		$hooks = array_map( static fn ( array $call ): string => (string) $call['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'admin_post_' . Blueworx_Clubhouse_Checkout_Page_Controller::ACTION, $hooks );
		$this->assertContains( 'admin_notices', $hooks );
	}
}
