<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles a full HTML document for a Base Look + branding: <head> carries the
 * self-hosted @font-face rules (injected inline), the base stylesheet link, the
 * look stylesheet, and the derived :root variables; <body> is a string of
 * rendered sections. home()
 * composes the demo Home shell, honouring per-section visibility. The same
 * output is what WordPress template_include will later echo — the preview is
 * just an earlier caller.
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

		return '<!doctype html><html lang="en"><head>'
			. '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . htmlspecialchars( $branding->get_club_name(), ENT_QUOTES, 'UTF-8' ) . '</title>'
			. '<style>' . $faces . '</style>'
			. '<link rel="stylesheet" href="' . $base . '">'
			. '<link rel="stylesheet" href="' . $sheet . '">'
			. '<style>' . $css . '</style>'
			. '</head><body>' . $body . self::reveal_script() . '</body></html>';
	}

	/**
	 * Progressive-enhancement scroll reveal: adds .ch-reveal to each top-level block
	 * (skipping the hero, which has its own CSS load-in), then .is-in as it enters the
	 * viewport. Bails out with content fully visible when IntersectionObserver is absent
	 * or the user prefers reduced motion, so nothing is ever hidden without JS. Vanilla
	 * JS by design — no dependency; GSAP stays reserved for genuinely complex animation.
	 */
	private static function reveal_script(): string {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/reveal.js' );
		return '<script>' . $js . '</script>';
	}

	/** Read a single content field, falling back to the hardcoded default when unset or no store. */
	private static function cget( ?Blueworx_Clubhouse_Content_Store $c, string $page, string $sec, string $field, mixed $default ): mixed {
		if ( null === $c ) {
			return $default;
		}
		$v = $c->get( $page, $sec, $field, null );
		return ( null === $v || '' === $v ) ? $default : $v;
	}

	/** Read a loop's stored items, falling back to the hardcoded default array when none saved. */
	private static function citems( ?Blueworx_Clubhouse_Content_Store $c, string $page, string $sec, array $default ): array {
		if ( null === $c ) {
			return $default;
		}
		$items = $c->get_items( $page, $sec );
		return array() === $items ? $default : $items;
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

	/**
	 * The membership tiers — the single source both the Membership page and the
	 * Home teaser render, so an owner's edits under Content → Membership → Tiers
	 * reach both. Returns tier_grid()-shaped rows (Home overrides the CTA to
	 * funnel to the Membership page; Membership keeps the source's own CTA).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function membership_tiers( ?Blueworx_Clubhouse_Content_Store $content ): array {
		$default = array(
			array( 'eyebrow' => 'Under 18', 'name' => 'Junior', 'price' => '£12', 'period' => '/mo',
				'features' => array( 'Any junior section', 'Coaching included', 'Holiday camp discounts' ),
				'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
			array( 'eyebrow' => 'Full playing', 'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
				'features' => array( 'Any section, any level', 'League affiliation', 'Clubhouse & socials' ),
				'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
			array( 'eyebrow' => 'Best value', 'name' => 'Family', 'price' => '£45', 'period' => '/mo',
				'features' => array( 'Up to 5 members', 'Any sections', 'Priority event booking' ),
				'recommended' => true, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
			array( 'eyebrow' => 'Off the pitch', 'name' => 'Social', 'price' => '£12', 'period' => '/mo',
				'features' => array( 'Full clubhouse access', 'Member events', 'Support your club' ),
				'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
		);
		$items = self::citems( $content, 'membership', 'tiers', $default );
		return array_map(
			static function ( array $t ): array {
				return array(
					'eyebrow'     => (string) ( $t['eyebrow'] ?? '' ),
					'name'        => (string) ( $t['name'] ?? '' ),
					'price'       => (string) ( $t['price'] ?? '' ),
					'period'      => (string) ( $t['period'] ?? '' ),
					'features'    => self::lines( $t['features'] ?? array() ),
					'recommended' => (bool) ( $t['featured'] ?? ( $t['recommended'] ?? false ) ),
					'cta_label'   => (string) ( $t['cta_label'] ?? '' ),
					'cta_href'    => (string) ( $t['cta_href'] ?? '' ),
				);
			},
			$items
		);
	}

	private static function shell_header( string $club, string $active, Blueworx_Clubhouse_Visibility $visibility, string $logo_url = '', ?Blueworx_Clubhouse_Content_Store $content = null ): string {
		// The announcement bar is owner-configurable (Content → Global → Header):
		// a show/hide toggle plus editable text + link. When off — or when the text
		// is cleared — Sections::header()'s empty-string guard drops the markup.
		$banner_on   = (bool) self::cget( $content, 'global', 'header', 'banner_show', true );
		$banner_text = $banner_on
			? self::cget( $content, 'global', 'header', 'banner', 'Summer sign-ups are open — register your interest for 2026/27 →' )
			: '';
		return Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => $club,
			'banner'      => $banner_text,
			'banner_href' => self::cget( $content, 'global', 'header', 'banner_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
			'nav'         => self::nav_links( array(
				array( 'label' => 'Home', 'key' => 'home' ),
				array( 'label' => 'About', 'key' => 'about' ),
				array( 'label' => 'Sports', 'key' => 'sports' ),
				array( 'label' => 'Teams', 'key' => 'teams' ),
				array( 'label' => 'Membership', 'key' => 'membership' ),
				array( 'label' => 'Events', 'key' => 'events' ),
				array( 'label' => 'Calendar', 'key' => 'calendar' ),
				array( 'label' => 'Contact', 'key' => 'contact' ),
			), $visibility ),
			'active'      => $active,
			'login'       => 'Log in',
			'login_href'  => Blueworx_Clubhouse_Links::url( 'login' ),
			'join'        => self::cget( $content, 'global', 'header', 'join', Blueworx_Clubhouse_Cta::JOIN ),
			'join_href'   => self::cget( $content, 'global', 'header', 'join_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
			'logo'        => $logo_url,
		) );
	}

	private static function shell_footer( string $club, Blueworx_Clubhouse_Visibility $visibility, Blueworx_Clubhouse_Branding $branding, ?Blueworx_Clubhouse_Content_Store $content = null ): string {
		return Blueworx_Clubhouse_Sections::footer( array(
			'club_name'  => $club,
			'tagline'    => self::cget( $content, 'global', 'footer', 'tagline', 'Nine sports, one club. A home ground for every team, and everyone who follows them.' ),
			'socials'    => array(
				'Facebook'  => $branding->get_facebook_url(),
				'Instagram' => $branding->get_instagram_url(),
				'LinkedIn'  => $branding->get_linkedin_url(),
			),
			'columns'    => array(
				array( 'title' => 'Club', 'links' => self::nav_links( array(
					array( 'label' => 'About', 'key' => 'about' ),
					array( 'label' => 'Sports', 'key' => 'sports' ),
					array( 'label' => 'Teams', 'key' => 'teams' ),
					array( 'label' => 'Events', 'key' => 'events' ),
				), $visibility ) ),
				array( 'title' => 'Get involved', 'links' => self::nav_links( array(
					array( 'label' => 'Membership', 'key' => 'membership' ),
					array( 'label' => 'Calendar', 'key' => 'calendar' ),
					array( 'label' => 'Volunteer', 'key' => 'contact' ),
					array( 'label' => 'Contact', 'key' => 'contact' ),
				), $visibility ) ),
			),
			'newsletter' => array(
				'heading'     => 'Stay in the loop',
				'lede'        => 'Fixtures, results and club news — one email a month.',
				'placeholder' => 'Your email',
				'cta'         => 'Subscribe',
			),
			'legal'      => array(),
		) );
	}

	/**
	 * Drop links whose target page is hidden, then resolve each surviving page
	 * key to its real URL via the Links seam. Filtering by key (not by parsing a
	 * resolved href) keeps hidden-page omission working whether links render as
	 * the preview '?page=' form or real WordPress permalinks.
	 *
	 * @param array<int,array{label:string,key:string}> $items
	 * @return array<int,array{label:string,href:string}>
	 */
	private static function nav_links( array $items, Blueworx_Clubhouse_Visibility $visibility ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( ! $visibility->is_page_visible( $item['key'] ) ) {
				continue;
			}
			$out[] = array( 'label' => $item['label'], 'href' => Blueworx_Clubhouse_Links::url( $item['key'] ) );
		}
		return $out;
	}

	public static function home(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = '';

		if ( $visibility->is_section_visible( 'home', 'header' ) ) {
			$out .= self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'home' ), $visibility, $logo_url, $content );
		}
		$out .= '<main class="ch-main" id="ch-main" tabindex="-1">';
		if ( $visibility->is_section_visible( 'home', 'hero' ) ) {
			// Home uses the full-bleed home_hero() (not the shared hero()); the
			// quick-links live in its foot, so no separate quick_tiles section here.
			$out .= Blueworx_Clubhouse_Sections::home_hero( array(
				'eyebrow'            => self::cget( $content, 'home', 'hero', 'eyebrow', 'Est. 1974 · Marlow, UK' ),
				'title_lead'         => self::cget( $content, 'home', 'hero', 'title_lead', 'Every sport. Every age. ' ),
				'title_highlight'    => self::cget( $content, 'home', 'hero', 'title_highlight', 'One community.' ),
				'lede'               => self::cget( $content, 'home', 'hero', 'lede', "Nine sports, twenty-four teams, and a clubhouse that's always open. Come for the game — stay for the people." ),
				// Off by default — the quick-tile row below repeats these actions. Still
				// configurable: an owner who sets a label in the catalogue gets the
				// button pair back (see home_hero()).
				'cta_primary'        => self::cget( $content, 'home', 'hero', 'cta_primary', '' ),
				'cta_primary_href'   => self::cget( $content, 'home', 'hero', 'cta_primary_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
				'cta_secondary'      => self::cget( $content, 'home', 'hero', 'cta_secondary', '' ),
				'cta_secondary_href' => self::cget( $content, 'home', 'hero', 'cta_secondary_href', Blueworx_Clubhouse_Links::url( 'about' ) ),
				'image'              => self::media_src( (string) self::cget( $content, 'home', 'hero', 'image', '' ) ),
				'image_alt'          => 'ClubHouse floodlit pitch on a Saturday',
				'tiles'              => self::citems( $content, 'home', 'quick_tiles', array(
					array( 'label' => Blueworx_Clubhouse_Cta::JOIN, 'href' => Blueworx_Clubhouse_Links::url( 'membership' ), 'icon' => 'join' ),
					array( 'label' => 'Take a tour', 'href' => Blueworx_Clubhouse_Links::url( 'about' ), 'icon' => 'tour' ),
					array( 'label' => 'See fixtures', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ), 'icon' => 'fixtures' ),
					array( 'label' => 'Get in touch', 'href' => Blueworx_Clubhouse_Links::url( 'contact' ), 'icon' => 'contact' ),
				) ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'ticker' ) ) {
			$default = array(
				array( 'text' => '1st XV promoted to Div 3 South' ),
				array( 'text' => 'Open Day — Sat 26 Jul, 10:00–14:00' ),
				array( 'text' => 'Clubhouse refurbishment complete' ),
				array( 'text' => 'Summer Football Camp · 4–8 Aug' ),
			);
			$items = self::citems( $content, 'home', 'ticker', $default );
			$out  .= Blueworx_Clubhouse_Sections::ticker( array_values( array_map(
				static fn( array $i ): string => (string) ( $i['text'] ?? '' ),
				$items
			) ) );
		}
		if ( $visibility->is_section_visible( 'home', 'stats' ) ) {
			$out .= Blueworx_Clubhouse_Sections::stat_strip( self::citems( $content, 'home', 'stats', array(
				array( 'value' => '900+', 'label' => 'Members', 'featured' => true ),
				array( 'value' => '9', 'label' => 'Sports' ),
				array( 'value' => '24', 'label' => 'Teams' ),
				array( 'value' => '1974', 'label' => 'Founded' ),
			) ) );
		}
		if ( $visibility->is_section_visible( 'home', 'sports' ) ) {
			$sports = array_slice( $collections->sports(), 0, 4 );
			$out .= Blueworx_Clubhouse_Sections::card_grid( array(
				'eyebrow'    => self::cget( $content, 'home', 'sports', 'eyebrow', 'Our sports' ),
				'heading'    => self::cget( $content, 'home', 'sports', 'heading', 'Pick your game.' ),
				'link_label' => 'All sections →',
				'link_href'  => Blueworx_Clubhouse_Links::url( 'sports' ),
				'cards'      => array_map(
					static function ( array $s ): array {
						return array(
							'image'     => $s['image'],
							'image_alt' => $s['title'],
							'tag'       => $s['label'],
							'title'     => $s['title'],
							'subtitle'  => $s['subtitle'],
						);
					},
					$sports
				),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'clubhouse' ) ) {
			$out .= Blueworx_Clubhouse_Sections::image_band( array(
				'eyebrow'   => self::cget( $content, 'home', 'clubhouse', 'eyebrow', 'The clubhouse' ),
				'heading'   => self::cget( $content, 'home', 'clubhouse', 'heading', "Bar, kitchen and a full social calendar — the club doesn\u{2019}t stop at the final whistle" ),
				'image'     => self::media_src( (string) self::cget( $content, 'home', 'clubhouse', 'image', '' ) ), 'image_alt' => 'ClubHouse pavilion at dusk',
				'cta_label' => self::cget( $content, 'home', 'clubhouse', 'cta_label', 'Visit us' ), 'cta_href' => self::cget( $content, 'home', 'clubhouse', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'membership' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'accent',
				'eyebrow'   => self::cget( $content, 'home', 'membership', 'eyebrow', 'Membership' ),
				'heading'   => self::cget( $content, 'home', 'membership', 'heading', 'Open to everyone, from £28/month.' ),
				'lede'      => self::cget( $content, 'home', 'membership', 'lede', 'From first-timers to county players — every tier includes clubhouse access, discounted events and a free trial session.' ),
				'cta_label' => self::cget( $content, 'home', 'membership', 'cta_label', Blueworx_Clubhouse_Cta::JOIN . ' →' ),
				'cta_href'  => self::cget( $content, 'home', 'membership', 'cta_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
			) );
			// The Home tier grid mirrors the single Membership tiers source, then
			// funnels each CTA to the fuller Membership page (where conversion → contact
			// happens). Editing the Membership tiers updates both pages.
			$home_tiers = array_map(
				static function ( array $t ): array {
					$t['cta_label'] = 'Join';
					$t['cta_href']  = Blueworx_Clubhouse_Links::url( 'membership' );
					return $t;
				},
				self::membership_tiers( $content )
			);
			$out .= Blueworx_Clubhouse_Sections::tier_grid( $home_tiers );
		}
		if ( $visibility->is_section_visible( 'home', 'activity' ) ) {
			$out .= Blueworx_Clubhouse_Sections::activity_tabs( array(
				'eyebrow'  => 'Club activity',
				'heading'  => "What\u{2019}s happening",
				'fixtures' => Blueworx_Clubhouse_Fixture_Projection::home_fixtures( $collections->fixtures() ),
				'events'   => array_map(
					static function ( array $e ): array {
						return array( 'tag' => $e['tag'], 'date' => $e['date'], 'title' => $e['title'], 'detail' => $e['detail'] );
					},
					array_slice( array_values( array_filter( $collections->events(), static fn( $e ) => 'upcoming' === $e['status'] ) ), 0, 3 )
				),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'news' ) ) {
			$default = array(
				array( 'image' => '', 'image_alt' => 'Clubhouse interior', 'tag' => 'Club news', 'date' => '2 Jul', 'title' => 'Clubhouse refurbishment complete' ),
				array( 'image' => '', 'image_alt' => 'Junior footballers', 'tag' => 'Sections', 'date' => '28 Jun', 'title' => 'Junior Football signs 40 new players' ),
				array( 'image' => '', 'image_alt' => 'Volunteers', 'tag' => 'Volunteering', 'date' => '24 Jun', 'title' => 'Volunteers needed for the Open Day' ),
			);
			$items = self::citems( $content, 'home', 'news', $default );
			$out .= Blueworx_Clubhouse_Sections::news_cards( array(
				'eyebrow' => self::cget( $content, 'home', 'news', 'eyebrow', 'Latest news' ),
				'heading' => self::cget( $content, 'home', 'news', 'heading', 'From the clubhouse' ),
				'cards'   => array_map(
					static function ( array $i ): array {
						return array(
							'image'     => self::media_src( (string) ( $i['image'] ?? '' ) ),
							'image_alt' => (string) ( $i['image_alt'] ?? '' ),
							'tag'       => (string) ( $i['tag'] ?? '' ),
							'date'      => (string) ( $i['date'] ?? '' ),
							'title'     => (string) ( $i['title'] ?? '' ),
						);
					},
					$items
				),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'sponsors' ) ) {
			$out .= Blueworx_Clubhouse_Sections::sponsors( array(
				'eyebrow' => 'Our partners', 'heading' => 'Our sponsors & partners', 'link_label' => 'Become a sponsor',
				'link_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
				'names'   => array_map( static fn( array $s ): string => $s['name'], $collections->sponsors() ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'social' ) ) {
			$out .= Blueworx_Clubhouse_Sections::social( array(
				'heading'       => self::cget( $content, 'home', 'social', 'heading', 'Follow the club' ),
				'lede'          => self::cget( $content, 'home', 'social', 'lede', 'Match-day photos, results and behind-the-scenes — join us on socials.' ),
				'facebook_url'  => $branding->get_facebook_url(),
				'instagram_url' => $branding->get_instagram_url(),
				'linkedin_url'  => $branding->get_linkedin_url(),
			) );
		}
		// Contact / Find-us closes the page: address, hours and the map link belong at
		// the foot, nearest the footer, not mid-scroll between content sections.
		if ( $visibility->is_section_visible( 'home', 'info' ) ) {
			$default = array(
				array( 'label' => 'Location', 'lines' => array( '12 Riverside Lane', 'Marlow, SL7 1AA' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Opening hours', 'lines' => array( 'Mon–Sun', '7:00am – 10:00pm' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Contact', 'lines' => array( 'hello@clubhouse.example', '01628 000 000' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Find us', 'lines' => array(), 'link_label' => 'Open in Maps', 'link_href' => Blueworx_Clubhouse_Sections::maps_url( array( '12 Riverside Lane', 'Marlow, SL7 1AA' ) ) ),
			);
			$items = self::citems( $content, 'home', 'info', $default );
			$out .= Blueworx_Clubhouse_Sections::info_strip( array_map(
				static function ( array $i ): array {
					return array(
						'label'      => (string) ( $i['label'] ?? '' ),
						'lines'      => self::lines( $i['lines'] ?? array() ),
						'link_label' => (string) ( $i['link_label'] ?? '' ),
						'link_href'  => (string) ( $i['link_href'] ?? '' ),
					);
				},
				$items
			) );
		}
		$out .= '</main>';
		if ( $visibility->is_section_visible( 'home', 'footer' ) ) {
			$out .= self::shell_footer( $club, $visibility, $branding, $content );
		}
		return $out;
	}

	public static function about(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'about' ), $visibility, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'about', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => self::cget( $content, 'about', 'hero', 'eyebrow', 'About the club' ),
				'title_lead'         => self::cget( $content, 'about', 'hero', 'title_lead', 'Fifty-two years of ' ),
				'title_highlight'    => self::cget( $content, 'about', 'hero', 'title_highlight', 'community sport.' ),
				'lede'               => self::cget( $content, 'about', 'hero', 'lede', 'From one rugby pitch in 1974 to nine sports and twenty-four teams — ClubHouse has always been about more than the game.' ),
				'cta_primary'        => self::cget( $content, 'about', 'hero', 'cta_primary', Blueworx_Clubhouse_Cta::JOIN ),
				'cta_primary_href'   => self::cget( $content, 'about', 'hero', 'cta_primary_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
				'cta_secondary'      => self::cget( $content, 'about', 'hero', 'cta_secondary', 'Meet the committee' ),
				'cta_secondary_href' => self::cget( $content, 'about', 'hero', 'cta_secondary_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
				'image'              => self::media_src( (string) self::cget( $content, 'about', 'hero', 'image', '' ) ),
				'image_alt'          => 'ClubHouse members on the terrace',
				'image_caption'      => '',
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'history' ) ) {
			$out .= Blueworx_Clubhouse_Sections::timeline( array(
				'eyebrow'    => 'Our story',
				'heading'    => self::cget( $content, 'about', 'history', 'heading', 'From one pitch to nine sports' ),
				'milestones' => array_map(
					static function ( array $m ): array {
						return array(
							'year'  => (string) ( $m['year'] ?? '' ),
							'title' => (string) ( $m['title'] ?? '' ),
							'desc'  => (string) ( $m['desc'] ?? '' ),
						);
					},
					self::citems( $content, 'about', 'history', array(
						array( 'year' => '1974', 'title' => 'One pitch, one team', 'desc' => 'A handful of rugby players lease a field by the river.' ),
						array( 'year' => '1982', 'title' => 'Cricket joins', 'desc' => 'Summer cricket takes over the square; the first pavilion goes up.' ),
						array( 'year' => '1991', 'title' => 'Juniors take root', 'desc' => 'Minis and colts sections launch across rugby and cricket.' ),
						array( 'year' => '2003', 'title' => 'Courts & clubhouse', 'desc' => 'Four tennis courts and the current clubhouse open.' ),
						array( 'year' => '2015', 'title' => 'Nine sports', 'desc' => 'Hockey, netball and squash complete the multi-sport club.' ),
						array( 'year' => '2024', 'title' => 'A modern home', 'desc' => 'A full clubhouse refurbishment for the next generation.' ),
					) )
				),
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'values' ) ) {
			$out .= Blueworx_Clubhouse_Sections::benefit_grid( array(
				'eyebrow' => 'What we stand for',
				'heading' => 'Our values',
				'cards'   => self::citems( $content, 'about', 'values', array(
					array( 'title' => 'Everyone plays', 'description' => 'Beginners and county players train side by side, every age welcome.' ),
					array( 'title' => 'Volunteer-run', 'description' => 'Coaches, committee and bar staff give their time so the club thrives.' ),
					array( 'title' => 'Community first', 'description' => 'The clubhouse is a place to belong, on and off the pitch.' ),
					array( 'title' => 'Play for life', 'description' => 'Pathways from minis to vets — a home for the whole journey.' ),
				) ),
			) );
		}
		// Facilities — the tangible "what we've got" — moves up above the committee,
		// so it lands right after the club's values.
		if ( $visibility->is_section_visible( 'about', 'facilities' ) ) {
			$out .= Blueworx_Clubhouse_Sections::image_band( array(
				'eyebrow'   => self::cget( $content, 'about', 'facilities', 'eyebrow', 'The facilities' ),
				'heading'   => self::cget( $content, 'about', 'facilities', 'heading', 'Five pitches, four courts, one clubhouse' ),
				'image'     => self::media_src( (string) self::cget( $content, 'about', 'facilities', 'image', '' ) ), 'image_alt' => 'ClubHouse grounds from the air',
				'cta_label' => self::cget( $content, 'about', 'facilities', 'cta_label', 'Book a visit' ), 'cta_href' => self::cget( $content, 'about', 'facilities', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'committee' ) ) {
			$out .= Blueworx_Clubhouse_Sections::people_grid( array(
				'eyebrow' => 'Who runs the club',
				'heading' => 'The committee',
				'people'  => array_map(
					static function ( array $p ): array {
						return array( 'name' => $p['name'], 'role' => $p['committee_role'], 'email' => '' );
					},
					array_values( array_filter( $collections->people(), static fn( $p ) => '' !== $p['committee_role'] ) )
				),
			) );
		}
		// "Get involved" — non-playing ways to support the club, distinct from the
		// membership Join CTA that closes the page.
		if ( $visibility->is_section_visible( 'about', 'get_involved' ) ) {
			$out .= Blueworx_Clubhouse_Sections::benefit_grid( array(
				'eyebrow' => self::cget( $content, 'about', 'get_involved', 'eyebrow', 'Beyond the pitch' ),
				'heading' => self::cget( $content, 'about', 'get_involved', 'heading', 'Get involved' ),
				'cards'   => self::citems( $content, 'about', 'get_involved', array(
					array( 'title' => 'Volunteer', 'description' => 'Help on match days, run the bar, or join the committee — every hand counts.' ),
					array( 'title' => 'Coach & officiate', 'description' => 'Gain qualifications and give the next generation their start.' ),
					array( 'title' => 'Sponsor & partner', 'description' => 'Back a team or the clubhouse and reach the whole community.' ),
				) ),
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Membership',
				'heading'   => self::cget( $content, 'about', 'cta', 'heading', 'Want to be part of it?' ),
				'lede'      => self::cget( $content, 'about', 'cta', 'lede', 'Play, volunteer, or just come for the atmosphere.' ),
				'cta_label' => self::cget( $content, 'about', 'cta', 'cta_label', Blueworx_Clubhouse_Cta::JOIN . ' →' ),
				'cta_href'  => self::cget( $content, 'about', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'membership' ) ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function membership(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'membership' ), $visibility, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'membership', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => self::cget( $content, 'membership', 'hero', 'eyebrow', 'Membership' ),
				'title_lead'         => self::cget( $content, 'membership', 'hero', 'title_lead', 'Join in five minutes. ' ),
				'title_highlight'    => self::cget( $content, 'membership', 'hero', 'title_highlight', 'Play for years.' ),
				'lede'               => self::cget( $content, 'membership', 'hero', 'lede', 'From first-timers to county players, there is a category for you — every membership includes clubhouse access, discounted events and a free trial.' ),
				'cta_primary'        => self::cget( $content, 'membership', 'hero', 'cta_primary', 'Register interest' ),
				'cta_primary_href'   => self::cget( $content, 'membership', 'hero', 'cta_primary_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
				'cta_secondary'      => self::cget( $content, 'membership', 'hero', 'cta_secondary', 'Ask a question' ),
				'cta_secondary_href' => self::cget( $content, 'membership', 'hero', 'cta_secondary_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
				'image'              => self::media_src( (string) self::cget( $content, 'membership', 'hero', 'image', '' ) ),
				'image_alt'          => 'ClubHouse members warming up',
				'image_caption'      => '',
			) );
		}
		// Tiers sit above the fold — the pricing is the primary intent, so it comes
		// straight after the hero, before the supporting "Why join" benefits.
		if ( $visibility->is_section_visible( 'membership', 'tiers' ) ) {
			$out .= Blueworx_Clubhouse_Sections::tier_grid( self::membership_tiers( $content ) );
		}
		if ( $visibility->is_section_visible( 'membership', 'why' ) ) {
			$out .= Blueworx_Clubhouse_Sections::benefit_grid( array(
				'eyebrow' => self::cget( $content, 'membership', 'why', 'eyebrow', 'Why join' ),
				'heading' => self::cget( $content, 'membership', 'why', 'heading', 'More than a membership' ),
				'cards'   => self::citems( $content, 'membership', 'why', array(
					array( 'title' => 'All training included', 'description' => 'Access every session for your section, all season.' ),
					array( 'title' => 'Discounted events', 'description' => 'Members save on tournaments, socials and camps.' ),
					array( 'title' => 'Clubhouse & socials', 'description' => 'The bar, the terrace, and a calendar of member events.' ),
					array( 'title' => 'Kit discounts', 'description' => 'Save on team kit at our partner suppliers.' ),
				) ),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'detail' ) ) {
			$default = array_merge(
				array_map(
					static fn( string $t ): array => array( 'text' => $t, 'included' => true ),
					array( "Access to all your section's training", 'League match fees', 'Clubhouse & bar membership', 'Member events & socials' )
				),
				array_map(
					static fn( string $t ): array => array( 'text' => $t, 'included' => false ),
					array( 'Individual coaching (available separately)', 'Tournament entry fees', 'Club kit (discounted, not free)' )
				)
			);
			$items = self::citems( $content, 'membership', 'detail', $default );
			$out .= Blueworx_Clubhouse_Sections::list_split( array(
				'eyebrow'            => 'The detail',
				'heading'            => 'What is included',
				'included_label'     => 'Included',
				'not_included_label' => 'Not included',
				'policies_label'     => 'Good to know',
				'included'     => array_values( array_map(
					static fn( array $i ): string => (string) ( $i['text'] ?? '' ),
					array_filter( $items, static fn( array $i ): bool => (bool) ( $i['included'] ?? false ) )
				) ),
				'not_included' => array_values( array_map(
					static fn( array $i ): string => (string) ( $i['text'] ?? '' ),
					array_filter( $items, static fn( array $i ): bool => ! ( $i['included'] ?? false ) )
				) ),
				'policies'     => array(
					array( 'title' => 'Free trial', 'desc' => 'Your first session is on us — try before you join.' ),
					array( 'title' => 'Juniors', 'desc' => 'Under-18s pay a reduced rate; safeguarding applies to all youth sections.' ),
					array( 'title' => 'Family cap', 'desc' => 'Family membership covers up to five people at one address.' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'steps' ) ) {
			$default = array(
				array( 'number' => '01', 'title' => 'Pick your section', 'description' => 'Browse sports and find where you fit.' ),
				array( 'number' => '02', 'title' => 'Choose a tier', 'description' => 'Adult, family, junior or social.' ),
				array( 'number' => '03', 'title' => 'Register interest', 'description' => 'Fill in a short form — no payment yet.' ),
				array( 'number' => '04', 'title' => 'Come and play', 'description' => 'We will match you to a coach and a session.' ),
			);
			$items = array_values( self::citems( $content, 'membership', 'steps', $default ) );
			$out .= Blueworx_Clubhouse_Sections::step_grid( array(
				'eyebrow' => 'How to join',
				'heading' => 'Four steps to playing',
				'steps'   => array_map(
					static function ( array $s, int $i ): array {
						return array(
							'number'      => sprintf( '%02d', $i + 1 ),
							'title'       => (string) ( $s['title'] ?? '' ),
							'description' => (string) ( $s['description'] ?? '' ),
						);
					},
					$items,
					array_keys( $items )
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'faq' ) ) {
			$default = array(
				array( 'question' => 'Do I have to commit for a season?', 'answer' => 'No — you can join any time and pay monthly.', 'open' => true ),
				array( 'question' => 'Can I try before I join?', 'answer' => 'Yes, your first session is a free trial.', 'open' => false ),
				array( 'question' => 'Do you have junior sections?', 'answer' => 'Every sport runs junior pathways from age 5 upward.', 'open' => false ),
				array( 'question' => 'Is there a family rate?', 'answer' => 'Family membership covers up to five people at one address.', 'open' => false ),
				array( 'question' => 'How do I pay?', 'answer' => 'Payment details are arranged once your interest is confirmed.', 'open' => false ),
			);
			$items = self::citems( $content, 'membership', 'faq', $default );
			$out .= Blueworx_Clubhouse_Sections::faq( array(
				'eyebrow' => 'Questions',
				'heading' => 'Frequently asked',
				'items'   => array_map(
					static function ( array $i ): array {
						return array(
							'question' => (string) ( $i['question'] ?? '' ),
							'answer'   => (string) ( $i['answer'] ?? '' ),
							'open'     => (bool) ( $i['open'] ?? false ),
						);
					},
					$items
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Ready?',
				'heading'   => self::cget( $content, 'membership', 'cta', 'heading', 'Register your interest' ),
				'lede'      => self::cget( $content, 'membership', 'cta', 'lede', 'Tell us a little about you and we will be in touch within a few days.' ),
				'cta_label' => self::cget( $content, 'membership', 'cta', 'cta_label', 'Register interest →' ),
				'cta_href'  => self::cget( $content, 'membership', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function contact(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'contact' ), $visibility, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'contact', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => self::cget( $content, 'contact', 'hero', 'eyebrow', 'Contact' ),
				'title_lead'         => self::cget( $content, 'contact', 'hero', 'title_lead', 'We will point you to ' ),
				'title_highlight'    => self::cget( $content, 'contact', 'hero', 'title_highlight', 'the right person.' ),
				'lede'               => self::cget( $content, 'contact', 'hero', 'lede', 'Questions about joining, playing, or hiring the clubhouse? Start here.' ),
				'cta_primary'        => self::cget( $content, 'contact', 'hero', 'cta_primary', 'Email the club' ),
				'cta_primary_href'   => self::cget( $content, 'contact', 'hero', 'cta_primary_href', 'mailto:hello@clubhouse.example' ),
				'cta_secondary'      => self::cget( $content, 'contact', 'hero', 'cta_secondary', 'Call 01628 000 000' ),
				'cta_secondary_href' => self::cget( $content, 'contact', 'hero', 'cta_secondary_href', 'tel:01628000000' ),
				'image'              => self::media_src( (string) self::cget( $content, 'contact', 'hero', 'image', '' ) ), 'image_alt' => '', 'image_caption' => '',
			) );
		}
		if ( $visibility->is_section_visible( 'contact', 'form' ) ) {
			$out .= Blueworx_Clubhouse_Sections::contact_form( array(
				'eyebrow'         => self::cget( $content, 'contact', 'form', 'eyebrow', 'Get in touch' ),
				'heading'         => self::cget( $content, 'contact', 'form', 'heading', 'Send us a message' ),
				'name_label'      => 'Full name',
				'email_label'     => 'Email',
				'enquiry_label'   => 'Enquiry type',
				'enquiry_options' => array( 'General enquiry', 'Membership', 'Coaching', 'Venue hire', 'Volunteering', 'Something else' ),
				'message_label'   => 'Message',
				'submit_label'    => self::cget( $content, 'contact', 'form', 'submit_label', 'Send message' ),
				'info'            => array(
					'heading' => 'Find us',
					'address' => array( '12 Riverside Lane', 'Marlow, SL7 1AA' ),
					'email'   => 'hello@clubhouse.example',
					'phone'   => '01628 000 000',
					'socials' => array(
						'Facebook'  => $branding->get_facebook_url(),
						'Instagram' => $branding->get_instagram_url(),
						'LinkedIn'  => $branding->get_linkedin_url(),
					),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'contact', 'directory' ) ) {
			$out .= Blueworx_Clubhouse_Sections::people_grid( array(
				'eyebrow' => 'Who to contact',
				'heading' => 'The directory',
				'people'  => array_map(
					static function ( array $p ): array {
						return array( 'name' => $p['name'], 'role' => $p['directory_role'], 'email' => $p['email'] );
					},
					array_values( array_filter( $collections->people(), static fn( $p ) => '' !== $p['directory_role'] ) )
				),
			) );
		}
		if ( $visibility->is_section_visible( 'contact', 'social' ) ) {
			$out .= Blueworx_Clubhouse_Sections::social( array(
				'heading'       => self::cget( $content, 'contact', 'social', 'heading', 'Stay connected' ),
				'lede'          => 'Follow the club for match-day updates, results and event announcements.',
				'facebook_url'  => $branding->get_facebook_url(),
				'instagram_url' => $branding->get_instagram_url(),
				'linkedin_url'  => $branding->get_linkedin_url(),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function login(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'login' ), $visibility, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'login', 'form' ) ) {
			$out .= Blueworx_Clubhouse_Sections::auth( array(
				'eyebrow'        => 'Members',
				'heading'        => self::cget( $content, 'login', 'form', 'heading', 'Log in to your account' ),
				'lede'           => self::cget( $content, 'login', 'form', 'lede', 'Access your membership, bookings and club events.' ),
				'email_label'    => 'Email',
				'password_label' => 'Password',
				'remember_label' => 'Remember me',
				'forgot_label'   => 'Forgot password?',
				'forgot_href'    => '',
				'submit_label'   => 'Log in',
				'join_prompt'    => 'Not a member yet?',
				'join_label'     => Blueworx_Clubhouse_Cta::JOIN,
				'join_href'      => Blueworx_Clubhouse_Links::url( 'membership' ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function sports(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'sports' ), $visibility, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'sports', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => self::cget( $content, 'sports', 'hero', 'eyebrow', 'Our sports' ),
				'title_lead'      => self::cget( $content, 'sports', 'hero', 'title_lead', 'Nine sports, ' ),
				'title_highlight' => self::cget( $content, 'sports', 'hero', 'title_highlight', 'one club.' ),
				'lede'            => self::cget( $content, 'sports', 'hero', 'lede', 'From first session to first team — find your section and get playing.' ),
				'filter_label'    => 'Filter by sport',
				'filters'         => array(
					array( 'label' => 'All', 'href' => Blueworx_Clubhouse_Links::url( 'sports' ), 'active' => true ),
					array( 'label' => 'Rugby', 'href' => Blueworx_Clubhouse_Links::url( 'sports' ), 'active' => false ),
					array( 'label' => 'Cricket', 'href' => Blueworx_Clubhouse_Links::url( 'sports' ), 'active' => false ),
					array( 'label' => 'Tennis', 'href' => Blueworx_Clubhouse_Links::url( 'sports' ), 'active' => false ),
					array( 'label' => 'Football', 'href' => Blueworx_Clubhouse_Links::url( 'sports' ), 'active' => false ),
					array( 'label' => 'Hockey', 'href' => Blueworx_Clubhouse_Links::url( 'sports' ), 'active' => false ),
					array( 'label' => 'Netball', 'href' => Blueworx_Clubhouse_Links::url( 'sports' ), 'active' => false ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'sports', 'directory' ) ) {
			$out .= Blueworx_Clubhouse_Sections::stat_card_grid( array(
				'eyebrow'    => 'All sections',
				'heading'    => 'Pick your sport.',
				'link_label' => Blueworx_Clubhouse_Cta::JOIN . ' →',
				'link_href'  => Blueworx_Clubhouse_Links::url( 'membership' ),
				'cards'      => array_map(
					static function ( array $s ): array {
						return array(
							'image'       => $s['image'],
							'image_alt'   => $s['title'],
							'chip'        => $s['label'],
							'title'       => $s['title'],
							'description' => $s['description'],
							'stats'       => array(
								array( 'value' => $s['stat1_value'], 'label' => $s['stat1_label'] ),
								array( 'value' => $s['stat2_value'], 'label' => $s['stat2_label'] ),
							),
						);
					},
					$collections->sports()
				),
			) );
		}
		if ( $visibility->is_section_visible( 'sports', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'New to the club?',
				'heading'   => self::cget( $content, 'sports', 'cta', 'heading', 'Try any sport with a free session' ),
				'lede'      => self::cget( $content, 'sports', 'cta', 'lede', 'Not sure which section fits? Come down and try before you join.' ),
				'cta_label' => self::cget( $content, 'sports', 'cta', 'cta_label', 'Register interest →' ),
				'cta_href'  => self::cget( $content, 'sports', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function teams(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'teams' ), $visibility, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'teams', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => self::cget( $content, 'teams', 'hero', 'eyebrow', 'Our teams' ),
				'title_lead'      => self::cget( $content, 'teams', 'hero', 'title_lead', 'Twenty-four teams, ' ),
				'title_highlight' => self::cget( $content, 'teams', 'hero', 'title_highlight', 'every level.' ),
				'lede'            => self::cget( $content, 'teams', 'hero', 'lede', 'League sides, development squads and junior pathways across all nine sports.' ),
				'filter_label'    => 'Filter teams by sport',
				'filters'         => array(
					array( 'label' => 'All', 'href' => Blueworx_Clubhouse_Links::url( 'teams' ), 'active' => true ),
					array( 'label' => 'Rugby', 'href' => Blueworx_Clubhouse_Links::url( 'teams' ), 'active' => false ),
					array( 'label' => 'Cricket', 'href' => Blueworx_Clubhouse_Links::url( 'teams' ), 'active' => false ),
					array( 'label' => 'Hockey', 'href' => Blueworx_Clubhouse_Links::url( 'teams' ), 'active' => false ),
					array( 'label' => 'Netball', 'href' => Blueworx_Clubhouse_Links::url( 'teams' ), 'active' => false ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'teams', 'directory' ) ) {
			$out .= Blueworx_Clubhouse_Sections::stat_card_grid( array(
				'eyebrow'    => 'Squads',
				'heading'    => 'Find your team.',
				'link_label' => '',
				'link_href'  => '',
				'cards'      => array_map(
					static function ( array $t ): array {
						return array(
							'image'       => $t['image'],
							'image_alt'   => $t['sport'] . ' ' . $t['title'],
							'chip'        => $t['sport'],
							'title'       => $t['title'],
							'description' => $t['description'],
							'stats'       => array(
								array( 'value' => $t['match_day'], 'label' => 'Match day' ),
								array( 'value' => $t['league'], 'label' => 'League' ),
							),
						);
					},
					$collections->teams()
				),
			) );
		}
		if ( $visibility->is_section_visible( 'teams', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Want to play?',
				'heading'   => self::cget( $content, 'teams', 'cta', 'heading', 'Trials run all season' ),
				'lede'      => self::cget( $content, 'teams', 'cta', 'lede', 'Every squad welcomes new players — get in touch and we will match you to a session.' ),
				'cta_label' => self::cget( $content, 'teams', 'cta', 'cta_label', 'Get in touch →' ),
				'cta_href'  => self::cget( $content, 'teams', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function events(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'events' ), $visibility, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'events', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => self::cget( $content, 'events', 'hero', 'eyebrow', "What's on" ),
				'title_lead'      => self::cget( $content, 'events', 'hero', 'title_lead', 'Socials, camps and ' ),
				'title_highlight' => self::cget( $content, 'events', 'hero', 'title_highlight', 'open days.' ),
				'lede'            => self::cget( $content, 'events', 'hero', 'lede', "There's always something happening at the club — on the pitch and off it." ),
				'filter_label'    => 'Filter events by type',
				'filters'         => array(
					array( 'label' => 'All', 'href' => Blueworx_Clubhouse_Links::url( 'events' ), 'active' => true ),
					array( 'label' => 'Social', 'href' => Blueworx_Clubhouse_Links::url( 'events' ), 'active' => false ),
					array( 'label' => 'Junior', 'href' => Blueworx_Clubhouse_Links::url( 'events' ), 'active' => false ),
					array( 'label' => 'Tournament', 'href' => Blueworx_Clubhouse_Links::url( 'events' ), 'active' => false ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'events', 'upcoming' ) ) {
			$upcoming = array_values( array_filter( $collections->events(), static fn( $e ) => 'upcoming' === $e['status'] ) );
			$out .= Blueworx_Clubhouse_Sections::event_grid( array(
				'eyebrow' => 'Coming up',
				'heading' => 'Upcoming events',
				'cards'   => array_map(
					static function ( array $e ): array {
						return array(
							'tag'       => $e['tag'],
							'date'      => $e['date'],
							'title'     => $e['title'],
							'detail'    => $e['detail'],
							'cta_label' => $e['cta_label'],
							'cta_href'  => $e['cta_href'],
						);
					},
					$upcoming
				),
			) );
		}
		if ( $visibility->is_section_visible( 'events', 'past' ) ) {
			$past = array_values( array_filter( $collections->events(), static fn( $e ) => 'past' === $e['status'] ) );
			$out .= Blueworx_Clubhouse_Sections::event_archive( array(
				'heading' => 'Recently at the club',
				'rows'    => array_map(
					static function ( array $e ): array {
						return array( 'date' => $e['date'], 'tag' => $e['tag'], 'title' => $e['title'] );
					},
					$past
				),
			) );
		}
		if ( $visibility->is_section_visible( 'events', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Hosting something?',
				'heading'   => self::cget( $content, 'events', 'cta', 'heading', 'Hire the clubhouse' ),
				'lede'      => self::cget( $content, 'events', 'cta', 'lede', 'Function room and bar available for members and the community.' ),
				'cta_label' => self::cget( $content, 'events', 'cta', 'cta_label', 'Enquire about hire →' ),
				'cta_href'  => self::cget( $content, 'events', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}

	public static function calendar(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'calendar' ), $visibility, $logo_url, $content ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'calendar', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => self::cget( $content, 'calendar', 'hero', 'eyebrow', 'Fixtures & results' ),
				'title_lead'      => self::cget( $content, 'calendar', 'hero', 'title_lead', 'Every game, ' ),
				'title_highlight' => self::cget( $content, 'calendar', 'hero', 'title_highlight', 'all season.' ),
				'lede'            => self::cget( $content, 'calendar', 'hero', 'lede', 'Match days across all nine sports, with results as they come in.' ),
				'filter_label'    => 'Filter fixtures by sport',
				'filters'         => array(
					array( 'label' => 'All', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ), 'active' => true ),
					array( 'label' => 'Rugby', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ), 'active' => false ),
					array( 'label' => 'Cricket', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ), 'active' => false ),
					array( 'label' => 'Hockey', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ), 'active' => false ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'calendar', 'schedule' ) ) {
			$out .= Blueworx_Clubhouse_Sections::calendar_months( array(
				'eyebrow' => self::cget( $content, 'calendar', 'schedule', 'eyebrow', 'The schedule' ),
				'heading' => self::cget( $content, 'calendar', 'schedule', 'heading', 'Fixtures & results' ),
				'months'  => Blueworx_Clubhouse_Fixture_Projection::calendar_months( $collections->fixtures() ),
			) );
		}
		if ( $visibility->is_section_visible( 'calendar', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Follow the club',
				'heading'   => self::cget( $content, 'calendar', 'cta', 'heading', 'Never miss a result' ),
				'lede'      => self::cget( $content, 'calendar', 'cta', 'lede', 'Fixtures, results and club news — one email a month.' ),
				'cta_label' => self::cget( $content, 'calendar', 'cta', 'cta_label', 'Join the mailing list →' ),
				'cta_href'  => self::cget( $content, 'calendar', 'cta', 'cta_href', Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club, $visibility, $branding, $content );
		return $out;
	}
}
