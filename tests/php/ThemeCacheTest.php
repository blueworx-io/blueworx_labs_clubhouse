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
}
