<?php
// tests/php/DemoModeTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoModeTest extends TestCase {

	public function test_look_cookie_constant(): void {
		$this->assertSame( 'clubhouse_demo_look', Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK );
	}

	public function test_resolve_returns_null_when_demo_off(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( false, 'floodlight', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_returns_null_without_look_cookie(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, null, array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_returns_known_slug_when_on(): void {
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, 'floodlight', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_unknown_slug_falls_through(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, 'retired', array( 'court-side', 'floodlight' ) ) );
	}
}
