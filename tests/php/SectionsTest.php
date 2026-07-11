<?php
// tests/php/SectionsTest.php

use PHPUnit\Framework\TestCase;

final class SectionsTest extends TestCase {

	/** No raw hex colour literal in markup — but ignore HTML numeric entities like &#039;. */
	private function assertNoHexColour( string $html ): void {
		$stripped = preg_replace( '/&#\d+;/', '', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $stripped );
	}

	/**
	 * Grids of repeated peer items must expose list semantics — `role="list"` on the
	 * container(s), `role="listitem"` on each child. WebKit drops the implicit list role
	 * when `list-style:none` is set, so this is the fix for `<ul>` grids too. The two
	 * markers are distinct substrings (`role="list"` never matches inside `role="listitem"`).
	 */
	private function assertListSemantics( string $html, int $lists, int $items ): void {
		$this->assertSame( $lists, substr_count( $html, 'role="list"' ), 'role="list" container count' );
		$this->assertSame( $items, substr_count( $html, 'role="listitem"' ), 'role="listitem" item count' );
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
		// Skip link is the first focusable element and targets the main landmark.
		$this->assertStringContainsString( '<a class="ch-skip" href="#ch-main">', $html );
		$this->assertSame( 0, strpos( $html, '<a class="ch-skip"' ) );
		// No-JS mobile disclosure carries the same links so navigation survives below 900px.
		$this->assertStringContainsString( 'ch-nav__disc', $html );
		$this->assertStringContainsString( 'ch-nav__burger', $html );
		$this->assertStringContainsString( 'ch-nav__drawer', $html );
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
		$this->assertListSemantics( $html, 1, 2 );
		$this->assertStringContainsString( '900+', $html );
	}

	public function test_stat_strip_marks_featured_by_data_not_position(): void {
		$html = Blueworx_Clubhouse_Sections::stat_strip( array(
			array( 'value' => '900+', 'label' => 'Members', 'featured' => true ),
			array( 'value' => '9', 'label' => 'Sports' ),
		) );
		// The emphasis is data-driven: exactly the flagged stat gets the modifier.
		$this->assertSame( 1, substr_count( $html, 'ch-stats__item--feature' ) );
		$this->assertStringContainsString( 'ch-stats__item--feature" role="listitem"><b class="ch-stats__value">900+', $html );
		$this->assertStringNotContainsString( 'style=', $html );
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
		$this->assertListSemantics( $html, 1, 2 );
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
		// Accessible, no-JS pause control (WCAG 2.2.2) — a labelled checkbox toggle.
		$this->assertStringContainsString( 'ch-ticker__pause-cb', $html );
		$this->assertStringContainsString( 'aria-label="Pause the news ticker"', $html );
		$this->assertStringContainsString( '<label class="ch-ticker__pause" for="ch-ticker-pause">', $html );
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
		$this->assertListSemantics( $html, 1, 2 );
		$this->assertStringContainsString( 'Pick your game.', $html );
		$this->assertStringContainsString( 'Rugby', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_stat_card_grid_renders_cards_with_chip_and_stats(): void {
		$html = Blueworx_Clubhouse_Sections::stat_card_grid( array(
			'eyebrow'    => 'All sections',
			'heading'    => 'Pick your sport.',
			'link_label' => 'Join the club →',
			'link_href'  => '?page=membership',
			'cards'      => array(
				array( 'image' => '', 'image_alt' => 'Rugby', 'chip' => 'Sat', 'title' => 'Rugby',
					'description' => 'Senior, colts and touch rugby.',
					'stats' => array( array( 'value' => '4', 'label' => 'Teams' ), array( 'value' => '120', 'label' => 'Players' ) ) ),
				array( 'image' => '', 'image_alt' => 'Tennis', 'chip' => 'Daily', 'title' => 'Tennis',
					'description' => 'Four courts, coaching for all ages.',
					'stats' => array( array( 'value' => '4', 'label' => 'Courts' ) ) ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-scards"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-scard"' ) );
		$this->assertStringContainsString( 'ch-scard__chip', $html );
		$this->assertStringContainsString( 'Senior, colts and touch rugby.', $html );
		// 3 stat pairs total across the two cards.
		$this->assertSame( 3, substr_count( $html, 'ch-scard__stat"' ) );
		// The card grid is the only list; stat rows are inline metrics, not list items.
		$this->assertListSemantics( $html, 1, 2 );
		$this->assertStringContainsString( 'Join the club', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_stat_card_grid_omits_head_link_when_label_empty(): void {
		$html = Blueworx_Clubhouse_Sections::stat_card_grid( array(
			'eyebrow' => 'x', 'heading' => 'y', 'link_label' => '', 'link_href' => '',
			'cards'   => array( array( 'image' => '', 'image_alt' => '', 'chip' => 'c', 'title' => 't', 'description' => 'd', 'stats' => array() ) ),
		) );
		$this->assertStringNotContainsString( 'ch-sec__head', $html );
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
		// 1 tier grid + 2 feature lists; 2 tier cards + 3 feature items (2 + 1).
		$this->assertListSemantics( $html, 3, 5 );
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
		// Fixtures / results / events are three lists of one item each.
		$this->assertListSemantics( $html, 3, 3 );
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
		$this->assertListSemantics( $html, 1, 2 );
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
		$this->assertListSemantics( $html, 1, 2 );
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
		$this->assertListSemantics( $html, 1, 3 );
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
		$this->assertListSemantics( $html, 1, 2 );
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
		$this->assertListSemantics( $html, 1, 2 );
		$this->assertStringContainsString( 'Priya Nair', $html );
		$this->assertStringContainsString( 'mailto:membership@clubhouse.example', $html );
		$this->assertSame( 1, substr_count( $html, 'ch-person__email' ) ); // only the one with an email
		// Photo-less avatars degrade to first+last initials, not an empty grey box.
		$this->assertSame( 2, substr_count( $html, 'ch-avatar' ) );
		$this->assertStringContainsString( '<div class="ch-person__avatar ch-avatar" aria-hidden="true">PN</div>', $html );
		$this->assertStringContainsString( '<div class="ch-person__avatar ch-avatar" aria-hidden="true">DR</div>', $html );
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
		$this->assertListSemantics( $html, 1, 2 );
		$this->assertStringContainsString( '1974', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_list_split_renders_three_columns(): void {
		$html = Blueworx_Clubhouse_Sections::list_split( array(
			'eyebrow' => 'The detail', 'heading' => 'What is included',
			'included_label' => 'Included', 'not_included_label' => 'Not included', 'policies_label' => 'Good to know',
			'included'     => array( 'All training', 'Match fees' ),
			'not_included' => array( 'Individual coaching' ),
			'policies'     => array( array( 'title' => 'Free trial', 'desc' => 'Your first session is on us.' ) ),
		) );
		$this->assertStringContainsString( 'class="ch-splits"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-split__yes' ) );
		$this->assertSame( 1, substr_count( $html, 'ch-split__no' ) );
		// 2 include/exclude lists + 1 policies list; 2 + 1 items + 1 policy.
		$this->assertListSemantics( $html, 3, 4 );
		$this->assertStringContainsString( 'Free trial', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_list_split_column_headers_come_from_data(): void {
		$html = Blueworx_Clubhouse_Sections::list_split( array(
			'eyebrow' => 'Le détail', 'heading' => 'Ce qui est inclus',
			'included_label' => 'Inclus', 'not_included_label' => 'Non inclus', 'policies_label' => 'Bon à savoir',
			'included'     => array( 'Tout l\'entraînement' ),
			'not_included' => array( 'Coaching individuel' ),
			'policies'     => array(),
		) );
		$this->assertStringContainsString( 'Inclus', $html );
		$this->assertStringContainsString( 'Non inclus', $html );
		$this->assertStringContainsString( 'Bon à savoir', $html );
		// No English column headers are baked into the renderer.
		$this->assertStringNotContainsString( '>Included<', $html );
		$this->assertStringNotContainsString( '>Good to know<', $html );
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
		$this->assertListSemantics( $html, 1, 2 );
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
		// tel: href strips whitespace so it dials; the visible number keeps its spacing.
		$this->assertStringContainsString( 'href="tel:01628000000"', $html );
		$this->assertStringNotContainsString( 'tel:01628 000 000', $html );
		$this->assertStringContainsString( '01628 000 000', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-contact__social' ) );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_auth_renders_login_card_with_fields_and_join_link(): void {
		$html = Blueworx_Clubhouse_Sections::auth( array(
			'eyebrow'        => 'Members',
			'heading'        => 'Log in to your account',
			'lede'           => 'Access your membership.',
			'email_label'    => 'Email',
			'password_label' => 'Password',
			'remember_label' => 'Remember me',
			'forgot_label'   => 'Forgot password?',
			'forgot_href'    => '#',
			'submit_label'   => 'Log in',
			'join_prompt'    => 'Not a member yet?',
			'join_label'     => 'Join the club',
			'join_href'      => '?page=membership',
		) );
		$this->assertStringContainsString( 'class="ch-auth"', $html );
		// The card carries the page's main heading (no hero on the login page).
		$this->assertStringContainsString( '<h1 class="ch-auth__title">Log in to your account</h1>', $html );
		$this->assertStringContainsString( 'type="email"', $html );
		$this->assertStringContainsString( 'type="password"', $html );
		$this->assertStringContainsString( 'autocomplete="current-password"', $html );
		$this->assertStringContainsString( 'onsubmit="return false"', $html );
		$this->assertStringContainsString( 'href="?page=membership"', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_hero_filter_renders_title_lede_and_filter_pills(): void {
		$html = Blueworx_Clubhouse_Sections::hero_filter( array(
			'eyebrow'         => 'Our sports',
			'title_lead'      => 'Nine sports, ',
			'title_highlight' => 'one club.',
			'lede'            => 'Find your section and get playing.',
			'filter_label'    => 'Filter by sport',
			'filters'         => array(
				array( 'label' => 'All', 'href' => '?page=sports', 'active' => true ),
				array( 'label' => 'Rugby', 'href' => '?page=sports', 'active' => false ),
				array( 'label' => 'Tennis', 'href' => '?page=sports', 'active' => false ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-hero-f"', $html );
		$this->assertStringContainsString( 'class="ch-hero-f__hl"', $html );
		$this->assertStringContainsString( 'Find your section', $html );
		// Filter bar is a nav landmark (not a list), label-driven, active pill flagged.
		$this->assertStringContainsString( '<nav class="ch-filters" aria-label="Filter by sport">', $html );
		// 4 = the nav's own class="ch-filters" (a substring match on "ch-filter") + the 3 pills.
		$this->assertSame( 4, substr_count( $html, 'class="ch-filter' ) );
		$this->assertSame( 1, substr_count( $html, 'ch-filter--on' ) );
		$this->assertStringContainsString( 'Rugby', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
}
