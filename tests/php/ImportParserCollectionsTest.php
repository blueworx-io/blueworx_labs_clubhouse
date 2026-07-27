<?php
// tests/php/ImportParserCollectionsTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportParserCollectionsTest extends TestCase {

	/** @param array<string,mixed> $collections */
	private function parse( array $collections ): array {
		return Blueworx_Clubhouse_Import_Parser::parse( array(
			'clubhouse_import' => 1,
			'collections'      => $collections,
		) );
	}

	public function test_a_collection_item_becomes_a_title_and_meta_pair(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array(
			array( 'title' => 'Tennis', 'subtitle' => 'Six courts' ),
		) ) );
		$item = $out['plan']->collections()['clubhouse_sport'][0];
		$this->assertSame( 'Tennis', $item['title'] );
		$this->assertSame( 'Six courts', $item['meta']['subtitle'] );
	}

	public function test_an_unknown_collection_type_is_warned_and_dropped(): void {
		$out = $this->parse( array( 'clubhouse_nope' => array( array( 'title' => 'X' ) ) ) );
		$this->assertTrue( $out['plan']->is_empty() );
		$this->assertSame( array( 'Ignored unknown collection "clubhouse_nope".' ), $out['plan']->warnings() );
	}

	public function test_a_collection_that_is_not_a_list_is_warned(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array( 'title' => 'Tennis' ) ) );
		$this->assertSame( array( 'Ignored "clubhouse_sport": expected a list of items.' ), $out['plan']->warnings() );
	}

	public function test_an_item_without_a_title_is_warned_and_dropped(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array(
			array( 'title' => 'Tennis' ),
			array( 'subtitle' => 'No name' ),
		) ) );
		$this->assertCount( 1, $out['plan']->collections()['clubhouse_sport'] );
		$this->assertSame( array( 'Ignored clubhouse_sport item 1: every item needs a title.' ), $out['plan']->warnings() );
	}

	public function test_an_empty_collection_list_is_not_planned(): void {
		// An empty list must not reach the plan: applying it would delete the demo
		// posts of that type and leave the section blank, which no owner asked for.
		$out = $this->parse( array( 'clubhouse_sport' => array() ) );
		$this->assertTrue( $out['plan']->is_empty() );
		$this->assertSame( array( 'Ignored "clubhouse_sport": the list is empty.' ), $out['plan']->warnings() );
	}

	public function test_unknown_meta_keys_are_warned_and_dropped(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array(
			array( 'title' => 'Tennis', 'evil' => 'x' ),
		) ) );
		$this->assertArrayNotHasKey( 'evil', $out['plan']->collections()['clubhouse_sport'][0]['meta'] );
		$this->assertSame( array( 'Ignored unknown field "clubhouse_sport/evil".' ), $out['plan']->warnings() );
	}

	public function test_values_are_sanitised_by_collection_meta(): void {
		$out = $this->parse( array( 'clubhouse_fixture' => array(
			array( 'title' => 'A vs B', 'match_date' => 'not-a-date', 'kickoff_time' => '14:00' ),
		) ) );
		$meta = $out['plan']->collections()['clubhouse_fixture'][0]['meta'];
		$this->assertSame( '', $meta['match_date'] ); // strict format check rejects it
		$this->assertSame( '14:00', $meta['kickoff_time'] );
	}

	public function test_a_select_value_outside_its_options_falls_back_to_the_default(): void {
		$out = $this->parse( array( 'clubhouse_event' => array(
			array( 'title' => 'Gala', 'status' => 'sideways' ),
		) ) );
		$this->assertSame( 'upcoming', $out['plan']->collections()['clubhouse_event'][0]['meta']['status'] );
		// Every other drop warns; a corrected select must not be the silent
		// exception. Collection_Meta::sanitise() is the source of truth for what
		// counts as a correction — detected here by comparing its output to what
		// was supplied, not by re-checking the option list ourselves.
		$this->assertSame(
			array( 'Ignored "clubhouse_event/status": "sideways" is not a valid option; using the default.' ),
			$out['plan']->warnings()
		);
	}

	public function test_a_select_value_matching_the_default_is_not_warned(): void {
		$out = $this->parse( array( 'clubhouse_event' => array(
			array( 'title' => 'Gala', 'status' => 'upcoming' ),
		) ) );
		$this->assertSame( array(), $out['plan']->warnings() );
	}

	public function test_a_media_field_is_kept_as_an_image_reference(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array(
			array( 'title' => 'Tennis', 'image' => array( 'url' => 'https://e.test/t.jpg', 'alt' => 'Court' ) ),
		) ) );
		$item = $out['plan']->collections()['clubhouse_sport'][0];
		$this->assertSame( 'https://e.test/t.jpg', $item['images']['image']['url'] );
		$this->assertSame( 'Court', $item['images']['image']['alt'] );
		$this->assertArrayNotHasKey( 'image', $item['meta'] );
	}

	public function test_a_bad_media_url_is_warned_and_the_item_still_imports(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array(
			array( 'title' => 'Tennis', 'image' => 'ftp://e.test/t.jpg' ),
		) ) );
		$item = $out['plan']->collections()['clubhouse_sport'][0];
		$this->assertSame( 'Tennis', $item['title'] );
		$this->assertSame( array(), $item['images'] );
		$this->assertSame( array( 'Ignored the image for clubhouse_sport item 0: expected an image URL.' ), $out['plan']->warnings() );
	}

	public function test_content_and_collections_can_arrive_in_one_file(): void {
		$out = Blueworx_Clubhouse_Import_Parser::parse( array(
			'clubhouse_import' => 1,
			'content'          => array( 'home' => array( 'hero' => array( 'eyebrow' => 'Est. 1974' ) ) ),
			'collections'      => array( 'clubhouse_sport' => array( array( 'title' => 'Tennis' ) ) ),
		) );
		$this->assertSame( 'Est. 1974', $out['plan']->fields()['home']['hero']['eyebrow'] );
		$this->assertCount( 1, $out['plan']->collections()['clubhouse_sport'] );
	}
}
