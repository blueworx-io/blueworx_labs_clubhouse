<?php
// tests/php/ThemeCssTest.php

use PHPUnit\Framework\TestCase;

final class ThemeCssTest extends TestCase {

	private function branding( string $accent ): Blueworx_Clubhouse_Branding {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		$b = new Blueworx_Clubhouse_Branding( $s );
		$b->set_accent( $accent );
		return $b;
	}

	public function test_includes_shell_tokens(): void {
		$vars = Blueworx_Clubhouse_Theme_Css::compose(
			new Blueworx_Clubhouse_Fake_Look(),
			$this->branding( '#c6f24e' )
		);
		$this->assertSame( '#faf8f3', $vars['--color-bg'] );
		$this->assertSame( '#1c1b18', $vars['--color-ink'] );
		$this->assertSame( 'Syne, sans-serif', $vars['--font-display'] );
	}

	public function test_includes_derived_accent_tokens(): void {
		$vars = Blueworx_Clubhouse_Theme_Css::compose(
			new Blueworx_Clubhouse_Fake_Look(),
			$this->branding( '#ff5b23' )
		);
		$this->assertSame( '#ff5b23', $vars['--color-accent'] );
		$this->assertArrayHasKey( '--color-accent-ink', $vars );
		$this->assertArrayHasKey( '--color-accent-deep', $vars );
		$this->assertArrayHasKey( '--color-accent-wash', $vars );
	}

	public function test_accent_token_overrides_a_shell_collision(): void {
		// A look that (wrongly) defines --color-accent must lose to the derived value.
		$look = new Blueworx_Clubhouse_Fake_Look(
			'x', 'X', 'x',
			array( '--color-bg' => '#faf8f3', '--color-ink' => '#1c1b18', '--color-accent' => '#000000' )
		);
		$vars = Blueworx_Clubhouse_Theme_Css::compose( $look, $this->branding( '#3b5bdb' ) );
		$this->assertSame( '#3b5bdb', $vars['--color-accent'] );
	}

	/**
	 * The footer sets the club's name at poster scale and sizes it from these,
	 * so a long name shrinks to fit instead of running off the edge. A name of
	 * wide letters has to count for more than one of narrow letters the same
	 * length, or one of the two is sized wrong.
	 */
	public function test_includes_the_length_of_the_club_name(): void {
		$length = function ( string $name ): array {
			$b = $this->branding( '#c6f24e' );
			$b->set_club_name( $name );
			$vars = Blueworx_Clubhouse_Theme_Css::compose( new Blueworx_Clubhouse_Fake_Look(), $b );
			$this->assertMatchesRegularExpression( '/^\d+(\.\d)?$/', $vars['--wordmark-len'] );
			$this->assertMatchesRegularExpression( '/^\d+(\.\d)?$/', $vars['--wordmark-len-caps'] );
			return array( (float) $vars['--wordmark-len'], (float) $vars['--wordmark-len-caps'] );
		};
		[ $short ]       = $length( 'Crewe' );
		[ $long, $caps ] = $length( 'Crewe Squash Club' );
		[ $narrow ]      = $length( 'illicit' );
		[ $wide ]        = $length( 'mwmwmwm' );
		$this->assertGreaterThan( $short, $long );
		$this->assertGreaterThan( $long, $caps, 'capitals take more room than the same name in lower case' );
		$this->assertGreaterThan( $narrow * 2, $wide );
	}

	public function test_to_css_emits_root_block(): void {
		$css = Blueworx_Clubhouse_Theme_Css::to_css( array( '--color-bg' => '#fff', '--x' => '1px' ) );
		$this->assertSame( ':root{--color-bg:#fff;--x:1px;}', $css );
	}
}
