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

	/** Normalise '#rgb' / 'rgb' / '#rrggbb' / 'rrggbb' to lowercase '#rrggbb'. */
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
}
