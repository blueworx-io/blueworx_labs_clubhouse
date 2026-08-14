<?php

use PHPUnit\Framework\TestCase;

/**
 * The Membership page has to describe what actually happens on it.
 *
 * Issue #90: the headline promised "Join in five minutes" above steps that said
 * register your interest, no payment yet, and we will be in touch within a few
 * days. Both are true of some club — the page now says whichever is true here.
 */
final class MembershipPromiseTest extends TestCase {

	protected function tearDown(): void {
		Blueworx_Clubhouse_Products_Source::set( null );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );
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

	/** A shop with a checkout, and one tier connected to a real price. */
	private function selling_content(): Blueworx_Clubhouse_Content_Store {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/checkout/' );

		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'membership', 'tiers', array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
		) );
		return $content;
	}

	private function page( ?Blueworx_Clubhouse_Content_Store $content ): string {
		return Blueworx_Clubhouse_Page_Renderer::membership(
			$this->branding(),
			$this->visibility(),
			$this->collections(),
			'',
			$content
		);
	}

	public function test_a_club_that_cannot_take_payment_does_not_promise_five_minutes(): void {
		$html = $this->page( null );
		$this->assertStringNotContainsString( 'Join in five minutes', $html );
		$this->assertStringContainsString( 'Register interest', $html );
		$this->assertStringContainsString( 'no payment yet', $html );
	}

	public function test_a_club_that_cannot_take_payment_says_it_will_be_in_touch(): void {
		$html = $this->page( null );
		$this->assertStringContainsString( 'in touch within a few days', $html );
		$this->assertStringContainsString( 'Payment details are arranged once your interest is confirmed', $html );
	}

	public function test_a_club_that_can_take_payment_promises_joining_and_paying(): void {
		$html = $this->page( $this->selling_content() );
		$this->assertStringContainsString( 'Join in five minutes', $html );
		$this->assertStringContainsString( 'Join and pay', $html );
		$this->assertStringContainsString( 'By card, at checkout, when you join.', $html );
	}

	public function test_a_club_that_can_take_payment_never_says_register_your_interest(): void {
		// The contradiction, from the other side: a page that takes cards must
		// not also tell someone to fill in a form and wait for a call.
		$html = $this->page( $this->selling_content() );
		$this->assertStringNotContainsString( 'no payment yet', $html );
		$this->assertStringNotContainsString( 'in touch within a few days', $html );
		$this->assertStringNotContainsString( 'Payment details are arranged', $html );
	}

	public function test_a_selling_page_sends_people_to_the_tiers_rather_than_the_contact_form(): void {
		$html = $this->page( $this->selling_content() );
		$this->assertStringContainsString( '#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'membership', 'tiers' ), $html );
	}

	public function test_a_clubs_own_copy_always_wins(): void {
		// The page picks a default, not the words. A club that has written its
		// own headline keeps it whether or not it sells.
		$content = $this->selling_content();
		$content->set( 'membership', 'hero', 'title_lead', 'Our own headline. ' );
		$html = $this->page( $content );
		$this->assertStringContainsString( 'Our own headline.', $html );
		$this->assertStringNotContainsString( 'Join in five minutes', $html );
	}

	public function test_half_a_connection_is_not_selling(): void {
		// A tier connected to a price but with no checkout page keeps the
		// club's typed price and the contact link, so the page must keep the
		// register-interest promise too.
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );

		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'membership', 'tiers', array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
		) );

		$this->assertStringContainsString( 'no payment yet', $this->page( $content ) );
	}

	public function test_the_promise_is_read_off_the_buttons_the_visitor_can_see(): void {
		$checkout = 'https://club.test/checkout/';
		$this->assertTrue( Blueworx_Clubhouse_Page_Renderer::tiers_sell(
			array( array( 'cta_href' => $checkout . '?line_items[0][price_id]=p1' ) ),
			$checkout
		) );
		$this->assertFalse( Blueworx_Clubhouse_Page_Renderer::tiers_sell(
			array( array( 'cta_href' => 'https://club.test/?page=contact' ) ),
			$checkout
		) );
		$this->assertFalse( Blueworx_Clubhouse_Page_Renderer::tiers_sell(
			array( array( 'cta_href' => 'https://club.test/checkout/' ) ),
			''
		) );
	}
}
