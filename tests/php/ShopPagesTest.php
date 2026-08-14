<?php

use PHPUnit\Framework\TestCase;

/**
 * Whether the shop's own pages are reachable, and what the repair does.
 *
 * The option keys and page names asserted here are read from SureCart's source
 * and exercised against a real install — see docs/integrations/surecart-notes.md.
 */
final class ShopPagesTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		wp_stub_reset();
	}

	private function stored_page( string $key, int $id, string $status, string $permalink = 'https://club.test/checkout/' ): void {
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( $key ), $id );
		$GLOBALS['wp_stub_post_status'][ $id ] = $status;
		$GLOBALS['wp_stub_permalinks'][ $id ]  = $permalink;
	}

	public function test_the_option_key_is_built_the_way_surecart_builds_it(): void {
		$this->assertSame( 'surecart_checkout_page_id', Blueworx_Clubhouse_Shop_Pages::option_name( 'checkout' ) );
		// The hyphen is SureCart's, not a typo — its uninstall routine lists
		// this exact key.
		$this->assertSame( 'surecart_order-confirmation_page_id', Blueworx_Clubhouse_Shop_Pages::option_name( 'order-confirmation' ) );
	}

	public function test_a_published_page_is_the_healthy_state(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Shop_Pages::STATUS_OK,
			Blueworx_Clubhouse_Shop_Pages::decide( true, 12, 'publish' )
		);
	}

	public function test_no_shop_is_not_a_problem_to_report(): void {
		// A club with no SureCart is not a club with a broken shop, and must
		// never see a notice about one.
		$this->assertSame(
			Blueworx_Clubhouse_Shop_Pages::STATUS_NO_SHOP,
			Blueworx_Clubhouse_Shop_Pages::decide( false, 0, '' )
		);
		$this->assertSame(
			array(),
			Blueworx_Clubhouse_Shop_Pages::problems( array( 'checkout' => Blueworx_Clubhouse_Shop_Pages::STATUS_NO_SHOP ) )
		);
	}

	public function test_a_trashed_page_is_unpublished_rather_than_missing(): void {
		// The distinction decides the repair: republishing brings the page
		// back, while seeding would create a second one beside it and leave
		// SureCart's links pointing at the wrong one.
		$this->assertSame(
			Blueworx_Clubhouse_Shop_Pages::STATUS_UNPUBLISHED,
			Blueworx_Clubhouse_Shop_Pages::decide( true, 12, 'trash' )
		);
	}

	public function test_a_draft_or_private_page_counts_as_unreachable(): void {
		// Visible to staff, a 404 to the buyer — which is everyone who matters.
		foreach ( array( 'draft', 'private', 'pending' ) as $status ) {
			$this->assertSame(
				Blueworx_Clubhouse_Shop_Pages::STATUS_UNPUBLISHED,
				Blueworx_Clubhouse_Shop_Pages::decide( true, 12, $status ),
				$status . ' must not count as reachable'
			);
		}
	}

	public function test_an_id_pointing_at_nothing_is_missing(): void {
		// A deleted page leaves the option behind holding a stale id.
		$this->assertSame(
			Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING,
			Blueworx_Clubhouse_Shop_Pages::decide( true, 12, '' )
		);
	}

	public function test_every_page_the_shop_needs_is_checked(): void {
		$this->assertSame(
			array( 'checkout', 'order-confirmation', 'dashboard', 'shop' ),
			array_keys( Blueworx_Clubhouse_Shop_Pages::pages() )
		);
		$this->assertSame(
			array( 'checkout', 'order-confirmation', 'dashboard', 'shop' ),
			array_keys( Blueworx_Clubhouse_Shop_Pages::statuses() )
		);
	}

	public function test_only_broken_pages_are_reported(): void {
		$problems = Blueworx_Clubhouse_Shop_Pages::problems( array(
			'checkout'  => Blueworx_Clubhouse_Shop_Pages::STATUS_OK,
			'dashboard' => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING,
			'shop'      => Blueworx_Clubhouse_Shop_Pages::STATUS_UNPUBLISHED,
		) );
		$this->assertSame( array( 'dashboard', 'shop' ), array_keys( $problems ) );
	}

	public function test_a_page_surecart_does_not_seed_is_not_promised_by_the_button(): void {
		// SureCart's seeder makes the checkout, dashboard and shop pages; its
		// order-confirmation page comes from onboarding instead. Offering to
		// create it would be a promise the button cannot keep.
		$repairable = Blueworx_Clubhouse_Shop_Pages::repairable(
			array(
				'checkout'           => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING,
				'order-confirmation' => Blueworx_Clubhouse_Shop_Pages::STATUS_MISSING,
			),
			Blueworx_Clubhouse_Shop_Pages::pages()
		);
		$this->assertSame( array( 'checkout' ), array_keys( $repairable ) );
	}

	public function test_a_page_that_only_needs_republishing_is_always_repairable(): void {
		// Even the one SureCart would not create: it already exists, so this is
		// just taking it out of the trash.
		$repairable = Blueworx_Clubhouse_Shop_Pages::repairable(
			array( 'order-confirmation' => Blueworx_Clubhouse_Shop_Pages::STATUS_UNPUBLISHED ),
			Blueworx_Clubhouse_Shop_Pages::pages()
		);
		$this->assertSame( array( 'order-confirmation' ), array_keys( $repairable ) );
	}

	public function test_a_trashed_checkout_page_yields_no_url_rather_than_a_dead_link(): void {
		// The bug this was written for: the URL used to come straight from the
		// option's permalink, so a trashed page produced a live-looking Join
		// button leading to a 404.
		$this->stored_page( 'checkout', 12, 'trash' );
		$this->assertSame( '', Blueworx_Clubhouse_Shop_Pages::url( 'checkout' ) );
		$this->assertSame( '', Blueworx_Clubhouse_SureCart_Products::checkout_url() );
	}

	public function test_a_published_checkout_page_yields_its_url(): void {
		$this->stored_page( 'checkout', 12, 'publish' );
		$this->assertSame( 'https://club.test/checkout/', Blueworx_Clubhouse_Shop_Pages::url( 'checkout' ) );
		$this->assertSame( 'https://club.test/checkout/', Blueworx_Clubhouse_SureCart_Products::checkout_url() );
	}

	public function test_no_shop_yields_no_url_and_never_touches_the_permalink(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( false );
		$this->stored_page( 'checkout', 12, 'publish' );
		$this->assertSame( '', Blueworx_Clubhouse_SureCart_Products::checkout_url() );
		$this->assertSame( array(), wp_stub_calls( 'get_permalink' ) );
	}

	public function test_a_junk_option_value_is_no_page(): void {
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( 'checkout' ), 'not-an-id' );
		$this->assertSame( 0, Blueworx_Clubhouse_Shop_Pages::page_id( 'checkout' ) );
	}

	public function test_repair_republishes_a_trashed_page_and_makes_no_new_one(): void {
		$this->stored_page( 'checkout', 12, 'trash' );
		Blueworx_Clubhouse_Shop_Pages::repair();

		$updated = wp_stub_calls( 'wp_update_post' );
		$this->assertNotSame( array(), $updated );
		$this->assertSame( 12, $updated[0]['args'][0]['ID'] );
		$this->assertSame( 'publish', $updated[0]['args'][0]['post_status'] );
		// Page creation is SureCart's job, and this plugin never inserts one.
		$this->assertSame( array(), wp_stub_calls( 'wp_insert_post' ) );
	}

	public function test_repair_never_writes_a_page_itself(): void {
		// The whole point: SureCart runs the shop. With its seeder unreachable
		// — as it is in this process — a missing page stays missing and is
		// reported, rather than being faked with markup copied out of SureCart.
		$this->assertFalse( Blueworx_Clubhouse_Shop_Pages::can_seed() );
		Blueworx_Clubhouse_Shop_Pages::repair();
		$this->assertSame( array(), wp_stub_calls( 'wp_insert_post' ) );
	}

	public function test_repair_reports_success_only_when_nothing_it_can_act_on_is_left(): void {
		foreach ( array_keys( Blueworx_Clubhouse_Shop_Pages::pages() ) as $i => $key ) {
			$this->stored_page( $key, 20 + $i, 'publish' );
		}
		$this->assertTrue( Blueworx_Clubhouse_Shop_Pages::repair() );

		$this->stored_page( 'checkout', 99, 'trash' );
		wp_stub_fail_update( 99 );
		$this->assertFalse( Blueworx_Clubhouse_Shop_Pages::repair() );
	}
}
