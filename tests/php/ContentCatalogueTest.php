<?php
// tests/php/ContentCatalogueTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ContentCatalogueTest extends TestCase {

	public function test_returns_nine_pages_in_page_map_order(): void {
		$tabs = array_column( Blueworx_Clubhouse_Content_Catalogue::pages(), 'tab' );
		$this->assertSame(
			array( 'global', 'about', 'membership', 'contact', 'login', 'sports', 'teams', 'events', 'calendar' ),
			$tabs
		);
	}

	/** Lockstep: every catalogue section key must exist in the visibility inventory for the same page, and vice-versa. */
	public function test_section_keys_match_visibility_inventory_exactly(): void {
		$inv = array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $p ) {
			$inv[ $p['page'] ] = array_column( $p['sections'], 'key' );
			sort( $inv[ $p['page'] ] );
		}
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$vis_page = 'global' === $page['tab'] ? 'home' : $page['tab'];
			$keys     = array_map( static fn( $s ) => $s['key'], $page['sections'] );
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
		$global = Blueworx_Clubhouse_Content_Catalogue::pages()[0]['sections'];
		$byKey  = array();
		foreach ( $global as $s ) { $byKey[ $s['key'] ] = $s['type']; }
		$this->assertSame( 'loop', $byKey['ticker'] );
		$this->assertSame( 'loop', $byKey['stats'] );
		$this->assertSame( 'loop', $byKey['info'] );
		$this->assertSame( 'auto', $byKey['activity'] ); // genuinely derived stays auto
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
		// about.history -> Sections::timeline( eyebrow, heading, milestones ): only heading is editable.
		$this->assertSame( array( 'heading' ), $this->fieldKeysByTabAndSection( 'about', 'history' ) );

		// about.facilities -> Sections::image_band( eyebrow, heading, image, image_alt, cta_label, cta_href ):
		// reshaped from a loop to the band's own fields.
		$this->assertSame(
			array( 'eyebrow', 'heading', 'image', 'cta_label', 'cta_href' ),
			$this->fieldKeysByTabAndSection( 'about', 'facilities' )
		);

		// contact.form -> Sections::contact_form(): intro/submissions_email/success_message never existed there.
		$this->assertSame(
			array( 'eyebrow', 'heading', 'submit_label' ),
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
}
