<?php

use PHPUnit\Framework\TestCase;

final class BlockLibraryTest extends TestCase {

	private function lib(): Blueworx_Clubhouse_Block_Library {
		return new Blueworx_Clubhouse_Block_Library( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_a_new_block_gets_a_slug_id_and_its_types_rank(): void {
		$lib = $this->lib();
		$id  = $lib->add( 'hero', 'Home hero' );
		$this->assertSame( 'home-hero', $id );

		$block = $lib->get( $id );
		$this->assertSame( 'hero', $block['type'] );
		$this->assertSame( 'Home hero', $block['name'] );
		$this->assertSame( Blueworx_Clubhouse_Block_Types::rank( 'hero' ), $block['position'] );
		$this->assertSame( array(), $block['content'] );
		$this->assertSame( '', $block['defaults_key'] );
	}

	public function test_a_given_position_and_defaults_key_are_kept(): void {
		$lib   = $this->lib();
		$id    = $lib->add( 'hero', 'About hero', 'about/hero', 10 );
		$block = $lib->get( $id );
		$this->assertSame( 'about/hero', $block['defaults_key'] );
		$this->assertSame( 10, $block['position'] );
	}

	public function test_two_blocks_of_the_same_name_get_distinct_ids(): void {
		$lib = $this->lib();
		$this->assertSame( 'hero', $lib->add( 'hero', 'Hero' ) );
		$this->assertSame( 'hero-2', $lib->add( 'hero', 'Hero' ) );
		$this->assertSame( 'hero-3', $lib->add( 'hero', 'Hero' ) );
	}

	public function test_content_round_trips(): void {
		$lib = $this->lib();
		$id  = $lib->add( 'hero', 'Home hero' );
		$lib->set_content( $id, array( 'title_lead' => 'Welcome', 'items' => array( array( 'text' => 'One' ) ) ) );
		$block = $lib->get( $id );
		$this->assertSame( 'Welcome', $block['content']['title_lead'] );
		$this->assertSame( 'One', $block['content']['items'][0]['text'] );
	}

	public function test_renaming_keeps_the_id_so_nothing_breaks(): void {
		$lib = $this->lib();
		$id  = $lib->add( 'band', 'Join CTA' );
		$lib->rename( $id, 'Come and play' );
		$this->assertSame( 'join-cta', $id );
		$this->assertSame( 'Come and play', $lib->get( $id )['name'] );
	}

	public function test_duplicating_copies_the_content_but_not_the_id(): void {
		$lib = $this->lib();
		$id  = $lib->add( 'band', 'Join CTA', 'about/cta', 70 );
		$lib->set_content( $id, array( 'heading' => 'Come down' ) );

		$copy = $lib->duplicate( $id, 'Join CTA for Contact' );
		$this->assertNotSame( $id, $copy );

		$new = $lib->get( $copy );
		$this->assertSame( 'Come down', $new['content']['heading'] );
		$this->assertSame( 'band', $new['type'] );
		$this->assertSame( 'about/cta', $new['defaults_key'] );
		$this->assertSame( 70, $new['position'] );
		$this->assertSame( 'Join CTA for Contact', $new['name'] );
	}

	public function test_deleting_removes_it_and_leaves_the_rest(): void {
		$lib = $this->lib();
		$a   = $lib->add( 'hero', 'A' );
		$b   = $lib->add( 'hero', 'B' );
		$lib->delete( $a );
		$this->assertFalse( $lib->has( $a ) );
		$this->assertTrue( $lib->has( $b ) );
		$this->assertNull( $lib->get( $a ) );
	}

	public function test_of_type_selects_only_that_type(): void {
		$lib = $this->lib();
		$lib->add( 'hero', 'A' );
		$lib->add( 'band', 'B' );
		$lib->add( 'hero', 'C' );
		$this->assertSame( array( 'a', 'c' ), array_keys( $lib->of_type( 'hero' ) ) );
	}

	public function test_unknown_ids_are_reported_honestly(): void {
		$lib = $this->lib();
		$this->assertFalse( $lib->has( 'nope' ) );
		$this->assertNull( $lib->get( 'nope' ) );
		$lib->delete( 'nope' );
		$lib->rename( 'nope', 'x' );
		$lib->set_content( 'nope', array( 'a' => 'b' ) );
		$this->assertSame( array(), $lib->all() );
	}

	public function test_a_block_survives_a_new_store_over_the_same_storage(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$first   = new Blueworx_Clubhouse_Block_Library( $storage );
		$id      = $first->add( 'hero', 'Home hero' );

		$second = new Blueworx_Clubhouse_Block_Library( $storage );
		$this->assertSame( 'Home hero', $second->get( $id )['name'] );
	}

	public function test_settings_round_trip(): void {
		$lib = $this->lib();
		$id  = $lib->add( 'hero', 'Home hero' );
		$lib->set_settings( $id, array( 'variant' => 'accent' ) );
		$this->assertSame( 'accent', $lib->get( $id )['settings']['variant'] );
	}

	public function test_setting_settings_on_an_unknown_id_is_a_no_op(): void {
		$lib = $this->lib();
		$lib->set_settings( 'nope', array( 'variant' => 'accent' ) );
		$this->assertSame( array(), $lib->all() );
	}

	/**
	 * A deleted block's id must never come back onto a page that still lists it —
	 * see delete(). Home and About both keep 'join-cta' in their composition after
	 * the block is deleted; a fresh block that lands back on 'join-cta' would
	 * silently reappear on both pages.
	 */
	public function test_a_deleted_id_is_never_handed_out_again(): void {
		$lib = $this->lib();
		$id  = $lib->add( 'band', 'Join CTA' );
		$this->assertSame( 'join-cta', $id );

		$lib->delete( $id );
		$new = $lib->add( 'band', 'Join CTA' );
		$this->assertNotSame( 'join-cta', $new );
		$this->assertSame( 'join-cta-2', $new );
	}

	public function test_retired_ids_survive_a_new_store_over_the_same_storage(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$first   = new Blueworx_Clubhouse_Block_Library( $storage );
		$id      = $first->add( 'band', 'Join CTA' );
		$first->delete( $id );

		$second = new Blueworx_Clubhouse_Block_Library( $storage );
		$new    = $second->add( 'band', 'Join CTA' );
		$this->assertNotSame( 'join-cta', $new );
	}
}
