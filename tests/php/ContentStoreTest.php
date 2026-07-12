<?php

use PHPUnit\Framework\TestCase;

final class ContentStoreTest extends TestCase {
	private function store(): Blueworx_Clubhouse_Content_Store {
		return new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_get_missing_field_returns_default(): void {
		$store = $this->store();
		$this->assertSame( 'D', $store->get( 'home', 'hero', 'heading', 'D' ) );
	}

	public function test_set_then_get_roundtrips(): void {
		$store = $this->store();
		$store->set( 'home', 'hero', 'heading', 'Welcome' );
		$this->assertSame( 'Welcome', $store->get( 'home', 'hero', 'heading' ) );
	}

	public function test_get_section_returns_all_fields(): void {
		$store = $this->store();
		$store->set( 'home', 'hero', 'heading', 'Welcome' );
		$store->set( 'home', 'hero', 'body', 'Hi' );
		$this->assertSame(
			array(
				'heading' => 'Welcome',
				'body'    => 'Hi',
			),
			$store->get_section( 'home', 'hero' )
		);
	}

	public function test_get_section_missing_returns_empty_array(): void {
		$this->assertSame( array(), $this->store()->get_section( 'home', 'nope' ) );
	}

	public function test_sections_and_pages_are_isolated(): void {
		$store = $this->store();
		$store->set( 'home', 'hero', 'heading', 'H' );
		$store->set( 'about', 'hero', 'heading', 'A' );
		$this->assertSame( 'H', $store->get( 'home', 'hero', 'heading' ) );
		$this->assertSame( 'A', $store->get( 'about', 'hero', 'heading' ) );
		$this->assertSame( array(), $store->get_section( 'home', 'other' ) );
	}
}
