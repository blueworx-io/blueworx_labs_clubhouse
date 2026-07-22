<?php
// tests/php/BaseStylesheetTest.php

use PHPUnit\Framework\TestCase;

/**
 * Covers the six shared components moved out of court-side.css into base.css
 * (see LookCoverageTest): they were styled by that look alone, leaving sports,
 * teams, events and calendar unstyled under Floodlight and Members House.
 * These assertions used to live in CourtSideStylesheetTest; they moved here
 * with the rules they check.
 */
final class BaseStylesheetTest extends TestCase {

	private function css(): string {
		$path = dirname( __DIR__, 2 ) . '/assets/looks/base.css';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	public function test_styles_the_new_collection_sections(): void {
		$css = $this->css();
		foreach ( array( '.ch-hero-filter', '.ch-filter', '.ch-scard', '.ch-events', '.ch-event', '.ch-archive', '.ch-cal__month' ) as $sel ) {
			$this->assertStringContainsString( $sel, $css );
		}
	}

	public function test_calendar_retones_result_badges_for_light_background(): void {
		$css = $this->css();
		// L/D badges are dark-context by default; the calendar sits on the light shell, so it must re-tone them.
		$this->assertStringContainsString( '.ch-cal__row .ch-badge--l', $css );
		$this->assertStringContainsString( '.ch-cal__row .ch-badge--d', $css );
	}

	public function test_styles_the_social_block(): void {
		$css = $this->css();
		foreach ( array( '.ch-social', '.ch-social__link' ) as $sel ) {
			$this->assertStringContainsString( $sel, $css );
		}
	}
}
