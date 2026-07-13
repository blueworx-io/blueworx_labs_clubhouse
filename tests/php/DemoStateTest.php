<?php
// tests/php/DemoStateTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoStateTest extends TestCase {

	public function test_defaults_to_off(): void {
		$state = new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertFalse( $state->is_on() );
	}

	public function test_set_true_then_read_on(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Demo_State( $storage ) )->set( true );
		$this->assertTrue( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}

	public function test_set_false_reads_off(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$state   = new Blueworx_Clubhouse_Demo_State( $storage );
		$state->set( true );
		$state->set( false );
		$this->assertFalse( $state->is_on() );
	}

	public function test_non_bool_stored_value_is_coerced(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$storage->set( Blueworx_Clubhouse_Demo_State::KEY, '1' );
		$this->assertTrue( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}
}
