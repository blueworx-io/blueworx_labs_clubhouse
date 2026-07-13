<?php
// tests/php/MembersHouseStylesheetTest.php

use PHPUnit\Framework\TestCase;

final class MembersHouseStylesheetTest extends TestCase {

	private function css(): string {
		$path = dirname( __DIR__, 2 ) . '/assets/looks/members-house.css';
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
		$this->assertStringContainsString( 'var(--color-accent)', $css );
		// The demo accent must never be baked in — that would break re-theming.
		$this->assertStringNotContainsString( '#7a2f3a', $css );
		// Nor Court Side's accent.
		$this->assertStringNotContainsString( '#c6f24e', $css );
	}

	public function test_uses_the_look_fonts_only_via_tokens(): void {
		// Fonts come from --font-display/--font-body tokens; the stylesheet must not
		// hardcode a family name (that lives in the look class, not the CSS).
		$css = $this->css();
		$this->assertStringNotContainsString( 'Fraunces', $css );
		$this->assertStringNotContainsString( 'Mulish', $css );
		$this->assertStringContainsString( 'var(--font-display)', $css );
		$this->assertStringContainsString( 'var(--font-body)', $css );
	}

	public function test_stylesheet_styles_the_brand_logo(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/looks/members-house.css' );
		$this->assertStringContainsString( '.ch-brand__logo', $css );
	}
}
