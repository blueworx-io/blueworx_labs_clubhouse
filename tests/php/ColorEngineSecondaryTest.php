<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The secondary colour's derivation. The point of these is that the secondary
 * carries the SAME legibility guarantees as the accent — it is spent on real
 * text and real marks, so "second colour" must not mean "second-class colour".
 */
final class ColorEngineSecondaryTest extends TestCase {

	/** @return array<int,Blueworx_Clubhouse_Base_Look> */
	private function looks(): array {
		return array(
			new Blueworx_Clubhouse_Court_Side(),
			new Blueworx_Clubhouse_Members_House(),
			new Blueworx_Clubhouse_Floodlight(),
		);
	}

	/**
	 * The ten colours the old Setup screen offered as preset swatches. The
	 * picker is the browser's own now — the page editor library's colour
	 * control has no preset list — so these are no longer offered anywhere,
	 * but they are still the colours a club actually reaches for, and each one
	 * has to survive the engine. #1f8a5c was caught this way: a mid-green that
	 * cleared neither black nor white.
	 *
	 * @return array<int,string>
	 */
	private function hues(): array {
		return array(
			'#c6f24e', // Volt lime — the shipped default.
			'#166534', // Pitch green.
			'#0b6fd1', // Club blue.
			'#123a8c', // Navy.
			'#7b2ff2', // Violet.
			'#c2185b', // Magenta.
			'#d62828', // Club red.
			'#e2711d', // Orange.
			'#f2b705', // Gold.
			'#0f766e', // Teal.
		);
	}

	public function test_it_emits_the_full_token_set(): void {
		$t = Blueworx_Clubhouse_Color_Engine::derive_secondary( '#0b6fd1', '#faf8f3', '#1c1b18' );
		foreach ( array( '', '-ink', '-deep', '-wash', '-block', '-hover', '-active', '-disabled' ) as $suffix ) {
			$this->assertArrayHasKey( '--color-secondary' . $suffix, $t, $suffix );
			$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $t[ '--color-secondary' . $suffix ] );
		}
		$this->assertSame( '#0b6fd1', $t['--color-secondary'] );
	}

	/**
	 * derive() and derive_named() are the same maths under two prefixes. If they
	 * ever diverge, one of the two colours quietly loses its guarantees.
	 */
	public function test_the_secondary_is_derived_by_the_same_maths_as_the_accent(): void {
		$accent    = Blueworx_Clubhouse_Color_Engine::derive( '#c2185b', '#faf8f3', '#1c1b18' );
		$secondary = Blueworx_Clubhouse_Color_Engine::derive_secondary( '#c2185b', '#faf8f3', '#1c1b18' );
		foreach ( array( '', '-ink', '-deep', '-wash', '-block' ) as $suffix ) {
			$this->assertSame(
				$accent[ '--color-accent' . $suffix ],
				$secondary[ '--color-secondary' . $suffix ],
				$suffix
			);
		}
	}

	/**
	 * The pairings the stylesheets actually paint must clear WCAG AA on every look.
	 *
	 * -deep on the page is universal: every look outlines and letters its secondary
	 * button in it. -ink ON the fill is asserted only where the look fills — Court
	 * Side and Members House flip to the fill on hover, Floodlight glows and never
	 * puts text on the colour, which is exactly the distinction accent_bears_text()
	 * already draws for the primary.
	 */
	public function test_the_painted_pairings_clear_AA_on_every_look_and_preset(): void {
		foreach ( $this->looks() as $look ) {
			$tokens = $look->tokens();
			foreach ( $this->hues() as $hue ) {
				$t = Blueworx_Clubhouse_Color_Engine::derive_secondary( $hue, $tokens['--color-bg'], $tokens['--color-ink'] );

				$deep = Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-secondary-deep'], $tokens['--color-bg'] );
				$this->assertGreaterThanOrEqual( 4.5, $deep, $look->slug() . ' ' . $hue . ' deep-on-bg' );

				if ( $look->accent_bears_text() ) {
					$ink = Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-secondary-ink'], $t['--color-secondary'] );
					$this->assertGreaterThanOrEqual( 4.5, $ink, $look->slug() . ' ' . $hue . ' ink-on-fill' );
				}
			}
		}
	}

	/**
	 * Every preset the picker offers is a colour the save would accept. A swatch
	 * the screen then refuses is worse than no swatch at all.
	 */
	public function test_every_preset_swatch_would_survive_a_save(): void {
		foreach ( $this->looks() as $look ) {
			foreach ( $this->hues() as $hue ) {
				$this->assertTrue(
					Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $look, $hue ),
					$hue . ' on ' . $look->slug()
				);
			}
		}
	}

	/**
	 * Hover and active go TOWARD the shell's contrast pole — darker on a light
	 * look, lighter on a dark one — so one stylesheet rule reads correctly on all
	 * three looks instead of inverting on Floodlight.
	 */
	public function test_hover_and_active_move_away_from_the_background_on_either_polarity(): void {
		$light = Blueworx_Clubhouse_Color_Engine::derive_secondary( '#0b6fd1', '#faf8f3', '#1c1b18' );
		$this->assertLessThan(
			Blueworx_Clubhouse_Color_Engine::relative_luminance( $light['--color-secondary'] ),
			Blueworx_Clubhouse_Color_Engine::relative_luminance( $light['--color-secondary-hover'] ),
			'on a light look, hover darkens'
		);
		$this->assertLessThan(
			Blueworx_Clubhouse_Color_Engine::relative_luminance( $light['--color-secondary-hover'] ),
			Blueworx_Clubhouse_Color_Engine::relative_luminance( $light['--color-secondary-active'] ),
			'active goes further than hover'
		);

		$dark = Blueworx_Clubhouse_Color_Engine::derive_secondary( '#0b6fd1', '#0b0f14', '#e8eef5' );
		$this->assertGreaterThan(
			Blueworx_Clubhouse_Color_Engine::relative_luminance( $dark['--color-secondary'] ),
			Blueworx_Clubhouse_Color_Engine::relative_luminance( $dark['--color-secondary-hover'] ),
			'on a dark look, hover lightens'
		);
	}

	/** Disabled recedes toward the page — the same meaning on either polarity. */
	public function test_disabled_moves_toward_the_background(): void {
		foreach ( array( array( '#faf8f3', '#1c1b18' ), array( '#0b0f14', '#e8eef5' ) ) as [ $bg, $ink ] ) {
			$t = Blueworx_Clubhouse_Color_Engine::derive_secondary( '#d62828', $bg, $ink );
			$this->assertLessThan(
				Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-secondary'], $bg ),
				Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-secondary-disabled'], $bg ),
				$bg
			);
		}
	}

	/**
	 * An unset secondary is derived from the primary: a 150° hue turn at the
	 * primary's own saturation. That is what makes the pair look chosen rather
	 * than a fixed colour bolted onto somebody's brand.
	 */
	public function test_the_default_secondary_is_a_different_colour(): void {
		$tokens = ( new Blueworx_Clubhouse_Court_Side() )->tokens();
		foreach ( $this->hues() as $hue ) {
			$derived = Blueworx_Clubhouse_Color_Engine::default_secondary( $hue, $tokens['--color-bg'], $tokens['--color-ink'] );
			$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $derived );
			$this->assertNotSame( strtolower( $hue ), $derived, 'a derived secondary is a different colour' );
		}
	}

	/**
	 * The lightness nudge exists because equal HSL lightness does not mean equal
	 * luminance: rotating the shipped lime lands on a blue at the same L and
	 * squarely in the mid-luminance band, where no text colour clears AA against
	 * a real look's ink. The default MUST come out of that band.
	 *
	 * The judgement is against the LOOK's shell, not against pure black and white.
	 * Court Side's near-black ink scores about 18% below the pole on the same
	 * fill, which is the whole reason a pole-based margin was not good enough —
	 * this case passed against black and still failed on the actual screen.
	 */
	public function test_the_default_leaves_the_illegible_band_judged_on_the_real_shell(): void {
		$cs       = new Blueworx_Clubhouse_Court_Side();
		$tokens   = $cs->tokens();
		$straight = '#4e74f2'; // The shipped lime rotated 150 degrees, lightness untouched.

		$this->assertFalse(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( $straight, $tokens['--color-bg'], $tokens['--color-ink'] ),
			'the straight rotation really is illegible on this look'
		);
		$this->assertGreaterThanOrEqual(
			5.0,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( '#000000', $straight ),
			'and it would have passed a pole-based check — which is why that check was wrong'
		);

		$derived = Blueworx_Clubhouse_Color_Engine::default_secondary( '#c6f24e', $tokens['--color-bg'], $tokens['--color-ink'] );
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( $derived, $tokens['--color-bg'], $tokens['--color-ink'] ),
			'the derived default is legible on the look it was derived for'
		);
	}

	/** The derived partner keeps the primary's hue relationship on either polarity. */
	public function test_the_default_is_derived_per_look(): void {
		$light = ( new Blueworx_Clubhouse_Court_Side() )->tokens();
		$dark  = ( new Blueworx_Clubhouse_Floodlight() )->tokens();
		$this->assertNotSame(
			Blueworx_Clubhouse_Color_Engine::default_secondary( '#c6f24e', $light['--color-bg'], $light['--color-ink'] ),
			Blueworx_Clubhouse_Color_Engine::default_secondary( '#c6f24e', $dark['--color-bg'], $dark['--color-ink'] ),
			'a light and a dark look need different lightnesses to stay legible'
		);
	}

	/**
	 * Not the 180° complement — that is the one relationship that reliably reads
	 * as a clash. 150° is a split-complement: clearly different, still harmonious.
	 */
	public function test_the_default_is_a_split_complement_not_the_opposite(): void {
		$t = ( new Blueworx_Clubhouse_Court_Side() )->tokens();
		// Pure red at 0°; +150° lands in the greens, not cyan (which is 180°).
		$derived = Blueworx_Clubhouse_Color_Engine::default_secondary( '#ff0000', $t['--color-bg'], $t['--color-ink'] );
		[ $h ]   = array_map( 'floatval', self::hsl_of( $derived ) );
		$this->assertGreaterThan( 120.0, $h );
		$this->assertLessThan( 175.0, $h, 'not the 180-degree complement' );
	}

	/** A grey primary has no hue to turn, and says so rather than inventing one. */
	public function test_a_grey_primary_keeps_its_own_hue(): void {
		$t       = ( new Blueworx_Clubhouse_Court_Side() )->tokens();
		$derived = Blueworx_Clubhouse_Color_Engine::default_secondary( '#808080', $t['--color-bg'], $t['--color-ink'] );
		[ , $s ] = self::hsl_of( $derived );
		$this->assertSame( 0.0, round( $s, 3 ), 'a grey stays grey — there is no hue to rotate' );
	}

	/**
	 * Hue/saturation of a hex, for assertions about the rotation. The engine's own
	 * conversion is protected (it is an implementation detail, not API), so this
	 * is an independent reimplementation — which is the right thing for a test:
	 * it would not follow a bug in the engine's version.
	 *
	 * @return array{0:float,1:float}
	 */
	private static function hsl_of( string $hex ): array {
		$h   = ltrim( $hex, '#' );
		$rgb = array_map( static fn ( string $p ): float => hexdec( $p ) / 255, str_split( $h, 2 ) );
		$max = max( $rgb );
		$min = min( $rgb );
		$d   = $max - $min;
		$l   = ( $max + $min ) / 2;
		if ( 0.0 === $d ) {
			return array( 0.0, 0.0 );
		}
		[ $r, $g, $b ] = $rgb;
		if ( $max === $r ) {
			$hue = fmod( ( $g - $b ) / $d, 6.0 );
		} elseif ( $max === $g ) {
			$hue = ( $b - $r ) / $d + 2;
		} else {
			$hue = ( $r - $g ) / $d + 4;
		}
		return array( fmod( $hue * 60 + 360, 360 ), $d / ( 1 - abs( 2 * $l - 1 ) ) );
	}

	/**
	 * The derived default must be USABLE, not merely different. A club that never
	 * opens the secondary field still gets one on every page, so it has to clear
	 * the same bar as a colour somebody chose deliberately — on every look, from
	 * every primary.
	 */
	public function test_the_derived_default_is_legible_on_every_look(): void {
		foreach ( $this->looks() as $look ) {
			$tokens = $look->tokens();
			foreach ( $this->hues() as $hue ) {
				$default = Blueworx_Clubhouse_Color_Engine::default_secondary( $hue, $tokens['--color-bg'], $tokens['--color-ink'] );
				$t       = Blueworx_Clubhouse_Color_Engine::derive_secondary(
					$default,
					$tokens['--color-bg'],
					$tokens['--color-ink']
				);
				$this->assertGreaterThanOrEqual(
					4.5,
					Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-secondary-deep'], $tokens['--color-bg'] ),
					$look->slug() . ' from ' . $hue
				);
				if ( $look->accent_bears_text() ) {
					$this->assertGreaterThanOrEqual(
						4.5,
						Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-secondary-ink'], $t['--color-secondary'] ),
						$look->slug() . ' from ' . $hue
					);
				}
			}
		}
	}
}
