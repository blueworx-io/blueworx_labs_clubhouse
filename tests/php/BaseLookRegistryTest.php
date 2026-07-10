<?php
// tests/php/BaseLookRegistryTest.php

use PHPUnit\Framework\TestCase;

final class BaseLookRegistryTest extends TestCase {

	private function registry(): Blueworx_Clubhouse_Base_Look_Registry {
		return new Blueworx_Clubhouse_Base_Look_Registry( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_register_and_get(): void {
		$r = $this->registry();
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'court-side', 'Court Side' ) );
		$this->assertTrue( $r->has( 'court-side' ) );
		$this->assertSame( 'Court Side', $r->get( 'court-side' )->name() );
		$this->assertNull( $r->get( 'nope' ) );
	}

	public function test_all_preserves_registration_order(): void {
		$r = $this->registry();
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'c', 'C' ) );
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'a', 'A' ) );
		$this->assertSame( array( 'c', 'a' ), array_keys( $r->all() ) );
	}

	public function test_active_defaults_to_first_registered(): void {
		$r = $this->registry();
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'court-side', 'Court Side' ) );
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'floodlight', 'Floodlight' ) );
		$this->assertSame( 'court-side', $r->active()->slug() );
	}

	public function test_set_active_persists_and_wins(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$r1      = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$r1->register( new Blueworx_Clubhouse_Fake_Look( 'court-side' ) );
		$r1->register( new Blueworx_Clubhouse_Fake_Look( 'floodlight' ) );
		$r1->set_active( 'floodlight' );

		// New registry over the SAME storage sees the persisted choice.
		$r2 = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$r2->register( new Blueworx_Clubhouse_Fake_Look( 'court-side' ) );
		$r2->register( new Blueworx_Clubhouse_Fake_Look( 'floodlight' ) );
		$this->assertSame( 'floodlight', $r2->active()->slug() );
	}

	public function test_active_ignores_unregistered_stored_slug(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$storage->set( 'active_base_look', 'ghost' );
		$r = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'court-side' ) );
		$this->assertSame( 'court-side', $r->active()->slug() );
	}

	public function test_active_is_null_when_empty(): void {
		$this->assertNull( $this->registry()->active() );
	}
}
