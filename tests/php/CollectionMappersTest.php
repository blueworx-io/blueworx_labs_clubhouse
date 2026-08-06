<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CollectionMappersTest extends TestCase {

	public function test_sport_mapper_fills_canonical_shape(): void {
		$raw = array(
			'title' => 'Rugby',
			'meta'  => array(
				'label' => 'Sat', 'subtitle' => 'Senior · colts', 'description' => 'Rugby desc',
				'stat1_value' => '4', 'stat1_label' => 'Teams', 'stat2_value' => '120', 'stat2_label' => 'Players', 'image' => '',
			),
		);
		$c = Blueworx_Clubhouse_Collection_Mappers::sport( $raw );
		$this->assertSame( 'Rugby', $c['title'] );
		$this->assertSame( 'Sat', $c['label'] );
		$this->assertSame( '120', $c['stat2_value'] );
	}

	public function test_person_mapper_defaults_missing_meta_to_empty(): void {
		$c = Blueworx_Clubhouse_Collection_Mappers::person( array( 'title' => 'Priya Nair', 'meta' => array() ) );
		$this->assertSame( 'Priya Nair', $c['name'] );
		$this->assertSame( '', $c['committee_role'] );
		$this->assertSame( '', $c['directory_role'] );
		$this->assertSame( '', $c['email'] );
	}

	public function test_fixture_mapper_maps_outcome_and_dates(): void {
		$c = Blueworx_Clubhouse_Collection_Mappers::fixture( array(
			'title' => 'Rugby vs Riverside',
			'meta'  => array( 'sport' => 'Rugby · 1st XV', 'match_date' => '2026-07-12', 'kickoff_time' => '14:00', 'venue' => 'Home', 'home_team' => 'ClubHouse', 'away_team' => 'Riverside RFC', 'score' => '', 'outcome' => '', 'result_summary' => '' ),
		) );
		$this->assertSame( 'ClubHouse', $c['home'] );
		$this->assertSame( 'Riverside RFC', $c['away'] );
		$this->assertSame( '', $c['outcome'] );
	}
/**
	 * The bug: a club open day sat under "Upcoming events" nine days after it
	 * happened, because status was a field somebody had to remember to change.
	 * A date that has passed now retires an event on its own.
	 */
	public function test_an_event_whose_date_has_passed_is_past(): void {
		$this->assertSame(
			'past',
			Blueworx_Clubhouse_Collection_Mappers::event_status( 'upcoming', '2026-07-26', '2026-08-04' )
		);
	}

	public function test_an_event_still_to_come_is_left_upcoming(): void {
		$this->assertSame(
			'upcoming',
			Blueworx_Clubhouse_Collection_Mappers::event_status( 'upcoming', '2026-08-30', '2026-08-04' )
		);
		// Today is not over yet.
		$this->assertSame(
			'upcoming',
			Blueworx_Clubhouse_Collection_Mappers::event_status( 'upcoming', '2026-08-04', '2026-08-04' )
		);
	}

	/**
	 * The override runs one way only. A club can mark something past early, and
	 * that sticks; nothing can force a finished event back to upcoming.
	 */
	public function test_a_club_can_retire_an_event_early_but_not_revive_one(): void {
		$this->assertSame(
			'past',
			Blueworx_Clubhouse_Collection_Mappers::event_status( 'past', '2026-08-30', '2026-08-04' )
		);
	}

	/** An undated or recurring event behaves exactly as it did before. */
	public function test_an_event_without_a_usable_date_keeps_its_stored_status(): void {
		foreach ( array( '', '   ', 'Sat 26 Jul', '26/07/2026' ) as $date ) {
			$this->assertSame(
				'upcoming',
				Blueworx_Clubhouse_Collection_Mappers::event_status( 'upcoming', $date, '2026-08-04' ),
				$date
			);
		}
	}
}
