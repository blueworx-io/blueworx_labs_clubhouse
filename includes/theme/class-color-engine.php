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
	 * Derive the legible accent token set for a look's shell.
	 *
	 * @return array{'--color-accent':string,'--color-accent-ink':string,'--color-accent-deep':string,'--color-accent-wash':string}
	 */
	public static function derive( string $accent, string $shell_bg, string $shell_ink ): array {
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
		$pole = self::contrast_ratio( '#000000', $shell_bg ) >= self::contrast_ratio( '#ffffff', $shell_bg )
			? '#000000'
			: '#ffffff';
		$deep = $pole;
		for ( $i = 20; $i >= 0; $i-- ) {
			$candidate = self::mix( $accent, $pole, $i / 20 );
			if ( self::contrast_ratio( $candidate, $shell_bg ) >= 4.5 ) {
				$deep = $candidate;
				break;
			}
		}

		return array(
			'--color-accent'      => $accent,
			'--color-accent-ink'  => $ink,
			'--color-accent-deep' => $deep,
			'--color-accent-wash' => self::mix( $accent, self::normalize_hex( $shell_bg ), 0.12 ),
		);
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
}
