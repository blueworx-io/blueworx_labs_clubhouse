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
	 * Whether this request should be dressed in the Clubhouse chrome. Pure.
	 *
	 * The rule is "everything the plugin does not render itself". It used to be
	 * "SureCart's dashboard, and anything a filter opts in", which left the 404,
	 * every blog post, every category archive and every product page rendering
	 * as bare theme output — blue underlined links, no header, no footer and no
	 * way back to the club. Those URLs are in the sitemap, so they are what a
	 * visitor arriving from a search result could land on.
	 *
	 * Two things are excluded outright, and neither the filter nor anything else
	 * can opt them back in:
	 *
	 *   - A clubhouse page, which already renders the full document itself;
	 *     dressing it would emit a second header and footer.
	 *   - Anything on a SureCart template — the customer dashboard, checkout,
	 *     order pages, products. SureCart ships a complete, self-contained UI and
	 *     wrapping it in the club's header, footer and design tokens fought its
	 *     own styling instead of complementing it. Commerce pages stand alone.
	 *
	 * @param bool $is_clubhouse_page Whether Frontend is serving this request.
	 * @param bool $is_dressable_view Whether this is a front-end view that renders
	 *                                a page (not a feed, robots.txt or a redirect).
	 * @param bool $is_surecart_view  Whether SureCart owns this page.
	 * @param bool $forced            An explicit opt-in from the filter below.
	 */
	public static function dresses( bool $is_clubhouse_page, bool $is_dressable_view, bool $is_surecart_view, bool $forced = false ): bool {
		if ( $is_clubhouse_page || $is_surecart_view ) {
			return false;
		}
		return $forced || $is_dressable_view;
	}

	/**
	 * Whether SureCart owns the page being rendered. Pure, so the matching is
	 * testable without WordPress.
	 *
	 * Matched on two independent signals, because SureCart's pages do not all
	 * look alike: the customer dashboard and checkout carry a page template whose
	 * slug names the vendor, while products are a post type of their own. Either
	 * is enough.
	 *
	 * @param string $template_slug The page's stored _wp_page_template value.
	 * @param string $post_type     The post type being rendered.
	 */
	public static function is_surecart( string $template_slug, string $post_type ): bool {
		if ( str_contains( strtolower( $template_slug ), 'surecart' ) ) {
			return true;
		}
		$type = strtolower( $post_type );
		// SureCart registers its post types under an sc_ prefix (sc_product and
		// friends). Prefix-matched rather than listed, so a type added by a later
		// SureCart release is covered without an update here.
		return str_starts_with( $type, 'sc_' ) || str_contains( $type, 'surecart' );
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
		/**
		 * Opt a request into, or out of, the Clubhouse chrome. Left as a filter
		 * because a club may run a plugin that renders its own complete document
		 * the way Clubhouse pages do, and that page needs a way to say so.
		 *
		 * @param bool $forced Whether to dress this request regardless.
		 */
		$forced = (bool) apply_filters( 'blueworx_clubhouse_dress_external_page', false );
		$slug   = function_exists( 'get_page_template_slug' ) ? get_page_template_slug() : '';
		return self::dresses(
			Blueworx_Clubhouse_Frontend::is_clubhouse_page(),
			self::is_dressable_view(),
			// Two ways to be a shop page. The template slug and post type below
			// are SureCart's own marks, and they are what a product or an order
			// page carries. Checkout and the thank-you page are also matched
			// directly, by the ids SureCart stores for them: this plugin serves
			// those two from its own template now (see Commerce_Pages), so they
			// already render a complete document, and dressing them injected the
			// club's header and footer around one — two footers on a payment
			// page. A page id is a fact; a template slug is a convention that a
			// club could clear from the editor without knowing what it did.
			self::is_surecart( is_string( $slug ) ? $slug : '', self::current_post_type() )
				|| self::is_commerce_page(),
			$forced
		);
	}

	/**
	 * Whether the page being rendered is one of the two this plugin dresses
	 * and serves its own document for — checkout, or the thank-you page.
	 */
	private static function is_commerce_page(): bool {
		if ( ! function_exists( 'get_queried_object_id' ) ) {
			return false;
		}
		return '' !== Blueworx_Clubhouse_Dashboard_Assets::page_key( (int) get_queried_object_id() );
	}

	/** The post type being rendered, or '' when the view has no single post. */
	private static function current_post_type(): string {
		if ( ! function_exists( 'get_post_type' ) ) {
			return '';
		}
		$type = get_post_type();
		return is_string( $type ) ? $type : '';
	}

	/**
	 * A front-end view that renders a page a human reads. Feeds, embeds and
	 * trackbacks are excluded because they are not HTML documents, and injecting
	 * a header into one corrupts it — inject() would decline anyway, but deciding
	 * here means the output buffer is never opened in the first place.
	 */
	private static function is_dressable_view(): bool {
		foreach ( array( 'is_feed', 'is_embed', 'is_trackback', 'is_robots' ) as $fn ) {
			if ( function_exists( $fn ) && $fn() ) {
				return false;
			}
		}
		foreach ( array( 'is_singular', 'is_404', 'is_archive', 'is_home', 'is_search' ) as $fn ) {
			if ( function_exists( $fn ) && $fn() ) {
				return true;
			}
		}
		return false;
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
		Blueworx_Clubhouse_Links::set_item_resolver( array( Blueworx_Clubhouse_Frontend::class, 'item_link_url' ) );
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
