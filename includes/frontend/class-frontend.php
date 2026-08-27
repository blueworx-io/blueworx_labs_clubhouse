<?php
// includes/frontend/class-frontend.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's only WordPress-coupled class: template selection and asset
 * enqueue. All HTML is delegated to Page_Map / Page_Renderer. Pure decision
 * helpers (resolve_slug, enqueue_specs) are unit-tested without a WP runtime;
 * the hook wiring is verified with the WP-function shim.
 *
 * Club pages are found the way WordPress finds any page — by the post behind
 * them. The plugin declares no rewrite rules of its own; Legacy_Urls forwards
 * the addresses the old ones used to answer.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Frontend {

	/**
	 * The query param older versions routed on. Nothing serves from it now —
	 * Legacy_Urls redirects it to the page's own address — but it is still the
	 * name a bookmark or an old link can carry, and the DB-free preview's own
	 * router reads the same name.
	 */
	public const QUERY_VAR = 'clubhouse_page';

	/** The slug the member area is served at. */
	public const MEMBER_AREA = 'member-dashboard';

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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector, no state change.
		$raw = $_GET[ Blueworx_Clubhouse_Links::ITEM_PARAM ] ?? '';
		return self::sanitize_filter( is_string( $raw ) ? wp_unslash( $raw ) : '' );
	}

	/**
	 * One sport or team, hung off its listing page's own address.
	 *
	 * Built on the listing page's own URL — whatever form that takes — with the
	 * item as a query argument. There is no page in the database for a single
	 * sport or team, so nothing can find '/sports/rugby/' now that the plugin
	 * declares no rewrite rules; Legacy_Urls redirects that old address here.
	 */
	public static function item_link_url( string $key, string $slug ): string {
		$base = self::link_url( $key );
		$sep  = str_contains( $base, '?' ) ? '&' : '?';
		return $base . $sep . Blueworx_Clubhouse_Links::ITEM_PARAM . '=' . rawurlencode( $slug );
	}


	/**
	 * Which club page a request resolves to, or null for one this site does not
	 * serve. Pure.
	 *
	 * @param bool  $is_front_page Whether this is the site root, which is Home.
	 * @param mixed $named         The Page_Map slug this request named, if any.
	 */
	public static function resolve_slug( bool $is_front_page, mixed $named, ?Blueworx_Clubhouse_Visibility $visibility = null ): ?string {
		$slug = null;
		if ( is_string( $named ) && '' !== $named && Blueworx_Clubhouse_Page_Map::has( $named ) ) {
			$slug = $named;
		} elseif ( $is_front_page ) {
			$slug = '';
		}
		if ( null === $slug ) {
			return null;
		}
		// A page whose integration is missing is not served at all. Its real page
		// stays in the database either way (see Page_Map::pages()), so without
		// this the URL would render a page of shortcodes nothing can expand.
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
		// Answers SureCart's own seeder filter regardless of is_active(): the
		// seeder runs on activation, before a store has necessarily connected,
		// and the filter is a no-op on a site with no shop.
		Blueworx_Clubhouse_Checkout_Form::register();
		add_action( 'init', array( self::class, 'maybe_upgrade' ) );
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
		// Before template_include, so a page we decline to serve is marked 404
		// while WordPress can still pick the theme's 404 template for it.
		add_action( 'template_redirect', array( self::class, 'maybe_404' ) );
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

	/**
	 * Stores the plugin version this site has already been brought up to. The
	 * option name still says "rewrite" because that is what it started life
	 * doing and renaming it would strand the stamp on every existing site.
	 */
	public const UPGRADE_VERSION_OPTION = 'blueworx_clubhouse_rewrite_version';

	/**
	 * Whether this site was last brought up to date by an older plugin version.
	 *
	 * Pure, so the decision is unit-testable without a WordPress runtime.
	 *
	 * @param mixed $stored Whatever is in the option — absent, or any old junk.
	 */
	public static function needs_upgrade( string $running_version, mixed $stored ): bool {
		return ! is_string( $stored ) || $stored !== $running_version;
	}

	/**
	 * Bring a site up to date once per release.
	 *
	 * The activation hook does this too, but activation does NOT re-run when a
	 * plugin is updated by uploading a new zip over a live one — which is how
	 * this plugin is deployed. So a release that adds a page had no way to
	 * create it, and the new address 404ed until someone acted by hand. Nothing
	 * in the plugin said so, which made it look like the page had not shipped.
	 *
	 * Stamped with the version rather than a boolean flag, so every future
	 * release is covered without anyone remembering to do this. Costs one
	 * option read on a normal request and nothing else.
	 *
	 * The flush is the last thing this plugin has to do with rewrite rules: it
	 * declares none of its own now, and this is what clears the ones earlier
	 * versions left in WordPress's cache. Without it those stale rules would go
	 * on answering for every club page — routing them past the real pages —
	 * until some unrelated permalink save cleared them.
	 */
	public static function maybe_upgrade(): void {
		$running = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? (string) BLUEWORX_LABS_CLUBHOUSE_VERSION : '';
		if ( '' === $running || ! self::needs_upgrade( $running, get_option( self::UPGRADE_VERSION_OPTION, null ) ) ) {
			return;
		}
		Blueworx_Clubhouse_Club_Pages::ensure();
		flush_rewrite_rules();
		self::drop_block_data();
		// Autoload off: read once per upgrade, never on a normal request.
		update_option( self::UPGRADE_VERSION_OPTION, $running, false );
	}

	/**
	 * Remove what the withdrawn block builder left behind.
	 *
	 * Pages are built from the club's content again, so these two options are
	 * read by nothing. They are autoloaded, so leaving them would cost every
	 * page load on every site for the sake of data no screen can reach. Nothing
	 * a club wrote lives only here: the content store is where the words are,
	 * and it is untouched.
	 */
	private static function drop_block_data(): void {
		delete_option( 'clubhouse_blocks' );
		delete_option( 'clubhouse_page_composition' );
	}

	/**
	 * The club page this request resolved to, as its Page_Map slug, or '' when
	 * the request is not one. Home's own answer is '' too — callers reach it
	 * through the front-page branch in current_slug() instead, so the two are
	 * never confused.
	 *
	 * A club page is found the way WordPress finds any page: by the post behind
	 * it (Club_Pages::ensure()). Shared by current_slug() and maybe_404(), so a
	 * page is found and refused by the same reading of the request.
	 */
	private static function queried_club_slug(): string {
		if ( ! function_exists( 'get_queried_object_id' ) ) {
			return '';
		}
		$post_id = (int) get_queried_object_id();
		return Blueworx_Clubhouse_Club_Pages::is_club_page( $post_id )
			? Blueworx_Clubhouse_Club_Pages::slug_for( $post_id )
			: '';
	}

	/**
	 * Which club page this request renders, or null for one we do not serve.
	 *
	 * Home is asked for twice over. Its own page answers first, so a club that
	 * has chosen a different front page still reaches Home at its own address;
	 * is_front_page() is the fallback, which covers the site root before
	 * ensure_home_is_front_page() has run and a site left on posts-on-front.
	 *
	 * The visibility check in resolve_slug() applies either way, so a page that
	 * is switched off refuses to render however it was reached.
	 */
	private static function current_slug(): ?string {
		$visibility = self::context()->visibility;
		$post_id    = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( Blueworx_Clubhouse_Club_Pages::is_club_page( $post_id ) ) {
			return self::resolve_slug( true, Blueworx_Clubhouse_Club_Pages::slug_for( $post_id ), $visibility );
		}
		$is_front = function_exists( 'is_front_page' ) ? is_front_page() : false;
		return $is_front ? self::resolve_slug( true, '', $visibility ) : null;
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

	/** The clubhouse slug this request resolves to, or null. Public so the member area's routing can ask. */
	public static function current_page_slug(): ?string {
		return self::current_slug();
	}

	/**
	 * Which design system this request gets: the member area's BlueWorx admin
	 * stylesheet, the club's own look, or nothing at all.
	 *
	 * A single answer rather than two independent checks, because the two must
	 * never both be true — the member area's design and the club's look define
	 * the same variables and would fight over the page.
	 *
	 * Pure, so the choice is testable without a WordPress runtime.
	 */
	public static function style_family( ?string $slug, bool $is_article ): string {
		if ( self::MEMBER_AREA === $slug ) {
			return 'member';
		}
		if ( null !== $slug || $is_article ) {
			return 'look';
		}
		return 'none';
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

	/**
	 * Whether this URL is one of ours that we are declining to serve.
	 *
	 * A page an owner switches off becomes a draft (Club_Pages::status_for()),
	 * so WordPress answers 404 for it without being asked. This is what covers
	 * the cases WordPress cannot see: a page whose integration is not installed
	 * — real, published, and unrenderable — and a page whose status has drifted
	 * from the visibility flag that governs it. Declining to render is not the
	 * same as saying the page is not there, and a search engine reads the
	 * status (Issue #211).
	 *
	 * Only a URL that named one of our pages counts. The bare site root is left
	 * alone even when Home is switched off: that address belongs to WordPress as
	 * much as to us, and 404ing it would take the whole site down rather than
	 * one page off it.
	 */
	public static function is_gone( mixed $named, ?Blueworx_Clubhouse_Visibility $visibility ): bool {
		if ( ! is_string( $named ) || '' === $named || ! Blueworx_Clubhouse_Page_Map::has( $named ) ) {
			return false;
		}
		return null === self::resolve_slug( false, $named, $visibility );
	}

	/**
	 * Answer 404 for a page this site does not serve, so the URL behaves like it
	 * does not exist — for a visitor and for a search engine.
	 */
	public static function maybe_404(): void {
		if ( ! self::is_gone( self::queried_club_slug(), self::context()->visibility ) ) {
			return;
		}
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Serve the plugin's own document for a club page or a news article, and
	 * leave everything else to the theme.
	 *
	 * Keyed off current_slug(), which is null for a page we decline to serve —
	 * so a switched-off page gets the theme's own 404 rather than our document
	 * with an empty body. Deliberately not keyed off is_404() as well:
	 * WordPress answers 404 for reasons of its own that leave the page
	 * perfectly renderable — a page number that does not exist, for one — and
	 * handing those back to the theme took the login form off the screen for
	 * anyone who mistyped their password.
	 *
	 * @param string $template Whatever WordPress had chosen.
	 */
	public static function filter_template( string $template ): string {
		if ( self::is_article() ) {
			return self::template_path();
		}
		return null === self::current_slug() ? $template : self::template_path();
	}

	/** Where this plugin's page document lives. */
	private static function template_path(): string {
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
			new Blueworx_Clubhouse_Content_Store( $storage )
		);
	}

	/** The Base Look slug this request will render (demo override or saved active). */
	public static function active_look_slug(): ?string {
		$look = self::context()->look;
		return null === $look ? null : $look->slug();
	}

	public static function enqueue_assets(): void {
		$family = self::style_family( self::current_slug(), self::is_article() );
		if ( 'none' === $family ) {
			return;
		}
		if ( 'member' === $family ) {
			// A BlueWorx admin screen, so it gets that design system and none of
			// the club's. No scroll reveal either: it ships elements hidden until
			// they scroll into view, which on a page of the shop's own web
			// components would hide a member's orders behind an animation.
			Blueworx_Clubhouse_Dashboard_Assets::enqueue();
			Blueworx_Clubhouse_Member_Dashboard::enqueue_shop_assets();
			// Switching panels without a reload. Deferred and enhancement-only:
			// the nav is real links, so the page works while this is still on
			// its way and if it never arrives at all.
			wp_enqueue_script(
				'clubhouse-member-area',
				BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/member-area.js',
				array(),
				BLUEWORX_LABS_CLUBHOUSE_VERSION,
				true
			);
			return;
		}
		if ( ! self::enqueue_look_styles() ) {
			return;
		}
		if ( 'login' === self::current_slug() ) {
			// The sign-in form on this page is one of the shop's web components,
			// and the script that brings them to life is declared by the shop's
			// own dashboard block — which this page is not. Without this the form
			// renders as inert markup and nobody can sign in.
			Blueworx_Clubhouse_Member_Dashboard::enqueue_shop_assets();
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
		Blueworx_Clubhouse_Menu::set_provider(
			static fn(): Blueworx_Clubhouse_Menu => new Blueworx_Clubhouse_Menu( new Blueworx_Clubhouse_Options_Storage() )
		);
		Blueworx_Clubhouse_News::set_source( new Blueworx_Clubhouse_WP_Posts() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only paging, no state change.
		Blueworx_Clubhouse_News::set_page( $_GET[ Blueworx_Clubhouse_News::PAGE_PARAM ] ?? 1 );

		$ctx      = self::context();
		$logo_url = self::resolve_logo( $ctx->branding->get_logo() );

		if ( $is_article ) {
			return Blueworx_Clubhouse_Page_Renderer::post( $ctx->branding, $ctx->visibility, $ctx->collections, $logo_url, $ctx->content );
		}
		return Blueworx_Clubhouse_Page_Map::render( $slug, $ctx->branding, $ctx->visibility, $ctx->collections, $logo_url, $ctx->content, self::current_filter(), self::current_item() );
	}

	/**
	 * Resolve an internal page key ('home', 'about', …) to a real WordPress URL.
	 * Installed as the Links resolver during front-end rendering so the shared
	 * renderer emits permalinks (/about/) instead of the preview's ?page= form.
	 */
	public static function link_url( string $key ): string {
		$slug = 'home' === $key ? '' : $key;

		// The page's own address, which is right on any permalink structure and
		// stays right if the page is moved. Below is the answer for a site whose
		// pages are not there yet — a window that closes the first time
		// maybe_upgrade() or the activation hook runs.
		$permalink = self::page_permalink( $slug );
		if ( '' !== $permalink ) {
			return $permalink;
		}
		return '' === $slug ? home_url( '/' ) : home_url( '/' . $slug . '/' );
	}

	/**
	 * The WordPress permalink of the real page behind a club page, or '' when
	 * there is not one to ask for — no page recorded yet, or an id naming a
	 * page that has since been deleted. '' means "fall back", never a URL: a
	 * link built on get_permalink()'s false would silently be the site root.
	 */
	private static function page_permalink( string $slug ): string {
		if ( ! class_exists( 'Blueworx_Clubhouse_Club_Pages' ) || ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		$post_id = Blueworx_Clubhouse_Club_Pages::post_id( $slug );
		if ( $post_id <= 0 ) {
			return '';
		}
		$url = get_permalink( $post_id );
		return is_string( $url ) ? $url : '';
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
