<?php
// tests/php/SectionsTest.php

use PHPUnit\Framework\TestCase;

final class SectionsTest extends TestCase {

	public function test_header_renders_banner_nav_active_and_dual_cta(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => 'ClubHouse',
			'banner'      => 'Summer sign-ups are open →',
			'banner_href' => '?page=membership',
			'nav'         => array(
				array( 'label' => 'Home', 'href' => '?page=home' ),
				array( 'label' => 'Membership', 'href' => '?page=membership' ),
			),
			'active'      => '?page=home',
			'login'       => 'Log in',
			'join'        => 'Join the Club',
			'join_href'   => '?page=membership',
		) );
		$this->assertStringContainsString( 'class="ch-banner"', $html );
		$this->assertStringContainsString( 'Summer sign-ups are open', $html );
		$this->assertStringContainsString( 'class="ch-nav"', $html );
		$this->assertStringContainsString( 'ch-nav__link--active', $html );
		$this->assertStringContainsString( 'Log in', $html );
		$this->assertStringContainsString( 'Join the Club', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_header_hides_banner_when_empty(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name' => 'ClubHouse', 'banner' => '', 'banner_href' => '',
			'nav' => array(), 'active' => '', 'login' => 'Log in',
			'join' => 'Join', 'join_href' => '#',
		) );
		$this->assertStringNotContainsString( 'class="ch-banner"', $html );
	}

	public function test_hero_highlights_accent_and_renders_media(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow'            => 'Est. 1974',
			'title_lead'         => 'Every sport. Every age. ',
			'title_highlight'    => 'One community.',
			'lede'               => 'Nine sports, twenty-four teams.',
			'cta_primary'        => 'Explore membership',
			'cta_primary_href'   => '?page=membership',
			'cta_secondary'      => 'Take a tour',
			'cta_secondary_href' => '#',
			'image'              => '',
			'image_alt'          => '',
			'image_caption'      => 'Saturday, floodlights on',
		) );
		$this->assertStringContainsString( 'class="ch-hero"', $html );
		$this->assertStringContainsString( 'class="ch-hero__hl"', $html );
		$this->assertStringContainsString( 'class="ch-hero__media"', $html );
		$this->assertStringContainsString( 'Saturday, floodlights on', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_stat_strip_renders_each_stat(): void {
		$html = Blueworx_Clubhouse_Sections::stat_strip( array(
			array( 'value' => '900+', 'label' => 'Members' ),
			array( 'value' => '9', 'label' => 'Sports' ),
		) );
		$this->assertStringContainsString( 'class="ch-stats"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-stats__item' ) );
		$this->assertStringContainsString( '900+', $html );
	}

	public function test_output_is_escaped(): void {
		$html = Blueworx_Clubhouse_Sections::footer( array(
			'club_name' => 'A & B <script>',
			'tagline'   => 'x',
		) );
		$this->assertStringContainsString( 'A &amp; B &lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_quick_tiles_render_each_link(): void {
		$html = Blueworx_Clubhouse_Sections::quick_tiles( array(
			array( 'label' => 'Membership', 'href' => '?page=membership' ),
			array( 'label' => 'Sports', 'href' => '?page=sports' ),
		) );
		$this->assertStringContainsString( 'class="ch-tiles"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-tiles__tile' ) );
		$this->assertStringContainsString( 'Membership', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_ticker_repeats_items_for_marquee(): void {
		$html = Blueworx_Clubhouse_Sections::ticker( array( 'News one', 'News two' ) );
		$this->assertStringContainsString( 'class="ch-ticker"', $html );
		$this->assertStringContainsString( 'News one', $html );
		// The track is duplicated so the CSS marquee loops seamlessly.
		$this->assertSame( 2, substr_count( $html, 'ch-ticker__track' ) );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_no_colour_literals_leak_into_markup(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow' => 'e', 'title_lead' => 't ', 'title_highlight' => 'h',
			'lede' => 'l', 'cta_primary' => 'a', 'cta_secondary' => 'b',
			'cta_primary_href' => '', 'cta_secondary_href' => '',
			'image' => '', 'image_alt' => '', 'image_caption' => '',
		) );
		// Skin-agnostic: sections must not hard-code colours or inline styles.
		// (href="#" is fine — we forbid hex colours and style attributes.)
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
}
