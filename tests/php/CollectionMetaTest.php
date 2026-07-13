<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CollectionMetaTest extends TestCase {

	public function test_types_are_the_six_cpts(): void {
		$this->assertSame(
			array( 'clubhouse_fixture', 'clubhouse_person', 'clubhouse_sponsor', 'clubhouse_sport', 'clubhouse_team', 'clubhouse_event' ),
			Blueworx_Clubhouse_Collection_Meta::types()
		);
	}

	public function test_fixture_fields_cover_the_meta_contract(): void {
		$keys = array_column( Blueworx_Clubhouse_Collection_Meta::fields( 'clubhouse_fixture' ), 'key' );
		$this->assertSame(
			array( 'sport', 'match_date', 'kickoff_time', 'venue', 'home_team', 'away_team', 'score', 'outcome', 'result_summary' ),
			$keys
		);
	}

	public function test_media_keys_are_image_typed_only(): void {
		$this->assertSame( array( 'image' ), Blueworx_Clubhouse_Collection_Meta::media_keys( 'clubhouse_sport' ) );
		$this->assertSame( array( 'image' ), Blueworx_Clubhouse_Collection_Meta::media_keys( 'clubhouse_team' ) );
		$this->assertSame( array(), Blueworx_Clubhouse_Collection_Meta::media_keys( 'clubhouse_fixture' ) );
	}

	public function test_date_sanitiser_accepts_iso_and_rejects_garbage(): void {
		$this->assertSame( '2026-07-12', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'match_date', '2026-07-12' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'match_date', '2026-13-40' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'match_date', 'not a date' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'match_date', '' ) );
	}

	public function test_time_sanitiser_is_strict_hhmm(): void {
		$this->assertSame( '14:30', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'kickoff_time', '14:30' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'kickoff_time', '25:99' ) );
	}

	public function test_outcome_select_allows_set_and_falls_back_to_default(): void {
		$this->assertSame( 'W', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'outcome', 'W' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'outcome', '' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'outcome', 'X' ) );
	}

	public function test_status_select_defaults_to_upcoming(): void {
		$this->assertSame( 'past', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'status', 'past' ) );
		$this->assertSame( 'upcoming', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'status', 'bogus' ) );
	}

	public function test_email_and_url_validate(): void {
		$this->assertSame( 'a@b.com', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_person', 'email', 'a@b.com' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_person', 'email', 'nope' ) );
		$this->assertSame( 'https://x.example', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sponsor', 'url', 'https://x.example' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sponsor', 'url', 'javascript:alert(1)' ) );
	}

	public function test_href_keeps_site_relative_but_blocks_javascript(): void {
		$this->assertSame( '?page=contact', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', '?page=contact' ) );
		$this->assertSame( 'https://x.example', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', 'https://x.example' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', 'javascript:alert(1)' ) );
	}

	public function test_href_blocks_whitespace_obfuscated_scheme(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', "java\tscript:alert(1)" ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', "java\nscript:alert(1)" ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', "vb script:x" ) );
		// Legitimate values still pass unchanged.
		$this->assertSame( '?page=contact', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', '?page=contact' ) );
		$this->assertSame( 'https://x.example', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', 'https://x.example' ) );
	}

	public function test_media_sanitises_to_positive_id_string(): void {
		$this->assertSame( '42', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sport', 'image', '42' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sport', 'image', '0' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sport', 'image', 'abc' ) );
	}

	public function test_text_strips_tags_and_collapses_whitespace(): void {
		$this->assertSame( 'Hello World', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sport', 'label', "  <b>Hello</b>\n  World  " ) );
	}

	public function test_unknown_key_returns_empty(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sport', 'nope', 'x' ) );
	}

	public function test_columns_defined_for_each_type(): void {
		$this->assertSame( array( 'match_date', 'matchup', 'result' ), array_keys( Blueworx_Clubhouse_Collection_Meta::columns( 'clubhouse_fixture' ) ) );
		$this->assertNotEmpty( Blueworx_Clubhouse_Collection_Meta::columns( 'clubhouse_person' ) );
	}
}
