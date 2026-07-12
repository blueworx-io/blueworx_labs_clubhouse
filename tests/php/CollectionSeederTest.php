<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CollectionSeederTest extends TestCase {
	protected function setUp(): void { wp_stub_reset(); }

	public function test_seeds_when_empty(): void {
		Blueworx_Clubhouse_Collection_Seeder::seed();
		$inserts = wp_stub_calls( 'wp_insert_post' );
		// 6 sports + 4 teams + 7 fixtures + 7 events + 6 sponsors + 7 people = 37.
		$this->assertSame( 37, count( $inserts ) );
	}

	public function test_skips_when_already_populated(): void {
		$GLOBALS['wp_stub_posts']['clubhouse_sport'] = array( (object) array( 'ID' => 1 ) );
		Blueworx_Clubhouse_Collection_Seeder::seed();
		$sportInserts = array_filter(
			wp_stub_calls( 'wp_insert_post' ),
			static fn( $c ) => ( $c['args'][0]['post_type'] ?? '' ) === 'clubhouse_sport'
		);
		$this->assertCount( 0, $sportInserts ); // sports skipped because already populated
	}
}
