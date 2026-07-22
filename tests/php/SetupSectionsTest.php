<?php
// tests/php/SetupSectionsTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupSectionsTest extends TestCase {

	private function inventory(): array {
		return Blueworx_Clubhouse_Setup_Sections::inventory();
	}

	public function test_covers_all_nine_pages_in_page_map_order(): void {
		$pages = array_map( static fn( $p ) => $p['page'], $this->inventory() );
		$this->assertSame(
			array( 'home', 'about', 'membership', 'contact', 'login', 'sports', 'teams', 'events', 'calendar' ),
			$pages
		);
	}

	public function test_home_sections_match_renderer_keys(): void {
		$home = array_values( array_filter( $this->inventory(), static fn( $p ) => 'home' === $p['page'] ) )[0];
		$keys = array_map( static fn( $s ) => $s['key'], $home['sections'] );
		$this->assertSame(
			array( 'header', 'hero', 'quick_tiles', 'ticker', 'stats', 'sports', 'clubhouse', 'membership', 'activity', 'news', 'info', 'sponsors', 'social', 'footer' ),
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

	public function test_total_section_count_is_46(): void {
		$total = array_sum( array_map( static fn( $p ) => count( $p['sections'] ), $this->inventory() ) );
		$this->assertSame( 46, $total );
	}
}
