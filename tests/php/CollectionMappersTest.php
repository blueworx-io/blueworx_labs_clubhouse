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
}
