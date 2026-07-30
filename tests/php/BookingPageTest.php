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

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function visibility(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function collections(): Blueworx_Clubhouse_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
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
		$html = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $this->visibility(), $this->collections() );
		$this->assertStringNotContainsString( 'Book a court', $html );

		$this->withLatePoint();
		$html = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $this->visibility(), $this->collections() );
		$this->assertStringContainsString( 'Book a court', $html, 'nav and footer pick it up' );
	}

	public function test_booking_renders_all_four_latepoint_shortcodes(): void {
		$this->withLatePoint();
		// No expander, so each slot shows its shortcode as text — which is exactly
		// what lets this assert the shortcodes reach the page verbatim.
		$html = Blueworx_Clubhouse_Page_Renderer::booking( $this->branding(), $this->visibility(), $this->collections() );

		$this->assertStringContainsString( '[latepoint_resources items=&quot;services&quot; columns=&quot;3&quot;]', $html );
		$this->assertStringContainsString( '[latepoint_resources items=&quot;locations&quot; columns=&quot;3&quot;]', $html );
		$this->assertStringContainsString( '[latepoint_resources items=&quot;agents&quot; columns=&quot;3&quot;]', $html );
		$this->assertStringContainsString( '[latepoint_calendar view=&quot;month&quot;]', $html );
		$this->assertSame( 4, substr_count( $html, 'class="ch-shortcode"' ) );
	}

	public function test_booking_expands_its_shortcodes_when_an_expander_is_installed(): void {
		$this->withLatePoint();
		Blueworx_Clubhouse_Shortcodes::set_expander(
			static fn( string $t ): string => '<div class="lp">' . strlen( $t ) . '</div>'
		);
		$html = Blueworx_Clubhouse_Page_Renderer::booking( $this->branding(), $this->visibility(), $this->collections() );
		$this->assertSame( 4, substr_count( $html, '<div class="lp">' ) );
	}

	/** Each slot is individually switchable, like every other section. */
	public function test_a_switched_off_slot_drops_out(): void {
		$this->withLatePoint();
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_section_visible( 'booking', 'agents', false );

		$html = Blueworx_Clubhouse_Page_Renderer::booking( $this->branding(), $vis, $this->collections() );
		$this->assertStringNotContainsString( 'items=&quot;agents&quot;', $html );
		$this->assertSame( 3, substr_count( $html, 'class="ch-shortcode"' ) );
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
		$content = new Blueworx_Clubhouse_Content_Store( $storage );
		$content->set( 'booking', 'calendar', 'shortcode', '' );

		$html = Blueworx_Clubhouse_Page_Renderer::booking( $this->branding(), $this->visibility(), $this->collections(), '', $content );
		$this->assertStringContainsString( '[latepoint_calendar view=&quot;month&quot;]', $html );
		$this->assertSame( 4, substr_count( $html, 'class="ch-shortcode"' ) );
	}

	/** A slot whose shortcode is replaced with whitespace still renders no band. */
	public function test_a_whitespace_shortcode_renders_no_band(): void {
		$this->withLatePoint();
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$content = new Blueworx_Clubhouse_Content_Store( $storage );
		$content->set( 'booking', 'calendar', 'shortcode', '   ' );

		$html = Blueworx_Clubhouse_Page_Renderer::booking( $this->branding(), $this->visibility(), $this->collections(), '', $content );
		$this->assertSame( 3, substr_count( $html, 'class="ch-shortcode"' ) );
	}

	public function test_a_club_can_retune_a_shortcode_without_a_release(): void {
		$this->withLatePoint();
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$content = new Blueworx_Clubhouse_Content_Store( $storage );
		$content->set( 'booking', 'services', 'shortcode', '[latepoint_resources items="services" columns="2"]' );

		$html = Blueworx_Clubhouse_Page_Renderer::booking( $this->branding(), $this->visibility(), $this->collections(), '', $content );
		$this->assertStringContainsString( 'columns=&quot;2&quot;', $html );
	}

	// ---- Admin ----

	public function test_the_content_editor_offers_booking_only_with_latepoint(): void {
		$tabs = static fn(): array => array_column( Blueworx_Clubhouse_Content_Catalogue::pages(), 'tab' );
		$this->assertNotContains( 'booking', $tabs() );

		$this->withLatePoint();
		$this->assertContains( 'booking', $tabs() );
	}

	/**
	 * The catalogue and the visibility inventory are held in lockstep by
	 * ContentCatalogueTest. That guard has to hold in BOTH integration states, or
	 * installing LatePoint would desynchronise the two.
	 */
	public function test_catalogue_and_inventory_stay_in_lockstep_with_latepoint_present(): void {
		$this->withLatePoint();
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
		$this->assertSame( array_keys( $inv ), array_keys( $seen ) );
		foreach ( $seen as $vis_page => $keys ) {
			sort( $keys );
			$this->assertSame( $inv[ $vis_page ], $keys, "Section keys diverge for {$vis_page}" );
		}
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
