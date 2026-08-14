<?php

use PHPUnit\Framework\TestCase;

final class PageCompositionTest extends TestCase {

	private function comp(): Blueworx_Clubhouse_Page_Composition {
		return new Blueworx_Clubhouse_Page_Composition( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_a_fresh_site_has_no_composition_and_no_blocks(): void {
		$comp = $this->comp();
		$this->assertFalse( $comp->is_configured() );
		$this->assertSame( array(), $comp->blocks( 'home' ) );
	}

	public function test_pages_are_enabled_until_switched_off(): void {
		$comp = $this->comp();
		$this->assertTrue( $comp->is_enabled( 'home' ) );
		$comp->set_enabled( 'home', false );
		$this->assertFalse( $comp->is_enabled( 'home' ) );
		$comp->set_enabled( 'home', true );
		$this->assertTrue( $comp->is_enabled( 'home' ) );
	}

	public function test_blocks_round_trip_in_the_order_given(): void {
		$comp = $this->comp();
		$comp->set_blocks( 'home', array( 'home-hero', 'home-ticker' ) );
		$this->assertSame( array( 'home-hero', 'home-ticker' ), $comp->blocks( 'home' ) );
		$this->assertTrue( $comp->is_configured() );
	}

	public function test_adding_appends_and_never_duplicates(): void {
		$comp = $this->comp();
		$comp->add( 'home', 'home-hero' );
		$comp->add( 'home', 'home-ticker' );
		$comp->add( 'home', 'home-hero' );
		$this->assertSame( array( 'home-hero', 'home-ticker' ), $comp->blocks( 'home' ) );
	}

	public function test_removing_leaves_a_gapless_list(): void {
		$comp = $this->comp();
		$comp->set_blocks( 'home', array( 'a', 'b', 'c' ) );
		$comp->remove( 'home', 'b' );
		$this->assertSame( array( 'a', 'c' ), $comp->blocks( 'home' ) );
	}

	public function test_removing_from_one_page_leaves_the_other(): void {
		$comp = $this->comp();
		$comp->set_blocks( 'home', array( 'shared-cta' ) );
		$comp->set_blocks( 'about', array( 'shared-cta' ) );
		$comp->remove( 'home', 'shared-cta' );
		$this->assertSame( array(), $comp->blocks( 'home' ) );
		$this->assertSame( array( 'shared-cta' ), $comp->blocks( 'about' ) );
	}

	public function test_uses_names_every_page_a_block_is_on(): void {
		$comp = $this->comp();
		$comp->set_blocks( 'home', array( 'shared-cta', 'home-hero' ) );
		$comp->set_blocks( 'about', array( 'shared-cta' ) );
		$comp->set_blocks( 'contact', array( 'contact-form' ) );
		$this->assertSame( array( 'home', 'about' ), $comp->uses( 'shared-cta' ) );
		$this->assertSame( array(), $comp->uses( 'nobody-uses-me' ) );
	}

	public function test_it_survives_a_new_store_over_the_same_storage(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$first   = new Blueworx_Clubhouse_Page_Composition( $storage );
		$first->set_blocks( 'home', array( 'home-hero' ) );
		$first->set_enabled( 'about', false );

		$second = new Blueworx_Clubhouse_Page_Composition( $storage );
		$this->assertSame( array( 'home-hero' ), $second->blocks( 'home' ) );
		$this->assertFalse( $second->is_enabled( 'about' ) );
	}
}
