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

	/** @param array{club:string,intro:string,chapters:array<int,array<string,mixed>>} $model */
	public static function render( array $model ): string {
		$out  = '<div class="wrap clubhouse-wrap"><div class="clubhouse-setup">';
		$out .= '<header class="clubhouse-head"><div class="clubhouse-head__titles">'
			. '<p class="clubhouse-eyebrow">Clubhouse · User guide</p>'
			. '<h1 class="clubhouse-head__h1">How ClubHouse works</h1></div></header>';
		$out .= '<p class="clubhouse-step__lede">' . self::esc( (string) $model['intro'] ) . '</p>';

		$out .= self::contents( $model['chapters'] );
		foreach ( $model['chapters'] as $chapter ) {
			$out .= self::chapter( $chapter );
		}

		return $out . '</div></div>';
	}

	/** @param array<int,array<string,mixed>> $chapters */
	private static function contents( array $chapters ): string {
		if ( count( $chapters ) < 2 ) {
			return '';
		}
		$out = '<nav class="clubhouse-chips" aria-label="Guide contents">';
		foreach ( $chapters as $chapter ) {
			$out .= '<a class="clubhouse-roletag" href="#guide-' . self::esc( (string) $chapter['key'] ) . '">'
				. self::esc( (string) $chapter['title'] ) . '</a>';
		}
		return $out . '</nav>';
	}

	/** @param array<string,mixed> $chapter */
	private static function chapter( array $chapter ): string {
		$out  = '<div class="clubhouse-step" id="guide-' . self::esc( (string) $chapter['key'] ) . '">';
		$out .= '<p class="clubhouse-step__k">Guide</p>';
		$out .= '<h2 class="clubhouse-step__h">' . self::esc( (string) $chapter['title'] ) . '</h2>';
		$out .= '<p class="clubhouse-step__lede">' . self::esc( (string) $chapter['lede'] ) . '</p>';

		foreach ( (array) $chapter['entries'] as $entry ) {
			$out .= self::entry( $entry );
		}
		return $out . '</div>';
	}

	/** @param array<string,mixed> $entry */
	private static function entry( array $entry ): string {
		$state = (string) $entry['state'];
		$out   = '<details class="clubhouse-guide-entry" open><summary class="clubhouse-guide-entry__head">'
			. '<span class="clubhouse-table__name">' . self::esc( (string) $entry['title'] ) . '</span>'
			. ( '' !== $state ? '<span class="clubhouse-roletag">' . self::esc( $state ) . '</span>' : '' )
			. '</summary>';

		foreach ( (array) $entry['body'] as $paragraph ) {
			$out .= '<p class="clubhouse-help">' . self::esc( (string) $paragraph ) . '</p>';
		}

		$steps = (array) $entry['steps'];
		if ( array() !== $steps ) {
			$out .= '<ol class="clubhouse-guide-steps">';
			foreach ( $steps as $step ) {
				$out .= '<li>' . self::esc( (string) $step ) . '</li>';
			}
			$out .= '</ol>';
		}

		$url = (string) $entry['url'];
		if ( '' !== $url ) {
			$out .= '<p class="clubhouse-help"><a class="button" href="' . self::esc( $url ) . '">Open '
				. self::esc( (string) $entry['title'] ) . '</a></p>';
		}

		return $out . '</details>';
	}
}
