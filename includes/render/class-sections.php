<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Skin-agnostic section renderers. Each returns semantic HTML using only ch-*
 * classes — no colours, fonts, radii or look slugs — so any Base Look styles the
 * same markup. All interpolated text is escaped here (the render path owns output
 * escaping). WordPress and the preview both render these same strings.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Sections {

	private static function e( string $s ): string {
		return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
	}

	/** @param array{club_name:string,nav:array<int,string>,cta:string} $data */
	public static function header( array $data ): string {
		$links = '';
		foreach ( $data['nav'] as $label ) {
			$links .= '<a class="ch-nav__link" href="#">' . self::e( $label ) . '</a>';
		}
		return '<header class="ch-nav"><div class="ch-wrap ch-nav__in">'
			. '<a class="ch-brand" href="#"><span class="ch-brand__mark">C</span>' . self::e( $data['club_name'] ) . '</a>'
			. '<nav class="ch-nav__links">' . $links . '</nav>'
			. '<a class="ch-btn ch-btn--ink" href="#">' . self::e( $data['cta'] ) . '</a>'
			. '</div></header>';
	}

	/** @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,cta_primary:string,cta_secondary:string} $data */
	public static function hero( array $data ): string {
		return '<section class="ch-hero"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h1 class="ch-hero__title">' . self::e( $data['title_lead'] )
			. '<span class="ch-hero__hl">' . self::e( $data['title_highlight'] ) . '</span></h1>'
			. '<div class="ch-hero__sub">'
			. '<p class="ch-hero__lede">' . self::e( $data['lede'] ) . '</p>'
			. '<div class="ch-hero__cta">'
			. '<a class="ch-btn ch-btn--accent" href="#">' . self::e( $data['cta_primary'] ) . '</a>'
			. '<a class="ch-btn ch-btn--ghost" href="#">' . self::e( $data['cta_secondary'] ) . '</a>'
			. '</div></div></div></section>';
	}

	/** @param array<int,array{value:string,label:string}> $stats */
	public static function stat_strip( array $stats ): string {
		$items = '';
		foreach ( $stats as $stat ) {
			$items .= '<div class="ch-stats__item"><b class="ch-stats__value">' . self::e( $stat['value'] )
				. '</b><span class="ch-stats__label">' . self::e( $stat['label'] ) . '</span></div>';
		}
		return '<section class="ch-stats"><div class="ch-wrap ch-stats__in">' . $items . '</div></section>';
	}

	/** @param array{club_name:string,tagline:string} $data */
	public static function footer( array $data ): string {
		return '<footer class="ch-footer"><div class="ch-wrap">'
			. '<a class="ch-brand" href="#"><span class="ch-brand__mark">C</span>' . self::e( $data['club_name'] ) . '</a>'
			. '<p class="ch-footer__tagline">' . self::e( $data['tagline'] ) . '</p>'
			. '</div></footer>';
	}
}
