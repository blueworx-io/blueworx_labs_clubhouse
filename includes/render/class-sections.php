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

	/** Image slot that degrades to a tonal placeholder when no URL is given. */
	private static function media( string $url, string $alt, string $modifier ): string {
		$cls = 'ch-media' . ( '' !== $modifier ? ' ' . $modifier : '' );
		$img = '' !== $url
			? '<img class="ch-media__img" src="' . self::e( $url ) . '" alt="' . self::e( $alt ) . '">'
			: '';
		return '<div class="' . $cls . '">' . $img . '</div>';
	}

	/**
	 * @param array{club_name:string,banner:string,banner_href:string,
	 *   nav:array<int,array{label:string,href:string}>,active:string,
	 *   login:string,join:string,join_href:string} $data
	 */
	public static function header( array $data ): string {
		$banner = '';
		if ( '' !== $data['banner'] ) {
			$banner = '<div class="ch-banner"><div class="ch-wrap ch-banner__in">'
				. '<a class="ch-banner__link" href="' . self::e( $data['banner_href'] ) . '">'
				. self::e( $data['banner'] ) . '</a></div></div>';
		}
		$links = '';
		foreach ( $data['nav'] as $item ) {
			$active  = $item['href'] === $data['active'] ? ' ch-nav__link--active' : '';
			$links  .= '<a class="ch-nav__link' . $active . '" href="' . self::e( $item['href'] ) . '">'
				. self::e( $item['label'] ) . '</a>';
		}
		return $banner
			. '<header class="ch-nav"><div class="ch-wrap ch-nav__in">'
			. '<a class="ch-brand" href="?page=home"><span class="ch-brand__mark">C</span>' . self::e( $data['club_name'] ) . '</a>'
			. '<nav class="ch-nav__links">' . $links . '</nav>'
			. '<div class="ch-nav__cta">'
			. '<a class="ch-btn ch-btn--ghost" href="#">' . self::e( $data['login'] ) . '</a>'
			. '<a class="ch-btn ch-btn--ink" href="' . self::e( $data['join_href'] ) . '">' . self::e( $data['join'] ) . '</a>'
			. '</div></div></header>';
	}

	/**
	 * @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,
	 *   cta_primary:string,cta_primary_href:string,cta_secondary:string,
	 *   cta_secondary_href:string,image:string,image_alt:string,image_caption:string} $data
	 */
	public static function hero( array $data ): string {
		$caption = '' !== $data['image_caption']
			? '<div class="ch-hero__pill"><i class="ch-hero__pill-dot"></i>' . self::e( $data['image_caption'] ) . '</div>'
			: '';
		$has_media = '' !== $data['image'] || '' !== $data['image_alt'] || '' !== $data['image_caption'];
		$media     = $has_media
			? '<div class="ch-hero__media">' . self::media( $data['image'], $data['image_alt'], '' ) . $caption . '</div>'
			: '';
		return '<section class="ch-hero"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h1 class="ch-hero__title">' . self::e( $data['title_lead'] )
			. '<span class="ch-hero__hl">' . self::e( $data['title_highlight'] ) . '</span></h1>'
			. '<div class="ch-hero__sub">'
			. '<p class="ch-hero__lede">' . self::e( $data['lede'] ) . '</p>'
			. '<div class="ch-hero__cta">'
			. '<a class="ch-btn ch-btn--accent" href="' . self::e( $data['cta_primary_href'] ) . '">' . self::e( $data['cta_primary'] ) . '</a>'
			. '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['cta_secondary_href'] ) . '">' . self::e( $data['cta_secondary'] ) . '</a>'
			. '</div></div>'
			. $media
			. '</div></section>';
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

	/** @param array<int,array{label:string,href:string}> $tiles */
	public static function quick_tiles( array $tiles ): string {
		$items = '';
		foreach ( $tiles as $t ) {
			$items .= '<a class="ch-tiles__tile" href="' . self::e( $t['href'] ) . '">'
				. '<span class="ch-tiles__label">' . self::e( $t['label'] ) . '</span>'
				. '<span class="ch-tiles__arrow" aria-hidden="true">→</span></a>';
		}
		return '<section class="ch-tiles-sec"><div class="ch-wrap"><div class="ch-tiles">' . $items . '</div></div></section>';
	}

	/** @param array<int,string> $items */
	public static function ticker( array $items ): string {
		$build = static function ( bool $hidden ) use ( $items ): string {
			$out = '<div class="ch-ticker__track"' . ( $hidden ? ' aria-hidden="true"' : '' ) . '>';
			foreach ( $items as $item ) {
				$out .= '<span class="ch-ticker__item"><i class="ch-ticker__dot"></i>' . self::e( $item ) . '</span>';
			}
			return $out . '</div>';
		};
		return '<section class="ch-ticker"><div class="ch-ticker__label">Club news</div>'
			. '<div class="ch-ticker__viewport">' . $build( false ) . $build( true ) . '</div></section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,link_label:string,link_href:string,
	 *   cards:array<int,array{image:string,image_alt:string,tag:string,title:string,subtitle:string}>} $data
	 */
	public static function card_grid( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$cards .= '<article class="ch-card">'
				. self::media( $c['image'], $c['image_alt'], 'ch-card__media' )
				. '<div class="ch-card__scrim"></div>'
				. '<span class="ch-card__tag">' . self::e( $c['tag'] ) . '</span>'
				. '<div class="ch-card__body"><h3 class="ch-card__title">' . self::e( $c['title'] ) . '</h3>'
				. '<p class="ch-card__sub">' . self::e( $c['subtitle'] ) . '</p></div></article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head"><div>'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2></div>'
			. '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . '</a></div>'
			. '<div class="ch-cards">' . $cards . '</div></div></section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,image:string,image_alt:string,
	 *   cta_label:string,cta_href:string} $data
	 */
	public static function image_band( array $data ): string {
		return '<section class="ch-band-img">'
			. self::media( $data['image'], $data['image_alt'], 'ch-band-img__media' )
			. '<div class="ch-band-img__scrim"></div>'
			. '<div class="ch-wrap ch-band-img__in"><div>'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-band-img__title">' . self::e( $data['heading'] ) . '</h2></div>'
			. '<a class="ch-btn ch-btn--accent" href="' . self::e( $data['cta_href'] ) . '">' . self::e( $data['cta_label'] ) . '</a>'
			. '</div></section>';
	}

	/**
	 * @param array{variant:string,eyebrow:string,heading:string,lede:string,
	 *   cta_label:string,cta_href:string} $data variant: 'accent' | 'ink'
	 */
	public static function band( array $data ): string {
		$mod     = 'ink' === $data['variant'] ? 'ch-band--ink' : 'ch-band--accent';
		$btn     = 'ink' === $data['variant'] ? 'ch-btn--accent' : 'ch-btn--ink';
		$eyebrow = '' !== $data['eyebrow']
			? '<span class="ch-eyebrow ch-eyebrow--band">' . self::e( $data['eyebrow'] ) . '</span>' : '';
		$lede    = '' !== $data['lede'] ? '<p class="ch-band__lede">' . self::e( $data['lede'] ) . '</p>' : '';
		return '<section class="ch-wrap ch-band-wrap"><div class="ch-band ' . $mod . '">'
			. $eyebrow
			. '<h2 class="ch-band__title">' . self::e( $data['heading'] ) . '</h2>'
			. $lede
			. '<a class="ch-btn ' . $btn . '" href="' . self::e( $data['cta_href'] ) . '">' . self::e( $data['cta_label'] ) . '</a>'
			. '</div></section>';
	}

	/**
	 * @param array<int,array{eyebrow:string,name:string,price:string,period:string,
	 *   features:array<int,string>,recommended:bool,cta_label:string,cta_href:string}> $tiers
	 */
	public static function tier_grid( array $tiers ): string {
		$cards = '';
		foreach ( $tiers as $t ) {
			$cls   = $t['recommended'] ? 'ch-tier ch-tier--pop' : 'ch-tier';
			$btn   = $t['recommended'] ? 'ch-btn--accent' : 'ch-btn--ghost';
			$feats = '';
			foreach ( $t['features'] as $f ) {
				$feats .= '<li class="ch-tier__feat">' . self::e( $f ) . '</li>';
			}
			$cards .= '<div class="' . $cls . '">'
				. '<span class="ch-tier__k">' . self::e( $t['eyebrow'] ) . '</span>'
				. '<h3 class="ch-tier__name">' . self::e( $t['name'] ) . '</h3>'
				. '<div class="ch-tier__amt">' . self::e( $t['price'] ) . '<small>' . self::e( $t['period'] ) . '</small></div>'
				. '<ul class="ch-tier__feats">' . $feats . '</ul>'
				. '<a class="ch-btn ' . $btn . ' ch-tier__cta" href="' . self::e( $t['cta_href'] ) . '">' . self::e( $t['cta_label'] ) . '</a>'
				. '</div>';
		}
		return '<section class="ch-wrap"><div class="ch-tiers">' . $cards . '</div></section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,
	 *   fixtures:array<int,array{month:string,day:string,competition:string,time:string,matchup:string}>,
	 *   results:array<int,array{date:string,home:string,away:string,score:string,outcome:string}>,
	 *   events:array<int,array{tag:string,date:string,title:string,detail:string}>} $data
	 */
	public static function activity_tabs( array $data ): string {
		$fx = '';
		foreach ( $data['fixtures'] as $f ) {
			$fx .= '<div class="ch-fx"><div class="ch-fx__date"><b>' . self::e( $f['day'] ) . '</b><span>' . self::e( $f['month'] ) . '</span></div>'
				. '<div class="ch-fx__body"><span class="ch-fx__comp">' . self::e( $f['competition'] ) . '</span>'
				. '<span class="ch-fx__match">' . self::e( $f['matchup'] ) . '</span></div>'
				. '<span class="ch-fx__time">' . self::e( $f['time'] ) . '</span></div>';
		}
		$rs = '';
		foreach ( $data['results'] as $r ) {
			$o    = strtolower( $r['outcome'] );
			$mod  = in_array( $o, array( 'w', 'l', 'd' ), true ) ? $o : 'd';
			$rs  .= '<div class="ch-res"><span class="ch-res__date">' . self::e( $r['date'] ) . '</span>'
				. '<span class="ch-res__teams">' . self::e( $r['home'] ) . ' v ' . self::e( $r['away'] ) . '</span>'
				. '<span class="ch-res__score">' . self::e( $r['score'] ) . '</span>'
				. '<span class="ch-badge ch-badge--' . $mod . '">' . self::e( $r['outcome'] ) . '</span></div>';
		}
		$ev = '';
		foreach ( $data['events'] as $e ) {
			$ev .= '<div class="ch-evt"><div class="ch-evt__meta"><span class="ch-evt__tag">' . self::e( $e['tag'] ) . '</span>'
				. '<span class="ch-evt__date">' . self::e( $e['date'] ) . '</span></div>'
				. '<h3 class="ch-evt__title">' . self::e( $e['title'] ) . '</h3>'
				. '<p class="ch-evt__detail">' . self::e( $e['detail'] ) . '</p></div>';
		}
		$tabs = '';
		foreach ( array( 'fixtures' => 'Fixtures', 'results' => 'Results', 'events' => 'Events' ) as $key => $label ) {
			$on    = 'fixtures' === $key ? ' ch-tabs__btn--on' : '';
			$tabs .= '<button type="button" class="ch-tabs__btn' . $on . '" data-ch-tabbtn="' . $key . '">' . self::e( $label ) . '</button>';
		}
		return '<section class="ch-sec ch-sec--alt"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-tabs" data-ch-tabs>'
			. '<div class="ch-tabs__bar">' . $tabs . '</div>'
			. '<div data-ch-tab="fixtures"><div class="ch-fx-list">' . $fx . '</div></div>'
			. '<div class="ch-tabs__panel--off" data-ch-tab="results"><div class="ch-res-list">' . $rs . '</div></div>'
			. '<div class="ch-tabs__panel--off" data-ch-tab="events"><div class="ch-evt-grid">' . $ev . '</div></div>'
			. '</div></div>'
			. '<script>(function(){var r=document.querySelector("[data-ch-tabs]");if(!r)return;'
			. 'r.querySelectorAll("[data-ch-tabbtn]").forEach(function(b){b.addEventListener("click",function(){'
			. 'var k=b.getAttribute("data-ch-tabbtn");'
			. 'r.querySelectorAll("[data-ch-tabbtn]").forEach(function(x){x.classList.toggle("ch-tabs__btn--on",x===b)});'
			. 'r.querySelectorAll("[data-ch-tab]").forEach(function(p){p.classList.toggle("ch-tabs__panel--off",p.getAttribute("data-ch-tab")!==k)});'
			. '})})})();</script></section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,
	 *   cards:array<int,array{image:string,image_alt:string,tag:string,date:string,title:string}>} $data
	 */
	public static function news_cards( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$cards .= '<a class="ch-news__card" href="#">'
				. self::media( $c['image'], $c['image_alt'], 'ch-news__media' )
				. '<div class="ch-news__meta"><span class="ch-news__tag">' . self::e( $c['tag'] ) . '</span>'
				. '<span class="ch-news__date">' . self::e( $c['date'] ) . '</span></div>'
				. '<h3 class="ch-news__title">' . self::e( $c['title'] ) . '</h3></a>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-news">' . $cards . '</div></div></section>';
	}

	/** @param array<int,array{label:string,lines:array<int,string>,link_label:string,link_href:string}> $cols */
	public static function info_strip( array $cols ): string {
		$out = '';
		foreach ( $cols as $c ) {
			$lines = '';
			foreach ( $c['lines'] as $line ) {
				$lines .= '<span class="ch-info__line">' . self::e( $line ) . '</span>';
			}
			$link = '' !== $c['link_label']
				? '<a class="ch-info__link" href="' . self::e( $c['link_href'] ) . '">' . self::e( $c['link_label'] ) . ' →</a>' : '';
			$out .= '<div class="ch-info__col"><div class="ch-info__label">' . self::e( $c['label'] ) . '</div>'
				. '<div class="ch-info__body">' . $lines . $link . '</div></div>';
		}
		return '<section class="ch-info"><div class="ch-wrap ch-info__in">' . $out . '</div></section>';
	}

	/** @param array{heading:string,link_label:string,link_href:string,names:array<int,string>} $data */
	public static function sponsors( array $data ): string {
		$tiles = '';
		foreach ( $data['names'] as $name ) {
			$tiles .= '<div class="ch-sponsors__tile">' . self::e( $name ) . '</div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head"><h2 class="ch-sec__title ch-sec__title--sm">' . self::e( $data['heading'] ) . '</h2>'
			. '<a class="ch-link" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . ' →</a></div>'
			. '<div class="ch-sponsors">' . $tiles . '</div></div></section>';
	}

	/**
	 * @param array{club_name:string,tagline:string,socials:array<int,string>,
	 *   columns:array<int,array{title:string,links:array<int,array{label:string,href:string}>}>,
	 *   newsletter:array{heading:string,lede:string,placeholder:string,cta:string},
	 *   legal:array<int,array{label:string,href:string}>} $data
	 */
	public static function footer( array $data ): string {
		$socials = '';
		foreach ( $data['socials'] as $name ) {
			$glyph    = self::e( mb_substr( $name, 0, 1 ) );
			$socials .= '<a class="ch-footer__social" href="#" aria-label="' . self::e( $name ) . '"><span aria-hidden="true">' . $glyph . '</span></a>';
		}
		$cols = '';
		foreach ( $data['columns'] as $col ) {
			$links = '';
			foreach ( $col['links'] as $l ) {
				$links .= '<a class="ch-footer__link" href="' . self::e( $l['href'] ) . '">' . self::e( $l['label'] ) . '</a>';
			}
			$cols .= '<div class="ch-footer__col"><h4 class="ch-footer__h">' . self::e( $col['title'] ) . '</h4>' . $links . '</div>';
		}
		$nl = '<div class="ch-footer__col ch-footer__nl"><h4 class="ch-footer__h">' . self::e( $data['newsletter']['heading'] ) . '</h4>'
			. '<p class="ch-footer__lede">' . self::e( $data['newsletter']['lede'] ) . '</p>'
			. '<form class="ch-footer__form" onsubmit="return false">'
			. '<input class="ch-footer__input" type="email" placeholder="' . self::e( $data['newsletter']['placeholder'] ) . '" aria-label="Email address">'
			. '<button class="ch-btn ch-btn--accent" type="submit">' . self::e( $data['newsletter']['cta'] ) . '</button></form></div>';
		$legal = '';
		foreach ( $data['legal'] as $l ) {
			$legal .= '<a class="ch-footer__legal-link" href="' . self::e( $l['href'] ) . '">' . self::e( $l['label'] ) . '</a>';
		}
		return '<footer class="ch-footer"><div class="ch-wrap">'
			. '<div class="ch-footer__grid">'
			. '<div class="ch-footer__brand-col">'
			. '<a class="ch-brand" href="?page=home"><span class="ch-brand__mark">C</span>' . self::e( $data['club_name'] ) . '</a>'
			. '<p class="ch-footer__tagline">' . self::e( $data['tagline'] ) . '</p>'
			. '<div class="ch-footer__socials">' . $socials . '</div></div>'
			. $cols . $nl . '</div>'
			. '<div class="ch-footer__legal">' . $legal . '</div>'
			. '</div></footer>';
	}

	/** @param array{eyebrow:string,heading:string,cards:array<int,array{title:string,description:string}>} $data */
	public static function benefit_grid( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$cards .= '<article class="ch-benefit"><span class="ch-benefit__dot" aria-hidden="true"></span>'
				. '<h3 class="ch-benefit__title">' . self::e( $c['title'] ) . '</h3>'
				. '<p class="ch-benefit__desc">' . self::e( $c['description'] ) . '</p></article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-benefits">' . $cards . '</div></div></section>';
	}

	/** @param array{eyebrow:string,heading:string,people:array<int,array{name:string,role:string,email:string}>} $data */
	public static function people_grid( array $data ): string {
		$people = '';
		foreach ( $data['people'] as $p ) {
			$email = '' !== $p['email']
				? '<a class="ch-person__email" href="mailto:' . self::e( $p['email'] ) . '">' . self::e( $p['email'] ) . '</a>' : '';
			$people .= '<article class="ch-person">'
				. self::media( '', $p['name'], 'ch-person__avatar' )
				. '<span class="ch-person__role">' . self::e( $p['role'] ) . '</span>'
				. '<h3 class="ch-person__name">' . self::e( $p['name'] ) . '</h3>' . $email . '</article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-people">' . $people . '</div></div></section>';
	}

	/** @param array{eyebrow:string,heading:string,milestones:array<int,array{year:string,title:string,desc:string}>} $data */
	public static function timeline( array $data ): string {
		$rows = '';
		foreach ( $data['milestones'] as $m ) {
			$rows .= '<div class="ch-milestone"><div class="ch-milestone__year">' . self::e( $m['year'] ) . '</div>'
				. '<div class="ch-milestone__body"><h3 class="ch-milestone__title">' . self::e( $m['title'] ) . '</h3>'
				. '<p class="ch-milestone__desc">' . self::e( $m['desc'] ) . '</p></div></div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-timeline">' . $rows . '</div></div></section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,included:array<int,string>,not_included:array<int,string>,
	 *   policies:array<int,array{title:string,desc:string}>} $data
	 */
	public static function list_split( array $data ): string {
		$yes = '';
		foreach ( $data['included'] as $item ) {
			$yes .= '<li class="ch-split__yes">' . self::e( $item ) . '</li>';
		}
		$no = '';
		foreach ( $data['not_included'] as $item ) {
			$no .= '<li class="ch-split__no">' . self::e( $item ) . '</li>';
		}
		$pol = '';
		foreach ( $data['policies'] as $p ) {
			$pol .= '<div class="ch-policy"><h4 class="ch-policy__title">' . self::e( $p['title'] ) . '</h4>'
				. '<p class="ch-policy__desc">' . self::e( $p['desc'] ) . '</p></div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-splits">'
			. '<div class="ch-split"><h3 class="ch-split__h">Included</h3><ul class="ch-split__list">' . $yes . '</ul></div>'
			. '<div class="ch-split"><h3 class="ch-split__h">Not included</h3><ul class="ch-split__list">' . $no . '</ul></div>'
			. '<div class="ch-split"><h3 class="ch-split__h">Good to know</h3><div class="ch-policies">' . $pol . '</div></div>'
			. '</div></div></section>';
	}

	/** @param array{eyebrow:string,heading:string,steps:array<int,array{number:string,title:string,description:string}>} $data */
	public static function step_grid( array $data ): string {
		$steps = '';
		foreach ( $data['steps'] as $s ) {
			$steps .= '<article class="ch-step"><span class="ch-step__num">' . self::e( $s['number'] ) . '</span>'
				. '<h3 class="ch-step__title">' . self::e( $s['title'] ) . '</h3>'
				. '<p class="ch-step__desc">' . self::e( $s['description'] ) . '</p></article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-steps">' . $steps . '</div></div></section>';
	}
}
