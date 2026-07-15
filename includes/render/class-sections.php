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

	/** Image slot that degrades to a patterned placeholder when no URL is given. */
	private static function media( string $url, string $alt, string $modifier ): string {
		$empty = '' === $url;
		$cls   = 'ch-media' . ( $empty ? ' ch-media--empty' : '' ) . ( '' !== $modifier ? ' ' . $modifier : '' );
		$img   = ! $empty
			? '<img class="ch-media__img" src="' . self::e( $url ) . '" alt="' . self::e( $alt ) . '">'
			: '';
		return '<div class="' . $cls . '">' . $img . '</div>';
	}

	/** @param array{club_name:string,logo?:string} $data */
	private static function brand_mark( array $data ): string {
		$logo = $data['logo'] ?? '';
		return '' !== $logo
			? '<img class="ch-brand__logo" src="' . self::e( $logo ) . '" alt="' . self::e( $data['club_name'] ) . '">'
			: '<span class="ch-brand__mark">C</span>';
	}

	/** Up-to-two-letter initials for a photo-less avatar (first + last word). */
	private static function initials( string $name ): string {
		$parts = array_values( array_filter( preg_split( '/\s+/', trim( $name ) ) ?: array() ) );
		if ( array() === $parts ) {
			return '';
		}
		$first = mb_substr( $parts[0], 0, 1 );
		$last  = count( $parts ) > 1 ? mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) : '';
		return mb_strtoupper( $first . $last );
	}

	/**
	 * @param array{club_name:string,banner:string,banner_href:string,
	 *   nav:array<int,array{label:string,href:string}>,active:string,
	 *   login:string,login_href?:string,join:string,join_href:string,logo?:string} $data
	 */
	public static function header( array $data ): string {
		$login_href = $data['login_href'] ?? '#';
		$banner     = '';
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
		return '<a class="ch-skip" href="#ch-main">Skip to content</a>'
			. $banner
			. '<header class="ch-nav"><div class="ch-wrap ch-nav__in">'
			. '<a class="ch-brand" href="' . self::e( Blueworx_Clubhouse_Links::url( 'home' ) ) . '">' . self::brand_mark( $data ) . self::e( $data['club_name'] ) . '</a>'
			. '<nav class="ch-nav__links" aria-label="Primary">' . $links . '</nav>'
			. '<div class="ch-nav__cta">'
			. '<a class="ch-btn ch-btn--ghost" href="' . self::e( $login_href ) . '">' . self::e( $data['login'] ) . '</a>'
			. '<a class="ch-btn ch-btn--ink" href="' . self::e( $data['join_href'] ) . '">' . self::e( $data['join'] ) . '</a>'
			// No-JS disclosure menu — the same links, revealed by the hamburger below 900px.
			. '<details class="ch-nav__disc">'
			. '<summary class="ch-nav__burger" aria-label="Menu"><span class="ch-nav__burger-bars" aria-hidden="true"></span></summary>'
			. '<nav class="ch-nav__drawer" aria-label="Menu">'
			. '<a class="ch-btn ch-btn--accent ch-nav__drawer-join" href="' . self::e( $data['join_href'] ) . '">' . self::e( $data['join'] ) . '</a>'
			. $links
			. '<a class="ch-nav__link ch-nav__drawer-login" href="' . self::e( $login_href ) . '">' . self::e( $data['login'] ) . '</a>'
			. '</nav></details>'
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

	/**
	 * Home-only full-bleed hero: a background image (or toned fallback panel) with
	 * the hero content and an integrated icon quick-link row overlaid. Distinct from
	 * hero() so the four other pages that use hero() are unaffected.
	 *
	 * @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,
	 *   cta_primary:string,cta_primary_href:string,cta_secondary:string,cta_secondary_href:string,
	 *   image:string,image_alt:string,
	 *   tiles:array<int,array{label:string,href:string,icon:string}>} $data
	 */
	public static function home_hero( array $data ): string {
		$has_img = '' !== $data['image'];
		$bg      = '<div class="ch-home-hero__bg' . ( $has_img ? '' : ' ch-home-hero__bg--empty' ) . '">'
			. ( $has_img ? '<img class="ch-home-hero__img" src="' . self::e( $data['image'] ) . '" alt="' . self::e( $data['image_alt'] ) . '">' : '' )
			. '</div>';
		$tiles = '';
		foreach ( $data['tiles'] as $t ) {
			// Tiles may be owner-edited content, where optional keys can be absent —
			// an unset/unknown icon degrades to no glyph rather than a warning.
			$svg = self::TILE_ICONS[ $t['icon'] ?? '' ] ?? '';
			$ico = '' !== $svg ? '<span class="ch-home-hero__tile-ico" aria-hidden="true">' . $svg . '</span>' : '';
			$tiles .= '<a class="ch-home-hero__tile" role="listitem" href="' . self::e( $t['href'] ?? '#' ) . '">'
				. $ico
				. '<span class="ch-home-hero__tile-label">' . self::e( $t['label'] ?? '' ) . '</span>'
				. '<span class="ch-home-hero__tile-arrow" aria-hidden="true">→</span></a>';
		}
		return '<section class="ch-home-hero">'
			. $bg
			. '<div class="ch-home-hero__scrim" aria-hidden="true"></div>'
			. '<div class="ch-wrap ch-home-hero__in">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h1 class="ch-home-hero__title">' . self::e( $data['title_lead'] )
			. '<span class="ch-home-hero__hl">' . self::e( $data['title_highlight'] ) . '</span></h1>'
			. '<p class="ch-home-hero__lede">' . self::e( $data['lede'] ) . '</p>'
			. '<div class="ch-home-hero__cta">'
			. '<a class="ch-btn ch-btn--accent" href="' . self::e( $data['cta_primary_href'] ) . '">' . self::e( $data['cta_primary'] ) . '</a>'
			. '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['cta_secondary_href'] ) . '">' . self::e( $data['cta_secondary'] ) . '</a>'
			. '</div>'
			. '<div class="ch-home-hero__foot" role="list">' . $tiles . '</div>'
			. '</div></section>';
	}

	/**
	 * @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,
	 *   filter_label:string,filters:array<int,array{label:string,href:string,active:bool}>} $data
	 */
	public static function hero_filter( array $data ): string {
		$pills = '';
		foreach ( $data['filters'] as $f ) {
			$on     = ! empty( $f['active'] ) ? ' ch-filter--on' : '';
			$pills .= '<a class="ch-filter' . $on . '" href="' . self::e( $f['href'] ) . '">' . self::e( $f['label'] ) . '</a>';
		}
		return '<section class="ch-hero-f"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h1 class="ch-hero-f__title">' . self::e( $data['title_lead'] )
			. '<span class="ch-hero-f__hl">' . self::e( $data['title_highlight'] ) . '</span></h1>'
			. '<p class="ch-hero-f__lede">' . self::e( $data['lede'] ) . '</p>'
			. '<nav class="ch-filters" aria-label="' . self::e( $data['filter_label'] ) . '">' . $pills . '</nav>'
			. '</div></section>';
	}

	/** @param array<int,array{value:string,label:string,featured?:bool}> $stats */
	public static function stat_strip( array $stats ): string {
		$items = '';
		foreach ( $stats as $stat ) {
			$feature = ! empty( $stat['featured'] ) ? ' ch-stats__item--feature' : '';
			$items  .= '<div class="ch-stats__item' . $feature . '" role="listitem"><b class="ch-stats__value">' . self::e( $stat['value'] )
				. '</b><span class="ch-stats__label">' . self::e( $stat['label'] ) . '</span></div>';
		}
		return '<section class="ch-stats"><div class="ch-wrap ch-stats__in" role="list">' . $items . '</div></section>';
	}

	/** @param array<int,array{label:string,href:string}> $tiles */
	public static function quick_tiles( array $tiles ): string {
		$items = '';
		foreach ( $tiles as $t ) {
			$items .= '<a class="ch-tiles__tile" role="listitem" href="' . self::e( $t['href'] ) . '">'
				. '<span class="ch-tiles__label">' . self::e( $t['label'] ) . '</span>'
				. '<span class="ch-tiles__arrow" aria-hidden="true">→</span></a>';
		}
		return '<section class="ch-tiles-sec"><div class="ch-wrap"><div class="ch-tiles" role="list">' . $items . '</div></div></section>';
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
			. '<input type="checkbox" class="ch-ticker__pause-cb" id="ch-ticker-pause" aria-label="Pause the news ticker">'
			. '<div class="ch-ticker__viewport">' . $build( false ) . $build( true ) . '</div>'
			. '<label class="ch-ticker__pause" for="ch-ticker-pause">'
			. '<span class="ch-ticker__ico-pause" aria-hidden="true">&#10073;&#10073;</span>'
			. '<span class="ch-ticker__ico-play" aria-hidden="true">&#9654;</span></label>'
			. '</section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,link_label:string,link_href:string,
	 *   cards:array<int,array{image:string,image_alt:string,tag:string,title:string,subtitle:string}>} $data
	 */
	public static function card_grid( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$cards .= '<article class="ch-card" role="listitem">'
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
			. '<div class="ch-cards" role="list">' . $cards . '</div></div></section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,link_label:string,link_href:string,
	 *   cards:array<int,array{image:string,image_alt:string,chip:string,title:string,description:string,
	 *   stats:array<int,array{value:string,label:string}>}>} $data
	 */
	public static function stat_card_grid( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$stats = '';
			foreach ( $c['stats'] as $s ) {
				$stats .= '<div class="ch-scard__stat"><b class="ch-scard__stat-v">' . self::e( $s['value'] )
					. '</b><span class="ch-scard__stat-l">' . self::e( $s['label'] ) . '</span></div>';
			}
			$cards .= '<article class="ch-scard" role="listitem">'
				. self::media( $c['image'], $c['image_alt'], 'ch-scard__media' )
				. '<span class="ch-scard__chip">' . self::e( $c['chip'] ) . '</span>'
				. '<div class="ch-scard__body">'
				. '<h3 class="ch-scard__title">' . self::e( $c['title'] ) . '</h3>'
				. '<p class="ch-scard__desc">' . self::e( $c['description'] ) . '</p>'
				. '<div class="ch-scard__stats">' . $stats . '</div></div></article>';
		}
		$head = '';
		if ( '' !== $data['link_label'] ) {
			$head = '<div class="ch-sec__head"><div>'
				. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
				. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2></div>'
				. '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . '</a></div>';
		} else {
			$head = '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
				. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. $head
			. '<div class="ch-scards" role="list">' . $cards . '</div></div></section>';
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
				$feats .= '<li class="ch-tier__feat" role="listitem">' . self::e( $f ) . '</li>';
			}
			$cards .= '<div class="' . $cls . '" role="listitem">'
				. '<span class="ch-tier__k">' . self::e( $t['eyebrow'] ) . '</span>'
				. '<h3 class="ch-tier__name">' . self::e( $t['name'] ) . '</h3>'
				. '<div class="ch-tier__amt">' . self::e( $t['price'] ) . '<small>' . self::e( $t['period'] ) . '</small></div>'
				. '<ul class="ch-tier__feats" role="list">' . $feats . '</ul>'
				. '<a class="ch-btn ' . $btn . ' ch-tier__cta" href="' . self::e( $t['cta_href'] ) . '">' . self::e( $t['cta_label'] ) . '</a>'
				. '</div>';
		}
		return '<section class="ch-wrap ch-tiers-sec"><div class="ch-tiers" role="list">' . $cards . '</div></section>';
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
			$fx .= '<div class="ch-fx" role="listitem"><div class="ch-fx__date"><b>' . self::e( $f['day'] ) . '</b><span>' . self::e( $f['month'] ) . '</span></div>'
				. '<div class="ch-fx__body"><span class="ch-fx__comp">' . self::e( $f['competition'] ) . '</span>'
				. '<span class="ch-fx__match">' . self::e( $f['matchup'] ) . '</span></div>'
				. '<span class="ch-fx__time">' . self::e( $f['time'] ) . '</span></div>';
		}
		$rs = '';
		foreach ( $data['results'] as $r ) {
			$o    = strtolower( $r['outcome'] );
			$mod  = in_array( $o, array( 'w', 'l', 'd' ), true ) ? $o : 'd';
			$rs  .= '<div class="ch-res" role="listitem"><span class="ch-res__date">' . self::e( $r['date'] ) . '</span>'
				. '<span class="ch-res__teams">' . self::e( $r['home'] ) . ' v ' . self::e( $r['away'] ) . '</span>'
				. '<span class="ch-res__score">' . self::e( $r['score'] ) . '</span>'
				. '<span class="ch-badge ch-badge--' . $mod . '">' . self::e( $r['outcome'] ) . '</span></div>';
		}
		$ev = '';
		foreach ( $data['events'] as $e ) {
			$ev .= '<div class="ch-evt" role="listitem"><div class="ch-evt__meta"><span class="ch-evt__tag">' . self::e( $e['tag'] ) . '</span>'
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
			. '<div data-ch-tab="fixtures"><div class="ch-fx-list" role="list">' . $fx . '</div></div>'
			. '<div class="ch-tabs__panel--off" data-ch-tab="results"><div class="ch-res-list" role="list">' . $rs . '</div></div>'
			. '<div class="ch-tabs__panel--off" data-ch-tab="events"><div class="ch-evt-grid" role="list">' . $ev . '</div></div>'
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
			$cards .= '<a class="ch-news__card" role="listitem" href="#">'
				. self::media( $c['image'], $c['image_alt'], 'ch-news__media' )
				. '<div class="ch-news__meta"><span class="ch-news__tag">' . self::e( $c['tag'] ) . '</span>'
				. '<span class="ch-news__date">' . self::e( $c['date'] ) . '</span></div>'
				. '<h3 class="ch-news__title">' . self::e( $c['title'] ) . '</h3></a>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-news" role="list">' . $cards . '</div></div></section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,
	 *   cards:array<int,array{tag:string,date:string,title:string,detail:string,cta_label:string,cta_href:string}>} $data
	 */
	public static function event_grid( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$cta = '' !== $c['cta_label']
				? '<a class="ch-btn ch-btn--ghost ch-event__cta" href="' . self::e( $c['cta_href'] ) . '">' . self::e( $c['cta_label'] ) . '</a>'
				: '';
			$cards .= '<article class="ch-event" role="listitem">'
				. '<div class="ch-event__meta"><span class="ch-event__tag">' . self::e( $c['tag'] ) . '</span>'
				. '<span class="ch-event__date">' . self::e( $c['date'] ) . '</span></div>'
				. '<h3 class="ch-event__title">' . self::e( $c['title'] ) . '</h3>'
				. '<p class="ch-event__detail">' . self::e( $c['detail'] ) . '</p>'
				. $cta . '</article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-events" role="list">' . $cards . '</div></div></section>';
	}

	/**
	 * @param array{heading:string,rows:array<int,array{date:string,tag:string,title:string}>} $data
	 */
	public static function event_archive( array $data ): string {
		$rows = '';
		foreach ( $data['rows'] as $r ) {
			$rows .= '<div class="ch-archive__row" role="listitem">'
				. '<span class="ch-archive__date">' . self::e( $r['date'] ) . '</span>'
				. '<span class="ch-archive__tag">' . self::e( $r['tag'] ) . '</span>'
				. '<span class="ch-archive__title">' . self::e( $r['title'] ) . '</span></div>';
		}
		return '<section class="ch-sec ch-sec--alt"><div class="ch-wrap">'
			. '<h2 class="ch-sec__title ch-sec__title--sm">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-archive" role="list">' . $rows . '</div></div></section>';
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
			$out .= '<div class="ch-info__col" role="listitem"><div class="ch-info__label">' . self::e( $c['label'] ) . '</div>'
				. '<div class="ch-info__body">' . $lines . $link . '</div></div>';
		}
		return '<section class="ch-info"><div class="ch-wrap ch-info__in" role="list">' . $out . '</div></section>';
	}

	/** @param array{heading:string,link_label:string,link_href:string,names:array<int,string>} $data */
	public static function sponsors( array $data ): string {
		$tiles = '';
		foreach ( $data['names'] as $name ) {
			$tiles .= '<div class="ch-sponsors__tile" role="listitem">' . self::e( $name ) . '</div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head"><h2 class="ch-sec__title ch-sec__title--sm">' . self::e( $data['heading'] ) . '</h2>'
			. '<a class="ch-link" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . ' →</a></div>'
			. '<div class="ch-sponsors" role="list">' . $tiles . '</div></div></section>';
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
			. '<a class="ch-brand" href="' . self::e( Blueworx_Clubhouse_Links::url( 'home' ) ) . '"><span class="ch-brand__mark">C</span>' . self::e( $data['club_name'] ) . '</a>'
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
			$cards .= '<article class="ch-benefit" role="listitem"><span class="ch-benefit__dot" aria-hidden="true"></span>'
				. '<h3 class="ch-benefit__title">' . self::e( $c['title'] ) . '</h3>'
				. '<p class="ch-benefit__desc">' . self::e( $c['description'] ) . '</p></article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-benefits" role="list">' . $cards . '</div></div></section>';
	}

	/** @param array{eyebrow:string,heading:string,people:array<int,array{name:string,role:string,email:string}>} $data */
	public static function people_grid( array $data ): string {
		$people = '';
		foreach ( $data['people'] as $p ) {
			$email = '' !== $p['email']
				? '<a class="ch-person__email" href="mailto:' . self::e( $p['email'] ) . '">' . self::e( $p['email'] ) . '</a>' : '';
			$people .= '<article class="ch-person" role="listitem">'
				. '<div class="ch-person__avatar ch-avatar" aria-hidden="true">' . self::e( self::initials( $p['name'] ) ) . '</div>'
				. '<span class="ch-person__role">' . self::e( $p['role'] ) . '</span>'
				. '<h3 class="ch-person__name">' . self::e( $p['name'] ) . '</h3>' . $email . '</article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-people" role="list">' . $people . '</div></div></section>';
	}

	/** @param array{eyebrow:string,heading:string,milestones:array<int,array{year:string,title:string,desc:string}>} $data */
	public static function timeline( array $data ): string {
		$rows = '';
		foreach ( $data['milestones'] as $m ) {
			$rows .= '<div class="ch-milestone" role="listitem"><div class="ch-milestone__year">' . self::e( $m['year'] ) . '</div>'
				. '<div class="ch-milestone__body"><h3 class="ch-milestone__title">' . self::e( $m['title'] ) . '</h3>'
				. '<p class="ch-milestone__desc">' . self::e( $m['desc'] ) . '</p></div></div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-timeline" role="list">' . $rows . '</div></div></section>';
	}

	/**
	 * Column headers are data, not baked-in English, so a non-English club can relabel them.
	 *
	 * @param array{eyebrow:string,heading:string,included_label:string,not_included_label:string,
	 *   policies_label:string,included:array<int,string>,not_included:array<int,string>,
	 *   policies:array<int,array{title:string,desc:string}>} $data
	 */
	public static function list_split( array $data ): string {
		$yes = '';
		foreach ( $data['included'] as $item ) {
			$yes .= '<li class="ch-split__yes" role="listitem">' . self::e( $item ) . '</li>';
		}
		$no = '';
		foreach ( $data['not_included'] as $item ) {
			$no .= '<li class="ch-split__no" role="listitem">' . self::e( $item ) . '</li>';
		}
		$pol = '';
		foreach ( $data['policies'] as $p ) {
			$pol .= '<div class="ch-policy" role="listitem"><h4 class="ch-policy__title">' . self::e( $p['title'] ) . '</h4>'
				. '<p class="ch-policy__desc">' . self::e( $p['desc'] ) . '</p></div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-splits">'
			. '<div class="ch-split"><h3 class="ch-split__h">' . self::e( $data['included_label'] ) . '</h3><ul class="ch-split__list" role="list">' . $yes . '</ul></div>'
			. '<div class="ch-split"><h3 class="ch-split__h">' . self::e( $data['not_included_label'] ) . '</h3><ul class="ch-split__list" role="list">' . $no . '</ul></div>'
			. '<div class="ch-split"><h3 class="ch-split__h">' . self::e( $data['policies_label'] ) . '</h3><div class="ch-policies" role="list">' . $pol . '</div></div>'
			. '</div></div></section>';
	}

	/** @param array{eyebrow:string,heading:string,steps:array<int,array{number:string,title:string,description:string}>} $data */
	public static function step_grid( array $data ): string {
		$steps = '';
		foreach ( $data['steps'] as $s ) {
			$steps .= '<article class="ch-step" role="listitem"><span class="ch-step__num">' . self::e( $s['number'] ) . '</span>'
				. '<h3 class="ch-step__title">' . self::e( $s['title'] ) . '</h3>'
				. '<p class="ch-step__desc">' . self::e( $s['description'] ) . '</p></article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-steps" role="list">' . $steps . '</div></div></section>';
	}

	/** @param array{eyebrow:string,heading:string,items:array<int,array{question:string,answer:string,open:bool}>} $data */
	public static function faq( array $data ): string {
		$items = '';
		foreach ( $data['items'] as $it ) {
			$open   = ! empty( $it['open'] ) ? ' open' : '';
			$items .= '<details class="ch-faq__item"' . $open . '>'
				. '<summary class="ch-faq__q">' . self::e( $it['question'] ) . '<span class="ch-faq__mark" aria-hidden="true"></span></summary>'
				. '<p class="ch-faq__a">' . self::e( $it['answer'] ) . '</p></details>';
		}
		return '<section class="ch-sec"><div class="ch-wrap ch-faq-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-faq">' . $items . '</div></div></section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,name_label:string,email_label:string,enquiry_label:string,
	 *   enquiry_options:array<int,string>,message_label:string,submit_label:string,
	 *   info:array{heading:string,address:array<int,string>,email:string,phone:string,socials:array<int,string>}} $data
	 */
	public static function contact_form( array $data ): string {
		$opts = '';
		foreach ( $data['enquiry_options'] as $o ) {
			$opts .= '<option>' . self::e( $o ) . '</option>';
		}
		$addr = '';
		foreach ( $data['info']['address'] as $line ) {
			$addr .= '<span class="ch-contact__line">' . self::e( $line ) . '</span>';
		}
		$socials = '';
		foreach ( $data['info']['socials'] as $name ) {
			$socials .= '<a class="ch-contact__social" href="#" aria-label="' . self::e( $name ) . '">'
				. '<span aria-hidden="true">' . self::e( mb_substr( $name, 0, 1 ) ) . '</span></a>';
		}
		$form = '<form class="ch-contact__form" onsubmit="return false">'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( $data['name_label'] ) . '</span>'
			. '<input class="ch-field__input" type="text" name="name"></label>'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( $data['email_label'] ) . '</span>'
			. '<input class="ch-field__input" type="email" name="email"></label>'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( $data['enquiry_label'] ) . '</span>'
			. '<select class="ch-field__input" name="enquiry">' . $opts . '</select></label>'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( $data['message_label'] ) . '</span>'
			. '<textarea class="ch-field__input" name="message" rows="5"></textarea></label>'
			. '<button class="ch-btn ch-btn--accent" type="submit">' . self::e( $data['submit_label'] ) . '</button></form>';
		$tel  = preg_replace( '/\s+/', '', $data['info']['phone'] );
		$info = '<aside class="ch-contact__info"><h3 class="ch-contact__h">' . self::e( $data['info']['heading'] ) . '</h3>'
			. self::media( '', 'Map of ClubHouse', 'ch-contact__map' )
			. '<div class="ch-contact__lines">' . $addr . '</div>'
			. '<a class="ch-contact__link" href="mailto:' . self::e( $data['info']['email'] ) . '">' . self::e( $data['info']['email'] ) . '</a>'
			. '<a class="ch-contact__link" href="tel:' . self::e( $tel ) . '">' . self::e( $data['info']['phone'] ) . '</a>'
			. '<div class="ch-contact__connect">' . $socials . '</div></aside>';
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-contact">' . $form . $info . '</div></div></section>';
	}

	/**
	 * Member sign-in — a single centred card. Unlike a content section, a narrow
	 * centred column is the expected shape for an auth form, so the width cap is
	 * deliberate here (it is the only thing on the page). The heading is an <h1>:
	 * this page has no hero, so the card carries the page's main heading.
	 *
	 * @param array{eyebrow:string,heading:string,lede:string,email_label:string,
	 *   password_label:string,remember_label:string,forgot_label:string,forgot_href:string,
	 *   submit_label:string,join_prompt:string,join_label:string,join_href:string} $data
	 */
	public static function auth( array $data ): string {
		$form = '<form class="ch-auth__form" onsubmit="return false">'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( $data['email_label'] ) . '</span>'
			. '<input class="ch-field__input" type="email" name="email" autocomplete="email"></label>'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( $data['password_label'] ) . '</span>'
			. '<input class="ch-field__input" type="password" name="password" autocomplete="current-password"></label>'
			. '<div class="ch-auth__row">'
			. '<label class="ch-auth__remember"><input type="checkbox" name="remember"><span>' . self::e( $data['remember_label'] ) . '</span></label>'
			. '<a class="ch-auth__forgot" href="' . self::e( $data['forgot_href'] ) . '">' . self::e( $data['forgot_label'] ) . '</a></div>'
			. '<button class="ch-btn ch-btn--accent ch-auth__submit" type="submit">' . self::e( $data['submit_label'] ) . '</button></form>';
		return '<section class="ch-sec"><div class="ch-wrap ch-auth-wrap"><div class="ch-auth">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h1 class="ch-auth__title">' . self::e( $data['heading'] ) . '</h1>'
			. '<p class="ch-auth__lede">' . self::e( $data['lede'] ) . '</p>'
			. $form
			. '<p class="ch-auth__alt">' . self::e( $data['join_prompt'] ) . ' '
			. '<a class="ch-auth__alt-link" href="' . self::e( $data['join_href'] ) . '">' . self::e( $data['join_label'] ) . '</a></p>'
			. '</div></div></section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,
	 *   months:array<int,array{label:string,rows:array<int,array{date:string,competition:string,
	 *   matchup:string,detail:string,outcome:string}>}>} $data
	 */
	public static function calendar_months( array $data ): string {
		$months = '';
		foreach ( $data['months'] as $m ) {
			$rows = '';
			foreach ( $m['rows'] as $r ) {
				if ( '' === $r['outcome'] ) {
					$status = '<span class="ch-cal__soon">Upcoming</span>';
				} else {
					$o      = strtolower( $r['outcome'] );
					$mod    = in_array( $o, array( 'w', 'l', 'd' ), true ) ? $o : 'd';
					$status = '<span class="ch-badge ch-badge--' . $mod . '">' . self::e( $r['outcome'] ) . '</span>';
				}
				$rows .= '<div class="ch-cal__row" role="listitem">'
					. '<span class="ch-cal__date">' . self::e( $r['date'] ) . '</span>'
					. '<div class="ch-cal__body"><span class="ch-cal__comp">' . self::e( $r['competition'] ) . '</span>'
					. '<span class="ch-cal__match">' . self::e( $r['matchup'] ) . '</span></div>'
					. '<span class="ch-cal__detail">' . self::e( $r['detail'] ) . '</span>'
					. $status . '</div>';
			}
			$months .= '<div class="ch-cal__month"><h3 class="ch-cal__mlabel">' . self::e( $m['label'] ) . '</h3>'
				. '<div class="ch-cal__rows" role="list">' . $rows . '</div></div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-cal">' . $months . '</div></div></section>';
	}

	/** Task-tile icons — inline SVG, inherit colour via currentColor. */
	private const TILE_ICONS = array(
		'join'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>',
		'tour'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m16.2 7.8-2.9 6.3-6.3 2.9 2.9-6.3 6.3-2.9z"/></svg>',
		'fixtures' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
		'contact'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>',
	);

	/** Self-hosted brand mark, inherits colour via currentColor — no hex, no icon font. */
	private const FACEBOOK_ICON = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">'
		. '<path d="M22 12.06C22 6.51 17.52 2 12 2S2 6.51 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.45 2.91h-2.33V22c4.78-.79 8.44-4.94 8.44-9.94Z"/></svg>';

	/** Self-hosted brand mark, inherits colour via currentColor — no hex, no icon font. */
	private const INSTAGRAM_ICON = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
		. '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/></svg>';

	/** Self-hosted brand mark, inherits colour via currentColor — no hex, no icon font. */
	private const LINKEDIN_ICON = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">'
		. '<path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14ZM7.12 20.45H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.22.79 24 1.77 24h20.45c.98 0 1.78-.78 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z"/></svg>';

	/** Global "follow us" links — not a live/embedded feed. @param array{heading:string,lede:string,facebook_url:string,instagram_url:string,linkedin_url:string} $data */
	public static function social( array $data ): string {
		$links = array(
			array( 'label' => 'Facebook', 'url' => $data['facebook_url'], 'icon' => self::FACEBOOK_ICON ),
			array( 'label' => 'Instagram', 'url' => $data['instagram_url'], 'icon' => self::INSTAGRAM_ICON ),
			array( 'label' => 'LinkedIn', 'url' => $data['linkedin_url'], 'icon' => self::LINKEDIN_ICON ),
		);
		$out = '';
		foreach ( $links as $l ) {
			$out .= '<a class="ch-social__link" role="listitem" href="' . self::e( $l['url'] ) . '" aria-label="Follow us on ' . self::e( $l['label'] ) . '">'
				. '<span class="ch-social__icon" aria-hidden="true">' . $l['icon'] . '</span>'
				. '<span class="ch-social__label">' . self::e( $l['label'] ) . '</span></a>';
		}
		return '<section class="ch-social"><div class="ch-wrap ch-social__in">'
			. '<div class="ch-social__text"><h2 class="ch-social__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<p class="ch-social__lede">' . self::e( $data['lede'] ) . '</p></div>'
			. '<div class="ch-social__links" role="list">' . $out . '</div>'
			. '</div></section>';
	}
}
