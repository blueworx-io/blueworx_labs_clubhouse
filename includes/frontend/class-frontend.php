<?php
// includes/frontend/class-frontend.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's only WordPress-coupled class: rewrite routing, template
 * selection, and asset enqueue. All HTML is delegated to Page_Map / Page_Renderer.
 * Pure decision helpers (resolve_slug, enqueue_specs) are unit-tested without a
 * WP runtime; the hook wiring is verified with the WP-function shim.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Frontend {

	public const QUERY_VAR = 'clubhouse_page';

	public static function resolve_slug( bool $is_front_page, mixed $query_var ): ?string {
		if ( $is_front_page ) {
			return '';
		}
		if ( is_string( $query_var ) && '' !== $query_var && Blueworx_Clubhouse_Page_Map::has( $query_var ) ) {
			return $query_var;
		}
		return null;
	}

	/**
	 * @return array{fonts_url:string,stylesheet_url:string,inline_css:string,reveal_url:string}
	 */
	public static function enqueue_specs(
		Blueworx_Clubhouse_Base_Look $look,
		string $root_css,
		string $plugin_url
	): array {
		return array(
			'fonts_url'      => Blueworx_Clubhouse_Page_Renderer::google_fonts_url( $look ),
			'stylesheet_url' => $plugin_url . $look->stylesheet(),
			'inline_css'     => $root_css,
			'reveal_url'     => $plugin_url . 'assets/js/reveal.js',
		);
	}
}
