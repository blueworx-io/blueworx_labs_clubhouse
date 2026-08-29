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
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_Integrations::set_detector( null );
		Blueworx_Clubhouse_Page_Fields::forget();
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
}
