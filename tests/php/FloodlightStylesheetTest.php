<?php
// tests/php/FloodlightStylesheetTest.php

use PHPUnit\Framework\TestCase;

final class FloodlightStylesheetTest extends TestCase {

	private function css(): string {
		$path = dirname( __DIR__, 2 ) . '/assets/looks/floodlight.css';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	public function test_styles_the_shell_sections(): void {
		$css = $this->css();
		foreach ( array( '.ch-nav', '.ch-hero', '.ch-stats', '.ch-footer', '.ch-btn', '.ch-faq', '.ch-tiers', '.ch-auth' ) as $sel ) {
			$this->assertStringContainsString( $sel, $css );
		}
	}

	public function test_accent_is_referenced_via_custom_property_not_literals(): void {
		$css = $this->css();
		$this->assertStringContainsString( 'var(--color-accent-deep)', $css );
		// The demo accent must never be baked in — that would break re-theming.
		$this->assertStringNotContainsString( '#8bf34d', $css );
		// Nor the other looks' demo accents.
		$this->assertStringNotContainsString( '#c6f24e', $css );
		$this->assertStringNotContainsString( '#7a2f3a', $css );
	}

	public function test_uses_the_look_fonts_only_via_tokens(): void {
		// Fonts come from --font-display/--font-body tokens; the stylesheet must not
		// name a family (that lives in the look class, not the CSS) — including in comments.
		$css = $this->css();
		$this->assertStringNotContainsString( 'Bricolage', $css );
		$this->assertStringNotContainsString( 'Inter', $css );
		$this->assertStringContainsString( 'var(--font-display)', $css );
		$this->assertStringContainsString( 'var(--font-body)', $css );
	}
}
