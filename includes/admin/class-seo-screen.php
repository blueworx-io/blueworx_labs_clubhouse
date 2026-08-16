<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML for the SEO report: one card per page, each listing what is right
 * and what is worth fixing, in plain sentences.
 *
 * There is no score and no traffic-light total. A number invites chasing the
 * number; the point is the sentence next to the thing that is wrong.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Seo_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * $role_tags is prebuilt markup from Access_Screen, empty for anyone but an
	 * administrator — the controller decides that, so this class stays WP-free.
	 *
	 * @param array{deferring_to:string,role_tags?:string,pages:array<int,array{label:string,url:string,status:string,checks:array<int,array{key:string,label:string,status:string,detail:string}>}>} $model
	 */
	public static function render( array $model ): string {
		$out  = '<div class="wrap clubhouse-wrap"><div class="clubhouse-setup">';
		$out .= '<header class="clubhouse-head"><div class="clubhouse-head__titles">'
			. '<p class="clubhouse-eyebrow">Clubhouse · Search &amp; sharing</p>'
			. '<h1 class="clubhouse-head__h1">How your site looks in search</h1>'
			. (string) ( $model['role_tags'] ?? '' ) . '</div></header>';

		$out .= self::intro( (string) $model['deferring_to'] );

		foreach ( $model['pages'] as $page ) {
			$out .= self::page_card( $page );
		}

		return $out . '</div></div>';
	}

	private static function intro( string $deferring_to ): string {
		if ( '' !== $deferring_to ) {
			// Not an error and not a nag: the club made a reasonable choice, and the
			// report still runs, because the checks below are about the page itself.
			return '<p class="clubhouse-step__lede">You have ' . self::esc( $deferring_to ) . ' installed, so ClubHouse leaves the '
				. 'search and social tags to it and adds none of its own. Everything below still applies — these are checks on the '
				. 'pages themselves, whichever plugin writes the tags.</p>';
		}
		return '<p class="clubhouse-step__lede">ClubHouse describes each page to search engines and fills in the preview card people '
			. 'see when your pages are shared. This is what it has to work with, page by page. Nothing here is broken — these are the '
			. 'things worth tidying when you have a minute.</p>';
	}

	/**
	 * @param array{label:string,url:string,status:string,checks:array<int,array{key:string,label:string,status:string,detail:string}>} $page
	 */
	private static function page_card( array $page ): string {
		$out  = '<div class="clubhouse-step">'
			. '<p class="clubhouse-step__k">' . self::badge( $page['status'] ) . '</p>'
			. '<h2 class="clubhouse-step__h">' . self::esc( $page['label'] ) . '</h2>';
		if ( '' !== $page['url'] ) {
			$out .= '<p class="clubhouse-help"><a href="' . self::esc( $page['url'] ) . '">' . self::esc( $page['url'] ) . '</a></p>';
		}
		$out .= '<table class="clubhouse-table"><tbody>';
		foreach ( $page['checks'] as $check ) {
			$out .= '<tr><th scope="row"><span class="clubhouse-table__name">' . self::esc( $check['label'] ) . '</span>'
				. '<span class="clubhouse-table__sub">' . self::word( $check['status'] ) . '</span></th>'
				. '<td>' . self::esc( $check['detail'] ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	private static function badge( string $status ): string {
		switch ( $status ) {
			case Blueworx_Clubhouse_Seo::FAIL:
				return 'Needs attention';
			case Blueworx_Clubhouse_Seo::WARN:
				return 'Could be better';
		}
		return 'All good';
	}

	private static function word( string $status ): string {
		switch ( $status ) {
			case Blueworx_Clubhouse_Seo::FAIL:
				return 'Needs attention';
			case Blueworx_Clubhouse_Seo::WARN:
				return 'Worth a look';
		}
		return 'Fine';
	}
}
