<?php

use PHPUnit\Framework\TestCase;

/**
 * Issue #99: the Calendar page is titled "Fixtures & results" and offers sport
 * filters, but on a club with no fixtures entered the schedule section
 * rendered nothing at all — silently. What was left on the page was LatePoint's
 * court-booking grid, which then read as the fixture list: the same coaching
 * sessions repeated every day.
 *
 * The other two halves of that finding are already fixed: the bookings sit in
 * their own labelled "Court bookings" section, and the pills moved down to the
 * fixtures they filter (#147).
 */
final class CalendarEmptyStateTest extends TestCase {

	private function calendar( Blueworx_Clubhouse_Collections $collections, string $filter = '' ): string {
		return Blueworx_Clubhouse_Page_Renderer::calendar(
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			$collections,
			'',
			null,
			$filter
		);
	}

	public function test_a_club_with_no_fixtures_is_told_so(): void {
		$body = $this->calendar( new Blueworx_Clubhouse_Fixtureless_Collections() );
		$this->assertStringContainsString( 'No fixtures listed yet', $body );
		$this->assertStringContainsString( 'Fixtures &amp; results', $body );
	}

	/** "All" on its own is not a filter — it offers everything or everything. */
	public function test_no_lone_all_pill_when_there_is_nothing_to_filter(): void {
		$body = $this->calendar( new Blueworx_Clubhouse_Fixtureless_Collections() );
		$this->assertStringNotContainsString( '<nav class="ch-filters"', $body );
	}

	/** A club WITH fixtures is unaffected — no note, real pills, real list. */
	public function test_a_club_with_fixtures_sees_its_fixtures(): void {
		$body = $this->calendar( new Blueworx_Clubhouse_Demo_Collections() );
		$this->assertStringNotContainsString( 'No fixtures listed yet', $body );
		$this->assertStringContainsString( 'ch-cal__month', $body );
		$this->assertStringContainsString( '<nav class="ch-filters"', $body );
	}

	/** A filter that matches nothing keeps its own wording, not the empty-club one. */
	public function test_a_filter_that_matches_nothing_says_something_different(): void {
		$html = Blueworx_Clubhouse_Sections::calendar_months( array(
			'eyebrow'    => 'The schedule',
			'heading'    => 'Fixtures & results',
			'empty_text' => 'No fixtures match that filter.',
			'months'     => array(),
		) );
		$this->assertStringContainsString( 'No fixtures match that filter.', $html );
	}

	/** The court bookings keep a section of their own, named for what it is. */
	public function test_court_bookings_are_labelled_separately(): void {
		$body = $this->calendar( new Blueworx_Clubhouse_Demo_Collections() );
		if ( ! str_contains( $body, 'ch-shortcode' ) ) {
			$this->markTestSkipped( 'booking section is not rendered without LatePoint' );
		}
		$this->assertStringContainsString( 'Court bookings', $body );
		$this->assertStringContainsString( 'Book a court', $body );
	}
}

/** A club that has entered no fixtures — a brand new one, or an off-season. */
final class Blueworx_Clubhouse_Fixtureless_Collections implements Blueworx_Clubhouse_Collections {

	public function sports(): array {
		return Blueworx_Clubhouse_Demo_Content::sports();
	}
	public function teams(): array {
		return Blueworx_Clubhouse_Demo_Content::teams();
	}
	public function events(): array {
		return Blueworx_Clubhouse_Demo_Content::events();
	}
	public function sponsors(): array {
		return Blueworx_Clubhouse_Demo_Content::sponsors();
	}
	public function people(): array {
		return Blueworx_Clubhouse_Demo_Content::people();
	}
	public function fixtures(): array {
		return array();
	}
}
