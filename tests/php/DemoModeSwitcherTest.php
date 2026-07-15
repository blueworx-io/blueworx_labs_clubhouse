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

	public function test_renders_one_swatch_per_accent(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', null );
		$this->assertSame( 5, substr_count( $html, 'data-clubhouse-accent="' ) );
		$this->assertStringContainsString( 'data-clubhouse-accent="berry"', $html );
		$this->assertStringContainsString( 'aria-label="Accent: Volt Lime"', $html );
	}

	/**
	 * The swatch markup carries no colour: demo.css is neutral tooling chrome and
	 * test_skin_agnostic_no_colour_literals pins that. demo.js paints each swatch
	 * from window.clubhouseDemoPalettes instead.
	 */
	public function test_swatches_carry_no_colour(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', null );
		$this->assertDoesNotMatchRegularExpression( '/(?<!&)#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	/**
	 * Swatches ship unpressed and the client flags the chosen one. The server cannot
	 * do it: the accent cookie deliberately never reaches PHP, and this markup is
	 * shared by every viewer — baking one viewer's choice in would be wrong the
	 * moment a page cache served it to someone else. The look controls differ because
	 * their cookie IS read server-side (the look switch reloads).
	 */
	public function test_swatches_start_unpressed_for_the_client_to_flag(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', null );
		$this->assertSame( 5, preg_match_all( '/data-clubhouse-accent="[^"]*"[^>]*aria-pressed="false"/', $html ) );
	}

	public function test_head_script_publishes_the_applied_accent_slug(): void {
		$js = Blueworx_Clubhouse_Demo_Mode::head_script(
			Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Court_Side() )
		);
		$this->assertStringContainsString( 'window.clubhouseDemoAccent', $js, 'demo.js needs the applied slug to flag the swatch without re-parsing the cookie' );
	}

	public function test_head_script_defines_the_palettes_and_reads_the_cookie(): void {
		$js = Blueworx_Clubhouse_Demo_Mode::head_script(
			Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Court_Side() )
		);
		$this->assertStringContainsString( 'window.clubhouseDemoPalettes', $js );
		$this->assertStringContainsString( 'clubhouse_demo_accent', $js );
		$this->assertStringContainsString( '#c6f24e', $js, 'palettes must reach the client' );
		$this->assertStringNotContainsString( '</script>', $js, 'must not be able to break out of its script tag' );
	}

	/** The JSON must be embeddable in an inline script without breaking out of it. */
	public function test_head_script_json_is_tag_safe(): void {
		$js = Blueworx_Clubhouse_Demo_Mode::head_script(
			Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Floodlight() )
		);
		$this->assertStringNotContainsString( '<\/script', $js );
		$this->assertStringNotContainsString( '<script', $js );
	}
}
