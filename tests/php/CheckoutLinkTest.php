<?php

use PHPUnit\Framework\TestCase;

final class CheckoutLinkTest extends TestCase {

	protected function setUp(): void {
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/checkout/' );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_Checkout::set_base_url( '' );
	}

	public function test_a_price_becomes_a_checkout_url_carrying_it(): void {
		$url = Blueworx_Clubhouse_Checkout::url( 'price_adult_monthly' );
		$this->assertStringStartsWith( 'https://club.test/checkout/?', $url );
		$this->assertStringContainsString( 'price_adult_monthly', $url );
	}

	public function test_no_checkout_page_means_no_link_rather_than_a_broken_one(): void {
		Blueworx_Clubhouse_Checkout::set_base_url( '' );
		$this->assertSame( '', Blueworx_Clubhouse_Checkout::url( 'price_adult_monthly' ) );
	}

	public function test_no_price_means_no_link(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Checkout::url( '' ) );
	}

	public function test_a_base_url_that_already_has_a_query_keeps_it(): void {
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/?page_id=42' );
		$url = Blueworx_Clubhouse_Checkout::url( 'price_adult_monthly' );
		$this->assertStringContainsString( 'page_id=42', $url );
		$this->assertStringContainsString( '&', $url );
	}

	public function test_a_price_id_is_url_encoded(): void {
		// Ids come from a third party. One with a reserved character must not be
		// able to add a parameter of its own.
		$url = Blueworx_Clubhouse_Checkout::url( 'price&admin=1' );
		$this->assertStringNotContainsString( 'price&admin=1', $url );
		$this->assertStringContainsString( 'price%26admin%3D1', $url );
	}
}
