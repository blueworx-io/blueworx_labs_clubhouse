<?php

use PHPUnit\Framework\TestCase;

final class MembershipTierPricingTest extends TestCase {

	protected function tearDown(): void {
		Blueworx_Clubhouse_Products_Source::set( null );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );
	}

	/**
	 * A club whose Membership page carries these tiers. Written onto the tier
	 * grid block, which is where a tier list lives now.
	 *
	 * @param array<int,array<string,mixed>> $tiers
	 */
	private function club( array $tiers ): Blueworx_Clubhouse_Fake_Storage {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::write( $storage, 'membership/tiers', array( 'items' => $tiers ) );
		return $storage;
	}

	/** A store with a checkout, and one tier connected to the adult monthly price. */
	private function connected(): Blueworx_Clubhouse_Fake_Storage {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/checkout/' );

		return $this->club( array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => "One\nTwo", 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
			array( 'name' => 'Social', 'price' => '£12', 'period' => '/mo', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => '' ),
		) );
	}

	private function tiers( Blueworx_Clubhouse_Fake_Storage $storage ): string {
		return Blueworx_Clubhouse_Test_Site::main( Blueworx_Clubhouse_Test_Site::page( 'membership', $storage ) );
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

		$html = $this->tiers( $this->club( array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_gone' ),
		) ) );
		$this->assertStringContainsString( '£99', $html );
		$this->assertStringContainsString( '?page=contact', $html );
	}

	public function test_no_checkout_page_means_no_checkout_link_even_when_connected(): void {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );

		// Half-connected is worse than not connected: the club's own page would
		// advertise the shop's price and send the visitor to a contact form.
		$html = $this->tiers( $this->club( array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
		) ) );
		$this->assertStringContainsString( '£99', $html );
		$this->assertStringNotContainsString( '£28', $html );
	}

	public function test_with_no_shop_installed_nothing_changes(): void {
		Blueworx_Clubhouse_Products_Source::set( null );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );

		$html = $this->tiers( $this->club( array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
		) ) );
		$this->assertStringContainsString( '£99', $html );
		$this->assertStringContainsString( '?page=contact', $html );
	}
}
