<?php
// tests/php/ThemeCacheTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ThemeCacheTest extends TestCase {

	private function look(): Blueworx_Clubhouse_Base_Look {
		return new Blueworx_Clubhouse_Court_Side();
	}

	public function test_root_css_matches_pure_compose(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$expected = Blueworx_Clubhouse_Theme_Css::to_css(
			Blueworx_Clubhouse_Theme_Css::compose( $this->look(), $branding )
		);
		$this->assertSame( $expected, $cache->root_css( $this->look(), $branding ) );
	}

	public function test_second_read_uses_cached_string(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$cache->root_css( $this->look(), $branding );
		// Corrupt the stored CSS; a cache hit must return the corrupted value (proves no recompute).
		$storage->set( 'root_css', ':root{--sentinel:1}' );
		$this->assertSame( ':root{--sentinel:1}', $cache->root_css( $this->look(), $branding ) );
	}

	public function test_accent_change_recomputes(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$first = $cache->root_css( $this->look(), $branding );
		$branding->set_accent( '#ff5b23' );
		$second = $cache->root_css( $this->look(), $branding );
		$this->assertNotSame( $first, $second );
	}

	public function test_invalidate_clears_cache(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$cache->root_css( $this->look(), $branding );
		$cache->invalidate();
		$this->assertSame( '', $storage->get( 'root_css', '' ) );
		$this->assertSame( '', $storage->get( 'root_css_sig', '' ) );
	}

	public function test_signature_changes_when_look_tokens_change(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$css_a = $cache->root_css( new Clubhouse_Test_Look( array( '--color-bg' => '#ffffff', '--color-ink' => '#000000' ) ), $branding );
		// Same slug + accent, but the look now emits a different bg token.
		$css_b = $cache->root_css( new Clubhouse_Test_Look( array( '--color-bg' => '#111111', '--color-ink' => '#eeeeee' ) ), $branding );

		$this->assertNotSame( $css_a, $css_b, 'A token change must bust the cache even with the same slug + accent.' );
	}

	public function test_signature_stable_for_identical_look_and_accent(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$first = $cache->root_css( new Blueworx_Clubhouse_Court_Side(), $branding );
		// Corrupt the stored CSS; an unchanged signature must return the cached (corrupt) value.
		$storage->set( 'root_css', ':root{--sentinel:1}' );
		$this->assertSame( ':root{--sentinel:1}', $cache->root_css( new Blueworx_Clubhouse_Court_Side(), $branding ) );
	}
}

/** Minimal Base Look whose token set can be varied per test. */
final class Clubhouse_Test_Look implements Blueworx_Clubhouse_Base_Look {
	/** @param array<string,string> $tokens */
	public function __construct( private array $tokens ) {}
	public function slug(): string { return 'test-look'; }
	public function name(): string { return 'Test Look'; }
	public function description(): string { return 'Fixture look for cache-signature tests.'; }
	public function tokens(): array { return $this->tokens; }
	public function fonts(): array { return array(); }
	public function stylesheet(): string { return 'assets/looks/test-look.css'; }
}
