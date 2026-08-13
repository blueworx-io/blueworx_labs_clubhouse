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
	 * so a release that adds a page slug needs its own flush — otherwise the rule
	 * is registered while WordPress serves cached rules, and the new permalink
	 * 404s with nothing to say why. This is how v0.42.0's /booking/ shipped broken.
	 */
	public function test_rewrites_flush_once_per_version_then_stay_quiet(): void {
		$this->assertTrue(
			Blueworx_Clubhouse_Frontend::rewrites_need_flush( '0.42.1', null ),
			'never stamped — a fresh install or an upgrade from before the stamp existed'
		);
		$this->assertTrue(
			Blueworx_Clubhouse_Frontend::rewrites_need_flush( '0.42.1', '0.42.0' ),
			'stamp predates the running version'
		);
		$this->assertFalse(
			Blueworx_Clubhouse_Frontend::rewrites_need_flush( '0.42.1', '0.42.1' ),
			'already flushed for this version — must not flush on every request'
		);
		$this->assertTrue(
			Blueworx_Clubhouse_Frontend::rewrites_need_flush( '0.42.1', array( 'junk' ) ),
			'a non-string option is not a usable stamp'
		);
	}

	public function test_register_hooks_the_flush_after_registering_the_rules(): void {
		Blueworx_Clubhouse_Frontend::register();
		$init = array_values( array_filter(
			wp_stub_calls( 'add_action' ),
			static fn( array $c ): bool => 'init' === $c['args'][0]
		) );
		$rules = null;
		$flush = null;
		foreach ( $init as $call ) {
			$target = $call['args'][1];
			if ( is_array( $target ) && 'register_rewrites' === $target[1] ) {
				$rules = $call['args'][2] ?? 10;
			}
			if ( is_array( $target ) && 'maybe_flush_rewrites' === $target[1] ) {
				$flush = $call['args'][2] ?? 10;
			}
		}
		$this->assertNotNull( $rules, 'rules are registered on init' );
		$this->assertNotNull( $flush, 'the flush check runs on init' );
		$this->assertGreaterThan( $rules, $flush, 'flushing before registering would cache the old rules' );
	}

	public function test_link_url_home_is_site_root(): void {
		$this->assertSame( 'https://club.test/', Blueworx_Clubhouse_Frontend::link_url( 'home' ) );
	}

	public function test_link_url_pretty_permalinks_use_slug_path(): void {
		update_option( 'permalink_structure', '/%postname%/' );
		$this->assertSame( 'https://club.test/about/', Blueworx_Clubhouse_Frontend::link_url( 'about' ) );
	}

	public function test_link_url_plain_permalinks_fall_back_to_query_var(): void {
		update_option( 'permalink_structure', '' );
		$this->assertSame( 'https://club.test/?clubhouse_page=about', Blueworx_Clubhouse_Frontend::link_url( 'about' ) );
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

	public function test_register_rewrites_adds_one_rule_per_non_home_page(): void {
		Blueworx_Clubhouse_Frontend::register_rewrites();

		$rules    = wp_stub_calls( 'add_rewrite_rule' );
		$non_home = array_filter( Blueworx_Clubhouse_Page_Map::pages(), static fn( $p ) => '' !== $p['slug'] );
		// One per page, plus one each for /sports/<sport>/ and /teams/<team>/.
		$this->assertCount( count( $non_home ) + 2, $rules );
		$all = implode( '|', array_map( static fn( $r ) => $r['args'][1], $rules ) );
		$this->assertStringContainsString( 'clubhouse_page=about', $all );
	}

	/**
	 * /sports/rugby/ must not be swallowed by the bare /sports/ rule. add_rewrite_rule
	 * with 'top' prepends, so the item rule has to be registered after its listing
	 * to end up in front of it.
	 */
	public function test_a_sport_page_rule_is_matched_before_the_sports_listing(): void {
		Blueworx_Clubhouse_Frontend::register_rewrites();

		$patterns = array_map( static fn( $r ) => $r['args'][0], wp_stub_calls( 'add_rewrite_rule' ) );
		$listing  = array_search( '^sports/?$', $patterns, true );
		$item     = array_search( '^sports/([^/]+)/?$', $patterns, true );

		$this->assertIsInt( $listing );
		$this->assertIsInt( $item );
		$this->assertGreaterThan( $listing, $item, 'the item rule must be added after the listing to sit above it' );
	}

	public function test_a_sport_page_rule_carries_the_item_through(): void {
		Blueworx_Clubhouse_Frontend::register_rewrites();

		$targets = array_map( static fn( $r ) => $r['args'][1], wp_stub_calls( 'add_rewrite_rule' ) );
		$this->assertContains(
			'index.php?clubhouse_page=sports&clubhouse_item=$matches[1]',
			$targets
		);
		$this->assertContains(
			'index.php?clubhouse_page=teams&clubhouse_item=$matches[1]',
			$targets
		);
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
}
