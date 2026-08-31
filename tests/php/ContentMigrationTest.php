<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ContentMigrationTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Storage $storage;

	protected function setUp(): void {
		$GLOBALS['wp_stub_postmeta'] = array();
		$GLOBALS['wp_stub_options']  = array();
		$this->storage               = new Blueworx_Clubhouse_Fake_Storage();
		update_option( 'clubhouse_page_id_home', 42 );
		update_option( 'clubhouse_page_id_about', 43 );
		// A couple of tests below vary which integrations are active, so the
		// memoised catalogue has to be forgotten between them the same way
		// PageFieldsTest does — or a later case reads an earlier one's cached
		// availability instead of its own.
		Blueworx_Clubhouse_Page_Fields::forget();
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_Integrations::set_detector( null );
		Blueworx_Clubhouse_Page_Fields::forget();
	}

	public function test_a_field_arrives_at_its_new_address(): void {
		$this->storage->set( 'content_home', array( 'hero' => array( 'title_lead' => 'Crewe Vagrants' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( 'Crewe Vagrants', $GLOBALS['wp_stub_postmeta'][42]['page_hero_title_lead'] );
	}

	public function test_rows_arrive_as_one_value(): void {
		$rows = array( array( 'text' => 'Match on Saturday' ) );
		$this->storage->set( 'content_home', array( 'ticker' => array( 'items' => $rows ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( $rows, $GLOBALS['wp_stub_postmeta'][42]['page_ticker_items'] );
	}

	public function test_a_switch_keeps_its_state_and_its_type(): void {
		$this->storage->set( 'visibility', array( 'sections' => array( 'home.social_feed' => false ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$content = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertFalse( $content->is_section_shown( 'home', 'social_feed' ) );
	}

	public function test_a_section_nobody_touched_arrives_shown(): void {
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$content = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertTrue( $content->is_section_shown( 'home', 'hero' ) );
	}

	public function test_global_content_goes_to_its_own_option(): void {
		$this->storage->set( 'content_global', array( 'header' => array( 'join' => 'Join us' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( 'Join us', $this->storage->get( 'global_content', array() )['header_join'] );
	}

	public function test_a_field_never_saved_is_not_written_at_all(): void {
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertArrayNotHasKey( 'page_hero_title_lead', $GLOBALS['wp_stub_postmeta'][42] ?? array() );
	}

	/**
	 * Image fields have held two shapes: an attachment id, and a raw URL from
	 * a demo or a preview. The media kind is an integer, so a raw URL would
	 * cast to 0 and the picture would vanish. Anything that cannot be resolved
	 * to an attachment is left where it is and named in the report.
	 */
	public function test_an_image_that_is_not_an_attachment_is_reported_and_not_written(): void {
		$this->storage->set( 'content_home', array( 'clubhouse' => array( 'image' => 'https://example.test/x.jpg' ) ) );
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertContains( 'home/clubhouse/image', $result['skipped'] );
		$this->assertArrayNotHasKey( 'page_clubhouse_image', $GLOBALS['wp_stub_postmeta'][42] ?? array() );
	}

	public function test_a_page_with_no_post_behind_it_is_reported(): void {
		delete_option( 'clubhouse_page_id_about' );
		$this->storage->set( 'content_about', array( 'hero' => array( 'title_lead' => 'About us' ) ) );
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertContains( 'about/hero/title_lead', $result['skipped'] );
	}

	/**
	 * Covers a repeater deliberately, not just a single text field: a repeater
	 * write that appended rather than replaced would still pass a postmeta
	 * diff on run 1 (nothing to compare against yet) and only show up as
	 * doubled rows on run 2 — the one shape a single-field diff cannot catch.
	 * global_content and the full $result are compared too, not just postmeta,
	 * so a second run that quietly moved a different value onto an unrelated
	 * global address would not slip past this either.
	 */
	public function test_running_twice_changes_nothing_the_second_time(): void {
		$this->storage->set( 'content_home', array(
			'hero'   => array( 'title_lead' => 'Crewe Vagrants' ),
			'ticker' => array( 'items' => array( array( 'text' => 'Match on Saturday' ), array( 'text' => 'AGM next month' ) ) ),
		) );
		$this->storage->set( 'content_global', array( 'header' => array( 'join' => 'Join us' ) ) );

		$first_result   = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$first_postmeta = $GLOBALS['wp_stub_postmeta'];
		$first_global   = $this->storage->get( 'global_content', array() );

		$second_result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );

		$this->assertSame( $first_postmeta, $GLOBALS['wp_stub_postmeta'] );
		$this->assertSame( $first_global, $this->storage->get( 'global_content', array() ) );
		$this->assertSame( $first_result, $second_result );
		// The repeater specifically: two rows in, two rows out — not four.
		$this->assertCount( 2, $GLOBALS['wp_stub_postmeta'][42]['page_ticker_items'] );
	}

	public function test_the_old_option_is_left_alone(): void {
		$this->storage->set( 'content_home', array( 'hero' => array( 'title_lead' => 'Crewe Vagrants' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertNotSame( array(), $this->storage->get( 'content_home', array() ) );
	}

	/**
	 * A media field an owner deliberately cleared reads back as '', not null —
	 * Content_Store cannot tell "cleared" from "never saved" any other way.
	 * Reporting it as "not a real attachment" would be a false alarm sending
	 * an operator chasing a picture that was never there.
	 */
	public function test_a_media_field_deliberately_cleared_is_not_reported(): void {
		$this->storage->set( 'content_home', array( 'hero' => array( 'image' => '' ) ) );
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( array(), $result['skipped'] );
		$this->assertArrayNotHasKey( 'page_hero_image', $GLOBALS['wp_stub_postmeta'][42] ?? array() );
	}

	/** The common case for a real club's photos: an id already, not a URL. */
	public function test_a_numeric_media_value_writes_as_an_int(): void {
		$this->storage->set( 'content_home', array( 'hero' => array( 'image' => '318' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( 318, $GLOBALS['wp_stub_postmeta'][42]['page_hero_image'] );
	}

	/** The hit path for a URL that does resolve to a real attachment. */
	public function test_an_image_url_that_resolves_to_an_attachment_is_written(): void {
		wp_stub_register_attachment( 'https://example.test/hero.jpg', 77 );
		$this->storage->set( 'content_home', array( 'hero' => array( 'image' => 'https://example.test/hero.jpg' ) ) );
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( 77, $GLOBALS['wp_stub_postmeta'][42]['page_hero_image'] );
		$this->assertNotContains( 'home/hero/image', $result['skipped'] );
	}

	/**
	 * A club that switched its footer off under Setup → Visibility has that
	 * state stored under 'home.footer' — Setup is keyed by the pages this
	 * plugin serves, and the sitewide chrome doesn't have one of its own. The
	 * Global content editor's own Shown switch lives at the 'global' address
	 * instead. Both must agree after migrating, or the club's real choice
	 * comes back on.
	 */
	public function test_a_global_panel_switched_off_in_setup_keeps_its_state(): void {
		$this->storage->set( 'visibility', array( 'sections' => array( 'home.footer' => false ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$content = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertFalse( $content->is_section_shown( 'global', 'footer' ) );
	}

	/**
	 * Bookings needs LatePoint, which is not active in this suite's default
	 * state — so Page_Fields::areas() drops the whole area, and it would be
	 * invisible to a migration that only walked areas(). Real content sitting
	 * under its old addresses must be named in the report, not silently
	 * never mentioned, and nothing may be silently placed for it either.
	 */
	public function test_content_behind_an_unavailable_area_is_reported_not_dropped(): void {
		$this->storage->set( 'content_booking', array( 'hero' => array( 'title_lead' => 'Book a court' ) ) );
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertContains( 'booking/hero/title_lead', $result['skipped'] );
		$content = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertNull( $content->get( 'booking', 'hero', 'title_lead', null ) );
	}

	/**
	 * The Calendar page itself needs no integration, but its own booking slot
	 * does (the same LatePoint gate as the whole Bookings page) — so this is
	 * the narrower case: one panel dropped from an otherwise-available area.
	 */
	public function test_content_behind_an_unavailable_panel_is_reported_not_dropped(): void {
		update_option( 'clubhouse_page_id_calendar', 44 );
		$this->storage->set( 'content_calendar', array( 'booking' => array( 'heading' => 'Book a slot' ) ) );
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertContains( 'calendar/booking/heading', $result['skipped'] );
		$content = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertNull( $content->get( 'calendar', 'booking', 'heading', null ) );
	}

	/**
	 * A field never saved in the old store has nothing to lose, so an
	 * unavailable area with no real content behind it stays quiet — the same
	 * rule every other kind follows, applied here too rather than reporting
	 * every declared address whether or not anything was ever written there.
	 */
	public function test_an_unavailable_area_with_nothing_saved_reports_nothing(): void {
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$booking_addresses = array_filter(
			$result['skipped'],
			static fn( string $address ): bool => str_starts_with( $address, 'booking/' ) || str_starts_with( $address, 'login/' )
		);
		$this->assertSame( array(), $booking_addresses );
	}

	/**
	 * The value-level round trip: with every integration active and a real
	 * post behind every page, every address Page_Fields declares is seeded
	 * with a value of its own kind, migrated, and read back through
	 * Page_Content — type and all (assertSame, not assertEquals; a toggle
	 * cast that returned a truthy string instead of true would pass
	 * assertEquals and fail the club's front end). This replaces an HTML
	 * before/after diff, which cannot tell "placed correctly" from "never
	 * written, fell through to a matching default" and folds false, '' and 0
	 * together.
	 *
	 * Booking and login are deliberately included by activating both
	 * integrations for this one test — content behind an unavailable
	 * integration is covered separately, above, by the tests that leave them
	 * off. all_areas() is the whole declaration before availability drops
	 * anything, so walking it here covers every address the migration could
	 * ever meet.
	 */
	public function test_every_declared_field_round_trips_through_the_migration(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		Blueworx_Clubhouse_Integrations::set_detector(
			static fn( string $tag ): bool => Blueworx_Clubhouse_Integrations::LATEPOINT_TAG === $tag
		);
		Blueworx_Clubhouse_Page_Fields::forget();

		$post_id = 100;
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			update_option( Blueworx_Clubhouse_Club_Pages::option_name( $page['slug'] ), $post_id );
			$post_id += 10;
		}

		// Seeds the old option shape directly, the way every other case in this
		// file does. Content_Store, which used to write it, is deleted — and the
		// migration reads the option directly for the same reason, so a seeder
		// here is closer to what the migration actually meets than a store
		// wrapper would have been.
		$old = function ( string $area, string $section, string $key, mixed $value ): void {
			$all = $this->storage->get( 'content_' . $area, array() );
			$all = is_array( $all ) ? $all : array();
			$all[ $section ][ $key ] = $value;
			$this->storage->set( 'content_' . $area, $all );
		};

		$expected  = array();
		$n         = 0;
		$toggle_n  = 0;

		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $area => $spec ) {
			foreach ( $spec['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					$section = (string) $panel['id'];
					foreach ( $panel['fields'] as $field ) {
						$key = Blueworx_Clubhouse_Page_Fields::field_key( $section, (string) $field['id'] );
						if ( '' === $key ) {
							continue;
						}
						++$n;
						$kind = (string) $field['kind'];

						switch ( $kind ) {
							case 'toggle':
								// Both ways, deterministically — the first toggle
								// seeded lands true, the next false, whatever order
								// Page_Fields declares them in.
								$value = 0 === ( $toggle_n % 2 );
								++$toggle_n;
								break;
							case 'media':
								// The common real-club shape: an attachment id
								// already. The URL-resolves and URL-misses paths
								// have their own dedicated tests, above.
								$value = 5000 + $n;
								break;
							case 'select':
								$options = $field['options'] ?? array();
								$value   = (string) ( $options[0]['value'] ?? '' );
								break;
							case 'repeater':
								$row   = static fn( string $tag ): array => array_combine(
									array_column( $field['fields'], 'id' ),
									array_map( static fn( array $c ): string => "{$tag} {$c['id']}", $field['fields'] )
								);
								$value = array( $row( 'row1' ), $row( 'row2' ) );
								break;
							default: // text, textarea — and url, which Page_Fields declares as kind 'text'.
								$value = "Value {$n} for {$area}/{$section}/{$key}";
						}

						$expected[] = array( 'area' => $area, 'section' => $section, 'key' => $key, 'kind' => $kind, 'value' => $value );

						if ( 'repeater' === $kind ) {
							$old( $area, $section, Blueworx_Clubhouse_Page_Fields::REPEATER_FIELD, $value );
						} else {
							$old( $area, $section, $key, $value );
						}
					}
				}
			}
		}

		$this->assertGreaterThan( 50, count( $expected ), 'the seed walked too little of Page_Fields to be a meaningful round trip' );

		$result  = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$content = new Blueworx_Clubhouse_Page_Content( $this->storage );

		foreach ( $expected as $e ) {
			$address = "{$e['area']}/{$e['section']}/{$e['key']}";
			if ( 'repeater' === $e['kind'] ) {
				$this->assertSame( $e['value'], $content->get_items( $e['area'], $e['section'] ), $address );
				continue;
			}
			$this->assertSame( $e['value'], $content->get( $e['area'], $e['section'], $e['key'], null ), $address );
		}

		$this->assertSame( array(), $result['skipped'], 'nothing should have been skipped — every page exists and every integration is active' );
	}
}
