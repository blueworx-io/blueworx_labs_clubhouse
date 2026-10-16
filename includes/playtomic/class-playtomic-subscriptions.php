<?php
// includes/playtomic/class-playtomic-subscriptions.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Does this member hold a live SureCart subscription on one of the club's
 * chosen prices? Read through SureCart's own REST route dispatched
 * in-process, the way SureCart_Products reads prices.
 *
 * Verified against SureCart 4.9.5 source on 2026-10-15 (Task 9, step 1):
 * the user's customer ids live in user meta `sc_customer_ids` as an array
 * keyed by mode ('live', 'test'); an older shape is a plain list (index 0).
 * `/surecart/v1/subscriptions` is a standard RestController index. Its
 * permission check lets a user without `read_sc_subscriptions` list only
 * when `customer_ids` is a sequential list of ids that user owns, so this
 * works for the signed-in member or an admin and fails closed for anyone
 * else. `status`, `per_page` and `page` are not declared in SureCart's source
 * (they pass through to its API), so those three are unverified.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Subscriptions {

	public const CUSTOMER_META = 'sc_customer_ids';
	public const ROUTE         = '/surecart/v1/subscriptions';

	/** Customer ids sent in one request. */
	public const CHUNK = 100;

	/** Rows asked for per page, and how many pages one chunk may take. */
	private const PER_PAGE  = 100;
	private const MAX_PAGES = 20;

	/** Statuses that mean the membership is in force. */
	private const LIVE = array( 'active', 'trialing', 'past_due' );

	/** @var (callable(array<int,string>,int):?array<int,mixed>)|null */
	private static $raw_fetcher = null;

	/**
	 * Test-only seam. Receives the customer ids for one request and the page.
	 *
	 * @param (callable(array<int,string>,int):?array<int,mixed>)|null $f
	 */
	public static function set_raw_fetcher( ?callable $f ): void {
		if ( ! defined( 'BLUEWORX_CLUBHOUSE_RUNNING_TESTS' ) || ! BLUEWORX_CLUBHOUSE_RUNNING_TESTS ) {
			return;
		}
		self::$raw_fetcher = $f;
	}

	public static function available(): bool {
		return Blueworx_Clubhouse_SureCart_Products::is_active();
	}

	/** @param array<int,string> $price_ids */
	public static function qualifies( int $user_id, array $price_ids ): bool {
		return array( $user_id ) === self::qualifying_user_ids( array( $user_id ), $price_ids );
	}

	/**
	 * Of these users, the ones holding a live subscription on one of the
	 * prices, found with one request for everyone (one per hundred customer
	 * ids) rather than one per member. Each subscription names its customer,
	 * which leads back to the user. A request that fails counts nobody in it.
	 *
	 * @param array<int,int>    $user_ids
	 * @param array<int,string> $price_ids
	 * @return array<int,int> In the order given.
	 */
	public static function qualifying_user_ids( array $user_ids, array $price_ids ): array {
		if ( array() === $price_ids ) {
			return array();
		}
		$owners = array();
		foreach ( $user_ids as $user_id ) {
			foreach ( self::customer_ids( (int) $user_id ) as $customer ) {
				$owners[ $customer ][] = (int) $user_id;
			}
		}
		if ( array() === $owners ) {
			return array();
		}

		$found = array();
		foreach ( array_chunk( array_map( 'strval', array_keys( $owners ) ), self::CHUNK ) as $customers ) {
			foreach ( self::fetch_all( $customers ) as $sub ) {
				if ( ! is_array( $sub ) || ! in_array( (string) ( $sub['status'] ?? '' ), self::LIVE, true ) ) {
					continue;
				}
				if ( ! in_array( self::id_of( $sub['price'] ?? '' ), $price_ids, true ) ) {
					continue;
				}
				foreach ( $owners[ self::id_of( $sub['customer'] ?? '' ) ] ?? array() as $user_id ) {
					$found[ $user_id ] = true;
				}
			}
		}

		$out = array();
		foreach ( $user_ids as $user_id ) {
			$user_id = (int) $user_id;
			if ( isset( $found[ $user_id ] ) && ! in_array( $user_id, $out, true ) ) {
				$out[] = $user_id;
			}
		}
		return $out;
	}

	/** A reference that is either an id or an expanded object carrying one. */
	private static function id_of( mixed $ref ): string {
		if ( is_array( $ref ) ) {
			return (string) ( $ref['id'] ?? '' );
		}
		return is_scalar( $ref ) ? (string) $ref : '';
	}

	/** @return array<int,string> */
	private static function customer_ids( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::CUSTOMER_META, true );
		if ( ! is_array( $raw ) ) {
			return '' === (string) $raw ? array() : array( (string) $raw );
		}
		return array_values( array_filter( array_map( 'strval', $raw ), static fn( string $id ): bool => '' !== $id ) );
	}

	/**
	 * Every subscription for these customers, page by page. Stops at a short
	 * page, at a page with nothing new (in case paging is ignored), or at a
	 * failed request.
	 *
	 * @param array<int,string> $customers
	 * @return array<int,mixed>
	 */
	private static function fetch_all( array $customers ): array {
		$rows = array();
		$seen = array();
		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$batch = self::fetch( $customers, $page );
			if ( null === $batch ) {
				break;
			}
			$new = 0;
			foreach ( $batch as $sub ) {
				$id = is_array( $sub ) ? self::id_of( $sub['id'] ?? '' ) : '';
				if ( '' !== $id && isset( $seen[ $id ] ) ) {
					continue;
				}
				if ( '' !== $id ) {
					$seen[ $id ] = true;
				}
				$rows[] = $sub;
				++$new;
			}
			if ( count( $batch ) < self::PER_PAGE || 0 === $new ) {
				break;
			}
		}
		return $rows;
	}

	/** @param array<int,string> $customers @return array<int,mixed>|null */
	private static function fetch( array $customers, int $page ): ?array {
		if ( null !== self::$raw_fetcher ) {
			return ( self::$raw_fetcher )( $customers, $page );
		}
		if ( ! self::available() || ! function_exists( 'rest_do_request' ) || ! class_exists( 'WP_REST_Request' ) ) {
			return null;
		}
		try {
			if ( function_exists( 'rest_get_server' ) && function_exists( 'did_action' ) && ! did_action( 'rest_api_init' ) ) {
				do_action( 'rest_api_init', rest_get_server() );
			}
			$request = new WP_REST_Request( 'GET', self::ROUTE );
			$request->set_param( 'customer_ids', $customers );
			$request->set_param( 'status', self::LIVE );
			$request->set_param( 'per_page', self::PER_PAGE );
			$request->set_param( 'page', $page );
			$response = rest_do_request( $request );
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ( method_exists( $response, 'is_error' ) && $response->is_error() ) ) {
			return null;
		}
		$data = $response->get_data();
		return is_array( $data ) ? $data : null;
	}
}
