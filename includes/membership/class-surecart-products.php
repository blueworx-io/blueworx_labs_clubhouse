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

	/**
	 * How long a failed fetch is remembered before the next request is allowed
	 * to try again. Short enough that a genuine recovery is picked up quickly,
	 * long enough that a sustained outage does not make every single page
	 * render pay for the full fetch_raw() dispatch — firing rest_api_init and
	 * a rest_do_request() that may itself be slow or hanging, exactly when the
	 * shop is unhealthy and that cost matters most.
	 */
	private const FAILURE_TTL = 30;

	/**
	 * Test-only seam: overrides how raw price records are obtained, bypassing
	 * rest_do_request() so prices()/price() can be exercised end to end without
	 * a WordPress REST server. Null (the default) uses the real dispatch in
	 * fetch_raw(). Return null from this to simulate a failed fetch.
	 *
	 * @var (callable():?array<int,mixed>)|null
	 */
	private static $raw_fetcher = null;

	/**
	 * Gated on BLUEWORX_CLUBHOUSE_RUNNING_TESTS (see tests/php/bootstrap.php):
	 * this is the more dangerous of the two test seams on this class — an
	 * arbitrary callable that would otherwise inject price records and labels
	 * straight onto the front end of a live site. A no-op everywhere the
	 * constant is not defined, which is every real request.
	 *
	 * @param (callable():?array<int,mixed>)|null $fetcher
	 */
	public static function set_raw_fetcher( ?callable $fetcher ): void {
		if ( ! defined( 'BLUEWORX_CLUBHOUSE_RUNNING_TESTS' ) ) {
			return;
		}
		self::$raw_fetcher = $fetcher;
	}

	/** @return array<int,array{id:string,product:string,label:string,amount:string,period:string}> */
	public function prices(): array {
		$context = self::cache_context();
		$cached  = get_transient( self::cache_key( $context ) );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( false !== get_transient( self::failure_cache_key( $context ) ) ) {
			// A failure was already recorded moments ago — skip repeating the
			// expensive dispatch (rest_api_init + rest_do_request, see
			// fetch_raw()) on every request during an outage. Whatever was last
			// fetched successfully is still the right thing to serve.
			return self::last_good( $context );
		}

		$prices = self::fetch_prices();
		if ( null === $prices ) {
			// A failed fetch (API outage, permission denial, anything) is not the
			// same fact as a genuinely empty catalogue, and must not be cached the
			// same way — doing so would make the admin picker read "your shop has
			// no products" for a full TTL, which the Content editor's select then
			// treats as license to clear every stored price_id on the next Save.
			// Remember only that it failed, briefly, so repeated failures are
			// cheap — see FAILURE_TTL — and serve whatever was last fetched
			// successfully in this same permission context, so a blip never
			// looks (or behaves) like the shop has nothing to sell.
			set_transient( self::failure_cache_key( $context ), true, self::FAILURE_TTL );
			return self::last_good( $context );
		}

		set_transient( self::cache_key( $context ), $prices, self::CACHE_TTL );
		// No expiry: this is read only when a later fetch fails, so it must
		// still be here whenever that happens, however long ago the last
		// success was.
		update_option( self::last_good_option( $context ), $prices, false );
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
	 * Test-only seam, same pattern as Shortcodes::set_expander(): overrides
	 * is_active() so prices()/price() can be exercised end to end (together
	 * with set_raw_fetcher()) without depending on a real surecart() function
	 * or \SureCart\SureCart class existing in the test process. Null (default)
	 * uses the real detection below.
	 *
	 * @var bool|null
	 */
	private static ?bool $active_override = null;

	public static function set_active_for_tests( ?bool $active ): void {
		if ( ! defined( 'BLUEWORX_CLUBHOUSE_RUNNING_TESTS' ) ) {
			return;
		}
		self::$active_override = $active;
	}

	/**
	 * Whether SureCart is live on this site. Guards every other method: false
	 * here means every caller sees an empty list or null, never a fatal.
	 */
	public static function is_active(): bool {
		if ( null !== self::$active_override ) {
			return self::$active_override;
		}
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
			// Both permission contexts' caches, not just the one that happened
			// to populate first — see cache_context().
			foreach ( self::CACHE_CONTEXTS as $context ) {
				delete_transient( self::cache_key( $context ) );
				delete_transient( self::failure_cache_key( $context ) );
			}
		};
		add_action( 'surecart/price/saved', $invalidate );
		add_action( 'surecart/product/saved', $invalidate );
		add_action( 'save_post_sc_product', $invalidate );
	}

	/** The two permission contexts a price cache can be gathered under — see cache_context(). */
	private const CACHE_CONTEXTS = array( 'guest', 'auth' );

	/**
	 * Fetch every sellable price from SureCart, mapped into this plugin's price
	 * array, or null when the fetch itself failed — see fetch_raw(). Any
	 * per-record failure still yields no fatal, only that one record dropped.
	 *
	 * @return array<int,array{id:string,product:string,label:string,amount:string,period:string}>|null
	 */
	private static function fetch_prices(): ?array {
		if ( ! self::is_active() ) {
			return array();
		}
		$data = self::fetch_raw();
		if ( null === $data ) {
			return null;
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
	 * Dispatch the verified REST route internally rather than through an
	 * unverified PHP model — see the class docblock. Returns null whenever the
	 * fetch itself could not be trusted (missing REST plumbing, an exception, an
	 * error response, a malformed body) — deliberately distinct from array(),
	 * which means "asked, and there is nothing to sell". prices() depends on
	 * that distinction to avoid caching an outage as an empty shop.
	 *
	 * **Unverified**: whether this route is readable by a logged-out visitor
	 * has never been checked against a live site — see
	 * docs/integrations/surecart-notes.md and the task-6 report's assumptions
	 * list. rest_do_request() applies the route's own permission callback
	 * against the current request's user, so if the route requires a
	 * capability, a logged-out visitor gets exactly this failure path (null),
	 * and — before the cache_context() split below — would have been served
	 * whatever a logged-in admin's request happened to cache first.
	 *
	 * @return array<int,mixed>|null
	 */
	private static function fetch_raw(): ?array {
		if ( null !== self::$raw_fetcher ) {
			return ( self::$raw_fetcher )();
		}
		if ( ! function_exists( 'rest_do_request' ) || ! class_exists( 'WP_REST_Request' ) ) {
			return null;
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
			return null;
		}

		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
			return null;
		}
		if ( method_exists( $response, 'is_error' ) && $response->is_error() ) {
			return null;
		}

		$data = $response->get_data();
		return is_array( $data ) ? $data : null;
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

		// format_period() returns '' both for a genuine one-off (no interval at
		// all) and for a cadence it has no words for (e.g. every 3 months) —
		// silence is right for the suffix beside a price, but wrong for the
		// tier itself: showing "£75" with no period on a recurring membership
		// reads as a single payment. A price that IS recurring but cannot be
		// labelled must drop the whole price, not just the suffix, so the tier
		// falls back to its typed price exactly as an archived price does.
		if ( '' !== $interval && '' === $period ) {
			return null;
		}

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

	/**
	 * Which permission context the current request fetches under. SureCart's
	 * route applies its own permission callback to the current user
	 * (fetch_raw()), and this cache is a shared transient — without this split,
	 * whichever request populates it first (an admin browsing the picker, or a
	 * logged-out visitor) decides what every other visitor sees for the rest of
	 * the TTL, including a privileged price a logged-out visitor should never
	 * have been shown, or vice versa.
	 */
	private static function cache_context(): string {
		return ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) ? 'auth' : 'guest';
	}

	/** Transient key, scoped to the running plugin version like Theme_Cache, and to cache_context(). */
	private static function cache_key( string $context ): string {
		$version = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : 'dev';
		return 'blueworx_clubhouse_surecart_prices_' . $context . '_' . md5( (string) $version );
	}

	/** Transient key for the short "a fetch just failed" marker — see FAILURE_TTL. */
	private static function failure_cache_key( string $context ): string {
		return 'blueworx_clubhouse_surecart_prices_failed_' . $context;
	}

	/**
	 * The last successfully fetched price list for a permission context, or
	 * array() when none has ever succeeded. Stored as an option (no expiry),
	 * not a transient — it must outlive the transient TTL, since it is only
	 * ever read after a fetch has already failed.
	 *
	 * Filters out anything that does not look like a price record: the option
	 * has no expiry and nothing else validates it once written, so a stored
	 * value corrupted by a manual edit, a failed unserialize, or a future
	 * format change must not fatal price()'s $price['id'] lookup — it should
	 * simply be treated as never having been there.
	 */
	private static function last_good( string $context ): array {
		$stored = get_option( self::last_good_option( $context ), array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		return array_values( array_filter( $stored, array( self::class, 'looks_like_a_price' ) ) );
	}

	/** @param mixed $row */
	private static function looks_like_a_price( $row ): bool {
		return is_array( $row ) && isset( $row['id'] ) && is_string( $row['id'] ) && '' !== $row['id'];
	}

	/**
	 * Deliberately NOT scoped to the plugin version, unlike cache_key(): this
	 * option is the permanent safety net a transient blip falls back to, and
	 * every release changes the version — keying it there would empty the net
	 * on every update until one fetch happened to succeed again, which is the
	 * exact failure this option exists to prevent.
	 */
	private static function last_good_option( string $context ): string {
		return 'blueworx_clubhouse_surecart_prices_last_good_' . $context;
	}
}
