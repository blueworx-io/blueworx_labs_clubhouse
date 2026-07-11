<?php
// tests/php/FloodlightTest.php

use PHPUnit\Framework\TestCase;

final class FloodlightTest extends TestCase {

	public function test_identity_and_stylesheet(): void {
		$look = new Blueworx_Clubhouse_Floodlight();
		$this->assertSame( 'floodlight', $look->slug() );
		$this->assertSame( 'Floodlight', $look->name() );
		$this->assertSame( 'assets/looks/floodlight.css', $look->stylesheet() );
		$this->assertNotSame( '', $look->description() );
	}

	public function test_tokens_carry_the_fixed_dark_shell(): void {
		$t = ( new Blueworx_Clubhouse_Floodlight() )->tokens();
		$this->assertSame( '#16120c', $t['--color-bg'] );
		$this->assertSame( '#f4ede0', $t['--color-ink'] );
		$this->assertSame( '#211a12', $t['--color-paper'] );
		$this->assertSame( '#a99f8c', $t['--color-ink-soft'] );
		$this->assertSame( '#3a3020', $t['--color-line'] );
		$this->assertStringContainsString( 'Bricolage Grotesque', $t['--font-display'] );
		$this->assertStringContainsString( 'Inter', $t['--font-body'] );
		// Crisp athletic mid radii.
		$this->assertSame( '16px', $t['--radius-xl'] );
		$this->assertSame( '12px', $t['--radius-lg'] );
		$this->assertSame( '8px', $t['--radius-md'] );
		// Accent is engine-derived, never baked into the look.
		$this->assertArrayNotHasKey( '--color-accent', $t );
	}

	public function test_fonts_are_bricolage_grotesque_and_body(): void {
		$families = array_column( ( new Blueworx_Clubhouse_Floodlight() )->fonts(), 'family' );
		$this->assertSame( array( 'Bricolage Grotesque', 'Inter' ), $families );
		foreach ( ( new Blueworx_Clubhouse_Floodlight() )->fonts() as $font ) {
			$this->assertArrayHasKey( 'weights', $font );
			$this->assertArrayHasKey( 'display', $font );
			$this->assertNotEmpty( $font['weights'] );
		}
	}

	public function test_registers_and_composes_with_derived_accent(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$registry->register( new Blueworx_Clubhouse_Floodlight() );
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_accent( '#8bf34d' );

		$vars = Blueworx_Clubhouse_Theme_Css::compose( $registry->active(), $branding );
		$this->assertSame( '#16120c', $vars['--color-bg'] );
		$this->assertSame( '#8bf34d', $vars['--color-accent'] );
		$this->assertArrayHasKey( '--color-accent-ink', $vars );
		$this->assertArrayHasKey( '--color-accent-deep', $vars );
	}
}
