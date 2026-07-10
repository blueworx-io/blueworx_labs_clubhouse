<?php
// tests/php/ColorEngineDeriveTest.php

use PHPUnit\Framework\TestCase;

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
			array( '--color-accent', '--color-accent-ink', '--color-accent-deep', '--color-accent-wash' ),
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
	 * accent-deep must be legible AS TEXT on the shell for the full hue range —
	 * this is the core multi-client guarantee.
	 *
	 * @dataProvider hues
	 */
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
	 *
	 * @dataProvider hues
	 */
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
	 *
	 * @dataProvider hues
	 */
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
