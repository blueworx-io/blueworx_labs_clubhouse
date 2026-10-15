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
			array( 'id' => 's1', 'status' => 'active', 'price' => 'p1' ),
			array( 'id' => 's2', 'status' => 'canceled', 'price' => 'p2' ),
		) );
		$this->assertTrue( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
		$this->assertFalse( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p2' ) ) );
	}

	public function test_price_may_be_an_expanded_object(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1', 'test' => 'cust-t' ) );
		$seen = array();
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( function ( array $customer_ids ) use ( &$seen ): ?array {
			$seen = $customer_ids;
			return array( array( 'id' => 's1', 'status' => 'trialing', 'price' => array( 'id' => 'p1' ) ) );
		} );
		$this->assertTrue( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
		$this->assertSame( array( 'cust-1', 'cust-t' ), $seen );
	}

	public function test_a_failed_fetch_does_not_qualify(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1' ) );
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( fn( array $ids ): ?array => null );
		$this->assertFalse( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
	}
}
