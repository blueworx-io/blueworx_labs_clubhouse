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

	public function test_backbone_blocks_use_the_tinted_block_token(): void {
		$css = $this->css();
		$this->assertStringContainsString( '.ch-banner{background:var(--color-accent-block)', $css );
		$this->assertStringContainsString( '.ch-ticker{display:flex;align-items:stretch;gap:0;background:var(--color-accent-block)', $css );
		$this->assertStringContainsString( '.ch-home-hero__bg{position:absolute;inset:0;z-index:-2;background:var(--color-accent-block)}', $css );
		$this->assertStringContainsString( 'transparent 60%),var(--color-accent-block)}', $css ); // __bg--empty
	}

	public function test_hero_scrim_stays_neutral_ink(): void {
		$this->assertStringContainsString(
			'.ch-home-hero__scrim{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,color-mix(in oklab,var(--color-ink) 22%,transparent)',
			$this->css()
		);
	}

	/**
	 * Accent-derived marks converge with the tinted field, so the blocks re-point
	 * their focus ring at --color-bg, which the token's rule guarantees is >=4.5:1
	 * on the block for every club colour.
	 */
	public function test_tinted_blocks_use_a_legible_focus_ring(): void {
		$css = $this->css();
		$this->assertStringContainsString( '.ch-banner :focus-visible,.ch-home-hero :focus-visible,.ch-ticker :focus-visible{outline-color:var(--color-bg)}', $css );
		$this->assertStringNotContainsString( 'outline:3px solid var(--color-accent);outline-offset:-3px', $css );
		$this->assertStringNotContainsString( 'outline:2px solid var(--color-accent);outline-offset:-3px', $css );
	}

	public function test_banner_link_hover_does_not_rely_on_accent_colour(): void {
		$css = $this->css();
		$this->assertStringContainsString( '.ch-banner__link:hover{text-decoration:underline}', $css );
		$this->assertStringNotContainsString( '.ch-banner__link:hover{color:var(--color-accent)}', $css );
	}
}
