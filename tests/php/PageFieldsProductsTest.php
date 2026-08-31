<?php
// tests/php/PageFieldsProductsTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * A membership tier's "Sells" picker is the one field whose options come from
 * outside this plugin — the shop's own prices. Everything that reads the page
 * fields has to pass the shop in, or the picker has nothing but its empty
 * option and a real value is cleaned straight back to '': see
 * ImportParserContentTest for the import's side of the same rule.
 */
final class PageFieldsProductsTest extends TestCase {

	protected function setUp(): void {
		Blueworx_Clubhouse_Page_Fields::forget();
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_Page_Fields::forget();
	}

	/** @return array<string,mixed> The membership tiers section. */
	private function tiers( ?Blueworx_Clubhouse_Products $products ): array {
		$sections = Blueworx_Clubhouse_Page_Fields::sections( $products );
		$this->assertArrayHasKey( 'membership/tiers', $sections, 'the membership tiers section has gone' );
		return $sections['membership/tiers'];
	}

	/** @return array<string,mixed> One cell of a tier row. */
	private function cell( ?Blueworx_Clubhouse_Products $products, string $id ): array {
		foreach ( $this->tiers( $products )['items']['fields'] as $cell ) {
			if ( $id === $cell['id'] ) {
				return $cell;
			}
		}
		$this->fail( sprintf( 'a tier has no "%s" field', $id ) );
	}

	/** @return array<string,string> value => label, in the order offered. */
	private function options( ?Blueworx_Clubhouse_Products $products, string $id ): array {
		return array_column( $this->cell( $products, $id )['options'], 'label', 'value' );
	}

	public function test_with_a_shop_every_price_is_offered(): void {
		$options = $this->options( new Blueworx_Clubhouse_Demo_Products(), 'price_id' );
		$this->assertSame( '', array_key_first( $options ) );
		$this->assertArrayHasKey( 'price_adult_monthly', $options );
		$this->assertSame( 'Adult membership — £28/mo', $options['price_adult_monthly'] );
	}

	public function test_the_annual_price_can_be_connected_to_its_own_product(): void {
		// Both cadences pick from the same shop prices; a tier that sells
		// annually must be able to name the annual price, not reuse the monthly.
		$products = new Blueworx_Clubhouse_Demo_Products();
		$annual   = $this->options( $products, 'price_id_annual' );
		$this->assertSame( 'select', $this->cell( $products, 'price_id_annual' )['kind'] );
		$this->assertArrayHasKey( 'price_adult_yearly', $annual );
		$this->assertSame( $this->options( $products, 'price_id' ), $annual );
	}

	public function test_a_tier_offers_a_typed_annual_price(): void {
		$this->assertContains( 'price_annual', array_column( $this->tiers( null )['items']['fields'], 'id' ) );
	}

	public function test_with_no_shop_only_not_connected_is_offered(): void {
		$this->assertSame( array( '' ), array_keys( $this->options( null, 'price_id' ) ) );
	}

	public function test_the_section_says_why_there_is_nothing_to_connect_to(): void {
		$this->assertNotSame( '', $this->tiers( null )['note'] );
	}

	public function test_the_field_is_a_select_so_it_validates_against_its_options(): void {
		$this->assertSame( 'select', $this->cell( new Blueworx_Clubhouse_Demo_Products(), 'price_id' )['kind'] );
	}

	/** A value the shop no longer offers must be rejected, not stored. */
	public function test_cleaning_rejects_a_price_that_is_not_offered(): void {
		$field = $this->cell( new Blueworx_Clubhouse_Demo_Products(), 'price_id' );
		$this->assertSame( '', \Blueworx\PageEditor\v1\Sanitise::field( $field, 'price_made_up' ) );
		$this->assertSame( 'price_adult_monthly', \Blueworx\PageEditor\v1\Sanitise::field( $field, 'price_adult_monthly' ) );
	}

	public function test_no_other_section_gained_a_product_field(): void {
		$found = 0;
		foreach ( Blueworx_Clubhouse_Page_Fields::sections( new Blueworx_Clubhouse_Demo_Products() ) as $section ) {
			foreach ( $section['items']['fields'] ?? array() as $cell ) {
				if ( 'price_id' === $cell['id'] ) {
					++$found;
				}
			}
		}
		$this->assertSame( 1, $found );
	}
}
