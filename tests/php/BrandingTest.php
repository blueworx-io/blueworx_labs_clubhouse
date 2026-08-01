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

	/**
	 * The secondary ships EMPTY, and empty is a meaningful value: it means "derive
	 * it from my primary". A stored default would instead pin every site to one
	 * colour and clash the moment the accent changed.
	 */
	public function test_the_secondary_ships_unset_and_round_trips(): void {
		$b = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertSame( '', $b->get_secondary() );
		$this->assertFalse( $b->has_secondary() );

		$b->set_secondary( '1D4ED8' );
		$this->assertSame( '#1d4ed8', $b->get_secondary(), 'normalised to a lowercase hash-prefixed hex' );
		$this->assertTrue( $b->has_secondary() );
	}

	/** Clearing stores empty, not '#' — the picker's Clear button posts an empty field. */
	public function test_clearing_the_secondary_returns_it_to_unset(): void {
		$b = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$b->set_secondary( '#1d4ed8' );
		$b->set_secondary( '' );
		$this->assertSame( '', $b->get_secondary() );
		$this->assertFalse( $b->has_secondary() );

		$b->set_secondary( '#' );
		$this->assertSame( '', $b->get_secondary(), 'a bare hash is empty, not a colour' );
	}

	/** A club's own secondary is returned unchanged, whatever the look. */
	public function test_a_chosen_secondary_is_used_as_is(): void {
		$b = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$b->set_secondary( '#1d4ed8' );
		$this->assertSame( '#1d4ed8', $b->effective_secondary( new Blueworx_Clubhouse_Court_Side() ) );
		$this->assertSame( '#1d4ed8', $b->effective_secondary( new Blueworx_Clubhouse_Floodlight() ) );
	}

	/**
	 * Unset falls back to a colour derived from the accent AND the look — the
	 * lightness a derived partner needs in order to stay legible differs between a
	 * light look and a dark one.
	 */
	public function test_an_unset_secondary_is_derived_from_the_accent_and_the_look(): void {
		$b = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$b->set_accent( '#c6f24e' );

		$light = $b->effective_secondary( new Blueworx_Clubhouse_Court_Side() );
		$dark  = $b->effective_secondary( new Blueworx_Clubhouse_Floodlight() );

		$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $light );
		$this->assertNotSame( '#c6f24e', $light );
		$this->assertNotSame( $light, $dark );
	}

	/** The shipped accent is exposed so the picker's reset has something to reset to. */
	public function test_the_default_accent_is_exposed(): void {
		$this->assertSame( '#c6f24e', Blueworx_Clubhouse_Branding::default_accent() );
	}
}
