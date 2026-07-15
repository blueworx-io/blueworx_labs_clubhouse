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

	public function test_stylesheet_styles_the_brand_logo(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/looks/court-side.css' );
		$this->assertStringContainsString( '.ch-brand__logo', $css );
	}

	/**
	 * The backbone blocks carry the club's tint, not flat near-black ink. The scrim
	 * is deliberately excluded: it darkens a club's hero PHOTOGRAPH, and tinting it
	 * would put a duotone wash over club photography.
	 */
	public function test_backbone_blocks_use_the_tinted_block_token(): void {
		$css = $this->css();
		$this->assertStringContainsString( '.ch-banner{background:var(--color-accent-block)', $css );
		$this->assertStringContainsString( '.ch-ticker{display:flex;align-items:center;gap:0;background:var(--color-accent-block)', $css );
		$this->assertStringContainsString( '.ch-home-hero__bg{position:absolute;inset:0;z-index:-2;background:var(--color-accent-block)}', $css );
		$this->assertStringContainsString( 'transparent 55%),var(--color-accent-block)}', $css ); // __bg--empty
	}

	public function test_hero_scrim_stays_neutral_ink(): void {
		$this->assertStringContainsString(
			'.ch-home-hero__scrim{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,color-mix(in oklab,var(--color-ink) 26%,transparent)',
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
		$this->assertStringContainsString( '.ch-banner :focus-visible,.ch-ticker :focus-visible{outline-color:var(--color-bg)}', $css );
		$this->assertStringNotContainsString( 'outline:3px solid var(--color-accent);outline-offset:-3px', $css );
		$this->assertStringNotContainsString( 'outline:2px solid var(--color-accent);outline-offset:-3px', $css );
	}

	public function test_banner_link_hover_does_not_rely_on_accent_colour(): void {
		$css = $this->css();
		$this->assertStringContainsString( '.ch-banner__link:hover{text-decoration:underline}', $css );
		$this->assertStringNotContainsString( '.ch-banner__link:hover{color:var(--color-accent)}', $css );
	}
}
