<?php
// tests/php/ColorEngineDeriveTest.php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ColorEngineDeriveTest extends TestCase {

	private const LIGHT_BG  = '#faf8f3';
	private const LIGHT_INK = '#1c1b18';
	private const DARK_BG   = '#17181a';
	private const DARK_INK  = '#f4f2ec';
	private const MID_BG  = '#808080'; // mid grey — luminance ~0.22, the AA danger band
	private const MID_INK = '#ffffff';

	/** @return array<string,string> */
	private function derive( string $accent, bool $dark = false ): array {
		return $dark
			? Blueworx_Clubhouse_Color_Engine::derive( $accent, self::DARK_BG, self::DARK_INK )
			: Blueworx_Clubhouse_Color_Engine::derive( $accent, self::LIGHT_BG, self::LIGHT_INK );
	}

	public function test_returns_expected_keys(): void {
		$t = $this->derive( '#c6f24e' );
		$this->assertSame(
			array( '--color-accent', '--color-accent-ink', '--color-accent-deep', '--color-accent-wash', '--color-accent-block' ),
			array_keys( $t )
		);
	}

	public function test_accent_is_normalised(): void {
		$this->assertSame( '#c6f24e', $this->derive( 'C6F24E' )['--color-accent'] );
	}

	/** A pale accent takes DARK ink; the ink must clear AA on the accent. */
	public function test_pale_accent_gets_dark_ink(): void {
		$t = $this->derive( '#c6f24e' ); // volt lime
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-ink'], '#c6f24e' )
		);
		$this->assertLessThan(
			0.5,
			Blueworx_Clubhouse_Color_Engine::relative_luminance( $t['--color-accent-ink'] )
		);
	}

	/** A dark accent takes LIGHT ink. */
	public function test_dark_accent_gets_light_ink(): void {
		$t = $this->derive( '#1f7a4d' ); // racing green
		$this->assertGreaterThan(
			0.5,
			Blueworx_Clubhouse_Color_Engine::relative_luminance( $t['--color-accent-ink'] )
		);
	}

	/**
	 * Ink placed ON the accent fill must clear AA for the saturated brand hues
	 * clubs actually use. (Mid-luminance desaturated accents can't reach AA with
	 * any text colour — those are rejected at accent-selection time in the admin
	 * UI, not here.)
	 */
	#[DataProvider('hues')]
	public function test_accent_ink_clears_AA_across_saturated_hues( string $accent ): void {
		$t = $this->derive( $accent );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-ink'], $accent ),
			"accent-ink for {$accent} fails AA on the accent fill"
		);
	}

	/**
	 * accent-deep must be legible AS TEXT on the shell for the full hue range —
	 * this is the core multi-client guarantee.
	 */
	#[DataProvider('hues')]
	public function test_accent_deep_clears_AA_on_light_shell( string $accent ): void {
		$t = $this->derive( $accent );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-deep'], self::LIGHT_BG ),
			"accent-deep for {$accent} fails AA on the light shell"
		);
	}

	/**
	 * accent-deep must clear AA as text on a DARK shell for the full hue range
	 * (re-skin safety).
	 */
	#[DataProvider('hues')]
	public function test_accent_deep_clears_AA_on_dark_shell( string $accent ): void {
		$t = $this->derive( $accent, true );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-deep'], self::DARK_BG ),
			"accent-deep for {$accent} fails AA on the dark shell"
		);
	}

	/**
	 * The hardest case: a MID-TONE shell (luminance in the band where a pure
	 * white text pole is NOT trivially safe) must still yield an AA-legible
	 * accent-deep for every hue — the regression guard for the pole-selection fix.
	 */
	#[DataProvider('hues')]
	public function test_accent_deep_clears_AA_on_mid_tone_shell( string $accent ): void {
		$t = Blueworx_Clubhouse_Color_Engine::derive( $accent, self::MID_BG, self::MID_INK );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-deep'], self::MID_BG ),
			"accent-deep for {$accent} fails AA on the mid-tone shell"
		);
	}

	public function test_wash_is_close_to_the_shell(): void {
		$t = $this->derive( '#c6f24e' );
		// A faint tint sits nearer the shell than the raw accent.
		$to_shell = Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-wash'], self::LIGHT_BG );
		$this->assertLessThan( 1.6, $to_shell );
	}

	/**
	 * The block fill is the look's OWN ink pulled 30% toward the accent. This exact
	 * value is the regression anchor: mix() weights toward its FIRST argument, and
	 * swapping the arguments yields #93b23e (2.28 contrast), which fails the guard
	 * at every step and silently falls through to plain ink — i.e. the tint just
	 * disappears rather than erroring. Only this assertion catches that.
	 */
	public function test_accent_block_is_the_ink_tinted_toward_the_accent(): void {
		$this->assertSame( '#4f5c28', $this->derive( '#c6f24e' )['--color-accent-block'] );
	}

	/**
	 * The block is painted `background:var(--color-accent-block); color:var(--color-bg)`,
	 * so this one ratio guarantees BOTH that the block reads against the page and
	 * that the bg-coloured text on it is legible.
	 */
	#[DataProvider('hues')]
	public function test_accent_block_clears_AA_on_light_shell( string $accent ): void {
		$t = $this->derive( $accent );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-block'], self::LIGHT_BG ),
			"accent-block for {$accent} fails AA on the light shell"
		);
	}

	/** Same guarantee on a dark shell — the token must follow the look's polarity. */
	#[DataProvider('hues')]
	public function test_accent_block_clears_AA_on_dark_shell( string $accent ): void {
		$t = $this->derive( $accent, true );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-block'], self::DARK_BG ),
			"accent-block for {$accent} fails AA on the dark shell"
		);
	}

	/**
	 * The guard steps back when the full 30% tint would fail, and still lands on a
	 * TINTED value rather than collapsing to ink. Needs a synthetic shell: the guard
	 * never fires on the shipped light shells at 30% (verified across every hue in
	 * hues() plus #ffffff and #ffff00). Here ink #444444 clears AA at 9.18, the 30%
	 * lime tint measures 4.49 and fails, so the guard steps back one notch to
	 * #657047 at 4.99.
	 */
	public function test_guard_steps_back_but_keeps_a_tint(): void {
		$block = Blueworx_Clubhouse_Color_Engine::derive( '#c6f24e', self::LIGHT_BG, '#444444' )['--color-accent-block'];
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $block, self::LIGHT_BG ),
			'guard must land on a value that clears AA'
		);
		$this->assertNotSame( '#444444', $block, 'guard must keep a tint, not collapse to ink' );
	}

	/**
	 * The floor is the look's ink. On a shell whose ink cannot itself clear AA
	 * (#ffffff on #808080 measures 3.95), no tint can either, so the token degrades
	 * to exactly the ink — never worse than today's background:var(--color-ink).
	 * AA is unreachable on such a shell and is not claimed.
	 */
	public function test_accent_block_falls_back_to_ink_when_no_tint_clears(): void {
		$t = Blueworx_Clubhouse_Color_Engine::derive( '#c6f24e', self::MID_BG, self::MID_INK );
		$this->assertSame( self::MID_INK, $t['--color-accent-block'] );
	}

	/** @return array<string,array{0:string}> */
	public static function hues(): array {
		return array(
			'lime'   => array( '#c6f24e' ),
			'orange' => array( '#ff5b23' ),
			'teal'   => array( '#0bb3a2' ),
			'green'  => array( '#1f7a4d' ),
			'cobalt' => array( '#3b5bdb' ),
			'berry'  => array( '#c2337a' ),
			'yellow' => array( '#ffd23f' ),
		);
	}
}
