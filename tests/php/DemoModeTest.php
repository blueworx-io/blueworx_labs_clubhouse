<?php
// tests/php/DemoModeTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoModeTest extends TestCase {

	public function test_cookie_name_constants(): void {
		$this->assertSame( 'clubhouse_demo', Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG );
		$this->assertSame( 'clubhouse_demo_look', Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK );
	}

	public function test_is_active_requires_both_capability_and_flag(): void {
		$this->assertTrue( Blueworx_Clubhouse_Demo_Mode::is_active( true, '1' ) );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::is_active( false, '1' ), 'non-admin cookie must not activate' );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::is_active( true, null ), 'no flag cookie = inactive' );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::is_active( true, '0' ), 'flag must be "1"' );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::is_active( true, 'yes' ) );
	}

	public function test_resolve_returns_null_when_inactive(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( false, 'floodlight', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_returns_null_without_look_cookie(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, null, array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_returns_known_slug(): void {
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, 'floodlight', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_unknown_or_stale_slug_falls_through_to_null(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, 'retired-look', array( 'court-side', 'floodlight' ) ) );
	}
}
