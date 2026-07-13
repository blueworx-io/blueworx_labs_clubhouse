<?php
// tests/php/DemoControllerTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] );
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
	}

	protected function tearDown(): void {
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] );
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
	}

	public function test_register_hooks_admin_bar_footer_and_enqueue(): void {
		Blueworx_Clubhouse_Demo_Controller::register();
		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'admin_bar_menu', $actions );
		$this->assertContains( 'wp_footer', $actions );
		$this->assertContains( 'wp_enqueue_scripts', $actions );
	}

	public function test_is_active_false_without_flag_cookie(): void {
		// current_user_can stub returns true (admin), but no flag cookie is set.
		$this->assertFalse( Blueworx_Clubhouse_Demo_Controller::is_active() );
	}

	public function test_is_active_true_for_admin_with_flag_cookie(): void {
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] = '1';
		$this->assertTrue( Blueworx_Clubhouse_Demo_Controller::is_active() );
	}

	public function test_look_slug_returns_known_cookie_look_when_active(): void {
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] = '1';
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'floodlight';
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ) );
	}

	public function test_look_slug_null_when_flag_absent(): void {
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'floodlight';
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertNull( Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ) );
	}

	public function test_look_slug_null_for_unknown_look(): void {
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] = '1';
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'not-a-look';
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertNull( Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ) );
	}

	public function test_enqueue_registers_demo_assets_for_admin(): void {
		Blueworx_Clubhouse_Demo_Controller::enqueue();
		$styles  = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'wp_enqueue_style' ) );
		$scripts = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'wp_enqueue_script' ) );
		$this->assertContains( 'clubhouse-demo', $styles );
		$this->assertContains( 'clubhouse-demo', $scripts );
	}
}
