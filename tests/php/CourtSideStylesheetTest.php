<?php
// tests/php/CourtSideStylesheetTest.php

use PHPUnit\Framework\TestCase;

final class CourtSideStylesheetTest extends TestCase {

	private function css(): string {
		$path = dirname( __DIR__, 2 ) . '/assets/looks/court-side.css';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	public function test_styles_the_shell_sections(): void {
		$css = $this->css();
		foreach ( array( '.ch-nav', '.ch-hero', '.ch-stats', '.ch-footer', '.ch-btn' ) as $sel ) {
			$this->assertStringContainsString( $sel, $css );
		}
	}

	public function test_accent_is_referenced_via_custom_property_not_literals(): void {
		$css = $this->css();
		$this->assertStringContainsString( 'var(--color-accent)', $css );
		// The accent must not be baked in as a hex — that would break re-theming.
		$this->assertStringNotContainsString( '#c6f24e', $css );
	}

	public function test_styles_the_new_collection_sections(): void {
		$css = $this->css();
		foreach ( array( '.ch-hero-f', '.ch-filter', '.ch-scard', '.ch-events', '.ch-event', '.ch-archive', '.ch-cal__month' ) as $sel ) {
			$this->assertStringContainsString( $sel, $css );
		}
	}
}
