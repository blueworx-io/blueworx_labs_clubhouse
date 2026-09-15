<?php
// tests/php/GuidesTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * ClubHouse's guides are handed to the Guides page in the WordPress
 * Enhancements plugin through its filters, rather than shown on a screen of
 * ClubHouse's own. These pin the catalogue's shape, that it covers every
 * ClubHouse screen and list, and that the filters carry all of it across.
 */
final class GuidesTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	/** @return array<int,array<string,mixed>> */
	private function all(): array {
		return Blueworx_Clubhouse_Guides::catalogue( array( 'shop' => true, 'bookings' => true ) );
	}

	public function test_every_guide_is_well_formed(): void {
		$tabs = Blueworx_Clubhouse_Guides::tabs();
		$seen = array();
		foreach ( $this->all() as $guide ) {
			$this->assertMatchesRegularExpression( '/^clubhouse-[a-z0-9-]+$/', $guide['id'] );
			$this->assertArrayNotHasKey( $guide['id'], $seen, 'duplicate id ' . $guide['id'] );
			$seen[ $guide['id'] ] = true;

			$this->assertNotSame( '', trim( $guide['title'] ), $guide['id'] );
			$this->assertArrayHasKey( $guide['tab'], $tabs, $guide['id'] . ' names a tab that does not exist' );
			$this->assertSame( Blueworx_Clubhouse_Guides::PRODUCT, $guide['product'], $guide['id'] );
			$this->assertContains(
				$guide['capability'],
				array( Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP, Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP, 'manage_options' ),
				$guide['id']
			);
			$this->assertNotSame( '', trim( (string) $guide['parts']['where'] ), $guide['id'] . ' has no Where line' );
			$this->assertNotSame( array(), $guide['parts']['steps'], $guide['id'] . ' has no steps' );
		}
		$this->assertGreaterThanOrEqual( 30, count( $seen ) );
	}

	public function test_every_tab_has_at_least_one_guide(): void {
		$used = array();
		foreach ( $this->all() as $guide ) {
			$used[ $guide['tab'] ] = true;
		}
		foreach ( array_keys( Blueworx_Clubhouse_Guides::tabs() ) as $tab ) {
			$this->assertArrayHasKey( $tab, $used, 'no guide under ' . $tab );
		}
	}

	/**
	 * Every ClubHouse screen an owner can open, and every list under
	 * Collections, has a guide that sends the reader there. A screen added to
	 * the registry without a guide fails here rather than going unexplained.
	 */
	public function test_every_screen_and_collection_is_covered(): void {
		$wheres = array();
		$titles = array();
		foreach ( $this->all() as $guide ) {
			$wheres[] = (string) $guide['parts']['where'];
			$titles[] = (string) $guide['title'];
		}
		$wheres = implode( "\n", $wheres );
		$titles = implode( "\n", $titles );

		// Each screen as the sidebar reads it, which is how a Where line names
		// it. A screen added to the registry has to be added here too, and
		// then to the guides.
		$sidebar = array(
			Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG        => 'Clubhouse → Setup',
			Blueworx_Clubhouse_Page_Editors::GLOBAL_SLUG      => 'Clubhouse → Global content',
			Blueworx_Clubhouse_Import_Controller::PAGE_SLUG   => 'Clubhouse → Import',
			Blueworx_Clubhouse_Seo_Controller::PAGE_SLUG      => 'Clubhouse → Search & sharing',
			Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG => 'Collections',
		);
		foreach ( Blueworx_Clubhouse_Admin_Pages::all() as $page ) {
			$this->assertArrayHasKey( $page['slug'], $sidebar, $page['label'] . ' is not named in this test' );
			$this->assertStringContainsString( $sidebar[ $page['slug'] ], $wheres, 'no guide points at ' . $page['label'] );
		}
		foreach ( array( 'Sports', 'Teams', 'Fixtures', 'Events', 'Sponsors', 'People' ) as $plural ) {
			$this->assertStringContainsString( $plural, $titles, 'no guide about ' . $plural );
		}
	}

	public function test_shop_guides_only_appear_when_a_shop_is_running(): void {
		$with    = array_column( Blueworx_Clubhouse_Guides::catalogue( array( 'shop' => true, 'bookings' => false ) ), 'id' );
		$without = array_column( Blueworx_Clubhouse_Guides::catalogue( array( 'shop' => false, 'bookings' => false ) ), 'id' );

		$this->assertContains( 'clubhouse-members-tiers', $with );
		$this->assertNotContains( 'clubhouse-members-tiers', $without );
		// What you ask members needs no shop: the questions are asked at sign-up either way.
		$this->assertContains( 'clubhouse-members-fields', $without );
	}

	public function test_bookings_guides_only_appear_when_latepoint_is_running(): void {
		$with    = array_column( Blueworx_Clubhouse_Guides::catalogue( array( 'shop' => false, 'bookings' => true ) ), 'id' );
		$without = array_column( Blueworx_Clubhouse_Guides::catalogue( array( 'shop' => false, 'bookings' => false ) ), 'id' );

		$this->assertContains( 'clubhouse-bookings-page', $with );
		$this->assertNotContains( 'clubhouse-bookings-page', $without );
	}

	/** Without the Enhancements helper, the body is still built in the same shape. */
	public function test_body_is_built_from_its_parts(): void {
		$html = Blueworx_Clubhouse_Guides::body(
			array(
				'where' => 'Clubhouse → Setup',
				'intro' => 'A <b>plain</b> sentence.',
				'steps' => array( 'Press *Save*.', 'Wait.' ),
				'then'  => 'Done.',
			)
		);
		$this->assertStringContainsString( '<p class="bw-guide__where"><strong>Where:</strong> Clubhouse → Setup</p>', $html );
		$this->assertStringContainsString( 'A &lt;b&gt;plain&lt;/b&gt; sentence.', $html );
		$this->assertStringContainsString( '<ol class="bw-guide__steps"><li>Press <em>Save</em>.</li><li>Wait.</li></ol>', $html );
		$this->assertStringContainsString( '<p class="bw-guide__then">Done.</p>', $html );
	}

	public function test_the_filters_carry_the_product_tabs_and_guides_across(): void {
		Blueworx_Clubhouse_Guides_Registrar::set_site( array( 'shop' => false, 'bookings' => false ) );

		$products = Blueworx_Clubhouse_Guides_Registrar::products( array( 'blueworx' => 'BlueWorx' ) );
		$this->assertSame( array( 'blueworx' => 'BlueWorx', 'clubhouse' => 'ClubHouse' ), $products );

		$tabs = Blueworx_Clubhouse_Guides_Registrar::tabs( array( 'getting-started' => 'Getting started' ) );
		$this->assertSame( 'Getting started', $tabs['getting-started'], 'existing tabs are kept' );
		foreach ( Blueworx_Clubhouse_Guides::tabs() as $id => $label ) {
			$this->assertSame( $label, $tabs[ $id ] );
		}

		$map = Blueworx_Clubhouse_Guides_Registrar::tab_products( array( 'getting-started' => 'blueworx', 'ch-pages' => 'blueworx' ) );
		$this->assertSame( 'blueworx', $map['getting-started'] );
		foreach ( array_keys( Blueworx_Clubhouse_Guides::tabs() ) as $id ) {
			$this->assertSame( 'clubhouse', $map[ $id ], $id );
		}

		$existing = array( array( 'id' => 'wp-posts-writing', 'title' => 'x', 'tab' => 'wp-posts', 'body' => '<p>x</p>' ) );
		$guides   = Blueworx_Clubhouse_Guides_Registrar::guides( $existing );
		$this->assertSame( 'wp-posts-writing', $guides[0]['id'], 'existing guides come first, untouched' );
		$ours = array_slice( $guides, 1 );
		$this->assertCount( count( Blueworx_Clubhouse_Guides::catalogue( array( 'shop' => false, 'bookings' => false ) ) ), $ours );
		foreach ( $ours as $guide ) {
			$this->assertSame( 'clubhouse', $guide['product'] );
			$this->assertStringContainsString( 'bw-guide__where', $guide['body'] );
			$this->assertArrayNotHasKey( 'parts', $guide, 'the page is handed a body, not parts' );
		}
	}

	public function test_registering_hooks_the_four_filters(): void {
		Blueworx_Clubhouse_Guides_Registrar::register();
		$hooked = array_map( static fn( array $c ): string => (string) $c['args'][0], wp_stub_calls( 'add_filter' ) );
		foreach ( array( 'blueworx_guide_products', 'blueworx_guide_tabs', 'blueworx_guide_tab_products', 'blueworx_guides' ) as $filter ) {
			$this->assertContains( $filter, $hooked );
		}
	}

	/** The Guides row lives in Enhancements' menu, so both roles must be allowed to keep it. */
	public function test_both_roles_keep_the_guides_menu(): void {
		$this->assertContains( 'blueworx-guides', Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist() );
		$this->assertContains( 'blueworx-guides', Blueworx_Clubhouse_Owner_Capabilities::editor_menu_allowlist() );
	}

	/** The screen of ClubHouse's own is gone, and the registry no longer names it. */
	public function test_the_old_user_guide_screen_is_gone(): void {
		$this->assertFalse( class_exists( 'Blueworx_Clubhouse_Guide_Controller' ) );
		$this->assertNull( Blueworx_Clubhouse_Admin_Pages::find( 'clubhouse-guide' ) );
		$this->assertFileDoesNotExist( dirname( __DIR__, 2 ) . '/includes/admin/class-guide.php' );
	}
}
