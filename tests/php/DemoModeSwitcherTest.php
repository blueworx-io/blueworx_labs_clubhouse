<?php
// tests/php/DemoModeSwitcherTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoModeSwitcherTest extends TestCase {

	/** @return array<int,array{slug:string,name:string}> */
	private function looks(): array {
		return array(
			array( 'slug' => 'court-side', 'name' => 'Court Side' ),
			array( 'slug' => 'members-house', 'name' => "Members' House" ),
			array( 'slug' => 'floodlight', 'name' => 'Floodlight' ),
		);
	}

	public function test_renders_one_control_per_look(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side' );
		$this->assertSame( 3, substr_count( $html, 'data-clubhouse-look="' ) );
		$this->assertStringContainsString( 'data-clubhouse-look="floodlight"', $html );
	}

	public function test_current_look_is_flagged(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'floodlight' );
		// The current control carries is-current + aria-pressed="true".
		$this->assertMatchesRegularExpression(
			'/data-clubhouse-look="floodlight"[^>]*aria-pressed="true"/',
			$html
		);
		$this->assertSame( 1, substr_count( $html, 'aria-pressed="true"' ), 'exactly one current control' );
	}

	public function test_includes_exit_control(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side' );
		$this->assertStringContainsString( 'data-clubhouse-demo-exit', $html );
	}

	public function test_escapes_dynamic_text(): void {
		$looks = array( array( 'slug' => 'x"y', 'name' => '<b>Hack</b>' ) );
		$html  = Blueworx_Clubhouse_Demo_Mode::switcher_html( $looks, null );
		$this->assertStringNotContainsString( '<b>Hack</b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;Hack&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'x&quot;y', $html );
	}

	public function test_skin_agnostic_no_colour_literals(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side' );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html, 'switcher must not hardcode colours' );
		$this->assertStringNotContainsString( 'var(--color-accent', $html, 'chrome must not consume accent tokens' );
	}
}
