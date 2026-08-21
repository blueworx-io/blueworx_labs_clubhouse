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
	private function collections(): Blueworx_Clubhouse_Demo_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	public function test_home_slug_is_empty_string_and_first(): void {
		$pages = Blueworx_Clubhouse_Page_Map::pages();
		$this->assertSame( '', $pages[0]['slug'] );
		$this->assertSame( 'home', $pages[0]['method'] );
	}

	/** Labels back the per-page document title. */
	public function test_label_for_known_and_unknown_slugs(): void {
		$this->assertSame( 'Home', Blueworx_Clubhouse_Page_Map::label( '' ) );
		$this->assertSame( 'Membership', Blueworx_Clubhouse_Page_Map::label( 'membership' ) );
		$this->assertSame( 'Calendar', Blueworx_Clubhouse_Page_Map::label( 'calendar' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Page_Map::label( 'nope' ) );
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
		$cal = Blueworx_Clubhouse_Page_Map::render( 'calendar', $this->branding(), $this->visibility(), $this->collections() );
		$this->assertStringContainsString( 'ch-cal', $cal );

		$about = Blueworx_Clubhouse_Page_Map::render( 'about', $this->branding(), $this->visibility(), $this->collections() );
		$this->assertStringContainsString( 'ch-benefits', $about );
		$this->assertStringNotContainsString( 'ch-cal"', $about );
	}

	public function test_render_home_for_empty_slug(): void {
		$home = Blueworx_Clubhouse_Page_Map::render( '', $this->branding(), $this->visibility(), $this->collections() );
		$this->assertStringContainsString( 'ch-cards', $home );
	}

	public function test_the_member_area_is_a_page_this_plugin_serves(): void {
		$entry = null;
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			if ( 'member-dashboard' === $page['slug'] ) {
				$entry = $page;
			}
		}
		$this->assertNotNull( $entry, 'member-dashboard must be in the page map' );
		$this->assertSame( 'Member area', $entry['label'] );
		$this->assertSame( 'member_dashboard', $entry['method'] );
	}

	/** Members-only pages are kept out of the SEO report; club pages are not. */
	public function test_private_pages_are_marked_as_such(): void {
		$this->assertTrue( Blueworx_Clubhouse_Page_Map::is_private( 'member-dashboard' ) );
		$this->assertFalse( Blueworx_Clubhouse_Page_Map::is_private( 'about' ) );
		$this->assertFalse( Blueworx_Clubhouse_Page_Map::is_private( 'nope' ) );
	}

	/** The one page that renders no club chrome — it is a BlueWorx admin screen. */
	public function test_member_area_renders_its_own_frame_and_no_club_chrome(): void {
		$html = Blueworx_Clubhouse_Page_Map::render(
			'member-dashboard',
			$this->branding(),
			$this->visibility(),
			$this->collections()
		);
		$this->assertStringContainsString( 'bw-admin', $html );
		$this->assertStringContainsString( 'clubhouse-member', $html );
		$this->assertStringNotContainsString( 'ch-nav', $html );
		$this->assertStringNotContainsString( 'ch-footer', $html );
	}

	/**
	 * What the SEO report is meant to walk: every servable page minus the
	 * members-only one. Stands in for exercising Seo_Controller::build_model()
	 * itself, which needs a WordPress runtime this harness does not provide
	 * (get_bloginfo() and friends) — the controller's own guard is covered only
	 * by inspection.
	 */
	public function test_available_pages_filtered_by_private_excludes_only_the_member_area(): void {
		$public = array_values( array_filter(
			Blueworx_Clubhouse_Page_Map::available(),
			static fn( array $page ): bool => ! Blueworx_Clubhouse_Page_Map::is_private( $page['slug'] )
		) );

		$slugs = array_column( $public, 'slug' );
		$this->assertNotContains( 'member-dashboard', $slugs );
		$this->assertCount( count( Blueworx_Clubhouse_Page_Map::available() ) - 1, $public );
	}
}
