<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a club site needs a search engine and a social card to understand about
 * a page — and whether each page actually says it.
 *
 * Two halves, both pure so they can be tested without a WordPress runtime or a
 * live site: tags() composes the meta a page should emit, and checks() reports
 * on markup that has already been rendered. The report reads the page the site
 * really serves rather than the settings behind it, so a section an owner has
 * switched off, or a heading a look changed, is judged as a visitor sees it.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Seo {

	/**
	 * Title and description lengths.
	 *
	 * These are not standards — search engines truncate by pixel width, not by
	 * character count, and the number moves. They are the widely used working
	 * ranges, used here to advise rather than to enforce: nothing is refused for
	 * being outside them, the report simply says so.
	 */
	public const TITLE_MIN = 30;
	public const TITLE_MAX = 65;
	public const DESC_MIN  = 120;
	public const DESC_MAX  = 160;

	public const PASS = 'pass';
	public const WARN = 'warn';
	public const FAIL = 'fail';

	/**
	 * Squeeze arbitrary page copy into a meta description: tags out, whitespace
	 * collapsed, cut at a word boundary rather than mid-word.
	 */
	public static function description( string $raw, int $limit = self::DESC_MAX ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', self::strip_tags_safe( $raw ) ) );
		if ( '' === $text || mb_strlen( $text ) <= $limit ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $limit );
		$space = mb_strrpos( $cut, ' ' );
		if ( false !== $space && $space > 0 ) {
			$cut = mb_substr( $cut, 0, $space );
		}
		return rtrim( $cut, " ,.;:—-" ) . '…';
	}

	/**
	 * The meta a page should emit.
	 *
	 * Emitted as a list of attribute/name/content triples rather than markup so
	 * the caller decides escaping, and so a test can assert on the values rather
	 * than on a string of HTML.
	 *
	 * @param array{title:string,description:string,url:string,site_name:string,type:string,image:string,image_width:int,image_height:int,image_alt:string,locale:string} $ctx
	 * @return array<int,array{attr:string,key:string,value:string}>
	 */
	public static function tags( array $ctx ): array {
		$title = trim( (string) ( $ctx['title'] ?? '' ) );
		$desc  = self::description( (string) ( $ctx['description'] ?? '' ) );
		$url   = trim( (string) ( $ctx['url'] ?? '' ) );
		$site  = trim( (string) ( $ctx['site_name'] ?? '' ) );
		$type  = trim( (string) ( $ctx['type'] ?? '' ) );
		$image = trim( (string) ( $ctx['image'] ?? '' ) );

		$tags = array();
		$add  = static function ( string $attr, string $key, string $value ) use ( &$tags ): void {
			// An empty tag is worse than an absent one: a scraper reads og:image=""
			// as an image that failed rather than as no image, and shows a blank card.
			if ( '' === trim( $value ) ) {
				return;
			}
			$tags[] = array( 'attr' => $attr, 'key' => $key, 'value' => $value );
		};

		$add( 'name', 'description', $desc );
		$add( 'property', 'og:title', $title );
		$add( 'property', 'og:description', $desc );
		$add( 'property', 'og:type', '' !== $type ? $type : 'website' );
		$add( 'property', 'og:url', $url );
		$add( 'property', 'og:site_name', $site );
		$add( 'property', 'og:locale', (string) ( $ctx['locale'] ?? '' ) );

		if ( '' !== $image ) {
			$add( 'property', 'og:image', $image );
			$width  = (int) ( $ctx['image_width'] ?? 0 );
			$height = (int) ( $ctx['image_height'] ?? 0 );
			if ( $width > 0 && $height > 0 ) {
				$add( 'property', 'og:image:width', (string) $width );
				$add( 'property', 'og:image:height', (string) $height );
			}
			$add( 'property', 'og:image:alt', (string) ( $ctx['image_alt'] ?? '' ) );
		}

		// summary_large_image only when there is an image to fill it; the plain
		// summary card is the honest choice when there is not.
		$add( 'name', 'twitter:card', '' !== $image ? 'summary_large_image' : 'summary' );
		$add( 'name', 'twitter:title', $title );
		$add( 'name', 'twitter:description', $desc );
		$add( 'name', 'twitter:image', $image );
		$add( 'name', 'twitter:image:alt', '' !== $image ? (string) ( $ctx['image_alt'] ?? '' ) : '' );

		return $tags;
	}

	/**
	 * @param array<int,array{attr:string,key:string,value:string}> $tags
	 */
	public static function render( array $tags ): string {
		$out = '';
		foreach ( $tags as $tag ) {
			$out .= '<meta ' . $tag['attr'] . '="' . htmlspecialchars( $tag['key'], ENT_QUOTES, 'UTF-8' ) . '"'
				. ' content="' . htmlspecialchars( $tag['value'], ENT_QUOTES, 'UTF-8' ) . '">' . "\n";
		}
		return $out;
	}

	/**
	 * Read the SEO-relevant facts out of a rendered page.
	 *
	 * Deliberately regex rather than a DOM parse: the report runs over every
	 * clubhouse page on one admin request, the markup is the plugin's own, and
	 * the questions asked (how many h1s, which images lack alt) are shallow.
	 *
	 * @return array{title:string,description:string,canonical:string,h1:int,images:int,no_alt:int,noindex:bool}
	 */
	public static function inspect( string $html ): array {
		return array(
			'title'       => self::first_match( '#<title[^>]*>(.*?)</title>#is', $html ),
			'description' => self::meta_content( $html, 'description' ),
			'canonical'   => self::first_match( '#<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']*)["\']#i', $html ),
			'h1'          => preg_match_all( '#<h1[\s>]#i', $html ),
			'images'      => preg_match_all( '#<img\b[^>]*>#i', $html ),
			'no_alt'      => self::images_without_alt( $html ),
			'noindex'     => false !== stripos( self::meta_content( $html, 'robots' ), 'noindex' ),
		);
	}

	private static function first_match( string $pattern, string $subject ): string {
		if ( 1 === preg_match( $pattern, $subject, $m ) ) {
			return trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
		}
		return '';
	}

	private static function meta_content( string $html, string $name ): string {
		$pattern = '#<meta[^>]+name=["\']' . preg_quote( $name, '#' ) . '["\'][^>]*content=["\']([^"\']*)["\']#i';
		return self::first_match( $pattern, $html );
	}

	/**
	 * An <img> with no alt attribute at all. alt="" is NOT counted: it is the
	 * correct markup for a decorative image, and flagging it would push someone
	 * towards writing alt text for a divider line.
	 */
	private static function images_without_alt( string $html ): int {
		if ( 0 === preg_match_all( '#<img\b[^>]*>#i', $html, $m ) ) {
			return 0;
		}
		$missing = 0;
		foreach ( $m[0] as $tag ) {
			if ( 1 !== preg_match( '#\salt\s*=#i', $tag ) ) {
				++$missing;
			}
		}
		return $missing;
	}

	/**
	 * Judge an inspected page. Each result is one plain sentence an owner can act
	 * on — no scores, and no rule that fails a page for something outside the
	 * owner's control.
	 *
	 * @param array{title:string,description:string,canonical:string,h1:int,images:int,no_alt:int,noindex:bool} $doc
	 * @return array<int,array{key:string,label:string,status:string,detail:string}>
	 */
	public static function checks( array $doc ): array {
		$out = array();

		$title     = (string) $doc['title'];
		$title_len = mb_strlen( $title );
		if ( '' === $title ) {
			$out[] = self::result( 'title', 'Page title', self::FAIL, 'This page has no title. Search results and browser tabs will show the address instead.' );
		} elseif ( $title_len > self::TITLE_MAX ) {
			$out[] = self::result( 'title', 'Page title', self::WARN, 'The title is ' . $title_len . ' characters, so search results will cut it short. Around ' . self::TITLE_MIN . '–' . self::TITLE_MAX . ' reads best.' );
		} elseif ( $title_len < self::TITLE_MIN ) {
			$out[] = self::result( 'title', 'Page title', self::WARN, 'The title is only ' . $title_len . ' characters. A little more detail helps people choose this page from a list of results.' );
		} else {
			$out[] = self::result( 'title', 'Page title', self::PASS, $title );
		}

		$desc     = (string) $doc['description'];
		$desc_len = mb_strlen( $desc );
		if ( '' === $desc ) {
			$out[] = self::result( 'description', 'Description', self::FAIL, 'No description, so search engines will invent one from whatever text they find first.' );
		} elseif ( $desc_len > self::DESC_MAX ) {
			$out[] = self::result( 'description', 'Description', self::WARN, 'The description is ' . $desc_len . ' characters and will be cut off around ' . self::DESC_MAX . '.' );
		} elseif ( $desc_len < self::DESC_MIN ) {
			$out[] = self::result( 'description', 'Description', self::WARN, 'The description is ' . $desc_len . ' characters. Nearer ' . self::DESC_MIN . '–' . self::DESC_MAX . ' gives you the full space in a search result.' );
		} else {
			$out[] = self::result( 'description', 'Description', self::PASS, $desc );
		}

		$canonical = (string) $doc['canonical'];
		if ( '' === $canonical ) {
			$out[] = self::result( 'canonical', 'Canonical address', self::WARN, 'No canonical address. If this page is reachable at more than one URL, search engines have to guess which is the real one.' );
		} elseif ( ! preg_match( '#^https?://#i', $canonical ) ) {
			$out[] = self::result( 'canonical', 'Canonical address', self::FAIL, 'The canonical address is not a full web address, which search engines ignore.' );
		} else {
			$out[] = self::result( 'canonical', 'Canonical address', self::PASS, $canonical );
		}

		$h1 = (int) $doc['h1'];
		if ( 0 === $h1 ) {
			$out[] = self::result( 'h1', 'Main heading', self::FAIL, 'This page has no main heading, so nothing tells a reader or a screen reader what it is about.' );
		} elseif ( $h1 > 1 ) {
			$out[] = self::result( 'h1', 'Main heading', self::WARN, $h1 . ' main headings on one page. One is the rule — the rest should be section headings.' );
		} else {
			$out[] = self::result( 'h1', 'Main heading', self::PASS, 'One main heading, as it should be.' );
		}

		$images = (int) $doc['images'];
		$no_alt = (int) $doc['no_alt'];
		if ( 0 === $images ) {
			$out[] = self::result( 'alt', 'Image descriptions', self::PASS, 'No images on this page.' );
		} elseif ( $no_alt > 0 ) {
			$out[] = self::result( 'alt', 'Image descriptions', self::FAIL, $no_alt . ' of ' . $images . ' images have no description, so anyone using a screen reader is told nothing about them.' );
		} else {
			$out[] = self::result( 'alt', 'Image descriptions', self::PASS, 'All ' . $images . ' images are described.' );
		}

		if ( (bool) $doc['noindex'] ) {
			// A warning, not a failure: hiding a page from search can be exactly what
			// an owner meant. It is flagged because it is easy to do by accident and
			// invisible from the page itself.
			$out[] = self::result( 'robots', 'Search engines', self::WARN, 'This page asks search engines not to list it. That is a WordPress-wide setting under Settings → Reading.' );
		} else {
			$out[] = self::result( 'robots', 'Search engines', self::PASS, 'This page can appear in search results.' );
		}

		return $out;
	}

	/** @return array{key:string,label:string,status:string,detail:string} */
	private static function result( string $key, string $label, string $status, string $detail ): array {
		return array( 'key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail );
	}

	/**
	 * The worst status in a set — what a page's row shows at a glance.
	 *
	 * @param array<int,array{status:string}> $checks
	 */
	public static function worst( array $checks ): string {
		$status = self::PASS;
		foreach ( $checks as $check ) {
			if ( self::FAIL === $check['status'] ) {
				return self::FAIL;
			}
			if ( self::WARN === $check['status'] ) {
				$status = self::WARN;
			}
		}
		return $status;
	}

	/**
	 * wp_strip_all_tags where it exists, a plain strip where it does not — this
	 * class also runs in the preview and in unit tests, with no WordPress loaded.
	 */
	private static function strip_tags_safe( string $text ): string {
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return (string) wp_strip_all_tags( $text );
		}
		return (string) preg_replace( '/<[^>]*>/', '', $text );
	}
}
