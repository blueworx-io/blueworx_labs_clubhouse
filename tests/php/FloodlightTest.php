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
		$this->assertSame( '#14110b', $t['--color-bg'] );
		$this->assertSame( '#f3ede0', $t['--color-ink'] );
		$this->assertSame( '#1e1913', $t['--color-paper'] );
		$this->assertSame( '#a99f8c', $t['--color-ink-soft'] );
		$this->assertSame( '#302a20', $t['--color-line'] );
		$this->assertStringContainsString( 'Bricolage Grotesque', $t['--font-display'] );
		$this->assertStringContainsString( 'Hanken Grotesk', $t['--font-body'] );
		// Bold-modern radii — enough body to carry a glow halo.
		$this->assertSame( '16px', $t['--radius-xl'] );
		$this->assertSame( '11px', $t['--radius-lg'] );
		$this->assertSame( '7px', $t['--radius-md'] );
		// Accent is engine-derived, never baked into the look.
		$this->assertArrayNotHasKey( '--color-accent', $t );
	}

	public function test_fonts_are_bricolage_grotesque_and_body(): void {
		$families = array_column( ( new Blueworx_Clubhouse_Floodlight() )->fonts(), 'family' );
		$this->assertSame( array( 'Bricolage Grotesque', 'Hanken Grotesk' ), $families );
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
		$branding->set_accent( '#f7a70a' );

		$vars = Blueworx_Clubhouse_Theme_Css::compose( $registry->active(), $branding );
		$this->assertSame( '#14110b', $vars['--color-bg'] );
		$this->assertSame( '#f7a70a', $vars['--color-accent'] );
		$this->assertArrayHasKey( '--color-accent-ink', $vars );
		$this->assertArrayHasKey( '--color-accent-deep', $vars );

		// The load-bearing dark-shell guarantee: accent-as-text (accent-deep) clears
		// WCAG AA against the dark canvas, so every accent mark/label/numeral is legible.
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $vars['--color-accent-deep'], $vars['--color-bg'] )
		);
	}
}
