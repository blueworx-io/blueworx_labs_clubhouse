<?php
// tests/php/PreviewRenderTest.php

use PHPUnit\Framework\TestCase;

final class PreviewRenderTest extends TestCase {

	public function test_preview_builds_a_full_court_side_home_document(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$html = blueworx_clubhouse_preview_document();

		$this->assertStringContainsString( '<!doctype html>', $html );
		$this->assertStringContainsString( 'court-side.css', $html );
		$this->assertStringContainsString( 'class="ch-home-hero"', $html );
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
		$this->assertStringContainsString( 'class="ch-home-hero"', $home );

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

	public function test_look_param_switches_to_floodlight(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$_GET['look'] = 'floodlight';
		$html = blueworx_clubhouse_preview_document();
		unset( $_GET['look'] );

		$this->assertStringContainsString( 'floodlight.css', $html );
		$this->assertStringContainsString( '/assets/fonts/bricolage-grotesque-', $html );
		$this->assertStringContainsString( '/assets/fonts/hanken-grotesk-', $html );
		// Dark shell token made it into the emitted :root.
		$this->assertStringContainsString( '#14110b', $html );
	}

	public function test_look_param_switches_to_members_house(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$_GET['look'] = 'members-house';
		$html = blueworx_clubhouse_preview_document();
		unset( $_GET['look'] );

		$this->assertStringContainsString( 'members-house.css', $html );
		$this->assertStringContainsString( '/assets/fonts/fraunces-', $html );
		$this->assertStringContainsString( '/assets/fonts/mulish-', $html );
		// Parchment shell token made it into the emitted :root.
		$this->assertStringContainsString( '#f2ece0', $html );
	}

	public function test_default_look_is_still_court_side(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		unset( $_GET['look'] );
		$html = blueworx_clubhouse_preview_document();
		$this->assertStringContainsString( 'court-side.css', $html );
		$this->assertStringNotContainsString( 'floodlight.css', $html );
		$this->assertStringNotContainsString( 'members-house.css', $html );
	}

	public function test_non_default_look_is_carried_through_page_links(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$_GET['look'] = 'members-house';
		$html = blueworx_clubhouse_preview_document();
		unset( $_GET['look'] );

		// A preview-only script rewrites on-page ?page= links so nav stays in the
		// selected look, carrying the active slug forward.
		$this->assertStringContainsString( 'a[href^="?page="]', $html );
		$this->assertStringContainsString( 'members-house', $html );
	}

	public function test_default_look_leaves_page_links_clean(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		unset( $_GET['look'] );
		$html = blueworx_clubhouse_preview_document();
		// On the default look the persist script is a no-op, so page links stay bare.
		$this->assertStringNotContainsString( 'a[href^="?page="]', $html );
	}
}
