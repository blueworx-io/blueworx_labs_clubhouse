<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Slice 2d — server-side filter pills on Sports/Teams/Events/Calendar.
 * Each page derives its pills from the data, marks the active one, and narrows
 * the listed items to the current filter slug.
 */
final class FilterPillsTest extends TestCase {

	/** @return array{0:Blueworx_Clubhouse_Branding,1:Blueworx_Clubhouse_Visibility,2:Blueworx_Clubhouse_Demo_Collections} */
	private function ctx(): array {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		return array(
			new Blueworx_Clubhouse_Branding( $s ),
			new Blueworx_Clubhouse_Visibility( $s ),
			new Blueworx_Clubhouse_Demo_Collections(),
		);
	}

	public function test_filtered_url_appends_the_filter_slug(): void {
		// Default Links form is '?page=<key>'; the filter appends with '&'.
		$this->assertSame( '?page=teams&clubhouse_filter=rugby', Blueworx_Clubhouse_Links::filtered_url( 'teams', 'rugby' ) );
		// An empty filter is the bare page URL (the "All" pill).
		$this->assertSame( '?page=teams', Blueworx_Clubhouse_Links::filtered_url( 'teams', '' ) );
	}

	public function test_teams_filter_narrows_cards_and_marks_active_pill(): void {
		[ $b, $v, $c ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::teams( $b, $v, $c, '', null, 'rugby' );
		$this->assertStringContainsString( '1st XV', $html );          // the Rugby team stays
		$this->assertStringNotContainsString( '1st XI', $html );        // the Cricket team is filtered out
		$this->assertStringNotContainsString( 'Ladies 1s', $html );     // the Hockey team is filtered out
		$this->assertStringContainsString( 'ch-filter--on', $html );
		// The active pill is Rugby, not All.
		$this->assertMatchesRegularExpression( '/ch-filter--on"[^>]*>Rugby</', $html );
	}

	public function test_teams_all_shows_every_team_with_all_active(): void {
		[ $b, $v, $c ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::teams( $b, $v, $c, '', null, '' );
		$this->assertStringContainsString( '1st XV', $html );
		$this->assertStringContainsString( '1st XI', $html );
		$this->assertStringContainsString( 'Ladies 1s', $html );
		$this->assertMatchesRegularExpression( '/ch-filter--on"[^>]*>All</', $html );
	}

	public function test_unknown_filter_falls_back_to_showing_everything(): void {
		[ $b, $v, $c ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::teams( $b, $v, $c, '', null, 'kabaddi' );
		$this->assertStringContainsString( '1st XV', $html );
		$this->assertStringContainsString( '1st XI', $html );
		$this->assertMatchesRegularExpression( '/ch-filter--on"[^>]*>All</', $html );
	}

	public function test_sports_filter_narrows_to_one_sport(): void {
		[ $b, $v, $c ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::sports( $b, $v, $c, '', null, 'tennis' );
		$this->assertStringContainsString( 'Four courts', $html );      // Tennis description
		$this->assertStringNotContainsString( 'touch rugby', $html );   // Rugby description gone
	}

	public function test_events_filter_narrows_upcoming_and_past_by_tag(): void {
		[ $b, $v, $c ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::events( $b, $v, $c, '', null, 'social' );
		$this->assertStringContainsString( 'Annual Awards Night', $html );  // tag Social (upcoming)
		$this->assertStringContainsString( 'Summer BBQ', $html );           // tag Social (past)
		$this->assertStringNotContainsString( 'Club Open Day', $html );     // tag Open day, filtered out
		$this->assertStringNotContainsString( 'Spring Sevens', $html );     // tag Tournament, filtered out
	}

	public function test_calendar_filter_narrows_fixtures_by_sport_prefix(): void {
		[ $b, $v, $c ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::calendar( $b, $v, $c, '', null, 'rugby' );
		$this->assertStringContainsString( 'Riverside RFC', $html );    // a Rugby · 1st XV fixture
		$this->assertStringNotContainsString( 'Castlebridge', $html );  // a Netball · Div 2 fixture
		$this->assertStringNotContainsString( 'Hartfield', $html );     // a Cricket fixture
	}

	public function test_calendar_pills_derive_from_fixture_sports(): void {
		[ $b, $v, $c ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::calendar( $b, $v, $c, '', null, '' );
		foreach ( array( 'Rugby', 'Netball', 'Hockey', 'Cricket', 'Tennis' ) as $sport ) {
			$this->assertMatchesRegularExpression( '/ch-filter[^"]*"[^>]*>' . $sport . '</', $html, "pill for {$sport}" );
		}
	}

	public function test_page_map_threads_the_filter(): void {
		[ $b, $v, $c ] = $this->ctx();
		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$html    = Blueworx_Clubhouse_Page_Map::render( 'teams', $b, $v, $c, '', $content, 'rugby' );
		$this->assertStringContainsString( '1st XV', $html );
		$this->assertStringNotContainsString( '1st XI', $html );
	}
}
