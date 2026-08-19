<?php

use PHPUnit\Framework\TestCase;

final class CommercePagesTest extends TestCase {

	public function test_the_checkout_page_is_recognised(): void {
		$this->assertSame( 'checkout', Blueworx_Clubhouse_Commerce_Pages::page_key( 12, 12, 34 ) );
	}

	public function test_the_confirmation_page_is_recognised(): void {
		$this->assertSame( 'order-confirmation', Blueworx_Clubhouse_Commerce_Pages::page_key( 34, 12, 34 ) );
	}

	public function test_any_other_page_is_left_alone(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Commerce_Pages::page_key( 99, 12, 34 ) );
	}

	public function test_a_shop_with_no_pages_set_up_dresses_nothing(): void {
		// 0 means "no page recorded". Without this, every page on the site whose
		// id happened to be 0 — and any post on a broken query — would be dressed.
		$this->assertSame( '', Blueworx_Clubhouse_Commerce_Pages::page_key( 0, 0, 0 ) );
	}

	public function test_both_pages_are_described(): void {
		foreach ( Blueworx_Clubhouse_Commerce_Pages::PAGES as $key => $page ) {
			$this->assertNotSame( '', $page['title'], $key . ' has no title' );
			$this->assertNotSame( '', $page['lede'], $key . ' has no lede' );
		}
		$this->assertSame( array( 'checkout', 'order-confirmation' ), array_keys( Blueworx_Clubhouse_Commerce_Pages::PAGES ) );
	}

	public function test_the_page_keeps_its_own_content_inside_our_frame(): void {
		// We do not build checkouts. The shop's form is rendered by the shop and
		// passed through untouched.
		$html = Blueworx_Clubhouse_Dashboard_Shell::bare(
			Blueworx_Clubhouse_Commerce_Pages::PAGES['checkout']['title'],
			Blueworx_Clubhouse_Commerce_Pages::PAGES['checkout']['lede'],
			'<form id="sc-checkout"></form>',
			'https://club.test/',
			'Club'
		);
		$this->assertStringContainsString( '<form id="sc-checkout"></form>', $html );
		$this->assertStringNotContainsString( 'bw-secnav', $html );
	}
}
