<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure colour math. Turns one club accent into a set of legible, derived
 * tokens (see Task 4). No WordPress, no storage — deterministic functions so
 * the multi-client colour guarantees are unit-tested.
 *
 * @package BlueworxLabsClubhouse
 */
class Blueworx_Clubhouse_Color_Engine {

	/**
	 * Normalise '#rgb' / 'rgb' / '#rrggbb' / 'rrggbb' to lowercase '#rrggbb'.
	 *
	 * Invalid input (bad length or non-hex chars) falls back to '#000000'.
	 */
	protected static function normalize_hex( string $hex ): string {
		$hex = strtolower( ltrim( trim( $hex ), '#' ) );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/', $hex ) ) {
			$hex = '000000';
		}
		return '#' . $hex;
	}

	/** @return array{0:int,1:int,2:int} */
	protected static function to_rgb( string $hex ): array {
		$hex = ltrim( self::normalize_hex( $hex ), '#' );
		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	protected static function to_hex( int $r, int $g, int $b ): string {
		$clamp = static fn ( int $v ): int => max( 0, min( 255, $v ) );
		return sprintf( '#%02x%02x%02x', $clamp( $r ), $clamp( $g ), $clamp( $b ) );
	}

	public static function relative_luminance( string $hex ): float {
		$lin = static function ( int $c ): float {
			$s = $c / 255;
			return $s <= 0.03928 ? $s / 12.92 : ( ( $s + 0.055 ) / 1.055 ) ** 2.4;
		};
		[ $r, $g, $b ] = self::to_rgb( $hex );
		return 0.2126 * $lin( $r ) + 0.7152 * $lin( $g ) + 0.0722 * $lin( $b );
	}

	public static function contrast_ratio( string $a, string $b ): float {
		$la = self::relative_luminance( $a );
		$lb = self::relative_luminance( $b );
		$hi = max( $la, $lb );
		$lo = min( $la, $lb );
		return ( $hi + 0.05 ) / ( $lo + 0.05 );
	}

	public static function mix( string $a, string $b, float $weight_a ): string {
		$weight_a = max( 0.0, min( 1.0, $weight_a ) );
		[ $ar, $ag, $ab ] = self::to_rgb( $a );
		[ $br, $bg, $bb ] = self::to_rgb( $b );
		$blend = static fn ( int $x, int $y ): int => (int) round( $x * $weight_a + $y * ( 1 - $weight_a ) );
		return self::to_hex( $blend( $ar, $br ), $blend( $ag, $bg ), $blend( $ab, $bb ) );
	}

	/**
	 * @return array{0:float,1:float,2:float} h in [0,360), s and l in [0,1]
	 */
	protected static function to_hsl( string $hex ): array {
		[ $r, $g, $b ] = array_map( static fn ( int $c ): float => $c / 255, self::to_rgb( $hex ) );
		$max           = max( $r, $g, $b );
		$min           = min( $r, $g, $b );
		$l             = ( $max + $min ) / 2;
		$d             = $max - $min;

		if ( 0.0 === $d ) {
			return array( 0.0, 0.0, $l ); // Grey: hue is undefined, and 0 is as good as any.
		}

		$s = $d / ( 1 - abs( 2 * $l - 1 ) );
		if ( $max === $r ) {
			$h = fmod( ( $g - $b ) / $d, 6.0 );
		} elseif ( $max === $g ) {
			$h = ( $b - $r ) / $d + 2;
		} else {
			$h = ( $r - $g ) / $d + 4;
		}
		$h = fmod( $h * 60 + 360, 360 );
		return array( $h, $s, $l );
	}

	protected static function from_hsl( float $h, float $s, float $l ): string {
		$h = fmod( fmod( $h, 360 ) + 360, 360 );
		$s = max( 0.0, min( 1.0, $s ) );
		$l = max( 0.0, min( 1.0, $l ) );

		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2.0 ) - 1 ) );
		$m = $l - $c / 2;

		if ( $h < 60 ) {
			[ $r, $g, $b ] = array( $c, $x, 0.0 );
		} elseif ( $h < 120 ) {
			[ $r, $g, $b ] = array( $x, $c, 0.0 );
		} elseif ( $h < 180 ) {
			[ $r, $g, $b ] = array( 0.0, $c, $x );
		} elseif ( $h < 240 ) {
			[ $r, $g, $b ] = array( 0.0, $x, $c );
		} elseif ( $h < 300 ) {
			[ $r, $g, $b ] = array( $x, 0.0, $c );
		} else {
			[ $r, $g, $b ] = array( $c, 0.0, $x );
		}

		return self::to_hex(
			(int) round( ( $r + $m ) * 255 ),
			(int) round( ( $g + $m ) * 255 ),
			(int) round( ( $b + $m ) * 255 )
		);
	}

	/**
	 * The pole — black or white — that contrasts MORE with a shell background.
	 * Blending toward it is how a brand colour is pulled into legibility without
	 * abandoning its hue, and it is the direction "darker" means on a light shell
	 * and "lighter" means on a dark one.
	 */
	protected static function pole_for( string $shell_bg ): string {
		return self::contrast_ratio( '#000000', $shell_bg ) >= self::contrast_ratio( '#ffffff', $shell_bg )
			? '#000000'
			: '#ffffff';
	}

	/**
	 * Derive the legible accent token set for a look's shell.
	 *
	 * @return array{'--color-accent':string,'--color-accent-ink':string,'--color-accent-deep':string,'--color-accent-wash':string,'--color-accent-block':string}
	 */
	public static function derive( string $accent, string $shell_bg, string $shell_ink ): array {
		return self::derive_named( '--color-accent', $accent, $shell_bg, $shell_ink );
	}

	/**
	 * The same derivation under a caller-chosen prefix, so the primary and the
	 * secondary get an identical token set and identical legibility guarantees
	 * from one piece of maths. derive() is this with '--color-accent'.
	 *
	 * Extracted rather than duplicated deliberately: a second copy of this for the
	 * secondary is how the two would drift, and the guarantees below are exactly
	 * what must NOT hold for only one of them.
	 *
	 * @return array<string,string>
	 */
	public static function derive_named( string $prefix, string $accent, string $shell_bg, string $shell_ink ): array {
		$accent = self::normalize_hex( $accent );

		// Ink ON the accent fill: the better-contrasting of the look ink vs
		// white. This is the mathematical best case — black and white are the
		// contrast extremes, so if neither clears AA against the accent, no text
		// colour can (a desaturated mid-luminance accent, e.g. #767676, tops out
		// ~4.2). Such accents are rejected at accent-selection time in the admin
		// UI; for the saturated brand colours clubs use, this pick clears AA
		// (asserted by test_accent_ink_clears_AA_across_saturated_hues).
		$ink = self::contrast_ratio( $shell_ink, $accent ) >= self::contrast_ratio( '#ffffff', $accent )
			? self::normalize_hex( $shell_ink )
			: '#ffffff';

		// Accent-as-text on the shell: blend toward whichever pole (black or
		// white) contrasts MORE with the shell. For any shell luminance at least
		// one pole clears AA, so the loop always ends on a legible value. Integer
		// stepping guarantees the pure pole (i = 0) is actually evaluated, and we
		// break on the first pass to keep the deep colour as close to the brand
		// accent as legibility allows.
		$pole = self::pole_for( $shell_bg );
		$deep = $pole;
		for ( $i = 20; $i >= 0; $i-- ) {
			$candidate = self::mix( $accent, $pole, $i / 20 );
			if ( self::contrast_ratio( $candidate, $shell_bg ) >= 4.5 ) {
				$deep = $candidate;
				break;
			}
		}

		// The fill for large inverted blocks (banner, Home hero, ticker): the look's
		// OWN ink pulled up to 30% toward the accent, so each club's site is tinted
		// with its brand while keeping the look's weight and polarity. Deriving from
		// $shell_ink rather than a fixed colour is what makes this a system-wide rule
		// instead of per-look config — any look inherits it, and a look that fills
		// those blocks with --color-paper (Floodlight) simply never references it.
		//
		// One constraint does double duty: these blocks are painted
		// `background:var(--color-accent-block); color:var(--color-bg)`, so
		// contrast(block, shell_bg) >= 4.5 guarantees BOTH that the block reads
		// against the page AND that the bg-coloured text on it is legible.
		//
		// Floor is plain ink, so the token is never worse than the untinted block it
		// replaces; on a shell whose ink cannot itself clear AA, nothing ink-derived
		// can, and we degrade to exactly ink rather than returning a worse value.
		$block = self::normalize_hex( $shell_ink );
		for ( $i = 6; $i >= 0; $i-- ) { // 6/20 = the 0.30 ceiling; twentieths match accent-deep's grid.
			$candidate = self::mix( $accent, self::normalize_hex( $shell_ink ), $i / 20 );
			if ( self::contrast_ratio( $candidate, $shell_bg ) >= 4.5 ) {
				$block = $candidate;
				break;
			}
		}

		return array(
			$prefix            => $accent,
			$prefix . '-ink'   => $ink,
			$prefix . '-deep'  => $deep,
			$prefix . '-wash'  => self::mix( $accent, self::normalize_hex( $shell_bg ), 0.12 ),
			$prefix . '-block' => $block,
		);
	}

	/**
	 * The secondary colour's full token set: the same five tokens the accent gets,
	 * plus the interaction states a second action colour needs in order to be
	 * usable without every club setting five colours by hand.
	 *
	 * Hover and active blend toward the shell's contrast pole — darker on a light
	 * look, lighter on a dark one — so a single rule reads correctly on all three
	 * looks rather than "darken on hover" inverting on Floodlight. Disabled goes
	 * the other way, toward the background, which is what "receded" means whatever
	 * the polarity.
	 *
	 * The tint (-wash) and the on-colour foreground (-ink) come from the shared
	 * derivation, so the secondary carries the same AA guarantee as the accent.
	 *
	 * @return array<string,string>
	 */
	public static function derive_secondary( string $secondary, string $shell_bg, string $shell_ink ): array {
		$secondary = self::normalize_hex( $secondary );
		$tokens    = self::derive_named( '--color-secondary', $secondary, $shell_bg, $shell_ink );
		$pole      = self::pole_for( $shell_bg );

		$tokens['--color-secondary-hover']    = self::mix( $secondary, $pole, 0.86 );
		$tokens['--color-secondary-active']   = self::mix( $secondary, $pole, 0.72 );
		$tokens['--color-secondary-disabled'] = self::mix( $secondary, self::normalize_hex( $shell_bg ), 0.35 );

		return $tokens;
	}

	/**
	 * The secondary a club gets when it has not chosen one: derived from its
	 * primary rather than hardcoded, so it is in the same family and at the same
	 * intensity instead of being a fixed colour that clashes with somebody's brand.
	 *
	 * A 150° hue turn at the primary's own saturation and lightness. Not the 180°
	 * complement, which is the one relationship that reliably reads as a clash;
	 * 150° is a split-complement — clearly a different colour, still harmonious,
	 * and far enough round that no primary lands on its own hue.
	 *
	 * Saturation is carried across untouched, which is what keeps the pair looking
	 * chosen: a muted primary gets a muted partner, a loud one gets a loud one. A
	 * grey primary (saturation 0) turns into itself, which is the honest answer —
	 * there is no hue to rotate.
	 *
	 * Lightness is carried across TOO, but only where it is legible. Equal HSL
	 * lightness does not mean equal luminance — the shipped lime and its 150° turn
	 * into blue sit at the same L and nowhere near the same brightness — so a
	 * straight rotation can land in the mid-luminance band where no text colour
	 * clears AA, which is the same band derive() rejects a chosen accent for. The
	 * lightness is therefore nudged the shortest distance out of that band, so the
	 * partner is as close to the primary's intensity as legibility allows and
	 * never inside it.
	 *
	 * Judged against the LOOK's own shell rather than against pure black and
	 * white. The two are not interchangeable: Court Side's near-black ink
	 * (#1c1b18) scores about 18% below pure black on the same fill, so a colour
	 * that clears AA against the pole can miss it against the ink actually
	 * painted. Taking the shell is what makes accent_is_legible() — the engine's
	 * existing contract — the bar here too, instead of a hand-tuned margin
	 * standing in for it.
	 */
	public static function default_secondary( string $accent, string $shell_bg, string $shell_ink ): string {
		[ $h, $s, $l ] = self::to_hsl( $accent );
		$h            += 150;

		$candidate = self::from_hsl( $h, $s, $l );
		if ( self::accent_is_legible( $candidate, $shell_bg, $shell_ink ) ) {
			return $candidate;
		}

		// Search outward in both directions at once and stop at the first legible
		// lightness — by construction the nearest one, in whichever direction it
		// happens to lie.
		for ( $step = 0.02; $step <= 1.0; $step += 0.02 ) {
			foreach ( array( $l - $step, $l + $step ) as $try ) {
				if ( $try < 0.0 || $try > 1.0 ) {
					continue;
				}
				$candidate = self::from_hsl( $h, $s, $try );
				if ( self::accent_is_legible( $candidate, $shell_bg, $shell_ink ) ) {
					return $candidate;
				}
			}
		}

		// Only reachable on a shell whose own ink cannot clear AA against anything,
		// where no colour would satisfy the loop. Returning the straight rotation
		// beats returning nothing: every derived token is legibility-clamped anyway.
		return self::from_hsl( $h, $s, $l );
	}

	/**
	 * Is this accent legible against the given shell? True iff BOTH derived
	 * tokens clear WCAG AA (>= 4.5): the ink on the accent fill (accent-ink vs
	 * accent) and the accent-as-text on the shell (accent-deep vs shell bg).
	 *
	 * accent-deep is AA-guaranteed by derive() on any shell, so in practice the
	 * binding constraint is accent-ink — a light accent on a light-ink (dark)
	 * shell has no legible text colour and is rejected. Used by the admin setup
	 * screen to refuse low-contrast accents at selection time.
	 */
	public static function accent_is_legible( string $accent, string $shell_bg, string $shell_ink ): bool {
		$d       = self::derive( $accent, $shell_bg, $shell_ink );
		$ink_ok  = self::contrast_ratio( $d['--color-accent-ink'], $d['--color-accent'] ) >= 4.5;
		$deep_ok = self::contrast_ratio( $d['--color-accent-deep'], self::normalize_hex( $shell_bg ) ) >= 4.5;
		return $ink_ok && $deep_ok;
	}

	/** Is the accent legible as text/marks ON the shell (accent-deep vs bg)? */
	public static function accent_deep_is_legible( string $accent, string $shell_bg ): bool {
		// shell_ink is irrelevant to accent-deep; pass the bg (unused for deep).
		$d = self::derive( $accent, $shell_bg, $shell_bg );
		return self::contrast_ratio( $d['--color-accent-deep'], self::normalize_hex( $shell_bg ) ) >= 4.5;
	}

	/**
	 * Look-aware acceptance: for a look that paints text on the accent fill,
	 * require full legibility (ink + deep); for a glow-only look, require only
	 * accent-deep (the accent never carries text there).
	 */
	public static function accent_is_legible_for( Blueworx_Clubhouse_Base_Look $look, string $accent ): bool {
		$t = $look->tokens();
		if ( $look->accent_bears_text() ) {
			return self::accent_is_legible( $accent, $t['--color-bg'], $t['--color-ink'] );
		}
		return self::accent_deep_is_legible( $accent, $t['--color-bg'] );
	}
}
