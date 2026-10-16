<?php
// tests/php/PlaytomicSubscriptionsTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicSubscriptionsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( null );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( null );
	}

	public function test_no_price_ids_means_nobody_qualifies(): void {
		$this->assertFalse( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array() ) );
	}

	public function test_user_without_a_customer_id_does_not_qualify_and_never_asks(): void {
		$asked = false;
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( function () use ( &$asked ): ?array { $asked = true; return array(); } );
		$this->assertFalse( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
		$this->assertFalse( $asked );
	}

	public function test_active_subscription_on_a_chosen_price_qualifies(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1' ) );
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( fn( array $customer_ids ): ?array => array(
			array( 'id' => 's1', 'status' => 'active', 'price' => 'p1', 'customer' => 'cust-1' ),
			array( 'id' => 's2', 'status' => 'canceled', 'price' => 'p2', 'customer' => 'cust-1' ),
		) );
		$this->assertTrue( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
		$this->assertFalse( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p2' ) ) );
	}

	public function test_price_may_be_an_expanded_object(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1', 'test' => 'cust-t' ) );
		$seen = array();
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( function ( array $customer_ids ) use ( &$seen ): ?array {
			$seen = $customer_ids;
			return array( array( 'id' => 's1', 'status' => 'trialing', 'price' => array( 'id' => 'p1' ), 'customer' => array( 'id' => 'cust-t' ) ) );
		} );
		$this->assertTrue( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
		$this->assertSame( array( 'cust-1', 'cust-t' ), $seen );
	}

	public function test_a_failed_fetch_does_not_qualify(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1' ) );
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( fn( array $ids, int $page ): ?array => null );
		$this->assertFalse( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
	}

	public function test_everyone_is_checked_with_one_request(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1' ) );
		update_user_meta( 2, 'sc_customer_ids', array( 'live' => 'cust-2' ) );
		update_user_meta( 3, 'sc_customer_ids', array( 'cust-3' ) );
		$calls = array();
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( function ( array $customer_ids, int $page ) use ( &$calls ): ?array {
			$calls[] = $customer_ids;
			return array(
				array( 'id' => 's1', 'status' => 'active', 'price' => 'p1', 'customer' => 'cust-1' ),
				array( 'id' => 's2', 'status' => 'canceled', 'price' => 'p1', 'customer' => 'cust-2' ),
				array( 'id' => 's9', 'status' => 'active', 'price' => 'p1', 'customer' => 'cust-somebody-else' ),
				array( 'id' => 's3', 'status' => 'past_due', 'price' => array( 'id' => 'p2' ), 'customer' => array( 'id' => 'cust-3' ) ),
			);
		} );

		$this->assertSame( array( 1, 3 ), Blueworx_Clubhouse_Playtomic_Subscriptions::qualifying_user_ids( array( 1, 2, 3 ), array( 'p1', 'p2' ) ) );
		$this->assertSame( array( array( 'cust-1', 'cust-2', 'cust-3' ) ), $calls );
	}

	public function test_a_user_whose_customer_is_in_no_row_is_left_out(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1' ) );
		update_user_meta( 2, 'sc_customer_ids', array( 'live' => 'cust-2' ) );
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( fn( array $ids, int $page ): ?array => array(
			array( 'id' => 's1', 'status' => 'active', 'price' => 'p1', 'customer' => 'cust-1' ),
			array( 'id' => 's2', 'status' => 'active', 'price' => 'p1' ),
		) );
		$this->assertSame( array( 1 ), Blueworx_Clubhouse_Playtomic_Subscriptions::qualifying_user_ids( array( 1, 2 ), array( 'p1' ) ) );
	}

	public function test_more_than_a_hundred_customers_are_asked_in_chunks(): void {
		$users = array();
		for ( $i = 1; $i <= 150; $i++ ) {
			update_user_meta( $i, 'sc_customer_ids', array( 'live' => 'cust-' . $i ) );
			$users[] = $i;
		}
		$sizes = array();
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( function ( array $customer_ids, int $page ) use ( &$sizes ): ?array {
			$sizes[] = count( $customer_ids );
			return array( array( 'id' => 's-' . $customer_ids[0], 'status' => 'active', 'price' => 'p1', 'customer' => $customer_ids[0] ) );
		} );
		$this->assertSame( array( 1, 101 ), Blueworx_Clubhouse_Playtomic_Subscriptions::qualifying_user_ids( $users, array( 'p1' ) ) );
		$this->assertSame( array( 100, 50 ), $sizes );
	}

	public function test_a_full_page_asks_for_the_next_one(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1' ) );
		update_user_meta( 2, 'sc_customer_ids', array( 'live' => 'cust-2' ) );
		$pages = array();
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( function ( array $customer_ids, int $page ) use ( &$pages ): ?array {
			$pages[] = $page;
			if ( 1 === $page ) {
				$rows = array();
				for ( $i = 0; $i < 100; $i++ ) {
					$rows[] = array( 'id' => 'old-' . $i, 'status' => 'canceled', 'price' => 'p1', 'customer' => 'cust-1' );
				}
				return $rows;
			}
			return array( array( 'id' => 'live-2', 'status' => 'active', 'price' => 'p1', 'customer' => 'cust-2' ) );
		} );
		$this->assertSame( array( 2 ), Blueworx_Clubhouse_Playtomic_Subscriptions::qualifying_user_ids( array( 1, 2 ), array( 'p1' ) ) );
		$this->assertSame( array( 1, 2 ), $pages );
	}
}
