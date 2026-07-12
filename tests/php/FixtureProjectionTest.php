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
		$this->assertSame( array( 'July', 'June' ), $labels );
		$july = $months[0]['rows'];
		// An upcoming row uses "{venue} · {time}" detail and empty outcome.
		$up = array_values( array_filter( $july, static fn( $r ) => '' === $r['outcome'] ) )[0];
		$this->assertStringContainsString( '·', $up['detail'] );
		// A played row uses the result_summary as detail.
		$won = array_values( array_filter( $july, static fn( $r ) => 'W' === $r['outcome'] ) )[0];
		$this->assertSame( 'Won by 34 runs', $won['detail'] );
	}
}
