<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class FixtureProjectionTest extends TestCase {

	private function fx(): array {
		return Blueworx_Clubhouse_Demo_Content::fixtures();
	}

	public function test_home_fixtures_are_upcoming_only_with_split_date(): void {
		$rows = Blueworx_Clubhouse_Fixture_Projection::home_fixtures( $this->fx() );
		$this->assertCount( 3, $rows );
		$this->assertSame( array( 'month', 'day', 'competition', 'time', 'matchup' ), array_keys( $rows[0] ) );
		$this->assertSame( 'JUL', $rows[0]['month'] );
		$this->assertSame( '12', $rows[0]['day'] );
		$this->assertSame( 'ClubHouse vs Riverside RFC', $rows[0]['matchup'] );
	}

	public function test_home_results_are_played_only_desc(): void {
		$rows = Blueworx_Clubhouse_Fixture_Projection::home_results( $this->fx() );
		$this->assertCount( 3, $rows );
		$this->assertSame( array( 'date', 'home', 'away', 'score', 'outcome' ), array_keys( $rows[0] ) );
		// Most recent played first: Cricket 2026-07-05.
		$this->assertSame( 'JUL 5', $rows[0]['date'] );
		$this->assertContains( $rows[0]['outcome'], array( 'W', 'L', 'D' ) );
	}

	public function test_calendar_groups_by_month_with_detail(): void {
		$months = Blueworx_Clubhouse_Fixture_Projection::calendar_months( $this->fx() );
		$labels = array_column( $months, 'label' );
		$this->assertSame( array( 'July 2026', 'June 2026' ), $labels );
		$july = $months[0]['rows'];
		// An upcoming row uses "{venue} · {time}" detail and empty outcome.
		$up = array_values( array_filter( $july, static fn( $r ) => '' === $r['outcome'] ) )[0];
		$this->assertStringContainsString( '·', $up['detail'] );
		// A played row uses the result_summary as detail.
		$won = array_values( array_filter( $july, static fn( $r ) => 'W' === $r['outcome'] ) )[0];
		$this->assertSame( 'Won by 34 runs', $won['detail'] );
	}

	public function test_calendar_groups_by_year_and_month_across_years(): void {
		$fixtures = array(
			$this->fixture( '2026-01-10' ),
			$this->fixture( '2025-01-20' ),
			$this->fixture( '2025-12-05' ),
		);
		$labels = array_column( Blueworx_Clubhouse_Fixture_Projection::calendar_months( $fixtures ), 'label' );
		// Newest-first, and 2025 January stays separate from 2026 January.
		$this->assertSame( array( 'January 2026', 'December 2025', 'January 2025' ), $labels );
	}

	public function test_malformed_dates_do_not_resolve_to_now(): void {
		$fixtures = array( $this->fixture( '' ), $this->fixture( 'garbage' ), $this->fixture( '2026-05-01' ) );

		// Undated fixtures are omitted from the date-ranked Home tabs.
		$upcoming = Blueworx_Clubhouse_Fixture_Projection::home_fixtures( $fixtures, 10 );
		$this->assertCount( 1, $upcoming );
		$this->assertSame( 'MAY', $upcoming[0]['month'] );

		// The calendar surfaces undated fixtures under a "Date TBC" bucket, ordered last.
		$labels = array_column( Blueworx_Clubhouse_Fixture_Projection::calendar_months( $fixtures ), 'label' );
		$this->assertSame( array( 'May 2026', 'Date TBC' ), $labels );
		$tbc = Blueworx_Clubhouse_Fixture_Projection::calendar_months( $fixtures )[1]['rows'];
		$this->assertSame( 'TBC', $tbc[0]['date'] );
	}

	/** @return array<string,mixed> A minimal upcoming fixture with the given match_date. */
	private function fixture( string $match_date ): array {
		return array(
			'sport' => 'Rugby', 'match_date' => $match_date, 'kickoff_time' => '14:00',
			'venue' => 'Home', 'home' => 'ClubHouse', 'away' => 'Rivals',
			'score' => '', 'outcome' => '', 'result_summary' => '',
		);
	}
}
