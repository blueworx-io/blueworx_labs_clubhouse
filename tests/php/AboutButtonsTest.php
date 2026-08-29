<?php

use PHPUnit\Framework\TestCase;

/**
 * Issue #164: "Meet the committee" and "Book a visit" both pointed at the
 * contact page. Neither did what it said, and the committee it offered to
 * introduce was further down the same page.
 */
final class AboutButtonsTest extends TestCase {

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function collections(): Blueworx_Clubhouse_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	private function about( Blueworx_Clubhouse_Visibility $visibility, ?Blueworx_Clubhouse_Page_Content $content = null ): string {
		$html  = Blueworx_Clubhouse_Page_Renderer::about( $this->branding(), $visibility, $this->collections(), '', $content );
		$open  = strpos( $html, '<main' );
		$close = strpos( $html, '</main>' );
		return substr( $html, (int) $open, (int) $close - (int) $open );
	}

	private function visible(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_meet_the_committee_goes_to_the_committee(): void {
		$html = $this->about( $this->visible() );
		$this->assertStringContainsString(
			'href="#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'about', 'committee' ) . '">Meet the committee',
			$html
		);
	}

	public function test_the_two_hero_buttons_no_longer_share_a_destination(): void {
		// The complaint underneath the labels: two buttons, one place.
		$html = $this->about( $this->visible() );
		$this->assertSame( 1, substr_count( $html, 'href="' . Blueworx_Clubhouse_Links::url( 'contact' ) . '"' ) );
	}

	public function test_a_club_with_no_committee_section_gets_the_contact_page_back(): void {
		// An anchor to a section that is not rendered points at nothing.
		wp_stub_reset();
		update_option( 'clubhouse_page_id_about', 42 );
		$GLOBALS['wp_stub_postmeta'][42]['page_committee__shown'] = '';
		$content = new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
		$html    = $this->about( $this->visible(), $content );
		$this->assertStringNotContainsString( '#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'about', 'committee' ), $html );
		$this->assertStringContainsString( Blueworx_Clubhouse_Links::url( 'contact' ) . '">Meet the committee', $html );
	}

	public function test_the_facilities_button_no_longer_promises_a_booking(): void {
		// Nothing on the other end of that link can take a booking.
		$html = $this->about( $this->visible() );
		$this->assertStringNotContainsString( 'Book a visit', $html );
		$this->assertStringContainsString( 'Arrange a visit', $html );
	}
}
