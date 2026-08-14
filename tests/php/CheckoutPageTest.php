<?php

use PHPUnit\Framework\TestCase;

/**
 * The checkout page's health, and the repair for when it has none.
 *
 * The names asserted here — the option keys, the block, the form post type —
 * are read from SureCart's own source rather than guessed, so a test that
 * pins them is pinning a real contract. See docs/integrations/surecart-notes.md.
 */
final class CheckoutPageTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		wp_stub_reset();
	}

	/** Put a page of the given status behind the checkout option. */
	private function stored_page( int $id, string $status, string $permalink = 'https://club.test/checkout/' ): void {
		update_option( Blueworx_Clubhouse_Checkout_Page::PAGE_OPTION, $id );
		$GLOBALS['wp_stub_post_status'][ $id ] = $status;
		$GLOBALS['wp_stub_permalinks'][ $id ]  = $permalink;
	}

	private function a_form(): void {
		update_option( Blueworx_Clubhouse_Checkout_Page::FORM_OPTION, 7 );
	}

	/** @return callable():int */
	private function form_id( int $id ): callable {
		return static fn (): int => $id;
	}

	public function test_a_published_page_is_the_healthy_state(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Checkout_Page::STATUS_OK,
			Blueworx_Clubhouse_Checkout_Page::decide( true, 12, 'publish', $this->form_id( 7 ) )
		);
	}

	public function test_no_shop_is_not_a_problem_to_report(): void {
		// A club with no SureCart is not a club with a broken shop. It must
		// never see a notice about one.
		$this->assertSame(
			Blueworx_Clubhouse_Checkout_Page::STATUS_NO_SHOP,
			Blueworx_Clubhouse_Checkout_Page::decide( false, 0, '', $this->form_id( 0 ) )
		);
		$this->assertFalse(
			Blueworx_Clubhouse_Checkout_Page::needs_attention( Blueworx_Clubhouse_Checkout_Page::STATUS_NO_SHOP )
		);
	}

	public function test_a_trashed_page_is_unpublished_rather_than_missing(): void {
		// The distinction is the point: replacing it would leave the club with
		// two checkout pages and SureCart pointing at the wrong one.
		$this->assertSame(
			Blueworx_Clubhouse_Checkout_Page::STATUS_UNPUBLISHED,
			Blueworx_Clubhouse_Checkout_Page::decide( true, 12, 'trash', $this->form_id( 7 ) )
		);
	}

	public function test_a_draft_page_counts_as_unreachable(): void {
		// Visible to staff, a 404 to the buyer — which is everyone who matters.
		$this->assertSame(
			Blueworx_Clubhouse_Checkout_Page::STATUS_UNPUBLISHED,
			Blueworx_Clubhouse_Checkout_Page::decide( true, 12, 'draft', $this->form_id( 7 ) )
		);
	}

	public function test_a_private_page_counts_as_unreachable_too(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Checkout_Page::STATUS_UNPUBLISHED,
			Blueworx_Clubhouse_Checkout_Page::decide( true, 12, 'private', $this->form_id( 7 ) )
		);
	}

	public function test_an_id_pointing_at_nothing_is_missing(): void {
		// A deleted page leaves the option behind holding a stale id.
		$this->assertSame(
			Blueworx_Clubhouse_Checkout_Page::STATUS_MISSING,
			Blueworx_Clubhouse_Checkout_Page::decide( true, 12, '', $this->form_id( 7 ) )
		);
	}

	public function test_no_page_and_no_form_offers_no_repair(): void {
		// Creating a page with no form on it would publish an empty checkout
		// and call the problem solved.
		$this->assertSame(
			Blueworx_Clubhouse_Checkout_Page::STATUS_NO_FORM,
			Blueworx_Clubhouse_Checkout_Page::decide( true, 0, '', $this->form_id( 0 ) )
		);
	}

	public function test_the_form_is_only_looked_up_when_the_page_is_missing(): void {
		// Finding the form is a database query, and every membership tier on
		// every front-end render resolves a checkout link through here.
		$calls     = 0;
		$counting  = function () use ( &$calls ): int {
			++$calls;
			return 7;
		};
		Blueworx_Clubhouse_Checkout_Page::decide( true, 12, 'publish', $counting );
		Blueworx_Clubhouse_Checkout_Page::decide( true, 12, 'trash', $counting );
		Blueworx_Clubhouse_Checkout_Page::decide( false, 0, '', $counting );
		$this->assertSame( 0, $calls );

		Blueworx_Clubhouse_Checkout_Page::decide( true, 0, '', $counting );
		$this->assertSame( 1, $calls );
	}

	public function test_the_page_content_is_the_block_surecart_looks_for(): void {
		$this->assertSame(
			'<!-- wp:surecart/checkout-form {"id":7} --><!-- /wp:surecart/checkout-form -->',
			Blueworx_Clubhouse_Checkout_Page::content_for( 7 )
		);
	}

	public function test_a_trashed_checkout_page_yields_no_url_rather_than_a_dead_link(): void {
		// The bug this class was written for: the URL used to come straight from
		// the option's permalink, so a trashed page produced a live-looking Join
		// button leading to a 404.
		$this->stored_page( 12, 'trash' );
		$this->assertSame( '', Blueworx_Clubhouse_Checkout_Page::url() );
		$this->assertSame( '', Blueworx_Clubhouse_SureCart_Products::checkout_url() );
	}

	public function test_a_published_checkout_page_yields_its_url(): void {
		$this->stored_page( 12, 'publish' );
		$this->assertSame( 'https://club.test/checkout/', Blueworx_Clubhouse_Checkout_Page::url() );
		$this->assertSame( 'https://club.test/checkout/', Blueworx_Clubhouse_SureCart_Products::checkout_url() );
	}

	public function test_no_shop_yields_no_url_and_never_touches_the_permalink(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( false );
		$this->stored_page( 12, 'publish' );
		$this->assertSame( '', Blueworx_Clubhouse_SureCart_Products::checkout_url() );
		$this->assertSame( array(), wp_stub_calls( 'get_permalink' ) );
	}

	public function test_a_junk_option_value_is_no_page(): void {
		update_option( Blueworx_Clubhouse_Checkout_Page::PAGE_OPTION, 'not-an-id' );
		$this->assertSame( 0, Blueworx_Clubhouse_Checkout_Page::page_id() );
	}

	public function test_the_form_option_is_preferred_over_searching_for_one(): void {
		$this->a_form();
		wp_stub_add_post( Blueworx_Clubhouse_Checkout_Page::FORM_POST_TYPE, 99, 'Another form' );
		$this->assertSame( 7, Blueworx_Clubhouse_Checkout_Page::form_id() );
	}

	public function test_a_form_is_found_even_when_the_option_was_lost(): void {
		wp_stub_add_post( Blueworx_Clubhouse_Checkout_Page::FORM_POST_TYPE, 99, 'Checkout' );
		$this->assertSame( 99, Blueworx_Clubhouse_Checkout_Page::form_id() );
	}

	public function test_no_form_anywhere_is_zero(): void {
		$this->assertSame( 0, Blueworx_Clubhouse_Checkout_Page::form_id() );
	}

	public function test_repairing_a_missing_page_creates_one_and_tells_surecart(): void {
		$this->a_form();
		$this->assertSame( Blueworx_Clubhouse_Checkout_Page::STATUS_MISSING, Blueworx_Clubhouse_Checkout_Page::status() );
		$this->assertTrue( Blueworx_Clubhouse_Checkout_Page::repair() );

		$inserted = wp_stub_calls( 'wp_insert_post' );
		$this->assertCount( 1, $inserted );
		$page = $inserted[0]['args'][0];
		$this->assertSame( 'page', $page['post_type'] );
		$this->assertSame( 'publish', $page['post_status'] );
		$this->assertSame( 'checkout', $page['post_name'] );
		$this->assertStringContainsString( 'wp:surecart/checkout-form', $page['post_content'] );

		// The option is the whole contract: SureCart's own links, its cart and
		// its post-purchase redirects all resolve through it.
		$this->assertGreaterThan( 0, (int) get_option( Blueworx_Clubhouse_Checkout_Page::PAGE_OPTION ) );
	}

	public function test_repairing_an_unpublished_page_publishes_it_rather_than_making_another(): void {
		$this->a_form();
		$this->stored_page( 12, 'trash' );
		$this->assertTrue( Blueworx_Clubhouse_Checkout_Page::repair() );

		$this->assertSame( array(), wp_stub_calls( 'wp_insert_post' ) );
		$updated = wp_stub_calls( 'wp_update_post' );
		$this->assertCount( 1, $updated );
		$this->assertSame( 12, $updated[0]['args'][0]['ID'] );
		$this->assertSame( 'publish', $updated[0]['args'][0]['post_status'] );
	}

	public function test_repair_does_nothing_on_a_healthy_site(): void {
		$this->a_form();
		$this->stored_page( 12, 'publish' );
		$this->assertTrue( Blueworx_Clubhouse_Checkout_Page::repair() );
		$this->assertSame( array(), wp_stub_calls( 'wp_insert_post' ) );
		$this->assertSame( array(), wp_stub_calls( 'wp_update_post' ) );
	}

	public function test_repair_refuses_when_there_is_no_form_to_put_on_the_page(): void {
		$this->assertFalse( Blueworx_Clubhouse_Checkout_Page::repair() );
		$this->assertSame( array(), wp_stub_calls( 'wp_insert_post' ) );
	}

	public function test_a_failed_insert_is_reported_rather_than_recorded(): void {
		// A half-repair that writes the option anyway would leave the site
		// pointing at a page that does not exist.
		$this->a_form();
		wp_stub_fail_insert( Blueworx_Clubhouse_Checkout_Page::TITLE );
		$this->assertFalse( Blueworx_Clubhouse_Checkout_Page::repair() );
		$this->assertSame( 0, Blueworx_Clubhouse_Checkout_Page::page_id() );
	}

	public function test_a_failed_publish_is_reported(): void {
		$this->a_form();
		$this->stored_page( 12, 'draft' );
		wp_stub_fail_update( 12 );
		$this->assertFalse( Blueworx_Clubhouse_Checkout_Page::repair() );
	}
}
