<?php
// tests/php/FrontendRegistryTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class FrontendRegistryTest extends TestCase {

	public function test_registry_registers_all_three_looks(): void {
		$reg = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Fake_Storage() );

		$this->assertTrue( $reg->has( 'court-side' ) );
		$this->assertTrue( $reg->has( 'members-house' ) );
		$this->assertTrue( $reg->has( 'floodlight' ) );
	}

	public function test_active_resolves_to_stored_non_default_look(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$reg     = Blueworx_Clubhouse_Frontend::registry( $storage );
		$reg->set_active( 'floodlight' );

		// Rebuild from the same storage to prove persistence, not in-memory state.
		$rebuilt = Blueworx_Clubhouse_Frontend::registry( $storage );
		$this->assertInstanceOf( Blueworx_Clubhouse_Floodlight::class, $rebuilt->active() );
	}

	public function test_active_falls_back_to_court_side_when_unset(): void {
		$reg = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertInstanceOf( Blueworx_Clubhouse_Court_Side::class, $reg->active() );
	}
}
