<?php

use PHPUnit\Framework\TestCase;

/**
 * Booking is offered in two places, and they have to admit it.
 *
 * Issue #136: "Book a court" lists sessions, courts and coaches; the Calendar
 * page shows LatePoint's month view of the same system. They look nothing
 * alike, neither links to the other, and nothing tells a member which to use.
 *
 * The split itself is deliberate and stays — the Bookings page is what, where
 * and who; the Calendar is when, beside the fixtures a member is already
 * reading. What was missing is any acknowledgement that they are two halves of
 * one thing.
 */
final class BookingJourneyTest extends TestCase {

	protected function setUp(): void {
		// Both the Bookings page and the Calendar's booking section only exist
		// when LatePoint does.
		Blueworx_Clubhouse_Integrations::set_detector(
			static fn( string $tag ): bool => Blueworx_Clubhouse_Integrations::LATEPOINT_TAG === $tag
		);
		Blueworx_Clubhouse_Shortcodes::set_expander( static fn( string $s ): string => '<div class="latepoint">' . $s . '</div>' );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_Integrations::set_detector( null );
		Blueworx_Clubhouse_Shortcodes::set_expander( null );
	}

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function visibility(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function collections(): Blueworx_Clubhouse_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	/**
	 * The page's own content, with the header and footer stripped off.
	 *
	 * Asserting against the whole document would prove nothing here: the nav
	 * links every page to every other, so "the Bookings page links to the
	 * Calendar" is true of a page that says nothing about booking at all.
	 */
	private function body( string $html ): string {
		$open  = strpos( $html, '<main' );
		$close = strpos( $html, '</main>' );
		$this->assertIsInt( $open );
		$this->assertIsInt( $close );
		return substr( $html, $open, $close - $open );
	}

	private function booking(): string {
		return $this->body( Blueworx_Clubhouse_Page_Renderer::booking( $this->branding(), $this->visibility(), $this->collections(), '', null ) );
	}

	private function calendar(): string {
		return $this->body( Blueworx_Clubhouse_Page_Renderer::calendar( $this->branding(), $this->visibility(), $this->collections(), '', null ) );
	}

	public function test_the_bookings_page_points_at_the_times(): void {
		// The page lists what you can book but cannot tell you when it is free,
		// so it has to hand over to the page that can.
		$html = $this->booking();
		$this->assertStringContainsString( Blueworx_Clubhouse_Links::url( 'calendar' ), $html );
	}

	public function test_the_bookings_page_no_longer_promises_a_time(): void {
		// It said "pick a session, a court and a time" over three lists that
		// offer no times at all.
		$this->assertStringNotContainsString( 'and a time', $this->booking() );
	}

	public function test_the_calendar_points_back_at_the_sessions(): void {
		$html = $this->calendar();
		$this->assertStringContainsString( Blueworx_Clubhouse_Links::url( 'booking' ), $html );
	}

	public function test_the_calendar_says_what_the_other_page_is_for(): void {
		// "Book a court" beside a month grid, with no hint that sessions, courts
		// and coaches are listed elsewhere, is where the confusion started.
		$this->assertStringContainsString( 'Sessions, courts and coaches', $this->calendar() );
	}

	public function test_a_club_without_latepoint_is_offered_neither(): void {
		// The Calendar page still renders without LatePoint; it must not carry a
		// link to a Bookings page this site does not serve.
		Blueworx_Clubhouse_Integrations::set_detector( null );
		$this->assertStringNotContainsString( 'Sessions, courts and coaches', $this->calendar() );
	}

	public function test_a_club_can_replace_the_cross_link(): void {
		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set( 'calendar', 'booking', 'link_label', 'Our own words' );
		$content->set( 'calendar', 'booking', 'link_href', 'https://club.test/elsewhere' );
		$html = $this->body( Blueworx_Clubhouse_Page_Renderer::calendar( $this->branding(), $this->visibility(), $this->collections(), '', $content ) );
		$this->assertStringContainsString( 'Our own words', $html );
		$this->assertStringContainsString( 'https://club.test/elsewhere', $html );
	}

	public function test_a_link_needs_both_halves_or_it_is_dropped(): void {
		// The rule the event cards and the CTA band already follow: a label with
		// no href renders a link that goes nowhere.
		$half = Blueworx_Clubhouse_Sections::shortcode_block( array(
			'eyebrow'    => 'E',
			'heading'    => 'H',
			'shortcode'  => '[x]',
			'link_label' => 'Somewhere',
			'link_href'  => '',
		) );
		$this->assertStringNotContainsString( '<a', $half );
	}
}
