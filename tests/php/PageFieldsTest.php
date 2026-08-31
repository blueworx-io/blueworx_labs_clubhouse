<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageFieldsTest extends TestCase {

	// A club with everything installed — areas() drops a whole area when its
	// integration is absent (Blueworx_Clubhouse_Page_Map::is_available()), the
	// same rule the import and the menu follow. Booking needs LatePoint and
	// Log in needs the shop; without both, this suite's own default state (no
	// detector, no shop) would silently drop them and every test below would be
	// counting a smaller site than the one it claims to.
	protected function setUp(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		Blueworx_Clubhouse_Integrations::set_detector(
			static fn( string $tag ): bool => Blueworx_Clubhouse_Integrations::LATEPOINT_TAG === $tag
		);
		// areas() is memoised — this suite is the one thing in the codebase
		// that changes what is installed between cases, so it is also the one
		// thing that has to forget the cache each time, or a later case (or a
		// later suite, in the same PHPUnit process) reads this one's cached
		// availability instead of its own.
		Blueworx_Clubhouse_Page_Fields::forget();
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_Integrations::set_detector( null );
		Blueworx_Clubhouse_Page_Fields::forget();
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
	 * Every area, built into the smallest valid settings screen and run
	 * through the library's own validator. This is what would have caught a
	 * kind the browser cannot draw as a repeater row, or a field missing a
	 * label — the checks above only compare this class against itself, never
	 * against the library's own rules.
	 */
	public function test_every_area_validates_as_a_library_screen(): void {
		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $key => $area ) {
			\Blueworx\PageEditor\v1\Schema::validate( array(
				'slug'        => 'test-' . $key,
				'title'       => $area['label'],
				'store'       => 'option',
				'option_name' => 'test_' . $key,
				'tabs'        => $area['tabs'],
			) );
			$this->addToAssertionCount( 1 );
		}
	}

	/**
	 * Every kind declared anywhere in Page_Fields — top-level fields and
	 * repeater cells alike — must be one Page_Content knows how to cast, or
	 * deliberately passes through as a string. See
	 * Blueworx_Clubhouse_Page_Content::KNOWN_KINDS: this is the guard that
	 * fires the day a kind is added here and not there, so
	 * the editor and the front end stop silently disagreeing about the same
	 * stored value instead of only disagreeing.
	 */
	public function test_every_kind_is_one_page_content_knows_how_to_cast(): void {
		$kinds = array();
		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $area ) {
			foreach ( $area['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					foreach ( $panel['fields'] as $field ) {
						$kinds[ $field['kind'] ] = true;
						foreach ( $field['fields'] ?? array() as $cell ) {
							$kinds[ $cell['kind'] ] = true;
						}
					}
				}
			}
		}
		foreach ( array_keys( $kinds ) as $kind ) {
			$this->assertContains(
				$kind,
				Blueworx_Clubhouse_Page_Content::KNOWN_KINDS,
				sprintf(
					'"%s" is declared in Page_Fields but is not in Page_Content::KNOWN_KINDS — add it to Page_Content::cast().',
					$kind
				)
			);
		}
	}

	/**
	 * kind_of()'s only behaviour for the panel's own auto-declared switch:
	 * 'toggle' when the panel is hideable, '' when it is not. Nothing else in
	 * this suite would fail if the '_shown' case were deleted.
	 *
	 * No panel is declared non-hideable today — every section
	 * here can be switched off (see hideable_panels()) — so the second
	 * assertion reaches the same branch the way it is actually reachable
	 * today: panel_for() finding no panel at all behaves identically to
	 * finding one whose 'hideable' flag is false, since both fail the
	 * `null !== $panel && true === $panel['hideable']` check kind_of() makes.
	 */
	public function test_kind_of_shown_is_toggle_only_on_a_hideable_panel(): void {
		$this->assertSame( 'toggle', Blueworx_Clubhouse_Page_Fields::kind_of( 'home', 'hero', '_shown' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Page_Fields::kind_of( 'home', 'not_a_real_section', '_shown' ) );
	}
}
