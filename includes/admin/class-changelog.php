<?php
// includes/admin/class-changelog.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shipped changelog, read as a list of releases.
 *
 * A club owner had no way to find out what a release changed. The record
 * existed — CHANGELOG.md, written for exactly that reader, because that is the
 * house rule for changelog wording — but it lived in a repository they cannot
 * reach.
 *
 * So this reads the file that ships rather than keeping a second set of notes
 * beside it. Two copies of the same sentences drift, and the copy an owner
 * reads would be the one nobody remembered to update.
 *
 * Pure: the file is handed in. Everything here can be tested against the real
 * changelog without a WordPress runtime.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Changelog {

	/**
	 * The phrase a release uses to say it changed nothing a club can see.
	 *
	 * Established wording in the file rather than a new convention: entries have
	 * said "Test suite only — nothing changes on your site" since 0.87.1. Marking
	 * those honestly is what stops the list reading as though every release
	 * brought something, which is how a list like this stops being worth opening.
	 */
	private const INVISIBLE = 'nothing changes on your site';

	/**
	 * Every release in the file, newest first — which is the order it is written
	 * in, and the order it is read back in. No sorting: a list that reordered
	 * itself would disagree with the file it came from, and the file is right.
	 *
	 * @return array<int,array{version:string,notes:array<int,string>,internal:bool}>
	 */
	public static function parse( string $markdown ): array {
		$releases = array();
		$current  = null;

		foreach ( preg_split( '/\R/', $markdown ) ?: array() as $raw ) {
			$line = trim( $raw );

			if ( 1 === preg_match( '/^##\s+v?(\d+\.\d+\.\d+)\s*$/', $line, $m ) ) {
				if ( null !== $current ) {
					$releases[] = self::finish( $current );
				}
				$current = array( 'version' => $m[1], 'notes' => array() );
				continue;
			}
			// The file's title, and any heading that is not a version — neither
			// is a release, and both appear before the first one.
			if ( null === $current || '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			// Bullets are the usual shape; the older entries are plain
			// paragraphs. Both are one thing that changed.
			$releases_note = 0 === strpos( $line, '- ' ) ? substr( $line, 2 ) : $line;
			$current['notes'][] = trim( $releases_note );
		}

		if ( null !== $current ) {
			$releases[] = self::finish( $current );
		}
		return $releases;
	}

	/**
	 * One release, with the question answered that only its notes can answer.
	 *
	 * @param array{version:string,notes:array<int,string>} $release
	 * @return array{version:string,notes:array<int,string>,internal:bool}
	 */
	private static function finish( array $release ): array {
		$internal = false;
		foreach ( $release['notes'] as $note ) {
			if ( false !== stripos( $note, self::INVISIBLE ) ) {
				$internal = true;
				break;
			}
		}
		return array(
			'version'  => $release['version'],
			'notes'    => $release['notes'],
			'internal' => $internal,
		);
	}

	/**
	 * One note as safe HTML.
	 *
	 * Bold survives, because the lead of every note is bold in the file and it
	 * is what makes a long list skimmable. Nothing else does: links become their
	 * own words, since a path into a repository is no use from wp-admin, and
	 * code ticks become ordinary words. Everything is escaped first, so a note
	 * that happens to contain markup is read, not run.
	 */
	public static function note_html( string $note ): string {
		$safe = htmlspecialchars( trim( $note ), ENT_QUOTES, 'UTF-8' );
		$safe = (string) preg_replace( '/\[([^\]]+)\]\([^)]*\)/', '$1', $safe );
		$safe = (string) preg_replace( '/`([^`]+)`/', '$1', $safe );
		return (string) preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $safe );
	}

	/** Whether a listed release is the one this site is running. */
	public static function is_current( string $version, string $running ): bool {
		return '' !== trim( $running ) && trim( $version ) === trim( $running );
	}
}
