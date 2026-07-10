<?php
// tests/php/ColorEngineMathTest.php

use PHPUnit\Framework\TestCase;

final class ColorEngineMathTest extends TestCase {

	public function test_luminance_extremes(): void {
		$this->assertEqualsWithDelta( 0.0, Blueworx_Clubhouse_Color_Engine::relative_luminance( '#000000' ), 0.001 );
		$this->assertEqualsWithDelta( 1.0, Blueworx_Clubhouse_Color_Engine::relative_luminance( '#ffffff' ), 0.001 );
	}

	public function test_contrast_black_on_white_is_21(): void {
		$this->assertEqualsWithDelta( 21.0, Blueworx_Clubhouse_Color_Engine::contrast_ratio( '#000000', '#ffffff' ), 0.05 );
	}

	public function test_contrast_is_symmetric(): void {
		$ab = Blueworx_Clubhouse_Color_Engine::contrast_ratio( '#c6f24e', '#1c1b18' );
		$ba = Blueworx_Clubhouse_Color_Engine::contrast_ratio( '#1c1b18', '#c6f24e' );
		$this->assertEqualsWithDelta( $ab, $ba, 0.0001 );
	}

	public function test_hex_normalisation_accepts_shorthand_and_no_hash(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Color_Engine::relative_luminance( '#ffffff' ),
			Blueworx_Clubhouse_Color_Engine::relative_luminance( 'fff' )
		);
	}

	public function test_mix_endpoints_and_midpoint(): void {
		$this->assertSame( '#ffffff', Blueworx_Clubhouse_Color_Engine::mix( '#ffffff', '#000000', 1.0 ) );
		$this->assertSame( '#000000', Blueworx_Clubhouse_Color_Engine::mix( '#ffffff', '#000000', 0.0 ) );
		$this->assertSame( '#808080', Blueworx_Clubhouse_Color_Engine::mix( '#ffffff', '#000000', 0.5 ) );
	}
}
