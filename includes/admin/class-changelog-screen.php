<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML for the What's New screen.
 *
 * A hundred and sixty-five releases is a lot to put in front of somebody who
 * wants to know what changed last week. The recent ones are laid out in full;
 * everything before that is behind one <details>, closed, so the history is
 * there without being the first thing anybody meets.
 *
 * Releases that changed nothing a club can see are kept in the list and marked,
 * rather than hidden. A gap in the version numbers reads as something missing;
 * "nothing you would notice" reads as an answer.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Changelog_Screen {

	/** How many releases are laid out before the rest is folded away. */
	private const RECENT = 8;

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * @param array{running:string,releases:array<int,array<string,mixed>>,role_tags?:string} $model
	 */
	public static function render( array $model ): string {
		$releases = $model['releases'];
		$running  = (string) $model['running'];

		$out  = '<div class="wrap clubhouse-wrap"><div class="clubhouse-setup">';
		$out .= '<header class="clubhouse-head"><div class="clubhouse-head__titles">'
			. '<p class="clubhouse-eyebrow">Clubhouse · What\'s new</p>'
			. '<h1 class="clubhouse-head__h1">What\'s new</h1>'
			. (string) ( $model['role_tags'] ?? '' ) . '</div></header>';

		if ( array() === $releases ) {
			// The changelog ships with the plugin, so this means the file is not
			// where it should be — worth saying plainly rather than showing an
			// empty page that looks like nothing has ever changed.
			$out .= '<p class="clubhouse-step__lede">The list of changes could not be read. '
				. 'Your site is working normally — only this screen is affected.</p>';
			return $out . '</div></div>';
		}

		$out .= '<p class="clubhouse-step__lede">Every update to Clubhouse, newest first, and what each one changed for your club. '
			. 'You are running version ' . self::esc( $running ) . '.</p>';

		$recent = array_slice( $releases, 0, self::RECENT );
		$rest   = array_slice( $releases, self::RECENT );

		$out .= '<div class="clubhouse-step">';
		foreach ( $recent as $release ) {
			$out .= self::release( $release, $running );
		}
		$out .= '</div>';

		if ( array() !== $rest ) {
			$out .= '<details class="clubhouse-step"><summary class="clubhouse-step__h">'
				. 'Everything before that (' . count( $rest ) . ' more)</summary>';
			foreach ( $rest as $release ) {
				$out .= self::release( $release, $running );
			}
			$out .= '</details>';
		}

		return $out . '</div></div>';
	}

	/** @param array<string,mixed> $release */
	private static function release( array $release, string $running ): string {
		$version = (string) $release['version'];
		$current = Blueworx_Clubhouse_Changelog::is_current( $version, $running );

		$out = '<section class="clubhouse-release">';
		$out .= '<h2 class="clubhouse-step__h">Version ' . self::esc( $version );
		if ( $current ) {
			// The one fact an owner is most often here to check: is what I am
			// reading the thing I am running?
			$out .= ' <span class="clubhouse-roletag">You are on this version</span>';
		}
		$out .= '</h2>';

		if ( (bool) $release['internal'] ) {
			$out .= '<p class="clubhouse-help">Nothing you would notice — this release changed how the plugin is built and tested.</p>';
			return $out . '</section>';
		}

		$out .= '<ul class="clubhouse-release__notes">';
		foreach ( (array) $release['notes'] as $note ) {
			$out .= '<li>' . Blueworx_Clubhouse_Changelog::note_html( (string) $note ) . '</li>';
		}
		return $out . '</ul></section>';
	}
}
