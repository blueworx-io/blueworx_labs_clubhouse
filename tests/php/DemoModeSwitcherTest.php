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

	public function test_renders_one_control_per_look_for_everyone(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', null );
		$this->assertSame( 3, substr_count( $html, 'data-clubhouse-look="' ) );
		$this->assertStringContainsString( 'data-clubhouse-look="floodlight"', $html );
	}

	public function test_current_look_is_flagged(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'floodlight', null );
		$this->assertMatchesRegularExpression( '/data-clubhouse-look="floodlight"[^>]*aria-pressed="true"/', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-pressed="true"' ) );
	}

	public function test_no_deactivate_control_for_non_admin(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', null );
		$this->assertStringNotContainsString( 'clubhouse-demo__exit', $html );
	}

	public function test_deactivate_control_present_when_url_given(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', 'https://club.test/toggle' );
		$this->assertStringContainsString( 'class="clubhouse-demo__exit"', $html );
		$this->assertStringContainsString( 'href="https://club.test/toggle"', $html );
		$this->assertStringContainsString( 'Turn off demo mode', $html );
	}

	public function test_escapes_dynamic_text(): void {
		$looks = array( array( 'slug' => 'x"y', 'name' => '<b>Hack</b>' ) );
		$html  = Blueworx_Clubhouse_Demo_Mode::switcher_html( $looks, null, null );
		$this->assertStringNotContainsString( '<b>Hack</b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;Hack&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'x&quot;y', $html );
	}

	public function test_skin_agnostic_no_colour_literals(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', 'https://club.test/t' );
		$this->assertDoesNotMatchRegularExpression( '/(?<!&)#[0-9a-fA-F]{3,6}\b/', $html, 'switcher must not hardcode colours' );
		$this->assertStringNotContainsString( 'var(--color-accent', $html );
	}
}
