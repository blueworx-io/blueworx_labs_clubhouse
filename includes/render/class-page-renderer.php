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
			. '</head><body>' . $body . '</body></html>';
	}

	private static function shell_header( string $club, string $active ): string {
		return Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => $club,
			'banner'      => 'Summer sign-ups are open — register your interest for 2026/27 →',
			'banner_href' => '?page=membership',
			'nav'         => array(
				array( 'label' => 'Home', 'href' => '?page=home' ),
				array( 'label' => 'About', 'href' => '?page=about' ),
				array( 'label' => 'Sports', 'href' => '?page=sports' ),
				array( 'label' => 'Teams', 'href' => '?page=teams' ),
				array( 'label' => 'Membership', 'href' => '?page=membership' ),
				array( 'label' => 'Events', 'href' => '?page=events' ),
				array( 'label' => 'Calendar', 'href' => '?page=calendar' ),
				array( 'label' => 'Contact', 'href' => '?page=contact' ),
			),
			'active'      => $active,
			'login'       => 'Log in',
			'join'        => 'Join the Club',
			'join_href'   => '?page=membership',
		) );
	}

	private static function shell_footer( string $club ): string {
		return Blueworx_Clubhouse_Sections::footer( array(
			'club_name'  => $club,
			'tagline'    => 'Nine sports, one club. A home ground for every team, and everyone who follows them.',
			'socials'    => array( 'Facebook', 'Instagram', 'Community', 'Share' ),
			'columns'    => array(
				array( 'title' => 'Club', 'links' => array(
					array( 'label' => 'About', 'href' => '?page=about' ),
					array( 'label' => 'Sports', 'href' => '?page=sports' ),
					array( 'label' => 'Teams', 'href' => '?page=teams' ),
					array( 'label' => 'Events', 'href' => '?page=events' ),
				) ),
				array( 'title' => 'Get involved', 'links' => array(
					array( 'label' => 'Membership', 'href' => '?page=membership' ),
					array( 'label' => 'Calendar', 'href' => '?page=calendar' ),
					array( 'label' => 'Volunteer', 'href' => '?page=contact' ),
					array( 'label' => 'Contact', 'href' => '?page=contact' ),
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
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = '';

		if ( $visibility->is_section_visible( 'home', 'header' ) ) {
			$out .= self::shell_header( $club, '?page=home' );
		}
		if ( $visibility->is_section_visible( 'home', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'Est. 1974 · Marlow, UK',
				'title_lead'         => 'Every sport. Every age. ',
				'title_highlight'    => 'One community.',
				'lede'               => "Nine sports, twenty-four teams, and a clubhouse that's always open. Come for the game — stay for the people.",
				'cta_primary'        => 'Explore membership',
				'cta_primary_href'   => '?page=membership',
				'cta_secondary'      => 'Take a tour →',
				'cta_secondary_href' => '?page=about',
				'image'              => '',
				'image_alt'          => 'ClubHouse floodlit pitch on a Saturday',
				'image_caption'      => 'Saturday, floodlights on',
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'quick_tiles' ) ) {
			$out .= Blueworx_Clubhouse_Sections::quick_tiles( array(
				array( 'label' => 'Join / Membership', 'href' => '?page=membership' ),
				array( 'label' => 'Sports & Sections', 'href' => '?page=sports' ),
				array( 'label' => 'Fixtures & Results', 'href' => '?page=calendar' ),
				array( 'label' => 'Events', 'href' => '?page=events' ),
				array( 'label' => 'Contact', 'href' => '?page=contact' ),
				array( 'label' => 'Member Login', 'href' => '#' ),
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
				array( 'value' => '900+', 'label' => 'Members' ),
				array( 'value' => '9', 'label' => 'Sports' ),
				array( 'value' => '24', 'label' => 'Teams' ),
				array( 'value' => '1974', 'label' => 'Founded' ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'sports' ) ) {
			$out .= Blueworx_Clubhouse_Sections::card_grid( array(
				'eyebrow'    => 'Our sports',
				'heading'    => 'Pick your game.',
				'link_label' => 'All sections →',
				'link_href'  => '?page=sports',
				'cards'      => array(
					array( 'image' => '', 'image_alt' => 'Rugby', 'tag' => 'Sat', 'title' => 'Rugby', 'subtitle' => 'Senior · colts · touch' ),
					array( 'image' => '', 'image_alt' => 'Tennis', 'tag' => 'Daily', 'title' => 'Tennis', 'subtitle' => 'Four courts · coaching' ),
					array( 'image' => '', 'image_alt' => 'Cricket', 'tag' => 'Summer', 'title' => 'Cricket', 'subtitle' => 'Youth → senior league' ),
					array( 'image' => '', 'image_alt' => 'Football', 'tag' => 'Sun', 'title' => 'Football', 'subtitle' => 'Juniors · ages 5–16' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'clubhouse' ) ) {
			$out .= Blueworx_Clubhouse_Sections::image_band( array(
				'eyebrow'   => 'The clubhouse',
				'heading'   => 'A home ground for every team, and everyone who follows them',
				'image'     => '', 'image_alt' => 'ClubHouse pavilion at dusk',
				'cta_label' => 'Visit us', 'cta_href' => '?page=contact',
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'membership' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'accent',
				'eyebrow'   => 'Membership',
				'heading'   => 'Open to everyone, from £28/month.',
				'lede'      => 'From first-timers to county players — every tier includes clubhouse access, discounted events and a free trial session.',
				'cta_label' => 'Choose your tier →',
				'cta_href'  => '?page=membership',
			) );
			$out .= Blueworx_Clubhouse_Sections::tier_grid( array(
				array( 'eyebrow' => 'Full playing', 'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
					'features' => array( 'Any section, any level', 'League affiliation', 'Clubhouse & socials' ),
					'recommended' => false, 'cta_label' => 'Join', 'cta_href' => '?page=membership' ),
				array( 'eyebrow' => 'Best value', 'name' => 'Family', 'price' => '£45', 'period' => '/mo',
					'features' => array( 'Up to 5 members', 'Any sections', 'Priority event booking' ),
					'recommended' => true, 'cta_label' => 'Join', 'cta_href' => '?page=membership' ),
				array( 'eyebrow' => 'Off the pitch', 'name' => 'Social', 'price' => '£12', 'period' => '/mo',
					'features' => array( 'Full clubhouse access', 'Member events', 'Support your club' ),
					'recommended' => false, 'cta_label' => 'Join', 'cta_href' => '?page=membership' ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'activity' ) ) {
			$out .= Blueworx_Clubhouse_Sections::activity_tabs( array(
				'eyebrow'  => 'Club activity',
				'heading'  => "What\u{2019}s happening",
				'fixtures' => array(
					array( 'month' => 'JUL', 'day' => '12', 'competition' => 'Rugby · 1st XV', 'time' => '14:00', 'matchup' => 'ClubHouse vs Riverside RFC' ),
					array( 'month' => 'JUL', 'day' => '13', 'competition' => 'Netball · Div 2', 'time' => '11:00', 'matchup' => 'ClubHouse vs Castlebridge' ),
					array( 'month' => 'JUL', 'day' => '19', 'competition' => 'Hockey · Ladies 1s', 'time' => '15:30', 'matchup' => 'ClubHouse vs Elmwood' ),
				),
				'results'  => array(
					array( 'date' => 'JUL 5', 'home' => 'ClubHouse 1st XI', 'away' => 'Hartfield CC', 'score' => '+34', 'outcome' => 'W' ),
					array( 'date' => 'JUN 28', 'home' => 'ClubHouse 2nd XV', 'away' => 'Dunmore', 'score' => '18–24', 'outcome' => 'L' ),
					array( 'date' => 'JUL 2', 'home' => 'J. Patel', 'away' => 'R. Osei', 'score' => '2–0', 'outcome' => 'W' ),
				),
				'events'   => array(
					array( 'tag' => 'Open day', 'date' => 'Sat 26 Jul', 'title' => 'Club Open Day', 'detail' => '10:00–14:00 · Clubhouse & grounds' ),
					array( 'tag' => 'Junior football', 'date' => '4–8 Aug', 'title' => 'Summer Football Camp', 'detail' => 'Ages 5–12 · book via Events' ),
					array( 'tag' => 'Social', 'date' => 'Fri 12 Sep', 'title' => 'Annual Awards Night', 'detail' => '19:00 · Clubhouse function room' ),
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
				'names'   => array( 'Sponsor 01', 'Sponsor 02', 'Sponsor 03', 'Sponsor 04', 'Sponsor 05', 'Sponsor 06' ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'footer' ) ) {
			$out .= self::shell_footer( $club );
		}
		return $out;
	}

	public static function about(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, '?page=about' );

		if ( $visibility->is_section_visible( 'about', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'About the club',
				'title_lead'         => 'Fifty-two years of ',
				'title_highlight'    => 'community sport.',
				'lede'               => 'From one rugby pitch in 1974 to nine sports and twenty-four teams — ClubHouse has always been about more than the game.',
				'cta_primary'        => 'Join the club',
				'cta_primary_href'   => '?page=membership',
				'cta_secondary'      => 'Meet the committee',
				'cta_secondary_href' => '?page=contact',
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
				'people'  => array(
					array( 'name' => 'Priya Nair', 'role' => 'Chair', 'email' => '' ),
					array( 'name' => 'Tom Ellison', 'role' => 'Treasurer', 'email' => '' ),
					array( 'name' => 'Grace Okafor', 'role' => 'Secretary', 'email' => '' ),
					array( 'name' => 'Daniel Reed', 'role' => 'Membership', 'email' => '' ),
					array( 'name' => 'Aisha Khan', 'role' => 'Safeguarding', 'email' => '' ),
					array( 'name' => 'Mark Bailey', 'role' => 'Grounds', 'email' => '' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'facilities' ) ) {
			$out .= Blueworx_Clubhouse_Sections::image_band( array(
				'eyebrow'   => 'The facilities',
				'heading'   => 'Five pitches, four courts, one clubhouse',
				'image'     => '', 'image_alt' => 'ClubHouse grounds from the air',
				'cta_label' => 'Book a visit', 'cta_href' => '?page=contact',
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Get involved',
				'heading'   => 'Want to be part of it?',
				'lede'      => 'Play, volunteer, or just come for the atmosphere.',
				'cta_label' => 'Join the club →',
				'cta_href'  => '?page=membership',
			) );
		}
		$out .= self::shell_footer( $club );
		return $out;
	}
}
