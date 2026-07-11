<?php
// tests/php/PageMapTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class PageMapTest extends TestCase {

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}
	private function visibility(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_home_slug_is_empty_string_and_first(): void {
		$pages = Blueworx_Clubhouse_Page_Map::pages();
		$this->assertSame( '', $pages[0]['slug'] );
		$this->assertSame( 'home', $pages[0]['method'] );
	}

	public function test_has_known_and_unknown(): void {
		$this->assertTrue( Blueworx_Clubhouse_Page_Map::has( '' ) );
		$this->assertTrue( Blueworx_Clubhouse_Page_Map::has( 'calendar' ) );
		$this->assertFalse( Blueworx_Clubhouse_Page_Map::has( 'nope' ) );
	}

	public function test_all_eight_page_slugs_present(): void {
		$slugs = array_column( Blueworx_Clubhouse_Page_Map::pages(), 'slug' );
		foreach ( array( '', 'about', 'membership', 'contact', 'login', 'sports', 'teams', 'events', 'calendar' ) as $slug ) {
			$this->assertContains( $slug, $slugs );
		}
	}

	public function test_render_dispatches_to_the_right_page(): void {
		// Calendar body carries the calendar-only hook; About carries benefits, not calendar.
		$cal = Blueworx_Clubhouse_Page_Map::render( 'calendar', $this->branding(), $this->visibility() );
		$this->assertStringContainsString( 'ch-cal', $cal );

		$about = Blueworx_Clubhouse_Page_Map::render( 'about', $this->branding(), $this->visibility() );
		$this->assertStringContainsString( 'ch-benefits', $about );
		$this->assertStringNotContainsString( 'ch-cal"', $about );
	}

	public function test_render_home_for_empty_slug(): void {
		$home = Blueworx_Clubhouse_Page_Map::render( '', $this->branding(), $this->visibility() );
		$this->assertStringContainsString( 'ch-cards', $home );
	}
}
