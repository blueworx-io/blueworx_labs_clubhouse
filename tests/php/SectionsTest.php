<?php
// tests/php/SectionsTest.php

use PHPUnit\Framework\TestCase;

final class SectionsTest extends TestCase {

	/** No raw hex colour literal in markup — but ignore HTML numeric entities like &#039;. */
	private function assertNoHexColour( string $html ): void {
		$stripped = preg_replace( '/&#\d+;/', '', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $stripped );
	}

	public function test_header_renders_banner_nav_active_and_dual_cta(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => 'ClubHouse',
			'banner'      => 'Summer sign-ups are open →',
			'banner_href' => '?page=membership',
			'nav'         => array(
				array( 'label' => 'Home', 'href' => '?page=home' ),
				array( 'label' => 'Membership', 'href' => '?page=membership' ),
			),
			'active'      => '?page=home',
			'login'       => 'Log in',
			'join'        => 'Join the Club',
			'join_href'   => '?page=membership',
		) );
		$this->assertStringContainsString( 'class="ch-banner"', $html );
		$this->assertStringContainsString( 'Summer sign-ups are open', $html );
		$this->assertStringContainsString( 'class="ch-nav"', $html );
		$this->assertStringContainsString( 'ch-nav__link--active', $html );
		$this->assertStringContainsString( 'Log in', $html );
		$this->assertStringContainsString( 'Join the Club', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_header_hides_banner_when_empty(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name' => 'ClubHouse', 'banner' => '', 'banner_href' => '',
			'nav' => array(), 'active' => '', 'login' => 'Log in',
			'join' => 'Join', 'join_href' => '#',
		) );
		$this->assertStringNotContainsString( 'class="ch-banner"', $html );
	}

	public function test_hero_highlights_accent_and_renders_media(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow'            => 'Est. 1974',
			'title_lead'         => 'Every sport. Every age. ',
			'title_highlight'    => 'One community.',
			'lede'               => 'Nine sports, twenty-four teams.',
			'cta_primary'        => 'Explore membership',
			'cta_primary_href'   => '?page=membership',
			'cta_secondary'      => 'Take a tour',
			'cta_secondary_href' => '#',
			'image'              => '',
			'image_alt'          => '',
			'image_caption'      => 'Saturday, floodlights on',
		) );
		$this->assertStringContainsString( 'class="ch-hero"', $html );
		$this->assertStringContainsString( 'class="ch-hero__hl"', $html );
		$this->assertStringContainsString( 'class="ch-hero__media"', $html );
		$this->assertStringContainsString( 'Saturday, floodlights on', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_hero_without_media_when_no_image_or_caption(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow' => 'Contact', 'title_lead' => 'We will point you to ', 'title_highlight' => 'the right person.',
			'lede' => 'Start here.', 'cta_primary' => 'Email us', 'cta_primary_href' => '#',
			'cta_secondary' => 'Call us', 'cta_secondary_href' => '#',
			'image' => '', 'image_alt' => '', 'image_caption' => '',
		) );
		$this->assertStringContainsString( 'class="ch-hero"', $html );
		$this->assertStringNotContainsString( 'ch-hero__media', $html );
	}

	public function test_stat_strip_renders_each_stat(): void {
		$html = Blueworx_Clubhouse_Sections::stat_strip( array(
			array( 'value' => '900+', 'label' => 'Members' ),
			array( 'value' => '9', 'label' => 'Sports' ),
		) );
		$this->assertStringContainsString( 'class="ch-stats"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-stats__item' ) );
		$this->assertStringContainsString( '900+', $html );
	}

	public function test_output_is_escaped(): void {
		$html = Blueworx_Clubhouse_Sections::footer( array(
			'club_name'  => 'A & B <script>',
			'tagline'    => 'x',
			'socials'    => array(),
			'columns'    => array(),
			'newsletter' => array( 'heading' => 'h', 'lede' => 'l', 'placeholder' => 'p', 'cta' => 'Subscribe' ),
			'legal'      => array(),
		) );
		$this->assertStringContainsString( 'A &amp; B &lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_footer_renders_columns_socials_and_newsletter(): void {
		$html = Blueworx_Clubhouse_Sections::footer( array(
			'club_name'  => 'ClubHouse', 'tagline' => 'A home ground for every team.',
			'socials'    => array( 'Facebook', 'Instagram' ),
			'columns'    => array(
				array( 'title' => 'Club', 'links' => array( array( 'label' => 'About', 'href' => '?page=about' ) ) ),
			),
			'newsletter' => array( 'heading' => 'Stay in the loop', 'lede' => 'Club news, monthly.', 'placeholder' => 'Your email', 'cta' => 'Subscribe' ),
			'legal'      => array( array( 'label' => 'Privacy', 'href' => '#' ) ),
		) );
		$this->assertStringContainsString( 'class="ch-footer"', $html );
		$this->assertStringContainsString( 'ch-footer__social', $html );
		$this->assertStringContainsString( 'Stay in the loop', $html );
		$this->assertStringContainsString( 'Privacy', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_quick_tiles_render_each_link(): void {
		$html = Blueworx_Clubhouse_Sections::quick_tiles( array(
			array( 'label' => 'Membership', 'href' => '?page=membership' ),
			array( 'label' => 'Sports', 'href' => '?page=sports' ),
		) );
		$this->assertStringContainsString( 'class="ch-tiles"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-tiles__tile' ) );
		$this->assertStringContainsString( 'Membership', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_ticker_repeats_items_for_marquee(): void {
		$html = Blueworx_Clubhouse_Sections::ticker( array( 'News one', 'News two' ) );
		$this->assertStringContainsString( 'class="ch-ticker"', $html );
		$this->assertStringContainsString( 'News one', $html );
		// The track is duplicated so the CSS marquee loops seamlessly.
		$this->assertSame( 2, substr_count( $html, 'ch-ticker__track' ) );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_card_grid_renders_head_and_overlay_cards(): void {
		$html = Blueworx_Clubhouse_Sections::card_grid( array(
			'eyebrow'    => 'Our sports',
			'heading'    => 'Pick your game.',
			'link_label' => 'All sections →',
			'link_href'  => '?page=sports',
			'cards'      => array(
				array( 'image' => '', 'image_alt' => '', 'tag' => 'Sat', 'title' => 'Rugby', 'subtitle' => 'Senior · colts · touch' ),
				array( 'image' => '', 'image_alt' => '', 'tag' => 'Daily', 'title' => 'Tennis', 'subtitle' => 'Four courts · coaching' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-cards"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-card"' ) );
		$this->assertStringContainsString( 'Pick your game.', $html );
		$this->assertStringContainsString( 'Rugby', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_image_band_renders_overlay_heading_and_cta(): void {
		$html = Blueworx_Clubhouse_Sections::image_band( array(
			'eyebrow'   => 'The clubhouse',
			'heading'   => 'A home ground for every team',
			'image'     => '', 'image_alt' => '',
			'cta_label' => 'Visit us', 'cta_href' => '?page=contact',
		) );
		$this->assertStringContainsString( 'class="ch-band-img"', $html );
		$this->assertStringContainsString( 'A home ground for every team', $html );
		$this->assertStringContainsString( 'Visit us', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_no_colour_literals_leak_into_markup(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow' => 'e', 'title_lead' => 't ', 'title_highlight' => 'h',
			'lede' => 'l', 'cta_primary' => 'a', 'cta_secondary' => 'b',
			'cta_primary_href' => '', 'cta_secondary_href' => '',
			'image' => '', 'image_alt' => '', 'image_caption' => '',
		) );
		// Skin-agnostic: sections must not hard-code colours or inline styles.
		// (href="#" is fine — we forbid hex colours and style attributes.)
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_band_accent_variant_renders_modifier(): void {
		$html = Blueworx_Clubhouse_Sections::band( array(
			'variant' => 'accent', 'eyebrow' => 'Membership',
			'heading' => 'Open to everyone, from £28/month.',
			'lede' => 'Every tier includes clubhouse access.',
			'cta_label' => 'Choose your tier', 'cta_href' => '?page=membership',
		) );
		$this->assertStringContainsString( 'ch-band--accent', $html );
		$this->assertStringContainsString( 'Open to everyone', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_tier_grid_marks_recommended_and_lists_features(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( array(
			array( 'eyebrow' => 'Full playing', 'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
				'features' => array( 'Any section', 'League affiliation' ), 'recommended' => false,
				'cta_label' => 'Join', 'cta_href' => '?page=membership' ),
			array( 'eyebrow' => 'Best value', 'name' => 'Family', 'price' => '£45', 'period' => '/mo',
				'features' => array( 'Up to 5 members' ), 'recommended' => true,
				'cta_label' => 'Join', 'cta_href' => '?page=membership' ),
		) );
		$this->assertSame( 2, substr_count( $html, 'ch-tier"' ) + substr_count( $html, 'ch-tier ch-tier--pop"' ) );
		$this->assertStringContainsString( 'ch-tier--pop', $html );
		$this->assertStringContainsString( 'Any section', $html );
		$this->assertNoHexColour( $html );
	}

	public function test_activity_tabs_render_all_three_panels(): void {
		$html = Blueworx_Clubhouse_Sections::activity_tabs( array(
			'eyebrow'  => 'Club activity',
			'heading'  => "What\u{2019}s happening",
			'fixtures' => array( array( 'month' => 'JUL', 'day' => '12', 'competition' => 'Rugby · 1st XV', 'time' => '14:00', 'matchup' => 'ClubHouse vs Riverside' ) ),
			'results'  => array( array( 'date' => 'JUL 5', 'home' => 'ClubHouse 1st XI', 'away' => 'Hartfield', 'score' => '+34', 'outcome' => 'W' ) ),
			'events'   => array( array( 'tag' => 'Open day', 'date' => 'Sat 26 Jul', 'title' => 'Club Open Day', 'detail' => '10:00–14:00' ) ),
		) );
		$this->assertStringContainsString( 'class="ch-tabs"', $html );
		$this->assertStringContainsString( 'data-ch-tab="fixtures"', $html );
		$this->assertStringContainsString( 'data-ch-tab="results"', $html );
		$this->assertStringContainsString( 'data-ch-tab="events"', $html );
		$this->assertStringContainsString( 'ClubHouse vs Riverside', $html );
		$this->assertStringContainsString( 'ch-badge--w', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_activity_result_badge_maps_each_outcome(): void {
		$mk = static function ( string $outcome ): string {
			return Blueworx_Clubhouse_Sections::activity_tabs( array(
				'eyebrow'  => 'x', 'heading' => 'x',
				'fixtures' => array(),
				'results'  => array( array( 'date' => 'JUL 1', 'home' => 'A', 'away' => 'B', 'score' => '1-0', 'outcome' => $outcome ) ),
				'events'   => array(),
			) );
		};
		$this->assertStringContainsString( 'ch-badge--w', $mk( 'W' ) );
		$this->assertStringContainsString( 'ch-badge--l', $mk( 'L' ) );
		$this->assertStringContainsString( 'ch-badge--d', $mk( 'D' ) );
		// Unknown outcome falls back to the draw modifier.
		$this->assertStringContainsString( 'ch-badge--d', $mk( 'X' ) );
	}

	public function test_news_cards_render_each_post(): void {
		$html = Blueworx_Clubhouse_Sections::news_cards( array(
			'eyebrow' => 'Latest news', 'heading' => 'From the clubhouse',
			'cards'   => array(
				array( 'image' => '', 'image_alt' => '', 'tag' => 'Club news', 'date' => '2 Jul', 'title' => 'Refurbishment complete' ),
				array( 'image' => '', 'image_alt' => '', 'tag' => 'Sections', 'date' => '28 Jun', 'title' => '40 new players' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-news"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-news__card' ) );
		$this->assertStringContainsString( 'From the clubhouse', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_info_strip_renders_columns_and_optional_link(): void {
		$html = Blueworx_Clubhouse_Sections::info_strip( array(
			array( 'label' => 'Location', 'lines' => array( '12 Riverside Lane', 'Marlow' ), 'link_label' => '', 'link_href' => '' ),
			array( 'label' => 'Find us', 'lines' => array(), 'link_label' => 'Open in Maps', 'link_href' => '#' ),
		) );
		$this->assertStringContainsString( 'class="ch-info"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-info__col' ) );
		$this->assertStringContainsString( '12 Riverside Lane', $html );
		$this->assertStringContainsString( 'Open in Maps', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_sponsors_render_each_tile(): void {
		$html = Blueworx_Clubhouse_Sections::sponsors( array(
			'heading' => 'Our sponsors & partners', 'link_label' => 'Become a sponsor', 'link_href' => '#',
			'names'   => array( 'Sponsor 01', 'Sponsor 02', 'Sponsor 03' ),
		) );
		$this->assertStringContainsString( 'class="ch-sponsors"', $html );
		$this->assertSame( 3, substr_count( $html, 'ch-sponsors__tile' ) );
		$this->assertStringContainsString( 'Become a sponsor', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_benefit_grid_renders_each_card(): void {
		$html = Blueworx_Clubhouse_Sections::benefit_grid( array(
			'eyebrow' => 'Why join', 'heading' => 'More than a membership',
			'cards'   => array(
				array( 'title' => 'All training included', 'description' => 'Every session, all season.' ),
				array( 'title' => 'Discounted events', 'description' => 'Members save on tournaments.' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-benefits"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-benefit"' ) );
		$this->assertStringContainsString( 'All training included', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_people_grid_renders_optional_email(): void {
		$html = Blueworx_Clubhouse_Sections::people_grid( array(
			'eyebrow' => 'Who to contact', 'heading' => 'The directory',
			'people'  => array(
				array( 'name' => 'Priya Nair', 'role' => 'Chair', 'email' => '' ),
				array( 'name' => 'Daniel Reed', 'role' => 'Membership', 'email' => 'membership@clubhouse.example' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-people"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-person"' ) );
		$this->assertStringContainsString( 'Priya Nair', $html );
		$this->assertStringContainsString( 'mailto:membership@clubhouse.example', $html );
		$this->assertSame( 1, substr_count( $html, 'ch-person__email' ) ); // only the one with an email
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_timeline_renders_each_milestone(): void {
		$html = Blueworx_Clubhouse_Sections::timeline( array(
			'eyebrow' => 'Our story', 'heading' => 'From one pitch to nine sports',
			'milestones' => array(
				array( 'year' => '1974', 'title' => 'One pitch, one team', 'desc' => 'A handful of players lease a field.' ),
				array( 'year' => '2024', 'title' => 'A modern home', 'desc' => 'A full clubhouse refurbishment.' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-timeline"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-milestone"' ) );
		$this->assertStringContainsString( '1974', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_list_split_renders_three_columns(): void {
		$html = Blueworx_Clubhouse_Sections::list_split( array(
			'eyebrow' => 'The detail', 'heading' => 'What is included',
			'included'     => array( 'All training', 'Match fees' ),
			'not_included' => array( 'Individual coaching' ),
			'policies'     => array( array( 'title' => 'Free trial', 'desc' => 'Your first session is on us.' ) ),
		) );
		$this->assertStringContainsString( 'class="ch-splits"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-split__yes' ) );
		$this->assertSame( 1, substr_count( $html, 'ch-split__no' ) );
		$this->assertStringContainsString( 'Free trial', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_step_grid_renders_numbered_steps(): void {
		$html = Blueworx_Clubhouse_Sections::step_grid( array(
			'eyebrow' => 'How to join', 'heading' => 'Four steps to playing',
			'steps'   => array(
				array( 'number' => '01', 'title' => 'Pick your section', 'description' => 'Find where you fit.' ),
				array( 'number' => '02', 'title' => 'Choose a tier', 'description' => 'Adult, family or junior.' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-steps"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-step"' ) );
		$this->assertStringContainsString( 'Pick your section', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_faq_renders_details_and_marks_open(): void {
		$html = Blueworx_Clubhouse_Sections::faq( array(
			'eyebrow' => 'Questions', 'heading' => 'Frequently asked',
			'items'   => array(
				array( 'question' => 'Do I have to commit?', 'answer' => 'No, join any time.', 'open' => true ),
				array( 'question' => 'Can I try first?', 'answer' => 'Yes, free trial.', 'open' => false ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-faq"', $html );
		$this->assertSame( 2, substr_count( $html, '<details class="ch-faq__item"' ) );
		$this->assertSame( 1, substr_count( $html, '<details class="ch-faq__item" open>' ) );
		$this->assertStringContainsString( 'Do I have to commit?', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_contact_form_renders_fields_select_and_info(): void {
		$html = Blueworx_Clubhouse_Sections::contact_form( array(
			'eyebrow' => 'Get in touch', 'heading' => 'Send us a message',
			'name_label' => 'Full name', 'email_label' => 'Email',
			'enquiry_label' => 'Enquiry type', 'enquiry_options' => array( 'General', 'Membership' ),
			'message_label' => 'Message', 'submit_label' => 'Send message',
			'info' => array(
				'heading' => 'Find us', 'address' => array( '12 Riverside Lane', 'Marlow' ),
				'email' => 'hello@clubhouse.example', 'phone' => '01628 000 000',
				'socials' => array( 'Facebook', 'Instagram' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-contact"', $html );
		$this->assertStringContainsString( 'onsubmit="return false"', $html );
		$this->assertSame( 2, substr_count( $html, '<option' ) );
		$this->assertStringContainsString( 'mailto:hello@clubhouse.example', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-contact__social' ) );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
}
