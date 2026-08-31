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
	 * emit. Two directions, both load-bearing:
	 *
	 *  1. Every declared section (Page_Fields::sections(), what the editors
	 *     themselves offer) either carries its id in the rendered markup, or is
	 *     named in $expected_missing — no silent third option.
	 *  2. What Link_Catalogue::targets() actually OFFERS as an anchor must be
	 *     exactly "every catalogued id minus $expected_missing" — a single
	 *     assertSame on two sorted arrays, not a one-directional
	 *     assertNotContains. A one-directional check only proves the excluded
	 *     ids stayed out; it says nothing about ids that should have stayed
	 *     IN but got dropped too (e.g. a resurrected type-based exclusion in
	 *     Link_Catalogue::has_no_anchor() that wrongly drops a rendering
	 *     section — loop 1 would not notice, since anchored() calls are
	 *     unconditional in Page_Renderer regardless of what the catalogue
	 *     offers, and a one-directional loop 2 would iterate zero times over
	 *     an empty $expected_missing and pass vacuously). assertSame on the
	 *     full sets catches that: the wrongly-dropped id would be present in
	 *     "catalogued minus excluded" but absent from $offered.
	 */
	public function test_rendered_pages_carry_the_ids_the_catalogue_advertises(): void {
		// Sections deliberately excluded from Link_Catalogue::anchors() because
		// they share another section's root and have no id of their own to
		// carry — kept in lock-step with Link_Catalogue::has_no_anchor().
		// The social feed ships hidden and renders nothing until a club pastes
		// posts in, so a default render carries no root for it — kept in
		// lock-step with Link_Catalogue::has_no_anchor().
		$expected_missing = array( Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'home', 'social_feed' ) );

		$branding    = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$visibility  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$collections = new Blueworx_Clubhouse_Demo_Collections();

		$missing    = array();
		$catalogued = array();
		$rendered   = array();
		foreach ( Blueworx_Clubhouse_Page_Fields::sections() as $section ) {
			$tab = $section['area'];
			if ( 'global' === $tab ) {
				continue;
			}
			$slug = 'home' === $tab ? '' : $tab;
			if ( ! isset( $rendered[ $tab ] ) ) {
				$rendered[ $tab ] = Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility, $collections );
			}
			$id           = Blueworx_Clubhouse_Link_Catalogue::anchor_id( $tab, $section['section'] );
			$catalogued[] = $id;
			if ( false === strpos( $rendered[ $tab ], 'id="' . $id . '"' ) ) {
				$missing[] = $id;
			}
		}
		sort( $missing );
		sort( $expected_missing );
		$this->assertSame( $expected_missing, $missing, 'ids missing from the markup must be exactly the sections named as deliberately excluded' );

		// The converse: what the catalogue actually offers must equal every
		// catalogued id minus the ones named above — not merely disjoint from
		// them. Two full sets, compared with assertSame, not a one-directional
		// assertNotContains that a vacuously-empty $expected_missing would let
		// pass regardless of what got dropped.
		$offered = array();
		foreach ( Blueworx_Clubhouse_Link_Catalogue::targets( $collections ) as $entry ) {
			if ( 'Sections' === $entry['group'] && 0 === strpos( $entry['target'], 'anchor:' ) ) {
				[ $tab, $key ] = explode( '.', substr( $entry['target'], strlen( 'anchor:' ) ), 2 );
				$offered[]     = Blueworx_Clubhouse_Link_Catalogue::anchor_id( $tab, $key );
			}
		}
		$expected_offered = array_values( array_diff( array_unique( $catalogued ), $expected_missing ) );
		sort( $offered );
		sort( $expected_offered );
		$this->assertSame( $expected_offered, $offered, 'the catalogue must offer exactly every catalogued anchor minus the ones named as deliberately excluded' );
	}
}
