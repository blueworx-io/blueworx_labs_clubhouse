<?php
// tests/php/CourtSideTest.php

use PHPUnit\Framework\TestCase;

final class CourtSideTest extends TestCase {

	public function test_identity_and_stylesheet(): void {
		$look = new Blueworx_Clubhouse_Court_Side();
		$this->assertSame( 'court-side', $look->slug() );
		$this->assertSame( 'Court Side', $look->name() );
		$this->assertSame( 'assets/looks/court-side.css', $look->stylesheet() );
	}

	public function test_tokens_carry_the_fixed_shell(): void {
		$t = ( new Blueworx_Clubhouse_Court_Side() )->tokens();
		$this->assertSame( '#faf8f3', $t['--color-bg'] );
		$this->assertSame( '#1c1b18', $t['--color-ink'] );
		$this->assertStringContainsString( 'Syne', $t['--font-display'] );
		$this->assertArrayNotHasKey( '--color-accent', $t ); // accent is engine-derived, never in the look
	}

	public function test_fonts_are_syne_and_inter(): void {
		$families = array_column( ( new Blueworx_Clubhouse_Court_Side() )->fonts(), 'family' );
		$this->assertSame( array( 'Syne', 'Inter' ), $families );
	}

	public function test_registers_and_composes_with_derived_accent(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$registry->register( new Blueworx_Clubhouse_Court_Side() );
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_accent( '#ff5b23' );

		$vars = Blueworx_Clubhouse_Theme_Css::compose( $registry->active(), $branding );
		$this->assertSame( '#faf8f3', $vars['--color-bg'] );
		$this->assertSame( '#ff5b23', $vars['--color-accent'] );
		$this->assertArrayHasKey( '--color-accent-ink', $vars );
	}
}
