<?php

use PHPUnit\Framework\TestCase;

/**
 * The shipped copies are what a site loads; the skill folder is what CI
 * compares against the foundation. If they drift, the plugin passes its own
 * suite while shipping something the design system never approved.
 */
final class DesignSystemVendoredTest extends TestCase {

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	public function test_the_shipped_stylesheet_is_the_skill_folder_stylesheet(): void {
		$skill   = $this->root() . '/.claude/skills/blueworx-admin-design/styles.css';
		$shipped = $this->root() . '/assets/blueworx-admin-design.css';
		$this->assertFileExists( $skill );
		$this->assertFileExists( $shipped );
		$this->assertSame( sha1_file( $skill ), sha1_file( $shipped ) );
	}

	public function test_the_shipped_icons_are_the_skill_folder_icons(): void {
		$skill   = $this->root() . '/.claude/skills/blueworx-admin-design/assets/icons/lucide-icons.js';
		$shipped = $this->root() . '/assets/blueworx-admin-icons.js';
		$this->assertFileExists( $skill );
		$this->assertFileExists( $shipped );
		$this->assertSame( sha1_file( $skill ), sha1_file( $shipped ) );
	}

	/** styles.css loads its faces from beside itself, so they must be there. */
	public function test_every_design_system_font_ships_beside_the_stylesheet(): void {
		$dir   = $this->root() . '/.claude/skills/blueworx-admin-design/fonts';
		$faces = glob( $dir . '/*.woff2' );
		$this->assertNotEmpty( $faces, 'the design system ships no fonts — check the copy' );
		foreach ( $faces as $face ) {
			$shipped = $this->root() . '/assets/fonts/' . basename( $face );
			$this->assertFileExists( $shipped, basename( $face ) . ' is missing from assets/fonts' );
			$this->assertSame( sha1_file( $face ), sha1_file( $shipped ), basename( $face ) . ' differs from the design system' );
		}
	}

	/**
	 * The page editor library is phase 3. Carrying one of its two artefacts
	 * without the other fails the foundation's sync check, so neither may
	 * appear yet.
	 */
	public function test_the_page_editor_library_is_not_vendored_yet(): void {
		$this->assertDirectoryDoesNotExist( $this->root() . '/blueworx-page-editor' );
		$this->assertFileDoesNotExist( $this->root() . '/assets/blueworx-page-editor.js' );
	}
}
