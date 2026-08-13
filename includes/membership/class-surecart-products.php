<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The only class in the plugin that knows SureCart exists.
 *
 * Built from docs/integrations/surecart-notes.md, a record of a real SureCart
 * install read over its REST API. That is also how prices are read here: the
 * notes verified the exact endpoint and field shapes
 * (GET /wp-json/surecart/v1/prices?expand[]=product) but could not verify any
 * PHP model class, because the access used to write them was REST-only. Rather
 * than guess an unverified class name, this dispatches that same verified
 * request internally through WordPress's own REST server — no HTTP round trip,
 * and nothing here depends on a SureCart PHP symbol that might not exist.
 *
 * Every SureCart-facing call is guarded so a site with no shop, or a shop whose
 * API has moved on, returns an empty price list and a null price rather than
 * fataling — this class loads on every request.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_SureCart_Products implements Blueworx_Clubhouse_Products {

	/** The REST route the notes observed carrying every sellable price. */
	private const PRICES_ROUTE = '/surecart/v1/prices';

	/**
	 * Cache invalidation hooks were never observed — the access used to write
	 * the notes was REST-only, so no PHP save hook could be exercised. Rather
	 * than leave a cache nothing can clear, this expiry is short enough that a
	 * price change is never stale for long even if register()'s hooks below
	 * turn out to be the wrong names, or SureCart fires none of them.
	 */
	private const CACHE_TTL = 300;

	/** @return array<int,array{id:string,product:string,label:string,amount:string,period:string}> */
	public function prices(): array {
		$cached = get_transient( self::cache_key() );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$prices = self::fetch_prices();
		set_transient( self::cache_key(), $prices, self::CACHE_TTL );
		return $prices;
	}

	/** @return array{id:string,product:string,label:string,amount:string,period:string}|null */
	public function price( string $id ): ?array {
		if ( '' === $id ) {
			return null;
		}
		foreach ( $this->prices() as $price ) {
			if ( $price['id'] === $id ) {
				return $price;
			}
		}
		return null;
	}

	/**
	 * Whether SureCart is live on this site. Guards every other method: false
	 * here means every caller sees an empty list or null, never a fatal.
	 */
	public static function is_active(): bool {
		return function_exists( 'surecart' ) || class_exists( '\SureCart\SureCart' );
	}

	/**
	 * SureCart's checkout page URL, or '' when it has none — which is the case
	 * on the club's own site today (issue #150). The notes could not verify
	 * where SureCart keeps this, because that site has no checkout page to
	 * observe; the fallback of returning '' is correct either way, since it
	 * makes every tier fall back to its typed price and the contact link.
	 */
	public static function checkout_url(): string {
		if ( ! self::is_active() || ! function_exists( 'get_option' ) ) {
			return '';
		}
		$page_id = get_option( 'surecart_checkout_page_id', 0 );
		if ( ! is_numeric( $page_id ) || (int) $page_id <= 0 ) {
			return '';
		}
		$url = function_exists( 'get_permalink' ) ? get_permalink( (int) $page_id ) : false;
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Hook cache invalidation to SureCart's save actions, so a price change
	 * shows without waiting for the transient to expire. The hook names below
	 * are the plugin's best-effort guess, not something the notes could
	 * confirm — add_action() registering a hook that never fires is harmless,
	 * and CACHE_TTL is the real safety net regardless of whether these fire.
	 */
	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		$invalidate = static function (): void {
			delete_transient( self::cache_key() );
		};
		add_action( 'surecart/price/saved', $invalidate );
		add_action( 'surecart/product/saved', $invalidate );
		add_action( 'save_post_sc_product', $invalidate );
	}

	/**
	 * Fetch every sellable price from SureCart, mapped into this plugin's price
	 * array. Dispatches the verified REST route internally rather than through
	 * an unverified PHP model — see the class docblock. Any failure at any step
	 * yields an empty array, never a notice or a fatal.
	 *
	 * @return array<int,array{id:string,product:string,label:string,amount:string,period:string}>
	 */
	private static function fetch_prices(): array {
		if ( ! self::is_active() ) {
			return array();
		}
		if ( ! function_exists( 'rest_do_request' ) || ! class_exists( 'WP_REST_Request' ) ) {
			return array();
		}

		try {
			// The internal REST server only knows routes registered on
			// rest_api_init, which a normal page render never fires. Firing it
			// once, guarded by did_action(), makes the dispatch below see
			// SureCart's routes without a real HTTP round trip.
			//
			// This is NOT scoped to SureCart: rest_api_init is a global action,
			// so firing it here also runs every other plugin's REST-registration
			// callback that has not already run. Guarded by is_active() (only
			// reachable from fetch_prices(), which only runs when SureCart is
			// live) and did_action() (fires at most once per request), so the
			// blast radius is one extra global hook run on the first page load
			// that needs a price — not a call anyone should widen without
			// re-checking that reasoning.
			if ( function_exists( 'rest_get_server' ) && function_exists( 'did_action' ) && ! did_action( 'rest_api_init' ) ) {
				do_action( 'rest_api_init', rest_get_server() );
			}

			$request = new WP_REST_Request( 'GET', self::PRICES_ROUTE );
			$request->set_param( 'per_page', 50 );
			$request->set_param( 'expand', array( 'product' ) );
			$response = rest_do_request( $request );
		} catch ( \Throwable $e ) {
			return array();
		}

		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
			return array();
		}
		if ( method_exists( $response, 'is_error' ) && $response->is_error() ) {
			return array();
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return array();
		}

		$prices = array();
		foreach ( $data as $raw ) {
			if ( ! is_array( $raw ) || ! self::is_sellable( $raw ) ) {
				continue;
			}
			$mapped = self::map_price( $raw );
			if ( null !== $mapped ) {
				$prices[] = $mapped;
			}
		}
		return $prices;
	}

	/**
	 * A price is sellable when it is not archived, not draft, and — when
	 * SureCart says so — not superseded by a newer version.
	 *
	 * current_version was true on every price the notes observed, so a
	 * superseded price's value there is unverified — treated as not sellable
	 * only when explicitly false, never assumed from an absent field.
	 *
	 * No draft field was ever observed either — the 13 real prices in the
	 * notes carried no status/draft/published key at all, so this cannot say
	 * what SureCart actually calls it. The three checks below are a cheap,
	 * fail-closed guess at the plausible names; each only excludes a price
	 * when the field is present AND says unpublished, so they cost nothing on
	 * the (verified) shape the notes recorded and do nothing harmful if the
	 * real field is named something else entirely. See the "draft" entry in
	 * the report's assumptions list — if SureCart's real draft field doesn't
	 * match one of these, a draft price would still reach this list.
	 *
	 * @param array<string,mixed> $price
	 */
	public static function is_sellable( array $price ): bool {
		if ( ! empty( $price['archived'] ) ) {
			return false;
		}
		if ( array_key_exists( 'current_version', $price ) && false === $price['current_version'] ) {
			return false;
		}
		if ( array_key_exists( 'status', $price ) && is_string( $price['status'] ) && 'draft' === strtolower( $price['status'] ) ) {
			return false;
		}
		if ( array_key_exists( 'draft', $price ) && true === $price['draft'] ) {
			return false;
		}
		if ( array_key_exists( 'published', $price ) && false === $price['published'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Map one raw SureCart price into this plugin's display-ready array, or
	 * null when the record is missing something this cannot safely label or
	 * price — better absent from the picker than shown wrong.
	 *
	 * @param array<string,mixed> $price
	 * @return array{id:string,product:string,label:string,amount:string,period:string}|null
	 */
	public static function map_price( array $price ): ?array {
		if ( ! isset( $price['id'], $price['amount'], $price['currency'] ) ) {
			return null;
		}
		if ( ! is_string( $price['id'] ) || '' === $price['id'] ) {
			return null;
		}
		if ( ! is_numeric( $price['amount'] ) || ! is_string( $price['currency'] ) ) {
			return null;
		}

		// The price's own name is frequently null (docs/integrations/surecart-notes.md);
		// the product's is what the picker label actually needs.
		$product_name = '';
		if ( isset( $price['product'] ) && is_array( $price['product'] ) && isset( $price['product']['name'] ) && is_string( $price['product']['name'] ) ) {
			$product_name = $price['product']['name'];
		} elseif ( isset( $price['name'] ) && is_string( $price['name'] ) ) {
			$product_name = $price['name'];
		}
		if ( '' === $product_name ) {
			return null;
		}

		$interval = ( isset( $price['recurring_interval'] ) && is_string( $price['recurring_interval'] ) ) ? $price['recurring_interval'] : '';
		$count    = ( isset( $price['recurring_interval_count'] ) && is_numeric( $price['recurring_interval_count'] ) ) ? (int) $price['recurring_interval_count'] : 0;

		$amount = self::format_amount( (int) $price['amount'], $price['currency'] );
		$period = self::format_period( $interval, $count );

		return array(
			'id'      => $price['id'],
			'product' => $product_name,
			'label'   => self::format_label( $product_name, $amount, $period ),
			'amount'  => $amount,
			'period'  => $period,
		);
	}

	/** GBP 2800 becomes "£28"; 2850 becomes "£28.50". */
	public static function format_amount( int $minor_units, string $currency ): string {
		$symbols = array( 'GBP' => '£', 'EUR' => '€', 'USD' => '$' );
		$major   = $minor_units / 100;
		// Whole amounts read as prices; part amounts need both decimals or £28.5
		// looks like a typo on a club's own page.
		$number  = ( 0 === $minor_units % 100 ) ? (string) (int) $major : number_format( $major, 2 );
		$symbol  = $symbols[ strtoupper( $currency ) ] ?? '';

		// An unknown currency gets its code rather than a guessed symbol: "28 NOK"
		// is merely plain, the wrong symbol is wrong.
		return '' === $symbol ? $number . ' ' . strtoupper( $currency ) : $symbol . $number;
	}

	/**
	 * The suffix the tier card shows beside the price. Anything that is not a
	 * plain monthly or yearly subscription gets none — the card has no words for
	 * "every 3 months", and silence beside a price beats a wrong claim about it.
	 */
	public static function format_period( string $interval, int $count ): string {
		if ( 1 !== $count ) {
			return '';
		}
		if ( 'month' === $interval ) {
			return '/mo';
		}
		if ( 'year' === $interval ) {
			return '/yr';
		}
		return '';
	}

	/** The picker's label: "Adult membership — £28/mo", or no suffix for a one-off. */
	public static function format_label( string $product, string $amount, string $period ): string {
		return $product . ' — ' . $amount . $period;
	}

	/** Transient key, scoped to the running plugin version like Theme_Cache. */
	private static function cache_key(): string {
		$version = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : 'dev';
		return 'blueworx_clubhouse_surecart_prices_' . md5( (string) $version );
	}
}
