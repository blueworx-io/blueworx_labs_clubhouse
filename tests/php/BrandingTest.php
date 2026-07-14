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

	public function test_social_url_defaults(): void {
		$b = $this->branding();
		$this->assertSame( 'https://facebook.com/clubhouse', $b->get_facebook_url() );
		$this->assertSame( 'https://instagram.com/clubhouse', $b->get_instagram_url() );
	}

	public function test_social_urls_persist(): void {
		$b = $this->branding();
		$b->set_facebook_url( 'https://facebook.com/marlowrugby' );
		$b->set_instagram_url( 'https://instagram.com/marlowrugby' );
		$this->assertSame( 'https://facebook.com/marlowrugby', $b->get_facebook_url() );
		$this->assertSame( 'https://instagram.com/marlowrugby', $b->get_instagram_url() );
	}

	public function test_social_urls_survive_a_new_instance_over_same_storage(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_facebook_url( 'https://facebook.com/marlowrugby' );
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_instagram_url( 'https://instagram.com/marlowrugby' );
		$again = new Blueworx_Clubhouse_Branding( $storage );
		$this->assertSame( 'https://facebook.com/marlowrugby', $again->get_facebook_url() );
		$this->assertSame( 'https://instagram.com/marlowrugby', $again->get_instagram_url() );
	}

	public function test_favicon_defaults_empty_and_round_trips(): void {
		$b = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertSame( '', $b->get_favicon() );
		$b->set_favicon( '77' );
		$this->assertSame( '77', $b->get_favicon() );
	}

	public function test_linkedin_has_demo_default_and_round_trips(): void {
		$b = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertSame( 'https://linkedin.com/company/clubhouse', $b->get_linkedin_url() );
		$b->set_linkedin_url( 'https://linkedin.com/company/riverside' );
		$this->assertSame( 'https://linkedin.com/company/riverside', $b->get_linkedin_url() );
	}
}
