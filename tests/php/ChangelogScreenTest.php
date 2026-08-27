<?php

use PHPUnit\Framework\TestCase;

/** What an owner is shown when they open What's new. */
final class ChangelogScreenTest extends TestCase {

	/** @return array<int,array<string,mixed>> */
	private function releases( int $count ): array {
		$out = array();
		for ( $i = $count; $i > 0; $i-- ) {
			$out[] = array( 'version' => '0.' . $i . '.0', 'notes' => array( 'Something changed in ' . $i . '.' ), 'internal' => false );
		}
		return $out;
	}

	/** @param array<int,array<string,mixed>> $releases */
	private function render( array $releases, string $running = '0.9.0' ): string {
		return Blueworx_Clubhouse_Changelog_Screen::render( array( 'running' => $running, 'releases' => $releases ) );
	}

	public function test_the_version_being_run_is_called_out(): void {
		$html = $this->render( $this->releases( 3 ), '0.2.0' );
		$this->assertStringContainsString( 'Version 0.2.0', $html );
		$this->assertStringContainsString( 'You are on this version', $html );
	}

	public function test_a_long_history_does_not_all_land_at_once(): void {
		// 165 releases in one column is not a screen anybody reads.
		$html = $this->render( $this->releases( 30 ) );
		$this->assertStringContainsString( '<details', $html );
		$this->assertStringContainsString( 'Everything before that (22 more)', $html );
	}

	public function test_a_short_history_is_not_folded_away(): void {
		$html = $this->render( $this->releases( 3 ) );
		$this->assertStringNotContainsString( '<details', $html );
	}

	public function test_every_release_is_on_the_page_however_long_the_list(): void {
		$html = $this->render( $this->releases( 30 ) );
		foreach ( $this->releases( 30 ) as $release ) {
			$this->assertStringContainsString( 'Version ' . $release['version'], $html );
		}
	}

	public function test_a_release_that_changed_nothing_visible_says_so_plainly(): void {
		$html = $this->render( array(
			array( 'version' => '0.9.1', 'notes' => array( 'Test suite only — nothing changes on your site.' ), 'internal' => true ),
		) );
		$this->assertStringContainsString( 'Nothing you would notice', $html );
		// The developer wording is not repeated at them underneath it.
		$this->assertStringNotContainsString( 'Test suite only', $html );
	}

	public function test_an_unreadable_changelog_says_so_rather_than_looking_empty(): void {
		$html = $this->render( array() );
		$this->assertStringContainsString( 'could not be read', $html );
		$this->assertStringContainsString( 'Your site is working normally', $html );
	}

	public function test_markup_in_a_note_is_read_not_run(): void {
		$html = $this->render( array(
			array( 'version' => '0.9.0', 'notes' => array( '<script>alert(1)</script> and **this** stays bold.' ), 'internal' => false ),
		) );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '<strong>this</strong>', $html );
	}
}
