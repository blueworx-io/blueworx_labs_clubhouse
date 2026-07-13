<?php
// tests/php/ColorEngineLegibilityTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ColorEngineLegibilityTest extends TestCase {

	// Court Side shell (light): warm near-white bg, near-black ink.
	private const CS_BG  = '#faf8f3';
	private const CS_INK = '#1c1b18';

	// Floodlight shell (dark): warm-ink canvas, bone ink (both light).
	private const FL_BG  = '#14110b';
	private const FL_INK = '#f3ede0';

	public function test_saturated_accent_is_legible_on_light_shell(): void {
		// Volt Lime: dark ink wins on the light lime fill; deep is AA-guaranteed.
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( '#c6f24e', self::CS_BG, self::CS_INK )
		);
	}

	public function test_dark_accent_is_legible_on_light_shell(): void {
		// Claret: white ink wins on the dark fill.
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( '#7a2f3a', self::CS_BG, self::CS_INK )
		);
	}

	public function test_light_accent_is_illegible_on_dark_shell(): void {
		// On Floodlight both candidate inks (bone + white) are light, so a light
		// accent fill cannot carry legible text: accent-ink fails AA -> not legible.
		$this->assertFalse(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( '#c6f24e', self::FL_BG, self::FL_INK )
		);
	}

	public function test_normalizes_input_hex_forms(): void {
		// Accepts shorthand / missing-hash input the same way derive() does.
		$this->assertSame(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( '#c6f24e', self::CS_BG, self::CS_INK ),
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( 'c6f24e', self::CS_BG, self::CS_INK )
		);
	}
}
