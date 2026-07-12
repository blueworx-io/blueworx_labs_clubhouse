<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoContentTest extends TestCase {

	public function test_sports_have_all_canonical_keys(): void {
		$sports = Blueworx_Clubhouse_Demo_Content::sports();
		$this->assertGreaterThanOrEqual( 6, count( $sports ) );
		foreach ( $sports as $s ) {
			foreach ( array( 'title', 'label', 'subtitle', 'description', 'stat1_value', 'stat1_label', 'stat2_value', 'stat2_label', 'image' ) as $k ) {
				$this->assertArrayHasKey( $k, $s );
			}
		}
		$this->assertSame( 'Rugby', $sports[0]['title'] );
	}

	public function test_fixtures_have_upcoming_and_played(): void {
		$fx = Blueworx_Clubhouse_Demo_Content::fixtures();
		$upcoming = array_filter( $fx, static fn( $f ) => '' === $f['outcome'] );
		$played   = array_filter( $fx, static fn( $f ) => '' !== $f['outcome'] );
		$this->assertGreaterThanOrEqual( 3, count( $upcoming ) );
		$this->assertGreaterThanOrEqual( 3, count( $played ) );
		foreach ( $fx as $f ) {
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $f['match_date'] );
			$this->assertContains( $f['outcome'], array( '', 'W', 'L', 'D' ) );
		}
	}

	public function test_people_have_committee_and_directory_members(): void {
		$people = Blueworx_Clubhouse_Demo_Content::people();
		$committee = array_filter( $people, static fn( $p ) => '' !== $p['committee_role'] );
		$directory = array_filter( $people, static fn( $p ) => '' !== $p['directory_role'] );
		$this->assertGreaterThanOrEqual( 6, count( $committee ) );
		$this->assertGreaterThanOrEqual( 6, count( $directory ) );
	}

	public function test_events_split_upcoming_past(): void {
		$events = Blueworx_Clubhouse_Demo_Content::events();
		$this->assertNotEmpty( array_filter( $events, static fn( $e ) => 'upcoming' === $e['status'] ) );
		$this->assertNotEmpty( array_filter( $events, static fn( $e ) => 'past' === $e['status'] ) );
	}
}
