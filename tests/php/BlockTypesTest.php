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
}
