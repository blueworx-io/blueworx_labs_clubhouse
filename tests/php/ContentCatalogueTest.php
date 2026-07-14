<?php
// tests/php/ContentCatalogueTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ContentCatalogueTest extends TestCase {

	public function test_returns_nine_pages_in_page_map_order(): void {
		$tabs = array_column( Blueworx_Clubhouse_Content_Catalogue::pages(), 'tab' );
		$this->assertSame(
			array( 'global', 'about', 'membership', 'contact', 'login', 'sports', 'teams', 'events', 'calendar' ),
			$tabs
		);
	}

	/** Lockstep: every catalogue section key must exist in the visibility inventory for the same page, and vice-versa. */
	public function test_section_keys_match_visibility_inventory_exactly(): void {
		$inv = array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $p ) {
			$inv[ $p['page'] ] = array_column( $p['sections'], 'key' );
			sort( $inv[ $p['page'] ] );
		}
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$vis_page = 'global' === $page['tab'] ? 'home' : $page['tab'];
			$keys     = array_map( static fn( $s ) => $s['key'], $page['sections'] );
			sort( $keys );
			$this->assertSame( $inv[ $vis_page ], $keys, "Section keys diverge for {$vis_page}" );
		}
	}

	public function test_every_section_has_a_valid_type(): void {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $s ) {
				$this->assertContains( $s['type'], array( 'fields', 'loop', 'linkout', 'auto' ), $s['key'] );
				if ( 'loop' === $s['type'] ) {
					$this->assertNotEmpty( $s['loop']['fields'] );
				}
			}
		}
	}

	public function test_cpt_linkouts_reference_real_post_types(): void {
		// Blueworx_Clubhouse_Collection_Types::POST_TYPES is a numeric-indexed list
		// whose VALUES are the post-type slugs — assert membership directly, no array_keys.
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $s ) {
				if ( 'linkout' === $s['type'] && 'cpt' === $s['link']['kind'] ) {
					$this->assertContains( $s['link']['cpt'], Blueworx_Clubhouse_Collection_Types::POST_TYPES, $s['key'] );
				}
			}
		}
	}

	public function test_editable_divergences_are_loops_not_auto(): void {
		$global = Blueworx_Clubhouse_Content_Catalogue::pages()[0]['sections'];
		$byKey  = array();
		foreach ( $global as $s ) { $byKey[ $s['key'] ] = $s['type']; }
		$this->assertSame( 'loop', $byKey['ticker'] );
		$this->assertSame( 'loop', $byKey['stats'] );
		$this->assertSame( 'loop', $byKey['info'] );
		$this->assertSame( 'auto', $byKey['activity'] ); // genuinely derived stays auto
	}
}
