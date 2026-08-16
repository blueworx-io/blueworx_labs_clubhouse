<?php

use PHPUnit\Framework\TestCase;

/**
 * Issue #100: LatePoint prints a large faded month name behind the first week
 * of its month grid. It is absolutely positioned across several day cells and
 * clipped mid-word, so it reads as text printed over the dates rather than as
 * a watermark, and it makes the first row hard to read.
 *
 * The rule that hides it is the single deliberate exception to "the vendor
 * styles its own UI", so it is pinned here: scoped to the shortcode wrapper,
 * hiding exactly one ornament and nothing else.
 */
final class CalendarWatermarkTest extends TestCase {

	private function base_css(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/looks/base.css' );
	}

	public function test_the_month_watermark_is_hidden(): void {
		$this->assertStringContainsString( '.ch-shortcode .os-day-month{display:none}', $this->base_css() );
	}

	public function test_it_is_scoped_to_blocks_this_plugin_placed(): void {
		// Unscoped, it would reach any LatePoint calendar on the site, including
		// pages this plugin does not render.
		$css = $this->base_css();
		$this->assertStringNotContainsString( "\n.os-day-month", $css );
		$this->assertMatchesRegularExpression( '/\.ch-shortcode\s+\.os-day-month/', $css );
	}

	public function test_nothing_else_of_latepoints_is_restyled(): void {
		// The exception is one ornament. If this count grows, the "vendor styles
		// the vendor" rule has quietly been abandoned and should be re-argued
		// rather than extended a line at a time.
		$css = $this->base_css();
		$this->assertSame(
			1,
			preg_match_all( '/\.(os|le|latepoint)-[a-z-]+\s*\{/', $css ),
			'only the month watermark may be overridden'
		);
	}
}
