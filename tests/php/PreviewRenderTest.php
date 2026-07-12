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

	public function test_preview_routes_the_four_collection_pages(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$b   = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$vis = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );

		$this->assertStringContainsString( 'ch-scards', blueworx_clubhouse_preview_body( 'sports', $b, $vis ) );
		$this->assertStringContainsString( 'ch-scards', blueworx_clubhouse_preview_body( 'teams', $b, $vis ) );
		$this->assertStringContainsString( 'ch-events', blueworx_clubhouse_preview_body( 'events', $b, $vis ) );
		$this->assertStringContainsString( 'ch-cal__month', blueworx_clubhouse_preview_body( 'calendar', $b, $vis ) );
	}

	public function test_look_param_switches_to_floodlight(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$_GET['look'] = 'floodlight';
		$html = blueworx_clubhouse_preview_document();
		unset( $_GET['look'] );

		$this->assertStringContainsString( 'floodlight.css', $html );
		$this->assertStringContainsString( 'family=Bricolage%20Grotesque', $html );
		$this->assertStringContainsString( 'family=Hanken%20Grotesk', $html );
		// Dark shell token made it into the emitted :root.
		$this->assertStringContainsString( '#14110b', $html );
	}

	public function test_look_param_switches_to_members_house(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$_GET['look'] = 'members-house';
		$html = blueworx_clubhouse_preview_document();
		unset( $_GET['look'] );

		$this->assertStringContainsString( 'members-house.css', $html );
		$this->assertStringContainsString( 'family=Fraunces', $html );
		$this->assertStringContainsString( 'family=Mulish', $html );
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
