<?php

use PHPUnit\Framework\TestCase;

final class SureCartProductsTest extends TestCase {

	public function test_whole_amounts_lose_their_decimals(): void {
		$this->assertSame( '£28', Blueworx_Clubhouse_SureCart_Products::format_amount( 2800, 'GBP' ) );
		$this->assertSame( '£300', Blueworx_Clubhouse_SureCart_Products::format_amount( 30000, 'GBP' ) );
	}

	public function test_part_amounts_keep_both_decimals(): void {
		$this->assertSame( '£28.50', Blueworx_Clubhouse_SureCart_Products::format_amount( 2850, 'GBP' ) );
		$this->assertSame( '£0.99', Blueworx_Clubhouse_SureCart_Products::format_amount( 99, 'GBP' ) );
	}

	public function test_other_currencies_get_their_own_symbol(): void {
		$this->assertSame( '€28', Blueworx_Clubhouse_SureCart_Products::format_amount( 2800, 'EUR' ) );
		$this->assertSame( '$28', Blueworx_Clubhouse_SureCart_Products::format_amount( 2800, 'USD' ) );
	}

	public function test_an_unknown_currency_falls_back_to_its_code(): void {
		// Better "28 NOK" than a wrong symbol on a club's own price.
		$this->assertSame( '28 NOK', Blueworx_Clubhouse_SureCart_Products::format_amount( 2800, 'NOK' ) );
	}

	public function test_recurring_intervals_become_the_suffix_the_card_shows(): void {
		$this->assertSame( '/mo', Blueworx_Clubhouse_SureCart_Products::format_period( 'month', 1 ) );
		$this->assertSame( '/yr', Blueworx_Clubhouse_SureCart_Products::format_period( 'year', 1 ) );
	}

	public function test_anything_else_has_no_suffix(): void {
		// A one-off, or an interval the card has no words for: better silent than
		// wrong beside a price.
		$this->assertSame( '', Blueworx_Clubhouse_SureCart_Products::format_period( '', 0 ) );
		$this->assertSame( '', Blueworx_Clubhouse_SureCart_Products::format_period( 'week', 1 ) );
		$this->assertSame( '', Blueworx_Clubhouse_SureCart_Products::format_period( 'month', 3 ) );
	}

	public function test_the_picker_label_names_the_product_and_the_price(): void {
		$this->assertSame(
			'Adult membership — £28/mo',
			Blueworx_Clubhouse_SureCart_Products::format_label( 'Adult membership', '£28', '/mo' )
		);
	}

	public function test_a_one_off_label_has_no_dangling_slash(): void {
		$this->assertSame(
			'Life membership — £500',
			Blueworx_Clubhouse_SureCart_Products::format_label( 'Life membership', '£500', '' )
		);
	}

	public function test_it_satisfies_the_products_interface(): void {
		$this->assertTrue(
			in_array( Blueworx_Clubhouse_Products::class, class_implements( Blueworx_Clubhouse_SureCart_Products::class ), true )
		);
	}
}
