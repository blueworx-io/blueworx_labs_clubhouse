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

		$rules      = wp_stub_calls( 'add_rewrite_rule' );
		$non_home   = array_filter( Blueworx_Clubhouse_Page_Map::pages(), static fn( $p ) => '' !== $p['slug'] );
		$this->assertCount( count( $non_home ), $rules );
		// Each rule maps its slug to the clubhouse_page query var.
		$this->assertStringContainsString( 'clubhouse_page=about', $rules[0]['args'][1] . $rules[1]['args'][1] . $rules[2]['args'][1] );
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
}
