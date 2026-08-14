<?php

use PHPUnit\Framework\TestCase;

final class BlockTypesTest extends TestCase {

	public function test_every_entry_is_complete_and_keyed_by_its_own_key(): void {
		foreach ( Blueworx_Clubhouse_Block_Types::all() as $key => $type ) {
			$this->assertSame( $key, $type['key'], $key );
			$this->assertNotSame( '', $type['label'], $key );
			$this->assertIsInt( $type['rank'], $key );
			$this->assertContains( $type['source'], array( 'content', 'collection', 'mixed' ), $key );
			$this->assertIsBool( $type['singleton'], $key );
			$this->assertIsString( $type['requires'], $key );
		}
	}

	public function test_header_and_footer_are_the_only_singletons(): void {
		$singletons = array();
		foreach ( Blueworx_Clubhouse_Block_Types::all() as $key => $type ) {
			if ( $type['singleton'] ) {
				$singletons[] = $key;
			}
		}
		sort( $singletons );
		$this->assertSame( array( 'footer', 'header' ), $singletons );
	}

	public function test_header_ranks_first_and_footer_last(): void {
		$ranks = array_column( Blueworx_Clubhouse_Block_Types::all(), 'rank' );
		$this->assertSame( min( $ranks ), Blueworx_Clubhouse_Block_Types::rank( 'header' ) );
		$this->assertSame( max( $ranks ), Blueworx_Clubhouse_Block_Types::rank( 'footer' ) );
	}

	public function test_unknown_keys_are_reported_honestly(): void {
		$this->assertFalse( Blueworx_Clubhouse_Block_Types::has( 'no_such_type' ) );
		$this->assertNull( Blueworx_Clubhouse_Block_Types::get( 'no_such_type' ) );
		$this->assertSame( 500, Blueworx_Clubhouse_Block_Types::rank( 'no_such_type' ) );
	}

	public function test_the_booking_slot_type_names_its_integration(): void {
		$type = Blueworx_Clubhouse_Block_Types::get( 'shortcode_block' );
		$this->assertSame( Blueworx_Clubhouse_Integrations::LATEPOINT_TAG, $type['requires'] );
	}

	public function test_every_address_maps_to_a_real_type(): void {
		foreach ( Blueworx_Clubhouse_Block_Addresses::map() as $address => $entry ) {
			$this->assertTrue(
				Blueworx_Clubhouse_Block_Types::has( $entry['type'] ),
				$address . ' maps to unknown type ' . $entry['type']
			);
			$this->assertIsInt( $entry['position'], $address );
		}
	}

	/**
	 * The addresses are the ones the content editor offers today. If a section is
	 * added to the catalogue without a block type behind it, the page editor
	 * would silently drop it — so the two lists are pinned together here.
	 */
	public function test_every_catalogue_address_has_a_block(): void {
		// Without a detector installed, Integrations::provides() is false and the
		// Bookings page and calendar/booking are dropped from the catalogue before
		// this test sees them. Install one for the duration of the test so all
		// addresses — LatePoint-gated ones included — are actually pinned, then
		// restore the "nothing installed" default the rest of the suite assumes.
		Blueworx_Clubhouse_Integrations::set_detector(
			static fn( string $tag ): bool => Blueworx_Clubhouse_Integrations::LATEPOINT_TAG === $tag
		);
		try {
			$map = Blueworx_Clubhouse_Block_Addresses::map();
			foreach ( Blueworx_Clubhouse_Content_Catalogue::index() as $address => $entry ) {
				$this->assertArrayHasKey( $address, $map, $address . ' has no block type' );
			}
		} finally {
			Blueworx_Clubhouse_Integrations::set_detector( null );
		}
	}

	/** Positions are unique within a page, or two blocks would fight for a slot. */
	public function test_positions_are_unique_within_each_page(): void {
		$seen = array();
		foreach ( Blueworx_Clubhouse_Block_Addresses::map() as $address => $entry ) {
			$page = explode( '/', $address )[0];
			$slot = $page . ':' . $entry['position'];
			$this->assertArrayNotHasKey( $slot, $seen, $address . ' collides with ' . ( $seen[ $slot ] ?? '' ) );
			$seen[ $slot ] = $address;
		}
	}

	public function test_folds_names_the_two_addresses_that_share_a_rendered_block(): void {
		$this->assertSame(
			array(
				'home/quick_tiles' => 'home/hero',
				'home/info'        => 'home/social',
			),
			Blueworx_Clubhouse_Block_Addresses::folds()
		);
	}

	/** Both sides of every fold must be real addresses, or a migration reading folds() would point at nothing. */
	public function test_every_folded_address_exists_in_the_map(): void {
		$map = Blueworx_Clubhouse_Block_Addresses::map();
		foreach ( Blueworx_Clubhouse_Block_Addresses::folds() as $folded => $into ) {
			$this->assertArrayHasKey( $folded, $map, $folded . ' is not a real address' );
			$this->assertArrayHasKey( $into, $map, $into . ' is not a real address' );
		}
	}
}
