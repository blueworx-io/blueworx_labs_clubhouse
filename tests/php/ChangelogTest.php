<?php

use PHPUnit\Framework\TestCase;

/**
 * The changelog, read as something a club secretary can be shown.
 *
 * The file is written for that reader already — it is the house rule for
 * changelog wording — so this reads it rather than keeping a second set of
 * notes that would drift from what was actually released.
 */
final class ChangelogTest extends TestCase {

	private const SAMPLE = <<<'MD'
# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## 0.91.2

- Development tooling only — nothing changes on your site. The local test setup can now run a real shop.

## 0.91.0

- **Members can now change their own name, email and password** — there was no way in before.
- A second thing that changed in the same release.

## 0.86.0

Your club's pages are now real WordPress pages.
MD;

	/** @return array<int,array<string,mixed>> */
	private function releases(): array {
		return Blueworx_Clubhouse_Changelog::parse( self::SAMPLE );
	}

	public function test_every_released_version_is_found(): void {
		$this->assertSame(
			array( '0.91.2', '0.91.0', '0.86.0' ),
			array_column( $this->releases(), 'version' )
		);
	}

	public function test_versions_stay_newest_first(): void {
		// The file is written newest first and read in file order — a release
		// list that reordered itself would disagree with the file it came from.
		$this->assertSame( '0.91.2', $this->releases()[0]['version'] );
	}

	public function test_a_release_carries_everything_that_changed_in_it(): void {
		$this->assertSame(
			array(
				'**Members can now change their own name, email and password** — there was no way in before.',
				'A second thing that changed in the same release.',
			),
			$this->releases()[1]['notes']
		);
	}

	public function test_a_note_written_as_a_paragraph_is_not_lost(): void {
		// Older entries are plain paragraphs rather than bullets.
		$this->assertSame( array( "Your club's pages are now real WordPress pages." ), $this->releases()[2]['notes'] );
	}

	public function test_the_preamble_is_not_mistaken_for_a_release(): void {
		$versions = array_column( $this->releases(), 'version' );
		$this->assertNotContains( 'Changelog', $versions );
		$this->assertCount( 3, $versions );
	}

	public function test_a_release_that_changed_nothing_visible_says_so(): void {
		// Dressing up a tooling release as a feature is how a list like this
		// stops being worth reading.
		$this->assertTrue( $this->releases()[0]['internal'] );
		$this->assertFalse( $this->releases()[1]['internal'] );
	}

	public function test_the_real_shipped_changelog_reads_cleanly(): void {
		// The file that actually ships, not a sample: every release must have a
		// version somebody can match against what they are running, and at least
		// one thing to say about it.
		$releases = Blueworx_Clubhouse_Changelog::parse(
			(string) file_get_contents( dirname( __DIR__, 2 ) . '/CHANGELOG.md' )
		);
		$this->assertGreaterThan( 100, count( $releases ) );
		foreach ( $releases as $release ) {
			$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $release['version'] );
			$this->assertNotSame( array(), $release['notes'], $release['version'] . ' has nothing to say' );
		}
	}

	public function test_bold_survives_as_emphasis_and_nothing_else_does(): void {
		// The lead of each note is bold in the file and should stay emphasised;
		// anything that looks like markup must not become markup.
		$html = Blueworx_Clubhouse_Changelog::note_html( '**Members can change things** — and <script>alert(1)</script> cannot.' );
		$this->assertStringContainsString( '<strong>Members can change things</strong>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_a_link_in_a_note_is_kept_as_its_words(): void {
		// Two entries carry a markdown link. An owner reading this screen wants
		// the sentence, not a URL they cannot follow from wp-admin.
		$this->assertSame(
			'See the upgrade record for what happens.',
			Blueworx_Clubhouse_Changelog::note_html( 'See [the upgrade record](docs/upgrades/x.md) for what happens.' )
		);
	}

	public function test_backticks_read_as_ordinary_words(): void {
		$this->assertSame( 'Run npm run build to check.', Blueworx_Clubhouse_Changelog::note_html( 'Run `npm run build` to check.' ) );
	}

	public function test_the_version_being_run_is_findable_in_the_list(): void {
		$this->assertTrue( Blueworx_Clubhouse_Changelog::is_current( '0.91.0', '0.91.0' ) );
		$this->assertFalse( Blueworx_Clubhouse_Changelog::is_current( '0.91.2', '0.91.0' ) );
	}

	public function test_a_version_not_in_the_file_marks_nothing_as_current(): void {
		// A club running a build that never made it into the file should see no
		// release wrongly flagged as theirs.
		foreach ( $this->releases() as $release ) {
			$this->assertFalse( Blueworx_Clubhouse_Changelog::is_current( $release['version'], '9.9.9' ) );
		}
	}
}
