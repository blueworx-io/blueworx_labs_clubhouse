<?php

use PHPUnit\Framework\TestCase;

/**
 * The privacy policy, the terms, and the cookie notice.
 *
 * Issue #121: both pages 404'd, nothing in the footer pointed at them, and
 * there was no cookie wording anywhere — on a site whose forms collect names,
 * email addresses and phone numbers.
 */
final class LegalPagesTest extends TestCase {

	private function privacy( ?Blueworx_Clubhouse_Fake_Storage $storage = null ): string {
		return Blueworx_Clubhouse_Test_Site::page( 'privacy', $storage );
	}

	private function terms( ?Blueworx_Clubhouse_Fake_Storage $storage = null ): string {
		return Blueworx_Clubhouse_Test_Site::page( 'terms', $storage );
	}

	public function test_both_pages_are_pages_this_site_serves(): void {
		$this->assertTrue( Blueworx_Clubhouse_Page_Map::is_available( 'privacy' ) );
		$this->assertTrue( Blueworx_Clubhouse_Page_Map::is_available( 'terms' ) );
	}

	public function test_the_privacy_page_names_what_the_forms_actually_collect(): void {
		$html = $this->privacy();
		$this->assertStringContainsString( 'What we collect', $html );
		$this->assertStringContainsString( 'phone number', $html );
		$this->assertStringContainsString( 'never see or store your card number', $html );
	}

	public function test_the_privacy_page_covers_the_rights_a_uk_visitor_has(): void {
		$html = $this->privacy();
		$this->assertStringContainsString( 'Your rights', $html );
		$this->assertStringContainsString( 'ico.org.uk', $html );
	}

	/**
	 * The starter pages carry worked examples wherever only the club can answer,
	 * and every one of them says so in the same words. A policy that confidently
	 * describes data sharing nobody does is worse than an unfinished one — only
	 * the second gets corrected — so the lead sentence is what keeps an example
	 * from reading as a decision the club has made.
	 */
	public function test_every_worked_example_says_it_is_one(): void {
		$lead = Blueworx_Clubhouse_Block_Defaults::EXAMPLE_LEAD;

		// The counts are the point: a paragraph added later without the lead is a
		// line an owner would ship believing their club had agreed to it.
		$this->assertSame( 6, substr_count( $this->privacy(), $lead ), 'privacy' );
		$this->assertSame( 4, substr_count( $this->terms(), $lead ), 'terms' );
	}

	/** No unfinished markers left on either page — an owner sees prose, not a brief. */
	public function test_no_placeholder_markers_are_left_on_either_page(): void {
		$this->assertStringNotContainsString( 'ADD:', $this->privacy() );
		$this->assertStringNotContainsString( 'ADD:', $this->terms() );
	}

	public function test_the_terms_page_leaves_payments_and_refunds_to_the_club(): void {
		$html = $this->terms();
		$this->assertStringContainsString( 'Membership and payments', $html );
		$this->assertStringContainsString( 'Refunds', $html );
	}

	public function test_a_club_can_replace_the_wording_entirely(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::write( $storage, 'privacy/body', array( 'items' => array(
			array( 'heading' => 'Our policy', 'body' => "First para.\n\nSecond para." ),
		) ) );
		$html = $this->privacy( $storage );
		$this->assertStringContainsString( 'Our policy', $html );
		$this->assertStringNotContainsString( 'ADD:', $html );
		// Blank lines become paragraphs; that is the whole of the formatting.
		$this->assertStringContainsString( 'First para.', $html );
		$this->assertStringContainsString( 'Second para.', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-prose__p' ) );
	}

	public function test_pasted_markup_is_escaped_rather_than_rendered(): void {
		// The one page a club is most likely to paste something into from
		// elsewhere, so it is also the one that must not honour what it pastes.
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::write( $storage, 'privacy/body', array( 'items' => array(
			array( 'heading' => 'X', 'body' => '<script>alert(1)</script>' ),
		) ) );
		$this->assertStringNotContainsString( '<script>', $this->privacy( $storage ) );
	}

	public function test_the_footer_links_to_both_pages_from_every_page(): void {
		// The slot has been in the footer all along and has always been empty.
		foreach ( array( $this->privacy(), $this->terms() ) as $html ) {
			$this->assertStringContainsString( 'ch-footer__legal', $html );
			$this->assertStringContainsString( '>Privacy<', $html );
			$this->assertStringContainsString( '>Terms<', $html );
		}
	}

	public function test_a_hidden_legal_page_is_not_linked(): void {
		// A link to a page a club has switched off is a link to a 404.
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_page_visible( 'terms', false );

		$html = $this->privacy( $storage );
		$this->assertStringContainsString( '>Privacy<', $html );
		$this->assertStringNotContainsString( '>Terms<', $html );
	}

	public function test_the_cookie_notice_says_what_is_true_of_this_site(): void {
		$html = $this->privacy();
		$this->assertStringContainsString( 'ch-cookie', $html );
		$this->assertStringContainsString( 'to take payment', $html );
		// It must not claim to do something it does not do.
		$this->assertStringNotContainsString( 'Reject', $html );
		$this->assertStringNotContainsString( 'Manage preferences', $html );
	}

	public function test_the_cookie_notice_ships_hidden_and_links_the_policy(): void {
		$html = $this->privacy();
		$this->assertStringContainsString( 'id="ch-cookie"', $html );
		$this->assertStringContainsString( 'hidden>', $html );
		$this->assertStringContainsString( 'Read our privacy policy', $html );
	}

	public function test_a_club_can_switch_the_cookie_notice_off_by_emptying_it(): void {
		// Clubs running a real consent plugin need this out of the way, and an
		// empty text is the plainest way to ask for that.
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::write( $storage, 'global/cookies', array( 'show' => '0' ) );
		$this->assertStringNotContainsString( 'ch-cookie', $this->privacy( $storage ) );
	}

	public function test_an_empty_document_renders_nothing_rather_than_an_empty_section(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Sections::prose( array( 'heading' => '', 'blocks' => array() ) ) );
		$this->assertSame(
			'',
			Blueworx_Clubhouse_Sections::prose( array( 'heading' => '', 'blocks' => array( array( 'heading' => '', 'body' => '' ) ) ) )
		);
	}

	public function test_every_clause_can_be_linked_to_directly(): void {
		// A policy is read to find one clause, and quoted by pointing at it.
		$this->assertStringContainsString( 'id="ch-prose-1"', $this->privacy() );
		$this->assertStringContainsString( 'id="ch-prose-2"', $this->privacy() );
	}
}
