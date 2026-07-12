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
		if ( is_string( $query_var ) && '' !== $query_var && Blueworx_Clubhouse_Page_Map::has( $query_var ) ) {
			return $query_var;
		}
		return $is_front_page ? '' : null;
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

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_rewrites' ) );
		add_action( 'init', array( Blueworx_Clubhouse_Collection_Types::class, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_filter( 'template_include', array( self::class, 'filter_template' ) );
		add_filter( 'wp_resource_hints', array( self::class, 'resource_hints' ), 10, 2 );
	}

	/**
	 * @param array<int,mixed> $urls
	 * @return array<int,mixed>
	 */
	public static function resource_hints( array $urls, string $relation_type ): array {
		if ( 'preconnect' === $relation_type && null !== self::current_slug() ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
		}
		return $urls;
	}

	public static function register_rewrites(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			if ( '' === $page['slug'] ) {
				continue;
			}
			add_rewrite_rule(
				'^' . $page['slug'] . '/?$',
				'index.php?' . self::QUERY_VAR . '=' . $page['slug'],
				'top'
			);
		}
	}

	private static function current_slug(): ?string {
		$is_front = function_exists( 'is_front_page' ) ? is_front_page() : false;
		$qv       = function_exists( 'get_query_var' ) ? get_query_var( self::QUERY_VAR ) : '';
		return self::resolve_slug( (bool) $is_front, $qv );
	}

	public static function filter_template( string $template ): string {
		$slug = self::current_slug();
		if ( null === $slug ) {
			return $template;
		}
		return dirname( __DIR__, 2 ) . '/templates/clubhouse.php';
	}

	private static function context(): array {
		$storage    = new Blueworx_Clubhouse_Options_Storage();
		$registry   = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$registry->register( new Blueworx_Clubhouse_Court_Side() );
		$look       = $registry->active();
		$branding   = new Blueworx_Clubhouse_Branding( $storage );
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$cache      = new Blueworx_Clubhouse_Theme_Cache( $storage );
		$collections = new Blueworx_Clubhouse_WP_Collections();
		return array( $look, $branding, $visibility, $cache, $collections );
	}

	public static function enqueue_assets(): void {
		if ( null === self::current_slug() ) {
			return;
		}
		list( $look, $branding, , $cache ) = self::context();
		if ( null === $look ) {
			return;
		}
		$specs = self::enqueue_specs(
			$look,
			$cache->root_css( $look, $branding ),
			BLUEWORX_LABS_CLUBHOUSE_URL
		);
		wp_enqueue_style( 'clubhouse-fonts', $specs['fonts_url'], array(), null );
		wp_enqueue_style( 'clubhouse-look', $specs['stylesheet_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_add_inline_style( 'clubhouse-look', $specs['inline_css'] );
		wp_enqueue_script( 'clubhouse-reveal', $specs['reveal_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	/** Render the current page body (used by the canvas template). */
	public static function render_body(): string {
		$slug = self::current_slug();
		if ( null === $slug ) {
			return '';
		}
		list( , $branding, $visibility, , $collections ) = self::context();
		return Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility, $collections );
	}

	public static function club_name(): string {
		list( , $branding, , ) = self::context();
		return $branding->get_club_name();
	}
}
