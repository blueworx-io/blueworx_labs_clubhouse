<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of the ClubHouse demo content, as canonical collection arrays.
 * Both the seeder (writes these as CPT posts) and Demo_Collections (serves them
 * directly to the preview/tests) read from here, so demo data lives in one place.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Content {

	/** @return array<int,array<string,mixed>> */
	public static function sports(): array {
		return array(
			array( 'title' => 'Rugby', 'label' => 'Sat', 'subtitle' => 'Senior · colts · touch',
				'description' => 'Senior, colts and touch rugby, from minis upward.',
				'stat1_value' => '4', 'stat1_label' => 'Teams', 'stat2_value' => '120', 'stat2_label' => 'Players', 'image' => '' ),
			array( 'title' => 'Tennis', 'label' => 'Daily', 'subtitle' => 'Four courts · coaching',
				'description' => 'Four courts with coaching for every age.',
				'stat1_value' => '4', 'stat1_label' => 'Courts', 'stat2_value' => '90', 'stat2_label' => 'Members', 'image' => '' ),
			array( 'title' => 'Cricket', 'label' => 'Summer', 'subtitle' => 'Youth → senior league',
				'description' => 'Youth to senior league cricket on the square.',
				'stat1_value' => '3', 'stat1_label' => 'Teams', 'stat2_value' => '80', 'stat2_label' => 'Players', 'image' => '' ),
			array( 'title' => 'Football', 'label' => 'Sun', 'subtitle' => 'Juniors · ages 5–16',
				'description' => 'Junior football for ages 5 to 16.',
				'stat1_value' => '6', 'stat1_label' => 'Teams', 'stat2_value' => '140', 'stat2_label' => 'Players', 'image' => '' ),
			array( 'title' => 'Hockey', 'label' => 'Sat', 'subtitle' => 'Ladies · mixed',
				'description' => 'Ladies and mixed hockey, league affiliated.',
				'stat1_value' => '3', 'stat1_label' => 'Teams', 'stat2_value' => '60', 'stat2_label' => 'Players', 'image' => '' ),
			array( 'title' => 'Netball', 'label' => 'Wed', 'subtitle' => 'Back-to-netball · squads',
				'description' => 'Back-to-netball through to divisional squads.',
				'stat1_value' => '2', 'stat1_label' => 'Teams', 'stat2_value' => '40', 'stat2_label' => 'Players', 'image' => '' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function teams(): array {
		return array(
			array( 'title' => '1st XV', 'sport' => 'Rugby', 'description' => 'Saturday league rugby, Division 3 South.', 'match_day' => 'Sat', 'league' => 'Div 3', 'image' => '' ),
			array( 'title' => '1st XI', 'sport' => 'Cricket', 'description' => 'Premier division Saturday cricket.', 'match_day' => 'Sat', 'league' => 'Prem', 'image' => '' ),
			array( 'title' => 'Ladies 1s', 'sport' => 'Hockey', 'description' => 'County league hockey with a strong colts feed.', 'match_day' => 'Sat', 'league' => 'County', 'image' => '' ),
			array( 'title' => 'Netball 2s', 'sport' => 'Netball', 'description' => 'Wednesday-night divisional netball.', 'match_day' => 'Wed', 'league' => 'Div 2', 'image' => '' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function fixtures(): array {
		return array(
			array( 'sport' => 'Rugby · 1st XV', 'match_date' => '2026-07-12', 'kickoff_time' => '14:00', 'venue' => 'Home', 'home' => 'ClubHouse', 'away' => 'Riverside RFC', 'score' => '', 'outcome' => '', 'result_summary' => '' ),
			array( 'sport' => 'Netball · Div 2', 'match_date' => '2026-07-13', 'kickoff_time' => '11:00', 'venue' => 'Away', 'home' => 'ClubHouse', 'away' => 'Castlebridge', 'score' => '', 'outcome' => '', 'result_summary' => '' ),
			array( 'sport' => 'Hockey · Ladies 1s', 'match_date' => '2026-07-19', 'kickoff_time' => '15:30', 'venue' => 'Home', 'home' => 'ClubHouse', 'away' => 'Elmwood', 'score' => '', 'outcome' => '', 'result_summary' => '' ),
			array( 'sport' => 'Cricket · 1st XI', 'match_date' => '2026-07-05', 'kickoff_time' => '11:00', 'venue' => 'Home', 'home' => 'ClubHouse 1st XI', 'away' => 'Hartfield CC', 'score' => '+34', 'outcome' => 'W', 'result_summary' => 'Won by 34 runs' ),
			array( 'sport' => 'Tennis · Singles', 'match_date' => '2026-07-02', 'kickoff_time' => '18:00', 'venue' => 'Home', 'home' => 'J. Patel', 'away' => 'R. Osei', 'score' => '2–0', 'outcome' => 'W', 'result_summary' => 'Won 2–0' ),
			array( 'sport' => 'Rugby · 2nd XV', 'match_date' => '2026-06-28', 'kickoff_time' => '14:00', 'venue' => 'Away', 'home' => 'ClubHouse 2nd XV', 'away' => 'Dunmore', 'score' => '18–24', 'outcome' => 'L', 'result_summary' => 'Lost 18–24' ),
			array( 'sport' => 'Hockey · Mixed', 'match_date' => '2026-06-21', 'kickoff_time' => '13:00', 'venue' => 'Home', 'home' => 'ClubHouse Mixed', 'away' => 'Elmwood', 'score' => '2–2', 'outcome' => 'D', 'result_summary' => 'Drew 2–2' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function events(): array {
		return array(
			array( 'title' => 'Club Open Day', 'tag' => 'Open day', 'date' => 'Sat 26 Jul', 'detail' => '10:00–14:00 · Clubhouse & grounds — all welcome.', 'cta_label' => 'Register interest', 'cta_href' => '?page=contact', 'status' => 'upcoming' ),
			array( 'title' => 'Summer Football Camp', 'tag' => 'Junior football', 'date' => '4–8 Aug', 'detail' => 'Ages 5–12 · a week of coaching and games.', 'cta_label' => 'Book a place', 'cta_href' => '?page=contact', 'status' => 'upcoming' ),
			array( 'title' => 'Annual Awards Night', 'tag' => 'Social', 'date' => 'Fri 12 Sep', 'detail' => '19:00 · Clubhouse function room.', 'cta_label' => '', 'cta_href' => '', 'status' => 'upcoming' ),
			array( 'title' => 'Summer BBQ & Family Day', 'tag' => 'Social', 'date' => 'Jun 2026', 'detail' => '', 'cta_label' => '', 'cta_href' => '', 'status' => 'past' ),
			array( 'title' => 'Spring Sevens Rugby Festival', 'tag' => 'Tournament', 'date' => 'May 2026', 'detail' => '', 'cta_label' => '', 'cta_href' => '', 'status' => 'past' ),
			array( 'title' => 'Annual General Meeting', 'tag' => 'Club', 'date' => 'Apr 2026', 'detail' => '', 'cta_label' => '', 'cta_href' => '', 'status' => 'past' ),
			array( 'title' => 'Easter Multi-Sport Camp', 'tag' => 'Junior', 'date' => 'Mar 2026', 'detail' => '', 'cta_label' => '', 'cta_href' => '', 'status' => 'past' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function sponsors(): array {
		return array(
			array( 'name' => 'Sponsor 01', 'url' => '' ),
			array( 'name' => 'Sponsor 02', 'url' => '' ),
			array( 'name' => 'Sponsor 03', 'url' => '' ),
			array( 'name' => 'Sponsor 04', 'url' => '' ),
			array( 'name' => 'Sponsor 05', 'url' => '' ),
			array( 'name' => 'Sponsor 06', 'url' => '' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function people(): array {
		return array(
			array( 'name' => 'Priya Nair', 'committee_role' => 'Chair', 'directory_role' => 'Press', 'email' => 'press@clubhouse.example' ),
			array( 'name' => 'Tom Ellison', 'committee_role' => 'Treasurer', 'directory_role' => 'Sponsorship', 'email' => 'sponsors@clubhouse.example' ),
			array( 'name' => 'Grace Okafor', 'committee_role' => 'Secretary', 'directory_role' => 'Venue hire', 'email' => 'hire@clubhouse.example' ),
			array( 'name' => 'Daniel Reed', 'committee_role' => 'Membership', 'directory_role' => 'Membership', 'email' => 'membership@clubhouse.example' ),
			array( 'name' => 'Aisha Khan', 'committee_role' => 'Safeguarding', 'directory_role' => 'Juniors & safeguarding', 'email' => 'safeguarding@clubhouse.example' ),
			array( 'name' => 'Mark Bailey', 'committee_role' => 'Grounds', 'directory_role' => '', 'email' => '' ),
			array( 'name' => 'The club office', 'committee_role' => '', 'directory_role' => 'General', 'email' => 'hello@clubhouse.example' ),
		);
	}
}
