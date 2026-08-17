<?php

use PHPUnit\Framework\TestCase;

/**
 * Issue #164: "Meet the committee" and "Book a visit" both pointed at the
 * contact page. Neither did what it said, and the committee it offered to
 * introduce was further down the same page.
 */
final class AboutButtonsTest extends TestCase {

	private function about( ?Blueworx_Clubhouse_Fake_Storage $storage = null ): string {
		return Blueworx_Clubhouse_Test_Site::main(
			Blueworx_Clubhouse_Test_Site::page( 'about', $storage ?? new Blueworx_Clubhouse_Fake_Storage() )
		);
	}

	public function test_meet_the_committee_goes_to_the_committee(): void {
		$html = $this->about();
		$this->assertStringContainsString(
			'href="#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'about', 'committee' ) . '">Meet the committee',
			$html
		);
	}

	public function test_the_two_hero_buttons_no_longer_share_a_destination(): void {
		// The complaint underneath the labels: two buttons, one place.
		$html = $this->about();
		$this->assertSame( 1, substr_count( $html, 'href="' . Blueworx_Clubhouse_Links::url( 'contact' ) . '"' ) );
	}

	public function test_a_club_with_no_committee_section_gets_the_contact_page_back(): void {
		// An anchor to a section that is not rendered points at nothing.
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::without( $storage, 'about/committee' );
		$html = $this->about( $storage );
		$this->assertStringNotContainsString( '#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'about', 'committee' ), $html );
		$this->assertStringContainsString( Blueworx_Clubhouse_Links::url( 'contact' ) . '">Meet the committee', $html );
	}

	public function test_the_facilities_button_no_longer_promises_a_booking(): void {
		// Nothing on the other end of that link can take a booking.
		$html = $this->about();
		$this->assertStringNotContainsString( 'Book a visit', $html );
		$this->assertStringContainsString( 'Arrange a visit', $html );
	}
}
