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

	public static function resolve_slug( bool $is_front_page, mixed $query_var, ?Blueworx_Clubhouse_Visibility $visibility = null ): ?string {
		$slug = null;
		if ( is_string( $query_var ) && '' !== $query_var && Blueworx_Clubhouse_Page_Map::has( $query_var ) ) {
			$slug = $query_var;
		} elseif ( $is_front_page ) {
			$slug = '';
		}
		if ( null === $slug ) {
			return null;
		}
		$page = '' === $slug ? 'home' : $slug;
		if ( null !== $visibility && ! $visibility->is_page_visible( $page ) ) {
			return null;
		}
		return $slug;
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
		return self::resolve_slug( (bool) $is_front, $qv, self::context()->visibility );
	}

	public static function filter_template( string $template ): string {
		$slug = self::current_slug();
		if ( null === $slug ) {
			return $template;
		}
		return dirname( __DIR__, 2 ) . '/templates/clubhouse.php';
	}

	/** Build a Base Look registry with all packs registered (Court Side first = fallback). */
	public static function registry( Blueworx_Clubhouse_Storage $storage ): Blueworx_Clubhouse_Base_Look_Registry {
		$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$registry->register( new Blueworx_Clubhouse_Court_Side() );
		$registry->register( new Blueworx_Clubhouse_Members_House() );
		$registry->register( new Blueworx_Clubhouse_Floodlight() );
		return $registry;
	}

	private static function context(): Blueworx_Clubhouse_Clubhouse_Context {
		$storage  = new Blueworx_Clubhouse_Options_Storage();
		$registry = self::registry( $storage );
		return new Blueworx_Clubhouse_Clubhouse_Context(
			$registry->active(),
			new Blueworx_Clubhouse_Branding( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage ),
			new Blueworx_Clubhouse_Theme_Cache( $storage ),
			new Blueworx_Clubhouse_WP_Collections(),
			$registry
		);
	}

	public static function enqueue_assets(): void {
		if ( null === self::current_slug() ) {
			return;
		}
		$ctx = self::context();
		if ( null === $ctx->look ) {
			return;
		}
		$specs = self::enqueue_specs(
			$ctx->look,
			$ctx->cache->root_css( $ctx->look, $ctx->branding ),
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
		$ctx = self::context();
		return Blueworx_Clubhouse_Page_Map::render( $slug, $ctx->branding, $ctx->visibility, $ctx->collections );
	}

	public static function club_name(): string {
		return self::context()->branding->get_club_name();
	}
}
