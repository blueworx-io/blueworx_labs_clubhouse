<?php
// tests/php/DemoControllerTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
	}

	protected function tearDown(): void {
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
	}

	public function test_register_hooks_include_admin_post_toggle(): void {
		Blueworx_Clubhouse_Demo_Controller::register();
		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'admin_bar_menu', $actions );
		$this->assertContains( 'wp_footer', $actions );
		$this->assertContains( 'wp_enqueue_scripts', $actions );
		$this->assertContains( 'admin_post_' . Blueworx_Clubhouse_Demo_Controller::TOGGLE_ACTION, $actions );
	}

	/**
	 * The accent must be applied in wp_head, before first paint — demo.js is a
	 * footer script, so applying there would flash the club's saved colour first.
	 * The spec names moving this into the footer bundle as the regression to guard.
	 */
	public function test_head_script_is_hooked_early_in_wp_head(): void {
		Blueworx_Clubhouse_Demo_Controller::register();
		$head = array_values( array_filter(
			wp_stub_calls( 'add_action' ),
			static fn( $c ) => 'wp_head' === $c['args'][0]
		) );
		$this->assertCount( 1, $head, 'the pre-paint head script must be registered on wp_head' );
		$this->assertSame( 1, $head[0]['args'][2], 'priority 1 — it must run before anything paints' );
	}

	public function test_is_on_reflects_stored_state(): void {
		$this->assertFalse( Blueworx_Clubhouse_Demo_Controller::is_on() );
		( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->set( true );
		$this->assertTrue( Blueworx_Clubhouse_Demo_Controller::is_on() );
	}

	public function test_look_slug_uses_cookie_only_when_on(): void {
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'floodlight';
		$this->assertNull( Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ), 'off = no override' );
		( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->set( true );
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ) );
	}

	public function test_look_slug_null_for_unknown_look_when_on(): void {
		( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->set( true );
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'not-a-look';
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		$this->assertNull( Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ) );
	}

	public function test_apply_toggle_flips_state(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->assertTrue( Blueworx_Clubhouse_Demo_Controller::apply_toggle( $storage ), 'off -> on' );
		$this->assertTrue( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Controller::apply_toggle( $storage ), 'on -> off' );
		$this->assertFalse( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}

	public function test_enqueue_serves_assets_when_on_regardless_of_cap(): void {
		( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->set( true );
		Blueworx_Clubhouse_Demo_Controller::enqueue();
		$styles = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'wp_enqueue_style' ) );
		$this->assertContains( 'clubhouse-demo', $styles );
	}

	public function test_enqueue_serves_nothing_when_off(): void {
		Blueworx_Clubhouse_Demo_Controller::enqueue();
		$this->assertSame( array(), wp_stub_calls( 'wp_enqueue_style' ) );
	}

	/**
	 * The load-bearing line: palettes must derive for the look the VIEWER is seeing,
	 * not the club's saved one. Frontend::context() resolves the demo look via
	 * $registry->get($demo_slug) and never makes it the registry's ACTIVE look, so
	 * deriving from active() would emit tokens for the wrong shell.
	 *
	 * The browser suite cannot catch this — preview/index.php calls set_active() with
	 * the viewer's look, conflating the two. This test is the only guard.
	 */
	public function test_head_script_derives_for_the_viewers_demo_look_not_the_saved_look(): void {
		$storage = new Blueworx_Clubhouse_Options_Storage();
		( new Blueworx_Clubhouse_Demo_State( $storage ) )->set( true );
		$registry = Blueworx_Clubhouse_Frontend::registry( $storage );
		$registry->set_active( 'court-side' );                                 // the club's SAVED look
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'floodlight';  // the VIEWER's demo look

		ob_start();
		Blueworx_Clubhouse_Demo_Controller::render_head_script();
		$out = (string) ob_get_clean();

		$viewer = Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Floodlight() )['volt-lime']['tokens']['--color-accent-block'];
		$saved  = Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Court_Side() )['volt-lime']['tokens']['--color-accent-block'];

		$this->assertNotSame( $saved, $viewer, 'guard: the two looks must derive differently or this test proves nothing' );
		$this->assertStringContainsString( $viewer, $out, 'must derive for the viewer\'s demo look' );
		$this->assertStringNotContainsString( $saved, $out, 'must NOT derive for the club\'s saved look' );
	}
}
