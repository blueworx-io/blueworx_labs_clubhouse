<?php

use PHPUnit\Framework\TestCase;

/**
 * Issue #295: a tier connected to a product the shop no longer sells.
 *
 * The picker is built from its declared options and never sees the stored
 * value, so a deleted product left the tier reading "Not connected" while it
 * went on charging exactly what it always had. Nothing was lost — but an owner
 * could reconnect it to the wrong thing without ever being told the old one
 * had gone.
 */
final class SoldOutTierPriceTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		\Blueworx\PageEditor\v1\Editor::reset();
		Blueworx_Clubhouse_Page_Fields::forget();
	}

	protected function tearDown(): void {
		\Blueworx\PageEditor\v1\Editor::reset();
		Blueworx_Clubhouse_Page_Fields::forget();
	}

	/** The membership screen's tier repeater, as the library received it. */
	private function tier_cells(): array {
		$screen = \Blueworx\PageEditor\v1\Editor::get(
			Blueworx_Clubhouse_Page_Editors::slug_for( 'membership' )
		);
		$this->assertNotNull( $screen, 'the membership editor was never registered' );

		// A section's repeater is declared as "<section>_items" — see
		// Page_Fields::repeater() and REPEATER_FIELD.
		foreach ( $screen['tabs'] as $tab ) {
			foreach ( $tab['panels'] as $panel ) {
				foreach ( $panel['fields'] as $field ) {
					if ( 'tiers_' . Blueworx_Clubhouse_Page_Fields::REPEATER_FIELD === ( $field['id'] ?? '' ) ) {
						return array_column( $field['fields'], null, 'id' );
					}
				}
			}
		}
		$this->fail( 'the tiers repeater is not on the membership screen' );
	}

	private function given_a_tier_connected_to( string $price_id ): void {
		// A club page's content lives on the page itself, so there has to be a
		// page for it to live on.
		update_option( 'clubhouse_page_id_membership', 42 );
		( new Blueworx_Clubhouse_Page_Content() )->set_items(
			'membership',
			'tiers',
			array( array( 'name' => 'Full member', 'price_id' => $price_id ) )
		);
	}

	public function test_a_product_the_shop_no_longer_sells_stays_on_the_picker(): void {
		$this->given_a_tier_connected_to( 'price_gone' );

		Blueworx_Clubhouse_Page_Editors::declare_screens();

		$options = array_column( $this->tier_cells()['price_id']['options'], 'label', 'value' );
		$this->assertArrayHasKey( 'price_gone', $options );
		$this->assertStringContainsString( 'No longer in your shop', $options['price_gone'] );
	}

	/** The annual picker has the same problem and the same answer. */
	public function test_the_annual_picker_keeps_it_too(): void {
		$this->given_a_tier_connected_to( 'price_gone' );

		Blueworx_Clubhouse_Page_Editors::declare_screens();

		$this->assertArrayHasKey(
			'price_gone',
			array_column( $this->tier_cells()['price_id_annual']['options'], 'label', 'value' )
		);
	}

	public function test_a_club_with_no_tiers_is_offered_nothing_extra(): void {
		Blueworx_Clubhouse_Page_Editors::declare_screens();

		$values = array_column( $this->tier_cells()['price_id']['options'], 'value' );
		$this->assertSame( array( '' ), $values );
	}

	/** A tier with no product connected is not a missing product. */
	public function test_an_unconnected_tier_adds_no_option(): void {
		$this->given_a_tier_connected_to( '' );

		Blueworx_Clubhouse_Page_Editors::declare_screens();

		$this->assertSame( array( '' ), array_column( $this->tier_cells()['price_id']['options'], 'value' ) );
	}
}
