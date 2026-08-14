<?php

use PHPUnit\Framework\TestCase;

final class ProductsSourceTest extends TestCase {

	protected function tearDown(): void {
		// A leaked adapter would silently change what every later test renders.
		Blueworx_Clubhouse_Products_Source::set( null );
	}

	public function test_nothing_is_installed_by_default(): void {
		$this->assertNull( Blueworx_Clubhouse_Products_Source::get() );
	}

	public function test_an_adapter_can_be_installed_and_removed(): void {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		$this->assertInstanceOf( Blueworx_Clubhouse_Products::class, Blueworx_Clubhouse_Products_Source::get() );

		Blueworx_Clubhouse_Products_Source::set( null );
		$this->assertNull( Blueworx_Clubhouse_Products_Source::get() );
	}

	public function test_the_demo_adapter_lists_prices_the_picker_can_show(): void {
		$prices = ( new Blueworx_Clubhouse_Demo_Products() )->prices();
		$this->assertNotSame( array(), $prices );
		foreach ( $prices as $price ) {
			$this->assertNotSame( '', $price['id'] );
			$this->assertNotSame( '', $price['product'] );
			$this->assertNotSame( '', $price['label'] );
			$this->assertStringStartsWith( '£', $price['amount'] );
			$this->assertContains( $price['period'], array( '/mo', '/yr', '' ) );
		}
	}

	public function test_a_known_price_comes_back_whole(): void {
		$demo  = new Blueworx_Clubhouse_Demo_Products();
		$price = $demo->price( 'price_adult_monthly' );
		$this->assertSame( 'price_adult_monthly', $price['id'] );
		$this->assertSame( '£28', $price['amount'] );
		$this->assertSame( '/mo', $price['period'] );
	}

	public function test_an_unknown_price_is_null_not_an_empty_array(): void {
		// Null is the single fallback signal the renderer branches on. An empty
		// array would be truthy-adjacent and invite a wrong check.
		$this->assertNull( ( new Blueworx_Clubhouse_Demo_Products() )->price( 'price_nope' ) );
	}
}
