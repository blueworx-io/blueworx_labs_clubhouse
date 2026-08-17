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
	 * Sanitise a raw filter param into a bare slug ([a-z0-9-]). Pure and testable;
	 * the filtered pages match this against their derived pill slugs. An unknown
	 * slug is harmless — the renderer falls back to "All".
	 */
	public static function sanitize_filter( mixed $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $raw ) ), '-' );
	}

	private static function current_filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter, no state change.
		$raw = $_GET[ Blueworx_Clubhouse_Links::FILTER_PARAM ] ?? '';
		return self::sanitize_filter( is_string( $raw ) ? wp_unslash( $raw ) : '' );
	}

	/**
	 * The sport or team named on a Sports/Teams URL, sanitised to the same slug
	 * shape the filter uses — it is matched against a slugified title, so anything
	 * that is not a slug cannot match and is safely reduced to one.
	 */
	public static function current_item(): string {
		// The rewrite fills the query var; the query form fills $_GET. Both reach
		// here so /sports/rugby/ and ?clubhouse_item=rugby serve the same page.
		$raw = function_exists( 'get_query_var' ) ? get_query_var( Blueworx_Clubhouse_Links::ITEM_PARAM ) : '';
		if ( ! is_string( $raw ) || '' === $raw ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector, no state change.
			$raw = $_GET[ Blueworx_Clubhouse_Links::ITEM_PARAM ] ?? '';
			$raw = is_string( $raw ) ? wp_unslash( $raw ) : '';
		}
		return self::sanitize_filter( $raw );
	}

	/** /sports/rugby/ when permalinks allow it, the query form when they do not. */
	public static function item_link_url( string $key, string $slug ): string {
		if ( '' !== (string) get_option( 'permalink_structure', '' ) ) {
			return home_url( '/' . $key . '/' . rawurlencode( $slug ) . '/' );
		}
		return home_url( '/?' . self::QUERY_VAR . '=' . $key . '&' . Blueworx_Clubhouse_Links::ITEM_PARAM . '=' . rawurlencode( $slug ) );
	}

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
		// A page whose integration is missing is not served at all — the rewrite
		// rule still exists (see Page_Map::pages()), so without this the URL would
		// render a page of shortcodes nothing can expand.
		if ( ! Blueworx_Clubhouse_Page_Map::is_available( $slug ) ) {
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
			'base_stylesheet_url' => $plugin_url . Blueworx_Clubhouse_Page_Renderer::BASE_STYLESHEET,
			'stylesheet_url'      => $plugin_url . $look->stylesheet(),
			'inline_css'          => $root_css,
			'reveal_url'          => $plugin_url . 'assets/js/reveal.js',
		);
	}

	public static function register(): void {
		// This is the environment that HAS do_shortcode, so this is where the seam
		// gets its real implementation. The preview and the unit tests leave it
		// unset and get escaped text instead — see Blueworx_Clubhouse_Shortcodes.
		//
		// Wrapped in a closure rather than passed as the string 'do_shortcode', so
		// the function is looked up when a shortcode is actually rendered rather
		// than when the plugin boots. register() runs before init, and a string
		// callable naming a not-yet-loaded function is not callable yet.
		Blueworx_Clubhouse_Shortcodes::set_expander(
			static fn( string $text ): string => (string) do_shortcode( $text )
		);
		// Likewise for integration detection: shortcode_exists() answers the question
		// that actually matters — is this shortcode registered right now — where a
		// file or class check would also pass for an installed-but-inactive plugin.
		Blueworx_Clubhouse_Integrations::set_detector(
			static fn( string $tag ): bool => (bool) shortcode_exists( $tag )
		);
		// The shop, when there is one. Both are installed together: a products
		// adapter with no checkout page can only produce half-connected tiers,
		// which the renderer deliberately refuses to show.
		//
		// The checkout URL is installed as a resolver, not a computed string:
		// register() runs on plugins_loaded, and checkout_url() reaches
		// get_permalink(), which needs $wp_rewrite — an object WordPress does
		// not create until after plugins_loaded. Resolving it here would fatal
		// on every request once the option is set (see Checkout::set_resolver()).
		if ( Blueworx_Clubhouse_SureCart_Products::is_active() ) {
			Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_SureCart_Products() );
			Blueworx_Clubhouse_Checkout::set_resolver(
				static fn(): string => Blueworx_Clubhouse_SureCart_Products::checkout_url()
			);
		}
		add_action( 'init', array( self::class, 'register_rewrites' ) );
		// Priority 11: after register_rewrites() above, so a flush writes the rules
		// this version actually declares.
		add_action( 'init', array( self::class, 'maybe_flush_rewrites' ), 11 );
		add_action( 'init', array( Blueworx_Clubhouse_Collection_Types::class, 'register' ) );
		// Priority 20, not the default 10: the look stylesheet has to print after
		// the active theme's. Our body rule is an element selector, so it ties on
		// specificity with a theme reset's `body { font: inherit }` or
		// `body { line-height: 1 }`, and a tie is settled by source order. Themes
		// enqueue at 10, so at 10 the winner is whichever happened to register
		// first — a coin flip we lost in production, dropping body copy to the
		// browser's default serif at line-height 1.
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ), 20 );
		add_action( 'wp_head', array( self::class, 'render_favicon' ) );
		add_filter( 'template_include', array( self::class, 'filter_template' ) );
		// The template used to print its own <title> and then call wp_head(), which
		// prints WordPress's — so every clubhouse page shipped two titles and the
		// consumer chose. Let WordPress own the tag and supply the text instead, so
		// exactly one is emitted. Theme support is declared here rather than assumed:
		// without it wp_head() prints no title at all, and dropping ours from the
		// template would have left the page with none.
		add_action( 'after_setup_theme', static function (): void {
			add_theme_support( 'title-tag' );
		} );
		add_filter( 'pre_get_document_title', array( self::class, 'filter_document_title' ) );
	}

	/**
	 * Our title text for a clubhouse page; anything else is left to WordPress and
	 * whatever SEO plugin the club runs.
	 */
	public static function filter_document_title( string $title ): string {
		return self::is_clubhouse_page() ? self::page_title() : $title;
	}

	/** Stores the plugin version whose rewrite rules are currently in the cache. */
	public const REWRITE_VERSION_OPTION = 'blueworx_clubhouse_rewrite_version';

	/**
	 * Whether the cached rewrite rules predate the running plugin.
	 *
	 * Pure, so the decision is unit-testable without a WordPress runtime.
	 *
	 * @param mixed $stored Whatever is in the option — absent, or any old junk.
	 */
	public static function rewrites_need_flush( string $running_version, mixed $stored ): bool {
		return ! is_string( $stored ) || $stored !== $running_version;
	}

	/**
	 * Flush rewrite rules once after an upgrade that added a page.
	 *
	 * The activation hook flushes, but activation does NOT re-run when a plugin is
	 * updated by uploading a new zip over a live one — which is how this plugin is
	 * deployed. So a release that adds a slug (v0.42.0 added /booking/) registered
	 * the rule while WordPress went on serving its cached rules, and the pretty
	 * permalink 404ed until someone re-saved permalinks by hand. Nothing in the
	 * plugin said so, which made it look like the new page had not shipped.
	 *
	 * Stamped with the version rather than a boolean flag, so every future release
	 * that changes the page map is covered without anyone remembering to do this.
	 * Runs after register_rewrites() on the same hook, so the rules being flushed
	 * into the cache are the current ones.
	 */
	public static function maybe_flush_rewrites(): void {
		$running = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? (string) BLUEWORX_LABS_CLUBHOUSE_VERSION : '';
		if ( '' === $running ) {
			return;
		}
		if ( ! self::rewrites_need_flush( $running, get_option( self::REWRITE_VERSION_OPTION, null ) ) ) {
			return;
		}
		flush_rewrite_rules();
		// Autoload off: read once per upgrade, never on a normal request.
		update_option( self::REWRITE_VERSION_OPTION, $running, false );
	}

	public static function register_rewrites(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
		add_rewrite_tag( '%' . Blueworx_Clubhouse_Links::ITEM_PARAM . '%', '([^&/]+)' );
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			if ( '' === $page['slug'] ) {
				continue;
			}
			// The item rule goes in first: 'top' prepends, so the LAST rule added
			// at the top is matched first, and /sports/rugby/ must not be eaten by
			// the bare /sports/ rule.
			add_rewrite_rule(
				'^' . $page['slug'] . '/?$',
				'index.php?' . self::QUERY_VAR . '=' . $page['slug'],
				'top'
			);
			if ( in_array( $page['slug'], array( 'sports', 'teams' ), true ) ) {
				add_rewrite_rule(
					'^' . $page['slug'] . '/([^/]+)/?$',
					'index.php?' . self::QUERY_VAR . '=' . $page['slug'] . '&' . Blueworx_Clubhouse_Links::ITEM_PARAM . '=$matches[1]',
					'top'
				);
			}
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
		return null !== self::current_slug() || self::is_article();
	}

	/**
	 * True when this request is a single WordPress post that the plugin should
	 * render as a club news article.
	 *
	 * Posts are taken over rather than left to the theme because the plugin is
	 * designed to run alongside a style-free theme: left alone, a match report
	 * would render as unstyled text with no header, no nav and no footer, on a
	 * site where every other page is dressed.
	 */
	public static function is_article(): bool {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( 'post' ) ) {
			return false;
		}
		// The news page's own visibility governs the articles too: a club that has
		// switched news off has said it does not want a news section, and leaving
		// the articles reachable and clubhouse-dressed would contradict that.
		return self::context()->visibility->is_page_visible( 'news' );
	}

	public static function filter_template( string $template ): string {
		if ( self::is_article() ) {
			return dirname( __DIR__, 2 ) . '/templates/clubhouse.php';
		}
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

	/** Public so External_Chrome dresses foreign pages from the same branding, look and content. */
	public static function context(): Blueworx_Clubhouse_Clubhouse_Context {
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
			self::composer()
		);
	}

	/** The Base Look slug this request will render (demo override or saved active). */
	public static function active_look_slug(): ?string {
		$look = self::context()->look;
		return null === $look ? null : $look->slug();
	}

	public static function enqueue_assets(): void {
		if ( ! self::is_clubhouse_page() ) {
			return;
		}
		if ( ! self::enqueue_look_styles() ) {
			return;
		}
		wp_enqueue_script(
			'clubhouse-reveal',
			BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/reveal.js',
			array(),
			BLUEWORX_LABS_CLUBHOUSE_VERSION,
			true
		);
		// Reveals the cookie notice, which ships hidden, and remembers its
		// dismissal — see assets/js/cookie-notice.js.
		wp_enqueue_script(
			'clubhouse-cookie-notice',
			BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/cookie-notice.js',
			array(),
			BLUEWORX_LABS_CLUBHOUSE_VERSION,
			true
		);
		// Reveals the "Copy link" button on a story's share row, which ships
		// hidden so it is never offered where it could not work — see
		// assets/js/share.js.
		wp_enqueue_script(
			'clubhouse-share',
			BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/share.js',
			array(),
			BLUEWORX_LABS_CLUBHOUSE_VERSION,
			true
		);
	}

	/**
	 * Enqueue the look's stylesheets, webfonts and derived :root variables —
	 * everything that makes a page look like this club, and nothing that
	 * animates or rearranges markup.
	 *
	 * Split out of enqueue_assets() so External_Chrome can dress a page another
	 * plugin owns in the site's type and colour without also loading the scroll
	 * reveal, which would hide that plugin's UI until it scrolled into view.
	 *
	 * @return bool False when there is no active look, so callers can bail too.
	 */
	public static function enqueue_look_styles(): bool {
		$ctx = self::context();
		if ( null === $ctx->look ) {
			return false;
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
		return true;
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
		$is_article = self::is_article();
		$slug       = self::current_slug();
		if ( ! $is_article && null === $slug ) {
			return '';
		}
		Blueworx_Clubhouse_Links::set_resolver( array( self::class, 'link_url' ) );
		Blueworx_Clubhouse_Links::set_item_resolver( array( self::class, 'item_link_url' ) );
		Blueworx_Clubhouse_Menu::set_provider(
			static fn(): Blueworx_Clubhouse_Menu => new Blueworx_Clubhouse_Menu( new Blueworx_Clubhouse_Options_Storage() )
		);
		Blueworx_Clubhouse_News::set_source( new Blueworx_Clubhouse_WP_Posts() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only paging, no state change.
		Blueworx_Clubhouse_News::set_page( $_GET[ Blueworx_Clubhouse_News::PAGE_PARAM ] ?? 1 );

		$ctx      = self::context();
		$logo_url = self::resolve_logo( $ctx->branding->get_logo() );
		// So a menu item pointing at a block resolves against the blocks this
		// club actually has, not the ones the plugin ships.
		Blueworx_Clubhouse_Link_Catalogue::set_composer( $ctx->composer );

		if ( $is_article ) {
			return Blueworx_Clubhouse_Page_Renderer::post( $ctx->branding, $ctx->visibility, $ctx->collections, $ctx->composer, $logo_url );
		}
		return Blueworx_Clubhouse_Page_Map::render( $slug, $ctx->branding, $ctx->visibility, $ctx->collections, $ctx->composer, $logo_url, self::current_filter(), self::current_item() );
	}

	/**
	 * The club's blocks and what each page is built from. Reads the same
	 * options everything else does, so a page composed in the admin screens is
	 * the page a visitor gets.
	 *
	 * A site that has never been composed is composed here, on the spot. The
	 * upgrade routine normally does it, but that runs on an admin request, and
	 * a club whose first visitor after an update arrives before its owner does
	 * must not be served a site with no pages on it.
	 */
	public static function composer(): Blueworx_Clubhouse_Page_Composer {
		$storage     = new Blueworx_Clubhouse_Options_Storage();
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );
		if ( ! $composition->is_configured() ) {
			Blueworx_Clubhouse_Blocks_Installer::install();
		}
		return new Blueworx_Clubhouse_Page_Composer(
			new Blueworx_Clubhouse_Block_Library( $storage ),
			$composition
		);
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

	/**
	 * Compose the document title for a clubhouse page. Every page used to print
	 * the club name alone, so a search result, a browser tab and a shared link
	 * were indistinguishable across the whole site. Pure so it can be tested
	 * without a WP runtime; the home page keeps the bare club name, since
	 * "Home — Club" reads worse than "Club" for the landing page.
	 */
	public static function document_title( string $club_name, string $page_label ): string {
		$club_name  = trim( $club_name );
		$page_label = trim( $page_label );
		if ( '' === $page_label || 'Home' === $page_label ) {
			return $club_name;
		}
		if ( '' === $club_name ) {
			return $page_label;
		}
		return $page_label . ' — ' . $club_name;
	}

	/** The document title for the page this request renders. */
	public static function page_title(): string {
		if ( self::is_article() ) {
			// The headline, not "News" — an article's title is the one thing that
			// distinguishes it in a tab, a search result and a shared link.
			return self::document_title( self::club_name(), (string) get_the_title() );
		}
		$slug  = self::current_slug();
		$label = null === $slug ? '' : Blueworx_Clubhouse_Page_Map::label( $slug );
		// A sport or team page is titled after the section, not after the listing
		// it hangs off. Without this every one of them read "Sports" in the tab, in
		// a search result and in a shared link — indistinguishable from each other.
		$item = self::current_item_title( $slug );
		return self::document_title( self::club_name(), '' !== $item ? $item : $label );
	}

	/**
	 * The name of the sport or team this request is showing, or '' when the
	 * request is a listing (or names something that no longer exists, in which
	 * case the listing is what actually renders).
	 */
	public static function current_item_title( ?string $slug ): string {
		$item = self::current_item();
		if ( '' === $item || ! in_array( $slug, array( 'sports', 'teams' ), true ) ) {
			return '';
		}
		$collections = self::context()->collections;
		$rows        = 'sports' === $slug ? $collections->sports() : $collections->teams();
		$row         = Blueworx_Clubhouse_Page_Renderer::find_by_slug( $rows, $item );
		return null === $row ? '' : (string) $row['title'];
	}
}
