<?php

use PHPUnit\Framework\TestCase;

final class ContentCatalogueProductsTest extends TestCase {

	/** @return array<string,mixed> The membership tiers section. */
	private function tiers_section( ?Blueworx_Clubhouse_Products $products ): array {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages( $products ) as $page ) {
			if ( 'membership' !== $page['tab'] ) {
				continue;
			}
			foreach ( $page['sections'] as $section ) {
				if ( 'tiers' === $section['key'] ) {
					return $section;
				}
			}
		}
		$this->fail( 'the membership tiers section has gone' );
	}

	/** @return array<string,mixed> The price_id field of that section's loop. */
	private function price_field( ?Blueworx_Clubhouse_Products $products ): array {
		foreach ( $this->tiers_section( $products )['loop']['fields'] as $field ) {
			if ( 'price_id' === $field['key'] ) {
				return $field;
			}
		}
		$this->fail( 'the tier has no product field' );
	}

	public function test_with_a_shop_every_price_is_offered(): void {
		$options = $this->price_field( new Blueworx_Clubhouse_Demo_Products() )['options'];
		$this->assertSame( '', array_key_first( $options ) );
		$this->assertArrayHasKey( 'price_adult_monthly', $options );
		$this->assertSame( 'Adult membership — £28/mo', $options['price_adult_monthly'] );
	}

	public function test_the_annual_price_can_be_connected_to_its_own_product(): void {
		// Both cadences pick from the same shop prices; a tier that sells
		// annually must be able to name the annual price, not reuse the monthly.
		$annual = null;
		foreach ( $this->tiers_section( new Blueworx_Clubhouse_Demo_Products() )['loop']['fields'] as $field ) {
			if ( 'price_id_annual' === $field['key'] ) {
				$annual = $field;
			}
		}
		$this->assertNotNull( $annual, 'the tier has no annual product field' );
		$this->assertSame( 'select', $annual['type'] );
		$this->assertArrayHasKey( 'price_adult_yearly', $annual['options'] );
		$this->assertSame( $this->price_field( new Blueworx_Clubhouse_Demo_Products() )['options'], $annual['options'] );
	}

	public function test_a_tier_offers_a_typed_annual_price(): void {
		$keys = array_column( $this->tiers_section( null )['loop']['fields'], 'key' );
		$this->assertContains( 'price_annual', $keys );
	}

	public function test_with_no_shop_only_not_connected_is_offered(): void {
		$options = $this->price_field( null )['options'];
		$this->assertSame( array( '' ), array_keys( $options ) );
	}

	public function test_the_section_says_why_there_is_nothing_to_connect_to(): void {
		$note = (string) $this->tiers_section( null )['note'];
		$this->assertNotSame( '', $note );
	}

	public function test_the_field_is_a_select_so_it_validates_against_its_options(): void {
		$this->assertSame( 'select', $this->price_field( new Blueworx_Clubhouse_Demo_Products() )['type'] );
	}

	/** A value the shop no longer offers must be rejected, not stored. */
	public function test_sanitising_rejects_a_price_that_is_not_offered(): void {
		$field = $this->price_field( new Blueworx_Clubhouse_Demo_Products() );
		$this->assertSame( '', Blueworx_Clubhouse_Content_Sanitiser::field( $field, 'price_made_up', true ) );
		$this->assertSame( 'price_adult_monthly', Blueworx_Clubhouse_Content_Sanitiser::field( $field, 'price_adult_monthly', true ) );
	}

	public function test_no_other_section_gained_a_product_field(): void {
		$found = 0;
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages( new Blueworx_Clubhouse_Demo_Products() ) as $page ) {
			foreach ( $page['sections'] as $section ) {
				foreach ( $section['loop']['fields'] ?? array() as $field ) {
					if ( 'price_id' === $field['key'] ) {
						++$found;
					}
				}
			}
		}
		$this->assertSame( 1, $found );
	}

	public function test_a_vanished_product_is_shown_as_such_rather_than_as_not_connected(): void {
		$field = $this->price_field( new Blueworx_Clubhouse_Demo_Products() );
		$html  = Blueworx_Clubhouse_Content_Screen::field_html( $field, 'price_vanished', 'tier-0-price_id' );

		// The stale value is still the selected one...
		$this->assertStringContainsString( 'value="price_vanished"', $html );
		$this->assertMatchesRegularExpression( '/value="price_vanished"[^>]*selected/', $html );
		// ...and it says what happened, rather than reading as "Not connected".
		$this->assertStringContainsString( 'longer available', $html );
	}

	public function test_a_normal_value_gains_no_extra_option(): void {
		$field = $this->price_field( new Blueworx_Clubhouse_Demo_Products() );
		$html  = Blueworx_Clubhouse_Content_Screen::field_html( $field, 'price_adult_monthly', 'tier-0-price_id' );
		$this->assertSame( 4, substr_count( $html, '<option ' ) );
	}
}
