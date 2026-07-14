<?php
// tests/php/SetupControllerTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	private function storage(): Blueworx_Clubhouse_Storage {
		return new Blueworx_Clubhouse_Fake_Storage();
	}

	public function test_saves_look_branding_and_visibility(): void {
		$storage = $this->storage();
		$post = array(
			'clubhouse_look'      => 'floodlight',
			'clubhouse_accent'    => '#f7a70a',            // glow-only look: accepted (deep-legible)
			'clubhouse_club_name' => 'Riverside RFC',
			'clubhouse_logo'      => '42',
			'clubhouse_facebook'  => 'https://facebook.com/riverside',
			'clubhouse_instagram' => 'https://instagram.com/riverside',
			'clubhouse_page'      => array( 'events' => '1' ),
			'clubhouse_section'   => array( 'home.hero' => '1' ),
		);
		$notices = Blueworx_Clubhouse_Setup_Controller::handle_save( $post, $storage );

		$registry = Blueworx_Clubhouse_Frontend::registry( $storage );
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$vis      = new Blueworx_Clubhouse_Visibility( $storage );

		$this->assertInstanceOf( Blueworx_Clubhouse_Floodlight::class, $registry->active() );
		$this->assertSame( '#f7a70a', $branding->get_accent() );
		$this->assertSame( 'Riverside RFC', $branding->get_club_name() );
		$this->assertSame( '42', $branding->get_logo() );
		$this->assertTrue( $vis->is_page_visible( 'events' ) );
		$this->assertFalse( $vis->is_section_visible( 'home', 'ticker' ) ); // unticked => hidden
		$this->assertTrue( $vis->is_section_visible( 'home', 'hero' ) );
		$this->assertSame( array(), array_values( array_filter( $notices, static fn( $n ) => 'error' === $n['type'] ) ) );
	}

	public function test_illegible_accent_is_rejected_but_other_fields_save(): void {
		$storage = $this->storage();
		// Court Side is text-bearing; #7a7a7a fails the ink check -> rejected.
		$post = array(
			'clubhouse_look'      => 'court-side',
			'clubhouse_accent'    => '#7a7a7a',
			'clubhouse_club_name' => 'Riverside RFC',
		);
		$notices = Blueworx_Clubhouse_Setup_Controller::handle_save( $post, $storage );

		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$this->assertSame( '#c6f24e', $branding->get_accent() ); // unchanged default (rejected, not '#7a7a7a')
		$this->assertSame( 'Riverside RFC', $branding->get_club_name() );
		$errors = array_values( array_filter( $notices, static fn( $n ) => 'error' === $n['type'] ) );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsStringIgnoringCase( 'contrast', $errors[0]['text'] );
	}

	public function test_illegible_accent_rejection_preserves_prior_accent(): void {
		$storage  = $this->storage();
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_accent( '#7a2f3a' );
		$post = array( 'clubhouse_look' => 'court-side', 'clubhouse_accent' => '#7a7a7a' );
		Blueworx_Clubhouse_Setup_Controller::handle_save( $post, $storage );
		$this->assertSame( '#7a2f3a', ( new Blueworx_Clubhouse_Branding( $storage ) )->get_accent() );
	}

	public function test_look_switch_orphaning_accent_warns(): void {
		$storage  = $this->storage();
		// A mid-grey accent stored while on the glow-only Floodlight (fine there)...
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_accent( '#7a7a7a' );
		Blueworx_Clubhouse_Frontend::registry( $storage )->set_active( 'floodlight' );
		// ...now switch to text-bearing Court Side without a new accent: orphaned.
		$post = array( 'clubhouse_look' => 'court-side' );
		$notices = Blueworx_Clubhouse_Setup_Controller::handle_save( $post, $storage );

		$warnings = array_values( array_filter( $notices, static fn( $n ) => 'warning' === $n['type'] ) );
		$this->assertNotEmpty( $warnings );
	}

	public function test_save_invalidates_theme_cache(): void {
		$storage = $this->storage();
		$cache = new Blueworx_Clubhouse_Theme_Cache( $storage );
		$cache->root_css( new Blueworx_Clubhouse_Court_Side(), new Blueworx_Clubhouse_Branding( $storage ) );
		$this->assertNotSame( '', $storage->get( 'root_css', '' ) );

		Blueworx_Clubhouse_Setup_Controller::handle_save( array( 'clubhouse_club_name' => 'X' ), $storage );
		$this->assertSame( '', $storage->get( 'root_css', '' ) );
	}

	public function test_register_adds_admin_menu_and_enqueue_hooks(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Setup_Controller::register();
		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'admin_menu', $actions );
		$this->assertContains( 'admin_enqueue_scripts', $actions );
	}

	public function test_handle_save_enables_demo_mode_when_checked(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Setup_Controller::handle_save( array( 'clubhouse_demo_active' => '1' ), $storage );
		$this->assertTrue( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}

	public function test_handle_save_disables_demo_mode_when_absent(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Demo_State( $storage ) )->set( true );
		Blueworx_Clubhouse_Setup_Controller::handle_save( array(), $storage );
		$this->assertFalse( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}

	public function test_build_model_reflects_live_state(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Frontend::registry( $storage )->set_active( 'floodlight' );
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_club_name( 'Riverside RFC' );

		$model = Blueworx_Clubhouse_Setup_Controller::build_model( $storage, array(), '<nonce>', 'https://club.test/x' );

		$this->assertSame( '<nonce>', $model['nonce_field'] );
		$this->assertSame( 'Riverside RFC', $model['branding']['club_name'] );
		$active = array_values( array_filter( $model['looks'], static fn( $l ) => $l['active'] ) );
		$this->assertSame( 'floodlight', $active[0]['slug'] );
		$this->assertCount( 3, $model['looks'] );
		$this->assertSame( 5, $model['progress']['total'] );
	}

	public function test_capability_is_the_custom_clubhouse_cap(): void {
		$this->assertSame( 'manage_clubhouse', Blueworx_Clubhouse_Setup_Controller::CAPABILITY );
	}

	public function test_screen_html_renders_the_setup_form(): void {
		$html = Blueworx_Clubhouse_Setup_Controller::screen_html( new Blueworx_Clubhouse_Fake_Storage(), array() );
		$this->assertStringContainsString( 'clubhouse-setup', $html );
		$this->assertStringContainsString( '<form', $html );
	}

	public function test_enqueue_loads_assets_on_owner_dashboard(): void {
		wp_stub_reset();
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		Blueworx_Clubhouse_Setup_Controller::enqueue( 'index.php' );
		$this->assertNotEmpty( wp_stub_calls( 'wp_enqueue_media' ) );
		$this->assertNotEmpty( wp_stub_calls( 'wp_enqueue_style' ) );
	}

	public function test_enqueue_skips_dashboard_for_non_owner(): void {
		wp_stub_reset();
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'administrator' ) );
		Blueworx_Clubhouse_Setup_Controller::enqueue( 'index.php' );
		$this->assertSame( array(), wp_stub_calls( 'wp_enqueue_media' ) );
	}

	public function test_handle_save_persists_favicon_and_linkedin(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Setup_Controller::handle_save( array(
			'clubhouse_favicon'  => '88',
			'clubhouse_linkedin' => 'https://linkedin.com/company/riverside',
		), $storage );
		$b = new Blueworx_Clubhouse_Branding( $storage );
		$this->assertSame( '88', $b->get_favicon() );
		$this->assertSame( 'https://linkedin.com/company/riverside', $b->get_linkedin_url() );
	}

	public function test_build_model_exposes_favicon_linkedin_and_per_look_tokens(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$model   = Blueworx_Clubhouse_Setup_Controller::build_model( $storage, array(), '<nonce>', 'https://x/y' );

		$this->assertArrayHasKey( 'favicon', $model['branding'] );
		$this->assertArrayHasKey( 'linkedin', $model['branding'] );
		$this->assertArrayHasKey( 'look_tokens', $model );
		$this->assertArrayHasKey( 'court-side', $model['look_tokens'] );
		$this->assertArrayHasKey( '--color-bg', $model['look_tokens']['court-side'] );
		$this->assertArrayHasKey( '--color-accent-deep', $model['look_tokens']['court-side'] );
		$this->assertIsString( $model['font_face_css'] );
		$this->assertStringContainsString( '@font-face', $model['font_face_css'] );
	}
}
