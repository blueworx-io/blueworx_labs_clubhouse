<?php

use PHPUnit\Framework\TestCase;

final class SureCartProductsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher( null );
		wp_stub_reset();
	}

	/** @param array<string,mixed> $overrides */
	private function raw_price( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'                       => 'p1',
				'amount'                   => 2800,
				'currency'                 => 'gbp',
				'name'                     => null,
				'archived'                 => false,
				'current_version'          => true,
				'recurring_interval'       => 'month',
				'recurring_interval_count' => 1,
				'product'                  => array( 'name' => 'Adult membership' ),
			),
			$overrides
		);
	}

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

	public function test_a_quarterly_price_maps_to_null_rather_than_showing_as_a_one_off(): void {
		// format_period( 'month', 3 ) is '' — silence beside the price is right
		// for the suffix, but a card that then shows "£75" with no period reads
		// as a single payment when the visitor is actually billed every quarter.
		// map_price() must drop the whole price, not just the suffix.
		$price = Blueworx_Clubhouse_SureCart_Products::map_price(
			array(
				'id'                       => 'quarterly-id',
				'name'                     => null,
				'amount'                   => 7500,
				'currency'                 => 'gbp',
				'archived'                 => false,
				'current_version'          => true,
				'recurring_interval'       => 'month',
				'recurring_interval_count' => 3,
				'product'                  => array( 'name' => 'Family membership' ),
			)
		);
		$this->assertNull( $price );
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

	// The tests below exercise prices()/price() end to end via set_raw_fetcher(),
	// the test seam that stands in for rest_do_request() — none of these can be
	// reached through map_price()/is_sellable() alone, since the bug each one
	// guards against lives in prices()'s caching, not the mapping.

	public function test_a_quarterly_price_is_not_offered_by_prices_and_price_returns_null(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher( fn(): array => array(
			$this->raw_price( array( 'id' => 'quarterly-id', 'amount' => 7500, 'recurring_interval_count' => 3 ) ),
		) );

		$products = new Blueworx_Clubhouse_SureCart_Products();
		$this->assertSame( array(), $products->prices() );
		$this->assertNull( $products->price( 'quarterly-id' ) );
	}

	public function test_a_failed_fetch_falls_back_to_the_last_good_list_not_an_empty_one(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher( fn(): array => array( $this->raw_price() ) );

		$products = new Blueworx_Clubhouse_SureCart_Products();
		$first    = $products->prices();
		$this->assertCount( 1, $first );

		// A later request whose cache has expired and whose fetch then fails —
		// e.g. the shop going briefly unreachable. Without the fix this would
		// come back as array(), which the admin picker and Content_Sanitiser's
		// select handling would both read as "the shop has no products",
		// clearing every stored price_id on the next Save.
		$GLOBALS['wp_stub_transients'] = array();
		Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher( fn(): ?array => null );

		$this->assertSame( $first, $products->prices() );
	}

	public function test_a_failed_fetch_is_never_cached_as_an_empty_catalogue(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher( fn(): ?array => null );

		$products = new Blueworx_Clubhouse_SureCart_Products();
		$products->prices(); // the failing fetch

		// The failure is now remembered briefly (FAILURE_TTL, see
		// test_a_second_failure_within_the_window_does_not_repeat_the_fetch),
		// so simulate that window elapsing before checking the property this
		// test actually guards: the failure itself was never written into the
		// normal success cache slot. If it had been, this reset would not
		// matter — the stale "nothing" would already be sitting there under
		// the same key a genuine success also uses.
		$GLOBALS['wp_stub_transients'] = array();
		Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher( fn(): array => array( $this->raw_price() ) );

		$this->assertCount( 1, $products->prices() );
	}

	public function test_a_price_cache_gathered_logged_out_is_not_served_to_a_logged_in_request(): void {
		// rest_do_request() applies the route's own permission callback against
		// the current user, so a logged-out and a logged-in request can
		// legitimately see different prices. The cache is a shared transient;
		// without splitting it by permission context, whichever request
		// populates it first decides what everyone sees for the next 5 minutes.
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		$GLOBALS['wp_stub_logged_in'] = false;
		Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher( fn(): array => array(
			$this->raw_price( array( 'id' => 'guest-visible' ) ),
		) );
		$products = new Blueworx_Clubhouse_SureCart_Products();
		$guest    = $products->prices();
		$this->assertSame( 'guest-visible', $guest[0]['id'] );

		$GLOBALS['wp_stub_logged_in'] = true;
		Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher( fn(): array => array(
			$this->raw_price( array( 'id' => 'auth-only' ) ),
		) );
		$auth = $products->prices();
		$this->assertSame( 'auth-only', $auth[0]['id'], 'the logged-in request must not be served the guest cache entry' );

		$GLOBALS['wp_stub_logged_in'] = false;
	}

	public function test_the_test_seams_are_inert_without_the_running_tests_constant(): void {
		// BLUEWORX_CLUBHOUSE_RUNNING_TESTS is defined once, process-wide, by
		// this suite's own bootstrap, so proving the gate actually gates
		// anything needs a process that never defines it — see the fixture.
		$fixture = __DIR__ . '/fixtures/surecart-seam-gate-check.php';
		$output  = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $fixture ) );
		$result  = json_decode( (string) $output, true );

		$this->assertIsArray( $result, 'fixture did not return valid JSON: ' . (string) $output );
		$this->assertFalse( $result['is_active'], 'set_active_for_tests() must be a no-op without the constant' );
		$this->assertFalse( $result['raw_fetcher_set'], 'set_raw_fetcher() must be a no-op without the constant' );
	}

	/** @return array<string,mixed> */
	private function detection( string $signal ): array {
		$fixture = __DIR__ . '/fixtures/surecart-detection-check.php';
		$output  = shell_exec(
			escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $fixture ) . ' ' . escapeshellarg( $signal )
		);
		$result = json_decode( (string) $output, true );
		$this->assertIsArray( $result, 'fixture did not return valid JSON: ' . (string) $output );
		return $result;
	}

	public function test_a_real_shop_is_detected_by_the_symbols_surecart_actually_defines(): void {
		// The regression this guards is the worst kind: silent and total. The
		// detection used to look for a surecart() function and a
		// \SureCart\SureCart class, neither of which exists in the plugin — so
		// it answered false on every real shop, and the entire integration was
		// dead in production while every test passed, because the tests set the
		// override instead of exercising this.
		$this->assertTrue( $this->detection( 'constant' )['is_active'], 'SURECART_PLUGIN_FILE must count as a shop' );
		$this->assertTrue( $this->detection( 'class' )['is_active'], 'the global SureCart class must count as a shop' );
	}

	public function test_a_site_without_surecart_is_not_mistaken_for_a_shop(): void {
		$this->assertFalse( $this->detection( 'none' )['is_active'] );
	}

	public function test_a_second_failure_within_the_window_does_not_repeat_the_fetch(): void {
		// The regression this guards: a failed fetch was never cached at all,
		// so a sustained outage made every single page render re-run the full
		// fetch_raw() dispatch — including firing rest_api_init and a
		// rest_do_request() that may itself be slow or hanging, exactly when
		// the shop is unhealthy. A short failure marker makes repeated
		// failures cheap while still serving the last-good list.
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		$calls = 0;
		Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher( function () use ( &$calls ): ?array {
			++$calls;
			return null;
		} );

		$products = new Blueworx_Clubhouse_SureCart_Products();
		$products->prices();
		$products->prices();

		$this->assertSame( 1, $calls, 'a second failure within the window must not repeat the expensive fetch' );
	}

	public function test_a_corrupt_last_good_option_is_filtered_rather_than_left_to_fatal(): void {
		// last_good() used to validate only is_array(), then price() indexed
		// $price['id'] on each element. The option never expires and the
		// invalidation hook only clears transients, so a corrupt value (a
		// manual edit, a failed unserialize, a future format change) would
		// otherwise stay forever. Only rows that actually look like a price
		// record must survive.
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );

		$method = new ReflectionMethod( Blueworx_Clubhouse_SureCart_Products::class, 'last_good_option' );
		$method->setAccessible( true );
		$key = $method->invoke( null, 'guest' );

		$good = array( 'id' => 'still-good', 'product' => 'P', 'label' => 'P — £1', 'amount' => '£1', 'period' => '' );
		$GLOBALS['wp_stub_options'][ $key ] = array(
			$good,
			array( 'no' => 'id here' ),
			'not even an array',
			null,
		);

		Blueworx_Clubhouse_SureCart_Products::set_raw_fetcher( fn(): ?array => null );

		$products = new Blueworx_Clubhouse_SureCart_Products();
		$this->assertSame( array( $good ), $products->prices() );
	}

	public function test_the_last_good_option_key_does_not_change_with_the_plugin_version(): void {
		// A permanent safety net keyed by version is empty again after every
		// release until one fetch succeeds — an update followed by an outage
		// wipes every connection on the next Save, exactly the failure the
		// net exists to prevent. The key must not carry the version at all.
		$method = new ReflectionMethod( Blueworx_Clubhouse_SureCart_Products::class, 'last_good_option' );
		$method->setAccessible( true );
		$key = $method->invoke( null, 'guest' );

		$this->assertStringNotContainsString( md5( (string) BLUEWORX_LABS_CLUBHOUSE_VERSION ), $key );
	}
}
