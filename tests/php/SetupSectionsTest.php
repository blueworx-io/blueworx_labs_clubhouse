<?php
// tests/php/SetupSectionsTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupSectionsTest extends TestCase {

	protected function tearDown(): void {
		Blueworx_Clubhouse_Integrations::set_detector( null );
	}

	private function inventory(): array {
		return Blueworx_Clubhouse_Setup_Sections::inventory();
	}

	/** Declares LatePoint live, so the Booking page is on offer. */
	private function withLatePoint(): void {
		Blueworx_Clubhouse_Integrations::set_detector(
			static fn( string $tag ): bool => Blueworx_Clubhouse_Integrations::LATEPOINT_TAG === $tag
		);
	}

	public function test_covers_the_always_available_pages_in_page_map_order(): void {
		$pages = array_map( static fn( $p ) => $p['page'], $this->inventory() );
		$this->assertSame(
			array( 'home', 'about', 'membership', 'contact', 'login', 'news', 'sports', 'teams', 'events', 'calendar', 'privacy', 'terms' ),
			$pages
		);
	}

	/**
	 * The Booking page is LatePoint's. Without it there are no toggles for it at
	 * all — an owner is not shown switches for sections that cannot render.
	 */
	public function test_booking_is_absent_without_latepoint_and_present_with_it(): void {
		$this->assertNotContains( 'booking', array_map( static fn( $p ) => $p['page'], $this->inventory() ) );

		$this->withLatePoint();
		$pages = array_map( static fn( $p ) => $p['page'], $this->inventory() );
		$this->assertContains( 'booking', $pages );
		$this->assertSame( 'calendar', $pages[ array_search( 'booking', $pages, true ) - 1 ], 'booking follows calendar' );
	}

	public function test_booking_sections_match_the_renderer_keys(): void {
		$this->withLatePoint();
		$booking = array_values( array_filter( $this->inventory(), static fn( $p ) => 'booking' === $p['page'] ) )[0];
		$this->assertSame(
			array( 'hero', 'services', 'locations', 'agents' ),
			array_map( static fn( $s ) => $s['key'], $booking['sections'] )
		);
	}

	public function test_home_sections_match_renderer_keys(): void {
		$home = array_values( array_filter( $this->inventory(), static fn( $p ) => 'home' === $p['page'] ) )[0];
		$keys = array_map( static fn( $s ) => $s['key'], $home['sections'] );
		$this->assertSame(
			array( 'cookies', 'header', 'hero', 'quick_tiles', 'ticker', 'sports', 'clubhouse', 'membership', 'activity', 'news', 'info', 'sponsors', 'social', 'footer', 'welcome' ),
			$keys
		);
	}

	public function test_every_section_has_a_nonempty_label(): void {
		foreach ( $this->inventory() as $page ) {
			$this->assertNotSame( '', $page['label'] );
			foreach ( $page['sections'] as $section ) {
				$this->assertNotSame( '', $section['label'], "empty label for {$page['page']}.{$section['key']}" );
			}
		}
	}

	/** 54 since Privacy and Terms added two each, the cookie notice one and the welcome pack one. Booking adds 5 more. */
	public function test_total_section_count_is_54_without_latepoint_and_59_with_it(): void {
		$count = fn(): int => array_sum( array_map( static fn( $p ) => count( $p['sections'] ), $this->inventory() ) );
		$this->assertSame( 54, $count() );

		$this->withLatePoint();
		$this->assertSame( 59, $count() );
	}
}
