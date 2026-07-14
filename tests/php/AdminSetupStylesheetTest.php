<?php
// tests/php/AdminSetupStylesheetTest.php
use PHPUnit\Framework\TestCase;

final class AdminSetupStylesheetTest extends TestCase {

	private function css(): string {
		$path = dirname( __DIR__, 2 ) . '/assets/css/admin-setup.css';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	public function test_styles_the_core_setup_hooks(): void {
		$css = $this->css();
		foreach ( array( '.clubhouse-setup', '.clubhouse-tab', '.clubhouse-panel', '.clubhouse-look-card', '.clubhouse-toggle', '.clubhouse-bar' ) as $sel ) {
			$this->assertStringContainsString( $sel, $css );
		}
	}

	public function test_uses_look_tokens_not_literal_colours_or_fonts(): void {
		$css = $this->css();
		$this->assertStringContainsString( 'var(--color-', $css );
		$this->assertStringContainsString( 'var(--font-', $css );
		// No look's literal accent may be baked in — that would break re-skinning.
		$this->assertStringNotContainsString( '#c6f24e', $css );
		$this->assertStringNotContainsString( '#7a2f3a', $css );
		$this->assertStringNotContainsString( '#f7a70a', $css );
		// No font-family names — fonts arrive only via the tokens.
		foreach ( array( 'Syne', 'Fraunces', 'Bricolage', 'Hanken', 'Mulish' ) as $family ) {
			$this->assertStringNotContainsString( $family, $css );
		}
	}

	public function test_panels_only_hide_under_the_js_class(): void {
		$css = $this->css();
		// JS-off must show everything: hiding is gated on the --js container class.
		$this->assertStringContainsString( '.clubhouse-setup--js', $css );
	}
}
