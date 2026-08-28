<?php

use PHPUnit\Framework\TestCase;

final class AdminShellTest extends TestCase {

	public function test_it_opens_the_documented_skeleton(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'Clubhouse · Access', 'ClubHouse users and access' );
		$this->assertStringContainsString( 'class="wrap bw-wrap"', $out );
		$this->assertStringContainsString( 'class="bw-admin bw-page"', $out );
		$this->assertStringContainsString( 'class="bw-pagehead"', $out );
		$this->assertStringContainsString( 'class="bw-pagehead__eyebrow"', $out );
		$this->assertStringContainsString( 'class="bw-pagehead__h1"', $out );
		$this->assertStringContainsString( 'ClubHouse users and access', $out );
	}

	public function test_the_lede_and_actions_are_left_out_when_empty(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'Eyebrow', 'Title' );
		$this->assertStringNotContainsString( 'bw-pagehead__lede', $out );
		$this->assertStringNotContainsString( 'bw-pagehead__actions', $out );
	}

	public function test_the_lede_and_actions_appear_when_given(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'Eyebrow', 'Title', 'One sentence.', '<a class="bw-btn bw-btn--primary" href="/x">View site</a>' );
		$this->assertStringContainsString( 'class="bw-pagehead__lede">One sentence.', $out );
		$this->assertStringContainsString( 'bw-pagehead__actions', $out );
		$this->assertStringContainsString( 'View site', $out );
	}

	public function test_it_escapes_what_a_club_typed(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'E', 'Crewe <script>alert(1)</script>' );
		$this->assertStringNotContainsString( '<script>', $out );
		$this->assertStringContainsString( '&lt;script&gt;', $out );
	}

	public function test_actions_are_trusted_markup_and_not_escaped(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'E', 'T', '', '<a class="bw-btn" href="/x">Go</a>' );
		$this->assertStringContainsString( '<a class="bw-btn" href="/x">Go</a>', $out );
	}

	/**
	 * Three, not two: open() leaves the wrapper, the page and the body
	 * standing. Getting this wrong strands a div and the design system's
	 * layout quietly collapses one level.
	 */
	public function test_close_shuts_all_three_wrappers(): void {
		$this->assertSame( '</div></div></div>', Blueworx_Clubhouse_Admin_Shell::close() );
	}

	public function test_the_body_opens_a_single_column(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'E', 'T' );
		$this->assertStringContainsString( 'class="bw-page__body bw-page__body--single"', $out );
	}

	public function test_a_card_carries_its_title_note_and_body(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::card( 'Access', 'Who can edit', 'Two roles can open Clubhouse.', '<p>Body</p>' );
		$this->assertStringContainsString( 'class="bw-card"', $out );
		$this->assertStringContainsString( 'class="bw-card__eyebrow">Access', $out );
		$this->assertStringContainsString( 'class="bw-card__title">Who can edit', $out );
		$this->assertStringContainsString( 'class="bw-card__note">Two roles can open Clubhouse.', $out );
		$this->assertStringContainsString( '<div class="bw-card__body"><p>Body</p></div>', $out );
	}

	public function test_a_card_leaves_out_an_empty_eyebrow_and_note(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::card( '', 'Title', '', '<p>Body</p>' );
		$this->assertStringNotContainsString( 'bw-card__eyebrow', $out );
		$this->assertStringNotContainsString( 'bw-card__note', $out );
		$this->assertStringContainsString( 'bw-card__title', $out );
	}

	public function test_a_card_escapes_its_titles_but_not_its_body(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::card( '', '<script>x</script>', '', '<p class="mine">Body</p>' );
		$this->assertStringNotContainsString( '<script>', $out );
		$this->assertStringContainsString( '<p class="mine">Body</p>', $out );
	}

	public function test_a_card_is_balanced(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::card( 'E', 'T', 'N', '<p>B</p>' );
		$this->assertSame( substr_count( $out, '<div' ), substr_count( $out, '</div>' ) );
	}

	public function test_every_tag_it_opens_is_closed(): void {
		$html  = Blueworx_Clubhouse_Admin_Shell::open( 'E', 'T', 'Lede.', '<a class="bw-btn" href="/x">Go</a>' );
		$html .= Blueworx_Clubhouse_Admin_Shell::close();
		$this->assertSame(
			substr_count( $html, '<div' ),
			substr_count( $html, '</div>' ),
			'the shell leaves an unbalanced div behind'
		);
	}
}
