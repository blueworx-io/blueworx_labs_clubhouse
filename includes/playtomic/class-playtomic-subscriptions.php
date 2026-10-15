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
 * else. `status` and `per_page` are not declared in SureCart's source (they
 * pass through to its API), so those two are unverified.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Subscriptions {

	public const CUSTOMER_META = 'sc_customer_ids';
	public const ROUTE         = '/surecart/v1/subscriptions';

	/** Statuses that mean the membership is in force. */
	private const LIVE = array( 'active', 'trialing', 'past_due' );

	/** @var (callable(array<int,string>):?array<int,mixed>)|null */
	private static $raw_fetcher = null;

	/** Test-only seam. @param (callable(array<int,string>):?array<int,mixed>)|null $f */
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
		if ( array() === $price_ids ) {
			return false;
		}
		$customers = self::customer_ids( $user_id );
		if ( array() === $customers ) {
			return false;
		}
		$subscriptions = self::fetch( $customers );
		if ( null === $subscriptions ) {
			return false;
		}
		foreach ( $subscriptions as $sub ) {
			if ( ! is_array( $sub ) || ! in_array( (string) ( $sub['status'] ?? '' ), self::LIVE, true ) ) {
				continue;
			}
			$price = $sub['price'] ?? '';
			$price = is_array( $price ) ? (string) ( $price['id'] ?? '' ) : (string) $price;
			if ( in_array( $price, $price_ids, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** @return array<int,string> */
	private static function customer_ids( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::CUSTOMER_META, true );
		if ( ! is_array( $raw ) ) {
			return '' === (string) $raw ? array() : array( (string) $raw );
		}
		return array_values( array_filter( array_map( 'strval', $raw ), static fn( string $id ): bool => '' !== $id ) );
	}

	/** @param array<int,string> $customers @return array<int,mixed>|null */
	private static function fetch( array $customers ): ?array {
		if ( null !== self::$raw_fetcher ) {
			return ( self::$raw_fetcher )( $customers );
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
			$request->set_param( 'per_page', 100 );
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
