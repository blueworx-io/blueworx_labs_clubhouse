<?php
// tests/php/PageFieldsSectionsTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * Page_Fields::sections() is the flat view of the fifteen editors that
 * everything outside the editors reads: the import's allow-list, the prompt it
 * writes for an AI, the sections an import switches on and off, and the menu's
 * list of anchors. These pin the shape and the content of that view.
 */
final class PageFieldsSectionsTest extends TestCase {

	// These describe a club with everything installed. Signing in and the member
	// area belong to the shop, so without one this site has neither page and the
	// lists below would be a different site's.
	protected function setUp(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		Blueworx_Clubhouse_Page_Fields::forget();
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_Page_Fields::forget();
	}

	/** @return array<string,mixed> */
	private function section( string $address ): array {
		$sections = Blueworx_Clubhouse_Page_Fields::sections();
		$this->assertArrayHasKey( $address, $sections );
		return $sections[ $address ];
	}

	/**
	 * Fourteen areas over nine pages: the sitewide header and footer get their
	 * own "Global content" screen, so editing the Home hero is not presented as
	 * a sitewide change. Both Global and Home map onto Visibility's single
	 * 'home' page. Bookings is absent without LatePoint, which this suite does
	 * not install.
	 */
	public function test_areas_are_declared_in_page_map_order_with_global_split_from_home(): void {
		$this->assertSame(
			array( 'global', 'home', 'about', 'membership', 'contact', 'login', 'news', 'sports', 'teams', 'events', 'calendar', 'privacy', 'terms', 'rules' ),
			array_keys( Blueworx_Clubhouse_Page_Fields::areas() )
		);
	}

	/**
	 * Lockstep: every declared section key must exist in the visibility
	 * inventory for the same page, and vice-versa. Compared as a union per
	 * visibility page, because Global and Home are two screens over one page —
	 * between them they must still account for exactly the inventory's keys, no
	 * more and no fewer.
	 */
	public function test_section_keys_match_visibility_inventory_exactly(): void {
		$inv = array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $p ) {
			// A page with no sections has nothing to hold in lockstep — the member
			// area is one: it carries a visibility switch, but every panel on it
			// belongs to the shop or the booking plugin, so there is nothing of
			// the club's to edit and no editor area to match.
			if ( array() === $p['sections'] ) {
				continue;
			}
			$inv[ $p['page'] ] = array_column( $p['sections'], 'key' );
			sort( $inv[ $p['page'] ] );
		}
		$seen = array();
		foreach ( Blueworx_Clubhouse_Page_Fields::sections() as $section ) {
			$vis_page            = 'global' === $section['area'] ? 'home' : $section['area'];
			$seen[ $vis_page ][] = $section['section'];
		}
		$this->assertSame( array_keys( $inv ), array_keys( $seen ), 'a visibility page has no editor area, or vice-versa' );
		foreach ( $seen as $vis_page => $keys ) {
			sort( $keys );
			$this->assertSame( $inv[ $vis_page ], $keys, "Section keys diverge for {$vis_page}" );
		}
	}

	/**
	 * A section that shows a collection names a post type that exists. This is
	 * what the import reads to decide whether a file covered such a section, so
	 * a typo here would silently switch a working section off.
	 */
	public function test_collection_backed_sections_name_real_post_types(): void {
		// 'post' is WordPress's own type rather than a clubhouse collection:
		// club news is written as ordinary posts, so naming it is a real answer
		// and not a typo.
		$types = array_merge( Blueworx_Clubhouse_Collection_Types::POST_TYPES, array( 'post' ) );
		$named = 0;
		foreach ( Blueworx_Clubhouse_Page_Fields::sections() as $address => $section ) {
			if ( '' === $section['collection'] ) {
				continue;
			}
			++$named;
			$this->assertContains( $section['collection'], $types, $address );
		}
		$this->assertGreaterThan( 0, $named, 'no section names a collection at all' );
	}

	/**
	 * The sections an import can only judge by their collection. Pinned by
	 * address, because dropping one of these quietly turns "the file gave us
	 * sponsors" back into "the file said nothing about sponsors" — and the
	 * import then switches the section off.
	 */
	public function test_the_collection_backed_sections_are_the_ones_expected(): void {
		$named = array();
		foreach ( Blueworx_Clubhouse_Page_Fields::sections() as $address => $section ) {
			if ( '' !== $section['collection'] ) {
				$named[ $address ] = $section['collection'];
			}
		}
		$this->assertSame(
			array(
				'home/sports'         => 'clubhouse_sport',
				'home/activity'       => 'clubhouse_event',
				'home/sponsors'       => 'clubhouse_sponsor',
				'about/committee'     => 'clubhouse_person',
				'contact/directory'   => 'clubhouse_person',
				'news/featured'       => 'post',
				'news/posts'          => 'post',
				'sports/directory'    => 'clubhouse_sport',
				'teams/directory'     => 'clubhouse_team',
				'events/upcoming'     => 'clubhouse_event',
				'events/past'         => 'clubhouse_event',
				'calendar/schedule'   => 'clubhouse_fixture',
			),
			$named
		);
	}

	/** Genuinely editable lists stay editable; genuinely derived ones stay derived. */
	public function test_editable_divergences_are_lists_not_derived(): void {
		$this->assertNotNull( $this->section( 'home/ticker' )['items'] );
		$this->assertNotNull( $this->section( 'home/info' )['items'] );

		$activity = $this->section( 'home/activity' );
		$this->assertNull( $activity['items'] );
		$this->assertSame( array(), $activity['fields'] );
	}

	/**
	 * A switch's default has to say what the site actually does with it unset.
	 *
	 * These two default to on in Page_Renderer. The editing screen used not to
	 * know that, drew both as off on a site that had never touched them, and a
	 * save then wrote that back — one visit to the old Club Pages screen
	 * switched the cookie notice and the announcement bar off.
	 */
	public function test_the_switches_that_are_on_by_default_say_so(): void {
		$this->assertTrue( $this->section( 'global/header' )['fields']['banner_show']['default'] );
		$this->assertTrue( $this->section( 'global/cookies' )['fields']['show']['default'] );
	}

	/** Every switch declares one, so the screen never has to guess. */
	public function test_every_switch_declares_a_default(): void {
		foreach ( Blueworx_Clubhouse_Page_Fields::sections() as $address => $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				if ( 'toggle' !== $field['kind'] ) {
					continue;
				}
				$this->assertArrayHasKey( 'default', $field, $address . '/' . $key . ' has no declared default' );
			}
		}
	}

	/** The Global screen is the sitewide chrome only — every Home section has its own. */
	public function test_the_global_area_holds_only_sitewide_sections(): void {
		$keys = array();
		foreach ( Blueworx_Clubhouse_Page_Fields::sections() as $section ) {
			if ( 'global' === $section['area'] ) {
				$keys[] = $section['section'];
			}
		}
		// The welcome pack is sitewide in the same sense the others are: it
		// belongs to no clubhouse page, and renders on the shop's dashboard.
		$this->assertSame( array( 'header', 'footer', 'welcome', 'cookies' ), $keys );
	}

	/**
	 * Guard against the "honest editor" defect: a handful of sections render via
	 * a Sections method whose signature accepts a narrower field set than what
	 * the editor used to declare (e.g. about.history offered `body`/`image` into
	 * Sections::timeline(), which has no such inputs — editing them did
	 * nothing). This pins each affected section's field keys to exactly what its
	 * renderer consumes, so a future field can't be re-added there without also
	 * updating the Page_Renderer wiring (and this test).
	 */
	public function test_narrowed_sections_declare_only_renderer_consumable_fields(): void {
		// about.history -> Sections::timeline(): heading is the only field of its
		// own (the milestones are an editable list, asserted by the renderer tests).
		$this->assertSame( array( 'heading' ), array_keys( $this->section( 'about/history' )['fields'] ) );

		// about.facilities -> Sections::image_band(): reshaped from a list to the
		// band's own fields.
		$this->assertSame(
			array( 'eyebrow', 'heading', 'image', 'cta_label', 'cta_href' ),
			array_keys( $this->section( 'about/facilities' )['fields'] )
		);

		// contact.form -> Sections::contact_form(): intro/submissions_email/
		// success_message never existed there. The info_* fields do: the address,
		// email and phone beside the form were hard-coded demo values that no club
		// could change, so they are now content like everything else. 'shortcode'
		// holds the club's real form; the built-in fields it replaced posted
		// nowhere, so they were never editable content in the first place.
		$this->assertSame(
			array( 'eyebrow', 'heading', 'shortcode', 'submit_label', 'info_heading', 'address', 'email', 'phone', 'map_image' ),
			array_keys( $this->section( 'contact/form' )['fields'] )
		);

		// login.form -> Sections::auth() has no support_email input.
		$this->assertSame( array( 'heading', 'lede' ), array_keys( $this->section( 'login/form' )['fields'] ) );

		// sports/teams/events/calendar heroes -> Sections::hero_filter(): no CTA
		// or image inputs.
		foreach ( array( 'sports', 'teams', 'events', 'calendar' ) as $area ) {
			$this->assertSame(
				array( 'eyebrow', 'title_lead', 'title_highlight', 'lede' ),
				array_keys( $this->section( $area . '/hero' )['fields'] ),
				"hero fields diverge for {$area}"
			);
		}
	}

	public function test_sections_are_keyed_by_area_and_section(): void {
		$hero = $this->section( 'home/hero' );
		$this->assertSame( 'home', $hero['area'] );
		$this->assertSame( 'Home', $hero['area_label'] );
		$this->assertSame( 'hero', $hero['section'] );
		$this->assertSame( 'Hero', $hero['section_label'] );
	}

	/** A field's key is the bare one an import file uses, not the screen's own id. */
	public function test_fields_are_keyed_by_the_key_an_import_file_uses(): void {
		$fields = $this->section( 'home/hero' )['fields'];
		$this->assertArrayHasKey( 'eyebrow', $fields );
		$this->assertSame( 'hero_eyebrow', $fields['eyebrow']['id'] );
	}

	/** A repeating section's rows come back under `items`, never as a field. */
	public function test_a_repeating_section_carries_its_rows_under_items(): void {
		$tiers = $this->section( 'membership/tiers' );
		$this->assertArrayNotHasKey( 'items', $tiers['fields'] );
		$this->assertSame( 'repeater', $tiers['items']['kind'] );
		$this->assertContains( 'name', array_column( $tiers['items']['fields'], 'id' ) );
	}

	public function test_a_section_covers_every_panel_of_every_area(): void {
		$expected = 0;
		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $area ) {
			foreach ( $area['tabs'] as $tab ) {
				$expected += count( $tab['panels'] );
			}
		}
		$this->assertCount( $expected, Blueworx_Clubhouse_Page_Fields::sections() );
	}

	public function test_address_label_names_a_section_for_a_human(): void {
		$this->assertSame( 'Home · Hero', Blueworx_Clubhouse_Page_Fields::address_label( 'home/hero' ) );
		$this->assertSame( 'Global content · Header', Blueworx_Clubhouse_Page_Fields::address_label( 'global/header' ) );
		$this->assertSame( 'Membership · FAQ', Blueworx_Clubhouse_Page_Fields::address_label( 'membership/faq' ) );
	}

	public function test_address_label_falls_back_to_the_raw_address(): void {
		$this->assertSame( 'ghost/gone', Blueworx_Clubhouse_Page_Fields::address_label( 'ghost/gone' ) );
	}
}
