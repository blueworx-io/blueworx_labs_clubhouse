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

	/**
	 * Structural rules shared by every look, loaded before the look's own
	 * stylesheet. Deliberately not a Base_Look method: a look substituting its
	 * own base is the drift this file prevents.
	 */
	public const BASE_STYLESHEET = 'assets/looks/base.css';

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
	 * @return array{font_face_css:string,base_stylesheet_url:string,stylesheet_url:string,inline_css:string,reveal_url:string}
	 */
	public static function enqueue_specs(
		Blueworx_Clubhouse_Base_Look $look,
		string $root_css,
		string $plugin_url
	): array {
		return array(
			'font_face_css'       => Blueworx_Clubhouse_Page_Renderer::font_face_css( $look, $plugin_url ),
			'base_stylesheet_url' => $plugin_url . self::BASE_STYLESHEET,
			'stylesheet_url'      => $plugin_url . $look->stylesheet(),
			'inline_css'          => $root_css,
			'reveal_url'          => $plugin_url . 'assets/js/reveal.js',
		);
	}

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_rewrites' ) );
		add_action( 'init', array( Blueworx_Clubhouse_Collection_Types::class, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'wp_head', array( self::class, 'render_favicon' ) );
		add_filter( 'template_include', array( self::class, 'filter_template' ) );
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

	/**
	 * True when this request renders a clubhouse page. Anything that decorates the
	 * clubhouse look must gate on this, because enqueue_assets() does: off a
	 * clubhouse page there is no look stylesheet, so the design tokens it would
	 * modify are not on the page to modify.
	 */
	public static function is_clubhouse_page(): bool {
		return null !== self::current_slug();
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
		$storage    = new Blueworx_Clubhouse_Options_Storage();
		$registry   = self::registry( $storage );
		$demo_slug  = Blueworx_Clubhouse_Demo_Controller::look_slug( $registry );
		$look       = null !== $demo_slug ? $registry->get( $demo_slug ) : $registry->active();
		return new Blueworx_Clubhouse_Clubhouse_Context(
			$look,
			new Blueworx_Clubhouse_Branding( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage ),
			new Blueworx_Clubhouse_Theme_Cache( $storage ),
			new Blueworx_Clubhouse_WP_Collections(),
			$registry,
			new Blueworx_Clubhouse_Content_Store( $storage )
		);
	}

	/** The Base Look slug this request will render (demo override or saved active). */
	public static function active_look_slug(): ?string {
		$look = self::context()->look;
		return null === $look ? null : $look->slug();
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
		wp_enqueue_style( 'clubhouse-base', $specs['base_stylesheet_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_enqueue_style( 'clubhouse-look', $specs['stylesheet_url'], array( 'clubhouse-base' ), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_add_inline_style( 'clubhouse-look', $specs['font_face_css'], 'before' );
		wp_add_inline_style( 'clubhouse-look', $specs['inline_css'] );
		wp_enqueue_script( 'clubhouse-reveal', $specs['reveal_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	/** Turn a stored logo (attachment ID or legacy URL) into a URL string for the header. */
	public static function resolve_logo( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}
		return ctype_digit( $stored ) ? Blueworx_Clubhouse_Media::url( (int) $stored ) : $stored;
	}

	/** Build the favicon <link> for a resolved favicon URL; empty string when none. */
	public static function favicon_link_html( string $favicon_url ): string {
		if ( '' === $favicon_url ) {
			return '';
		}
		return '<link rel="icon" href="' . htmlspecialchars( $favicon_url, ENT_QUOTES, 'UTF-8' ) . '">';
	}

	/**
	 * Echo the favicon <link> on every front-end page (wp_head), not only clubhouse
	 * pages: the favicon identifies the whole site, including native blog posts the
	 * neutral theme renders. Self-guards — favicon_link_html emits nothing until the
	 * owner sets a favicon, so no gate on the clubhouse route is needed or wanted.
	 */
	public static function render_favicon(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Options_Storage() );
		$favicon  = self::resolve_logo( $branding->get_favicon() );
		echo self::favicon_link_html( $favicon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in favicon_link_html.
	}

	/** Render the current page body (used by the canvas template). */
	public static function render_body(): string {
		$slug = self::current_slug();
		if ( null === $slug ) {
			return '';
		}
		Blueworx_Clubhouse_Links::set_resolver( array( self::class, 'link_url' ) );
		$ctx      = self::context();
		$logo_url = self::resolve_logo( $ctx->branding->get_logo() );
		return Blueworx_Clubhouse_Page_Map::render( $slug, $ctx->branding, $ctx->visibility, $ctx->collections, $logo_url, $ctx->content );
	}

	/**
	 * Resolve an internal page key ('home', 'about', …) to a real WordPress URL.
	 * Installed as the Links resolver during front-end rendering so the shared
	 * renderer emits permalinks (/about/) instead of the preview's ?page= form.
	 * Falls back to the clubhouse_page query var when permalinks are plain.
	 */
	public static function link_url( string $key ): string {
		$slug = 'home' === $key ? '' : $key;
		if ( '' === $slug ) {
			return home_url( '/' );
		}
		if ( '' !== (string) get_option( 'permalink_structure', '' ) ) {
			return home_url( '/' . $slug . '/' );
		}
		return home_url( '/?' . self::QUERY_VAR . '=' . $slug );
	}

	public static function club_name(): string {
		return self::context()->branding->get_club_name();
	}
}
