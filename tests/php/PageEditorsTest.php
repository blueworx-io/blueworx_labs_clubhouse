<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageEditorsTest extends TestCase {

	// Every integration installed, the same state PageFieldsTest exercises —
	// without it, 'login' (requires_shop) and 'booking' (requires LatePoint)
	// both drop out of Page_Fields::areas() and the "fifteen" screens this
	// class declares would quietly be fewer, order-dependent on whatever an
	// earlier test left behind.
	protected function setUp(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		Blueworx_Clubhouse_Integrations::set_detector(
			static fn( string $tag ): bool => Blueworx_Clubhouse_Integrations::LATEPOINT_TAG === $tag
		);
		Blueworx_Clubhouse_Page_Fields::forget();
		// hide_record_editors() reads Editor::all(), the library's own static
		// registry — a test that calls declare_screens() leaves it populated
		// for whichever test runs next in the same process otherwise.
		\Blueworx\PageEditor\v1\Editor::reset();
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_Integrations::set_detector( null );
		Blueworx_Clubhouse_Page_Fields::forget();
		\Blueworx\PageEditor\v1\Editor::reset();
		// link_suggestions() installs a real resolver as a side effect of
		// screens() — every test in this class runs it. Left in place it
		// would leak into any other test file that calls Links::url()
		// expecting the untouched default.
		Blueworx_Clubhouse_Links::set_resolver( null );
	}

	/** @return array<string,array<string,mixed>> slug => screen */
	private function screens(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Editors::screens() as $screen ) {
			$out[ $screen['slug'] ] = $screen;
		}
		return $out;
	}

	public function test_every_screen_the_library_would_refuse_is_named(): void {
		foreach ( Blueworx_Clubhouse_Page_Editors::screens() as $screen ) {
			// validate() throws on anything wrong, naming the field. Letting it
			// through here would mean a live screen that says "not ready".
			\Blueworx\PageEditor\v1\Schema::validate( $screen );
			$this->addToAssertionCount( 1 );
		}
	}

	public function test_a_club_page_screen_stores_a_record_on_the_page_post_type(): void {
		$home = $this->screens()['clubhouse-page-home'];
		$this->assertSame( 'post', $home['store'] );
		$this->assertSame( 'page', $home['post_type'] );
	}

	public function test_global_content_stores_to_an_option(): void {
		$global = $this->screens()['clubhouse-global-content'];
		$this->assertSame( 'option', $global['store'] );
		$this->assertSame( 'clubhouse_global_content', $global['option_name'] );
	}

	public function test_every_club_page_screen_hangs_off_the_clubhouse_menu(): void {
		foreach ( $this->screens() as $slug => $screen ) {
			$this->assertSame( Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG, $screen['parent'], $slug );
		}
	}

	public function test_a_screen_declares_the_content_capability(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			$this->screens()['clubhouse-page-home']['capability']
		);
	}

	public function test_home_gets_three_tabs_and_a_short_page_gets_one(): void {
		$this->assertCount( 3, $this->screens()['clubhouse-page-home']['tabs'] );
		$this->assertCount( 1, $this->screens()['clubhouse-page-login']['tabs'] );
	}

	public function test_the_editor_url_carries_the_page_it_edits(): void {
		update_option( 'clubhouse_page_id_about', 91 );
		$this->assertStringContainsString( 'page=clubhouse-page-about', Blueworx_Clubhouse_Page_Editors::editor_url( 'about' ) );
		$this->assertStringContainsString( 'id=91', Blueworx_Clubhouse_Page_Editors::editor_url( 'about' ) );
	}

	/** The setUp() comment's own claim: with everything installed, all fifteen. */
	public function test_there_are_fifteen_screens_with_everything_installed(): void {
		$this->assertCount( 15, Blueworx_Clubhouse_Page_Editors::screens() );
	}

	public function test_exactly_one_screen_stores_to_an_option(): void {
		$option_screens = array_filter(
			Blueworx_Clubhouse_Page_Editors::screens(),
			static fn( array $screen ): bool => 'option' === $screen['store']
		);
		$this->assertCount( 1, $option_screens );
		$this->assertSame( Blueworx_Clubhouse_Page_Editors::GLOBAL_SLUG, array_values( $option_screens )[0]['slug'] );
	}

	/**
	 * The one piece of unplanned, load-bearing behaviour in this class: a slug
	 * rename or a selector typo here would un-hide all fourteen with nothing
	 * failing, if nothing asserted it.
	 */
	public function test_a_url_field_offers_the_sites_own_pages(): void {
		$fields = array();
		foreach ( Blueworx_Clubhouse_Page_Editors::screens() as $screen ) {
			foreach ( $screen['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					foreach ( $panel['fields'] as $field ) {
						if ( 'url' === ( $field['format'] ?? '' ) ) {
							$fields[] = $field;
						}
					}
				}
			}
		}
		$this->assertNotSame( array(), $fields, 'No url fields at all — the translation lost them.' );
		foreach ( $fields as $field ) {
			$this->assertNotEmpty( $field['suggestions'] ?? array(), $field['id'] );
			foreach ( $field['suggestions'] as $suggestion ) {
				$this->assertArrayHasKey( 'value', $suggestion );
				$this->assertArrayHasKey( 'label', $suggestion );
			}
		}
	}

	/**
	 * A menu target like "shop:dashboard" is a token this plugin resolves and a
	 * browser does not. These go into a free-text box that has to hold a link,
	 * so every suggestion is either a path or an absolute address.
	 */
	public function test_a_suggestion_is_an_address_and_not_a_target_token(): void {
		foreach ( Blueworx_Clubhouse_Page_Editors::screens() as $screen ) {
			foreach ( $screen['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					foreach ( $panel['fields'] as $field ) {
						foreach ( $field['suggestions'] ?? array() as $suggestion ) {
							$this->assertMatchesRegularExpression( '#^(/|https?://)#', $suggestion['value'], $field['id'] );
						}
					}
				}
			}
		}
	}

	/** Repeater cells carry the same suggestions as a top-level field — a quick tile's link is as much a link as the hero's. */
	public function test_a_repeater_cells_url_field_offers_suggestions_too(): void {
		$home = $this->screens()['clubhouse-page-home'];
		$cell = null;
		foreach ( $home['tabs'] as $tab ) {
			foreach ( $tab['panels'] as $panel ) {
				foreach ( $panel['fields'] as $field ) {
					if ( 'quick_tiles_items' === ( $field['id'] ?? '' ) ) {
						foreach ( $field['fields'] as $candidate ) {
							if ( 'href' === $candidate['id'] ) {
								$cell = $candidate;
							}
						}
					}
				}
			}
		}
		$this->assertNotNull( $cell, 'Quick tiles href cell not found.' );
		$this->assertNotEmpty( $cell['suggestions'] ?? array() );
	}

	/**
	 * Whether shortcode_exists( 'latepoint_calendar' ) sees LatePoint's own
	 * shortcode depends on two plugins' relative init registration order,
	 * which WordPress does not guarantee. Declaring screens at a later
	 * priority can only see more integrations register, never fewer.
	 */
	public function test_screens_are_declared_late_on_init(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Page_Editors::register();

		$call = null;
		foreach ( wp_stub_calls( 'add_action' ) as $candidate ) {
			$hook     = $candidate['args'][0] ?? null;
			$callback = $candidate['args'][1] ?? null;
			if ( 'init' === $hook && is_array( $callback )
				&& Blueworx_Clubhouse_Page_Editors::class === $callback[0]
				&& 'declare_screens' === $callback[1] ) {
				$call = $candidate;
			}
		}
		$this->assertNotNull( $call, 'declare_screens() is not hooked to init.' );
		$this->assertSame( 20, $call['args'][2] ?? 10, 'declare_screens() must run at priority 20, after other plugins have had a chance to register on init.' );
	}

	public function test_hide_record_editors_removes_every_record_editor_but_keeps_global_content(): void {
		Blueworx_Clubhouse_Page_Editors::declare_screens();

		Blueworx_Clubhouse_Page_Editors::hide_record_editors();

		$removed = array_map(
			static fn( array $call ): string => $call['args'][1],
			wp_stub_calls( 'remove_submenu_page' )
		);
		$this->assertCount( 14, $removed );
		$this->assertContains( 'clubhouse-page-home', $removed );
		$this->assertNotContains( Blueworx_Clubhouse_Page_Editors::GLOBAL_SLUG, $removed );
		foreach ( wp_stub_calls( 'remove_submenu_page' ) as $call ) {
			$this->assertSame( Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG, $call['args'][0] );
		}
	}
}
