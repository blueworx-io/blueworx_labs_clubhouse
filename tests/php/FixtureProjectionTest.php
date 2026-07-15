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

	/**
	 * Results was removed in 0.26.0, but `outcome` was NOT: it is how a played
	 * fixture is told from an upcoming one, so home_fixtures() and the calendar
	 * both still depend on it. This pins that the projection no longer offers a
	 * results view while the outcome field keeps doing its other job.
	 */
	public function test_home_results_projection_is_gone(): void {
		$this->assertFalse(
			method_exists( Blueworx_Clubhouse_Fixture_Projection::class, 'home_results' ),
			'home_results() was removed with the Results tab'
		);
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

	/**
	 * The other half of the outcome contract: a played fixture must stay OUT of the
	 * upcoming list. Results is gone, but this behaviour is load-bearing for the
	 * Fixtures tab and is exactly what a careless removal of `outcome` would break.
	 */
	public function test_home_fixtures_exclude_played_matches(): void {
		$played   = array( 'sport' => 'Rugby', 'match_date' => '2026-05-01', 'kickoff_time' => '14:00', 'venue' => 'H', 'home' => 'A', 'away' => 'B', 'score' => '1-0', 'outcome' => 'W', 'result_summary' => 'Won' );
		$upcoming = array( 'sport' => 'Rugby', 'match_date' => '2026-05-02', 'kickoff_time' => '14:00', 'venue' => 'H', 'home' => 'C', 'away' => 'D', 'score' => '', 'outcome' => '', 'result_summary' => '' );
		$rows     = Blueworx_Clubhouse_Fixture_Projection::home_fixtures( array( $played, $upcoming ) );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'C vs D', $rows[0]['matchup'] );
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
