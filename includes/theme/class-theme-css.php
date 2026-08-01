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
		return array_merge( $shell, $accent, $secondary );
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
