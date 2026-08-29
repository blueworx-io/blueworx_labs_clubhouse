<?php

use PHPUnit\Framework\TestCase;

/**
 * Issue #147: on the Calendar page the sport pills sat in the hero, above
 * LatePoint's booking calendar — which they do not filter. The list they do
 * filter, fixtures & results, was a long way further down the page, so the
 * pills read as filtering the booking grid.
 *
 * They now render inside the fixtures section, directly above the list.
 */
final class CalendarFilterPositionTest extends TestCase {

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function collections(): Blueworx_Clubhouse_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	private function visibility(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function calendar( ?Blueworx_Clubhouse_Visibility $vis = null, string $filter = '', ?Blueworx_Clubhouse_Page_Content $content = null ): string {
		return Blueworx_Clubhouse_Page_Renderer::calendar(
			$this->branding(),
			$vis ?? $this->visibility(),
			$this->collections(),
			'',
			$content,
			$filter
		);
	}

	/** The hero's own markup, minus the no-reload script that names the pill class. */
	private function hero_of( string $body ): string {
		$hero = substr( $body, (int) strpos( $body, 'class="ch-hero-filter"' ) );
		$hero = substr( $hero, 0, (int) strpos( $hero, '</section>' ) );
		return (string) preg_replace( '#<script>.*?</script>#s', '', $hero );
	}

	public function test_the_pills_are_not_in_the_hero(): void {
		$hero = $this->hero_of( $this->calendar() );
		$this->assertStringNotContainsString( '<nav class="ch-filters"', $hero );
		$this->assertStringNotContainsString( 'class="ch-filter', $hero );
	}

	public function test_the_pills_sit_immediately_above_the_fixtures_list(): void {
		$body  = $this->calendar();
		$pills = strpos( $body, '<nav class="ch-filters"' );
		$cal   = strpos( $body, 'class="ch-cal"' );
		$this->assertIsInt( $pills );
		$this->assertIsInt( $cal );
		$this->assertLessThan( $cal, $pills, 'pills come before the fixtures list' );
		// Nothing but the pill row itself between them: no other section slipped in.
		$between = substr( $body, (int) $pills, (int) $cal - (int) $pills );
		$this->assertStringNotContainsString( '<section', $between );
	}

	public function test_the_pills_are_below_the_booking_calendar_they_do_not_filter(): void {
		$body    = $this->calendar();
		$booking = strpos( $body, 'ch-shortcode' );
		if ( false === $booking ) {
			$this->markTestSkipped( 'booking section not rendered without LatePoint' );
		}
		$this->assertGreaterThan( $booking, (int) strpos( $body, '<nav class="ch-filters"' ) );
	}

	public function test_the_pills_still_work_and_still_narrow_the_list(): void {
		$body = $this->calendar();
		$this->assertStringContainsString( 'clubhouse_filter=', $body );
		$this->assertMatchesRegularExpression( '/ch-filter[^"]*"[^>]*>All</', $body );
		$this->assertStringContainsString( 'aria-label="Filter fixtures by sport"', $body );
	}

	/**
	 * A filter that matches nothing empties the list. The pills have to survive
	 * that, or there is no way back to "All" short of editing the URL.
	 */
	public function test_the_pills_survive_an_empty_result(): void {
		$html = Blueworx_Clubhouse_Sections::calendar_months( array(
			'eyebrow'      => 'The schedule',
			'heading'      => 'Fixtures & results',
			'empty_text'   => 'No fixtures match that filter.',
			'filter_label' => 'Filter fixtures by sport',
			'filters'      => array(
				array( 'label' => 'All', 'href' => '?page=calendar', 'active' => false ),
				array( 'label' => 'Rugby', 'href' => '?page=calendar&clubhouse_filter=rugby', 'active' => true ),
			),
			'months'       => array(),
		) );
		$this->assertStringContainsString( 'No fixtures match that filter.', $html );
		$this->assertStringContainsString( '<nav class="ch-filters"', $html );
		$this->assertMatchesRegularExpression( '/ch-filter[^"]*"[^>]*>All</', $html );
	}

	/** A section with no pills passed renders exactly as it always did. */
	public function test_a_list_with_no_filters_is_unchanged(): void {
		$html = Blueworx_Clubhouse_Sections::calendar_months( array(
			'eyebrow' => 'The schedule',
			'heading' => 'Fixtures & results',
			'months'  => array(
				array( 'label' => 'September', 'rows' => array(
					array( 'date' => 'Sat 6', 'competition' => 'League', 'matchup' => 'A v B', 'detail' => 'Home', 'outcome' => '' ),
				) ),
			),
		) );
		$this->assertStringNotContainsString( 'ch-filters', $html );
		$this->assertStringContainsString( 'ch-cal__month', $html );
	}

	/**
	 * With the booking section switched off the pills must still land directly
	 * above the fixtures rather than floating up into the hero's place.
	 */
	public function test_layout_holds_with_the_booking_section_off(): void {
		$vis = $this->visibility();
		$vis->set_section_visible( 'calendar', 'booking', false );
		$body = $this->calendar( $vis );
		$this->assertLessThan(
			(int) strpos( $body, 'class="ch-cal"' ),
			(int) strpos( $body, '<nav class="ch-filters"' )
		);
	}

	/**
	 * Hiding the fixtures list hides its pills with it — pills with nothing
	 * under them would filter a list that is not on the page.
	 */
	public function test_hiding_the_fixtures_hides_the_pills(): void {
		wp_stub_reset();
		update_option( 'clubhouse_page_id_calendar', 42 );
		$GLOBALS['wp_stub_postmeta'][42]['page_schedule__shown'] = '';
		$content = new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
		$body    = $this->calendar( null, '', $content );
		$this->assertStringNotContainsString( '<nav class="ch-filters"', $body );
	}

	/** Sports and Teams keep their pills in the hero: there they filter what follows. */
	public function test_sports_and_teams_keep_their_hero_pills(): void {
		foreach ( array( 'sports', 'teams' ) as $page ) {
			$body = Blueworx_Clubhouse_Page_Renderer::$page( $this->branding(), $this->visibility(), $this->collections() );
			$hero = substr( $body, (int) strpos( $body, 'class="ch-hero-filter"' ) );
			$hero = substr( $hero, 0, (int) strpos( $hero, '</section>' ) );
			$this->assertStringContainsString( 'ch-filter', $hero, "{$page} keeps its hero pills" );
		}
	}
}
