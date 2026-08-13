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

	// The record shapes below are the one from docs/integrations/surecart-notes.md,
	// trimmed and varied to cover the cases the notes called out: a null price
	// name, a one-off with null interval fields, and the archived/current_version
	// booleans.

	public function test_a_monthly_recurring_price_maps_with_the_product_name_and_mo_suffix(): void {
		$price = Blueworx_Clubhouse_SureCart_Products::map_price(
			array(
				'id'                       => 'd31a3fac-1b95-4c45-965b-f55fecc34a58',
				'object'                   => 'price',
				'name'                     => 'Subscribe Monthly & Save',
				'amount'                   => 2900,
				'currency'                 => 'gbp',
				'archived'                 => false,
				'current_version'          => true,
				'recurring_interval'       => 'month',
				'recurring_interval_count' => 1,
				'product'                  => array( 'name' => 'Subscribe & Save Product' ),
			)
		);
		$this->assertSame(
			array(
				'id'      => 'd31a3fac-1b95-4c45-965b-f55fecc34a58',
				'product' => 'Subscribe & Save Product',
				'label'   => 'Subscribe & Save Product — £29/mo',
				'amount'  => '£29',
				'period'  => '/mo',
			),
			$price
		);
	}

	public function test_a_one_off_price_has_null_interval_fields_and_maps_to_no_period(): void {
		// 7 of the 13 real prices had this shape: recurring_interval and
		// recurring_interval_count both null, not absent.
		$price = Blueworx_Clubhouse_SureCart_Products::map_price(
			array(
				'id'                       => 'one-off-id',
				'name'                     => null,
				'amount'                   => 5000,
				'currency'                 => 'gbp',
				'archived'                 => false,
				'current_version'          => true,
				'recurring_interval'       => null,
				'recurring_interval_count' => null,
				'product'                  => array( 'name' => 'Life membership' ),
			)
		);
		$this->assertSame( '', $price['period'] );
		$this->assertSame( '£50', $price['amount'] );
		$this->assertSame( 'Life membership', $price['product'] );
	}

	public function test_a_null_price_name_still_gets_a_usable_label_from_the_product(): void {
		// 3 of the first 6 real prices had a null price name; the label must
		// come from the product, not read as blank.
		$price = Blueworx_Clubhouse_SureCart_Products::map_price(
			array(
				'id'                       => 'null-name-id',
				'name'                     => null,
				'amount'                   => 1200,
				'currency'                 => 'gbp',
				'archived'                 => false,
				'current_version'          => true,
				'recurring_interval'       => 'month',
				'recurring_interval_count' => 1,
				'product'                  => array( 'name' => 'Junior membership' ),
			)
		);
		$this->assertSame( 'Junior membership — £12/mo', $price['label'] );
	}

	public function test_a_record_missing_fields_entirely_does_not_fatal(): void {
		$this->assertNull( Blueworx_Clubhouse_SureCart_Products::map_price( array() ) );
		$this->assertNull( Blueworx_Clubhouse_SureCart_Products::map_price( array( 'id' => 'only-an-id' ) ) );
		// No product name anywhere to build a label from.
		$this->assertNull(
			Blueworx_Clubhouse_SureCart_Products::map_price(
				array( 'id' => 'x', 'amount' => 100, 'currency' => 'gbp', 'name' => null, 'product' => 'a-bare-id-string' )
			)
		);
	}

	public function test_an_archived_price_is_not_sellable(): void {
		$this->assertFalse(
			Blueworx_Clubhouse_SureCart_Products::is_sellable(
				array( 'archived' => true, 'current_version' => true )
			)
		);
	}

	public function test_a_price_that_is_not_the_current_version_is_not_sellable(): void {
		$this->assertFalse(
			Blueworx_Clubhouse_SureCart_Products::is_sellable(
				array( 'archived' => false, 'current_version' => false )
			)
		);
	}

	public function test_an_active_current_price_is_sellable(): void {
		$this->assertTrue(
			Blueworx_Clubhouse_SureCart_Products::is_sellable(
				array( 'archived' => false, 'current_version' => true )
			)
		);
	}
}
