<?php

use PHPUnit\Framework\TestCase;

/** What the owner is told when the shop's pages are not all there. */
final class ShopPagesNoticeTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		wp_stub_reset();
	}

	/** @param array<string,string> $problems */
	private function message( array $problems, bool $can_seed = true ): ?array {
		return Blueworx_Clubhouse_Shop_Pages_Controller::message(
			$problems,
			Blueworx_Clubhouse_Shop_Pages::pages(),
			$can_seed
		);
	}

	public function test_a_healthy_shop_is_told_nothing(): void {
		$this->assertNull( $this->message( array() ) );
		$this->assertSame( '', Blueworx_Clubhouse_Shop_Pages_Controller::notice_html( null, 'https://club.test/' ) );
	}

	public function test_each_broken_page_is_explained_by_what_the_visitor_loses(): void {
		$message = $this->message( array(
			'checkout'  => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING,
			'dashboard' => Blueworx_Clubhouse_Shop_Pages::STATUS_UNPUBLISHED,
		) );
		$this->assertNotNull( $message );
		$this->assertCount( 2, $message['lines'] );
		$this->assertStringContainsString( 'contact page', $message['lines'][0] );
		$this->assertStringContainsString( 'in the trash', $message['lines'][1] );
	}

	public function test_the_button_is_offered_when_surecart_can_put_them_back(): void {
		$message = $this->message( array( 'checkout' => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING ) );
		$this->assertSame( 'Put the missing pages back', $message['button'] );
		$this->assertSame( '', $message['footnote'] );
	}

	public function test_no_button_when_surecart_cannot_seed(): void {
		// Better to say "open SureCart" than to offer a button that would do
		// nothing, or to write SureCart's pages ourselves.
		$message = $this->message( array( 'checkout' => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING ), false );
		$this->assertSame( '', $message['button'] );
		$this->assertStringContainsString( 'Open SureCart', $message['footnote'] );
	}

	public function test_a_page_the_button_will_not_fix_is_named_up_front(): void {
		// Pressing the button and finding a warning still there should not be a
		// surprise.
		$message = $this->message( array(
			'checkout'           => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING,
			'order-confirmation' => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING,
		) );
		$this->assertSame( 'Put the missing pages back', $message['button'] );
		$this->assertStringContainsString( 'order confirmation page', $message['footnote'] );
	}

	public function test_the_footnote_does_not_mention_a_button_that_is_not_there(): void {
		$message = $this->message( array( 'order-confirmation' => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING ) );
		$this->assertSame( '', $message['button'] );
		$this->assertSame( 'Open SureCart and finish setting the shop up.', $message['footnote'] );
	}

	public function test_a_trashed_page_is_repairable_even_when_seeding_is_not_available(): void {
		$message = $this->message( array( 'shop' => Blueworx_Clubhouse_Shop_Pages::STATUS_UNPUBLISHED ), false );
		$this->assertSame( 'Put the missing pages back', $message['button'] );
	}

	public function test_the_notice_carries_the_repair_link(): void {
		$html = Blueworx_Clubhouse_Shop_Pages_Controller::notice_html(
			$this->message( array( 'checkout' => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING ) ),
			'https://club.test/wp-admin/admin-post.php?action=clubhouse_shop_pages_repair&_wpnonce=abc'
		);
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'clubhouse_shop_pages_repair', $html );
	}

	public function test_the_repair_link_is_escaped(): void {
		$html = Blueworx_Clubhouse_Shop_Pages_Controller::notice_html(
			$this->message( array( 'checkout' => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING ) ),
			'https://club.test/"><script>alert(1)</script>'
		);
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_nothing_is_printed_on_a_site_without_surecart(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( false );
		ob_start();
		Blueworx_Clubhouse_Shop_Pages_Controller::render_notice();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	public function test_the_notice_is_printed_when_pages_are_missing(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		ob_start();
		Blueworx_Clubhouse_Shop_Pages_Controller::render_notice();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'not ready to take payments', $html );
		$this->assertStringContainsString( 'checkout page', $html );
	}

	public function test_the_repair_is_reachable_only_through_an_admin_post_action(): void {
		// handle_repair() ends in exit(), so it is not callable from a unit
		// test; what is worth pinning is that nothing else can reach it — it
		// hangs off admin_post_, and the handler checks the nonce and the
		// capability before it writes anything.
		Blueworx_Clubhouse_Shop_Pages_Controller::register();
		$hooks = array_map( static fn ( array $call ): string => (string) $call['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'admin_post_' . Blueworx_Clubhouse_Shop_Pages_Controller::ACTION, $hooks );
		$this->assertContains( 'admin_notices', $hooks );
	}
}
