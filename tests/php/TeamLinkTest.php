<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * A team can carry a link to its own page elsewhere — a league table, a
 * governing-body squad page, whatever the section already keeps up to date.
 * It is optional, and a team without one must show no button at all rather
 * than a button that goes nowhere.
 */
final class TeamLinkTest extends TestCase {

	/** @return array{0:Blueworx_Clubhouse_Branding,1:Blueworx_Clubhouse_Visibility} */
	private function ctx(): array {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		return array( new Blueworx_Clubhouse_Branding( $s ), new Blueworx_Clubhouse_Visibility( $s ) );
	}

	public function test_a_team_has_a_link_field_an_owner_can_fill_in(): void {
		$keys = array_column( Blueworx_Clubhouse_Collection_Meta::fields( 'clubhouse_team' ), 'key' );
		$this->assertContains( 'link', $keys );
	}

	public function test_the_link_field_keeps_a_real_address_and_drops_anything_else(): void {
		$this->assertSame(
			'https://league.example.com/first-xv',
			Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_team', 'link', ' https://league.example.com/first-xv ' )
		);
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_team', 'link', 'not a link' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_team', 'link', 'javascript:alert(1)' ) );
	}

	public function test_the_link_reaches_the_page_from_the_stored_post(): void {
		$team = Blueworx_Clubhouse_Collection_Mappers::team(
			array( 'title' => '1st XV', 'meta' => array( 'link' => 'https://league.example.com/first-xv' ) )
		);
		$this->assertSame( 'https://league.example.com/first-xv', $team['link'] );

		$bare = Blueworx_Clubhouse_Collection_Mappers::team( array( 'title' => '1st XV', 'meta' => array() ) );
		$this->assertSame( '', $bare['link'] );
	}

	public function test_the_teams_page_card_offers_the_link(): void {
		[ $b, $v ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::teams( $b, $v, new Blueworx_Clubhouse_Linked_Team_Collections() );
		$this->assertStringContainsString( 'https://league.example.com/first-xv', $html );
		$this->assertStringContainsString( 'ch-scard__cta', $html );
		// Off-site, so it opens in its own tab and cannot reach back into ours.
		$this->assertStringContainsString( 'rel="noopener"', $html );
	}

	public function test_a_team_with_no_link_gets_no_button_on_its_card(): void {
		[ $b, $v ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::teams( $b, $v, new Blueworx_Clubhouse_Unlinked_Team_Collections() );
		$this->assertStringNotContainsString( 'ch-scard__cta', $html );
	}

	public function test_the_team_page_cta_block_carries_the_link(): void {
		[ $b, $v ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::team_page( '1st-xv', $b, $v, new Blueworx_Clubhouse_Linked_Team_Collections() );
		$about = strstr( $html, 'About the section' );
		$this->assertIsString( $about, 'the team page must render the About the section block' );
		$this->assertStringContainsString( 'https://league.example.com/first-xv', (string) $about );
	}

	public function test_a_team_with_no_link_gets_no_button_in_its_cta_block(): void {
		[ $b, $v ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::team_page( '1st-xv', $b, $v, new Blueworx_Clubhouse_Unlinked_Team_Collections() );
		$about = strstr( $html, 'About the section' );
		$this->assertIsString( $about, 'the team page must render the About the section block' );
		$band = substr( (string) $about, 0, (int) strpos( (string) $about, '</section>' ) );
		$this->assertStringNotContainsString( 'ch-btn', $band );
	}

	public function test_a_sport_page_is_untouched_by_all_this(): void {
		[ $b, $v ] = $this->ctx();
		$sport = Blueworx_Clubhouse_Demo_Content::sports()[0];
		$html  = Blueworx_Clubhouse_Page_Renderer::sport_page(
			Blueworx_Clubhouse_Page_Renderer::slugify( (string) $sport['title'] ),
			$b,
			$v,
			new Blueworx_Clubhouse_Demo_Collections()
		);
		$about = strstr( $html, 'About the section' );
		$this->assertIsString( $about );
		$band = substr( (string) $about, 0, (int) strpos( (string) $about, '</section>' ) );
		$this->assertStringNotContainsString( 'ch-btn', $band );
	}
}

/** One team, with a link to its page on the league's site. */
final class Blueworx_Clubhouse_Linked_Team_Collections implements Blueworx_Clubhouse_Collections {
	public function sports(): array {
		return Blueworx_Clubhouse_Demo_Content::sports();
	}
	public function teams(): array {
		return array(
			array(
				'title'       => '1st XV',
				'sport'       => 'Rugby',
				'description' => 'Saturday league rugby, Division 3 South.',
				'match_day'   => 'Sat',
				'league'      => 'Div 3',
				'image'       => '',
				'link'        => 'https://league.example.com/first-xv',
			),
		);
	}
	public function fixtures(): array {
		return Blueworx_Clubhouse_Demo_Content::fixtures();
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
}

/** The same team, from a club that has no page anywhere else. */
final class Blueworx_Clubhouse_Unlinked_Team_Collections implements Blueworx_Clubhouse_Collections {
	public function sports(): array {
		return Blueworx_Clubhouse_Demo_Content::sports();
	}
	public function teams(): array {
		$teams = ( new Blueworx_Clubhouse_Linked_Team_Collections() )->teams();
		$teams[0]['link'] = '';
		return $teams;
	}
	public function fixtures(): array {
		return Blueworx_Clubhouse_Demo_Content::fixtures();
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
}
