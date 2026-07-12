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
		$b    = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$coll = new Blueworx_Clubhouse_Demo_Collections();

		$home = Blueworx_Clubhouse_Page_Map::render( '', $b, $vis, $coll );
		$this->assertStringContainsString( 'class="ch-hero"', $home );

		// A known page (about) renders its own markup rather than Home's.
		$other = Blueworx_Clubhouse_Page_Map::render( 'about', $b, $vis, $coll );
		$this->assertStringContainsString( 'class="ch-nav"', $other );
	}

	public function test_preview_routes_the_four_collection_pages(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$b    = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$coll = new Blueworx_Clubhouse_Demo_Collections();

		$this->assertStringContainsString( 'ch-scards', Blueworx_Clubhouse_Page_Map::render( 'sports', $b, $vis, $coll ) );
		$this->assertStringContainsString( 'ch-scards', Blueworx_Clubhouse_Page_Map::render( 'teams', $b, $vis, $coll ) );
		$this->assertStringContainsString( 'ch-events', Blueworx_Clubhouse_Page_Map::render( 'events', $b, $vis, $coll ) );
		$this->assertStringContainsString( 'ch-cal__month', Blueworx_Clubhouse_Page_Map::render( 'calendar', $b, $vis, $coll ) );
	}
}
