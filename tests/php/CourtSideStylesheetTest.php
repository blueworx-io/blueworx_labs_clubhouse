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
		foreach ( array( '.ch-nav', '.ch-hero', '.ch-footer', '.ch-btn' ) as $sel ) {
			$this->assertStringContainsString( $sel, $css );
		}
	}

	/**
	 * The eyebrow is a filled accent pill, so its text must route through
	 * --color-accent-ink (the engine's guaranteed-legible-on-accent token).
	 * --color-ink only works for pale accents — it fails AA on Cobalt (3.04) and
	 * Berry (3.32). The Home hero must not recolour it either: recolouring the text
	 * of a filled pill painted the accent onto the accent, a 1:1 invisible label.
	 */
	public function test_eyebrow_pill_text_is_legible_on_the_accent(): void {
		$css = $this->css();
		$this->assertStringContainsString( 'color:var(--color-accent-ink);background:var(--color-accent)', $css );
		$this->assertStringNotContainsString( '.ch-home-hero .ch-eyebrow{color:var(--color-accent)}', $css );
	}

	public function test_accent_is_referenced_via_custom_property_not_literals(): void {
		$css = $this->css();
		$this->assertStringContainsString( 'var(--color-accent)', $css );
		// The accent must not be baked in as a hex — that would break re-theming.
		$this->assertStringNotContainsString( '#c6f24e', $css );
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
		$this->assertStringContainsString( '.ch-ticker{display:flex;align-items:stretch;gap:0;background:var(--color-accent-block)', $css );
		$this->assertStringContainsString( '.ch-home-hero__bg{position:absolute;inset:0;z-index:-2;background:var(--color-accent-block)}', $css );
		$this->assertStringContainsString( 'transparent 55%),var(--color-accent-block)}', $css ); // __bg--empty
	}

	/**
	 * The scrim darkens with neutral ink, never the club's accent — an accent-tinted
	 * wash would recolour every club's hero photo. Asserted on the declaration's
	 * content rather than its exact gradient, so the wash can be retuned for
	 * legibility without the test having to be rewritten each time.
	 */
	public function test_hero_scrim_stays_neutral_ink(): void {
		$decl = $this->declaration( '.ch-home-hero__scrim' );
		$this->assertStringContainsString( 'var(--color-ink)', $decl );
		$this->assertStringNotContainsString( '--color-accent', $decl );
	}

	/** The body of one rule, found by its exact selector. */
	private function declaration( string $selector ): string {
		$css   = $this->css();
		$at    = strpos( $css, $selector . '{' );
		$this->assertNotFalse( $at, "missing rule for {$selector}" );
		$open  = (int) $at + strlen( $selector ) + 1;
		$close = (int) strpos( $css, '}', $open );
		return substr( $css, $open, $close - $open );
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
