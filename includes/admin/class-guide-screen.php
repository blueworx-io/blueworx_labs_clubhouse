<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML for the User Guide screen.
 *
 * Chapters are <details> elements rather than a tabbed panel: the guide is read
 * by someone looking for one answer, so everything has to be findable with the
 * browser's own find-in-page, which cannot see inside a hidden tab. Open by
 * default for the same reason.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Guide_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * $role_tags is prebuilt markup from Access_Screen, empty for anyone but an
	 * administrator — the controller decides that, so this class stays WP-free.
	 *
	 * @param array{club:string,intro:string,role_tags?:string,chapters:array<int,array<string,mixed>>} $model
	 */
	public static function render( array $model ): string {
		$out = Blueworx_Clubhouse_Admin_Shell::open(
			'Clubhouse · User guide',
			'How ClubHouse works',
			(string) $model['intro'],
			(string) ( $model['role_tags'] ?? '' )
		);

		$out .= self::contents( $model['chapters'] );
		foreach ( $model['chapters'] as $chapter ) {
			$out .= self::chapter( $chapter );
		}

		return $out . Blueworx_Clubhouse_Admin_Shell::close();
	}

	/** @param array<int,array<string,mixed>> $chapters */
	private static function contents( array $chapters ): string {
		if ( count( $chapters ) < 2 ) {
			return '';
		}
		$out = '<nav class="bw-chips" aria-label="Guide contents">';
		foreach ( $chapters as $chapter ) {
			$out .= '<a class="bw-chip" href="#guide-' . self::esc( (string) $chapter['key'] ) . '">'
				. self::esc( (string) $chapter['title'] ) . '</a>';
		}
		return $out . '</nav>';
	}

	/** @param array<string,mixed> $chapter */
	private static function chapter( array $chapter ): string {
		$body = '';
		foreach ( (array) $chapter['entries'] as $entry ) {
			$body .= self::entry( $entry );
		}

		// The anchor the contents list jumps to has to be the panel itself, so
		// the heading lands at the top of the viewport rather than halfway down
		// a card. Admin_Shell::card() has no id, so the anchor wraps it.
		return '<div id="guide-' . self::esc( (string) $chapter['key'] ) . '">'
			. Blueworx_Clubhouse_Admin_Shell::card(
				'Guide',
				(string) $chapter['title'],
				(string) $chapter['lede'],
				$body
			)
			. '</div>';
	}

	/** @param array<string,mixed> $entry */
	private static function entry( array $entry ): string {
		$state = (string) $entry['state'];
		$out   = '<details class="bw-accordion" open><summary class="bw-accordion__head">'
			. '<span class="bw-accordion__title">' . self::esc( (string) $entry['title'] ) . '</span>'
			. ( '' !== $state ? '<span class="bw-badge bw-badge--neutral">' . self::esc( $state ) . '</span>' : '' )
			. '</summary><div class="bw-accordion__body">';

		foreach ( (array) $entry['body'] as $paragraph ) {
			$out .= '<p class="bw-fieldnote">' . self::esc( (string) $paragraph ) . '</p>';
		}

		$steps = (array) $entry['steps'];
		if ( array() !== $steps ) {
			$out .= '<ol class="bw-steps">';
			foreach ( $steps as $step ) {
				$out .= '<li class="bw-step">' . self::esc( (string) $step ) . '</li>';
			}
			$out .= '</ol>';
		}

		$url = (string) $entry['url'];
		if ( '' !== $url ) {
			$out .= '<p><a class="bw-btn bw-btn--secondary" href="' . self::esc( $url ) . '">Open '
				. self::esc( (string) $entry['title'] ) . '</a></p>';
		}

		return $out . '</div></details>';
	}
}
