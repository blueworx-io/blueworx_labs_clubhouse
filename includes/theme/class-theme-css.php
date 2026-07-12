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
		return array_merge( $shell, $accent );
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
