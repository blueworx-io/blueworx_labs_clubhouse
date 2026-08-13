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

	public function test_a_resolver_is_not_called_until_a_url_is_actually_built(): void {
		// checkout_url() is not safe to call at plugins_loaded (see class docblock
		// on Checkout::set_resolver()) — the whole point of the resolver seam is
		// that installing it must not itself trigger the call.
		$calls = 0;
		Blueworx_Clubhouse_Checkout::set_resolver( function () use ( &$calls ) {
			++$calls;
			return 'https://club.test/checkout/';
		} );
		$this->assertSame( 0, $calls );
	}

	public function test_a_resolver_runs_at_most_once_per_request(): void {
		$calls = 0;
		Blueworx_Clubhouse_Checkout::set_resolver( function () use ( &$calls ) {
			++$calls;
			return 'https://club.test/checkout/';
		} );

		Blueworx_Clubhouse_Checkout::url( 'price_a' );
		Blueworx_Clubhouse_Checkout::base_url();
		Blueworx_Clubhouse_Checkout::url( 'price_b' );

		$this->assertSame( 1, $calls );
	}

	public function test_a_resolver_produces_a_working_checkout_url(): void {
		Blueworx_Clubhouse_Checkout::set_resolver( static fn(): string => 'https://club.test/checkout/' );
		$url = Blueworx_Clubhouse_Checkout::url( 'price_adult_monthly' );
		$this->assertStringStartsWith( 'https://club.test/checkout/?', $url );
		$this->assertStringContainsString( 'price_adult_monthly', $url );
	}
}
