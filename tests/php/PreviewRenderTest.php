<?php
// tests/php/PreviewRenderTest.php

use PHPUnit\Framework\TestCase;

final class PreviewRenderTest extends TestCase {

	public function test_preview_builds_a_full_court_side_home_document(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$html = blueworx_clubhouse_preview_document();

		$this->assertStringContainsString( '<!doctype html>', $html );
		$this->assertStringContainsString( 'court-side.css', $html );
		$this->assertStringContainsString( 'class="ch-hero"', $html );
		$this->assertStringContainsString( ':root{', $html );
		// The accent switcher embeds pre-derived palettes (real engine output).
		$this->assertStringContainsString( 'data-ch-palettes', $html );
		$this->assertStringContainsString( '--color-accent-ink', $html );
	}

	public function test_preview_defaults_to_home_and_routes_by_page_param(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$b   = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$vis = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );

		$home = blueworx_clubhouse_preview_body( 'home', $b, $vis );
		$this->assertStringContainsString( 'class="ch-hero"', $home );

		// Unknown page falls back to Home rather than erroring.
		$other = blueworx_clubhouse_preview_body( 'about', $b, $vis );
		$this->assertStringContainsString( 'class="ch-nav"', $other );
	}
}
