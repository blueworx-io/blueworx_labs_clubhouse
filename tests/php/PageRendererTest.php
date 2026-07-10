<?php
// tests/php/PageRendererTest.php

use PHPUnit\Framework\TestCase;

final class PageRendererTest extends TestCase {

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_google_fonts_url_lists_both_families(): void {
		$url = Blueworx_Clubhouse_Page_Renderer::google_fonts_url( new Blueworx_Clubhouse_Court_Side() );
		$this->assertStringStartsWith( 'https://fonts.googleapis.com/css2?', $url );
		$this->assertStringContainsString( 'family=Syne:wght@600;700;800', $url );
		$this->assertStringContainsString( 'family=Inter:wght@400;500;600', $url );
		$this->assertStringContainsString( 'display=swap', $url );
	}

	public function test_document_head_carries_tokens_fonts_and_stylesheet(): void {
		$b = $this->branding();
		$b->set_accent( '#3b5bdb' );
		$doc = Blueworx_Clubhouse_Page_Renderer::document(
			new Blueworx_Clubhouse_Court_Side(), $b, '<main>hi</main>', '/wp-content/plugins/clubhouse/'
		);
		$this->assertStringContainsString( '<!doctype html>', $doc );
		$this->assertStringContainsString( ':root{', $doc );
		$this->assertStringContainsString( '--color-bg:#faf8f3;', $doc );
		$this->assertStringContainsString( '--color-accent:#3b5bdb;', $doc );
		$this->assertStringContainsString( 'fonts.googleapis.com', $doc );
		$this->assertStringContainsString( '/wp-content/plugins/clubhouse/assets/looks/court-side.css', $doc );
		$this->assertStringContainsString( '<main>hi</main>', $doc );
	}

	public function test_home_includes_the_shell_sections(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $vis );
		$this->assertStringContainsString( 'class="ch-nav"', $body );
		$this->assertStringContainsString( 'class="ch-hero"', $body );
		$this->assertStringContainsString( 'class="ch-tiles"', $body );
		$this->assertStringContainsString( 'class="ch-stats"', $body );
		$this->assertStringContainsString( 'class="ch-cards"', $body );
		$this->assertStringContainsString( 'class="ch-tiers"', $body );
		$this->assertStringContainsString( 'class="ch-footer"', $body );
	}

	public function test_home_respects_visibility(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_section_visible( 'home', 'stats', false );
		$body = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $vis );
		$this->assertStringNotContainsString( 'class="ch-stats"', $body );
		$this->assertStringContainsString( 'class="ch-hero"', $body ); // others still present
	}
}
