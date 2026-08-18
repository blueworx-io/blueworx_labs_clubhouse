<?php
// tests/php/WelcomePackTest.php

use PHPUnit\Framework\TestCase;

/**
 * The welcome pack a club writes once and a member reads on their dashboard.
 *
 * The rendering is pure, so everything a club would notice is pinned here
 * without WordPress or SureCart: what shows, what stays hidden, and the fact
 * that nothing an owner types can become markup.
 */
final class WelcomePackTest extends TestCase {

	/** @return array{heading:string,body:string,link_label:string,link_href:string} */
	private function pack( array $over = array() ): array {
		return array_merge(
			array(
				'heading'    => 'Welcome to the club',
				'body'       => 'The gate code is on your membership card.',
				'link_label' => '',
				'link_href'  => '',
			),
			$over
		);
	}

	public function test_a_written_pack_renders_its_heading_and_words(): void {
		$html = Blueworx_Clubhouse_Welcome_Pack::render( $this->pack() );

		$this->assertStringContainsString( 'Welcome to the club', $html );
		$this->assertStringContainsString( 'The gate code is on your membership card.', $html );
	}

	/**
	 * A club that has not written one gets nothing at all. An empty heading over
	 * a blank space is a worse dashboard than no section.
	 */
	public function test_a_pack_with_no_words_renders_nothing(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Welcome_Pack::render( $this->pack( array( 'body' => '' ) ) ) );
		$this->assertSame( '', Blueworx_Clubhouse_Welcome_Pack::render( $this->pack( array( 'body' => "  \n \n " ) ) ) );
	}

	/** A heading alone is not a welcome pack. */
	public function test_a_heading_with_no_body_renders_nothing(): void {
		$this->assertSame(
			'',
			Blueworx_Clubhouse_Welcome_Pack::render( $this->pack( array( 'heading' => 'Welcome', 'body' => '' ) ) )
		);
	}

	/** Words without a heading are still worth showing. */
	public function test_a_body_with_no_heading_still_renders(): void {
		$html = Blueworx_Clubhouse_Welcome_Pack::render( $this->pack( array( 'heading' => '' ) ) );

		$this->assertStringContainsString( 'membership card', $html );
		$this->assertStringNotContainsString( 'clubhouse-welcome__h', $html );
	}

	public function test_a_blank_line_starts_a_new_paragraph(): void {
		$html = Blueworx_Clubhouse_Welcome_Pack::render(
			$this->pack( array( 'body' => "First thing.\n\nSecond thing.\n\n\nThird thing." ) )
		);

		$this->assertSame( 3, substr_count( $html, 'clubhouse-welcome__p' ) );
	}

	/** A single newline is the textarea wrapping, not the owner's meaning. */
	public function test_single_newlines_stay_within_one_paragraph(): void {
		$paragraphs = Blueworx_Clubhouse_Welcome_Pack::paragraphs( "One line\nand its continuation." );

		$this->assertSame( array( 'One line and its continuation.' ), $paragraphs );
	}

	public function test_a_link_needs_both_a_label_and_an_address(): void {
		$both = Blueworx_Clubhouse_Welcome_Pack::render(
			$this->pack( array( 'link_label' => 'The handbook', 'link_href' => 'https://club.example/handbook.pdf' ) )
		);
		$this->assertStringContainsString( 'href="https://club.example/handbook.pdf"', $both );
		$this->assertStringContainsString( 'The handbook', $both );

		// Half a link is dead text or an unreadable address — neither is drawn.
		foreach (
			array(
				array( 'link_label' => 'The handbook', 'link_href' => '' ),
				array( 'link_label' => '', 'link_href' => 'https://club.example/handbook.pdf' ),
			) as $half
		) {
			$this->assertStringNotContainsString(
				'clubhouse-welcome__link',
				Blueworx_Clubhouse_Welcome_Pack::render( $this->pack( $half ) )
			);
		}
	}

	public function test_nothing_an_owner_types_becomes_markup(): void {
		$html = Blueworx_Clubhouse_Welcome_Pack::render(
			$this->pack(
				array(
					'heading'    => 'Welcome <script>alert(1)</script>',
					'body'       => 'Gate code <img src=x onerror=alert(1)>',
					'link_label' => '<b>Handbook</b>',
					'link_href'  => 'https://club.example/"onmouseover="alert(1)',
				)
			)
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( 'onmouseover="alert', $html );
	}

	/**
	 * The dashboard belongs to the shop and carries none of the club's design
	 * tokens, so the pack must not reach for them — a var() there resolves to
	 * nothing and the block would arrive unstyled in a way nobody could debug.
	 * The club's accent is inlined as a literal colour instead.
	 */
	public function test_the_styling_asks_for_nothing_the_dashboard_does_not_have(): void {
		$css = Blueworx_Clubhouse_Welcome_Pack::css();

		$this->assertStringNotContainsString( 'var(--', $css );
		$this->assertStringNotContainsString( 'font-family', $css );
	}

	public function test_the_pack_greets_a_member_at_the_top_of_the_dashboard(): void {
		// It used to hang off the bottom, under the tabs and an empty
		// appointments list — the last thing a new member saw, if they scrolled.
		$page = Blueworx_Clubhouse_Welcome_Pack::compose( '<div class="sc-dashboard">Orders</div>', 'BLOCK' );

		$this->assertLessThan(
			strpos( $page, 'sc-dashboard' ),
			strpos( $page, 'BLOCK' ),
			'the welcome pack must come before the dashboard, not after it'
		);
	}

	public function test_the_dashboard_itself_is_never_touched(): void {
		$page = Blueworx_Clubhouse_Welcome_Pack::compose( '<div class="sc-dashboard">Orders</div>', 'BLOCK' );
		$this->assertStringContainsString( '<div class="sc-dashboard">Orders</div>', $page );
	}

	public function test_it_reads_as_a_banner_rather_than_a_paragraph_at_the_foot(): void {
		$html = Blueworx_Clubhouse_Welcome_Pack::render( $this->pack( array(
			'link_label' => 'Download the Welcome Pack',
			'link_href'  => 'https://club.example/handbook.pdf',
		) ) );

		$this->assertStringContainsString( 'clubhouse-welcome--banner', $html );
		// The download is a button a member can see, not an underlined line of text.
		$this->assertStringContainsString( 'clubhouse-welcome__link', $html );
		$this->assertStringContainsString( 'Download the Welcome Pack', $html );
	}

	public function test_the_club_accent_colours_the_banner(): void {
		// The dashboard loads none of the club's CSS, so the colour has to be
		// written in literally rather than reached for through a token.
		$css = Blueworx_Clubhouse_Welcome_Pack::css( '#c6f24e', '#101010' );
		$this->assertStringContainsString( '#c6f24e', $css );
		$this->assertStringContainsString( '#101010', $css );
	}

	public function test_a_nonsense_accent_falls_back_rather_than_writing_junk_into_the_page(): void {
		$css = Blueworx_Clubhouse_Welcome_Pack::css( 'red; } body { display:none', '#101010' );
		$this->assertStringNotContainsString( 'display:none', $css );
	}

	/** It is a section a visibility toggle controls, so it has to be in the inventory. */
	public function test_the_pack_can_be_switched_off_like_any_other_section(): void {
		$home = array_values(
			array_filter(
				Blueworx_Clubhouse_Setup_Sections::inventory(),
				static fn( array $p ): bool => 'home' === $p['page']
			)
		)[0];

		$this->assertContains( 'welcome', array_column( $home['sections'], 'key' ) );
	}
}
