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
}
