<?php
// tests/php/SectionAnchorTest.php

use PHPUnit\Framework\TestCase;

final class SectionAnchorTest extends TestCase {

	public function test_anchored_injects_the_id_into_the_root_tag(): void {
		$out = Blueworx_Clubhouse_Page_Renderer::anchored( 'about', 'history', '<section class="ch-x">hi</section>' );
		$this->assertSame( '<section id="ch-about-history" class="ch-x">hi</section>', $out );
	}

	public function test_anchored_handles_a_root_tag_with_no_attributes(): void {
		$out = Blueworx_Clubhouse_Page_Renderer::anchored( 'home', 'ticker', '<div>hi</div>' );
		$this->assertSame( '<div id="ch-home-ticker">hi</div>', $out );
	}

	public function test_anchored_leaves_an_already_identified_root_alone(): void {
		$html = '<section id="mine" class="ch-x">hi</section>';
		$this->assertSame( $html, Blueworx_Clubhouse_Page_Renderer::anchored( 'about', 'history', $html ) );
	}

	public function test_anchored_leaves_empty_output_alone(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::anchored( 'about', 'history', '' ) );
	}

	public function test_anchored_snake_case_section_keys_become_hyphenated_ids(): void {
		$out = Blueworx_Clubhouse_Page_Renderer::anchored( 'home', 'quick_tiles', '<div>x</div>' );
		$this->assertStringContainsString( 'id="ch-home-quick-tiles"', $out );
	}

	/**
	 * The catalogue must not offer an anchor the markup does not emit — and,
	 * just as importantly, it must not withhold an anchor the markup DOES
	 * emit (an over-broad exclusion in Link_Catalogue::anchors() would make
	 * this loop shrink and stay green, hiding the very regression it exists
	 * to catch). So this walks every Content_Catalogue section — the brief's
	 * original, unfiltered source — and separately asserts that the ids
	 * missing from the rendered markup are EXACTLY the ones this test
	 * explicitly names as deliberately excluded (today: none — see
	 * Link_Catalogue::has_no_anchor()). If that named list ever needs an
	 * entry, it must be added here at the same time, in the open, not left to
	 * an exclusion elsewhere that this test can't see.
	 */
	public function test_rendered_pages_carry_the_ids_the_catalogue_advertises(): void {
		// Sections deliberately excluded from Link_Catalogue::anchors() because
		// they share another section's root and have no id of their own to
		// carry — kept in lock-step with Link_Catalogue::has_no_anchor().
		$expected_missing = array();

		$branding    = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$visibility  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$collections = new Blueworx_Clubhouse_Demo_Collections();

		$missing = array();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$tab = (string) $page['tab'];
			if ( 'global' === $tab ) {
				continue;
			}
			$slug = 'home' === $tab ? '' : $tab;
			if ( ! Blueworx_Clubhouse_Page_Map::is_available( $slug ) ) {
				continue;
			}
			$html = Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility, $collections );
			foreach ( $page['sections'] as $section ) {
				$id = Blueworx_Clubhouse_Link_Catalogue::anchor_id( $tab, (string) $section['key'] );
				if ( false === strpos( $html, 'id="' . $id . '"' ) ) {
					$missing[] = $id;
				}
			}
		}
		sort( $missing );
		sort( $expected_missing );
		$this->assertSame( $expected_missing, $missing, 'ids missing from the markup must be exactly the sections named as deliberately excluded' );

		// The other direction: the catalogue must not offer an anchor the
		// markup does not emit either — confirms has_no_anchor() actually
		// excludes every id that's missing above, nothing more, nothing less.
		$offered = array();
		foreach ( Blueworx_Clubhouse_Link_Catalogue::targets( $collections ) as $entry ) {
			if ( 'Sections' === $entry['group'] && 0 === strpos( $entry['target'], 'anchor:' ) ) {
				[ $tab, $key ] = explode( '.', substr( $entry['target'], strlen( 'anchor:' ) ), 2 );
				$offered[]     = Blueworx_Clubhouse_Link_Catalogue::anchor_id( $tab, $key );
			}
		}
		foreach ( $expected_missing as $id ) {
			$this->assertNotContains( $id, $offered, "catalogue must not offer anchor '$id', which the markup does not emit" );
		}
	}
}
