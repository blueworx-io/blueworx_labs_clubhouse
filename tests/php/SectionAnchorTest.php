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
	 * The catalogue must not offer an anchor the markup does not emit. Renders
	 * every page and asserts each anchor target the catalogue actually offers
	 * (Link_Catalogue::targets()'s 'Sections' group — not every Content_Catalogue
	 * section, some of which share another section's root and carry no id of
	 * their own; see Link_Catalogue::anchors()) has a matching id in the markup.
	 */
	public function test_rendered_pages_carry_the_ids_the_catalogue_advertises(): void {
		$branding    = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$visibility  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$collections = new Blueworx_Clubhouse_Demo_Collections();

		$rendered = array();
		$missing  = array();
		foreach ( Blueworx_Clubhouse_Link_Catalogue::targets( $collections ) as $entry ) {
			if ( 'Sections' !== $entry['group'] || 0 !== strpos( $entry['target'], 'anchor:' ) ) {
				continue;
			}
			[ $tab, $key ] = explode( '.', substr( $entry['target'], strlen( 'anchor:' ) ), 2 );
			$slug          = 'home' === $tab ? '' : $tab;
			if ( ! array_key_exists( $slug, $rendered ) ) {
				$rendered[ $slug ] = Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility, $collections );
			}
			$id = Blueworx_Clubhouse_Link_Catalogue::anchor_id( $tab, $key );
			if ( false === strpos( $rendered[ $slug ], 'id="' . $id . '"' ) ) {
				$missing[] = $id;
			}
		}
		$this->assertSame( array(), $missing, 'catalogued sections with no anchor in the markup' );
	}
}
