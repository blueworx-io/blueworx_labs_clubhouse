<?php
// tests/php/SectionsTest.php

use PHPUnit\Framework\TestCase;

final class SectionsTest extends TestCase {

	public function test_header_renders_brand_nav_and_cta(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name' => 'ClubHouse',
			'nav'       => array( 'Membership', 'Sports' ),
			'cta'       => 'Join the Club',
		) );
		$this->assertStringContainsString( 'class="ch-nav"', $html );
		$this->assertStringContainsString( 'ClubHouse', $html );
		$this->assertStringContainsString( 'Membership', $html );
		$this->assertStringContainsString( 'Join the Club', $html );
	}

	public function test_hero_highlights_the_accent_span(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow'         => 'Est. 1974',
			'title_lead'      => 'Every sport. Every age. ',
			'title_highlight' => 'One community.',
			'lede'            => 'Nine sports, twenty-four teams.',
			'cta_primary'     => 'Explore membership',
			'cta_secondary'   => 'Take a tour',
		) );
		$this->assertStringContainsString( 'class="ch-hero"', $html );
		$this->assertStringContainsString( 'class="ch-hero__hl"', $html );
		$this->assertStringContainsString( 'One community.', $html );
		$this->assertStringContainsString( 'Explore membership', $html );
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

	public function test_no_colour_literals_leak_into_markup(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow' => 'e', 'title_lead' => 't ', 'title_highlight' => 'h',
			'lede' => 'l', 'cta_primary' => 'a', 'cta_secondary' => 'b',
		) );
		// Skin-agnostic: sections must not hard-code colours or inline styles.
		// (href="#" is fine — we forbid hex colours and style attributes.)
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
}
