<?php
// tests/php/MembersHouseTest.php

use PHPUnit\Framework\TestCase;

final class MembersHouseTest extends TestCase {

	public function test_identity_and_stylesheet(): void {
		$look = new Blueworx_Clubhouse_Members_House();
		$this->assertSame( 'members-house', $look->slug() );
		$this->assertSame( "Members' House", $look->name() );
		$this->assertSame( 'assets/looks/members-house.css', $look->stylesheet() );
		$this->assertNotSame( '', $look->description() );
	}

	public function test_tokens_carry_the_fixed_parchment_shell(): void {
		$t = ( new Blueworx_Clubhouse_Members_House() )->tokens();
		$this->assertSame( '#f2ece0', $t['--color-bg'] );
		$this->assertSame( '#201c15', $t['--color-ink'] );
		$this->assertSame( '#fbf7ef', $t['--color-paper'] );
		$this->assertSame( '#6b6154', $t['--color-ink-soft'] );
		$this->assertSame( '#e0d8c7', $t['--color-line'] );
		$this->assertStringContainsString( 'Fraunces', $t['--font-display'] );
		$this->assertStringContainsString( 'Mulish', $t['--font-body'] );
		// Small crisp radii — the editorial signature.
		$this->assertSame( '10px', $t['--radius-xl'] );
		$this->assertSame( '7px', $t['--radius-lg'] );
		$this->assertSame( '4px', $t['--radius-md'] );
		// Accent is engine-derived, never baked into the look.
		$this->assertArrayNotHasKey( '--color-accent', $t );
	}

	public function test_fonts_are_fraunces_and_mulish(): void {
		$families = array_column( ( new Blueworx_Clubhouse_Members_House() )->fonts(), 'family' );
		$this->assertSame( array( 'Fraunces', 'Mulish' ), $families );
		foreach ( ( new Blueworx_Clubhouse_Members_House() )->fonts() as $font ) {
			$this->assertArrayHasKey( 'weights', $font );
			$this->assertArrayHasKey( 'display', $font );
			$this->assertNotEmpty( $font['weights'] );
		}
	}

	public function test_registers_and_composes_with_derived_accent(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$registry->register( new Blueworx_Clubhouse_Members_House() );
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_accent( '#7a2f3a' );

		$vars = Blueworx_Clubhouse_Theme_Css::compose( $registry->active(), $branding );
		$this->assertSame( '#f2ece0', $vars['--color-bg'] );
		$this->assertSame( '#7a2f3a', $vars['--color-accent'] );
		$this->assertArrayHasKey( '--color-accent-ink', $vars );
		$this->assertArrayHasKey( '--color-accent-deep', $vars );
	}
}
