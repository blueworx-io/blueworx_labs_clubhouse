<?php
// tests/php/FrontendTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class FrontendTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_register_registers_expected_hooks(): void {
		Blueworx_Clubhouse_Frontend::register();

		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$filters = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_filter' ) );

		$this->assertContains( 'init', $actions );
		$this->assertContains( 'wp_enqueue_scripts', $actions );
		$this->assertContains( 'template_include', $filters );
		$this->assertContains( 'wp_resource_hints', $filters );
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

		$this->assertStringContainsString( 'fonts.googleapis.com', $specs['fonts_url'] );
		$this->assertSame( 'https://club.test/wp-content/plugins/clubhouse/assets/looks/court-side.css', $specs['stylesheet_url'] );
		$this->assertSame( ':root{--x:1}', $specs['inline_css'] );
		$this->assertSame( 'https://club.test/wp-content/plugins/clubhouse/assets/js/reveal.js', $specs['reveal_url'] );
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

	public function test_resolve_logo_turns_an_attachment_id_into_a_url(): void {
		$this->assertSame( 'https://club.test/wp-content/uploads/att-9.png', Blueworx_Clubhouse_Frontend::resolve_logo( '9' ) );
	}

	public function test_resolve_logo_passes_through_a_stored_url_and_empty(): void {
		$this->assertSame( 'https://cdn.example/logo.svg', Blueworx_Clubhouse_Frontend::resolve_logo( 'https://cdn.example/logo.svg' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::resolve_logo( '' ) );
	}
}
