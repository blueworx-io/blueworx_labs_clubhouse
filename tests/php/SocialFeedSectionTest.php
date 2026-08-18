<?php

use PHPUnit\Framework\TestCase;

final class SocialFeedSectionTest extends TestCase {

	/** @param array<int,array<string,string>> $posts */
	private function html( array $posts, string $platform = 'facebook' ): string {
		return Blueworx_Clubhouse_Sections::social_feed( array(
			'platform' => $platform,
			'heading'  => 'Latest from the club',
			'lede'     => 'What we have been up to.',
			'posts'    => $posts,
		) );
	}

	/**
	 * @param array<string,string> $overrides
	 * @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}>
	 */
	private function onePost( array $overrides = array() ): array {
		return array( array_merge( array(
			'id'        => 'a',
			'image'     => 'https://cdn.test/1.jpg',
			'caption'   => 'Saturday win',
			'date'      => '2026-08-15T10:30:00+00:00',
			'permalink' => 'https://facebook.com/club/posts/1',
		), $overrides ) );
	}

	public function test_no_posts_means_no_band_at_all(): void {
		// A heading over an empty space reads as a broken site.
		$this->assertSame( '', $this->html( array() ) );
	}

	public function test_a_post_renders_as_a_card_linking_back_to_the_platform(): void {
		$html = $this->html( $this->onePost() );
		$this->assertStringContainsString( 'ch-feed__card', $html );
		$this->assertStringContainsString( 'href="https://facebook.com/club/posts/1"', $html );
		$this->assertStringContainsString( 'Saturday win', $html );
		$this->assertStringContainsString( 'https://cdn.test/1.jpg', $html );
	}

	public function test_the_platform_is_named_once_in_the_eyebrow(): void {
		$this->assertStringContainsString( 'Facebook', $this->html( $this->onePost() ) );
		$this->assertStringContainsString( 'Instagram', $this->html( $this->onePost(), 'instagram' ) );
	}

	public function test_an_unknown_platform_names_none(): void {
		$html = $this->html( $this->onePost(), 'myspace' );
		$this->assertStringNotContainsString( 'ch-eyebrow', $html );
		$this->assertStringContainsString( 'ch-feed__card', $html );
	}

	public function test_a_caption_is_text_never_markup(): void {
		$html = $this->html( $this->onePost( array( 'caption' => '<script>alert(1)</script>' ) ) );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_a_long_caption_is_cut_rather_than_running_down_the_page(): void {
		$html = $this->html( $this->onePost( array( 'caption' => str_repeat( 'word ', 80 ) ) ) );
		$this->assertStringContainsString( "\u{2026}", $html );
	}

	public function test_a_text_only_post_still_renders(): void {
		$html = $this->html( $this->onePost( array( 'image' => '', 'caption' => 'Just words' ) ) );
		$this->assertStringContainsString( 'ch-media--empty', $html );
		$this->assertStringContainsString( 'Just words', $html );
	}

	public function test_an_undated_post_shows_no_date(): void {
		$html = $this->html( $this->onePost( array( 'date' => '' ) ) );
		$this->assertStringNotContainsString( 'ch-feed__date', $html );
	}

	public function test_a_date_is_shown_readably(): void {
		$this->assertStringContainsString( '15 Aug 2026', $this->html( $this->onePost() ) );
	}
}
