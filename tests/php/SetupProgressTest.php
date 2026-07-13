<?php
// tests/php/SetupProgressTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupProgressTest extends TestCase {

	private function look(): Blueworx_Clubhouse_Base_Look {
		return new Blueworx_Clubhouse_Court_Side();
	}

	public function test_fresh_defaults_count_zero_and_look_not_chosen(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );

		$this->assertSame( 6, $p['total'] );
		$this->assertSame( 0, $p['completed'] );
		foreach ( $p['items'] as $done ) {
			$this->assertFalse( $done );
		}
	}

	public function test_configured_values_count_toward_completion(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_accent( '#7a2f3a' );                 // legible on Court Side, != default
		$branding->set_club_name( 'Riverside RFC' );
		$branding->set_logo( '42' );
		$branding->set_facebook_url( 'https://facebook.com/riverside' );
		$branding->set_instagram_url( 'https://instagram.com/riverside' );

		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), true );

		foreach ( $p['items'] as $key => $done ) {
			$this->assertTrue( $done, "expected item {$key} to be complete" );
		}
		$this->assertSame( 6, $p['completed'] );
	}

	public function test_illegible_non_default_accent_does_not_count(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_accent( '#7a7a7a' ); // != default, but illegible-as-ink on Court Side
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), true );
		$this->assertFalse( $p['items']['accent'] );
	}

	public function test_default_values_do_not_count(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_club_name( 'ClubHouse' ); // the demo default
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );
		$this->assertFalse( $p['items']['club_name'] );
	}
}
