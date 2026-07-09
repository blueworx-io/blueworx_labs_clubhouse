<?php

use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase {
	public function test_register_and_get(): void {
		$r = new Blueworx_Clubhouse_Registry();
		$r->register( 'home', 'HOME_ITEM' );
		$this->assertSame( 'HOME_ITEM', $r->get( 'home' ) );
	}

	public function test_get_missing_returns_null(): void {
		$r = new Blueworx_Clubhouse_Registry();
		$this->assertNull( $r->get( 'nope' ) );
	}

	public function test_has(): void {
		$r = new Blueworx_Clubhouse_Registry();
		$r->register( 'a', 1 );
		$this->assertTrue( $r->has( 'a' ) );
		$this->assertFalse( $r->has( 'b' ) );
	}

	public function test_keys_preserve_registration_order(): void {
		$r = new Blueworx_Clubhouse_Registry();
		$r->register( 'first', 1 );
		$r->register( 'second', 2 );
		$r->register( 'third', 3 );
		$this->assertSame( array( 'first', 'second', 'third' ), $r->keys() );
	}

	public function test_register_overwrites_same_key_without_reordering(): void {
		$r = new Blueworx_Clubhouse_Registry();
		$r->register( 'a', 1 );
		$r->register( 'b', 2 );
		$r->register( 'a', 99 );
		$this->assertSame( 99, $r->get( 'a' ) );
		$this->assertSame( array( 'a', 'b' ), $r->keys() );
	}
}
