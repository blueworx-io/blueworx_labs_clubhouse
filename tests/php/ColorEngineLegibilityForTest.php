<?php
// tests/php/ColorEngineLegibilityForTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ColorEngineLegibilityForTest extends TestCase {

	public function test_text_bearing_look_requires_ink_contrast(): void {
		// #7a7a7a is a mid-luminance grey: on Court Side both candidate inks
		// (near-black shell ink and white) fall just under AA on the fill, so the
		// accent cannot carry legible text -> rejected for a text-bearing look.
		$this->assertFalse(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( new Blueworx_Clubhouse_Court_Side(), '#7a7a7a' )
		);
		// A saturated accent is fine on Court Side.
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( new Blueworx_Clubhouse_Court_Side(), '#7a2f3a' )
		);
	}

	public function test_glow_only_look_accepts_bright_accent(): void {
		// Floodlight never paints text on the accent, so a bright accent that would
		// fail the ink check is still acceptable (accent-deep is AA-guaranteed).
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( new Blueworx_Clubhouse_Floodlight(), '#c6f24e' )
		);
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( new Blueworx_Clubhouse_Floodlight(), '#f7a70a' )
		);
	}

	public function test_accent_bears_text_flags_match_stylesheets(): void {
		$this->assertTrue( ( new Blueworx_Clubhouse_Court_Side() )->accent_bears_text() );
		$this->assertTrue( ( new Blueworx_Clubhouse_Members_House() )->accent_bears_text() );
		$this->assertFalse( ( new Blueworx_Clubhouse_Floodlight() )->accent_bears_text() );
	}

	public function test_accent_deep_is_legible_true_on_any_shell(): void {
		// derive() guarantees accent-deep clears AA on any shell.
		$this->assertTrue( Blueworx_Clubhouse_Color_Engine::accent_deep_is_legible( '#c6f24e', '#14110b' ) );
		$this->assertTrue( Blueworx_Clubhouse_Color_Engine::accent_deep_is_legible( '#7a7a7a', '#faf8f3' ) );
	}
}
