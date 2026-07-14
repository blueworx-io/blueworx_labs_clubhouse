<?php
// tests/php/SetupProgressTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupProgressTest extends TestCase {

	private function look(): Blueworx_Clubhouse_Base_Look {
		return new Blueworx_Clubhouse_Court_Side();
	}

	public function test_fresh_defaults_count_zero_over_six_groups(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );

		$this->assertSame( 6, $p['total'] );
		$this->assertSame( 0, $p['completed'] );
		$this->assertSame( array( 'look', 'accent', 'club_name', 'logo_favicon', 'social', 'visibility' ), array_keys( $p['items'] ) );
		foreach ( $p['items'] as $done ) {
			$this->assertFalse( $done );
		}
	}

	public function test_all_groups_complete(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$branding->set_accent( '#7a2f3a' );                 // legible on Court Side, != default
		$branding->set_club_name( 'Riverside RFC' );
		$branding->set_logo( '42' );
		$branding->set_facebook_url( 'https://facebook.com/riverside' );

		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), true, true );

		foreach ( $p['items'] as $key => $done ) {
			$this->assertTrue( $done, "expected group {$key} to be complete" );
		}
		$this->assertSame( 6, $p['completed'] );
	}

	public function test_visibility_counts_only_once_saved(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$not_saved = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false, false );
		$this->assertFalse( $not_saved['items']['visibility'] );
		$saved = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false, true );
		$this->assertTrue( $saved['items']['visibility'] );
	}

	public function test_favicon_alone_completes_the_logo_favicon_group(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$branding->set_favicon( '99' ); // logo still empty
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );
		$this->assertTrue( $p['items']['logo_favicon'] );
	}

	public function test_linkedin_alone_completes_the_social_group(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$branding->set_linkedin_url( 'https://linkedin.com/company/riverside' ); // fb/ig still default
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );
		$this->assertTrue( $p['items']['social'] );
	}

	public function test_default_socials_do_not_count(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );
		$this->assertFalse( $p['items']['social'] );
	}

	public function test_illegible_non_default_accent_does_not_count(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$branding->set_accent( '#7a7a7a' ); // != default, but illegible-as-ink on Court Side
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), true );
		$this->assertFalse( $p['items']['accent'] );
	}
}
