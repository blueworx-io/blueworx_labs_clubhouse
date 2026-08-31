<?php

use PHPUnit\Framework\TestCase;

/**
 * The owner's dashboard. It used to be the entire Setup form; a page editor
 * library screen cannot be embedded, so it points at Setup instead.
 */
final class OwnerWelcomeTest extends TestCase {

	public function test_it_links_to_setup_pages_and_members(): void {
		$html = Blueworx_Clubhouse_Owner_Welcome::render();

		$this->assertStringContainsString( 'page=' . Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG, $html );
		$this->assertStringContainsString( 'edit.php?post_type=page', $html );
		$this->assertStringContainsString( 'users.php', $html );
	}

	/**
	 * A screen that reads and points, not one that edits. Carrying tabs and a
	 * save bar together is what the design system's adherence check refuses.
	 */
	public function test_it_is_not_an_editor_screen(): void {
		$html = Blueworx_Clubhouse_Owner_Welcome::render();

		$this->assertStringNotContainsString( 'bw-savebar', $html );
		$this->assertStringNotContainsString( 'bw-tabs', $html );
		$this->assertStringNotContainsString( '<form', $html );
	}

	public function test_it_is_in_the_design_systems_clothes(): void {
		$this->assertStringContainsString( 'bw-admin', Blueworx_Clubhouse_Owner_Welcome::render() );
	}

	/**
	 * Every class this panel wears is one the design system actually defines.
	 *
	 * An invented class is not a small mistake here: it has no styles behind
	 * it, so the row it names simply does not draw. This panel shipped with
	 * three of them (issue #307), and nothing noticed, because a class that
	 * does nothing still reads as present in the markup.
	 */
	public function test_every_class_it_wears_is_one_the_design_system_defines(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/blueworx-admin-design.css' );
		preg_match_all( '/\.(bw-[a-zA-Z0-9_-]+)/', $css, $defined );
		$known = array_flip( $defined[1] );

		preg_match_all( '/class="([^"]*)"/', Blueworx_Clubhouse_Owner_Welcome::render(), $used );
		$worn = array();
		foreach ( $used[1] as $list ) {
			foreach ( preg_split( '/\s+/', trim( $list ) ) as $class ) {
				if ( '' !== $class && 0 === strpos( $class, 'bw-' ) ) {
					$worn[ $class ] = true;
				}
			}
		}

		$this->assertNotSame( array(), $worn, 'the panel wears no design system classes at all' );
		foreach ( array_keys( $worn ) as $class ) {
			$this->assertArrayHasKey(
				$class,
				$known,
				sprintf( '"%s" is not a class the design system defines, so nothing draws it.', $class )
			);
		}
	}
}
