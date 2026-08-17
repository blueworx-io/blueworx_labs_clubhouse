<?php

use PHPUnit\Framework\TestCase;

/**
 * Issue #108: About, Membership and Book a court each showed a tall grey block
 * with a picture glyph in it where the hero image should be.
 *
 * The image itself is configurable, so the demo simply had none set — but the
 * hero reserved the box anyway, because the renderer fills the alt text in by
 * default and the block counted alt text as media. Any club that chooses not
 * to use a hero image got the same empty frame.
 */
final class HeroImageBoxTest extends TestCase {

	/** @param array<string,string> $over */
	private function hero( array $over = array() ): string {
		return Blueworx_Clubhouse_Sections::hero( array_merge( array(
			'eyebrow'            => 'About the club',
			'title_lead'         => 'Fifty-two years of ',
			'title_highlight'    => 'community sport.',
			'lede'               => 'More than the game.',
			'cta_primary'        => 'Join',
			'cta_primary_href'   => '?page=membership',
			'cta_secondary'      => '',
			'cta_secondary_href' => '',
			'image'              => '',
			'image_alt'          => '',
			'image_caption'      => '',
		), $over ) );
	}

	public function test_no_image_means_no_picture_box(): void {
		$html = $this->hero( array( 'image_alt' => 'Members on the terrace' ) );
		$this->assertStringNotContainsString( 'ch-hero__media', $html );
		$this->assertStringNotContainsString( 'ch-media--empty', $html );
	}

	/**
	 * Alt text and a caption describe an image. Neither is an image, and the
	 * renderer supplies alt text whether or not one was chosen — which is how
	 * the empty frame got onto three pages in the first place.
	 */
	public function test_a_caption_alone_does_not_conjure_a_box(): void {
		$html = $this->hero( array( 'image_caption' => 'Saturday, floodlights on' ) );
		$this->assertStringNotContainsString( 'ch-hero__media', $html );
	}

	public function test_the_rest_of_the_hero_is_untouched(): void {
		$html = $this->hero( array( 'image_alt' => 'Members on the terrace' ) );
		$this->assertStringContainsString( 'class="ch-hero"', $html );
		$this->assertStringContainsString( 'community sport.', $html );
		$this->assertStringContainsString( 'More than the game.', $html );
		$this->assertStringContainsString( '?page=membership', $html );
	}

	public function test_an_image_that_is_set_still_shows(): void {
		$html = $this->hero( array(
			'image'         => 'https://example.test/terrace.jpg',
			'image_alt'     => 'Members on the terrace',
			'image_caption' => 'Saturday, floodlights on',
		) );
		$this->assertStringContainsString( 'class="ch-hero__media"', $html );
		$this->assertStringContainsString( 'src="https://example.test/terrace.jpg"', $html );
		$this->assertStringContainsString( 'alt="Members on the terrace"', $html );
		$this->assertStringContainsString( 'Saturday, floodlights on', $html );
	}

	/** @return array<int,array<int,string>> */
	public static function pages(): array {
		return array( array( 'about' ), array( 'membership' ), array( 'booking' ) );
	}

	/**
	 * The three pages named in the review, rendered as a club with no hero
	 * images chosen — which is every club on day one.
	 */
	#[PHPUnit\Framework\Attributes\DataProvider( 'pages' )]
	public function test_the_named_pages_no_longer_reserve_an_empty_frame( string $page ): void {
		// Bookings only exists where LatePoint does, and a page that renders
		// nothing would pass this vacuously.
		Blueworx_Clubhouse_Integrations::set_detector( static fn( string $tag ): bool => true );
		try {
			$body = Blueworx_Clubhouse_Test_Site::page( $page );
		} finally {
			Blueworx_Clubhouse_Integrations::set_detector( null );
		}
		$this->assertStringContainsString( 'ch-hero', $body, "{$page} renders a hero at all" );
		$this->assertStringNotContainsString( 'ch-hero__media', $body, "{$page} reserves no empty hero frame" );
	}
}
