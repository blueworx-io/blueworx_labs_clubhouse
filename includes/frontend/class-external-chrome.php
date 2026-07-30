<?php
// includes/frontend/class-external-chrome.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps pages this plugin does NOT own — SureCart's customer dashboard and the
 * like — in the Clubhouse header and footer.
 *
 * Why this exists: the plugin is designed to run alongside a deliberately
 * style-free theme (blankslate), and Frontend only takes over the slugs in
 * Page_Map. A page another plugin owns therefore renders through that bare
 * theme: SureCart's own component CSS loads and looks fine, but there is no
 * header, no nav, no footer, no container width and no site typography around
 * it. The page reads as broken even though nothing is.
 *
 * What it deliberately does NOT do is style the other plugin's markup. The
 * chrome and the look's design tokens (fonts, colours, spacing scale) are
 * loaded so the page belongs to the site; the content well is an empty box.
 * SureCart styles SureCart.
 *
 * The chrome is injected by filtering the finished response rather than by
 * hooking wp_body_open/wp_footer, because whether those fire is the host
 * theme's choice and the theme here is not ours. A string filter works for any
 * theme, and the decision plus the injection are pure functions, so both are
 * unit-tested without a WordPress runtime.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_External_Chrome {

	/**
	 * Marker in a page template's slug that identifies a page as SureCart's.
	 * SureCart's dashboard sets `pages/template-surecart-dashboard.php`, which
	 * is the signal available early enough to decide on — the `surecart-template`
	 * body class it also adds is only filtered while the body is being rendered,
	 * long after wp_enqueue_scripts has run.
	 */
	private const SURECART_TEMPLATE_MARKER = 'surecart';

	/**
	 * Whether this request should be dressed in the Clubhouse chrome. Pure.
	 *
	 * A clubhouse page is excluded because it already renders the full document
	 * itself; dressing it would emit a second header and footer.
	 *
	 * @param bool   $is_clubhouse_page Whether Frontend is serving this request.
	 * @param bool   $is_singular       Whether a single post/page is being shown.
	 * @param string $template_slug     The page's stored _wp_page_template value.
	 * @param bool   $forced            An explicit opt-in from the filter below.
	 */
	public static function dresses( bool $is_clubhouse_page, bool $is_singular, string $template_slug, bool $forced = false ): bool {
		if ( $is_clubhouse_page || ! $is_singular ) {
			return false;
		}
		if ( $forced ) {
			return true;
		}
		return str_contains( strtolower( $template_slug ), self::SURECART_TEMPLATE_MARKER );
	}

	/**
	 * Put $header directly after the opening <body> tag and $footer directly
	 * before the closing one. Pure.
	 *
	 * A response with no <body> — a feed, a REST payload, an already-buffered
	 * fragment — is returned untouched rather than half-wrapped, so a surprise
	 * response shape degrades to "no chrome" and never to broken markup.
	 */
	public static function inject( string $html, string $header, string $footer ): string {
		$open = strpos( $html, '<body' );
		if ( false === $open ) {
			return $html;
		}
		$open_end = strpos( $html, '>', $open );
		$close    = strrpos( $html, '</body>' );
		if ( false === $open_end || false === $close || $close < $open_end ) {
			return $html;
		}
		return substr( $html, 0, $open_end + 1 )
			. $header
			. substr( $html, $open_end + 1, $close - $open_end - 1 )
			. $footer
			. substr( $html, $close );
	}

	/**
	 * The container the other plugin's content is rendered into. The markup
	 * itself lives on Sections with every other ch-* class, so the static look
	 * coverage check can see it.
	 */
	public static function open_content(): string {
		return Blueworx_Clubhouse_Sections::external_open();
	}

	public static function close_content(): string {
		return Blueworx_Clubhouse_Sections::external_close();
	}

	public static function register(): void {
		// Priority 100: after anything that might redirect or take the request
		// over, so we only buffer a response that is actually going to render.
		add_action( 'template_redirect', array( self::class, 'maybe_start' ), 100 );
		// Priority 20 for the same reason Frontend uses it — the look stylesheet
		// has to print after the theme's to win the source-order tie on `body`.
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue' ), 20 );
	}

	/** Whether the request in flight is one we dress. */
	public static function applies(): bool {
		if ( ! function_exists( 'is_singular' ) ) {
			return false;
		}
		$slug = function_exists( 'get_page_template_slug' ) ? get_page_template_slug() : '';
		/**
		 * Opt a page into the Clubhouse chrome that template detection misses —
		 * SureCart's checkout and order pages are plain block pages, so they carry
		 * no template slug to key off.
		 *
		 * @param bool $forced Whether to dress this request regardless.
		 */
		$forced = (bool) apply_filters( 'blueworx_clubhouse_dress_external_page', false );
		return self::dresses(
			Blueworx_Clubhouse_Frontend::is_clubhouse_page(),
			(bool) is_singular(),
			is_string( $slug ) ? $slug : '',
			$forced
		);
	}

	public static function maybe_enqueue(): void {
		if ( ! self::applies() ) {
			return;
		}
		// Styles only, no reveal.js: it adds an initially-hidden class to the
		// children of .ch-main, and hiding another plugin's UI behind our scroll
		// animation is exactly the interference this wrapper avoids. The content
		// well is .ch-external for the same reason — it is not .ch-main, so the
		// look's flow-margin rules do not reach inside it either.
		Blueworx_Clubhouse_Frontend::enqueue_look_styles();
	}

	public static function maybe_start(): void {
		if ( ! self::applies() ) {
			return;
		}
		ob_start( array( self::class, 'wrap' ) );
	}

	/**
	 * The ob_start callback. Builds the chrome from the same branding, content
	 * and visibility the clubhouse pages use, so the nav an owner configures is
	 * the nav that appears here.
	 */
	public static function wrap( string $html ): string {
		Blueworx_Clubhouse_Links::set_resolver( array( Blueworx_Clubhouse_Frontend::class, 'link_url' ) );
		Blueworx_Clubhouse_Menu::set_provider(
			static fn(): Blueworx_Clubhouse_Menu => new Blueworx_Clubhouse_Menu( new Blueworx_Clubhouse_Options_Storage() )
		);
		$ctx = Blueworx_Clubhouse_Frontend::context();
		if ( null === $ctx->look ) {
			return $html;
		}
		$logo_url = Blueworx_Clubhouse_Frontend::resolve_logo( $ctx->branding->get_logo() );
		$club     = $ctx->branding->get_club_name();
		$header   = Blueworx_Clubhouse_Page_Renderer::chrome_header( $club, $ctx->visibility, $ctx->collections, $logo_url, $ctx->content )
			. self::open_content();
		$footer   = self::close_content()
			. Blueworx_Clubhouse_Page_Renderer::chrome_footer( $club, $ctx->visibility, $ctx->branding, $ctx->content );
		return self::inject( $html, $header, $footer );
	}
}
