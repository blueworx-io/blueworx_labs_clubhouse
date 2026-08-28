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
		$out = Blueworx_Clubhouse_Admin_Shell::open(
			'Clubhouse · Search and sharing',
			'How your site looks in search',
			self::intro( (string) $model['deferring_to'] ),
			(string) ( $model['role_tags'] ?? '' )
		);

		foreach ( $model['pages'] as $page ) {
			$out .= self::page_card( $page );
		}

		return $out . Blueworx_Clubhouse_Admin_Shell::close();
	}

	/** Plain text — the shell escapes it and puts it in the page header's lede. */
	private static function intro( string $deferring_to ): string {
		if ( '' !== $deferring_to ) {
			// Not an error and not a nag: the club made a reasonable choice, and the
			// report still runs, because the checks below are about the page itself.
			return 'You have ' . $deferring_to . ' installed, so ClubHouse leaves the '
				. 'search and social tags to it and adds none of its own. Everything below still applies — these are checks on the '
				. 'pages themselves, whichever plugin writes the tags.';
		}
		return 'ClubHouse describes each page to search engines and fills in the preview card people '
			. 'see when your pages are shared. This is what it has to work with, page by page. Nothing here is broken — these are the '
			. 'things worth tidying when you have a minute.';
	}

	/**
	 * @param array{label:string,url:string,status:string,checks:array<int,array{key:string,label:string,status:string,detail:string}>} $page
	 */
	private static function page_card( array $page ): string {
		$body = '';
		if ( '' !== $page['url'] ) {
			$body .= '<p class="bw-fieldnote"><a href="' . self::esc( $page['url'] ) . '">' . self::esc( $page['url'] ) . '</a></p>';
		}
		$body .= '<table class="bw-table"><tbody>';
		foreach ( $page['checks'] as $check ) {
			$body .= '<tr><th scope="row"><span class="bw-table__primary">' . self::esc( $check['label'] ) . '</span>'
				. '<span class="bw-table__sub">' . self::word( $check['status'] ) . '</span></th>'
				. '<td>' . self::esc( $check['detail'] ) . '</td></tr>';
		}
		$body .= '</tbody></table>';

		// The status reads as a badge rather than an eyebrow now: it carries a
		// state, which is what a badge is for, and the design system gives it a
		// colour that matches the state without a traffic-light score.
		$out  = '<div class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">'
			. '<h2 class="bw-card__title">' . self::esc( $page['label'] ) . '</h2></div>'
			. '<div class="bw-card__actions">' . self::badge( $page['status'] ) . '</div></div>'
			. '<div class="bw-card__body">' . $body . '</div></div>';
		return $out;
	}

	private static function badge( string $status ): string {
		switch ( $status ) {
			case Blueworx_Clubhouse_Seo::FAIL:
				return '<span class="bw-badge bw-badge--danger">Needs attention</span>';
			case Blueworx_Clubhouse_Seo::WARN:
				return '<span class="bw-badge bw-badge--warning">Could be better</span>';
		}
		return '<span class="bw-badge bw-badge--success">All good</span>';
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
