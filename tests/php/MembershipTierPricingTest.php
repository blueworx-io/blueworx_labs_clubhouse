<?php

use PHPUnit\Framework\TestCase;

final class MembershipTierPricingTest extends TestCase {

	protected function tearDown(): void {
		Blueworx_Clubhouse_Products_Source::set( null );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );
	}

	/** A store with a checkout, and one tier connected to the adult monthly price. */
	private function connected(): Blueworx_Clubhouse_Content_Store {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/checkout/' );

		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items(
			'membership',
			'tiers',
			array(
				array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => "One\nTwo", 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
				array( 'name' => 'Social', 'price' => '£12', 'period' => '/mo', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => '' ),
			)
		);
		return $content;
	}

	private function tiers( Blueworx_Clubhouse_Content_Store $content ): string {
		return Blueworx_Clubhouse_Sections::tier_grid(
			Blueworx_Clubhouse_Page_Renderer::membership_tiers_for_test( $content ),
			2
		);
	}

	public function test_a_connected_tier_shows_the_shops_price_not_the_typed_one(): void {
		$html = $this->tiers( $this->connected() );
		$this->assertStringContainsString( '£28', $html );
		$this->assertStringNotContainsString( '£99', $html );
	}

	public function test_a_connected_tier_links_to_checkout_carrying_its_price(): void {
		$html = $this->tiers( $this->connected() );
		$this->assertStringContainsString( 'https://club.test/checkout/', $html );
		$this->assertStringContainsString( 'price_adult_monthly', $html );
	}

	public function test_an_unconnected_tier_is_untouched(): void {
		$html = $this->tiers( $this->connected() );
		// The second tier keeps its typed price and its contact link.
		$this->assertStringContainsString( '£12', $html );
		$this->assertStringContainsString( '?page=contact', $html );
	}

	public function test_a_deleted_price_falls_back_to_the_typed_price(): void {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/checkout/' );

		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'membership', 'tiers', array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_gone' ),
		) );

		$html = $this->tiers( $content );
		$this->assertStringContainsString( '£99', $html );
		$this->assertStringContainsString( '?page=contact', $html );
	}

	public function test_no_checkout_page_means_no_checkout_link_even_when_connected(): void {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );

		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'membership', 'tiers', array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
		) );

		// Half-connected is worse than not connected: the club's own page would
		// advertise the shop's price and send the visitor to a contact form.
		$html = $this->tiers( $content );
		$this->assertStringContainsString( '£99', $html );
		$this->assertStringNotContainsString( '£28', $html );
	}

	/**
	 * The tier arrays themselves, rather than the rendered grid — the cadence
	 * rules are decided here and drawn elsewhere.
	 *
	 * @param array<int,array<string,mixed>> $items
	 * @return array<int,array<string,mixed>>
	 */
	private function tierData( array $items ): array {
		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'membership', 'tiers', $items );
		return Blueworx_Clubhouse_Page_Renderer::membership_tiers_for_test( $content );
	}

	/** @param array<int,array<string,mixed>> $items */
	private function tierDataWithShop( array $items ): array {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/checkout/' );
		return $this->tierData( $items );
	}

	public function test_a_tier_carries_both_cadences(): void {
		$tiers = $this->tierData( array( array(
			'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
			'price_annual' => '£280', 'cta_label' => 'Join',
		) ) );
		$this->assertSame( '£28', $tiers[0]['monthly']['price'] );
		$this->assertSame( '/mo', $tiers[0]['monthly']['period'] );
		$this->assertTrue( $tiers[0]['monthly']['available'] );
		$this->assertSame( '£280', $tiers[0]['annual']['price'] );
		$this->assertSame( '/yr', $tiers[0]['annual']['period'] );
		$this->assertTrue( $tiers[0]['annual']['available'] );
	}

	public function test_a_tier_with_no_annual_price_says_so_rather_than_vanishing(): void {
		$tiers = $this->tierData( array( array( 'name' => 'Junior', 'price' => '£12', 'period' => '/mo' ) ) );
		$this->assertFalse( $tiers[0]['annual']['available'] );
		// It still shows the price it has, so the card does not empty out.
		$this->assertSame( '£12', $tiers[0]['annual']['price'] );
	}

	public function test_a_tier_with_only_an_annual_price_works_the_same_way_round(): void {
		$tiers = $this->tierData( array( array( 'name' => 'Life', 'price' => '', 'price_annual' => '£500' ) ) );
		$this->assertFalse( $tiers[0]['monthly']['available'] );
		$this->assertSame( '£500', $tiers[0]['monthly']['price'] );
		$this->assertTrue( $tiers[0]['annual']['available'] );
	}

	public function test_the_annual_saving_is_worked_out_not_typed(): void {
		$tiers = $this->tierData( array( array( 'name' => 'Adult', 'price' => '£28', 'price_annual' => '£280' ) ) );
		$this->assertSame( 'Save £56 a year', $tiers[0]['saving'] );
	}

	public function test_no_saving_when_annual_is_not_actually_cheaper(): void {
		$tiers = $this->tierData( array( array( 'name' => 'Adult', 'price' => '£28', 'price_annual' => '£340' ) ) );
		$this->assertSame( '', $tiers[0]['saving'] );
	}

	public function test_no_saving_from_a_price_that_is_not_plainly_a_number(): void {
		// A wrong saving contradicts the prices beside it; a missing one costs nothing.
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::annual_saving( 'from £28', '£280' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::annual_saving( '£28 per adult', '£280' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::annual_saving( '£28', '' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::annual_saving( 'Free', 'Free' ) );
	}

	public function test_a_saving_keeps_the_currency_it_was_given(): void {
		$this->assertSame( 'Save €56 a year', Blueworx_Clubhouse_Page_Renderer::annual_saving( '€28', '€280' ) );
		// Two different currencies cannot be subtracted from each other.
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::annual_saving( '€28', '£280' ) );
	}

	public function test_each_cadence_sells_its_own_price(): void {
		$tiers = $this->tierDataWithShop( array( array(
			'name' => 'Adult', 'price' => '£28', 'price_id' => 'price_adult_monthly', 'price_id_annual' => 'price_adult_yearly',
		) ) );
		$this->assertStringContainsString( 'price_adult_monthly', $tiers[0]['monthly']['cta_href'] );
		$this->assertStringContainsString( 'price_adult_yearly', $tiers[0]['annual']['cta_href'] );
		$this->assertSame( '£28', $tiers[0]['monthly']['price'] );
		$this->assertSame( '£300', $tiers[0]['annual']['price'] );
	}

	public function test_an_annual_price_the_shop_does_not_know_falls_back_to_the_typed_one(): void {
		$tiers = $this->tierDataWithShop( array( array(
			'name' => 'Adult', 'price' => '£28', 'price_annual' => '£280', 'price_id_annual' => 'price_vanished',
		) ) );
		$this->assertSame( '£280', $tiers[0]['annual']['price'] );
		$this->assertStringNotContainsString( 'price_vanished', $tiers[0]['annual']['cta_href'] );
	}

	public function test_the_old_flat_fields_still_carry_the_monthly_price(): void {
		// Anything reading a tier the way it did before must be unchanged.
		$tiers = $this->tierData( array( array( 'name' => 'Adult', 'price' => '£28', 'period' => '/mo', 'price_annual' => '£280' ) ) );
		$this->assertSame( '£28', $tiers[0]['price'] );
		$this->assertSame( '/mo', $tiers[0]['period'] );
	}

	public function test_with_no_shop_installed_nothing_changes(): void {
		Blueworx_Clubhouse_Products_Source::set( null );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );

		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'membership', 'tiers', array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
		) );

		$html = $this->tiers( $content );
		$this->assertStringContainsString( '£99', $html );
		$this->assertStringContainsString( '?page=contact', $html );
	}
}
