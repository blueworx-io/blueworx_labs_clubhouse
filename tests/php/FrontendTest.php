<?php
// tests/php/FrontendTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class FrontendTest extends TestCase {

	public function test_resolve_slug_front_page_is_home(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::resolve_slug( true, null ) );
	}

	public function test_resolve_slug_known_query_var(): void {
		$this->assertSame( 'about', Blueworx_Clubhouse_Frontend::resolve_slug( false, 'about' ) );
	}

	public function test_resolve_slug_unknown_is_null(): void {
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( false, 'nope' ) );
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( false, '' ) );
	}

	public function test_enqueue_specs_shape(): void {
		$look  = new Blueworx_Clubhouse_Court_Side();
		$specs = Blueworx_Clubhouse_Frontend::enqueue_specs( $look, ':root{--x:1}', 'https://club.test/wp-content/plugins/clubhouse/' );

		$this->assertStringContainsString( 'fonts.googleapis.com', $specs['fonts_url'] );
		$this->assertSame( 'https://club.test/wp-content/plugins/clubhouse/assets/looks/court-side.css', $specs['stylesheet_url'] );
		$this->assertSame( ':root{--x:1}', $specs['inline_css'] );
		$this->assertSame( 'https://club.test/wp-content/plugins/clubhouse/assets/js/reveal.js', $specs['reveal_url'] );
	}
}
