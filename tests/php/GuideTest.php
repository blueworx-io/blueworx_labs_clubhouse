<?php
// tests/php/GuideTest.php

use PHPUnit\Framework\TestCase;

/**
 * The guide is derived from the site, not written out by hand. These pin that:
 * change the site and the guide changes, without anyone editing prose.
 */
final class GuideTest extends TestCase {

	/** @return array<string,mixed> */
	private function site( array $over = array() ): array {
		return array_merge(
			array(
				'club_name'   => 'Marlow Community SC',
				'looks'       => array(
					array( 'name' => 'Court Side', 'description' => 'Bright and sporty.', 'active' => true ),
					array( 'name' => 'Floodlight', 'description' => 'Dark and dramatic.', 'active' => false ),
				),
				'pages'       => array(
					array(
						'key'      => 'home',
						'label'    => 'Home',
						'visible'  => true,
						'sections' => array(
							array( 'label' => 'Hero', 'visible' => true ),
							array( 'label' => 'Sponsors', 'visible' => false ),
						),
					),
				),
				'screens'     => array(
					array( 'label' => 'Clubhouse Setup', 'description' => 'Base look, branding and page visibility.', 'url' => '/wp-admin/setup' ),
				),
				'collections' => array(
					array( 'plural' => 'Teams', 'description' => 'The teams within each sport.', 'count' => 4, 'url' => '/wp-admin/teams' ),
				),
				'setup_url'   => '/wp-admin/setup',
				'content_url' => '/wp-admin/content',
			),
			$over
		);
	}

	/** @return array<string,array<string,mixed>> chapter key => chapter */
	private function chapters( array $site ): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Guide::build( $site )['chapters'] as $chapter ) {
			$out[ $chapter['key'] ] = $chapter;
		}
		return $out;
	}

	private function text( array $chapter ): string {
		$text = '';
		foreach ( $chapter['entries'] as $entry ) {
			$text .= $entry['title'] . ' ' . implode( ' ', $entry['body'] ) . ' ' . implode( ' ', $entry['steps'] ) . ' ' . $entry['state'] . ' ';
		}
		return $text;
	}

	public function test_a_page_added_to_the_site_gets_a_guide_entry_on_its_own(): void {
		$site   = $this->site();
		$site['pages'][] = array( 'key' => 'blog', 'label' => 'News', 'visible' => true, 'sections' => array() );

		$titles = array_column( $this->chapters( $site )['pages']['entries'], 'title' );
		$this->assertContains( 'News', $titles );
	}

	public function test_a_switched_off_page_is_reported_as_off_and_says_how_to_bring_it_back(): void {
		$site = $this->site();
		$site['pages'][0]['visible'] = false;

		$entry = $this->chapters( $site )['pages']['entries'][0];
		$this->assertSame( 'Switched off', $entry['state'] );
		$this->assertStringContainsString( 'Visibility', implode( ' ', $entry['body'] ) );
	}

	/** The commonest "where has it gone" question, answered before it is asked. */
	public function test_hidden_sections_are_named_and_reassured_about(): void {
		$body = implode( ' ', $this->chapters( $this->site() )['pages']['entries'][0]['body'] );

		$this->assertStringContainsString( 'Sponsors', $body );
		$this->assertStringContainsString( 'Nothing has been deleted', $body );
	}

	public function test_only_the_screens_this_user_can_open_are_listed(): void {
		$titles = array_column( $this->chapters( $this->site() )['screens']['entries'], 'title' );

		$this->assertSame( array( 'Clubhouse Setup' ), $titles );
		// Nothing invented: a screen the controller did not hand over is not named.
		$this->assertStringNotContainsString( 'Import', $this->text( $this->chapters( $this->site() )['screens'] ) );
	}

	public function test_the_active_look_is_named_and_the_alternatives_offered(): void {
		$text = $this->text( $this->chapters( $this->site() )['look'] );

		$this->assertStringContainsString( 'Court Side', $text );
		$this->assertStringContainsString( 'Floodlight', $text );
	}

	public function test_an_empty_collection_says_so_rather_than_showing_a_bare_zero(): void {
		$site = $this->site();
		$site['collections'][0]['count'] = 0;

		$entry = $this->chapters( $site )['collections']['entries'][0];
		$this->assertSame( '0 items', $entry['state'] );
		$this->assertStringContainsString( 'none yet', implode( ' ', $entry['body'] ) );
	}

	public function test_a_chapter_with_nothing_in_it_is_dropped_rather_than_left_empty(): void {
		$chapters = $this->chapters( $this->site( array( 'collections' => array(), 'screens' => array() ) ) );

		$this->assertArrayNotHasKey( 'collections', $chapters );
		$this->assertArrayNotHasKey( 'screens', $chapters );
		// The chapters that stand on their own are still there.
		$this->assertArrayHasKey( 'content', $chapters );
		$this->assertArrayHasKey( 'look', $chapters );
	}

	public function test_every_chapter_carries_a_key_a_title_and_entries(): void {
		foreach ( Blueworx_Clubhouse_Guide::build( $this->site() )['chapters'] as $chapter ) {
			$this->assertNotSame( '', $chapter['key'] );
			$this->assertNotSame( '', $chapter['title'] );
			$this->assertNotSame( array(), $chapter['entries'] );
		}
	}

	public function test_the_screen_escapes_what_it_is_given(): void {
		$site = $this->site();
		$site['pages'][0]['label'] = 'Home <script>alert(1)</script>';

		$html = Blueworx_Clubhouse_Guide_Screen::render( Blueworx_Clubhouse_Guide::build( $site ) );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * Find-in-page is how somebody looks for one answer in a reference, and it
	 * cannot see inside a closed disclosure or a hidden tab.
	 */
	public function test_the_screen_renders_every_chapter_open(): void {
		$html = Blueworx_Clubhouse_Guide_Screen::render( Blueworx_Clubhouse_Guide::build( $this->site() ) );

		$this->assertSame( 0, substr_count( $html, '<details class="clubhouse-guide-entry">' ) );
		$this->assertGreaterThan( 0, substr_count( $html, '<details class="clubhouse-guide-entry" open>' ) );
	}
}
