<?php

use PHPUnit\Framework\TestCase;

final class FakeStorageTest extends TestCase {
	public function test_get_returns_default_when_missing(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		$this->assertSame( 'fallback', $s->get( 'x', 'fallback' ) );
	}

	public function test_set_then_get_roundtrips(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		$s->set( 'x', array( 'a' => 1 ) );
		$this->assertSame( array( 'a' => 1 ), $s->get( 'x' ) );
	}

	public function test_delete_removes_value(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		$s->set( 'x', 1 );
		$s->delete( 'x' );
		$this->assertNull( $s->get( 'x' ) );
	}
}
