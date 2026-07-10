<?php

use PHPUnit\Framework\TestCase;

final class BrandingTest extends TestCase {

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_defaults(): void {
		$b = $this->branding();
		$this->assertSame( '#c6f24e', $b->get_accent() );
		$this->assertSame( 'ClubHouse', $b->get_club_name() );
		$this->assertSame( '', $b->get_logo() );
	}

	public function test_accent_persists_and_is_lowercased_with_hash(): void {
		$b = $this->branding();
		$b->set_accent( 'FF5B23' );
		$this->assertSame( '#ff5b23', $b->get_accent() );
	}

	public function test_name_and_logo_persist(): void {
		$b = $this->branding();
		$b->set_club_name( 'Marlow Rugby' );
		$b->set_logo( 'https://x/logo.png' );
		$this->assertSame( 'Marlow Rugby', $b->get_club_name() );
		$this->assertSame( 'https://x/logo.png', $b->get_logo() );
	}

	public function test_survives_a_new_instance_over_same_storage(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_accent( '#3b5bdb' );
		$this->assertSame( '#3b5bdb', ( new Blueworx_Clubhouse_Branding( $storage ) )->get_accent() );
	}
}
