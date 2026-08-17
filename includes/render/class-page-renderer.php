<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles a full HTML document for a Base Look + branding: <head> carries the
 * self-hosted @font-face rules (injected inline), the base stylesheet link, the
 * look stylesheet, and the derived :root variables; <body> is a string of
 * rendered sections.
 *
 * The eleven page methods that used to live here are gone: every page a club
 * composes is built by Page_Composer from that page's blocks. What is left is
 * the document shell, a handful of pure helpers the block layer shares, and the
 * three pages that are generated from a collection item rather than composed —
 * a sport, a team, and a single article. Each of those wears the same header
 * and footer as everywhere else, drawn from the same two singleton blocks.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Renderer {

	/**
	 * Structural rules shared by every look, loaded before the look's own
	 * stylesheet. Deliberately not a Base_Look method: a look substituting its
	 * own base is the drift this file prevents. Lives on the pure render layer
	 * (not Frontend) because Frontend::enqueue_specs() consumes it — the pure
	 * layer must not depend on the WordPress-coupled class that depends on it.
	 */
	public const BASE_STYLESHEET = 'assets/looks/base.css';

	public static function font_face_css( Blueworx_Clubhouse_Base_Look $look, string $base_url ): string {
		// Normalise to exactly one trailing slash so callers may pass the base with or
		// without it; an empty base stays empty (relative paths). Guards a future caller
		// against the '…pluginassets/fonts/…' footgun.
		$base = '' === $base_url ? '' : rtrim( $base_url, '/' ) . '/';
		$css  = '';
		foreach ( $look->fonts() as $font ) {
			$stem    = $font['stem'];
			$display = $font['display'];
			foreach ( $font['weights'] as $weight ) {
				$css .= "@font-face{font-family:'" . $font['family'] . "';"
					. 'font-style:normal;'
					. 'font-weight:' . (int) $weight . ';'
					. 'font-display:' . $display . ';'
					. 'src:url(' . $base . 'assets/fonts/' . $stem . '-' . $weight . '.woff2) format(\'woff2\')}';
			}
		}
		return $css;
	}

	public static function document(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding,
		string $body,
		string $plugin_url = ''
	): string {
		$vars     = Blueworx_Clubhouse_Theme_Css::compose( $look, $branding );
		$css      = Blueworx_Clubhouse_Theme_Css::to_css( $vars );
		$faces    = self::font_face_css( $look, $plugin_url );
		$base     = htmlspecialchars( $plugin_url . self::BASE_STYLESHEET, ENT_QUOTES, 'UTF-8' );
		$sheet    = htmlspecialchars( $plugin_url . $look->stylesheet(), ENT_QUOTES, 'UTF-8' );

		return '<!doctype html><html lang="en-GB"><head>'
			. '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . htmlspecialchars( $branding->get_club_name(), ENT_QUOTES, 'UTF-8' ) . '</title>'
			. '<style>' . $faces . '</style>'
			. '<link rel="stylesheet" href="' . $base . '">'
			. '<link rel="stylesheet" href="' . $sheet . '">'
			. '<style>' . $css . '</style>'
			. '</head><body>' . $body . self::inline_script( 'reveal.js' ) . self::inline_script( 'cookie-notice.js' )
			. self::inline_script( 'share.js' ) . '</body></html>';
	}

	/**
	 * Progressive-enhancement scroll reveal: adds .ch-reveal to each top-level block
	 * (skipping the hero, which has its own CSS load-in), then .is-in as it enters the
	 * viewport. Bails out with content fully visible when IntersectionObserver is absent
	 * or the user prefers reduced motion, so nothing is ever hidden without JS. Vanilla
	 * JS by design — no dependency; GSAP stays reserved for genuinely complex animation.
	 *
	 * Also carries the cookie notice's script, which reveals it and remembers its
	 * dismissal. WordPress enqueues both properly (Frontend::enqueue_assets); this
	 * is the preview's equivalent, so the preview shows the same page a visitor
	 * would see rather than one with a permanent bar across the bottom.
	 */
	private static function inline_script( string $file ): string {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/' . $file );
		return '<script>' . $js . '</script>';
	}

	/**
	 * A count written the way the copy writes it — "nine", "twenty-four".
	 *
	 * The default copy used to hardcode those words, so a club with six sections
	 * still read "Nine sports" in its own headline, footer and About page. The
	 * numbers now come from the collections that fill the page, so the claim and
	 * the list below it cannot disagree.
	 *
	 * Words up to ninety-nine, digits above: no club has a hundred sections, and
	 * a fallback that degrades to a numeral is better than one that runs out.
	 */
	public static function number_word( int $n ): string {
		$units = array( 'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
			'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen',
			'eighteen', 'nineteen' );
		$tens  = array( 2 => 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety' );

		if ( $n < 0 || $n > 99 ) {
			return (string) $n;
		}
		if ( $n < 20 ) {
			return $units[ $n ];
		}
		$ten = $tens[ intdiv( $n, 10 ) ];
		$rem = $n % 10;
		return 0 === $rem ? $ten : $ten . '-' . $units[ $rem ];
	}

	/** number_word() with its first letter capitalised, for the start of a sentence. */
	public static function number_word_upper( int $n ): string {
		return ucfirst( self::number_word( $n ) );
	}

	/**
	 * Resolve a stored image field to a URL. Stored values are attachment IDs
	 * (Task 6 saves `absint`); '' (no override) and any non-digit string (a raw
	 * URL, as every render/preview test passes) come back unchanged.
	 */
	private static function media_src( string $val ): string {
		if ( ctype_digit( $val ) && function_exists( 'wp_get_attachment_image_url' ) ) {
			$url = wp_get_attachment_image_url( (int) $val, 'large' );
			return is_string( $url ) ? $url : $val;
		}
		return $val;
	}

	/**
	 * Split a textarea's "one item per line" convention (how the catalogue stores
	 * lists like tier features or info-strip lines) into a trimmed, non-empty
	 * array. A value that is already an array (today's hardcoded defaults) passes
	 * through unchanged.
	 */
	private static function lines( mixed $val ): array {
		if ( is_array( $val ) ) {
			return $val;
		}
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) $val ) ), static fn( string $l ): bool => '' !== $l ) );
	}

	/** Lowercase, hyphenated slug — the one place a label becomes a filter slug.
	 * Public because Link_Catalogue builds filter targets from the same labels
	 * the pill rows do; two implementations would drift. */
	public static function slugify( string $s ): string {
		$s = strtolower( trim( $s ) );
		return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $s ), '-' );
	}

	/**
	 * Whether this page can actually take money: at least one tier whose button
	 * goes to the shop's checkout rather than the contact form. Pure.
	 *
	 * The Membership page used to promise "Join in five minutes" above steps
	 * that said register your interest, no payment yet, and we will be in touch
	 * within a few days (issue #90). Both halves were once true of some club, so
	 * the fix is not to pick one and hard-code it: it is for the page to say
	 * whichever is true here. Every line it decides is still only a default, so
	 * a club that has written its own copy keeps it.
	 *
	 * Read off the finished tiers rather than recomputed, so it cannot disagree
	 * with the buttons the visitor is looking at.
	 *
	 * @param array<int,array<string,mixed>> $tiers    From membership_tiers().
	 * @param string                         $checkout The checkout base URL, '' when there is none.
	 */
	public static function tiers_sell( array $tiers, string $checkout ): bool {
		if ( '' === $checkout ) {
			return false;
		}
		foreach ( $tiers as $tier ) {
			if ( str_starts_with( (string) ( $tier['cta_href'] ?? '' ), $checkout ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Stamp a section's root element with the id its anchor target points at.
	 *
	 * The id goes on the section's own root rather than a wrapper: the looks
	 * give .ch-main's children flow margins and reveal.js hides them until they
	 * scroll in, so an extra element in that child list would take styling meant
	 * for a section and shift the page. An already-identified root is left alone.
	 */
	public static function anchored( string $page, string $section, string $html ): string {
		if ( '' === $html || '<' !== $html[0] ) {
			return $html;
		}
		$id = Blueworx_Clubhouse_Link_Catalogue::anchor_id( $page, $section );
		// Match the opening tag's name only; a root that already carries an id
		// keeps it, because something else is relying on that one.
		if ( ! (bool) preg_match( '/^<([a-z][a-z0-9]*)(\s[^>]*)?>/i', $html, $m ) ) {
			return $html;
		}
		if ( isset( $m[2] ) && (bool) preg_match( '/\sid\s*=/i', $m[2] ) ) {
			return $html;
		}
		$rest = $m[2] ?? '';
		return '<' . $m[1] . ' id="' . $id . '"' . $rest . '>' . substr( $html, strlen( $m[0] ) );
	}

	/**
	 * One section's own page: the thing the Sports cards used to promise and not
	 * deliver. Assembled entirely from sections that already exist, so it inherits
	 * every look without a new visual language to maintain.
	 *
	 * Returns '' when no sport matches the slug, which is the caller's signal to
	 * fall back to the listing rather than render an empty page.
	 */
	public static function sport_page(
		string $slug,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		Blueworx_Clubhouse_Page_Composer $composer,
		string $logo_url = ''
	): string {
		$sport = self::find_by_slug( $collections->sports(), $slug );
		if ( null === $sport ) {
			return '';
		}
		$club  = $branding->get_club_name();
		$title = (string) $sport['title'];
		$out   = $composer->chrome_header( Blueworx_Clubhouse_Links::item_url( 'sports', $slug ), $branding, $visibility, $collections, $logo_url )
			. '<main class="ch-main" id="ch-main" tabindex="-1">';

		$out .= Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow'            => '' !== (string) $sport['label'] ? (string) $sport['label'] : 'Our sports',
			'title_lead'         => $title . ' ',
			'title_highlight'    => 'at ' . $club . '.',
			'lede'               => (string) $sport['subtitle'],
			'cta_primary'        => Blueworx_Clubhouse_Cta::JOIN,
			'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
			'cta_secondary'      => 'All sports',
			'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'sports' ),
			'image'              => self::media_src( (string) $sport['image'] ),
			'image_alt'          => $title . ' at ' . $club,
			'image_caption'      => '',
		) );

		$out .= self::section_detail_blocks( $sport, $title, $collections );
		$out .= '</main>' . $composer->chrome_footer( $branding, $visibility, $collections );
		return $out;
	}

	/**
	 * One team's own page. Same shape as sport_page(): the team's own words, when
	 * and where it trains, who to ask, and its fixtures.
	 */
	public static function team_page(
		string $slug,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		Blueworx_Clubhouse_Page_Composer $composer,
		string $logo_url = ''
	): string {
		$team = self::find_by_slug( $collections->teams(), $slug );
		if ( null === $team ) {
			return '';
		}
		$club  = $branding->get_club_name();
		$title = (string) $team['title'];
		$out   = $composer->chrome_header( Blueworx_Clubhouse_Links::item_url( 'teams', $slug ), $branding, $visibility, $collections, $logo_url )
			. '<main class="ch-main" id="ch-main" tabindex="-1">';

		$out .= Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow'            => (string) $team['sport'],
			'title_lead'         => $title . ' ',
			'title_highlight'    => '' !== (string) $team['league'] ? (string) $team['league'] . '.' : 'squad.',
			'lede'               => (string) $team['description'],
			'cta_primary'        => Blueworx_Clubhouse_Cta::JOIN,
			'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
			'cta_secondary'      => 'All teams',
			'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'teams' ),
			'image'              => self::media_src( (string) $team['image'] ),
			'image_alt'          => $title . ' at ' . $club,
			'image_caption'      => '',
		) );

		// A team's fixtures are its sport's, narrowed to the ones it plays in.
		$out .= self::section_detail_blocks( $team, (string) $team['sport'], $collections, $title );
		$out .= '</main>' . $composer->chrome_footer( $branding, $visibility, $collections );
		return $out;
	}

	/**
	 * The blocks a sport page and a team page share: what the club says about it,
	 * when it trains, who to ask, and its fixtures. Kept in one place because the
	 * two pages differ only in what fills them.
	 *
	 * @param array<string,mixed> $row        The sport or team.
	 * @param string              $sport_name The sport whose fixtures apply.
	 * @param string              $team_name  Narrow fixtures to this side, when given.
	 */
	private static function section_detail_blocks(
		array $row,
		string $sport_name,
		Blueworx_Clubhouse_Collections $collections,
		string $team_name = ''
	): string {
		$out = '';

		$description = trim( (string) ( $row['description'] ?? '' ) );
		if ( '' !== $description ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'paper',
				'eyebrow'   => 'About the section',
				'heading'   => (string) $row['title'],
				'lede'      => $description,
				'cta_label' => '',
				'cta_href'  => '',
			) );
		}

		// Training times and a name to ask for. Rendered only when the club has
		// filled them in — an empty "Training" heading answers nothing.
		$training = self::lines( (string) ( $row['training'] ?? '' ) );
		$contact  = trim( (string) ( $row['contact_name'] ?? '' ) );
		$email    = trim( (string) ( $row['contact_email'] ?? '' ) );
		if ( array() !== $training || '' !== $contact || '' !== $email ) {
			$out .= Blueworx_Clubhouse_Sections::info_panel( array(
				'eyebrow'       => 'Getting involved',
				'heading'       => 'Training and contacts',
				'training'      => $training,
				'contact_name'  => $contact,
				'contact_email' => $email,
			) );
		}

		$fixtures = array_values( array_filter(
			$collections->fixtures(),
			static function ( array $f ) use ( $sport_name, $team_name ): bool {
				// A fixture names its sport as "Rugby · 1st XV" — the sport, then the
				// side. Matching the whole string against "Rugby" found nothing, so
				// the sport is taken from the part before the separator and the side
				// from what follows.
				$parts = array_map( 'trim', explode( '·', (string) $f['sport'] ) );
				$sport = self::slugify( $parts[0] ?? '' );
				$side  = self::slugify( $parts[1] ?? '' );

				if ( '' !== $sport_name && $sport !== self::slugify( $sport_name ) ) {
					return false;
				}
				if ( '' === $team_name ) {
					return true;
				}
				$wanted = self::slugify( $team_name );
				return $side === $wanted
					|| self::slugify( (string) $f['home'] ) === $wanted
					|| self::slugify( (string) $f['away'] ) === $wanted;
			}
		) );
		if ( array() !== $fixtures ) {
			$out .= Blueworx_Clubhouse_Sections::calendar_months( array(
				'eyebrow'    => 'On the calendar',
				'heading'    => 'Fixtures & results',
				'empty_text' => '',
				'months'     => Blueworx_Clubhouse_Fixture_Projection::calendar_months( $fixtures ),
			) );
		}

		return $out;
	}

	/**
	 * The row whose title slugifies to $slug, or null. Matched on the derived slug
	 * rather than a stored one so a club renaming a section does not have to think
	 * about URLs — the same derivation the filter pills already use.
	 *
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<string,mixed>|null
	 */
	public static function find_by_slug( array $rows, string $slug ): ?array {
		$slug = self::slugify( $slug );
		if ( '' === $slug ) {
			return null;
		}
		foreach ( $rows as $row ) {
			if ( self::slugify( (string) $row['title'] ) === $slug ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * A single article.
	 *
	 * Not reached through the page map: an article lives at whatever permalink
	 * WordPress gives it, so the front end routes to this directly. It takes the
	 * same arguments as every other page renderer so the shell either side of it
	 * is composed identically — the header and footer here are the same header and
	 * footer as everywhere else on the site.
	 */
	public static function post(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		Blueworx_Clubhouse_Page_Composer $composer,
		string $logo_url = ''
	): string {
		$source = Blueworx_Clubhouse_News::source();
		$post   = null !== $source ? $source->current() : null;

		$out = $composer->chrome_header( Blueworx_Clubhouse_Links::url( 'news' ), $branding, $visibility, $collections, $logo_url )
			. '<main class="ch-main ch-main--article" id="ch-main" tabindex="-1">';

		if ( null === $post ) {
			// Routed here without a post to show. Say so in the club's own look
			// rather than falling through to a blank article shell.
			$out .= '<section class="ch-sec"><div class="ch-wrap"><p class="ch-empty">'
				. 'That story is no longer here. Everything the club has published is on the news page.'
				. '</p></div></section>';
			$out .= '</main>' . $composer->chrome_footer( $branding, $visibility, $collections );
			return $out;
		}

		// One <article> element holding the whole story, rather than five sections
		// sitting directly in <main>. The looks put a 96px flow margin between
		// main's children, which is the right rhythm between the bands of a landing
		// page and far too much between a byline and the first paragraph of the
		// piece it belongs to. Nesting takes the article out of that flow and lets
		// its own spacing govern, without a look having to know about news.
		$out .= '<article class="ch-article">';
		$out .= Blueworx_Clubhouse_Sections::post_head( array(
			'back_label' => 'All news',
			'back_href'  => Blueworx_Clubhouse_Links::url( 'news' ),
			'post'       => $post,
		) );
		$out .= Blueworx_Clubhouse_Sections::post_media( array(
			'image'     => (string) $post['image'],
			'image_alt' => (string) $post['image_alt'],
			'caption'   => (string) $post['image_caption'],
		) );
		$out .= Blueworx_Clubhouse_Sections::post_body( array(
			'html' => (string) $post['html'],
			'tags' => (array) $post['tags'],
		) );
		$out .= Blueworx_Clubhouse_Sections::post_author( array(
			'label'  => 'Written by',
			'author' => (array) $post['author'],
		) );
		$out .= Blueworx_Clubhouse_Sections::post_share( array(
			'title' => (string) $post['title'],
			'url'   => (string) $post['href'],
		) );
		$out .= '</article>';
		// Outside the article: what to read next is not part of this story.
		$out .= Blueworx_Clubhouse_Sections::post_steps(
			null !== $source ? $source->adjacent() : array( 'previous' => null, 'next' => null )
		);
		$out .= Blueworx_Clubhouse_Sections::post_related( array(
			'heading'    => 'Keep reading',
			'link_label' => 'All news',
			'link_href'  => Blueworx_Clubhouse_Links::url( 'news' ),
			'posts'      => null !== $source ? $source->related( Blueworx_Clubhouse_News::RELATED ) : array(),
		) );

		$out .= '</main>' . $composer->chrome_footer( $branding, $visibility, $collections );
		return $out;
	}
}
