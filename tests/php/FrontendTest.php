<?php
// tests/php/FrontendTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class FrontendTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	protected function tearDown(): void {
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
		// register() installs the live shortcode expander, which is global state.
		// Left installed it would make every later test execute shortcode text —
		// against a do_shortcode() that does not exist in this harness — and which
		// tests broke would depend on the order they happened to run in.
		Blueworx_Clubhouse_Shortcodes::set_expander( null );
		// Same reason: register() also installs the integration detector, and
		// Page_Map::is_available() consults it on effectively every render. Left
		// installed it decides which pages exist for every later test.
		Blueworx_Clubhouse_Integrations::set_detector( null );
		// render_body() installs a Menu provider backed by real options — global
		// state that would otherwise leak into every test that runs afterwards in
		// this same PHPUnit process, including PreviewRenderTest, whose entire
		// premise is "no provider installed, so Menu::current() is DEFAULTS".
		Blueworx_Clubhouse_Menu::set_provider( null );
		// register() also installs the shop, when SureCart looks active —
		// likewise global state that must not leak into later tests.
		Blueworx_Clubhouse_Products_Source::set( null );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
	}

	/**
	 * The critical bug this guards: Frontend::register() once called
	 * SureCart_Products::checkout_url() directly at install time, which
	 * reaches get_permalink() — needing $wp_rewrite, an object WordPress does
	 * not create until after plugins_loaded, exactly when register() runs. A
	 * resolver must be installed instead, and left uncalled until a checkout
	 * link is actually built. Re-introducing an eager
	 * `set_base_url( checkout_url() )` in register() would call get_permalink()
	 * immediately and fail this.
	 */
	public function test_register_does_not_resolve_a_checkout_url_eagerly(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		update_option( 'surecart_checkout_page_id', 42 );

		Blueworx_Clubhouse_Frontend::register();

		$this->assertSame(
			array(),
			wp_stub_calls( 'get_permalink' ),
			'register() must not resolve the checkout URL eagerly'
		);
	}

	/**
	 * Upgrading by uploading a zip over a live plugin does not re-run activation,
	 * so a release that adds a page needs its own way to create it — otherwise
	 * the new address 404s with nothing to say why. This is how v0.42.0's
	 * /booking/ shipped broken.
	 */
	public function test_the_upgrade_runs_once_per_version_then_stays_quiet(): void {
		$this->assertTrue(
			Blueworx_Clubhouse_Frontend::needs_upgrade( '0.42.1', null ),
			'never stamped — a fresh install or an upgrade from before the stamp existed'
		);
		$this->assertTrue(
			Blueworx_Clubhouse_Frontend::needs_upgrade( '0.42.1', '0.42.0' ),
			'stamp predates the running version'
		);
		$this->assertFalse(
			Blueworx_Clubhouse_Frontend::needs_upgrade( '0.42.1', '0.42.1' ),
			'already run for this version — must not run on every request'
		);
		$this->assertTrue(
			Blueworx_Clubhouse_Frontend::needs_upgrade( '0.42.1', array( 'junk' ) ),
			'a non-string option is not a usable stamp'
		);
	}

	/**
	 * Issue #243. An existing site has this plugin's old rules sitting in
	 * WordPress's cache, and a cached rule outranks WordPress's own page rule —
	 * so until they are cleared, every club page keeps routing past the real
	 * page behind it. Nothing else clears them: a zip dropped over a live
	 * plugin does not re-run activation.
	 */
	public function test_the_upgrade_clears_the_rules_older_versions_cached(): void {
		Blueworx_Clubhouse_Frontend::maybe_upgrade();
		$this->assertCount( 1, wp_stub_calls( 'flush_rewrite_rules' ), 'the stale rules are cleared once' );

		Blueworx_Clubhouse_Frontend::maybe_upgrade();
		$this->assertCount( 1, wp_stub_calls( 'flush_rewrite_rules' ), 'and not again on every request after' );
	}

	public function test_link_url_home_is_site_root(): void {
		$this->assertSame( 'https://club.test/', Blueworx_Clubhouse_Frontend::link_url( 'home' ) );
	}

	public function test_link_url_pretty_permalinks_use_slug_path(): void {
		update_option( 'permalink_structure', '/%postname%/' );
		$this->assertSame( 'https://club.test/about/', Blueworx_Clubhouse_Frontend::link_url( 'about' ) );
	}

	public function test_link_url_names_the_page_even_before_it_exists(): void {
		// A site part-way through the upgrade has no page to ask for a permalink,
		// and the plugin no longer has a query var to fall back on. The address
		// the page is about to take is the only honest answer.
		update_option( 'permalink_structure', '' );
		$this->assertSame( 'https://club.test/about/', Blueworx_Clubhouse_Frontend::link_url( 'about' ) );
	}

	public function test_link_url_asks_wordpress_for_the_pages_permalink(): void {
		// The page's own address, whatever the club's permalink structure is
		// and wherever the page has been moved to. Building '/about/' by hand
		// is what this replaces.
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 53 );
		$GLOBALS['wp_stub_permalinks'][53] = 'https://club.test/club/about-us/';
		$this->assertSame( 'https://club.test/club/about-us/', Blueworx_Clubhouse_Frontend::link_url( 'about' ) );
	}

	public function test_link_url_home_asks_for_the_home_pages_permalink(): void {
		// Home's slug is '', and its id is stored under its own option name.
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 7 );
		$GLOBALS['wp_stub_permalinks'][7] = 'https://club.test/';
		$this->assertSame( 'https://club.test/', Blueworx_Clubhouse_Frontend::link_url( 'home' ) );
	}

	public function test_link_url_falls_back_when_the_page_is_not_there_yet(): void {
		// A site part-way through the upgrade has the flag but no page. The old
		// construction still answers, so no link goes dead in the gap.
		update_option( 'permalink_structure', '/%postname%/' );
		$this->assertSame( 'https://club.test/about/', Blueworx_Clubhouse_Frontend::link_url( 'about' ) );
	}

	public function test_link_url_falls_back_when_the_permalink_cannot_be_built(): void {
		// A stored id naming a page that has since been deleted: get_permalink()
		// answers false, and a link built on false would be the site root.
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 999 );
		$this->assertSame( 'https://club.test/about/', Blueworx_Clubhouse_Frontend::link_url( 'about' ) );
	}

	public function test_item_link_url_is_built_on_the_listing_pages_permalink(): void {
		// A sport hangs off the Sports page wherever that page lives, rather
		// than assuming it sits at the site root.
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'sports' ), 61 );
		$GLOBALS['wp_stub_permalinks'][61] = 'https://club.test/club/sports/';
		$this->assertSame(
			'https://club.test/club/sports/?clubhouse_item=rugby',
			Blueworx_Clubhouse_Frontend::item_link_url( 'sports', 'rugby' )
		);
	}

	public function test_item_link_url_escapes_the_item_slug(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'sports' ), 61 );
		$GLOBALS['wp_stub_permalinks'][61] = 'https://club.test/sports/';
		$this->assertSame(
			'https://club.test/sports/?clubhouse_item=under%2013s',
			Blueworx_Clubhouse_Frontend::item_link_url( 'sports', 'under 13s' )
		);
	}

	public function test_item_link_url_falls_back_without_a_page(): void {
		update_option( 'permalink_structure', '/%postname%/' );
		$this->assertSame(
			'https://club.test/sports/?clubhouse_item=rugby',
			Blueworx_Clubhouse_Frontend::item_link_url( 'sports', 'rugby' )
		);
	}

	public function test_item_link_url_uses_a_query_arg_when_the_permalink_has_one(): void {
		// Plain permalinks: the listing page's own address is '?page_id=61', so
		// hanging '/rugby/' off the end of it would build nonsense.
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'sports' ), 61 );
		$GLOBALS['wp_stub_permalinks'][61] = 'https://club.test/?page_id=61';
		$this->assertSame(
			'https://club.test/?page_id=61&clubhouse_item=rugby',
			Blueworx_Clubhouse_Frontend::item_link_url( 'sports', 'rugby' )
		);
	}

	public function test_register_registers_expected_hooks(): void {
		Blueworx_Clubhouse_Frontend::register();

		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$filters = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_filter' ) );

		$this->assertContains( 'init', $actions );
		$this->assertContains( 'wp_enqueue_scripts', $actions );
		$this->assertContains( 'template_include', $filters );
	}

	/**
	 * The look stylesheet must print AFTER the active theme's stylesheet.
	 *
	 * Our body rule (font-family/font-size/line-height) is an element selector,
	 * so it ties on specificity with a theme reset's own `body { font: inherit }`
	 * or `body { line-height: 1 }`. A tie is broken by source order, so whichever
	 * sheet is enqueued later wins. Themes register their stylesheet on
	 * wp_enqueue_scripts at the default priority 10; at 10 we are a coin-flip on
	 * registration order, and on the live site we lost — body fell back to the
	 * browser's default serif at line-height 1.
	 *
	 * Running later than 10 makes us deterministically last.
	 */
	public function test_assets_enqueue_after_theme_stylesheet(): void {
		Blueworx_Clubhouse_Frontend::register();

		$enqueue = array_values(
			array_filter(
				wp_stub_calls( 'add_action' ),
				static fn( $c ) => 'wp_enqueue_scripts' === $c['args'][0]
			)
		);

		$this->assertCount( 1, $enqueue, 'expected exactly one wp_enqueue_scripts registration' );
		$this->assertArrayHasKey( 2, $enqueue[0]['args'], 'enqueue_assets must declare an explicit priority' );
		$this->assertGreaterThan(
			10,
			$enqueue[0]['args'][2],
			'look CSS must be enqueued after the default priority so it outranks the theme stylesheet'
		);
	}

	/**
	 * Issue #243. Club pages are real WordPress pages, found the way WordPress
	 * finds any page, so the plugin declares no rules and no query var of its
	 * own. A rule registered here would sit above WordPress's own page rule and
	 * take every club page back off its real page.
	 */
	public function test_the_plugin_registers_no_rewrite_rules_of_its_own(): void {
		Blueworx_Clubhouse_Frontend::register();
		Blueworx_Clubhouse_Frontend::maybe_upgrade();

		$this->assertSame( array(), wp_stub_calls( 'add_rewrite_rule' ) );
		$this->assertSame( array(), wp_stub_calls( 'add_rewrite_tag' ) );
		$this->assertFalse(
			method_exists( Blueworx_Clubhouse_Frontend::class, 'register_rewrites' ),
			'nothing may bring the rules back by calling this directly'
		);
	}

	/**
	 * The pages are created on init, not on a hook that only fires when a plugin
	 * is activated: uploading a new zip over a live plugin does not re-run
	 * activation, which is how this plugin is deployed.
	 */
	public function test_register_hooks_the_upgrade_on_init(): void {
		Blueworx_Clubhouse_Frontend::register();
		$init = array_values( array_filter(
			wp_stub_calls( 'add_action' ),
			static fn( array $c ): bool => 'init' === $c['args'][0]
		) );
		$targets = array_map(
			static fn( array $c ) => is_array( $c['args'][1] ) ? $c['args'][1][1] : $c['args'][1],
			$init
		);
		$this->assertContains( 'maybe_upgrade', $targets );
	}

	public function test_resolve_slug_front_page_is_home(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::resolve_slug( true, null ) );
	}

	public function test_resolve_slug_query_var_wins_over_front_page(): void {
		// Posts-on-front installs report is_front_page() true even for /about/;
		// a present, known query var must win so sub-pages don't render Home.
		$this->assertSame( 'about', Blueworx_Clubhouse_Frontend::resolve_slug( true, 'about' ) );
	}

	public function test_resolve_slug_front_page_without_query_var_is_home(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::resolve_slug( true, '' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::resolve_slug( true, null ) );
	}

	public function test_resolve_slug_known_query_var(): void {
		$this->assertSame( 'about', Blueworx_Clubhouse_Frontend::resolve_slug( false, 'about' ) );
	}

	public function test_resolve_slug_unknown_is_null(): void {
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( false, 'nope' ) );
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( false, '' ) );
	}

	public function test_enqueue_specs_shape(): void {
		$look  = new Blueworx_Clubhouse_Court_Side();
		$specs = Blueworx_Clubhouse_Frontend::enqueue_specs( $look, ':root{--x:1}', 'https://club.test/wp-content/plugins/clubhouse/' );

		$this->assertStringContainsString( '@font-face', $specs['font_face_css'] );
		$this->assertStringContainsString(
			"src:url(https://club.test/wp-content/plugins/clubhouse/assets/fonts/syne-700.woff2) format('woff2')",
			$specs['font_face_css']
		);
		$this->assertStringNotContainsString( 'googleapis', $specs['font_face_css'] );
		$this->assertSame( 'https://club.test/wp-content/plugins/clubhouse/assets/looks/court-side.css', $specs['stylesheet_url'] );
		$this->assertSame( ':root{--x:1}', $specs['inline_css'] );
		$this->assertSame( 'https://club.test/wp-content/plugins/clubhouse/assets/js/reveal.js', $specs['reveal_url'] );
	}

	public function test_no_google_font_origins_are_referenced(): void {
		$look  = new Blueworx_Clubhouse_Court_Side();
		$specs = Blueworx_Clubhouse_Frontend::enqueue_specs( $look, ':root{}', 'https://club.test/wp/' );
		$this->assertStringNotContainsString( 'gstatic', $specs['font_face_css'] );
		$this->assertFalse( method_exists( Blueworx_Clubhouse_Frontend::class, 'resource_hints' ) );
	}

	public function test_enqueue_specs_includes_the_base_stylesheet_before_the_look(): void {
		$look  = new Blueworx_Clubhouse_Court_Side();
		$specs = Blueworx_Clubhouse_Frontend::enqueue_specs( $look, ':root{}', 'https://club.test/plugins/clubhouse/' );

		$this->assertSame(
			'https://club.test/plugins/clubhouse/assets/looks/base.css',
			$specs['base_stylesheet_url']
		);
		// The look stylesheet is still resolved separately — base does not replace it.
		$this->assertSame(
			'https://club.test/plugins/clubhouse/assets/looks/court-side.css',
			$specs['stylesheet_url']
		);
	}

	public function test_base_stylesheet_is_the_same_for_every_look(): void {
		$urls = array();
		foreach ( array( new Blueworx_Clubhouse_Court_Side(), new Blueworx_Clubhouse_Floodlight(), new Blueworx_Clubhouse_Members_House() ) as $look ) {
			$specs  = Blueworx_Clubhouse_Frontend::enqueue_specs( $look, ':root{}', 'https://club.test/' );
			$urls[] = $specs['base_stylesheet_url'];
		}
		// Base is look-independent by design: a look cannot substitute its own.
		$this->assertSame( array( 'https://club.test/assets/looks/base.css' ), array_values( array_unique( $urls ) ) );
	}

	public function test_club_name_reads_branding_through_context(): void {
		wp_stub_reset();
		update_option( 'clubhouse_branding', array( 'club_name' => 'Riverside RFC' ) );
		$this->assertSame( 'Riverside RFC', Blueworx_Clubhouse_Frontend::club_name() );
	}

	public function test_resolve_slug_hidden_page_is_null(): void {
		$vis = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$vis->set_page_visible( 'about', false );
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( false, 'about', $vis ) );
	}

	public function test_resolve_slug_visible_page_still_resolves(): void {
		$vis = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertSame( 'about', Blueworx_Clubhouse_Frontend::resolve_slug( false, 'about', $vis ) );
	}

	public function test_resolve_slug_hidden_home_is_null(): void {
		$vis = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$vis->set_page_visible( 'home', false );
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( true, null, $vis ) );
	}

	public function test_resolve_slug_without_visibility_unchanged(): void {
		$this->assertSame( 'about', Blueworx_Clubhouse_Frontend::resolve_slug( false, 'about' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::resolve_slug( true, null ) );
	}

	public function test_active_look_slug_reflects_demo_override_when_on(): void {
		wp_stub_reset();
		( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->set( true );
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'floodlight';
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Frontend::active_look_slug() );
	}

	public function test_active_look_slug_is_saved_look_without_demo(): void {
		wp_stub_reset();
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
		// No saved look → registry falls back to first registered (Court Side).
		$this->assertSame( 'court-side', Blueworx_Clubhouse_Frontend::active_look_slug() );
	}

	public function test_resolve_logo_turns_an_attachment_id_into_a_url(): void {
		$this->assertSame( 'https://club.test/wp-content/uploads/att-9.png', Blueworx_Clubhouse_Frontend::resolve_logo( '9' ) );
	}

	public function test_resolve_logo_passes_through_a_stored_url_and_empty(): void {
		$this->assertSame( 'https://cdn.example/logo.svg', Blueworx_Clubhouse_Frontend::resolve_logo( 'https://cdn.example/logo.svg' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::resolve_logo( '' ) );
	}

	public function test_favicon_link_html_emits_link_when_set_and_nothing_when_empty(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::favicon_link_html( '' ) );
		$html = Blueworx_Clubhouse_Frontend::favicon_link_html( 'https://club.test/favicon.png' );
		$this->assertStringContainsString( '<link rel="icon"', $html );
		$this->assertStringContainsString( 'href="https://club.test/favicon.png"', $html );
	}

	public function test_render_favicon_emits_nothing_until_one_is_set(): void {
		wp_stub_off_clubhouse_page();
		ob_start();
		Blueworx_Clubhouse_Frontend::render_favicon();
		$this->assertSame( '', (string) ob_get_clean(), 'no favicon link before the owner sets one' );
	}

	/**
	 * The favicon identifies the whole site, so it must render on every front-end
	 * page — including native blog posts, which are NOT clubhouse pages. Guards the
	 * removal of the old clubhouse-page gate.
	 */
	public function test_render_favicon_emits_site_wide_once_set_even_off_a_clubhouse_page(): void {
		wp_stub_off_clubhouse_page();
		( new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Options_Storage() ) )->set_favicon( '42' );
		ob_start();
		Blueworx_Clubhouse_Frontend::render_favicon();
		$out = (string) ob_get_clean();
		$this->assertStringContainsString( '<link rel="icon"', $out );
		$this->assertStringContainsString( 'att-42', $out, 'resolves the attachment id to its URL' );
	}

	public function test_favicon_link_html_escapes_the_url(): void {
		$html = Blueworx_Clubhouse_Frontend::favicon_link_html( 'https://club.test/f.png?a=b&c="x"' );
		$this->assertStringContainsString( '&amp;', $html );
		$this->assertStringNotContainsString( '="x"', $html );
	}

	public function test_sanitize_filter_reduces_to_a_bare_slug(): void {
		$this->assertSame( 'rugby', Blueworx_Clubhouse_Frontend::sanitize_filter( 'rugby' ) );
		$this->assertSame( 'rugby', Blueworx_Clubhouse_Frontend::sanitize_filter( 'Rugby' ) );
		$this->assertSame( 'open-day', Blueworx_Clubhouse_Frontend::sanitize_filter( 'Open day' ) );
		$this->assertSame( 'rug-by', Blueworx_Clubhouse_Frontend::sanitize_filter( 'rug!by/' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::sanitize_filter( '' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::sanitize_filter( array( 'x' ) ) ); // non-string
	}

	/**
	 * Every clubhouse page printed the club name alone, so a browser tab, a search
	 * result and a shared link were identical across the whole site.
	 */
	public function test_document_title_names_the_page_then_the_club(): void {
		$this->assertSame(
			'Membership — Crewe Vagrants Squash',
			Blueworx_Clubhouse_Frontend::document_title( 'Crewe Vagrants Squash', 'Membership' )
		);
		$this->assertSame(
			'Teams — Crewe Vagrants Squash',
			Blueworx_Clubhouse_Frontend::document_title( 'Crewe Vagrants Squash', 'Teams' )
		);
	}

	/** The landing page keeps the bare club name — "Home — Club" reads worse. */
	public function test_document_title_leaves_the_home_page_as_the_club_name(): void {
		$this->assertSame(
			'Crewe Vagrants Squash',
			Blueworx_Clubhouse_Frontend::document_title( 'Crewe Vagrants Squash', 'Home' )
		);
		$this->assertSame(
			'Crewe Vagrants Squash',
			Blueworx_Clubhouse_Frontend::document_title( 'Crewe Vagrants Squash', '' )
		);
	}

	/** Before the club sets a name there is nothing to append to. */
	public function test_document_title_falls_back_to_the_page_label(): void {
		$this->assertSame( 'Contact', Blueworx_Clubhouse_Frontend::document_title( '', 'Contact' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::document_title( '', '' ) );
	}

	/**
	 * render_body() installs Blueworx_Clubhouse_Menu::set_provider() so a saved
	 * menu actually renders — but that provider is process-global, and PHPUnit
	 * runs single-process. Left installed, it would make every later render in
	 * this run (including PreviewRenderTest, and any bare Page_Renderer call in
	 * this very test class) silently read this test's stored menu instead of
	 * Menu::DEFAULTS. First half proves the leak is real; calling tearDown()
	 * mid-test simulates PHPUnit's between-test call and proves it is cleared.
	 */
	public function test_render_body_installs_a_menu_provider_that_tear_down_clears(): void {
		wp_stub_on_clubhouse_page();
		update_option( 'clubhouse_menu', array(
			array( 'label' => 'Say hello', 'target' => 'page:contact', 'children' => array() ),
		) );

		Blueworx_Clubhouse_Frontend::render_body();

		$this->assertSame(
			array( array( 'label' => 'Say hello', 'target' => 'page:contact', 'children' => array() ) ),
			Blueworx_Clubhouse_Menu::current()->tree(),
			'the provider installed by render_body() is still live immediately afterwards'
		);

		$this->tearDown();

		$this->assertSame(
			Blueworx_Clubhouse_Menu::DEFAULTS,
			Blueworx_Clubhouse_Menu::current()->tree(),
			'once tearDown() runs, a bare render is back to the defaults, as PreviewRenderTest requires'
		);
	}

	/**
	 * Issue #211. The rewrite rule still matches a switched-off page, so
	 * WordPress has a valid query and served it 200 with the theme's fallback.
	 * Declining to render is not the same as saying the page is not there.
	 */
	public function test_a_switched_off_page_is_gone_rather_than_merely_unrendered(): void {
		$storage    = new Blueworx_Clubhouse_Fake_Storage();
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$visibility->set_page_visible( 'contact', false );

		$this->assertTrue( Blueworx_Clubhouse_Frontend::is_gone( 'contact', $visibility ) );
		$this->assertFalse( Blueworx_Clubhouse_Frontend::is_gone( 'about', $visibility ), 'a page that is on' );
	}

	/** A page whose integration is absent takes the same path and the same answer. */
	public function test_a_page_whose_integration_is_missing_is_gone_too(): void {
		$visibility = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertTrue( Blueworx_Clubhouse_Frontend::is_gone( 'booking', $visibility ) );

		Blueworx_Clubhouse_Integrations::set_detector( static fn( string $tag ): bool => true );
		try {
			$this->assertFalse( Blueworx_Clubhouse_Frontend::is_gone( 'booking', $visibility ) );
		} finally {
			Blueworx_Clubhouse_Integrations::set_detector( null );
		}
	}

	/**
	 * A URL that was never ours is not ours to 404 — and neither is the bare
	 * site root, which belongs to WordPress as much as to the plugin.
	 */
	public function test_a_url_that_is_not_ours_is_left_alone(): void {
		$visibility = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$visibility->set_page_visible( 'home', false );

		$this->assertFalse( Blueworx_Clubhouse_Frontend::is_gone( 'nope', $visibility ) );
		$this->assertFalse( Blueworx_Clubhouse_Frontend::is_gone( '', $visibility ) );
		$this->assertFalse( Blueworx_Clubhouse_Frontend::is_gone( null, $visibility ) );
	}

	public function test_style_family_picks_the_member_design_for_the_member_area(): void {
		$this->assertSame( 'member', Blueworx_Clubhouse_Frontend::style_family( 'member-dashboard', false ) );
	}

	public function test_style_family_picks_the_club_look_for_club_pages_and_articles(): void {
		$this->assertSame( 'look', Blueworx_Clubhouse_Frontend::style_family( 'about', false ) );
		$this->assertSame( 'look', Blueworx_Clubhouse_Frontend::style_family( '', false ) );
		$this->assertSame( 'look', Blueworx_Clubhouse_Frontend::style_family( null, true ) );
	}

	public function test_style_family_loads_nothing_off_our_pages(): void {
		$this->assertSame( 'none', Blueworx_Clubhouse_Frontend::style_family( null, false ) );
	}

	public function test_a_club_page_is_served_from_this_plugins_template(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$GLOBALS['wp_stub_queried_object_id'] = 42;
		$GLOBALS['wp_stub_is_404']            = false;

		$this->assertSame(
			dirname( __DIR__, 2 ) . '/templates/clubhouse.php',
			Blueworx_Clubhouse_Frontend::filter_template( '/theme/page.php' )
		);
	}

	public function test_any_other_page_keeps_the_themes_template(): void {
		// This runs on every front-end request. Anything not ours comes back
		// untouched or the plugin has taken over the whole site.
		$GLOBALS['wp_stub_queried_object_id'] = 999;
		$GLOBALS['wp_stub_is_front_page']     = false;

		$this->assertSame( '/theme/page.php', Blueworx_Clubhouse_Frontend::filter_template( '/theme/page.php' ) );
	}

	/**
	 * A page this site declines to serve gets the theme's own 404 page, not our
	 * document with an empty body inside it — right status, blank page.
	 */
	public function test_a_page_we_decline_to_serve_keeps_the_themes_404(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		( new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Options_Storage() ) )
			->set_page_visible( 'about', false );
		$GLOBALS['wp_stub_queried_object_id'] = 42;
		$GLOBALS['wp_stub_is_404']            = true;

		$this->assertSame( '/theme/404.php', Blueworx_Clubhouse_Frontend::filter_template( '/theme/404.php' ) );
	}

	/**
	 * WordPress answers 404 for reasons of its own that leave the page
	 * perfectly renderable — a page number that does not exist, for one. Ours
	 * is still the document to serve, or a mistyped password takes the login
	 * form off the screen instead of showing the error.
	 */
	public function test_a_servable_page_is_still_ours_when_wordpress_says_404(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'login' ), 42 );
		$GLOBALS['wp_stub_queried_object_id'] = 42;
		$GLOBALS['wp_stub_is_404']            = true;

		$this->assertSame(
			dirname( __DIR__, 2 ) . '/templates/clubhouse.php',
			Blueworx_Clubhouse_Frontend::filter_template( '/theme/404.php' )
		);
	}

	/**
	 * Home is found by its own page like every other club page, so a club that
	 * has deliberately chosen a different front page still reaches Home at the
	 * address Home lives at.
	 */
	public function test_home_renders_from_its_own_page_when_it_is_not_the_front_page(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 7 );
		$GLOBALS['wp_stub_is_front_page']     = false;
		$GLOBALS['wp_stub_queried_object_id'] = 7;

		$this->assertSame( '', Blueworx_Clubhouse_Frontend::current_page_slug() );
	}
}
