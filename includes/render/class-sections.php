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

	/**
	 * Escape a value for output, exactly once.
	 *
	 * Text reaches here already entity-encoded often enough that escaping blind
	 * printed the entity itself on the page: WordPress hands back titles and
	 * excerpts with &#8217; for an apostrophe and &#038; for an ampersand, and a
	 * headline read "Mental &#038; Physical Challenges to Overcome". Decoding
	 * first makes this idempotent — text that is already plain is unchanged, and
	 * text that arrives encoded is encoded once rather than twice.
	 */
	private static function e( string $s ): string {
		return htmlspecialchars( html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * A slot holding another plugin's shortcode — SureCart, SureDash, LatePoint,
	 * SureForms. The expanded markup is emitted UNESCAPED; this is the single
	 * place in the render path where that happens, and it is the whole purpose of
	 * the section. See Blueworx_Clubhouse_Shortcodes::expand() for what keeps it
	 * contained: only 'shortcode' catalogue fields reach it, and an environment
	 * with no expander installed gets the text escaped rather than executed.
	 *
	 * Renders nothing at all when the field is empty, so an unused slot never
	 * leaves an empty band on the page.
	 *
	 * @param array{eyebrow?:string,heading?:string,shortcode:string} $data
	 */
	public static function shortcode_block( array $data ): string {
		$html = Blueworx_Clubhouse_Shortcodes::expand( (string) $data['shortcode'] );
		if ( '' === trim( $html ) ) {
			return '';
		}
		$eyebrow = (string) ( $data['eyebrow'] ?? '' );
		$heading = (string) ( $data['heading'] ?? '' );
		// A way out to the other half of the same journey — the Calendar's month
		// view and the Bookings page's lists are one system split in two, and
		// each has to say so. Both halves or neither, the rule the event cards
		// and the CTA band already follow.
		$label = trim( (string) ( $data['link_label'] ?? '' ) );
		$href  = trim( (string) ( $data['link_href'] ?? '' ) );
		$link  = '' !== $label && '' !== $href
			? '<a class="ch-btn ch-btn--ghost" href="' . self::e( $href ) . '">' . self::e( $label ) . '</a>'
			: '';
		$head  = '';
		if ( '' !== $eyebrow || '' !== $heading || '' !== $link ) {
			$head = '<div class="ch-sec__head"><div>'
				. ( '' !== $eyebrow ? '<span class="ch-eyebrow">' . self::e( $eyebrow ) . '</span>' : '' )
				. ( '' !== $heading ? '<h2 class="ch-sec__title ch-sec__title--sm">' . self::e( $heading ) . '</h2>' : '' )
				. '</div>' . $link . '</div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">' . $head
			. '<div class="ch-shortcode">' . $html . '</div>'
			. '</div></section>';
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

	/**
	 * The brand image when a logo is set; nothing otherwise. Without a logo the
	 * club name stands alone — no placeholder glyph.
	 *
	 * @param array{club_name:string,logo?:string} $data
	 */
	private static function brand_mark( array $data ): string {
		$logo = $data['logo'] ?? '';
		return '' !== $logo
			? '<img class="ch-brand__logo" src="' . self::e( $logo ) . '" alt="' . self::e( $data['club_name'] ) . '">'
			: '';
	}

	/**
	 * The header brand: the logo on its own, or the club wordmark on its own —
	 * never both. A crest that already contains the club's name next to the same
	 * name set in type reads as a doubled title, so the logo replaces the
	 * wordmark rather than sitting beside it. The link keeps its accessible name
	 * either way: the logo carries the club name as its alt text.
	 *
	 * @param array{club_name:string,logo?:string} $data
	 */
	private static function brand_link( array $data ): string {
		$mark = self::brand_mark( $data );
		$body = '' !== $mark ? $mark : self::e( $data['club_name'] );
		return '<a class="ch-brand" href="' . self::e( Blueworx_Clubhouse_Links::url( 'home' ) ) . '">' . $body . '</a>';
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
	 *   nav:array<int,array{label:string,href:string,children?:array<int,array{label:string,href:string}>}>,active:string,
	 *   login:string,login_href?:string,join:string,join_href:string,logo?:string} $data
	 */
	public static function header( array $data ): string {
		$login_href = $data['login_href'] ?? '#';
		// A club with no shop has nobody to sign in and nowhere to send them, so
		// the button is not drawn at all. An empty label is how the caller says
		// so — see Page_Renderer::header_account().
		$login = (string) $data['login'];
		$account = '' === $login
			? ''
			: '<a class="ch-btn ch-btn--ghost" href="' . self::e( $login_href ) . '">' . self::e( $login ) . '</a>';
		$drawer_account = '' === $login
			? ''
			: '<a class="ch-nav__link ch-nav__drawer-login" href="' . self::e( $login_href ) . '">' . self::e( $login ) . '</a>';
		$banner     = '';
		if ( '' !== $data['banner'] ) {
			$banner = '<div class="ch-banner"><div class="ch-wrap ch-banner__in">'
				. '<a class="ch-banner__link" href="' . self::e( $data['banner_href'] ) . '">'
				. self::e( $data['banner'] ) . '</a></div></div>';
		}
		$links = '';
		foreach ( $data['nav'] as $item ) {
			$links .= self::nav_item( $item, $data['active'] );
		}
		return '<a class="ch-skip" href="#ch-main">Skip to content</a>'
			. $banner
			. '<header class="ch-nav"><div class="ch-wrap ch-nav__in">'
			. self::brand_link( $data )
			. '<nav class="ch-nav__links" aria-label="Primary">' . $links . '</nav>'
			. '<div class="ch-nav__cta">'
			. $account
			. '<a class="ch-btn ch-btn--ink" href="' . self::e( $data['join_href'] ) . '">' . self::e( $data['join'] ) . '</a>'
			// No-JS disclosure menu — the same links, revealed by the hamburger below 900px.
			. '<details class="ch-nav__disc">'
			. '<summary class="ch-nav__burger" aria-label="Menu"><span class="ch-nav__burger-bars" aria-hidden="true"></span></summary>'
			. '<nav class="ch-nav__drawer" aria-label="Menu">'
			. '<a class="ch-btn ch-btn--accent ch-nav__drawer-join" href="' . self::e( $data['join_href'] ) . '">' . self::e( $data['join'] ) . '</a>'
			. $links
			. $drawer_account
			. '</nav></details>'
			. '</div></div></header>';
	}

	/**
	 * One header nav entry — a link, or a parent with a submenu.
	 *
	 * The submenu opens on :hover and :focus-within (see the look stylesheets),
	 * so it needs no JavaScript and stays reachable by keyboard: tabbing into a
	 * child keeps the list open because focus is still inside the wrapper.
	 *
	 * @param array{label:string,href:string,children?:array<int,array{label:string,href:string}>} $item
	 */
	private static function nav_item( array $item, string $active ): string {
		$children = is_array( $item['children'] ?? null ) ? $item['children'] : array();
		$href     = (string) ( $item['href'] ?? '' );
		$label    = (string) ( $item['label'] ?? '' );

		if ( array() === $children ) {
			// Flat branch: matches the pre-existing inline loop byte-for-byte,
			// including its no-emptiness-guard quirk (both '' matches active as '').
			$flat_cls = 'ch-nav__link' . ( $href === $active ? ' ch-nav__link--active' : '' );
			return '<a class="' . $flat_cls . '" href="' . self::e( $href ) . '">' . self::e( $label ) . '</a>';
		}

		// A parent with children only counts as "here" when its own href is a
		// real, matching target — two empty strings should not read as active.
		$is_here = '' !== $href && $href === $active;
		$cls     = 'ch-nav__link' . ( $is_here ? ' ch-nav__link--active' : '' );

		// A parent whose own target has gone still heads its children, but must
		// not be a link to nowhere — Menu::items() hands it an empty href. It
		// still needs to be reachable by Tab (tabindex="0") so :focus-within can
		// reveal its children with no JavaScript; a bare <span> is never in the
		// tab order on its own.
		$head = '' === $href
			? '<span class="' . $cls . ' ch-nav__link--static" tabindex="0" aria-haspopup="true">' . self::e( $label ) . '</span>'
			: '<a class="' . $cls . '" href="' . self::e( $href ) . '" aria-haspopup="true">' . self::e( $label ) . '</a>';

		$sub = '';
		foreach ( $children as $child ) {
			$sub .= '<a class="ch-nav__sublink" href="' . self::e( $child['href'] ?? '' ) . '">' . self::e( $child['label'] ?? '' ) . '</a>';
		}

		return '<span class="ch-nav__item ch-nav__item--has-children">' . $head
			. '<span class="ch-nav__sub">' . $sub . '</span></span>';
	}

	/**
	 * The content well for a page this plugin does not render — one another
	 * plugin owns, framed by the Clubhouse header and footer (External_Chrome).
	 *
	 * Two halves rather than one wrapper because the other plugin's output lands
	 * between them, and we never see it as a string. Deliberately NOT .ch-main:
	 * the looks give .ch-main's children flow margins and reveal.js hides them
	 * until they scroll in, and neither belongs on markup that is not ours.
	 * The id stays #ch-main so the header's skip link still lands.
	 */
	public static function external_open(): string {
		return '<main class="ch-external" id="ch-main" tabindex="-1"><div class="ch-external__in">';
	}

	public static function external_close(): string {
		return '</div></main>';
	}

	/**
	 * @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,
	 *   cta_primary:string,cta_primary_href:string,cta_secondary:string,
	 *   cta_secondary_href:string,image:string,image_alt:string,image_caption:string} $data
	 */
	/**
	 * The shared head of the hero family — the eyebrow plus the highlighted title —
	 * used by all three hero variants: hero() (standard, ch-hero), home_hero()
	 * (full-bleed, ch-home-hero) and hero_filter() (filtered, ch-hero-filter).
	 * $block is the variant's BEM block, so each keeps its own per-look styling;
	 * only the structure is unified. The lede stays per-variant because hero()
	 * nests it in a __sub row with its CTA, unlike the other two.
	 *
	 * The lead is wrapped in its own __lead span so the stylesheet can make it a
	 * block: the highlight then always opens line two, whatever the lead's length,
	 * and its underline/fill is one continuous run instead of two disconnected
	 * segments where the text happened to wrap. Structural rather than per-page
	 * content, so the rule holds for every heading built from this head.
	 *
	 * An empty lead emits no span at all — a block-level empty span would print a
	 * blank first line above the highlight.
	 *
	 * @param array{eyebrow:string,title_lead:string,title_highlight:string} $data
	 */
	private static function hero_head( string $block, array $data ): string {
		$lead = '' !== $data['title_lead']
			? '<span class="' . $block . '__lead">' . self::e( self::lead_with_gap( $data['title_lead'] ) ) . '</span>'
			: '';
		return '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h1 class="' . $block . '__title">' . $lead
			. '<span class="' . $block . '__hl">' . self::e( $data['title_highlight'] ) . '</span></h1>';
	}

	/**
	 * The highlight is an inline sibling of the lead, so without a separator the
	 * two run together — "Represent" + "Crewe Vagrants" rendered as
	 * "RepresentCrewe Vagrants". Content authors cannot fix it themselves: a
	 * trailing space in the field is stripped on save. Add one here unless the
	 * lead already ends in whitespace or opens a word the highlight completes
	 * (a trailing hyphen or en dash). Harmless on the looks where the highlight
	 * is a block-level box, since the space collapses at the end of the line.
	 */
	public static function lead_with_gap( string $lead ): string {
		if ( '' === $lead || preg_match( '/[\s\-–—]$/u', $lead ) ) {
			return $lead;
		}
		return $lead . ' ';
	}

	/** One hero button, or nothing when either half of it is missing. */
	private static function hero_button( string $variant, string $label, string $href ): string {
		if ( '' === trim( $label ) || '' === trim( $href ) ) {
			return '';
		}
		return '<a class="ch-btn ' . $variant . '" href="' . self::e( $href ) . '">' . self::e( $label ) . '</a>';
	}

	public static function hero( array $data ): string {
		$caption = '' !== $data['image_caption']
			? '<div class="ch-hero__pill"><i class="ch-hero__pill-dot"></i>' . self::e( $data['image_caption'] ) . '</div>'
			: '';
		// Only an actual image earns the picture box. Alt text and a caption are
		// descriptions OF an image, and the renderer fills alt in by default, so
		// treating either as "there is media here" reserved a tall empty frame
		// with a placeholder glyph in it on every page whose hero image had not
		// been set — About, Membership and Book a court in the demo (issue #108).
		$has_media = '' !== $data['image'];
		$media     = $has_media
			? '<div class="ch-hero__media">' . self::media( $data['image'], $data['image_alt'], '' ) . $caption . '</div>'
			: '';
		// Both halves, or no button — the rule the event cards and the CTA band
		// already follow. A hero with no buttons at all (a legal page, say) drops
		// the row rather than emitting two empty ones, which rendered as a pair
		// of unlabelled boxes linking to the current page.
		$buttons = self::hero_button( 'ch-btn--accent', $data['cta_primary'], $data['cta_primary_href'] )
			. self::hero_button( 'ch-btn--ghost', $data['cta_secondary'], $data['cta_secondary_href'] );

		return '<section class="ch-hero"><div class="ch-wrap">'
			. self::hero_head( 'ch-hero', $data )
			. '<div class="ch-hero__sub">'
			. '<p class="ch-hero__lede">' . self::e( $data['lede'] ) . '</p>'
			. ( '' !== $buttons ? '<div class="ch-hero__cta">' . $buttons . '</div>' : '' )
			. '</div>'
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
	 *   tiles:array<int,array{label:string,href:string,icon:string}>,
	 *   tiles_id?:string} $data
	 */
	public static function home_hero( array $data ): string {
		$has_img = '' !== $data['image'];
		$bg      = '<div class="ch-home-hero__bg' . ( $has_img ? '' : ' ch-home-hero__bg--empty' ) . '">'
			. ( $has_img ? '<img class="ch-home-hero__img" src="' . self::e( $data['image'] ) . '" alt="' . self::e( $data['image_alt'] ) . '">' : '' )
			. '</div>';
		$tiles = '';
		foreach ( $data['tiles'] as $t ) {
			// Tiles may be owner-edited content, where optional keys can be absent.
			// A tile with no destination is a link to nowhere, so skip it rather than
			// emit a dead href="#" — the same rule the rest of the front end follows.
			$href = (string) ( $t['href'] ?? '' );
			if ( '' === $href ) {
				continue;
			}
			// An unset/unknown icon degrades to no glyph rather than a warning.
			$svg = self::TILE_ICONS[ $t['icon'] ?? '' ] ?? '';
			$ico = '' !== $svg ? '<span class="ch-home-hero__tile-ico" aria-hidden="true">' . $svg . '</span>' : '';
			$tiles .= '<a class="ch-home-hero__tile" href="' . self::e( $href ) . '">'
				. $ico
				. '<span class="ch-home-hero__tile-label">' . self::e( $t['label'] ?? '' ) . '</span>'
				. '<span class="ch-home-hero__tile-arrow" aria-hidden="true">→</span></a>';
		}
		// The quick-tile row below carries the same actions, so the hero CTAs are off
		// by default (the caller passes empty labels) — but stay configurable: set a
		// primary label and the button pair returns, like any other element.
		$primary   = (string) ( $data['cta_primary'] ?? '' );
		$secondary = (string) ( $data['cta_secondary'] ?? '' );
		$cta        = '';
		if ( '' !== $primary ) {
			$cta = '<div class="ch-home-hero__cta">'
				. '<a class="ch-btn ch-btn--accent" href="' . self::e( $data['cta_primary_href'] ) . '">' . self::e( $primary ) . '</a>'
				. ( '' !== $secondary
					? '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['cta_secondary_href'] ) . '">' . self::e( $secondary ) . '</a>'
					: '' )
				. '</div>';
		}
		// Quick tiles are owner-editable content in their own right (Content →
		// Home → Quick tiles) but render inside the hero's own root rather than a
		// section of their own — 'tiles_id' lets Link_Catalogue's anchor for that
		// content land on this element instead of a section that doesn't exist.
		$tiles_id = '' !== (string) ( $data['tiles_id'] ?? '' ) ? ' id="' . self::e( $data['tiles_id'] ) . '"' : '';
		return '<section class="ch-home-hero">'
			. $bg
			. '<div class="ch-home-hero__scrim" aria-hidden="true"></div>'
			. '<div class="ch-wrap ch-home-hero__in">'
			. self::hero_head( 'ch-home-hero', $data )
			. '<p class="ch-home-hero__lede">' . self::e( $data['lede'] ) . '</p>'
			. $cta
			// A row of links is a set of links, not a list: role="list" here forced a
			// role="listitem" onto each <a>, which overrode the link role and had a
			// screen reader announce "list item" where a link was.
			. '<nav class="ch-home-hero__foot"' . $tiles_id . ' aria-label="Quick links">' . $tiles . '</nav>'
			. '</div></section>';
	}

	/**
	 * The pill row, or nothing when there is nothing to filter by. Shared so a
	 * page can put the pills wherever the thing they filter actually is: in the
	 * hero on Sports and Teams, directly above the fixtures list on Calendar.
	 *
	 * @param array<int,array{label:string,href:string,active:bool}> $filters
	 */
	private static function filter_nav( array $filters, string $label ): string {
		// One pill is always "All", and "All" on its own filters nothing — it
		// offers a choice between everything and everything. A club with no
		// fixtures entered got exactly that above an empty list.
		if ( count( $filters ) < 2 ) {
			return '';
		}
		$pills = '';
		foreach ( $filters as $f ) {
			$on     = ! empty( $f['active'] ) ? ' ch-filter--on' : '';
			$pills .= '<a class="ch-filter' . $on . '" href="' . self::e( $f['href'] ?? '' ) . '"'
				. ( ! empty( $f['active'] ) ? ' aria-current="page"' : '' ) . '>' . self::e( $f['label'] ?? '' ) . '</a>';
		}
		return '<nav class="ch-filters" aria-label="' . self::e( $label ) . '">' . $pills . '</nav>';
	}

	/**
	 * @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,
	 *   filter_label:string,filters:array<int,array{label:string,href:string,active:bool}>} $data
	 */
	public static function hero_filter( array $data ): string {
		return '<section class="ch-hero-filter"><div class="ch-wrap">'
			. self::hero_head( 'ch-hero-filter', $data )
			. '<p class="ch-hero-filter__lede">' . self::e( $data['lede'] ) . '</p>'
			. self::filter_nav( $data['filters'], $data['filter_label'] )
			// Inside the section, not after it: as a sibling in <main> the script
			// would sit between this section and the next, breaking the adjacent-
			// sibling rule that tightens the gap below the pill row.
			. '</div>' . self::FILTER_SCRIPT . '</section>';
	}

	/**
	 * Filter pills stay real links — that is the no-JS behaviour and it keeps each
	 * filter shareable and crawlable — but with JS they swap the page's <main>
	 * in place instead of reloading, so the hero, header and scroll position hold
	 * still while the list below changes.
	 *
	 * The server keeps doing the filtering. Nothing here re-implements which rows
	 * match, so the derived structure below the pills (upcoming/past splits, month
	 * grouping, empty-state copy) stays correct without being duplicated in JS.
	 *
	 * Guarded and delegated from `document`, because the pill row lives inside the
	 * <main> that gets replaced — a listener bound to the pills themselves would
	 * die on the first swap. Modified clicks and middle-clicks fall through to the
	 * browser so open-in-new-tab still works; any fetch failure falls back to a
	 * normal navigation rather than leaving the reader on a stale list.
	 */
	private const FILTER_SCRIPT = '<script>(function(){if(window.__chFilters)return;window.__chFilters=1;'
		. 'document.addEventListener("click",function(e){'
		. 'if(e.defaultPrevented||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||0!==e.button)return;'
		. 'var a=e.target&&e.target.closest?e.target.closest("a.ch-filter"):null;if(!a)return;'
		. 'if(a.classList.contains("ch-filter--on")){e.preventDefault();return;}'
		. 'var main=document.querySelector(".ch-main");if(!main)return;'
		. 'e.preventDefault();main.setAttribute("aria-busy","true");'
		. 'fetch(a.href,{credentials:"same-origin"}).then(function(r){'
		. 'if(!r.ok)throw 0;return r.text()}).then(function(t){'
		. 'var d=(new DOMParser()).parseFromString(t,"text/html"),n=d.querySelector(".ch-main");'
		. 'if(!n)throw 0;main.innerHTML=n.innerHTML;main.removeAttribute("aria-busy");'
		. 'history.pushState({chFilter:1},"",a.href)}).catch(function(){'
		. 'main.removeAttribute("aria-busy");window.location.href=a.href})});'
		// Back/forward has to re-render, and the swapped-in markup is not in any
		// history entry — a reload is the honest way to restore the previous filter.
		. 'window.addEventListener("popstate",function(){location.reload()})})();</script>';

	/**
	 * What a collection-backed section renders when its collection is empty.
	 *
	 * A club that has not entered a committee, or has no upcoming events, was
	 * still given the section's eyebrow and heading with nothing under them — the
	 * About page showed a bare "The committee", the Events page a bare "Upcoming
	 * events". The import's switch-off-empty-sections tick box cannot reach these:
	 * they take no content from the file, so the file can never be "missing" them.
	 * Decide at render time instead — nothing at all by default, or a short note
	 * where the section sits behind a filter and vanishing entirely would leave
	 * the reader wondering whether the filter worked.
	 *
	 * `after_head` is already-built markup the caller wants kept with the
	 * section when everything else has gone — the filter pills, so a filter that
	 * matches nothing can still be cleared.
	 *
	 * @param array{eyebrow?:string,heading?:string,empty_text?:string,after_head?:string} $data
	 */
	private static function empty_section( array $data ): string {
		$text = (string) ( $data['empty_text'] ?? '' );
		if ( '' === $text ) {
			return '';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( (string) ( $data['eyebrow'] ?? '' ) ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( (string) ( $data['heading'] ?? '' ) ) . '</h2>'
			. (string) ( $data['after_head'] ?? '' )
			. '<p class="ch-empty">' . self::e( $text ) . '</p></div></section>';
	}

	/** @param array<int,array{label:string,href:string}> $tiles */
	public static function quick_tiles( array $tiles ): string {
		$items = '';
		foreach ( $tiles as $t ) {
			$items .= '<a class="ch-tiles__tile" href="' . self::e( $t['href'] ?? '' ) . '">'
				. '<span class="ch-tiles__label">' . self::e( $t['label'] ?? '' ) . '</span>'
				. '<span class="ch-tiles__arrow" aria-hidden="true">→</span></a>';
		}
		return '<section class="ch-tiles-sec"><div class="ch-wrap"><nav class="ch-tiles" aria-label="Quick links">' . $items . '</nav></div></section>';
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
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head"><div>'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2></div>'
			. '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . '</a></div>'
			. self::card_list( $data['cards'] ) . '</div></section>';
	}

	/** One `ch-cards` list — the body shared by card_grid and card_grid_switch. */
	private static function card_list( array $cards ): string {
		$out = '';
		foreach ( $cards as $c ) {
			// Same treatment as the stat cards: the title carries the link, and the
			// card gets the click target back through CSS.
			$href  = trim( (string) ( $c['href'] ?? '' ) );
			$title = '' !== $href
				? '<a class="ch-scard__link" href="' . self::e( $href ) . '">' . self::e( $c['title'] ?? '' ) . '</a>'
				: self::e( $c['title'] ?? '' );
			$out  .= '<article class="ch-card' . ( '' !== $href ? ' ch-scard--linked' : '' ) . '" role="listitem">'
				. self::media( $c['image'] ?? '', $c['image_alt'] ?? '', 'ch-card__media' )
				. '<div class="ch-card__scrim"></div>'
				. '<span class="ch-card__tag">' . self::e( $c['tag'] ?? '' ) . '</span>'
				. '<div class="ch-card__body"><h3 class="ch-card__title">' . $title . '</h3>'
				. '<p class="ch-card__sub">' . self::e( $c['subtitle'] ?? '' ) . '</p></div></article>';
		}
		return '<div class="ch-cards" role="list">' . $out . '</div>';
	}

	/**
	 * The Home card grid with a Sports/Teams switch: same treatment as card_grid,
	 * but the reader chooses which collection they are looking at instead of the
	 * page committing to one. Switching is client-side, so no reload.
	 *
	 * A group with no cards is dropped rather than shown as an empty tab; if that
	 * leaves one group, it renders as a plain card_grid with no switch, and if it
	 * leaves none the section renders nothing at all.
	 *
	 * @param array{eyebrow:string,heading:string,
	 *   groups:array<string,array{label:string,link_label:string,link_href:string,
	 *     cards:array<int,array{image:string,image_alt:string,tag:string,title:string,subtitle:string}>}>} $data
	 */
	public static function card_grid_switch( array $data ): string {
		$groups = array_filter( $data['groups'], static fn( array $g ): bool => array() !== $g['cards'] );
		if ( array() === $groups ) {
			return '';
		}
		if ( 1 === count( $groups ) ) {
			$only = array_values( $groups )[0];
			return self::card_grid( array(
				'eyebrow'    => $data['eyebrow'],
				'heading'    => $data['heading'],
				'link_label' => $only['link_label'],
				'link_href'  => $only['link_href'],
				'cards'      => $only['cards'],
			) );
		}
		$panels = array();
		foreach ( $groups as $key => $group ) {
			$panels[ $key ] = array(
				'label' => $group['label'],
				'body'  => self::card_list( $group['cards'] )
					. '<a class="ch-btn ch-btn--ghost ch-cards__all" href="' . self::e( $group['link_href'] ) . '">'
					. self::e( $group['link_label'] ) . '</a>',
			);
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head"><div>'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2></div></div>'
			. self::tab_group( $panels )
			. '</div>' . self::TAB_SCRIPT . '</section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,link_label:string,link_href:string,
	 *   cards:array<int,array{image:string,image_alt:string,chip:string,title:string,description:string,
	 *   cta_label?:string,cta_href?:string,
	 *   stats:array<int,array{value:string,label:string}>}>} $data
	 */
	public static function stat_card_grid( array $data ): string {
		if ( array() === $data['cards'] ) {
			return self::empty_section( $data );
		}
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$stats = '';
			foreach ( ( $c['stats'] ?? array() ) as $s ) {
				$stats .= '<div class="ch-scard__stat"><b class="ch-scard__stat-v">' . self::e( $s['value'] ?? '' )
					. '</b><span class="ch-scard__stat-l">' . self::e( $s['label'] ?? '' ) . '</span></div>';
			}
			// The title is the link, not the whole card: a card wrapped in an anchor
			// swallows the stats and the image into one enormous link label, which
			// is what a screen reader then has to read out before saying "link".
			// The class puts the click target back over the whole card visually.
			$href  = trim( (string) ( $c['href'] ?? '' ) );
			$title = '' !== $href
				? '<a class="ch-scard__link" href="' . self::e( $href ) . '">' . self::e( $c['title'] ?? '' ) . '</a>'
				: self::e( $c['title'] ?? '' );
			// An optional second link, off this site — the team's page on a league or
			// governing-body site. Both halves or neither, as everywhere else. It sits
			// above the title link's full-card overlay, or it could not be clicked.
			$cta_href  = trim( (string) ( $c['cta_href'] ?? '' ) );
			$cta_label = trim( (string) ( $c['cta_label'] ?? '' ) );
			$cta       = ( '' !== $cta_href && '' !== $cta_label )
				? '<a class="ch-btn ch-btn--ghost ch-scard__cta" href="' . self::e( $cta_href ) . '" target="_blank" rel="noopener">' . self::e( $cta_label ) . '</a>'
				: '';
			$cards .= '<article class="ch-scard' . ( '' !== $href ? ' ch-scard--linked' : '' ) . '" role="listitem">'
				. self::media( $c['image'] ?? '', $c['image_alt'] ?? '', 'ch-scard__media' )
				. '<span class="ch-scard__chip">' . self::e( $c['chip'] ?? '' ) . '</span>'
				. '<div class="ch-scard__body">'
				. '<h3 class="ch-scard__title">' . $title . '</h3>'
				. '<p class="ch-scard__desc">' . self::e( $c['description'] ?? '' ) . '</p>'
				. '<div class="ch-scard__stats">' . $stats . '</div>' . $cta . '</div></article>';
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
		// With no image the band is a solid coloured block (its own background), so
		// render no media slot at all — the empty-media placeholder glyph reads as a
		// broken image here rather than as "add a photo".
		$media = '' !== $data['image']
			? self::media( $data['image'], $data['image_alt'], 'ch-band-img__media' )
			: '';
		return '<section class="ch-band-img' . ( '' === $data['image'] ? ' ch-band-img--plain' : '' ) . '">'
			. $media
			. '<div class="ch-band-img__scrim"></div>'
			. '<div class="ch-wrap ch-band-img__in"><div>'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-band-img__title">' . self::e( $data['heading'] ) . '</h2></div>'
			. '<a class="ch-btn ch-btn--accent" href="' . self::e( $data['cta_href'] ) . '">' . self::e( $data['cta_label'] ) . '</a>'
			. '</div></section>';
	}

	/**
	 * @param array{variant:string,eyebrow:string,heading:string,lede:string,
	 *   cta_label:string,cta_href:string,cta_external?:bool} $data variant: 'accent' | 'ink'
	 */
	public static function band( array $data ): string {
		$mod     = 'ink' === $data['variant'] ? 'ch-band--ink' : 'ch-band--accent';
		$btn     = 'ink' === $data['variant'] ? 'ch-btn--accent' : 'ch-btn--ink';
		$eyebrow = '' !== $data['eyebrow']
			? '<span class="ch-eyebrow ch-eyebrow--band">' . self::e( $data['eyebrow'] ) . '</span>' : '';
		$lede    = '' !== $data['lede'] ? '<p class="ch-band__lede">' . self::e( $data['lede'] ) . '</p>' : '';
		// A band that points off this site says so in the markup, the same way the
		// contact map link does, so it opens in its own tab and cannot reach back.
		$away = ! empty( $data['cta_external'] ) ? ' target="_blank" rel="noopener"' : '';
		// Both halves, or neither — the same rule as the event cards. A band with a
		// label and no link (or a link and no words) rendered a button that went
		// nowhere, and a band is also useful as a plain statement with no button.
		$cta = '' !== trim( (string) $data['cta_label'] ) && '' !== trim( (string) $data['cta_href'] )
			? '<a class="ch-btn ' . $btn . '" href="' . self::e( $data['cta_href'] ) . '"' . $away . '>' . self::e( $data['cta_label'] ) . '</a>'
			: '';
		return '<section class="ch-wrap ch-band-wrap"><div class="ch-band ' . $mod . '">'
			. $eyebrow
			. '<h2 class="ch-band__title">' . self::e( $data['heading'] ) . '</h2>'
			. $lede
			. $cta
			. '</div></section>';
	}

	/**
	 * A document made of headed prose — a privacy policy, terms, anything a club
	 * has to say at length and nobody wants to read in three columns.
	 *
	 * Deliberately the plainest section here: one measure, one heading level, no
	 * cards, no images, no calls to action. A legal page is read to find one
	 * clause, so it is set for scanning rather than for persuading, and each
	 * block carries an id so a clause can be linked to directly.
	 *
	 * Blank lines in a block's body become paragraphs. That is the whole of the
	 * formatting: an owner writing a policy in a textarea should not have to
	 * learn markup, and letting HTML through here would be an injection hole in
	 * the one place a club is most likely to paste something from elsewhere.
	 *
	 * @param array{heading:string,blocks:array<int,array{heading:string,body:string}>} $data
	 */
	public static function prose( array $data ): string {
		$out = '';
		foreach ( $data['blocks'] as $i => $block ) {
			$heading = trim( (string) ( $block['heading'] ?? '' ) );
			$body    = trim( (string) ( $block['body'] ?? '' ) );
			if ( '' === $heading && '' === $body ) {
				continue;
			}
			$paras = '';
			foreach ( preg_split( '/\n\s*\n/', $body ) ?: array() as $para ) {
				$para = trim( (string) $para );
				if ( '' !== $para ) {
					$paras .= '<p class="ch-prose__p">' . nl2br( self::e( $para ) ) . '</p>';
				}
			}
			$id   = 'ch-prose-' . ( (int) $i + 1 );
			$out .= '<div class="ch-prose__block" id="' . self::e( $id ) . '">'
				. ( '' !== $heading ? '<h2 class="ch-prose__h">' . self::e( $heading ) . '</h2>' : '' )
				. $paras
				. '</div>';
		}
		if ( '' === $out ) {
			return '';
		}
		return '<section class="ch-sec"><div class="ch-wrap"><div class="ch-prose">' . $out . '</div></div></section>';
	}

	/**
	 * A standing note that the site uses cookies, and where to read about them.
	 *
	 * What this is NOT is a consent manager. It does not pretend to withhold
	 * anything: the shop and its payment provider set their own cookies as soon
	 * as a commerce page loads, and a banner that claimed to block them while
	 * they loaded anyway would be worse than saying nothing — it would be a
	 * false statement on every page. A club that needs true consent gating
	 * wants a dedicated consent plugin, and this notice steps aside for one
	 * (it is skipped whenever its text is empty).
	 *
	 * The dismissal is remembered in localStorage rather than a cookie, so
	 * reading this notice does not itself add to what the site stores about
	 * someone.
	 *
	 * @param array{text:string,link_label:string,link_href:string,dismiss:string} $data
	 */
	public static function cookie_notice( array $data ): string {
		$text = trim( $data['text'] );
		if ( '' === $text ) {
			return '';
		}
		$link = '' !== trim( $data['link_href'] ) && '' !== trim( $data['link_label'] )
			? ' <a class="ch-cookie__link" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . '</a>'
			: '';
		// hidden until the script has checked storage, so a returning visitor who
		// dismissed it never sees it flash back on the next page.
		return '<div class="ch-cookie" id="ch-cookie" role="region" aria-label="Cookie notice" hidden>'
			. '<p class="ch-cookie__text">' . self::e( $text ) . $link . '</p>'
			. '<button type="button" class="ch-btn ch-cookie__dismiss" id="ch-cookie-dismiss">'
			. self::e( '' !== trim( $data['dismiss'] ) ? $data['dismiss'] : 'Got it' )
			. '</button>'
			. '</div>';
	}

	/**
	 * Training times and who to ask, for a sport or team page. A plain two-column
	 * panel: nothing here is a link except the email, because the point is the
	 * information rather than a journey onward.
	 *
	 * @param array{eyebrow:string,heading:string,training:array<int,string>,
	 *   contact_name:string,contact_email:string} $data
	 */
	public static function info_panel( array $data ): string {
		$times = '';
		foreach ( $data['training'] as $line ) {
			$times .= '<li class="ch-info__time" role="listitem">' . self::e( $line ) . '</li>';
		}
		$training_block = '' !== $times
			? '<div class="ch-info__col"><h3 class="ch-info__h">Training</h3>'
				. '<ul class="ch-info__times" role="list">' . $times . '</ul></div>'
			: '';

		$name  = trim( $data['contact_name'] );
		$email = trim( $data['contact_email'] );
		$who   = '';
		if ( '' !== $name || '' !== $email ) {
			$who = '<div class="ch-info__col"><h3 class="ch-info__h">Who to ask</h3>'
				. ( '' !== $name ? '<p class="ch-info__name">' . self::e( $name ) . '</p>' : '' )
				. ( '' !== $email
					? '<a class="ch-info__email" href="mailto:' . self::e( $email ) . '">' . self::e( $email ) . '</a>'
					: '' )
				. '</div>';
		}

		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title ch-sec__title--sm">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-info">' . $training_block . $who . '</div>'
			. '</div></section>';
	}

	/**
	 * @param array<int,array{eyebrow:string,name:string,price:string,period:string,
	 *   features:array<int,string>,recommended:bool,cta_label:string,cta_href:string}> $tiers
	 */
	/**
	 * The tier name's heading level depends on what sits above the grid: on Home
	 * it follows a section h2, so h3 is right; on Membership the grid comes
	 * straight after the page h1, where h3 skips a level. The caller knows which,
	 * so it passes the level rather than this guessing.
	 *
	 * @param 2|3 $level
	 */
	public static function tier_grid( array $tiers, int $level = 3, bool $switcher = true ): string {
		$h     = 2 === $level ? 'h2' : 'h3';
		// A switch that changes nothing is a control that lies about what the
		// page can do: offered only when some tier is genuinely priced both ways.
		$switchable = false;
		foreach ( $tiers as $t ) {
			if ( ( $t['monthly']['available'] ?? false ) && ( $t['annual']['available'] ?? false ) ) {
				$switchable = true;
				break;
			}
		}
		$switcher = $switcher && $switchable;

		$cards = '';
		foreach ( $tiers as $t ) {
			$cls   = ( $t['recommended'] ?? false ) ? 'ch-tier ch-tier--pop' : 'ch-tier';
			$btn   = ( $t['recommended'] ?? false ) ? 'ch-btn--accent' : 'ch-btn--ghost';
			$feats = '';
			foreach ( ( $t['features'] ?? array() ) as $f ) {
				$feats .= '<li class="ch-tier__feat" role="listitem">' . self::e( $f ) . '</li>';
			}
			$cards .= '<div class="' . $cls . '" role="listitem">'
				. '<span class="ch-tier__k">' . self::e( $t['eyebrow'] ?? '' ) . '</span>'
				. '<' . $h . ' class="ch-tier__name">' . self::e( $t['name'] ?? '' ) . '</' . $h . '>'
				. self::tier_prices( $t, $switcher )
				. '<ul class="ch-tier__feats" role="list">' . $feats . '</ul>'
				. self::tier_ctas( $t, $btn, $switcher )
				. '</div>';
		}
		return '<section class="ch-wrap ch-tiers-sec"' . ( $switcher ? ' data-ch-cadence-root' : '' ) . '>'
			. ( $switcher ? self::cadence_switcher() : '' )
			. '<div class="ch-tiers" role="list">' . $cards . '</div>'
			. '</section>' . ( $switcher ? self::CADENCE_SCRIPT : '' );
	}

	/**
	 * The Monthly / Annual switch. Two buttons rather than tabs: nothing is being
	 * revealed and hidden except the price beside them, and a tablist would
	 * promise a panel that does not exist.
	 */
	private static function cadence_switcher(): string {
		return '<div class="ch-cadence" role="group" aria-label="How often to pay">'
			. '<button type="button" class="ch-cadence__btn ch-cadence__btn--on" data-ch-cadence="monthly" aria-pressed="true">Monthly</button>'
			. '<button type="button" class="ch-cadence__btn" data-ch-cadence="annual" aria-pressed="false">Annual</button>'
			. '</div>';
	}

	/**
	 * Both prices, one of them hidden. Rendering both into the same card is what
	 * keeps the grid still as somebody switches: nothing is measured, moved or
	 * re-laid out, only shown.
	 *
	 * A tier priced only one way shows the price it has under both switch
	 * positions, with a quiet note saying which it is — a card that empties out
	 * or disappears mid-toggle reads as a broken page.
	 *
	 * @param array<string,mixed> $tier
	 */
	private static function tier_prices( array $tier, bool $switcher ): string {
		$flat = array(
			'price'     => (string) ( $tier['price'] ?? '' ),
			'period'    => (string) ( $tier['period'] ?? '' ),
			'available' => true,
		);
		$monthly = is_array( $tier['monthly'] ?? null ) ? $tier['monthly'] : $flat;

		$out = self::tier_price( $monthly, 'monthly', '', false );
		if ( ! $switcher ) {
			return $out;
		}
		$annual = is_array( $tier['annual'] ?? null ) ? $tier['annual'] : $flat;
		return $out . self::tier_price( $annual, 'annual', (string) ( $tier['saving'] ?? '' ), true );
	}

	/**
	 * One cadence's price block.
	 *
	 * @param array<string,mixed> $cadence
	 */
	private static function tier_price( array $cadence, string $key, string $saving, bool $hidden ): string {
		$note = '';
		if ( ! ( $cadence['available'] ?? true ) ) {
			$note = 'monthly' === $key ? 'Annual only' : 'Monthly only';
		}
		return '<div class="ch-tier__price ch-tier__price--' . $key . ( $hidden ? ' ch-is-off' : '' ) . '" data-ch-cadence-side="' . $key . '">'
			. '<div class="ch-tier__amt">' . self::e( (string) ( $cadence['price'] ?? '' ) )
			. '<small>' . self::e( (string) ( $cadence['period'] ?? '' ) ) . '</small></div>'
			. ( '' !== $saving ? '<span class="ch-tier__save">' . self::e( $saving ) . '</span>' : '' )
			. ( '' !== $note ? '<span class="ch-tier__note">' . self::e( $note ) . '</span>' : '' )
			. '</div>';
	}

	/**
	 * One button per cadence, so each buys the thing its price names. Without a
	 * switcher there is only the monthly one, which is what every caller had
	 * before this existed.
	 *
	 * @param array<string,mixed> $tier
	 */
	private static function tier_ctas( array $tier, string $btn, bool $switcher ): string {
		$flat = array(
			'cta_label' => (string) ( $tier['cta_label'] ?? '' ),
			'cta_href'  => (string) ( $tier['cta_href'] ?? '' ),
		);
		$monthly = is_array( $tier['monthly'] ?? null ) ? $tier['monthly'] : $flat;

		$out = self::tier_cta( $monthly, $btn, 'monthly', false );
		if ( ! $switcher ) {
			return $out;
		}
		$annual = is_array( $tier['annual'] ?? null ) ? $tier['annual'] : $flat;
		return $out . self::tier_cta( $annual, $btn, 'annual', true );
	}

	/** @param array<string,mixed> $cadence */
	private static function tier_cta( array $cadence, string $btn, string $key, bool $hidden ): string {
		return '<a class="ch-btn ' . $btn . ' ch-tier__cta ch-tier__cta--' . $key . ( $hidden ? ' ch-is-off' : '' ) . '"'
			. ' data-ch-cadence-side="' . $key . '"'
			. ' href="' . self::e( (string) ( $cadence['cta_href'] ?? '' ) ) . '">'
			. self::e( (string) ( $cadence['cta_label'] ?? '' ) ) . '</a>';
	}

	/**
	 * Binds every cadence switch on the page — Home and Membership can each
	 * carry one, and a single querySelector would leave the second dead. Guarded
	 * so emitting it once per grid is safe, exactly as TAB_SCRIPT is.
	 *
	 * One pass sets both the class and aria-pressed, so a sighted visitor and a
	 * screen reader user are never told different things about which is on.
	 */
	private const CADENCE_SCRIPT = '<script>(function(){if(window.__chCadence)return;window.__chCadence=1;'
		. 'document.addEventListener("click",function(e){'
		. 'var b=e.target&&e.target.closest?e.target.closest("[data-ch-cadence]"):null;if(!b)return;'
		. 'var r=b.closest("[data-ch-cadence-root]");if(!r)return;'
		. 'var k=b.getAttribute("data-ch-cadence");'
		. 'r.querySelectorAll("[data-ch-cadence]").forEach(function(x){var on=x===b;'
		. 'x.classList.toggle("ch-cadence__btn--on",on);x.setAttribute("aria-pressed",on?"true":"false")});'
		. 'r.querySelectorAll("[data-ch-cadence-side]").forEach(function(p){'
		. 'p.classList.toggle("ch-is-off",p.getAttribute("data-ch-cadence-side")!==k)});'
		. '});})();</script>';

	/**
	 * @param array{eyebrow:string,heading:string,
	 *   fixtures:array<int,array{month:string,day:string,competition:string,time:string,matchup:string}>,
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
		$ev = '';
		foreach ( $data['events'] as $e ) {
			$ev .= '<div class="ch-evt" role="listitem"><div class="ch-evt__meta"><span class="ch-evt__tag">' . self::e( $e['tag'] ) . '</span>'
				. '<span class="ch-evt__date">' . self::e( $e['date'] ) . '</span></div>'
				. '<h3 class="ch-evt__title">' . self::e( $e['title'] ) . '</h3>'
				. '<p class="ch-evt__detail">' . self::e( $e['detail'] ) . '</p></div>';
		}
		return '<section class="ch-sec ch-sec--alt"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. self::tab_group( array(
				'fixtures' => array( 'label' => 'Fixtures', 'body' => '<div class="ch-fx-list" role="list">' . $fx . '</div>' ),
				'events'   => array( 'label' => 'Events', 'body' => '<div class="ch-evt-grid" role="list">' . $ev . '</div>' ),
			) )
			. '</div>' . self::TAB_SCRIPT . '</section>';
	}

	/**
	 * The site's one in-page tab treatment: a button bar plus one panel per key,
	 * first key active. Switching is client-side — no navigation, no reload.
	 *
	 * Panels are hidden with a class rather than the `hidden` attribute so a
	 * stylesheet-less page still shows every panel's content instead of none.
	 *
	 * @param array<string,array{label:string,body:string}> $panels ordered; first is active
	 */
	private static function tab_group( array $panels ): string {
		// Ids must be unique per document — Home carries two tab groups — but also
		// stable, so rendering the same page twice gives byte-identical output. A
		// counter satisfied the first and broke the second, so the group is named
		// after its own first panel key ('fixtures', 'sports'), which is unique per
		// page and the same on every render.
		$seq    = (string) array_key_first( $panels );
		$bar    = '';
		$bodies = '';
		$first  = true;
		foreach ( $panels as $key => $panel ) {
			$btn_id   = 'ch-tab-' . $seq . '-' . self::e( $key );
			$panel_id = 'ch-tabpanel-' . $seq . '-' . self::e( $key );
			// A roving tabindex is what makes a tablist one stop rather than one per
			// tab: arrow keys move between tabs, Tab moves out to the panel.
			$bar    .= '<button type="button" role="tab" id="' . $btn_id . '"'
				. ' aria-selected="' . ( $first ? 'true' : 'false' ) . '"'
				. ' aria-controls="' . $panel_id . '"'
				. ' tabindex="' . ( $first ? '0' : '-1' ) . '"'
				. ' class="ch-tabs__btn' . ( $first ? ' ch-tabs__btn--on' : '' )
				. '" data-ch-tabbtn="' . self::e( $key ) . '">' . self::e( $panel['label'] ) . '</button>';
			$bodies .= '<div role="tabpanel" id="' . $panel_id . '" aria-labelledby="' . $btn_id . '" tabindex="0"'
				. ' class="' . ( $first ? '' : 'ch-tabs__panel--off' ) . '" data-ch-tab="' . self::e( $key ) . '">'
				. $panel['body'] . '</div>';
			$first   = false;
		}
		return '<div class="ch-tabs" data-ch-tabs><div class="ch-tabs__bar" role="tablist">' . $bar . '</div>' . $bodies . '</div>';
	}

	/**
	 * Binds every tab group on the page, not just the first — Home now carries two
	 * (the activity tabs and the sports/teams switch), and a single querySelector
	 * left the second one dead. Guarded so emitting it once per section is safe.
	 */
	private const TAB_SCRIPT = '<script>(function(){if(window.__chTabs)return;window.__chTabs=1;'
		// One selector does both the class swap and the ARIA state, so the two can
		// never drift: a sighted user and a screen reader user are told the same
		// thing about which tab is on.
		. 'function sel(r,b){var k=b.getAttribute("data-ch-tabbtn");'
		. 'r.querySelectorAll("[data-ch-tabbtn]").forEach(function(x){var on=x===b;'
		. 'x.classList.toggle("ch-tabs__btn--on",on);x.setAttribute("aria-selected",on?"true":"false");'
		. 'x.setAttribute("tabindex",on?"0":"-1")});'
		. 'r.querySelectorAll("[data-ch-tab]").forEach(function(p){p.classList.toggle("ch-tabs__panel--off",p.getAttribute("data-ch-tab")!==k)});}'
		. 'document.addEventListener("click",function(e){'
		. 'var b=e.target&&e.target.closest?e.target.closest("[data-ch-tabbtn]"):null;if(!b)return;'
		. 'var r=b.closest("[data-ch-tabs]");if(!r)return;sel(r,b);});'
		// Arrow keys are what a tablist is expected to answer to; without them the
		// roving tabindex would strand a keyboard user on the first tab.
		. 'document.addEventListener("keydown",function(e){'
		. 'var b=e.target&&e.target.closest?e.target.closest("[data-ch-tabbtn]"):null;if(!b)return;'
		. 'var d=e.key==="ArrowRight"?1:e.key==="ArrowLeft"?-1:0;if(!d)return;'
		. 'var r=b.closest("[data-ch-tabs]");if(!r)return;'
		. 'var t=Array.prototype.slice.call(r.querySelectorAll("[data-ch-tabbtn]"));'
		. 'var n=t[(t.indexOf(b)+d+t.length)%t.length];if(!n)return;'
		. 'e.preventDefault();sel(r,n);n.focus();});'
		. '})();</script>';

	/**
	 * @param array{eyebrow:string,heading:string,
	 *   cards:array<int,array{image:string,image_alt:string,tag:string,date:string,title:string}>} $data
	 */
	public static function news_cards( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$body = self::media( $c['image'] ?? '', $c['image_alt'] ?? '', 'ch-news__media' )
				. '<div class="ch-news__meta"><span class="ch-news__tag">' . self::e( $c['tag'] ?? '' ) . '</span>'
				. '<span class="ch-news__date">' . self::e( $c['date'] ?? '' ) . '</span></div>'
				. '<h3 class="ch-news__title">' . self::e( $c['title'] ?? '' ) . '</h3>';
			// A card that has a story behind it is a link; one that does not stays an
			// article, because a card that looks clickable and is not is worse than a
			// card that never invited the click. role="listitem" is dropped on the
			// link so the anchor keeps its own role.
			$href    = (string) ( $c['href'] ?? '' );
			$cards  .= '' !== $href
				? '<a class="ch-news__card ch-news__card--link" href="' . self::e( $href ) . '">' . $body . '</a>'
				: '<article class="ch-news__card" role="listitem">' . $body . '</article>';
		}
		// List semantics only while the cards are articles. Once they are links,
		// role="list" would force role="listitem" onto each anchor and override the
		// link role — the same trap the hero tiles and social icons fell into.
		$linked = false;
		foreach ( $data['cards'] as $c ) {
			if ( '' !== (string) ( $c['href'] ?? '' ) ) {
				$linked = true;
				break;
			}
		}
		// The section used to end at the cards, so a reader who wanted the rest of
		// the club's news had nowhere to go — News existed but nothing led to it.
		// Absent link_href means no link, which is what a club with news switched
		// off gets.
		// Same classes as the sports section's "All sections →", so the two read as
		// one treatment rather than two links that happen to do the same job.
		$more = '' !== (string) ( $data['link_href'] ?? '' )
			? '<a class="ch-btn ch-btn--ghost ch-cards__all" href="' . self::e( (string) $data['link_href'] ) . '">'
				. self::e( (string) ( $data['link_label'] ?? 'All news →' ) ) . '</a>'
			: '';
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. ( $linked ? '<div class="ch-news">' : '<div class="ch-news" role="list">' )
			. $cards . '</div>' . $more . '</div></section>';
	}

	/** How the platform is named to a visitor. Anything else names no platform at all. */
	private const FEED_PLATFORMS = array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
	);

	/** Longest caption a card carries before it is cut — enough for a sentence, not a whole post. */
	private const FEED_CAPTION_MAX = 140;

	/**
	 * The club's recent social posts, drawn as cards that link back to the
	 * platform. Never a heading over an empty space: no posts means no band, in
	 * every one of the three states the feed can be in (see Social_Feed).
	 *
	 * Captions are plain text and are escaped like everything else here; the
	 * platform's own markup is never trusted, and no post is ever embedded.
	 *
	 * @param array{platform:string,heading:string,lede:string,
	 *   posts:array<int,array{id:string,image:string,caption:string,date:string,permalink:string}>} $data
	 */
	public static function social_feed( array $data ): string {
		$posts = $data['posts'] ?? array();
		if ( array() === $posts ) {
			return '';
		}
		$platform = self::FEED_PLATFORMS[ (string) ( $data['platform'] ?? '' ) ] ?? '';

		$cards = '';
		foreach ( $posts as $post ) {
			$caption = self::truncate( (string) ( $post['caption'] ?? '' ), self::FEED_CAPTION_MAX );
			$date    = self::feed_date( (string) ( $post['date'] ?? '' ) );
			$cards  .= '<a class="ch-feed__card" href="' . self::e( (string) $post['permalink'] ) . '">'
				// The image carries the caption as its alt text; a post whose
				// picture is its whole content would otherwise be silent.
				. self::media( (string) ( $post['image'] ?? '' ), $caption, 'ch-feed__media' )
				. ( '' !== $caption ? '<p class="ch-feed__caption">' . self::e( $caption ) . '</p>' : '' )
				. ( '' !== $date ? '<span class="ch-feed__date">' . self::e( $date ) . '</span>' : '' )
				. '</a>';
		}

		$lede = (string) ( $data['lede'] ?? '' );
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head"><div>'
			. ( '' !== $platform ? '<span class="ch-eyebrow">On ' . self::e( $platform ) . '</span>' : '' )
			. '<h2 class="ch-sec__title ch-sec__title--sm">' . self::e( (string) ( $data['heading'] ?? '' ) ) . '</h2>'
			. ( '' !== $lede ? '<p class="ch-feed__lede">' . self::e( $lede ) . '</p>' : '' )
			. '</div></div>'
			// Cards are links, so no role="list": it would force role="listitem"
			// onto each anchor and override the link role — the trap the news
			// cards and the hero tiles both fell into.
			. '<div class="ch-feed">' . $cards . '</div>'
			. '</div></section>';
	}

	/** Cut at a word boundary and mark the cut, or leave short text alone. */
	private static function truncate( string $text, int $max ): string {
		$text = trim( $text );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $max );
		$space = mb_strrpos( $cut, ' ' );
		if ( false !== $space && $space > 0 ) {
			$cut = mb_substr( $cut, 0, $space );
		}
		return rtrim( $cut ) . "\u{2026}";
	}

	/**
	 * An ISO 8601 timestamp as a date a reader recognises, or '' when there is
	 * no usable date — an unreadable one beside a post is worse than none.
	 * wp_date() applies the site's own timezone where WordPress is present; the
	 * preview has no WordPress, so it falls back to UTC.
	 */
	private static function feed_date( string $iso ): string {
		$iso = trim( $iso );
		if ( '' === $iso ) {
			return '';
		}
		$time = strtotime( $iso );
		if ( false === $time ) {
			return '';
		}
		return function_exists( 'wp_date' ) ? (string) wp_date( 'j M Y', $time ) : gmdate( 'j M Y', $time );
	}

	/**
	 * @param array{eyebrow:string,heading:string,
	 *   cards:array<int,array{tag:string,date:string,title:string,detail:string,cta_label:string,cta_href:string}>} $data
	 */
	public static function event_grid( array $data ): string {
		if ( array() === $data['cards'] ) {
			return self::empty_section( $data );
		}
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			// Both halves, or neither. A label with no link rendered a button that
			// looked live and reloaded the page when pressed — two of them shipped
			// on the demo Events page. Same rule as the contact details.
			$cta = '' !== ( $c['cta_label'] ?? '' ) && '' !== trim( (string) ( $c['cta_href'] ?? '' ) )
				? '<a class="ch-btn ch-btn--ghost ch-event__cta" href="' . self::e( $c['cta_href'] ) . '">' . self::e( $c['cta_label'] ) . '</a>'
				: '';
			$cards .= '<article class="ch-event" role="listitem">'
				. '<div class="ch-event__meta"><span class="ch-event__tag">' . self::e( $c['tag'] ?? '' ) . '</span>'
				. '<span class="ch-event__date">' . self::e( $c['date'] ?? '' ) . '</span></div>'
				. '<h3 class="ch-event__title">' . self::e( $c['title'] ?? '' ) . '</h3>'
				. '<p class="ch-event__detail">' . self::e( $c['detail'] ?? '' ) . '</p>'
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


	/**
	 * Google Maps search URL for a club address, built from the address lines we
	 * already render. Empty when there is no address, so the caller omits the link
	 * rather than emitting a dead one.
	 *
	 * @param array<int,string> $lines address lines
	 */
	public static function maps_url( array $lines ): string {
		$query = trim( implode( ', ', array_filter( array_map( 'trim', $lines ) ) ) );
		if ( '' === $query ) {
			return '';
		}
		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query );
	}

	/** @param array{eyebrow:string,heading:string,link_label:string,link_href:string,names:array<int,string>} $data */
	public static function sponsors( array $data ): string {
		// A club that has entered no sponsors gets no band. The heading and the
		// "Become a sponsor" button over an empty strip announced that the club
		// has no partners, which is worse than saying nothing (issue #163). A
		// sponsor saved without a name counts as none: it would only ever render
		// as a blank tile.
		$names = array_values( array_filter( array_map( 'trim', $data['names'] ), static fn( string $n ): bool => '' !== $n ) );
		if ( array() === $names ) {
			return '';
		}
		$tiles = '';
		foreach ( $names as $name ) {
			$tiles .= '<div class="ch-sponsors__tile" role="listitem">' . self::e( $name ) . '</div>';
		}
		$cta = '' !== $data['link_href']
			? '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . '</a>'
			: '';
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head"><div>'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title ch-sec__title--sm">' . self::e( $data['heading'] ) . '</h2></div>'
			. $cta . '</div>'
			. '<div class="ch-sponsors" role="list">' . $tiles . '</div></div></section>';
	}

	/**
	 * @param array{club_name:string,tagline:string,socials:array<string,string>,
	 *   columns:array<int,array{title:string,links:array<int,array{label:string,href:string}>}>,
	 *   newsletter:array{heading:string,lede:string,shortcode?:string},
	 *   copyright:string,
	 *   legal:array<int,array{label:string,href:string}>,
	 *   cookie?:string} $data
	 */
	public static function footer( array $data ): string {
		$cols = '';
		foreach ( $data['columns'] as $col ) {
			$links = '';
			foreach ( $col['links'] as $l ) {
				$links .= '<a class="ch-footer__link" href="' . self::e( $l['href'] ) . '">' . self::e( $l['label'] ) . '</a>';
			}
			// h2, not h4: the footer is its own landmark and nothing above these sits
			// at h3, so h4 jumped two levels and broke heading navigation on every page.
			$cols .= '<div class="ch-footer__col"><h2 class="ch-footer__h">' . self::e( $col['title'] ) . '</h2>' . $links . '</div>';
		}
		// Same rule as the contact form: a signup box that swallows an address and
		// says nothing is worse than none, so the input only appears once a real
		// form is behind it. Until then the column keeps its words and drops the box.
		$nl_shortcode = trim( (string) ( $data['newsletter']['shortcode'] ?? '' ) );
		$nl_form      = '' !== $nl_shortcode
			? '<div class="ch-footer__form ch-shortcode">'
				. Blueworx_Clubhouse_Shortcodes::expand( $nl_shortcode ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a shortcode's own output, same contract as shortcode_block().
				. '</div>'
			: '';
		$nl = '<div class="ch-footer__col ch-footer__nl"><h2 class="ch-footer__h">' . self::e( $data['newsletter']['heading'] ) . '</h2>'
			. '<p class="ch-footer__lede">' . self::e( $data['newsletter']['lede'] ) . '</p>'
			. $nl_form . '</div>';
		$legal = '';
		foreach ( $data['legal'] as $l ) {
			$legal .= '<a class="ch-footer__legal-link" href="' . self::e( $l['href'] ) . '">' . self::e( $l['label'] ) . '</a>';
		}
		$copyright = (string) ( $data['copyright'] ?? '' );
		// The bottom bar carries the copyright even on a club that has set no legal
		// links, so it renders whenever either half has something to say.
		$bottom = '';
		if ( '' !== $copyright || '' !== $legal ) {
			$bottom = '<div class="ch-footer__bottom">'
				. ( '' !== $copyright ? '<span class="ch-footer__copyright">' . self::e( $copyright ) . '</span>' : '' )
				. ( '' !== $legal ? '<div class="ch-footer__legal">' . $legal . '</div>' : '' )
				. '</div>';
		}

		// The club's name at poster scale, dropped almost into the background. It
		// is decoration repeating the name in the brand column directly above, so
		// it is hidden from screen readers rather than read out twice.
		$wordmark = '<div class="ch-footer__wordmark" aria-hidden="true">' . self::e( $data['club_name'] ) . '</div>';

		return '<footer class="ch-footer"><div class="ch-wrap">'
			. '<div class="ch-footer__grid">'
			. '<div class="ch-footer__brand-col">'
			. '<a class="ch-brand" href="' . self::e( Blueworx_Clubhouse_Links::url( 'home' ) ) . '">' . self::e( $data['club_name'] ) . '</a>'
			. '<p class="ch-footer__tagline">' . self::e( $data['tagline'] ) . '</p>'
			. '<div class="ch-footer__socials ch-social__links">' . self::social_links( $data['socials'], true ) . '</div></div>'
			. $cols . $nl . '</div>'
			. $wordmark
			. $bottom
			. '</div></footer>'
			// Outside the footer's wrap: it is fixed to the viewport, not part of
			// the footer's layout, and it must not inherit the footer's measure.
			. (string) ( $data['cookie'] ?? '' );
	}

	/** @param array{eyebrow:string,heading:string,cards:array<int,array{title:string,description:string}>} $data */
	public static function benefit_grid( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$cards .= '<article class="ch-benefit" role="listitem"><span class="ch-benefit__dot" aria-hidden="true"></span>'
				. '<h3 class="ch-benefit__title">' . self::e( $c['title'] ?? '' ) . '</h3>'
				. '<p class="ch-benefit__desc">' . self::e( $c['description'] ?? '' ) . '</p></article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-benefits" role="list">' . $cards . '</div></div></section>';
	}

	/**
	 * A person's photo when the club has set one, their initials when it has
	 * not. The initials block is the intended look for a club with no
	 * headshots, so it stays decorative (aria-hidden, the name is right below);
	 * a real photo takes the person's name as its alt text.
	 */
	private static function person_avatar( array $p ): string {
		$photo = (string) ( $p['photo'] ?? '' );
		$name  = (string) ( $p['name'] ?? '' );
		if ( '' !== $photo ) {
			return '<img class="ch-person__avatar ch-person__photo" src="' . self::e( $photo ) . '" alt="' . self::e( $name ) . '">';
		}
		return '<div class="ch-person__avatar ch-avatar" aria-hidden="true">' . self::e( self::initials( $name ) ) . '</div>';
	}

	/** @param array{eyebrow:string,heading:string,people:array<int,array{name:string,role:string,email:string,photo?:string}>} $data */
	public static function people_grid( array $data ): string {
		if ( array() === $data['people'] ) {
			return self::empty_section( $data );
		}
		$people = '';
		foreach ( $data['people'] as $p ) {
			$email = '' !== ( $p['email'] ?? '' )
				? '<a class="ch-person__email" href="mailto:' . self::e( $p['email'] ) . '">' . self::e( $p['email'] ) . '</a>' : '';
			$people .= '<article class="ch-person" role="listitem">'
				. self::person_avatar( $p )
				. '<span class="ch-person__role">' . self::e( $p['role'] ?? '' ) . '</span>'
				. '<h3 class="ch-person__name">' . self::e( $p['name'] ?? '' ) . '</h3>' . $email . '</article>';
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
			$rows .= '<div class="ch-milestone" role="listitem"><div class="ch-milestone__year">' . self::e( $m['year'] ?? '' ) . '</div>'
				. '<div class="ch-milestone__body"><h3 class="ch-milestone__title">' . self::e( $m['title'] ?? '' ) . '</h3>'
				. '<p class="ch-milestone__desc">' . self::e( $m['desc'] ?? '' ) . '</p></div></div>';
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
			$steps .= '<article class="ch-step" role="listitem"><span class="ch-step__num">' . self::e( $s['number'] ?? '' ) . '</span>'
				. '<h3 class="ch-step__title">' . self::e( $s['title'] ?? '' ) . '</h3>'
				. '<p class="ch-step__desc">' . self::e( $s['description'] ?? '' ) . '</p></article>';
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
				. '<summary class="ch-faq__q">' . self::e( $it['question'] ?? '' ) . '<span class="ch-faq__mark" aria-hidden="true"></span></summary>'
				. '<p class="ch-faq__a">' . self::e( $it['answer'] ?? '' ) . '</p></details>';
		}
		return '<section class="ch-sec"><div class="ch-wrap ch-faq-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-faq">' . $items . '</div></div></section>';
	}

	/**
	 * @param array{eyebrow:string,heading:string,club_name:string,shortcode?:string,
	 *   offline_note:string,submit_label:string,
	 *   info:array{heading:string,address:array<int,string>,email:string,phone:string,map:string,socials:array<string,string>}} $data
	 */
	public static function contact_form( array $data ): string {
		$addr = '';
		foreach ( $data['info']['address'] as $line ) {
			$addr .= '<span class="ch-contact__line">' . self::e( $line ) . '</span>';
		}
		// The club's own form, if they have built one. A form that posts nowhere is
		// worse than no form: a visitor types out an enquiry, presses send, and is
		// told nothing while the club never hears from them. So the built-in fields
		// only render when a real form is in place; otherwise the slot offers the
		// email address, which does work.
		$shortcode = trim( (string) ( $data['shortcode'] ?? '' ) );
		if ( '' !== $shortcode ) {
			$form = '<div class="ch-contact__form ch-shortcode">'
				. Blueworx_Clubhouse_Shortcodes::expand( $shortcode ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a shortcode's own output, same contract as shortcode_block().
				. '</div>';
		} else {
			$email = trim( (string) $data['info']['email'] );
			$form  = '<div class="ch-contact__form ch-contact__form--offline">'
				. '<p class="ch-contact__offline-note">' . self::e( $data['offline_note'] ) . '</p>'
				. ( '' !== $email
					? '<a class="ch-btn ch-btn--accent" href="mailto:' . self::e( $email ) . '">'
						. self::e( $data['submit_label'] ) . '</a>'
					: '' )
				. '</div>';
		}
		$tel = preg_replace( '/\s+/', '', $data['info']['phone'] );
		// The map is a link to Google Maps for the club's own address, not a dead
		// tile: with no map image set the placeholder was unfillable — there was no
		// field for it — and its alt said "ClubHouse" on every club's site.
		$club = trim( (string) ( $data['club_name'] ?? '' ) );
		$map  = self::media( (string) ( $data['info']['map'] ?? '' ), '' !== $club ? 'Map of ' . $club : 'Map', 'ch-contact__map' );
		$maps_href = self::maps_url( $data['info']['address'] );
		if ( '' !== $maps_href ) {
			$map = '<a class="ch-contact__map-link" href="' . self::e( $maps_href ) . '" target="_blank" rel="noopener">' . $map . '</a>';
		}
		$info = '<aside class="ch-contact__info"><h3 class="ch-contact__h">' . self::e( $data['info']['heading'] ) . '</h3>'
			. $map
			. '<div class="ch-contact__lines">' . $addr . '</div>'
			// Not every club publishes both. An empty value used to still print its
			// link, giving an empty target the keyboard could land on and a screen
			// reader would announce as an unlabelled link to "mailto:" or "tel:".
			. ( '' !== $data['info']['email']
				? '<a class="ch-contact__link" href="mailto:' . self::e( $data['info']['email'] ) . '">' . self::e( $data['info']['email'] ) . '</a>'
				: '' )
			. ( '' !== $data['info']['phone']
				? '<a class="ch-contact__link" href="tel:' . self::e( $tel ) . '">' . self::e( $data['info']['phone'] ) . '</a>'
				: '' )
			. '<div class="ch-contact__connect ch-social__links">' . self::social_links( $data['info']['socials'] ) . '</div></aside>';
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-contact">' . $form . $info . '</div></div></section>';
	}

	/**
	 * The member account card — sign in, request a reset link, set a new
	 * password, and the confirmations in between. One card renders all of them
	 * because they are one journey: a member who asks for a reset link should
	 * stay on the page they started on, in the club's own look, rather than being
	 * handed to wp-login.php halfway through.
	 *
	 * Unlike a content section, a narrow centred column is the expected shape for
	 * an auth form, so the width cap is deliberate — it is the only thing on the
	 * page. The heading is an <h1>: this page has no hero, so the card carries the
	 * page's main heading.
	 *
	 * @param array{eyebrow:string,heading:string,lede:string,email_label:string,
	 *   password_label:string,remember_label:string,forgot_label:string,forgot_href:string,
	 *   submit_label:string,join_prompt:string,join_label:string,join_href:string,
	 *   state:array{view:string,error:string,notice:string,form_action:string,hidden:string,redirect_to:string,logged_in:string}} $data
	 */
	public static function auth( array $data ): string {
		$state = $data['state'];
		$view  = $state['view'];

		$copy = self::auth_copy( $view, $data );
		$body = self::auth_message( $state )
			. self::auth_form( $view, $state, $data )
			. self::auth_alt( $view, $data );

		return '<section class="ch-sec"><div class="ch-wrap ch-auth-wrap"><div class="ch-auth" data-auth-view="' . self::e( $view ) . '">'
			. '<span class="ch-eyebrow">' . self::e( $copy['eyebrow'] ) . '</span>'
			. '<h1 class="ch-auth__title">' . self::e( $copy['heading'] ) . '</h1>'
			. '<p class="ch-auth__lede">' . self::e( $copy['lede'] ) . '</p>'
			. $body
			. '</div></div></section>';
	}

	/**
	 * Heading and lede per view. Sign-in keeps whatever the owner wrote in the
	 * content editor; the recovery screens are fixed wording, because they explain
	 * a mechanism rather than describe the club.
	 *
	 * @param array<string,mixed> $data
	 * @return array{eyebrow:string,heading:string,lede:string}
	 */
	private static function auth_copy( string $view, array $data ): array {
		switch ( $view ) {
			case 'forgot':
				return array(
					'eyebrow' => 'Members',
					'heading' => 'Forgotten your password?',
					'lede'    => 'Enter the email address or username on your membership and we will send you a link to set a new password.',
				);
			case 'sent':
				return array(
					'eyebrow' => 'Members',
					'heading' => 'Check your email',
					'lede'    => 'The link is valid for 24 hours. If nothing arrives, check your spam folder before trying again.',
				);
			case 'reset':
				return array(
					'eyebrow' => 'Members',
					'heading' => 'Set a new password',
					'lede'    => 'Choose something you do not use anywhere else.',
				);
			case 'resetok':
				return array(
					'eyebrow' => 'Members',
					'heading' => 'Password updated',
					'lede'    => 'You can sign in with your new password now.',
				);
			case 'signedout':
				return array(
					'eyebrow' => 'Members',
					'heading' => 'You are signed out',
					'lede'    => 'Sign back in whenever you need your membership, bookings or club events.',
				);
		}
		return array(
			'eyebrow' => (string) $data['eyebrow'],
			'heading' => (string) $data['heading'],
			'lede'    => (string) $data['lede'],
		);
	}

	/**
	 * The error or confirmation banner. role="alert" so a screen reader announces
	 * a rejected sign-in rather than leaving the member wondering what happened.
	 *
	 * @param array{error:string,notice:string} $state
	 */
	private static function auth_message( array $state ): string {
		if ( '' !== $state['error'] ) {
			return '<p class="ch-auth__msg ch-auth__msg--error" role="alert">' . self::e( $state['error'] ) . '</p>';
		}
		if ( '' !== $state['notice'] ) {
			return '<p class="ch-auth__msg ch-auth__msg--ok" role="status">' . self::e( $state['notice'] ) . '</p>';
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<string,mixed> $data
	 */
	private static function auth_form( string $view, array $state, array $data ): string {
		// The confirmation views have nothing to submit — the link back to sign in
		// is the whole call to action, and an empty form would only invite a click.
		if ( in_array( $view, array( 'sent', 'resetok', 'signedout' ), true ) ) {
			return '';
		}

		$action = (string) $state['form_action'];
		$hidden = (string) $state['hidden'];
		if ( '' !== $state['redirect_to'] ) {
			$hidden .= '<input type="hidden" name="redirect_to" value="' . self::e( (string) $state['redirect_to'] ) . '">';
		}
		// No action attribute in the preview, where there is nothing to post to;
		// the form still renders so the page can be reviewed.
		$open = '<form class="ch-auth__form" method="post"'
			. ( '' !== $action ? ' action="' . self::e( $action ) . '"' : '' ) . '>';

		if ( 'forgot' === $view ) {
			return $open . $hidden
				. '<input type="hidden" name="clubhouse_auth_action" value="forgot">'
				. '<label class="ch-field"><span class="ch-field__label">Email or username</span>'
				. '<input class="ch-field__input" type="text" name="user_login" autocomplete="username" required></label>'
				. '<button class="ch-btn ch-btn--accent ch-auth__submit" type="submit">Send reset link</button></form>';
		}

		if ( 'reset' === $view ) {
			return $open . $hidden
				. '<input type="hidden" name="clubhouse_auth_action" value="reset">'
				. '<label class="ch-field"><span class="ch-field__label">New password</span>'
				. '<input class="ch-field__input" type="password" name="pass1" autocomplete="new-password" required></label>'
				. '<label class="ch-field"><span class="ch-field__label">Repeat new password</span>'
				. '<input class="ch-field__input" type="password" name="pass2" autocomplete="new-password" required></label>'
				. '<button class="ch-btn ch-btn--accent ch-auth__submit" type="submit">Save password</button></form>';
		}

		$forgot = '' !== $data['forgot_href']
			? '<a class="ch-auth__forgot" href="' . self::e( (string) $data['forgot_href'] ) . '">' . self::e( (string) $data['forgot_label'] ) . '</a>'
			: '';
		return $open . $hidden
			. '<input type="hidden" name="clubhouse_auth_action" value="signin">'
			// type="text", not type="email": a WordPress account can be signed into
			// with either its username or its email, and type="email" would have the
			// browser refuse a perfectly valid username before it was ever sent.
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( (string) $data['email_label'] ) . '</span>'
			. '<input class="ch-field__input" type="text" name="user_login" autocomplete="username" required></label>'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( (string) $data['password_label'] ) . '</span>'
			. '<input class="ch-field__input" type="password" name="user_password" autocomplete="current-password" required></label>'
			. '<div class="ch-auth__row">'
			. '<label class="ch-auth__remember"><input type="checkbox" name="remember" value="1"><span>' . self::e( (string) $data['remember_label'] ) . '</span></label>'
			. $forgot . '</div>'
			. '<button class="ch-btn ch-btn--accent ch-auth__submit" type="submit">' . self::e( (string) $data['submit_label'] ) . '</button></form>';
	}

	/**
	 * The footer line under the card: an invitation to join on the sign-in form,
	 * and a way back to it from everywhere else.
	 *
	 * @param array<string,mixed> $data
	 */
	private static function auth_alt( string $view, array $data ): string {
		if ( 'signin' === $view ) {
			return '<p class="ch-auth__alt">' . self::e( (string) $data['join_prompt'] ) . ' '
				. '<a class="ch-auth__alt-link" href="' . self::e( (string) $data['join_href'] ) . '">' . self::e( (string) $data['join_label'] ) . '</a></p>';
		}
		return '<p class="ch-auth__alt"><a class="ch-auth__alt-link" href="' . self::e( (string) $data['signin_href'] ) . '">Back to sign in</a></p>';
	}

	/**
	 * The fixtures list, with its own filter pills directly above it when the
	 * page passes any. On Calendar the pills used to sit in the hero, above the
	 * booking calendar they have no effect on (issue #147); they belong with the
	 * list they narrow. They ride along into the empty state too, or a filter
	 * that matches nothing would leave no way back to "All".
	 *
	 * @param array{eyebrow:string,heading:string,
	 *   filters?:array<int,array{label:string,href:string,active:bool}>,filter_label?:string,
	 *   months:array<int,array{label:string,rows:array<int,array{date:string,competition:string,
	 *   matchup:string,detail:string,outcome:string}>}>} $data
	 */
	public static function calendar_months( array $data ): string {
		$filters = self::filter_nav(
			(array) ( $data['filters'] ?? array() ),
			(string) ( $data['filter_label'] ?? '' )
		);
		if ( array() === $data['months'] ) {
			return self::empty_section( array_merge( $data, array( 'after_head' => $filters ) ) );
		}
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
			. $filters
			. '<div class="ch-cal">' . $months . '</div></div></section>';
	}

	/** Task-tile icons — inline SVG, inherit colour via currentColor. */
	private const TILE_ICONS = array(
		'join'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>',
		'tour'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m16.24 7.76-1.804 5.411a2 2 0 0 1-1.265 1.265L7.76 16.24l1.804-5.411a2 2 0 0 1 1.265-1.265z"/></svg>',
		'fixtures' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>',
		'contact'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/></svg>',
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

	/** Self-hosted brand mark, inherits colour via currentColor — no hex, no icon font. */
	private const X_ICON = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">'
		. '<path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.65l-5.2-6.82-5.96 6.82H1.7l7.49-8.56L1.03 2.25h6.82l4.84 6.4 5.55-6.4Zm-1.16 17.52h1.83L7.01 4.13H5.05l12.03 15.64Z"/></svg>';

	/**
	 * The site's one social-link treatment: a branded pill per network the club
	 * actually has a URL for. Used by the social band, the footer, and the contact
	 * panel, so all three stay identical and none can carry a dead link.
	 *
	 * @param array<string,string> $urls      network name => URL (empty URL = no pill)
	 * @param bool                 $icon_only drop the visible network name, leaving a
	 *                                        round icon button. The link keeps its
	 *                                        aria-label, so the name is still announced.
	 */
	public static function social_links( array $urls, bool $icon_only = false ): string {
		$icons = array(
			'Facebook'  => self::FACEBOOK_ICON,
			'Instagram' => self::INSTAGRAM_ICON,
			'LinkedIn'  => self::LINKEDIN_ICON,
			'X'         => self::X_ICON,
		);
		$out = '';
		foreach ( $urls as $name => $url ) {
			if ( '' === $url ) {
				continue;
			}
			$icon  = $icons[ $name ] ?? '';
			$class = $icon_only ? 'ch-social__link ch-social__link--icon' : 'ch-social__link';
			$label = $icon_only ? '' : '<span class="ch-social__label">' . self::e( $name ) . '</span>';
			$out .= '<a class="' . $class . '" href="' . self::e( $url ) . '" aria-label="Follow us on ' . self::e( $name ) . '">'
				. '<span class="ch-social__icon" aria-hidden="true">' . $icon . '</span>'
				. $label . '</a>';
		}
		return $out;
	}

	/**
	 * The news index head: eyebrow, big highlighted title, and a standfirst set
	 * beside it rather than under it.
	 *
	 * @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string} $data
	 */
	public static function news_head( array $data ): string {
		return '<section class="ch-newshead"><div class="ch-wrap ch-newshead__in">'
			. '<div class="ch-newshead__titles">' . self::hero_head( 'ch-newshead', $data ) . '</div>'
			. '<p class="ch-newshead__lede">' . self::e( $data['lede'] ) . '</p>'
			. '</div></section>';
	}

	/**
	 * The lead story, as a single wide card that sits across the join between the
	 * head above it and the grid below.
	 *
	 * @param array{post:array{title:string,href:string,excerpt:string,category:string,date:string,read:string,image:string,image_alt:string},label:string,cta:string} $data
	 */
	public static function news_featured( array $data ): string {
		$p = $data['post'];
		return '<section class="ch-featured"><div class="ch-wrap">'
			. '<a class="ch-featured__card" href="' . self::e( $p['href'] ) . '">'
			. self::media( $p['image'], $p['image_alt'], 'ch-featured__media' )
			. '<div class="ch-featured__body">'
			. '<div class="ch-featured__meta">'
			. '<span class="ch-featured__flag">' . self::e( $data['label'] ) . '</span>'
			. '<span class="ch-featured__cat">' . self::e( $p['category'] ) . '</span>'
			. '<span class="ch-featured__date">' . self::e( self::dateline( $p ) ) . '</span></div>'
			. '<h2 class="ch-featured__title">' . self::e( $p['title'] ) . '</h2>'
			. '<p class="ch-featured__excerpt">' . self::e( $p['excerpt'] ) . '</p>'
			. '<span class="ch-featured__cta">' . self::e( $data['cta'] ) . '</span>'
			. '</div></a></div></section>';
	}

	/**
	 * The archive: category pills, a count, the grid, and a pager.
	 *
	 * Pills reuse the .ch-filter markup the sports and events pages already use,
	 * so a club's chosen look styles them once and they match everywhere.
	 *
	 * @param array{filter_label:string,filters:array<int,array{label:string,href:string,active:bool}>,
	 *   count_label:string,posts:array<int,array<string,mixed>>,empty_text:string,
	 *   pager:array{page:int,pages:int,prev_href:string,next_href:string,pages_list:array<int,array{label:string,href:string,active:bool}>}} $data
	 */
	public static function news_grid( array $data ): string {
		$pills = '';
		foreach ( $data['filters'] as $f ) {
			$on     = ! empty( $f['active'] ) ? ' ch-filter--on' : '';
			$pills .= '<a class="ch-filter' . $on . '" href="' . self::e( $f['href'] ) . '">' . self::e( $f['label'] ) . '</a>';
		}

		$cards = '';
		foreach ( $data['posts'] as $p ) {
			$cards .= '<article class="ch-postcard" role="listitem">'
				. '<a class="ch-postcard__link" href="' . self::e( (string) $p['href'] ) . '">'
				. self::media( (string) $p['image'], (string) $p['image_alt'], 'ch-postcard__media' )
				. '<div class="ch-postcard__meta">'
				. '<span class="ch-postcard__cat">' . self::e( (string) $p['category'] ) . '</span>'
				. '<span class="ch-postcard__date">' . self::e( self::dateline( $p ) ) . '</span></div>'
				. '<h3 class="ch-postcard__title">' . self::e( (string) $p['title'] ) . '</h3>'
				. '<p class="ch-postcard__excerpt">' . self::e( (string) $p['excerpt'] ) . '</p>'
				. '</a></article>';
		}

		$body = '' !== $cards
			? '<div class="ch-posts" role="list">' . $cards . '</div>'
			: '<p class="ch-empty">' . self::e( $data['empty_text'] ) . '</p>';

		return '<section class="ch-sec ch-newsgrid"><div class="ch-wrap">'
			. '<div class="ch-newsgrid__bar">'
			. '<nav class="ch-filters" aria-label="' . self::e( $data['filter_label'] ) . '">' . $pills . '</nav>'
			. '<span class="ch-newsgrid__count">' . self::e( $data['count_label'] ) . '</span>'
			. '</div>'
			. $body
			. self::pager( $data['pager'] )
			. '</div></section>';
	}

	/**
	 * Numbered paging, as real links.
	 *
	 * Nothing renders on a one-page archive: a pager showing a single disabled
	 * "1" tells the reader there is more and then refuses to give it to them.
	 *
	 * @param array{page:int,pages:int,prev_href:string,next_href:string,pages_list:array<int,array{label:string,href:string,active:bool}>} $pager
	 */
	private static function pager( array $pager ): string {
		if ( (int) $pager['pages'] < 2 ) {
			return '';
		}
		$numbers = '';
		foreach ( $pager['pages_list'] as $p ) {
			$on       = ! empty( $p['active'] ) ? ' ch-pager__no--on' : '';
			$numbers .= '<a class="ch-pager__no' . $on . '" href="' . self::e( $p['href'] ) . '"'
				. ( ! empty( $p['active'] ) ? ' aria-current="page"' : '' ) . '>' . self::e( $p['label'] ) . '</a>';
		}
		// A disabled button at either end is emitted as a span, not a dead link:
		// a link to the page you are already on is a trap for keyboard and screen
		// reader users, who cannot see that it is greyed out.
		$prev = '' !== $pager['prev_href']
			? '<a class="ch-pager__step" href="' . self::e( $pager['prev_href'] ) . '" rel="prev">← Previous</a>'
			: '<span class="ch-pager__step ch-pager__step--off">← Previous</span>';
		$next = '' !== $pager['next_href']
			? '<a class="ch-pager__step ch-pager__step--next" href="' . self::e( $pager['next_href'] ) . '" rel="next">Next →</a>'
			: '<span class="ch-pager__step ch-pager__step--off">Next →</span>';

		return '<nav class="ch-pager" aria-label="Pagination">' . $prev
			. '<div class="ch-pager__nos">' . $numbers . '</div>' . $next . '</nav>';
	}

	/** "24 July 2026 · 4 min read" — either half alone when the other is missing. */
	private static function dateline( array $post ): string {
		$parts = array_filter( array( (string) ( $post['date'] ?? '' ), (string) ( $post['read'] ?? '' ) ) );
		return implode( ' · ', $parts );
	}

	/**
	 * The head of an article: the way back, the category and dateline, the
	 * headline, the standfirst and the byline.
	 *
	 * @param array{back_label:string,back_href:string,post:array<string,mixed>} $data
	 */
	public static function post_head( array $data ): string {
		$p      = $data['post'];
		$author = (array) $p['author'];
		$avatar = '' !== (string) $author['initials']
			? '<span class="ch-byline__avatar" aria-hidden="true">' . self::e( (string) $author['initials'] ) . '</span>'
			: '';
		$byline = '' !== (string) $author['name']
			? '<div class="ch-byline">' . $avatar
				. '<span class="ch-byline__who"><span class="ch-byline__name">' . self::e( (string) $author['name'] ) . '</span>'
				. ( '' !== (string) $author['role'] ? '<span class="ch-byline__role">' . self::e( (string) $author['role'] ) . '</span>' : '' )
				. '</span></div>'
			: '';

		return '<section class="ch-posthead"><div class="ch-wrap"><div class="ch-posthead__in">'
			. '<a class="ch-posthead__back" href="' . self::e( $data['back_href'] ) . '">← ' . self::e( $data['back_label'] ) . '</a>'
			. '<div class="ch-posthead__meta">'
			. '<span class="ch-posthead__cat">' . self::e( (string) $p['category'] ) . '</span>'
			. '<span class="ch-posthead__date">' . self::e( self::dateline( $p ) ) . '</span></div>'
			. '<h1 class="ch-posthead__title">' . self::e( (string) $p['title'] ) . '</h1>'
			. ( '' !== (string) $p['standfirst'] ? '<p class="ch-posthead__standfirst">' . self::e( (string) $p['standfirst'] ) . '</p>' : '' )
			. $byline
			. '</div></div></section>';
	}

	/**
	 * The article's lead image and its caption. Nothing at all when there is no
	 * image — a caption under an empty box reads as a broken photo.
	 *
	 * @param array{image:string,image_alt:string,caption:string} $data
	 */
	public static function post_media( array $data ): string {
		if ( '' === $data['image'] ) {
			return '';
		}
		$caption = '' !== $data['caption']
			? '<figcaption class="ch-postmedia__caption">' . self::e( $data['caption'] ) . '</figcaption>'
			: '';
		return '<figure class="ch-postmedia"><div class="ch-wrap">'
			. self::media( $data['image'], $data['image_alt'], 'ch-postmedia__media' )
			. $caption . '</div></figure>';
	}

	/**
	 * The article body.
	 *
	 * This is the ONE section that emits stored markup unescaped, and it has to:
	 * a post is written in the WordPress editor and its paragraphs, headings,
	 * lists, quotes and images are the content. What keeps it contained is that
	 * the markup has already been through WordPress's own filters and its author
	 * needed the capability to publish a post in the first place — the same trust
	 * boundary every WordPress theme works inside.
	 *
	 * @param array{html:string,tags:array<int,string>} $data
	 */
	public static function post_body( array $data ): string {
		$tags = '';
		foreach ( $data['tags'] as $tag ) {
			// A chip with no word in it is a blob on the page, not a tag. Skipping
			// them here means the row disappears when every tag is blank, rather
			// than shrinking to an empty strip.
			if ( '' === trim( (string) $tag ) ) {
				continue;
			}
			$tags .= '<span class="ch-posttag">' . self::e( (string) $tag ) . '</span>';
		}
		$tag_row = '' !== $tags ? '<div class="ch-posttags">' . $tags . '</div>' : '';

		return '<section class="ch-postbody"><div class="ch-wrap"><div class="ch-postbody__in">'
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- post content; see the docblock above.
			. '<div class="ch-prose">' . $data['html'] . '</div>'
			. $tag_row
			. '</div></div></section>';
	}

	/**
	 * The author card under an article. Skipped entirely when nobody has written
	 * a biography, since a card holding only a name repeats the byline above it.
	 *
	 * @param array{label:string,author:array{name:string,role:string,initials:string,bio:string}} $data
	 */
	public static function post_author( array $data ): string {
		$a = $data['author'];
		if ( '' === trim( (string) $a['bio'] ) ) {
			return '';
		}
		return '<section class="ch-sec ch-postauthor"><div class="ch-wrap"><div class="ch-postauthor__in">'
			. '<span class="ch-postauthor__avatar" aria-hidden="true">' . self::e( (string) $a['initials'] ) . '</span>'
			. '<div class="ch-postauthor__body">'
			. '<span class="ch-eyebrow">' . self::e( $data['label'] ) . '</span>'
			. '<p class="ch-postauthor__name">' . self::e( (string) $a['name'] ) . '</p>'
			. '<p class="ch-postauthor__bio">' . self::e( (string) $a['bio'] ) . '</p>'
			. '</div></div></div></section>';
	}

	/**
	 * Share a story.
	 *
	 * Plain links to the share endpoints, not vendor buttons: an official share
	 * widget is a third-party script on every article that reads the page and
	 * the reader, in exchange for a button. These are ordinary anchors, so
	 * nothing loads and nobody is tracked until a reader chooses to go.
	 *
	 * Facebook, WhatsApp and email are what a club audience actually uses — a
	 * match report goes to a team group chat far more often than anywhere else.
	 * Copy link covers everything else, which is why it is here instead of a
	 * longer row of networks nobody at the club posts to.
	 *
	 * @param array{title:string,url:string} $data
	 */
	public static function post_share( array $data ): string {
		$url   = trim( (string) $data['url'] );
		$title = (string) $data['title'];
		if ( '' === $url ) {
			return '';
		}

		$targets = array(
			array( 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ) ),
			array( 'WhatsApp', 'https://wa.me/?text=' . rawurlencode( trim( $title . ' ' . $url ) ) ),
			array( 'Email', 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $url ) ),
		);

		$links = '';
		foreach ( $targets as $target ) {
			list( $label, $href ) = $target;
			// nofollow because a share link is not the club endorsing the network.
			$links .= '<a class="ch-share__link" href="' . self::e( $href ) . '" target="_blank" rel="noopener nofollow">'
				. '<span class="ch-share__sr">Share this story on </span>' . self::e( $label ) . '</a>';
		}

		// Ships hidden and is revealed by script only once copying is actually
		// available, rather than offering a button that looks live and silently
		// does nothing — see assets/js/share.js.
		$copy = '<button type="button" class="ch-share__link" hidden'
			. ' data-clubhouse-copy="' . self::e( $url ) . '" data-copied-label="Link copied">Copy link</button>';

		return '<section class="ch-sec ch-share"><div class="ch-wrap"><div class="ch-share__in">'
			. '<span class="ch-share__k">Share this story</span>'
			. '<div class="ch-share__links">' . $links . $copy . '</div>'
			. '</div></div></section>';
	}

	/**
	 * Previous and next, so a reader can work along the news rather than going
	 * back to the index between every story.
	 *
	 * Each half is drawn only when there is a story there: the oldest post has
	 * no previous and the newest has no next, and half a control is better than
	 * a link that goes nowhere. When neither exists — a club with one story —
	 * nothing is drawn at all.
	 *
	 * The titles are shown rather than bare arrows, because "Previous" alone
	 * asks a reader to click to find out what it is.
	 *
	 * @param array{previous:array{title:string,href:string}|null,next:array{title:string,href:string}|null} $data
	 */
	public static function post_steps( array $data ): string {
		$prev = self::post_step( $data['previous'] ?? null, 'prev', 'Previous story' );
		$next = self::post_step( $data['next'] ?? null, 'next', 'Next story' );
		if ( '' === $prev && '' === $next ) {
			return '';
		}
		return '<nav class="ch-sec ch-poststeps" aria-label="More stories">'
			. '<div class="ch-wrap"><div class="ch-poststeps__in">' . $prev . $next . '</div></div></nav>';
	}

	/**
	 * One half of the previous/next control.
	 *
	 * @param array{title:string,href:string}|null $step
	 */
	private static function post_step( ?array $step, string $dir, string $label ): string {
		if ( null === $step ) {
			return '';
		}
		return '<a class="ch-poststep ch-poststep--' . self::e( $dir ) . '" href="' . self::e( (string) $step['href'] ) . '">'
			. '<span class="ch-poststep__k">' . self::e( $label ) . '</span>'
			. '<span class="ch-poststep__title">' . self::e( (string) $step['title'] ) . '</span>'
			. '</a>';
	}

	/**
	 * "Keep reading" — three more posts, or nothing at all on a site with only
	 * one article, where an empty band would say the club has nothing else.
	 *
	 * @param array{heading:string,link_label:string,link_href:string,posts:array<int,array<string,mixed>>} $data
	 */
	public static function post_related( array $data ): string {
		if ( array() === $data['posts'] ) {
			return '';
		}
		$cards = '';
		foreach ( $data['posts'] as $p ) {
			$cards .= '<article class="ch-postcard ch-postcard--sm" role="listitem">'
				. '<a class="ch-postcard__link" href="' . self::e( (string) $p['href'] ) . '">'
				. self::media( (string) $p['image'], (string) $p['image_alt'], 'ch-postcard__media' )
				. '<div class="ch-postcard__meta">'
				. '<span class="ch-postcard__cat">' . self::e( (string) $p['category'] ) . '</span>'
				. '<span class="ch-postcard__date">' . self::e( (string) $p['date'] ) . '</span></div>'
				. '<h3 class="ch-postcard__title">' . self::e( (string) $p['title'] ) . '</h3>'
				. '</a></article>';
		}
		return '<section class="ch-sec ch-sec--alt ch-related"><div class="ch-wrap">'
			. '<div class="ch-related__bar">'
			. '<h2 class="ch-sec__title ch-sec__title--sm">' . self::e( $data['heading'] ) . '</h2>'
			. '<a class="ch-related__all" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . ' →</a>'
			. '</div>'
			. '<div class="ch-posts ch-posts--sm" role="list">' . $cards . '</div>'
			. '</div></section>';
	}

	/**
	 * The page's closing band: "follow us" links (not a live/embedded feed) and the
	 * find-us details in one light section flush against the footer. They were two
	 * stacked sections — a light social band above a dark info strip — which read as
	 * two endings and left a slab of dark between the content and the footer.
	 *
	 * Either half may be empty (its section toggle is off) and the band still works.
	 *
	 * @param array{heading:string,lede:string,facebook_url:string,instagram_url:string,linkedin_url:string,x_url:string,columns:array<int,array{label:string,lines:array<int,string>,link_label:string,link_href:string}>,cols_id?:string} $data
	 */
	public static function closing_band( array $data ): string {
		$social = '';
		if ( '' !== $data['heading'] || '' !== $data['lede'] ) {
			$links   = self::social_links( array(
				'Facebook'  => $data['facebook_url'],
				'Instagram' => $data['instagram_url'],
				'LinkedIn'  => $data['linkedin_url'],
				'X'         => (string) ( $data['x_url'] ?? '' ),
			) );
			$social  = '<div class="ch-wrap ch-social__in">'
				. '<div class="ch-social__text"><h2 class="ch-social__title">' . self::e( $data['heading'] ) . '</h2>'
				. '<p class="ch-social__lede">' . self::e( $data['lede'] ) . '</p></div>'
				. '<div class="ch-social__links">' . $links . '</div>'
				. '</div>';
		}
		$cols = '';
		foreach ( $data['columns'] as $c ) {
			$lines = '';
			foreach ( $c['lines'] as $line ) {
				$lines .= '<span class="ch-social__col-line">' . self::e( $line ) . '</span>';
			}
			$link = ( '' !== $c['link_label'] && '' !== $c['link_href'] )
				? '<a class="ch-social__col-link" href="' . self::e( $c['link_href'] ) . '">' . self::e( $c['link_label'] ) . ' →</a>' : '';
			$cols .= '<div class="ch-social__col" role="listitem"><div class="ch-social__col-label">' . self::e( $c['label'] ) . '</div>'
				. '<div class="ch-social__col-body">' . $lines . $link . '</div></div>';
		}
		if ( '' !== $cols ) {
			// The info columns are owner-editable content in their own right (Content
			// → Home → Find us details) but share this band's root with 'social' —
			// 'cols_id' lets Link_Catalogue's anchor for that content land on this
			// element instead of a section that doesn't exist.
			$only    = '' === $social ? ' ch-social__cols--only' : '';
			$cols_id = '' !== (string) ( $data['cols_id'] ?? '' ) ? ' id="' . self::e( $data['cols_id'] ) . '"' : '';
			$cols    = '<div class="ch-wrap ch-social__cols' . $only . '"' . $cols_id . ' role="list">' . $cols . '</div>';
		}
		if ( '' === $social && '' === $cols ) {
			return '';
		}
		return '<section class="ch-social">' . $social . $cols . '</section>';
	}
}
