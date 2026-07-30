<?php
// tests/php/ContentCatalogueTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ContentCatalogueTest extends TestCase {

	/**
	 * Ten tabs over nine pages: the sitewide header and footer get their own
	 * "Global" tab, so editing the Home hero is not presented as a sitewide change.
	 * Both Global and Home map onto Visibility's single 'home' page.
	 */
	public function test_returns_tabs_in_page_map_order_with_global_split_from_home(): void {
		$tabs = array_column( Blueworx_Clubhouse_Content_Catalogue::pages(), 'tab' );
		$this->assertSame(
			array( 'global', 'home', 'about', 'membership', 'contact', 'login', 'sports', 'teams', 'events', 'calendar' ),
			$tabs
		);
	}

	/**
	 * Lockstep: every catalogue section key must exist in the visibility inventory
	 * for the same page, and vice-versa. Compared as a union per visibility page,
	 * because Global and Home are two tabs over one page — between them they must
	 * still account for exactly the inventory's keys, no more and no fewer.
	 */
	public function test_section_keys_match_visibility_inventory_exactly(): void {
		$inv = array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $p ) {
			$inv[ $p['page'] ] = array_column( $p['sections'], 'key' );
			sort( $inv[ $p['page'] ] );
		}
		$seen = array();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$vis_page = 'global' === $page['tab'] ? 'home' : $page['tab'];
			foreach ( $page['sections'] as $s ) {
				$seen[ $vis_page ][] = $s['key'];
			}
		}
		$this->assertSame( array_keys( $inv ), array_keys( $seen ), 'a visibility page has no catalogue tab, or vice-versa' );
		foreach ( $seen as $vis_page => $keys ) {
			sort( $keys );
			$this->assertSame( $inv[ $vis_page ], $keys, "Section keys diverge for {$vis_page}" );
		}
	}

	public function test_every_section_has_a_valid_type(): void {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $s ) {
				$this->assertContains( $s['type'], array( 'fields', 'loop', 'linkout', 'auto' ), $s['key'] );
				if ( 'loop' === $s['type'] ) {
					$this->assertNotEmpty( $s['loop']['fields'] );
				}
			}
		}
	}

	public function test_cpt_linkouts_reference_real_post_types(): void {
		// Blueworx_Clubhouse_Collection_Types::POST_TYPES is a numeric-indexed list
		// whose VALUES are the post-type slugs — assert membership directly, no array_keys.
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $s ) {
				if ( 'linkout' === $s['type'] && 'cpt' === $s['link']['kind'] ) {
					$this->assertContains( $s['link']['cpt'], Blueworx_Clubhouse_Collection_Types::POST_TYPES, $s['key'] );
				}
			}
		}
	}

	public function test_editable_divergences_are_loops_not_auto(): void {
		$byKey = array();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			if ( 'home' !== $page['tab'] ) {
				continue;
			}
			foreach ( $page['sections'] as $s ) { $byKey[ $s['key'] ] = $s['type']; }
		}
		$this->assertSame( 'loop', $byKey['ticker'] );
		$this->assertSame( 'loop', $byKey['info'] );
		$this->assertSame( 'auto', $byKey['activity'] ); // genuinely derived stays auto
	}

	/** The Global tab is the sitewide chrome only — every Home section moved to its own tab. */
	public function test_global_tab_holds_only_sitewide_sections(): void {
		$pages = Blueworx_Clubhouse_Content_Catalogue::pages();
		$global = array_values( array_filter( $pages, static fn( $p ) => 'global' === $p['tab'] ) )[0];
		$this->assertSame( array( 'header', 'footer' ), array_map( static fn( $s ) => $s['key'], $global['sections'] ) );
		foreach ( $global['sections'] as $s ) {
			$this->assertSame( 'global', $s['store_page'], $s['key'] );
		}
	}

	/** Every section on the Home tab stores against the home page. */
	public function test_home_tab_sections_all_store_against_home(): void {
		$pages = Blueworx_Clubhouse_Content_Catalogue::pages();
		$home  = array_values( array_filter( $pages, static fn( $p ) => 'home' === $p['tab'] ) )[0];
		$this->assertNotSame( array(), $home['sections'] );
		foreach ( $home['sections'] as $s ) {
			$this->assertSame( 'home', $s['store_page'], $s['key'] );
		}
	}

	/**
	 * Guard against the "honest catalogue" defect: a handful of sections render via
	 * a Sections method whose signature accepts a narrower field set than what the
	 * catalogue used to declare (e.g. about.history offered `body`/`image` into
	 * Sections::timeline(), which has no such inputs — editing them did nothing).
	 * This pins each affected section's field keys to exactly what its renderer
	 * consumes, so a future field can't be re-added there without also updating
	 * the Page_Renderer wiring (and this test).
	 *
	 * @return array<string,array{0:string,1:string,2:array<int,string>}> [tab, section key, expected field keys]
	 */
	private function fieldKeysByTabAndSection( string $tab, string $key ): array {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			if ( $page['tab'] !== $tab ) {
				continue;
			}
			foreach ( $page['sections'] as $s ) {
				if ( $s['key'] === $key ) {
					return array_column( $s['fields'], 'key' );
				}
			}
		}
		$this->fail( "No section '{$key}' on tab '{$tab}'" );
	}

	public function test_global_header_exposes_announcement_bar_fields(): void {
		$fields = array();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			if ( 'global' !== $page['tab'] ) { continue; }
			foreach ( $page['sections'] as $s ) {
				if ( 'header' === $s['key'] ) { $fields = $s['fields']; }
			}
		}
		$byKey = array();
		foreach ( $fields as $f ) { $byKey[ $f['key'] ] = $f['type']; }
		$this->assertSame( 'toggle', $byKey['banner_show'] ?? null );
		$this->assertSame( 'text', $byKey['banner'] ?? null );
		$this->assertSame( 'url', $byKey['banner_href'] ?? null );
	}

	public function test_narrowed_sections_declare_only_renderer_consumable_fields(): void {
		// about.history -> Sections::timeline(): heading is the only non-loop field
		// (the milestones are an editable loop, asserted separately by the renderer tests).
		$this->assertSame( array( 'heading' ), $this->fieldKeysByTabAndSection( 'about', 'history' ) );

		// about.facilities -> Sections::image_band( eyebrow, heading, image, image_alt, cta_label, cta_href ):
		// reshaped from a loop to the band's own fields.
		$this->assertSame(
			array( 'eyebrow', 'heading', 'image', 'cta_label', 'cta_href' ),
			$this->fieldKeysByTabAndSection( 'about', 'facilities' )
		);

		// contact.form -> Sections::contact_form(): intro/submissions_email/success_message never existed there.
		// The info_* fields do: the address, email and phone beside the form were
		// hard-coded demo values ("12 Riverside Lane, Marlow") that no club could
		// change, so they are now content like everything else.
		$this->assertSame(
			array( 'eyebrow', 'heading', 'submit_label', 'info_heading', 'address', 'email', 'phone', 'map_image' ),
			$this->fieldKeysByTabAndSection( 'contact', 'form' )
		);

		// login.form -> Sections::auth() has no support_email input.
		$this->assertSame( array( 'heading', 'lede' ), $this->fieldKeysByTabAndSection( 'login', 'form' ) );

		// sports/teams/events/calendar heroes -> Sections::hero_filter(): no CTA or image inputs.
		foreach ( array( 'sports', 'teams', 'events', 'calendar' ) as $tab ) {
			$this->assertSame(
				array( 'eyebrow', 'title_lead', 'title_highlight', 'lede' ),
				$this->fieldKeysByTabAndSection( $tab, 'hero' ),
				"hero fields diverge for {$tab}"
			);
		}
	}

	public function test_index_keys_by_store_page_and_section(): void {
		$index = Blueworx_Clubhouse_Content_Catalogue::index();
		$this->assertArrayHasKey( 'home/hero', $index );
		// The Home hero lives on the Home tab, not Global — Global is header/footer only.
		$this->assertSame( 'home', $index['home/hero']['tab'] );
		$this->assertSame( 'Home', $index['home/hero']['tab_label'] );
		$this->assertSame( 'Hero', $index['home/hero']['section_label'] );
	}

	public function test_index_uses_store_page_not_tab_for_global_sections(): void {
		$index = Blueworx_Clubhouse_Content_Catalogue::index();
		// The Global tab's Header stores under the 'global' store_page.
		$this->assertArrayHasKey( 'global/header', $index );
		$this->assertSame( 'global', $index['global/header']['tab'] );
		$this->assertSame( 'Header', $index['global/header']['section_label'] );
	}

	public function test_index_covers_every_catalogue_section(): void {
		$expected = 0;
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$expected += count( $page['sections'] );
		}
		$this->assertCount( $expected, Blueworx_Clubhouse_Content_Catalogue::index() );
	}

	public function test_address_label_names_a_section_for_a_human(): void {
		$this->assertSame( 'Home · Hero', Blueworx_Clubhouse_Content_Catalogue::address_label( 'home/hero' ) );
		$this->assertSame( 'Global · Header', Blueworx_Clubhouse_Content_Catalogue::address_label( 'global/header' ) );
		$this->assertSame( 'Membership · FAQ', Blueworx_Clubhouse_Content_Catalogue::address_label( 'membership/faq' ) );
	}

	public function test_address_label_falls_back_to_the_raw_address(): void {
		$this->assertSame( 'ghost/gone', Blueworx_Clubhouse_Content_Catalogue::address_label( 'ghost/gone' ) );
	}
}
