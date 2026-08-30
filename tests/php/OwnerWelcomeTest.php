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
}
