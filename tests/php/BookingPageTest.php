<?php
// tests/php/BookingPageTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * The Booking page exists only when LatePoint does. These pin both states, because
 * "hidden when absent" is the whole requirement — a half-applied version would
 * leave an owner configuring a page that renders nothing, or a nav link to a URL
 * that refuses to serve.
 */
final class BookingPageTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Integrations::set_detector( null );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_Integrations::set_detector( null );
		Blueworx_Clubhouse_Shortcodes::set_expander( null );
	}

	private function withLatePoint(): void {
		Blueworx_Clubhouse_Integrations::set_detector(
			static fn( string $tag ): bool => Blueworx_Clubhouse_Integrations::LATEPOINT_TAG === $tag
		);
	}

	private function visibility(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function page( string $page, ?Blueworx_Clubhouse_Fake_Storage $storage = null ): string {
		return Blueworx_Clubhouse_Test_Site::page( $page, $storage ?? new Blueworx_Clubhouse_Fake_Storage() );
	}

	// ---- Availability ----

	public function test_page_map_offers_booking_only_with_latepoint(): void {
		$this->assertFalse( Blueworx_Clubhouse_Page_Map::is_available( 'booking' ) );
		$this->withLatePoint();
		$this->assertTrue( Blueworx_Clubhouse_Page_Map::is_available( 'booking' ) );
	}

	/**
	 * pages() keeps booking in every state, because rewrite rules are registered
	 * from it and are cached until flushed. A rule that came and went with a
	 * third-party plugin would leave /booking/ 404ing until permalinks were saved.
	 */
	public function test_the_rewrite_source_list_always_contains_booking(): void {
		$slugs = array_column( Blueworx_Clubhouse_Page_Map::pages(), 'slug' );
		$this->assertContains( 'booking', $slugs, 'without LatePoint' );

		$this->withLatePoint();
		$this->assertContains( 'booking', array_column( Blueworx_Clubhouse_Page_Map::pages(), 'slug' ) );
	}

	/** The rule exists, so the render gate is what has to refuse the request. */
	public function test_the_url_is_not_served_without_latepoint(): void {
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( false, 'booking', $this->visibility() ) );

		$this->withLatePoint();
		$this->assertSame( 'booking', Blueworx_Clubhouse_Frontend::resolve_slug( false, 'booking', $this->visibility() ) );
	}

	// ---- Front end ----

	public function test_no_page_links_to_booking_without_latepoint(): void {
		$this->assertStringNotContainsString( 'Bookings', $this->page( 'home' ) );

		$this->withLatePoint();
		$this->assertStringContainsString( 'Bookings', $this->page( 'home' ), 'nav and footer pick it up' );
	}

	/**
	 * Three resource lists — what, where, who. The "when" (LatePoint's booking
	 * calendar) sits on the Calendar page instead, beside the fixtures.
	 */
	public function test_booking_renders_the_three_resource_shortcodes(): void {
		$this->withLatePoint();
		// No expander, so each slot shows its shortcode as text — which is exactly
		// what lets this assert the shortcodes reach the page verbatim.
		$html = $this->page( 'booking' );

		$this->assertStringContainsString( '[latepoint_resources items=&quot;services&quot; columns=&quot;3&quot;]', $html );
		$this->assertStringContainsString( '[latepoint_resources items=&quot;locations&quot; columns=&quot;3&quot;]', $html );
		$this->assertStringContainsString( '[latepoint_resources items=&quot;agents&quot; columns=&quot;3&quot;]', $html );
		$this->assertStringNotContainsString( 'latepoint_calendar', $html, 'the calendar lives on the Calendar page' );
		$this->assertSame( 3, substr_count( $html, 'class="ch-shortcode"' ) );
	}

	public function test_booking_expands_its_shortcodes_when_an_expander_is_installed(): void {
		$this->withLatePoint();
		Blueworx_Clubhouse_Shortcodes::set_expander(
			static fn( string $t ): string => '<div class="lp">' . strlen( $t ) . '</div>'
		);
		$html = $this->page( 'booking' );
		$this->assertSame( 3, substr_count( $html, '<div class="lp">' ) );
	}

	/** Each slot is a block of its own, so taking one off the page drops it. */
	public function test_a_slot_taken_off_the_page_drops_out(): void {
		$this->withLatePoint();
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::without( $storage, 'booking/agents' );

		$html = $this->page( 'booking', $storage );
		$this->assertStringNotContainsString( 'items=&quot;agents&quot;', $html );
		$this->assertSame( 2, substr_count( $html, 'class="ch-shortcode"' ) );
	}

	// ---- The booking calendar on the Calendar page ----

	/**
	 * The Calendar page is served whether or not LatePoint is installed — it has
	 * fixtures to show either way. So this section is gated on the integration
	 * itself, not just on the page being reachable.
	 */
	public function test_the_calendar_page_shows_the_booking_calendar_only_with_latepoint(): void {
		$html = $this->page( 'calendar' );
		$this->assertStringNotContainsString( 'latepoint_calendar', $html );
		$this->assertStringContainsString( 'ch-cal', $html, 'the fixtures schedule still renders' );

		$this->withLatePoint();
		$html = $this->page( 'calendar' );
		$this->assertStringContainsString( '[latepoint_calendar view=&quot;month&quot;]', $html );
		$this->assertStringContainsString( 'ch-cal', $html, 'and still renders alongside it' );
	}

	/** Booking sits above the fixtures: deciding when to play comes before results. */
	public function test_the_booking_calendar_renders_above_the_fixtures_schedule(): void {
		$this->withLatePoint();
		$html = $this->page( 'calendar' );
		$this->assertLessThan(
			strpos( $html, 'ch-cal__month' ),
			strpos( $html, 'class="ch-shortcode"' ),
			'the booking calendar comes before the month-grouped fixtures'
		);
	}

	public function test_the_booking_calendar_can_be_taken_off_on_its_own(): void {
		$this->withLatePoint();
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::without( $storage, 'calendar/booking' );

		$html = $this->page( 'calendar', $storage );
		$this->assertStringNotContainsString( 'latepoint_calendar', $html );
		$this->assertStringContainsString( 'ch-cal', $html );
	}

	/** Its content fields disappear from the block editor when LatePoint does. */
	public function test_the_booking_calendar_section_is_hidden_from_the_admin_without_latepoint(): void {
		$fields = static function (): array {
			foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
				if ( 'calendar' === $page['tab'] ) {
					return array_column( $page['sections'], 'key' );
				}
			}
			return array();
		};

		$this->assertNotContains( 'booking', $fields(), 'no content fields' );

		$this->withLatePoint();
		$this->assertContains( 'booking', $fields() );
	}

	/**
	 * Clearing the field restores the default rather than dropping the slot: ''
	 * is the unset sentinel every content field uses, so cget() reads it as "no
	 * override". Pinned because the opposite is the intuitive guess, and acting
	 * on that guess would mean an owner clearing a field and seeing no change.
	 * Dropping a slot is the visibility toggle's job — see the test above.
	 */
	public function test_clearing_a_shortcode_restores_its_default_rather_than_dropping_it(): void {
		$this->withLatePoint();
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::write( $storage, 'booking/agents', array( 'shortcode' => '' ) );

		$html = $this->page( 'booking', $storage );
		$this->assertStringContainsString( '[latepoint_resources items=&quot;agents&quot; columns=&quot;3&quot;]', $html );
		$this->assertSame( 3, substr_count( $html, 'class="ch-shortcode"' ) );
	}

	/** A slot whose shortcode is replaced with whitespace still renders no band. */
	public function test_a_whitespace_shortcode_renders_no_band(): void {
		$this->withLatePoint();
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::write( $storage, 'booking/agents', array( 'shortcode' => '   ' ) );

		$html = $this->page( 'booking', $storage );
		$this->assertSame( 2, substr_count( $html, 'class="ch-shortcode"' ) );
	}

	public function test_a_club_can_retune_a_shortcode_without_a_release(): void {
		$this->withLatePoint();
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::write(
			$storage,
			'booking/services',
			array( 'shortcode' => '[latepoint_resources items="services" columns="2"]' )
		);

		$html = $this->page( 'booking', $storage );
		$this->assertStringContainsString( 'columns=&quot;2&quot;', $html );
	}

	// ---- Admin ----

	public function test_the_content_editor_offers_booking_only_with_latepoint(): void {
		$tabs = static fn(): array => array_column( Blueworx_Clubhouse_Content_Catalogue::pages(), 'tab' );
		$this->assertNotContains( 'booking', $tabs() );

		$this->withLatePoint();
		$this->assertContains( 'booking', $tabs() );
	}

	public function test_every_booking_slot_exposes_an_editable_shortcode_field(): void {
		$this->withLatePoint();
		$booking = null;
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			if ( 'booking' === $page['tab'] ) {
				$booking = $page;
			}
		}
		$this->assertNotNull( $booking );

		foreach ( $booking['sections'] as $section ) {
			if ( 'hero' === $section['key'] ) {
				continue;
			}
			$types = array_column( $section['fields'], 'type', 'key' );
			$this->assertSame( 'shortcode', $types['shortcode'] ?? null, $section['key'] );
		}
	}
}
