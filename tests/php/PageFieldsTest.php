<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageFieldsTest extends TestCase {

	// A club with everything installed — areas() drops a whole area when its
	// integration is absent (Blueworx_Clubhouse_Page_Map::is_available()), the
	// same rule Content_Catalogue::pages() follows. Booking needs LatePoint and
	// Log in needs the shop; without both, this suite's own default state (no
	// detector, no shop) would silently drop them and every test below would be
	// counting a smaller site than the one it claims to.
	protected function setUp(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		Blueworx_Clubhouse_Integrations::set_detector(
			static fn( string $tag ): bool => Blueworx_Clubhouse_Integrations::LATEPOINT_TAG === $tag
		);
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_Integrations::set_detector( null );
	}

	public function test_every_club_page_and_global_has_an_area(): void {
		$areas = array_keys( Blueworx_Clubhouse_Page_Fields::areas() );
		sort( $areas );
		$expected = array( 'about', 'booking', 'calendar', 'contact', 'events', 'global', 'home', 'login', 'membership', 'news', 'privacy', 'rules', 'sports', 'teams', 'terms' );
		sort( $expected );
		$this->assertSame( $expected, $areas );
	}

	public function test_field_ids_are_unique_within_an_area(): void {
		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $key => $area ) {
			$ids = array();
			foreach ( $area['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					foreach ( $panel['fields'] as $field ) {
						$ids[] = $field['id'];
					}
					if ( ! empty( $panel['hideable'] ) ) {
						$ids[] = $panel['id'] . '__shown';
					}
				}
			}
			$this->assertSame( array_unique( $ids ), $ids, sprintf( 'The "%s" area has two fields with the same id. One would silently overwrite the other.', $key ) );
		}
	}

	public function test_every_field_id_is_its_section_and_key_joined(): void {
		$this->assertSame( 'hero_eyebrow', Blueworx_Clubhouse_Page_Fields::field_id( 'hero', 'eyebrow' ) );
	}

	public function test_every_kind_is_one_the_library_accepts(): void {
		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $area ) {
			foreach ( $area['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					foreach ( $panel['fields'] as $field ) {
						$this->assertContains( $field['kind'], \Blueworx\PageEditor\v1\Schema::KINDS, $field['id'] );
						foreach ( $field['fields'] ?? array() as $cell ) {
							$this->assertContains( $cell['kind'], \Blueworx\PageEditor\v1\Schema::REPEATER_KINDS, $field['id'] . '.' . $cell['id'] );
						}
					}
				}
			}
		}
	}

	/**
	 * The lockstep that proves this is a translation and not a rewrite. This is
	 * a permanent guard, not a temporary one: four other classes still read
	 * Content_Catalogue (Link_Catalogue, Import_Sections, Import_Prompt,
	 * Import_Parser), so it is not being deleted this phase. It is only
	 * retired alongside the catalogue itself, whenever that finally happens.
	 */
	public function test_every_catalogue_field_has_a_counterpart(): void {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$area = (string) $section['store_page'];
				$sec  = (string) $section['key'];
				foreach ( $section['fields'] ?? array() as $field ) {
					$this->assertNotSame(
						'',
						Blueworx_Clubhouse_Page_Fields::kind_of( $area, $sec, (string) $field['key'] ),
						sprintf( '%s/%s/%s is in the catalogue and not in Page_Fields.', $area, $sec, $field['key'] )
					);
				}
				if ( ! empty( $section['loop'] ) ) {
					$this->assertSame(
						'repeater',
						Blueworx_Clubhouse_Page_Fields::kind_of( $area, $sec, Blueworx_Clubhouse_Page_Fields::REPEATER_FIELD )
					);
				}
			}
		}
	}
}
