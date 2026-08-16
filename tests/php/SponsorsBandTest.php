<?php

use PHPUnit\Framework\TestCase;

/**
 * Issue #163: the sponsors band rendered its heading and its "Become a
 * sponsor" button whether or not the club had any sponsors, leaving an empty
 * strip that advertised the absence.
 *
 * Sponsors themselves are already a content type the club edits, so the demo
 * names are demo data, not a defect. What is left is the empty case.
 */
final class SponsorsBandTest extends TestCase {

	/** @param array<int,string> $names */
	private function band( array $names ): string {
		return Blueworx_Clubhouse_Sections::sponsors( array(
			'eyebrow'    => 'Our partners',
			'heading'    => 'Our sponsors & partners',
			'link_label' => 'Become a sponsor',
			'link_href'  => '?page=contact',
			'names'      => $names,
		) );
	}

	public function test_no_sponsors_means_no_band_at_all(): void {
		$this->assertSame( '', $this->band( array() ) );
	}

	/** Not just the tiles — the heading and the button go with them. */
	public function test_the_heading_and_button_go_too(): void {
		$html = $this->band( array() );
		$this->assertStringNotContainsString( 'Our sponsors', $html );
		$this->assertStringNotContainsString( 'Become a sponsor', $html );
	}

	public function test_a_sponsor_saved_without_a_name_is_not_a_sponsor(): void {
		$this->assertSame( '', $this->band( array( '', '   ' ) ) );
	}

	public function test_blank_entries_do_not_become_blank_tiles(): void {
		$html = $this->band( array( 'Marlow Motors', '  ', 'Riverside Insurance' ) );
		$this->assertSame( 2, substr_count( $html, 'ch-sponsors__tile' ) );
	}

	public function test_names_the_club_entered_still_show(): void {
		$html = $this->band( array( 'Marlow Motors', 'Riverside Insurance' ) );
		$this->assertStringContainsString( 'Marlow Motors', $html );
		$this->assertStringContainsString( 'Riverside Insurance', $html );
		$this->assertStringContainsString( 'Become a sponsor', $html );
	}

	/** The home page drops the section rather than emitting an empty wrapper. */
	public function test_the_home_page_omits_the_band_when_the_club_has_no_sponsors(): void {
		$body = Blueworx_Clubhouse_Page_Renderer::home(
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Sponsorless_Collections()
		);
		$this->assertStringNotContainsString( 'ch-sponsors', $body );
		$this->assertStringNotContainsString( 'Become a sponsor', $body );
	}
}

/** A club like any other, that simply has no sponsors yet. */
final class Blueworx_Clubhouse_Sponsorless_Collections implements Blueworx_Clubhouse_Collections {

	public function sports(): array {
		return Blueworx_Clubhouse_Demo_Content::sports();
	}
	public function teams(): array {
		return Blueworx_Clubhouse_Demo_Content::teams();
	}
	public function fixtures(): array {
		return Blueworx_Clubhouse_Demo_Content::fixtures();
	}
	public function events(): array {
		return Blueworx_Clubhouse_Demo_Content::events();
	}
	public function people(): array {
		return Blueworx_Clubhouse_Demo_Content::people();
	}
	public function sponsors(): array {
		return array();
	}
}
