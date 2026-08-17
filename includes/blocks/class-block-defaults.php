<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The words a block shows before its club has written any of its own.
 *
 * These used to sit inline in Page_Renderer's eleven page methods, as the last
 * argument of every cget() call. They cannot simply be frozen into seeded data,
 * because several are computed at render time: the Home lede counts the club's
 * sports and teams, the About hero points at the committee only when that
 * section is on the page, and half the Membership page says something different
 * depending on whether its tiers can take a payment. Freezing those would leave
 * a club reading "nine sports" with six.
 *
 * So they stay in code, and a block stores only what its owner has overridden —
 * exactly the behaviour cget() has always had. A block names which set it draws
 * on through its `defaults_key`, which is the page/section address it was
 * migrated or seeded from; a block an owner creates fresh has no key and gets
 * its type's generic set.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Defaults {

	/**
	 * The default content for one block.
	 *
	 * @return array<string,mixed>
	 */
	public static function for_key( string $defaults_key, Blueworx_Clubhouse_Page_State $state ): array {
		switch ( $defaults_key ) {
			case 'global/header':
				return self::header( $state );
			case 'global/footer':
				return self::footer( $state );
			case 'global/cookies':
				return self::cookies();

			case 'home/hero':
				return self::home_hero( $state );
			case 'home/ticker':
				return self::home_ticker();
			case 'home/sports':
				return self::home_sports();
			case 'home/clubhouse':
				return self::home_clubhouse( $state );
			case 'home/membership':
				return self::home_membership();
			case 'home/tiers':
			case 'membership/tiers':
				return self::tiers();
			case 'home/activity':
				return self::home_activity();
			case 'home/news':
				return self::home_news();
			case 'home/sponsors':
				return self::home_sponsors();
			case 'home/social':
				return self::home_social();

			case 'about/hero':
				return self::about_hero( $state );
			case 'about/history':
				return self::about_history( $state );
			case 'about/values':
				return self::about_values();
			case 'about/facilities':
				return self::about_facilities( $state );
			case 'about/committee':
				return self::about_committee();
			case 'about/get_involved':
				return self::about_get_involved();
			case 'about/cta':
				return self::about_cta();

			case 'membership/hero':
				return self::membership_hero( $state );
			case 'membership/why':
				return self::membership_why();
			case 'membership/detail':
				return self::membership_detail();
			case 'membership/steps':
				return self::membership_steps( $state );
			case 'membership/faq':
				return self::membership_faq( $state );
			case 'membership/cta':
				return self::membership_cta( $state );

			case 'contact/hero':
				return self::contact_hero();
			case 'contact/form':
				return self::contact_form();
			case 'contact/directory':
				return self::contact_directory();
			case 'contact/social':
				return self::contact_social();

			case 'login/form':
				return self::login_form();

			case 'news/head':
				return self::news_head();
			case 'news/featured':
				return self::news_featured();
			case 'news/posts':
				return self::news_posts();

			case 'sports/hero':
				return self::sports_hero( $state );
			case 'sports/directory':
				return self::sports_directory();
			case 'sports/cta':
				return self::sports_cta();

			case 'teams/hero':
				return self::teams_hero( $state );
			case 'teams/directory':
				return self::teams_directory();
			case 'teams/cta':
				return self::teams_cta();

			case 'events/hero':
				return self::events_hero();
			case 'events/upcoming':
				return self::events_upcoming();
			case 'events/past':
				return self::events_past();
			case 'events/cta':
				return self::events_cta();

			case 'calendar/hero':
				return self::calendar_hero( $state );
			case 'calendar/booking':
				return self::calendar_booking();
			case 'calendar/schedule':
				return self::calendar_schedule();
			case 'calendar/cta':
				return self::calendar_cta();

			case 'booking/hero':
				return self::booking_hero( $state );
			case 'booking/services':
				return self::booking_slot( 'What you can book', 'Sessions and services', 'services' );
			case 'booking/locations':
				return self::booking_slot( 'Where you play', 'Courts and locations', 'locations' );
			case 'booking/agents':
				return self::booking_slot( 'Who you book with', 'Coaches and staff', 'agents' );

			case 'privacy/hero':
				return self::legal_hero( 'privacy', $state );
			case 'privacy/body':
				return self::legal_body( 'privacy', $state );
			case 'terms/hero':
				return self::legal_hero( 'terms', $state );
			case 'terms/body':
				return self::legal_body( 'terms', $state );
		}
		return array();
	}

	// -- Global ---------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function header( Blueworx_Clubhouse_Page_State $state ): array {
		return array(
			'banner_show' => true,
			'banner'      => 'Summer sign-ups are open — register your interest for 2026/27 →',
			'banner_href' => Blueworx_Clubhouse_Links::url( 'membership' ),
			'join'        => Blueworx_Clubhouse_Cta::JOIN,
			'join_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
		);
	}

	/** @return array<string,mixed> */
	private static function footer( Blueworx_Clubhouse_Page_State $state ): array {
		// The cookie notice renders inside the footer rather than as a section of
		// its own, so its wording lives on the footer block, under cookie_* keys
		// that cannot collide with the footer's own fields.
		$cookies = self::cookies();
		return array(
			'tagline'              => 'One club, every sport. A home ground for every team, and everyone who follows them.',
			'newsletter_heading'   => 'Stay in the loop',
			'newsletter_lede'      => 'Fixtures, results and club news — one email a month.',
			'newsletter_shortcode' => '',
			'cookie_show'          => $cookies['show'],
			'cookie_text'          => $cookies['text'],
			'cookie_link_label'    => $cookies['link_label'],
			'cookie_link_href'     => $cookies['link_href'],
			'cookie_dismiss'       => $cookies['dismiss'],
		);
	}

	/**
	 * The default cookie wording. Written to be true of a stock Clubhouse site
	 * rather than to sound thorough: this plugin sets nothing on a visitor's
	 * machine, WordPress sets a cookie when somebody signs in, and the shop and
	 * its payment provider set their own on the pages that take payment.
	 *
	 * @return array<string,mixed>
	 */
	private static function cookies(): array {
		return array(
			'show'       => true,
			'text'       => 'This site uses cookies to keep you signed in and, on our shop pages, to take payment. We do not use them to advertise to you.',
			'link_label' => 'Read our privacy policy',
			'link_href'  => Blueworx_Clubhouse_Links::url( 'privacy' ),
			'dismiss'    => 'Got it',
		);
	}

	// -- Home -----------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function home_hero( Blueworx_Clubhouse_Page_State $state ): array {
		$collections = $state->collections();
		return array(
			'eyebrow'            => 'Est. 1974 · Marlow, UK',
			'title_lead'         => 'Every sport. Every age. ',
			'title_highlight'    => 'One community.',
			'lede'               => Blueworx_Clubhouse_Page_Renderer::number_word_upper( count( $collections->sports() ) )
				. ' sports, ' . Blueworx_Clubhouse_Page_Renderer::number_word( count( $collections->teams() ) )
				. " teams, and a clubhouse that's always open. Come for the game — stay for the people.",
			// Off by default — the quick-tile row below repeats these actions. Still
			// configurable: an owner who sets a label gets the button pair back.
			'cta_primary'        => '',
			'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
			'cta_secondary'      => '',
			'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'about' ),
			'image'              => '',
			'image_alt'          => $state->club() . ' floodlit pitch on a Saturday',
			'tiles_id'           => Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'home', 'quick_tiles' ),
			'items'              => array(
				array( 'label' => Blueworx_Clubhouse_Cta::JOIN, 'href' => Blueworx_Clubhouse_Links::url( 'membership' ), 'icon' => 'join' ),
				array( 'label' => 'Take a tour', 'href' => Blueworx_Clubhouse_Links::url( 'about' ), 'icon' => 'tour' ),
				array( 'label' => 'See fixtures', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ), 'icon' => 'fixtures' ),
				array( 'label' => 'Get in touch', 'href' => Blueworx_Clubhouse_Links::url( 'contact' ), 'icon' => 'contact' ),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function home_ticker(): array {
		return array(
			'items' => array(
				array( 'text' => '1st XV promoted to Div 3 South' ),
				array( 'text' => 'Open Day — Sat 26 Jul, 10:00–14:00' ),
				array( 'text' => 'Clubhouse refurbishment complete' ),
				array( 'text' => 'Summer Football Camp · 4–8 Aug' ),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function home_sports(): array {
		return array(
			'eyebrow' => 'Our sports',
			'heading' => 'Pick your game.',
		);
	}

	/** @return array<string,mixed> */
	private static function home_clubhouse( Blueworx_Clubhouse_Page_State $state ): array {
		return array(
			'eyebrow'   => 'The clubhouse',
			'heading'   => "Bar, kitchen and a full social calendar — the club doesn\u{2019}t stop at the final whistle",
			'image'     => '',
			'image_alt' => $state->club() . ' pavilion at dusk',
			'cta_label' => 'Visit us',
			'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
		);
	}

	/** @return array<string,mixed> */
	private static function home_membership(): array {
		return array(
			'variant'   => 'accent',
			'eyebrow'   => 'Membership',
			'heading'   => 'Open to everyone, from £28/month.',
			'lede'      => 'From first-timers to county players — every tier includes clubhouse access, discounted events and a free trial session.',
			'cta_label' => Blueworx_Clubhouse_Cta::JOIN . ' →',
			'cta_href'  => Blueworx_Clubhouse_Links::url( 'membership' ),
		);
	}

	/** @return array<string,mixed> */
	private static function home_activity(): array {
		return array(
			'eyebrow' => 'Club activity',
			'heading' => "What\u{2019}s happening",
		);
	}

	/** @return array<string,mixed> */
	private static function home_news(): array {
		return array(
			'eyebrow' => 'Latest news',
			'heading' => 'From the clubhouse',
			'items'   => array(
				array( 'image' => '', 'image_alt' => 'Clubhouse interior', 'tag' => 'Club news', 'date' => '2 Jul', 'title' => 'Clubhouse refurbishment complete' ),
				array( 'image' => '', 'image_alt' => 'Junior footballers', 'tag' => 'Sections', 'date' => '28 Jun', 'title' => 'Junior Football signs 40 new players' ),
				array( 'image' => '', 'image_alt' => 'Volunteers', 'tag' => 'Volunteering', 'date' => '24 Jun', 'title' => 'Volunteers needed for the Open Day' ),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function home_sponsors(): array {
		return array(
			'eyebrow'    => 'Our partners',
			'heading'    => 'Our sponsors & partners',
			'link_label' => 'Become a sponsor',
			'link_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
		);
	}

	/**
	 * The band that closes the Home page: the club's socials, and the find-us
	 * columns that used to be a section of their own (`home/info`). One rendered
	 * block, two halves, each with its own switch — which is why they are
	 * settings on this block rather than two blocks.
	 *
	 * @return array<string,mixed>
	 */
	private static function home_social(): array {
		return array(
			'heading' => 'Follow the club',
			'lede'    => 'Match-day photos, results and behind-the-scenes — join us on socials.',
			'cols_id' => Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'home', 'info' ),
			'items'   => array(
				array( 'label' => 'Location', 'lines' => array( '12 Riverside Lane', 'Marlow, SL7 1AA' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Opening hours', 'lines' => array( 'Mon–Sun', '7:00am – 10:00pm' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Contact', 'lines' => array( 'hello@clubhouse.example', '01628 000 000' ), 'link_label' => '', 'link_href' => '' ),
				array( 'label' => 'Find us', 'lines' => array(), 'link_label' => 'Open in Maps', 'link_href' => Blueworx_Clubhouse_Sections::maps_url( array( '12 Riverside Lane', 'Marlow, SL7 1AA' ) ) ),
			),
		);
	}

	// -- About ----------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function about_hero( Blueworx_Clubhouse_Page_State $state ): array {
		$collections = $state->collections();
		$club        = $state->club();
		return array(
			'eyebrow'            => 'About the club',
			'title_lead'         => 'Fifty-two years of ',
			'title_highlight'    => 'community sport.',
			'lede'               => 'From one rugby pitch in 1974 to ' . Blueworx_Clubhouse_Page_Renderer::number_word( count( $collections->sports() ) )
				. ' sports and ' . Blueworx_Clubhouse_Page_Renderer::number_word( count( $collections->teams() ) )
				. ' teams — ' . $club . ' has always been about more than the game.',
			'cta_primary'        => Blueworx_Clubhouse_Cta::JOIN,
			'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
			'cta_secondary'      => 'Meet the committee',
			// The committee is further down this same page, so the button that
			// offers to introduce them goes there. A club that has taken that block
			// off the page gets the contact page back, because the anchor would
			// point at nothing.
			'cta_secondary_href' => $state->has_type( 'people_grid' )
				? '#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'about', 'committee' )
				: Blueworx_Clubhouse_Links::url( 'contact' ),
			'image'              => '',
			'image_alt'          => $club . ' members on the terrace',
			'image_caption'      => '',
		);
	}

	/** @return array<string,mixed> */
	private static function about_history( Blueworx_Clubhouse_Page_State $state ): array {
		return array(
			'eyebrow' => 'Our story',
			'heading' => 'From one pitch to ' . Blueworx_Clubhouse_Page_Renderer::number_word( count( $state->collections()->sports() ) ) . ' sports',
			'items'   => array(
				array( 'year' => '1974', 'title' => 'One pitch, one team', 'desc' => 'A handful of rugby players lease a field by the river.' ),
				array( 'year' => '1982', 'title' => 'Cricket joins', 'desc' => 'Summer cricket takes over the square; the first pavilion goes up.' ),
				array( 'year' => '1991', 'title' => 'Juniors take root', 'desc' => 'Minis and colts sections launch across rugby and cricket.' ),
				array( 'year' => '2003', 'title' => 'Courts & clubhouse', 'desc' => 'Four tennis courts and the current clubhouse open.' ),
				array( 'year' => '2015', 'title' => 'A multi-sport club', 'desc' => 'Hockey, netball and squash complete the multi-sport club.' ),
				array( 'year' => '2024', 'title' => 'A modern home', 'desc' => 'A full clubhouse refurbishment for the next generation.' ),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function about_values(): array {
		return array(
			'eyebrow' => 'What we stand for',
			'heading' => 'Our values',
			'items'   => array(
				array( 'title' => 'Everyone plays', 'description' => 'Beginners and county players train side by side, every age welcome.' ),
				array( 'title' => 'Volunteer-run', 'description' => 'Coaches, committee and bar staff give their time so the club thrives.' ),
				array( 'title' => 'Community first', 'description' => 'The clubhouse is a place to belong, on and off the pitch.' ),
				array( 'title' => 'Play for life', 'description' => 'Pathways from minis to vets — a home for the whole journey.' ),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function about_facilities( Blueworx_Clubhouse_Page_State $state ): array {
		return array(
			'eyebrow'   => 'The facilities',
			'heading'   => 'Five pitches, four courts, one clubhouse',
			'image'     => '',
			'image_alt' => $state->club() . ' grounds from the air',
			'cta_label' => 'Arrange a visit',
			'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
		);
	}

	/** @return array<string,mixed> */
	private static function about_committee(): array {
		return array(
			'eyebrow' => 'Who runs the club',
			'heading' => 'The committee',
			'source'  => 'committee',
		);
	}

	/** @return array<string,mixed> */
	private static function about_get_involved(): array {
		return array(
			'eyebrow' => 'Beyond the pitch',
			'heading' => 'Get involved',
			'items'   => array(
				array( 'title' => 'Volunteer', 'description' => 'Help on match days, run the bar, or join the committee — every hand counts.' ),
				array( 'title' => 'Coach & officiate', 'description' => 'Gain qualifications and give the next generation their start.' ),
				array( 'title' => 'Sponsor & partner', 'description' => 'Back a team or the clubhouse and reach the whole community.' ),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function about_cta(): array {
		return array(
			'variant'   => 'ink',
			'eyebrow'   => 'Membership',
			'heading'   => 'Want to be part of it?',
			'lede'      => 'Play, volunteer, or just come for the atmosphere.',
			'cta_label' => Blueworx_Clubhouse_Cta::JOIN . ' →',
			'cta_href'  => Blueworx_Clubhouse_Links::url( 'membership' ),
		);
	}

	// -- Membership -----------------------------------------------------------

	/** @return array<string,mixed> */
	private static function membership_hero( Blueworx_Clubhouse_Page_State $state ): array {
		$sells = $state->tiers_sell();
		return array(
			'eyebrow'            => 'Membership',
			'title_lead'         => $sells ? 'Join in five minutes. ' : 'Find your membership. ',
			'title_highlight'    => 'Play for years.',
			'lede'               => 'From first-timers to county players, there is a category for you — every membership includes clubhouse access, discounted events and a free trial.',
			'cta_primary'        => $sells ? 'Choose your membership' : 'Register interest',
			'cta_primary_href'   => $sells ? $state->tiers_anchor() : Blueworx_Clubhouse_Links::url( 'contact' ),
			'cta_secondary'      => 'Ask a question',
			'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
			'image'              => '',
			'image_alt'          => $state->club() . ' members warming up',
			'image_caption'      => '',
		);
	}

	/** @return array<string,mixed> */
	private static function tiers(): array {
		return array( 'items' => self::tier_items() );
	}

	/**
	 * The tiers a club starts with, before it has written its own.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function tier_items(): array {
		return array(
			array(
				'eyebrow' => 'Under 18', 'name' => 'Junior', 'price' => '£12', 'period' => '/mo',
				'features' => array( 'Any junior section', 'Coaching included', 'Holiday camp discounts' ),
				'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
			),
			array(
				'eyebrow' => 'Full playing', 'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
				'features' => array( 'Any section, any level', 'League affiliation', 'Clubhouse & socials' ),
				'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
			),
			array(
				'eyebrow' => 'Best value', 'name' => 'Family', 'price' => '£45', 'period' => '/mo',
				'features' => array( 'Up to 5 members', 'Any sections', 'Priority event booking' ),
				'recommended' => true, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
			),
			array(
				'eyebrow' => 'Off the pitch', 'name' => 'Social', 'price' => '£12', 'period' => '/mo',
				'features' => array( 'Full clubhouse access', 'Member events', 'Support your club' ),
				'recommended' => false, 'cta_label' => 'Join', 'cta_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
			),
		);
	}

	/**
	 * One tier, priced by the shop when it names a real price.
	 *
	 * Anything missing — no shop, no such price, no checkout page — leaves the
	 * tier exactly as the club typed it, which is how every site behaved before
	 * this existed. Deliberately all-or-nothing: showing the shop's price beside
	 * a contact link would advertise a price the visitor cannot pay.
	 *
	 * @param array<string,mixed> $t
	 * @return array<string,mixed>
	 */
	public static function price_tier( array $t ): array {
		$tier = array(
			'eyebrow'     => (string) ( $t['eyebrow'] ?? '' ),
			'name'        => (string) ( $t['name'] ?? '' ),
			'price'       => (string) ( $t['price'] ?? '' ),
			'period'      => (string) ( $t['period'] ?? '' ),
			'features'    => Blueworx_Clubhouse_Block_Content::lines( $t['features'] ?? array() ),
			'recommended' => (bool) ( $t['featured'] ?? ( $t['recommended'] ?? false ) ),
			'cta_label'   => (string) ( $t['cta_label'] ?? '' ),
			'cta_href'    => (string) ( $t['cta_href'] ?? Blueworx_Clubhouse_Links::url( 'contact' ) ),
		);

		$price_id = (string) ( $t['price_id'] ?? '' );
		if ( '' === $price_id ) {
			return $tier;
		}
		$price = Blueworx_Clubhouse_Products_Source::get()?->price( $price_id );
		if ( null === $price ) {
			return $tier;
		}
		$checkout = Blueworx_Clubhouse_Checkout::url( $price_id );
		if ( '' === $checkout ) {
			return $tier;
		}

		$tier['price']    = $price['amount'];
		$tier['period']   = $price['period'];
		$tier['cta_href'] = $checkout;
		return $tier;
	}

	/** @return array<string,mixed> */
	private static function membership_why(): array {
		return array(
			'eyebrow' => 'Why join',
			'heading' => 'More than a membership',
			'items'   => array(
				array( 'title' => 'All training included', 'description' => 'Access every session for your section, all season.' ),
				array( 'title' => 'Discounted events', 'description' => 'Members save on tournaments, socials and camps.' ),
				array( 'title' => 'Clubhouse & socials', 'description' => 'The bar, the terrace, and a calendar of member events.' ),
				array( 'title' => 'Kit discounts', 'description' => 'Save on team kit at our partner suppliers.' ),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function membership_detail(): array {
		return array(
			'eyebrow'            => 'The detail',
			'heading'            => 'What is included',
			'included_label'     => 'Included',
			'not_included_label' => 'Not included',
			'policies_label'     => 'Good to know',
			'policies'           => array(
				array( 'title' => 'Free trial', 'desc' => 'Your first session is on us — try before you join.' ),
				array( 'title' => 'Juniors', 'desc' => 'Under-18s pay a reduced rate; safeguarding applies to all youth sections.' ),
				array( 'title' => 'Family cap', 'desc' => 'Family membership covers up to five people at one address.' ),
			),
			'items'              => array_merge(
				array_map(
					static fn( string $t ): array => array( 'text' => $t, 'included' => true ),
					array( "Access to all your section's training", 'League match fees', 'Clubhouse & bar membership', 'Member events & socials' )
				),
				array_map(
					static fn( string $t ): array => array( 'text' => $t, 'included' => false ),
					array( 'Individual coaching (available separately)', 'Tournament entry fees', 'Club kit (discounted, not free)' )
				)
			),
		);
	}

	/** @return array<string,mixed> */
	private static function membership_steps( Blueworx_Clubhouse_Page_State $state ): array {
		$sells = $state->tiers_sell();
		return array(
			'eyebrow' => 'How to join',
			'heading' => 'Four steps to playing',
			'items'   => array(
				array( 'number' => '01', 'title' => 'Pick your section', 'description' => 'Browse sports and find where you fit.' ),
				array( 'number' => '02', 'title' => 'Choose a tier', 'description' => 'Adult, family, junior or social.' ),
				$sells
					? array( 'number' => '03', 'title' => 'Join and pay', 'description' => 'Pay securely online — it takes a couple of minutes.' )
					: array( 'number' => '03', 'title' => 'Register interest', 'description' => 'Fill in a short form — no payment yet.' ),
				$sells
					? array( 'number' => '04', 'title' => 'Come and play', 'description' => 'We will match you to a coach and a session.' )
					: array( 'number' => '04', 'title' => 'Come and play', 'description' => 'We will be in touch, then match you to a coach and a session.' ),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function membership_faq( Blueworx_Clubhouse_Page_State $state ): array {
		$sells = $state->tiers_sell();
		return array(
			'eyebrow' => 'Questions',
			'heading' => 'Frequently asked',
			'items'   => array(
				array( 'question' => 'Do I have to commit for a season?', 'answer' => 'No — you can join any time and pay monthly.', 'open' => true ),
				array( 'question' => 'Can I try before I join?', 'answer' => 'Yes, your first session is a free trial.', 'open' => false ),
				array( 'question' => 'Do you have junior sections?', 'answer' => 'Every sport runs junior pathways from age 5 upward.', 'open' => false ),
				array( 'question' => 'Is there a family rate?', 'answer' => 'Family membership covers up to five people at one address.', 'open' => false ),
				$sells
					? array( 'question' => 'How do I pay?', 'answer' => 'By card, at checkout, when you join.', 'open' => false )
					: array( 'question' => 'How do I pay?', 'answer' => 'Payment details are arranged once your interest is confirmed.', 'open' => false ),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function membership_cta( Blueworx_Clubhouse_Page_State $state ): array {
		$sells = $state->tiers_sell();
		return array(
			'variant'   => 'ink',
			'eyebrow'   => 'Ready?',
			'heading'   => $sells ? 'Join the club' : 'Register your interest',
			'lede'      => $sells
				? 'Pick the membership that fits and pay online — you can play this week.'
				: 'Tell us a little about you and we will be in touch within a few days.',
			'cta_label' => $sells ? 'See memberships →' : 'Register interest →',
			'cta_href'  => $sells ? $state->tiers_anchor() : Blueworx_Clubhouse_Links::url( 'contact' ),
		);
	}

	// -- Contact --------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function contact_hero(): array {
		return array(
			'eyebrow'            => 'Contact',
			'title_lead'         => 'We will point you to ',
			'title_highlight'    => 'the right person.',
			'lede'               => 'Questions about joining, playing, or hiring the clubhouse? Start here.',
			'cta_primary'        => 'Email the club',
			'cta_primary_href'   => 'mailto:hello@clubhouse.example',
			'cta_secondary'      => 'Call 01628 000 000',
			'cta_secondary_href' => 'tel:01628000000',
			'image'              => '',
			'image_alt'          => '',
			'image_caption'      => '',
		);
	}

	/** @return array<string,mixed> */
	private static function contact_form(): array {
		return array(
			'eyebrow'      => 'Get in touch',
			'heading'      => 'Send us a message',
			'shortcode'    => '',
			'offline_note' => 'Drop us an email and someone from the committee will come back to you.',
			'submit_label' => 'Send message',
			'info_heading' => 'Find us',
			'address'      => "12 Riverside Lane\nMarlow, SL7 1AA",
			'email'        => 'hello@clubhouse.example',
			'phone'        => '01628 000 000',
			'map_image'    => '',
		);
	}

	/** @return array<string,mixed> */
	private static function contact_directory(): array {
		return array(
			'eyebrow' => 'Who to contact',
			'heading' => 'The directory',
			'source'  => 'directory',
		);
	}

	/** @return array<string,mixed> */
	private static function contact_social(): array {
		return array(
			'heading' => 'Stay connected',
			'lede'    => 'Follow the club for match-day updates, results and event announcements.',
			'cols_id' => '',
			'items'   => array(),
		);
	}

	// -- Login ----------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function login_form(): array {
		return array(
			'eyebrow' => 'Members',
			'heading' => 'Log in to your account',
			'lede'    => 'Access your membership, bookings and club events.',
		);
	}

	// -- News -----------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function news_head(): array {
		return array(
			'eyebrow'         => 'The clubhouse journal',
			'title_lead'      => 'News from ',
			'title_highlight' => 'the club.',
			'lede'            => 'Match reports, section updates, coaching notes and everything else happening on and off the pitch.',
		);
	}

	/** @return array<string,mixed> */
	private static function news_featured(): array {
		return array(
			'label' => 'Featured',
			'cta'   => 'Read the story',
		);
	}

	/** @return array<string,mixed> */
	private static function news_posts(): array {
		return array(
			'filter_label'      => 'Filter news by category',
			'empty_text'        => 'There is no club news yet. Anything the club publishes will appear here.',
			'empty_text_filter' => 'No news in this category yet.',
		);
	}

	// -- Sports ---------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function sports_hero( Blueworx_Clubhouse_Page_State $state ): array {
		return array(
			'eyebrow'         => 'Our sports',
			'title_lead'      => Blueworx_Clubhouse_Page_Renderer::number_word_upper( count( $state->collections()->sports() ) ) . ' sports, ',
			'title_highlight' => 'one club.',
			'lede'            => 'From first session to first team — find your section and get playing.',
			'filter_label'    => 'Filter by sport',
		);
	}

	/** @return array<string,mixed> */
	private static function sports_directory(): array {
		return array(
			'eyebrow'      => 'All sections',
			'heading'      => 'Pick your sport.',
			'link_label'   => Blueworx_Clubhouse_Cta::JOIN . ' →',
			'link_href'    => Blueworx_Clubhouse_Links::url( 'membership' ),
			'empty_filter' => 'No sections match that filter.',
		);
	}

	/** @return array<string,mixed> */
	private static function sports_cta(): array {
		return array(
			'variant'   => 'ink',
			'eyebrow'   => 'New to the club?',
			'heading'   => 'Try any sport with a free session',
			'lede'      => 'Not sure which section fits? Come down and try before you join.',
			'cta_label' => 'Register interest →',
			'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
		);
	}

	// -- Teams ----------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function teams_hero( Blueworx_Clubhouse_Page_State $state ): array {
		$collections = $state->collections();
		return array(
			'eyebrow'         => 'Our teams',
			'title_lead'      => Blueworx_Clubhouse_Page_Renderer::number_word_upper( count( $collections->teams() ) ) . ' teams, ',
			'title_highlight' => 'every level.',
			'lede'            => 'League sides, development squads and junior pathways across all '
				. Blueworx_Clubhouse_Page_Renderer::number_word( count( $collections->sports() ) ) . ' sports.',
			'filter_label'    => 'Filter teams by sport',
		);
	}

	/** @return array<string,mixed> */
	private static function teams_directory(): array {
		return array(
			'eyebrow'      => 'Squads',
			'heading'      => 'Find your team.',
			'link_label'   => '',
			'link_href'    => '',
			'empty_filter' => 'No teams match that filter.',
		);
	}

	/** @return array<string,mixed> */
	private static function teams_cta(): array {
		return array(
			'variant'   => 'ink',
			'eyebrow'   => 'Want to play?',
			'heading'   => 'Trials run all season',
			'lede'      => 'Every squad welcomes new players — get in touch and we will match you to a session.',
			'cta_label' => 'Get in touch →',
			'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
		);
	}

	// -- Events ---------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function events_hero(): array {
		return array(
			'eyebrow'         => "What's on",
			'title_lead'      => 'Socials, camps and ',
			'title_highlight' => 'open days.',
			'lede'            => "There's always something happening at the club — on the pitch and off it.",
			'filter_label'    => 'Filter events by type',
		);
	}

	/** @return array<string,mixed> */
	private static function events_upcoming(): array {
		return array(
			'eyebrow'      => 'Coming up',
			'heading'      => 'Upcoming events',
			'empty_filter' => 'No events match that filter.',
		);
	}

	/** @return array<string,mixed> */
	private static function events_past(): array {
		return array( 'heading' => 'Recently at the club' );
	}

	/** @return array<string,mixed> */
	private static function events_cta(): array {
		return array(
			'variant'   => 'ink',
			'eyebrow'   => 'Hosting something?',
			'heading'   => 'Hire the clubhouse',
			'lede'      => 'Function room and bar available for members and the community.',
			'cta_label' => 'Enquire about hire →',
			'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
		);
	}

	// -- Calendar -------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function calendar_hero( Blueworx_Clubhouse_Page_State $state ): array {
		return array(
			'eyebrow'         => 'Fixtures & results',
			'title_lead'      => 'Every game, ',
			'title_highlight' => 'all season.',
			'lede'            => 'Match days across all ' . Blueworx_Clubhouse_Page_Renderer::number_word( count( $state->collections()->sports() ) ) . ' sports, with results as they come in.',
			// No pills here: on this page they filter the fixtures list far below,
			// not the booking calendar that follows the hero.
			'filter_label'    => '',
		);
	}

	/** @return array<string,mixed> */
	private static function calendar_booking(): array {
		return array(
			'eyebrow'    => 'Court bookings',
			'heading'    => 'Book a court',
			'shortcode'  => '[latepoint_calendar view="month"]',
			// The other half of the same journey: this grid answers "when"; the
			// Bookings page answers what, where and who.
			'link_label' => 'Sessions, courts and coaches',
			'link_href'  => Blueworx_Clubhouse_Links::url( 'booking' ),
		);
	}

	/** @return array<string,mixed> */
	private static function calendar_schedule(): array {
		return array(
			'eyebrow'      => 'The schedule',
			'heading'      => 'Fixtures & results',
			'filter_label' => 'Filter fixtures by sport',
			'empty_text'   => 'No fixtures listed yet — check back soon.',
			'empty_filter' => 'No fixtures match that filter.',
		);
	}

	/** @return array<string,mixed> */
	private static function calendar_cta(): array {
		return array(
			'variant'   => 'ink',
			'eyebrow'   => 'Follow the club',
			'heading'   => 'Never miss a result',
			'lede'      => 'Fixtures, results and club news — one email a month.',
			'cta_label' => 'Join the mailing list →',
			'cta_href'  => Blueworx_Clubhouse_Links::url( 'contact' ),
		);
	}

	// -- Booking --------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function booking_hero( Blueworx_Clubhouse_Page_State $state ): array {
		return array(
			'eyebrow'            => 'Court bookings',
			'title_lead'         => 'Book your ',
			'title_highlight'    => 'time on court.',
			'lede'               => 'See what the club runs, where you play and who you book with. Times and free slots are on the calendar.',
			'cta_primary'        => 'See what is free',
			'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'calendar' ) . '#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'calendar', 'booking' ),
			'cta_secondary'      => 'Contact the club',
			'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'contact' ),
			'image'              => '',
			'image_alt'          => $state->club(),
			'image_caption'      => '',
		);
	}

	/** @return array<string,mixed> */
	private static function booking_slot( string $eyebrow, string $heading, string $items ): array {
		return array(
			'eyebrow'    => $eyebrow,
			'heading'    => $heading,
			'shortcode'  => '[latepoint_resources items="' . $items . '" columns="3"]',
			'link_label' => '',
			'link_href'  => '',
		);
	}

	// -- Legal ----------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function legal_hero( string $page, Blueworx_Clubhouse_Page_State $state ): array {
		$club = $state->club();
		$copy = 'privacy' === $page
			? array(
				'eyebrow'         => 'Privacy',
				'title_lead'      => 'How we look after ',
				'title_highlight' => 'your details.',
				'lede'            => 'What ' . $club . ' collects when you get in touch or join, why we hold it, and how to ask us to change or delete it.',
			)
			: array(
				'eyebrow'         => 'Terms',
				'title_lead'      => 'The rules of ',
				'title_highlight' => 'using this site.',
				'lede'            => 'What you can expect from ' . $club . ', and what we expect from you.',
			);
		// A legal page is a destination, not a journey — nothing to click on to,
		// and no photograph.
		return $copy + array(
			'cta_primary'        => '',
			'cta_primary_href'   => '',
			'cta_secondary'      => '',
			'cta_secondary_href' => '',
			'image'              => '',
			'image_alt'          => '',
			'image_caption'      => '',
		);
	}

	/**
	 * The starter policy wording. Deliberately narrow: it says only what is true
	 * of a stock Clubhouse site and names the things only the club can answer
	 * rather than inventing them. A generated policy that confidently describes
	 * data sharing nobody does is worse than an obviously unfinished one,
	 * because only the second gets corrected.
	 *
	 * @return array<string,mixed>
	 */
	private static function legal_body( string $page, Blueworx_Clubhouse_Page_State $state ): array {
		$club = $state->club();
		if ( 'privacy' === $page ) {
			return array(
				'heading' => '',
				'items'   => array(
					array(
						'heading' => 'Who we are',
						'body'    => $club . ' runs this website. If you have a question about anything on this page, or you want to see, correct or delete what we hold about you, contact us through the contact page and say so — we will answer.'
							. "\n\n" . 'ADD: your club’s postal address, and the name or role of whoever handles data questions. If your club is registered with the ICO, add your registration number here.',
					),
					array(
						'heading' => 'What we collect, and when',
						'body'    => 'We only collect what you type into a form on this site, and only when you choose to send it.'
							. "\n\n" . 'Getting in touch: your name, email address and whatever you write in the message. Joining or paying: your name, email address and billing details, which go to our payment provider — we never see or store your card number. Signing in: your email address and password, held by the website itself. Booking a session: your name, email address and phone number.'
							. "\n\n" . 'ADD: anything else your club collects — membership forms, medical or emergency-contact details for juniors, photography consent.',
					),
					array(
						'heading' => 'Why we hold it',
						'body'    => 'To reply to you, to run your membership, to take a payment you have asked to make, and to organise the sessions you have booked. We do not sell it, and we do not use it to advertise to you.',
					),
					array(
						'heading' => 'Who else sees it',
						'body'    => 'Our website host, and our payment provider when you pay. Both handle it on our behalf and are not allowed to use it for anything else.'
							. "\n\n" . 'ADD: anyone else your club shares data with — a league or governing body, an email newsletter service, a booking system.',
					),
					array(
						'heading' => 'Cookies',
						'body'    => 'A cookie is a small file a website leaves on your device. This site sets one when you sign in, so it can remember you between pages. Our shop and payment pages set their own when you use them, which is what makes paying work.'
							. "\n\n" . 'ADD: if your club adds analytics or advertising tools, say so here — those are the ones people care about.',
					),
					array(
						'heading' => 'How long we keep it',
						'body'    => 'ADD: how long your club keeps enquiries, membership records and payment records. Payment records usually have to be kept for six years; an enquiry rarely needs keeping at all once it is answered.',
					),
					array(
						'heading' => 'Your rights',
						'body'    => 'You can ask us what we hold about you, ask us to correct it, and ask us to delete it. You can withdraw consent at any time, and you can complain to the Information Commissioner’s Office at ico.org.uk if you think we have got it wrong. Ask us first if you can — it is usually quicker.',
					),
					array(
						'heading' => 'Children',
						'body'    => 'ADD: how your club handles junior members’ details, and who gives consent for them.',
					),
				),
			);
		}
		return array(
			'heading' => '',
			'items'   => array(
				array(
					'heading' => 'Using this website',
					'body'    => 'You are welcome to use this site to find out about the club, get in touch, and manage a membership. Please do not try to break it, take it offline, or use it to send anyone anything they did not ask for.',
				),
				array(
					'heading' => 'Membership and payments',
					'body'    => 'ADD: what a membership includes and for how long, when payment is taken, whether it renews automatically, and how somebody cancels. If you take payments, this is the section that matters most — write it before you sell anything.',
				),
				array(
					'heading' => 'Refunds',
					'body'    => 'ADD: your club’s refund position — for memberships, for sessions cancelled by a member, and for sessions cancelled by the club.',
				),
				array(
					'heading' => 'Bookings',
					'body'    => 'ADD: how far ahead sessions can be booked, how much notice is needed to cancel one, and what happens if somebody does not turn up.',
				),
				array(
					'heading' => 'Behaviour at the club',
					'body'    => 'ADD: link to your club’s code of conduct, safeguarding policy and any ground rules, or write the short version here.',
				),
				array(
					'heading' => 'What we can and cannot promise',
					'body'    => 'We keep this site accurate and available as best we can, but we cannot promise it is never wrong or never offline. Fixtures, times and prices can change.',
				),
				array(
					'heading' => 'Changes to these terms',
					'body'    => 'We may update this page. The version here is the one that applies.',
				),
			),
		);
	}
}
