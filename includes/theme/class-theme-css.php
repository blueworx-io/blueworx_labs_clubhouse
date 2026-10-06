<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composes the final :root custom-property map for the active look + branding:
 * fixed shell tokens, then the derived accent tokens (which win any collision).
 * Pure — the WP wrapper (later plan) caches to_css() output and inlines it in
 * wp_head, so there is no per-request colour math.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Theme_Css {

	/** @return array<string,string> */
	public static function compose(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding
	): array {
		$shell  = $look->tokens();
		$accent = Blueworx_Clubhouse_Color_Engine::derive(
			$branding->get_accent(),
			$shell['--color-bg'],
			$shell['--color-ink']
		);
		// The secondary is composed here, beside the accent, rather than anywhere
		// else — this is the one place a look's shell and a club's brand meet, so
		// emitting it here is what makes it reach every surface the accent reaches:
		// the front-end :root, the Setup screen's per-look tokens, and the live
		// re-skin. Falls back to a value derived from the accent when unset.
		$secondary = Blueworx_Clubhouse_Color_Engine::derive_secondary(
			$branding->effective_secondary( $look ),
			$shell['--color-bg'],
			$shell['--color-ink']
		);
		// The club's name is the other thing a club brings to a look. The footer
		// sets it at poster scale, sized from how much room it takes — measured
		// twice, because two of the looks set it in capitals and one does not.
		// It travels as a token rather than on the element because the sections
		// carry no inline styles.
		$name     = $branding->get_club_name();
		$wordmark = array(
			'--wordmark-len'      => (string) self::wordmark_length( $name ),
			'--wordmark-len-caps' => (string) self::wordmark_length( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $name ) : strtoupper( $name ) ),
		);
		return array_merge( $shell, $accent, $secondary, $wordmark );
	}

	/**
	 * How much of a line a name takes, counted in ordinary lowercase letters.
	 *
	 * A plain character count is too blunt to size the footer wordmark from: an
	 * "i" is under half the width of an "m", so two names of the same length can
	 * differ by a third on the page. Three rough classes — narrow, wide, and
	 * capitals or digits — close most of that gap without knowing the font. The
	 * looks hold the last step, how wide one of these units is in their own
	 * typeface.
	 */
	public static function wordmark_length( string $name ): float {
		$length = 0.0;
		$chars  = preg_split( '//u', $name, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( is_array( $chars ) ? $chars : array() as $char ) {
			if ( false !== strpos( " iIjlftr1.,'’:;!|-()", $char ) ) {
				$length += 0.55;
			} elseif ( false !== strpos( 'mwMW@&%', $char ) ) {
				$length += 1.6;
			} elseif ( 1 === preg_match( '/[\p{Lu}\d]/u', $char ) ) {
				$length += 1.2;
			} else {
				$length += 1.0;
			}
		}
		return max( 1.0, round( $length, 1 ) );
	}

	/** @param array<string,string> $vars */
	public static function to_css( array $vars ): string {
		$decls = '';
		foreach ( $vars as $name => $value ) {
			$decls .= $name . ':' . $value . ';';
		}
		return ':root{' . $decls . '}';
	}
}
