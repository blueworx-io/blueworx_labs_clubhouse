<?php
// tests/php/DemoModeTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoModeTest extends TestCase {

	public function test_look_cookie_constant(): void {
		$this->assertSame( 'clubhouse_demo_look', Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK );
	}

	public function test_resolve_returns_null_when_demo_off(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( false, 'floodlight', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_returns_null_without_look_cookie(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, null, array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_returns_known_slug_when_on(): void {
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, 'floodlight', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_unknown_slug_falls_through(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, 'retired', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_accent_cookie_constant(): void {
		$this->assertSame( 'clubhouse_demo_accent', Blueworx_Clubhouse_Demo_Mode::COOKIE_ACCENT );
	}

	public function test_palettes_cover_the_five_swatches_in_order(): void {
		$p = Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Court_Side() );
		$this->assertSame(
			array( 'volt-lime', 'signal-orange', 'court-teal', 'cobalt', 'berry' ),
			array_keys( $p )
		);
		$this->assertSame( 'Volt Lime', $p['volt-lime']['name'] );
		$this->assertSame( '#c6f24e', $p['volt-lime']['hex'] );
	}

	/** Every palette carries the full derived token set the client will apply. */
	public function test_each_palette_carries_all_engine_tokens(): void {
		$p = Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Court_Side() );
		foreach ( $p as $slug => $entry ) {
			$this->assertSame(
				array( '--color-accent', '--color-accent-ink', '--color-accent-deep', '--color-accent-wash', '--color-accent-block' ),
				array_keys( $entry['tokens'] ),
				"palette {$slug} is missing engine tokens"
			);
		}
	}

	/** Tokens come from the real engine, for THIS look's shell. */
	public function test_palettes_match_the_engine_for_the_given_look(): void {
		$look = new Blueworx_Clubhouse_Court_Side();
		$t    = $look->tokens();
		$this->assertSame(
			Blueworx_Clubhouse_Color_Engine::derive( '#c6f24e', $t['--color-bg'], $t['--color-ink'] ),
			Blueworx_Clubhouse_Demo_Mode::palettes( $look )['volt-lime']['tokens']
		);
	}

	/**
	 * The contract that makes storing a SLUG (not a hex) the right call: the same
	 * swatch derives different tokens per look, because each look has its own shell.
	 * Court Side is light, Floodlight dark — so lime resolves to a dark olive block
	 * on one and a pale cream block on the other.
	 */
	public function test_the_same_swatch_derives_differently_per_look(): void {
		$light = Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Court_Side() )['volt-lime']['tokens'];
		$dark  = Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Floodlight() )['volt-lime']['tokens'];
		$this->assertSame( '#c6f24e', $light['--color-accent'], 'the raw accent is the same' );
		$this->assertSame( '#c6f24e', $dark['--color-accent'] );
		$this->assertNotSame(
			$light['--color-accent-block'],
			$dark['--color-accent-block'],
			'derived tokens MUST differ per shell — this is why the cookie stores a slug'
		);
	}

	/**
	 * A clubhouse page must bypass the page cache ONLY while demo mode is on, so
	 * each visitor's look-cookie forces a fresh render on the switcher's reload. Off
	 * a clubhouse page, or with demo off, normal caching stands.
	 */
	public function test_should_bypass_cache_only_when_on_and_on_a_clubhouse_page(): void {
		$this->assertTrue( Blueworx_Clubhouse_Demo_Mode::should_bypass_cache( true, true ) );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::should_bypass_cache( true, false ) );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::should_bypass_cache( false, true ) );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::should_bypass_cache( false, false ) );
	}
}
