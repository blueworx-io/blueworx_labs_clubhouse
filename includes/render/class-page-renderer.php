<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles a full HTML document for a Base Look + branding: <head> carries the
 * Google-fonts link, the look stylesheet, and the derived :root variables; <body>
 * is a string of rendered sections. home() composes the demo Home shell, honouring
 * per-section visibility. The same output is what WordPress template_include will
 * later echo — the preview is just an earlier caller.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Renderer {

	public static function google_fonts_url( Blueworx_Clubhouse_Base_Look $look ): string {
		$families = array();
		foreach ( $look->fonts() as $font ) {
			$families[] = 'family=' . rawurlencode( $font['family'] )
				. ':wght@' . implode( ';', $font['weights'] );
		}
		return 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
	}

	public static function document(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding,
		string $body,
		string $plugin_url = ''
	): string {
		$vars = Blueworx_Clubhouse_Theme_Css::compose( $look, $branding );
		$css  = Blueworx_Clubhouse_Theme_Css::to_css( $vars );
		$font = htmlspecialchars( self::google_fonts_url( $look ), ENT_QUOTES, 'UTF-8' );
		$sheet = htmlspecialchars( $plugin_url . $look->stylesheet(), ENT_QUOTES, 'UTF-8' );

		return '<!doctype html><html lang="en"><head>'
			. '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . htmlspecialchars( $branding->get_club_name(), ENT_QUOTES, 'UTF-8' ) . '</title>'
			. '<link rel="preconnect" href="https://fonts.googleapis.com">'
			. '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
			. '<link rel="stylesheet" href="' . $font . '">'
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

	private static function shell_header( string $club, string $active ): string {
		return Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => $club,
			'banner'      => 'Summer sign-ups are open — register your interest for 2026/27 →',
			'banner_href' => Blueworx_Clubhouse_Links::url( 'membership' ),
			'nav'         => array(
				array( 'label' => 'Home', 'href' => Blueworx_Clubhouse_Links::url( 'home' ) ),
				array( 'label' => 'About', 'href' => Blueworx_Clubhouse_Links::url( 'about' ) ),
				array( 'label' => 'Sports', 'href' => Blueworx_Clubhouse_Links::url( 'sports' ) ),
				array( 'label' => 'Teams', 'href' => Blueworx_Clubhouse_Links::url( 'teams' ) ),
				array( 'label' => 'Membership', 'href' => Blueworx_Clubhouse_Links::url( 'membership' ) ),
				array( 'label' => 'Events', 'href' => Blueworx_Clubhouse_Links::url( 'events' ) ),
				array( 'label' => 'Calendar', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ) ),
				array( 'label' => 'Contact', 'href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
			),
			'active'      => $active,
			'login'       => 'Log in',
			'login_href'  => Blueworx_Clubhouse_Links::url( 'login' ),
			'join'        => 'Join the Club',
			'join_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
		) );
	}

	private static function shell_footer( string $club ): string {
		return Blueworx_Clubhouse_Sections::footer( array(
			'club_name'  => $club,
			'tagline'    => 'Nine sports, one club. A home ground for every team, and everyone who follows them.',
			'socials'    => array( 'Facebook', 'Instagram', 'Community', 'Share' ),
			'columns'    => array(
				array( 'title' => 'Club', 'links' => array(
					array( 'label' => 'About', 'href' => Blueworx_Clubhouse_Links::url( 'about' ) ),
					array( 'label' => 'Sports', 'href' => Blueworx_Clubhouse_Links::url( 'sports' ) ),
					array( 'label' => 'Teams', 'href' => Blueworx_Clubhouse_Links::url( 'teams' ) ),
					array( 'label' => 'Events', 'href' => Blueworx_Clubhouse_Links::url( 'events' ) ),
				) ),
				array( 'title' => 'Get involved', 'links' => array(
					array( 'label' => 'Membership', 'href' => Blueworx_Clubhouse_Links::url( 'membership' ) ),
					array( 'label' => 'Calendar', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ) ),
					array( 'label' => 'Volunteer', 'href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
					array( 'label' => 'Contact', 'href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
				) ),
			),
			'newsletter' => array(
				'heading'     => 'Stay in the loop',
				'lede'        => 'Fixtures, results and club news — one email a month.',
				'placeholder' => 'Your email',
				'cta'         => 'Subscribe',
			),
			'legal'      => array(
				array( 'label' => 'Privacy Policy', 'href' => '#' ),
				array( 'label' => 'Terms', 'href' => '#' ),
				array( 'label' => 'Club Rules', 'href' => '#' ),
				array( 'label' => 'Safeguarding', 'href' => '#' ),
			),
		) );
	}

	public static function home(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections
	): string {
		$club = $branding->get_club_name();
		$out  = '';

		if ( $visibility->is_section_visible( 'home', 'header' ) ) {
			$out .= self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'home' ) );
		}
		$out .= '<main class="ch-main" id="ch-main" tabindex="-1">';
		if ( $visibility->is_section_visible( 'home', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'Est. 1974 · Marlow, UK',
				'title_lead'         => 'Every sport. Every age. ',
				'title_highlight'    => 'One community.',
				'lede'               => "Nine sports, twenty-four teams, and a clubhouse that's always open. Come for the game — stay for the people.",
				'cta_primary'        => 'Explore membership',
				'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
				'cta_secondary'      => 'Take a tour →',
				'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'about' ),
				'image'              => '',
				'image_alt'          => 'ClubHouse floodlit pitch on a Saturday',
				'image_caption'      => 'Saturday, floodlights on',
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'quick_tiles' ) ) {
			// Task-oriented shortcuts (verb-first), deliberately not a mirror of the nav.
			$out .= Blueworx_Clubhouse_Sections::quick_tiles( array(
				array( 'label' => 'Join the club', 'href' => Blueworx_Clubhouse_Links::url( 'membership' ) ),
				array( 'label' => 'Take a tour', 'href' => Blueworx_Clubhouse_Links::url( 'about' ) ),
				array( 'label' => 'See fixtures', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ) ),
				array( 'label' => 'Get in touch', 'href' => Blueworx_Clubhouse_Links::url( 'contact' ) ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'ticker' ) ) {
			$out .= Blueworx_Clubhouse_Sections::ticker( array(
				'1st XV promoted to Div 3 South',
				'Open Day — Sat 26 Jul, 10:00–14:00',
				'Clubhouse refurbishment complete',
				'Summer Football Camp · 4–8 Aug',
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'stats' ) ) {
			$out .= Blueworx_Clubhouse_Sections::stat_strip( array(
				array( 'value' => '900+', 'label' => 'Members', 'featured' => true ),
				array( 'value' => '9', 'label' => 'Sports' ),
				array( 'value' => '24', 'label' => 'Teams' ),
				array( 'value' => '1974', 'label' => 'Founded' ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'sports' ) ) {
			$sports = array_slice( $collections->sports(), 0, 4 );
			$out .= Blueworx_Clubhouse_Sections::card_grid( array(
				'eyebrow'    => 'Our sports',
				'heading'    => 'Pick your game.',
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
				'eyebrow'   => 'The clubhouse',
				'heading'   => 'A home ground for every team, and everyone who follows them',
				'image'     => '', 'image_alt' => 'ClubHouse pavilion at dusk',
				'cta_label' => 'Visit us', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'membership' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'accent',
				'eyebrow'   => 'Membership',
				'heading'   => 'Open to everyone, from £28/month.',
				'lede'      => 'From first-timers to county players — every tier includes clubhouse access, discounted events and a free trial session.',
				'cta_label' => 'Choose your tier →',
				'cta_href'  => Blueworx_Clubhouse_Links::url( 'membership' ),
			) );
			$out .= Blueworx_Clubhouse_Sections::tier_grid( array(
				array( 'eyebrow' => 'Full playing', 'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
					'features' => array( 'Any section, any level', 'League affiliation', 'Clubhouse & socials' ),
					'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'membership' ) ),
				array( 'eyebrow' => 'Best value', 'name' => 'Family', 'price' => '£45', 'period' => '/mo',
					'features' => array( 'Up to 5 members', 'Any sections', 'Priority event booking' ),
					'recommended' => true, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'membership' ) ),
				array( 'eyebrow' => 'Off the pitch', 'name' => 'Social', 'price' => '£12', 'period' => '/mo',
					'features' => array( 'Full clubhouse access', 'Member events', 'Support your club' ),
					'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'membership' ) ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'activity' ) ) {
			$out .= Blueworx_Clubhouse_Sections::activity_tabs( array(
				'eyebrow'  => 'Club activity',
				'heading'  => "What\u{2019}s happening",
				'fixtures' => Blueworx_Clubhouse_Fixture_Projection::home_fixtures( $collections->fixtures() ),
				'results'  => Blueworx_Clubhouse_Fixture_Projection::home_results( $collections->fixtures() ),
				'events'   => array_map(
					static function ( array $e ): array {
						return array( 'tag' => $e['tag'], 'date' => $e['date'], 'title' => $e['title'], 'detail' => $e['detail'] );
					},
					array_slice( array_values( array_filter( $collections->events(), static fn( $e ) => 'upcoming' === $e['status'] ) ), 0, 3 )
				),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'news' ) ) {
			$out .= Blueworx_Clubhouse_Sections::news_cards( array(
				'eyebrow' => 'Latest news',
				'heading' => 'From the clubhouse',
				'cards'   => array(
					array( 'image' => '', 'image_alt' => 'Clubhouse interior', 'tag' => 'Club news', 'date' => '2 Jul', 'title' => 'Clubhouse refurbishment complete' ),
					array( 'image' => '', 'image_alt' => 'Junior footballers', 'tag' => 'Sections', 'date' => '28 Jun', 'title' => 'Junior Football signs 40 new players' ),
					array( 'image' => '', 'image_alt' => 'Volunteers', 'tag' => 'Volunteering', 'date' => '24 Jun', 'title' => 'Volunteers needed for the Open Day' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'info' ) ) {
			$out .= Blueworx_Clubhouse_Sections::info_strip( array(
				array( 'label' => 'Location', 'lines' => array( '12 Riverside Lane', 'Marlow, SL7 1AA' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Opening hours', 'lines' => array( 'Mon–Sun', '7:00am – 10:00pm' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Contact', 'lines' => array( 'hello@clubhouse.example', '01628 000 000' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Find us', 'lines' => array(), 'link_label' => 'Open in Maps', 'link_href' => '#' ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'sponsors' ) ) {
			$out .= Blueworx_Clubhouse_Sections::sponsors( array(
				'heading' => 'Our sponsors & partners', 'link_label' => 'Become a sponsor', 'link_href' => '#',
				'names'   => array_map( static fn( array $s ): string => $s['name'], $collections->sponsors() ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'social' ) ) {
			$out .= Blueworx_Clubhouse_Sections::social( array(
				'heading'       => 'Follow the club',
				'lede'          => 'Match-day photos, results and behind-the-scenes — join us on socials.',
				'facebook_url'  => $branding->get_facebook_url(),
				'instagram_url' => $branding->get_instagram_url(),
			) );
		}
		$out .= '</main>';
		if ( $visibility->is_section_visible( 'home', 'footer' ) ) {
			$out .= self::shell_footer( $club );
		}
		return $out;
	}

	public static function about(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'about' ) ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'about', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'About the club',
				'title_lead'         => 'Fifty-two years of ',
				'title_highlight'    => 'community sport.',
				'lede'               => 'From one rugby pitch in 1974 to nine sports and twenty-four teams — ClubHouse has always been about more than the game.',
				'cta_primary'        => 'Join the club',
				'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
				'cta_secondary'      => 'Meet the committee',
				'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
				'image'              => '',
				'image_alt'          => 'ClubHouse members on the terrace',
				'image_caption'      => '',
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'history' ) ) {
			$out .= Blueworx_Clubhouse_Sections::timeline( array(
				'eyebrow'    => 'Our story',
				'heading'    => 'From one pitch to nine sports',
				'milestones' => array(
					array( 'year' => '1974', 'title' => 'One pitch, one team', 'desc' => 'A handful of rugby players lease a field by the river.' ),
					array( 'year' => '1982', 'title' => 'Cricket joins', 'desc' => 'Summer cricket takes over the square; the first pavilion goes up.' ),
					array( 'year' => '1991', 'title' => 'Juniors take root', 'desc' => 'Minis and colts sections launch across rugby and cricket.' ),
					array( 'year' => '2003', 'title' => 'Courts & clubhouse', 'desc' => 'Four tennis courts and the current clubhouse open.' ),
					array( 'year' => '2015', 'title' => 'Nine sports', 'desc' => 'Hockey, netball and squash complete the multi-sport club.' ),
					array( 'year' => '2024', 'title' => 'A modern home', 'desc' => 'A full clubhouse refurbishment for the next generation.' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'values' ) ) {
			$out .= Blueworx_Clubhouse_Sections::benefit_grid( array(
				'eyebrow' => 'What we stand for',
				'heading' => 'Our values',
				'cards'   => array(
					array( 'title' => 'Everyone plays', 'description' => 'Beginners and county players train side by side, every age welcome.' ),
					array( 'title' => 'Volunteer-run', 'description' => 'Coaches, committee and bar staff give their time so the club thrives.' ),
					array( 'title' => 'Community first', 'description' => 'The clubhouse is a place to belong, on and off the pitch.' ),
					array( 'title' => 'Play for life', 'description' => 'Pathways from minis to vets — a home for the whole journey.' ),
				),
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
		if ( $visibility->is_section_visible( 'about', 'facilities' ) ) {
			$out .= Blueworx_Clubhouse_Sections::image_band( array(
				'eyebrow'   => 'The facilities',
				'heading'   => 'Five pitches, four courts, one clubhouse',
				'image'     => '', 'image_alt' => 'ClubHouse grounds from the air',
				'cta_label' => 'Book a visit', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Get involved',
				'heading'   => 'Want to be part of it?',
				'lede'      => 'Play, volunteer, or just come for the atmosphere.',
				'cta_label' => 'Join the club →',
				'cta_href'  => Blueworx_Clubhouse_Links::url( 'membership' ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}

	public static function membership(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'membership' ) ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'membership', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'Membership',
				'title_lead'         => 'Join in five minutes. ',
				'title_highlight'    => 'Play for years.',
				'lede'               => 'From first-timers to county players, there is a category for you — every membership includes clubhouse access, discounted events and a free trial.',
				'cta_primary'        => 'Register interest',
				'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'contact' ),
				'cta_secondary'      => 'Ask a question',
				'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
				'image'              => '',
				'image_alt'          => 'ClubHouse members warming up',
				'image_caption'      => '',
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'why' ) ) {
			$out .= Blueworx_Clubhouse_Sections::benefit_grid( array(
				'eyebrow' => 'Why join',
				'heading' => 'More than a membership',
				'cards'   => array(
					array( 'title' => 'All training included', 'description' => 'Access every session for your section, all season.' ),
					array( 'title' => 'Discounted events', 'description' => 'Members save on tournaments, socials and camps.' ),
					array( 'title' => 'Clubhouse & socials', 'description' => 'The bar, the terrace, and a calendar of member events.' ),
					array( 'title' => 'Kit discounts', 'description' => 'Save on team kit at our partner suppliers.' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'tiers' ) ) {
			$out .= Blueworx_Clubhouse_Sections::tier_grid( array(
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
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'detail' ) ) {
			$out .= Blueworx_Clubhouse_Sections::list_split( array(
				'eyebrow'            => 'The detail',
				'heading'            => 'What is included',
				'included_label'     => 'Included',
				'not_included_label' => 'Not included',
				'policies_label'     => 'Good to know',
				'included'     => array( "Access to all your section's training", 'League match fees', 'Clubhouse & bar membership', 'Member events & socials' ),
				'not_included' => array( 'Individual coaching (available separately)', 'Tournament entry fees', 'Club kit (discounted, not free)' ),
				'policies'     => array(
					array( 'title' => 'Free trial', 'desc' => 'Your first session is on us — try before you join.' ),
					array( 'title' => 'Juniors', 'desc' => 'Under-18s pay a reduced rate; safeguarding applies to all youth sections.' ),
					array( 'title' => 'Family cap', 'desc' => 'Family membership covers up to five people at one address.' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'steps' ) ) {
			$out .= Blueworx_Clubhouse_Sections::step_grid( array(
				'eyebrow' => 'How to join',
				'heading' => 'Four steps to playing',
				'steps'   => array(
					array( 'number' => '01', 'title' => 'Pick your section', 'description' => 'Browse sports and find where you fit.' ),
					array( 'number' => '02', 'title' => 'Choose a tier', 'description' => 'Adult, family, junior or social.' ),
					array( 'number' => '03', 'title' => 'Register interest', 'description' => 'Fill in a short form — no payment yet.' ),
					array( 'number' => '04', 'title' => 'Come and play', 'description' => 'We will match you to a coach and a session.' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'faq' ) ) {
			$out .= Blueworx_Clubhouse_Sections::faq( array(
				'eyebrow' => 'Questions',
				'heading' => 'Frequently asked',
				'items'   => array(
					array( 'question' => 'Do I have to commit for a season?', 'answer' => 'No — you can join any time and pay monthly.', 'open' => true ),
					array( 'question' => 'Can I try before I join?', 'answer' => 'Yes, your first session is a free trial.', 'open' => false ),
					array( 'question' => 'Do you have junior sections?', 'answer' => 'Every sport runs junior pathways from age 5 upward.', 'open' => false ),
					array( 'question' => 'Is there a family rate?', 'answer' => 'Family membership covers up to five people at one address.', 'open' => false ),
					array( 'question' => 'How do I pay?', 'answer' => 'Payment details are arranged once your interest is confirmed.', 'open' => false ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Ready?',
				'heading'   => 'Register your interest',
				'lede'      => 'Tell us a little about you and we will be in touch within a few days.',
				'cta_label' => 'Register interest →',
				'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}

	public static function contact(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'contact' ) ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'contact', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'Contact',
				'title_lead'         => 'We will point you to ',
				'title_highlight'    => 'the right person.',
				'lede'               => 'Questions about joining, playing, or hiring the clubhouse? Start here.',
				'cta_primary'        => 'Email the club',
				'cta_primary_href'   => 'mailto:hello@clubhouse.example',
				'cta_secondary'      => 'Call 01628 000 000',
				'cta_secondary_href' => 'tel:01628000000',
				'image'              => '', 'image_alt' => '', 'image_caption' => '',
			) );
		}
		if ( $visibility->is_section_visible( 'contact', 'form' ) ) {
			$out .= Blueworx_Clubhouse_Sections::contact_form( array(
				'eyebrow'         => 'Get in touch',
				'heading'         => 'Send us a message',
				'name_label'      => 'Full name',
				'email_label'     => 'Email',
				'enquiry_label'   => 'Enquiry type',
				'enquiry_options' => array( 'General enquiry', 'Membership', 'Coaching', 'Venue hire', 'Volunteering', 'Something else' ),
				'message_label'   => 'Message',
				'submit_label'    => 'Send message',
				'info'            => array(
					'heading' => 'Find us',
					'address' => array( '12 Riverside Lane', 'Marlow, SL7 1AA' ),
					'email'   => 'hello@clubhouse.example',
					'phone'   => '01628 000 000',
					'socials' => array( 'Facebook', 'Instagram', 'Community', 'Share' ),
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
				'heading'       => 'Stay connected',
				'lede'          => 'Follow the club for match-day updates, results and event announcements.',
				'facebook_url'  => $branding->get_facebook_url(),
				'instagram_url' => $branding->get_instagram_url(),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}

	public static function login(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'login' ) ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'login', 'form' ) ) {
			$out .= Blueworx_Clubhouse_Sections::auth( array(
				'eyebrow'        => 'Members',
				'heading'        => 'Log in to your account',
				'lede'           => 'Access your membership, bookings and club events.',
				'email_label'    => 'Email',
				'password_label' => 'Password',
				'remember_label' => 'Remember me',
				'forgot_label'   => 'Forgot password?',
				'forgot_href'    => '#',
				'submit_label'   => 'Log in',
				'join_prompt'    => 'Not a member yet?',
				'join_label'     => 'Join the club',
				'join_href'      => Blueworx_Clubhouse_Links::url( 'membership' ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}

	public static function sports(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'sports' ) ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'sports', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => 'Our sports',
				'title_lead'      => 'Nine sports, ',
				'title_highlight' => 'one club.',
				'lede'            => 'From first session to first team — find your section and get playing.',
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
				'link_label' => 'Join the club →',
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
				'heading'   => 'Try any sport with a free session',
				'lede'      => 'Not sure which section fits? Come down and try before you join.',
				'cta_label' => 'Register interest →',
				'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}

	public static function teams(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'teams' ) ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'teams', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => 'Our teams',
				'title_lead'      => 'Twenty-four teams, ',
				'title_highlight' => 'every level.',
				'lede'            => 'League sides, development squads and junior pathways across all nine sports.',
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
				'heading'   => 'Trials run all season',
				'lede'      => 'Every squad welcomes new players — get in touch and we will match you to a session.',
				'cta_label' => 'Get in touch →',
				'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}

	public static function events(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'events' ) ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'events', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => "What's on",
				'title_lead'      => 'Socials, camps and ',
				'title_highlight' => 'open days.',
				'lede'            => "There's always something happening at the club — on the pitch and off it.",
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
				'heading'   => 'Hire the clubhouse',
				'lede'      => 'Function room and bar available for members and the community.',
				'cta_label' => 'Enquire about hire →',
				'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}

	public static function calendar(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, Blueworx_Clubhouse_Links::url( 'calendar' ) ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'calendar', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => 'Fixtures & results',
				'title_lead'      => 'Every game, ',
				'title_highlight' => 'all season.',
				'lede'            => 'Match days across all nine sports, with results as they come in.',
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
				'eyebrow' => 'The schedule',
				'heading' => 'Fixtures & results',
				'months'  => Blueworx_Clubhouse_Fixture_Projection::calendar_months( $collections->fixtures() ),
			) );
		}
		if ( $visibility->is_section_visible( 'calendar', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Follow the club',
				'heading'   => 'Never miss a result',
				'lede'      => 'Fixtures, results and club news — one email a month.',
				'cta_label' => 'Join the mailing list →',
				'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}
}
