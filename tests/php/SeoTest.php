<?php
// tests/php/SeoTest.php

use PHPUnit\Framework\TestCase;

/**
 * The meta a page emits, and the report on what a rendered page actually says.
 */
final class SeoTest extends TestCase {

	/** @return array<string,mixed> */
	private function ctx( array $over = array() ): array {
		return array_merge(
			array(
				'title'        => 'Membership — Marlow Community SC',
				'description'  => 'Join the club: what membership costs, what it includes, and how to sign up before the season starts.',
				'url'          => 'https://club.example/membership/',
				'site_name'    => 'Marlow Community SC',
				'type'         => 'website',
				'image'        => 'https://club.example/logo.png',
				'image_width'  => 1200,
				'image_height' => 630,
				'image_alt'    => 'Marlow Community SC',
				'locale'       => 'en_GB',
			),
			$over
		);
	}

	/** @return array<string,string> key => content */
	private function flatten( array $tags ): array {
		$out = array();
		foreach ( $tags as $tag ) {
			$out[ $tag['key'] ] = $tag['value'];
		}
		return $out;
	}

	public function test_open_graph_and_twitter_tags_are_emitted(): void {
		$tags = $this->flatten( Blueworx_Clubhouse_Seo::tags( $this->ctx() ) );

		foreach ( array( 'og:title', 'og:description', 'og:type', 'og:url', 'og:site_name', 'og:image' ) as $key ) {
			$this->assertArrayHasKey( $key, $tags );
		}
		$this->assertSame( '1200', $tags['og:image:width'] );
		$this->assertSame( '630', $tags['og:image:height'] );
		$this->assertSame( 'summary_large_image', $tags['twitter:card'] );
		$this->assertSame( $tags['og:title'], $tags['twitter:title'] );
	}

	/**
	 * An empty og:image reads to a scraper as an image that failed to load, and
	 * it shows a broken card rather than falling back to text.
	 */
	public function test_a_club_with_no_logo_emits_no_image_tags_at_all(): void {
		$tags = $this->flatten( Blueworx_Clubhouse_Seo::tags( $this->ctx( array( 'image' => '' ) ) ) );

		$this->assertArrayNotHasKey( 'og:image', $tags );
		$this->assertArrayNotHasKey( 'og:image:width', $tags );
		$this->assertArrayNotHasKey( 'twitter:image', $tags );
		$this->assertSame( 'summary', $tags['twitter:card'] );
	}

	public function test_description_is_cut_at_a_word_boundary(): void {
		$long = str_repeat( 'Marlow Community Sports Club welcomes new members every season. ', 6 );
		$desc = Blueworx_Clubhouse_Seo::description( $long );

		$this->assertLessThanOrEqual( Blueworx_Clubhouse_Seo::DESC_MAX + 1, mb_strlen( $desc ) );
		$this->assertStringEndsWith( '…', $desc );
		$this->assertStringNotContainsString( '  ', $desc );
	}

	public function test_description_strips_markup_and_collapses_whitespace(): void {
		$this->assertSame(
			'Hello there',
			Blueworx_Clubhouse_Seo::description( "<p>Hello   <strong>there</strong></p>\n" )
		);
	}

	public function test_rendered_tags_escape_their_content(): void {
		$html = Blueworx_Clubhouse_Seo::render(
			array( array( 'attr' => 'property', 'key' => 'og:title', 'value' => 'Marlow "Vagrants" & co' ) )
		);
		$this->assertStringContainsString( '&quot;Vagrants&quot;', $html );
		$this->assertStringContainsString( '&amp; co', $html );
	}

	public function test_inspect_reads_the_facts_out_of_rendered_markup(): void {
		$html = '<html><head><title>Membership</title>'
			. '<meta name="description" content="Join the club.">'
			. '<link rel="canonical" href="https://club.example/membership/">'
			. '</head><body><h1>Join</h1><h2>Tiers</h2>'
			. '<img src="a.jpg" alt="Team photo"><img src="b.jpg"><img src="c.jpg" alt="">'
			. '</body></html>';

		$doc = Blueworx_Clubhouse_Seo::inspect( $html );

		$this->assertSame( 'Membership', $doc['title'] );
		$this->assertSame( 'Join the club.', $doc['description'] );
		$this->assertSame( 'https://club.example/membership/', $doc['canonical'] );
		$this->assertSame( 1, $doc['h1'] );
		$this->assertSame( 3, $doc['images'] );
		// alt="" is correct markup for a decorative image, so only the one with no
		// alt attribute at all counts as missing.
		$this->assertSame( 1, $doc['no_alt'] );
		$this->assertFalse( $doc['noindex'] );
	}

	public function test_noindex_is_detected(): void {
		$doc = Blueworx_Clubhouse_Seo::inspect( '<meta name="robots" content="noindex, nofollow">' );
		$this->assertTrue( $doc['noindex'] );
	}

	/** @return array<string,mixed> */
	private function doc( array $over = array() ): array {
		return array_merge(
			array(
				'title'       => 'Membership — Marlow Community Sports Club',
				'description' => str_repeat( 'a', 130 ),
				'canonical'   => 'https://club.example/membership/',
				'h1'          => 1,
				'images'      => 4,
				'no_alt'      => 0,
				'noindex'     => false,
			),
			$over
		);
	}

	/** @return array<string,string> key => status */
	private function statuses( array $doc ): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Seo::checks( $doc ) as $check ) {
			$out[ $check['key'] ] = $check['status'];
		}
		return $out;
	}

	public function test_a_well_formed_page_passes_every_check(): void {
		$this->assertSame(
			array_fill_keys( array( 'title', 'description', 'canonical', 'h1', 'alt', 'robots' ), Blueworx_Clubhouse_Seo::PASS ),
			$this->statuses( $this->doc() )
		);
	}

	public function test_missing_essentials_fail_rather_than_warn(): void {
		$s = $this->statuses( $this->doc( array( 'title' => '', 'description' => '', 'h1' => 0, 'no_alt' => 2 ) ) );

		$this->assertSame( Blueworx_Clubhouse_Seo::FAIL, $s['title'] );
		$this->assertSame( Blueworx_Clubhouse_Seo::FAIL, $s['description'] );
		$this->assertSame( Blueworx_Clubhouse_Seo::FAIL, $s['h1'] );
		$this->assertSame( Blueworx_Clubhouse_Seo::FAIL, $s['alt'] );
	}

	public function test_lengths_outside_the_working_range_only_warn(): void {
		$s = $this->statuses( $this->doc( array( 'title' => 'Join', 'description' => 'Short.' ) ) );

		$this->assertSame( Blueworx_Clubhouse_Seo::WARN, $s['title'] );
		$this->assertSame( Blueworx_Clubhouse_Seo::WARN, $s['description'] );
	}

	public function test_more_than_one_main_heading_warns(): void {
		$this->assertSame( Blueworx_Clubhouse_Seo::WARN, $this->statuses( $this->doc( array( 'h1' => 3 ) ) )['h1'] );
	}

	public function test_a_relative_canonical_fails_and_an_absent_one_warns(): void {
		$this->assertSame( Blueworx_Clubhouse_Seo::FAIL, $this->statuses( $this->doc( array( 'canonical' => '/membership/' ) ) )['canonical'] );
		$this->assertSame( Blueworx_Clubhouse_Seo::WARN, $this->statuses( $this->doc( array( 'canonical' => '' ) ) )['canonical'] );
	}

	/** Hiding a site from search can be deliberate, so it is surfaced, not failed. */
	public function test_noindex_is_surfaced_as_a_warning(): void {
		$this->assertSame( Blueworx_Clubhouse_Seo::WARN, $this->statuses( $this->doc( array( 'noindex' => true ) ) )['robots'] );
	}

	public function test_a_pages_status_is_its_worst_check(): void {
		$this->assertSame( Blueworx_Clubhouse_Seo::PASS, Blueworx_Clubhouse_Seo::worst( Blueworx_Clubhouse_Seo::checks( $this->doc() ) ) );
		$this->assertSame( Blueworx_Clubhouse_Seo::WARN, Blueworx_Clubhouse_Seo::worst( Blueworx_Clubhouse_Seo::checks( $this->doc( array( 'h1' => 2 ) ) ) ) );
		$this->assertSame( Blueworx_Clubhouse_Seo::FAIL, Blueworx_Clubhouse_Seo::worst( Blueworx_Clubhouse_Seo::checks( $this->doc( array( 'h1' => 2, 'title' => '' ) ) ) ) );
	}

	/**
	 * Two plugins both writing og:title is worse than either doing it alone, so
	 * a site with a dedicated SEO plugin gets nothing from us.
	 */
	public function test_a_dedicated_seo_plugin_takes_over_entirely(): void {
		$this->assertTrue( Blueworx_Clubhouse_Seo_Head::defers_to( array( 'WPSEO_Options' ) ) );
		$this->assertTrue( Blueworx_Clubhouse_Seo_Head::defers_to( array( 'RankMath' ) ) );
		$this->assertFalse( Blueworx_Clubhouse_Seo_Head::defers_to( array() ) );
		$this->assertFalse( Blueworx_Clubhouse_Seo_Head::defers_to( array( 'Some_Other_Plugin' ) ) );
	}
}
