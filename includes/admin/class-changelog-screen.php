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

		$tags = (string) ( $model['role_tags'] ?? '' );

		if ( array() === $releases ) {
			// The changelog ships with the plugin, so this means the file is not
			// where it should be — worth saying plainly rather than showing an
			// empty page that looks like nothing has ever changed.
			return Blueworx_Clubhouse_Admin_Shell::open( 'Clubhouse · What\'s new', 'What\'s new', '', $tags )
				. '<div class="bw-notice bw-notice--warning"><i class="bw-icon bw-notice__icon" data-lucide="triangle-alert"></i>'
				. '<div class="bw-notice__body"><p class="bw-notice__text">The list of changes could not be read. '
				. 'Your site is working normally — only this screen is affected.</p></div></div>'
				. Blueworx_Clubhouse_Admin_Shell::close();
		}

		$out = Blueworx_Clubhouse_Admin_Shell::open(
			'Clubhouse · What\'s new',
			'What\'s new',
			'Every update to Clubhouse, newest first, and what each one changed for your club. '
				. 'You are running version ' . $running . '.',
			$tags
		);

		$recent = array_slice( $releases, 0, self::RECENT );
		$rest   = array_slice( $releases, self::RECENT );

		foreach ( $recent as $release ) {
			$out .= self::release( $release, $running );
		}

		if ( array() !== $rest ) {
			$older = '';
			foreach ( $rest as $release ) {
				$older .= self::release( $release, $running );
			}
			$out .= '<details class="bw-accordion"><summary class="bw-accordion__head">'
				. '<span class="bw-accordion__title">Everything before that</span>'
				. '<span class="bw-accordion__sub">' . count( $rest ) . ' more</span></summary>'
				. '<div class="bw-accordion__body">' . $older . '</div></details>';
		}

		return $out . Blueworx_Clubhouse_Admin_Shell::close();
	}

	/** @param array<string,mixed> $release */
	private static function release( array $release, string $running ): string {
		$version = (string) $release['version'];
		$current = Blueworx_Clubhouse_Changelog::is_current( $version, $running );

		if ( (bool) $release['internal'] ) {
			$body = '<p class="bw-fieldnote">Nothing you would notice — this release changed how the plugin is built and tested.</p>';
		} else {
			$body = '<ul>';
			foreach ( (array) $release['notes'] as $note ) {
				$body .= '<li>' . Blueworx_Clubhouse_Changelog::note_html( (string) $note ) . '</li>';
			}
			$body .= '</ul>';
		}

		// The one fact an owner is most often here to check — is what I am
		// reading the thing I am running? — reads as a badge on the panel.
		$badge = $current
			? '<div class="bw-card__actions"><span class="bw-badge bw-badge--accent">You are on this version</span></div>'
			: '';

		return '<div class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">'
			. '<p class="bw-card__eyebrow">Release</p>'
			. '<h2 class="bw-card__title">Version ' . self::esc( $version ) . '</h2></div>'
			. $badge . '</div><div class="bw-card__body">' . $body . '</div></div>';
	}
}
