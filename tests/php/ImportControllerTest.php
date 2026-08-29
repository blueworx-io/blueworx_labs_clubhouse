<?php
// tests/php/ImportControllerTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportControllerTest extends TestCase {

	private Blueworx_Clubhouse_Storage $storage;

	protected function setUp(): void {
		wp_stub_reset();
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
		// Import_Applier now writes through Page_Content, which resolves a page
		// key to a post id and silently no-ops when there is none. Every test
		// here that applies content writes to 'home', so it needs a post id
		// fixture or the write goes nowhere and the assertion is vacuous.
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 101 );
	}

	/** Write a temp file holding $json and return the $_FILES-shaped array. */
	private function upload( string $json ): array {
		$path = tempnam( sys_get_temp_dir(), 'chimport' );
		file_put_contents( $path, $json );
		return array( 'tmp_name' => $path, 'error' => 0, 'size' => strlen( $json ), 'name' => 'clubhouse-import.json' );
	}

	private function valid_json(): string {
		return '{"clubhouse_import":1,"content":{"home":{"hero":{"eyebrow":"Est. 1974"}}}}';
	}

	/**
	 * Regression: the prompt URL reached Import_Screen already esc_html'd (from
	 * wp_nonce_url), the screen escaped it again, and the href shipped '&amp;amp;'.
	 * The browser then sent the nonce as 'amp;_wpnonce', so check_admin_referer()
	 * saw none and the download 403'd with "The link you followed has expired."
	 */
	public function test_the_prompt_url_is_unescaped_so_the_screen_escapes_it_exactly_once(): void {
		$url = Blueworx_Clubhouse_Import_Controller::prompt_url();
		$this->assertStringContainsString( '&_wpnonce=', $url );
		$this->assertStringNotContainsString( '&#038;', $url );
		$this->assertStringNotContainsString( '&amp;', $url );
	}

	/** The rendered href must survive one HTML-decode into a URL whose nonce is its own parameter. */
	public function test_the_rendered_prompt_href_carries_a_usable_nonce(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render(
			array(
				'state'         => 'start',
				'download_url'  => Blueworx_Clubhouse_Import_Controller::prompt_url(),
				'action_url'    => 'https://club.test/wp-admin/admin.php?page=clubhouse-import',
				'nonce_field'   => '',
				'error'         => '',
				'rows'          => array(),
				'warnings'      => array(),
				'images_needed' => array(),
				'max_upload'    => '1 MB',
			)
		);
		$this->assertSame( 1, preg_match( '/href="([^"]*clubhouse_import_prompt[^"]*)"/', $html, $m ) );
		$href = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
		parse_str( (string) parse_url( $href, PHP_URL_QUERY ), $query );
		$this->assertSame( Blueworx_Clubhouse_Import_Controller::DOWNLOAD_ACTION, $query['action'] ?? null );
		$this->assertArrayHasKey( '_wpnonce', $query );
	}

	public function test_it_registers_a_submenu_under_clubhouse(): void {
		Blueworx_Clubhouse_Import_Controller::add_menu();
		$call = wp_stub_calls( 'add_submenu_page' )[0]['args'];
		$this->assertSame( Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG, $call[0] );
		$this->assertSame( Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP, $call[3] );
		$this->assertSame( Blueworx_Clubhouse_Import_Controller::PAGE_SLUG, $call[4] );
	}

	public function test_no_upload_shows_the_start_state(): void {
		$model = Blueworx_Clubhouse_Import_Controller::handle_request( array(), array(), $this->storage );
		$this->assertSame( 'start', $model['state'] );
		$this->assertSame( '', $model['error'] );
	}

	public function test_a_valid_upload_shows_a_preview_and_writes_nothing(): void {
		$model = Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ),
			$this->upload( $this->valid_json() ),
			$this->storage
		);
		$this->assertSame( 'preview', $model['state'] );
		$this->assertSame( 'Home · Hero', $model['rows'][0]['label'] );

		$store = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertNull( $store->get( 'home', 'hero', 'eyebrow' ) );
	}

	public function test_a_valid_upload_stores_the_plan_in_a_user_scoped_transient(): void {
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ),
			$this->upload( $this->valid_json() ),
			$this->storage
		);
		$this->assertNotFalse( get_transient( 'clubhouse_import_plan_7' ) );
	}

	public function test_malformed_json_is_a_hard_error(): void {
		$model = Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ),
			$this->upload( '{not json' ),
			$this->storage
		);
		$this->assertSame( 'start', $model['state'] );
		$this->assertStringContainsString( 'could not be read', $model['error'] );
	}

	public function test_an_oversized_file_is_refused_without_being_read(): void {
		$file          = $this->upload( $this->valid_json() );
		$file['size']  = Blueworx_Clubhouse_Import_Controller::MAX_BYTES + 1;
		$model         = Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ),
			$file,
			$this->storage
		);
		$this->assertStringContainsString( 'too large', $model['error'] );
	}

	public function test_a_failed_upload_is_reported(): void {
		$model = Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ),
			array( 'tmp_name' => '', 'error' => 4, 'size' => 0, 'name' => '' ),
			$this->storage
		);
		$this->assertStringContainsString( 'Choose a file', $model['error'] );
	}

	public function test_apply_writes_the_stored_plan_and_clears_the_transient(): void {
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ),
			$this->upload( $this->valid_json() ),
			$this->storage
		);
		$model = Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_apply' => '1' ),
			array(),
			$this->storage
		);

		$this->assertSame( 'result', $model['state'] );
		$store = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertSame( 'Est. 1974', $store->get( 'home', 'hero', 'eyebrow' ) );
		$this->assertFalse( get_transient( 'clubhouse_import_plan_7' ) );
	}

	public function test_apply_without_a_stored_plan_is_refused(): void {
		$model = Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_apply' => '1' ),
			array(),
			$this->storage
		);
		$this->assertSame( 'start', $model['state'] );
		$this->assertStringContainsString( 'expired', $model['error'] );
	}

	public function test_cancel_clears_the_transient_and_returns_to_the_start(): void {
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ),
			$this->upload( $this->valid_json() ),
			$this->storage
		);
		$model = Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_cancel' => '1' ),
			array(),
			$this->storage
		);
		$this->assertSame( 'start', $model['state'] );
		$this->assertFalse( get_transient( 'clubhouse_import_plan_7' ) );
	}

	public function test_apply_records_images_still_needed_in_storage(): void {
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$json = '{"clubhouse_import":1,"content":{"home":{"hero":{"image":"https://e.test/gone.jpg"}}}}';
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ),
			$this->upload( $json ),
			$this->storage
		);
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_apply' => '1' ),
			array(),
			$this->storage
		);
		$needed = $this->storage->get( Blueworx_Clubhouse_Import_Controller::IMAGES_NEEDED_KEY, array() );
		$this->assertSame( 'Home · Hero — Background image', $needed[0]['label'] );
	}

	public function test_a_second_unrelated_import_does_not_erase_the_first_images_still_needed_list(): void {
		// The prompt actively encourages importing a page at a time, so a second,
		// unrelated import with no images of its own must not reset the to-do
		// list a prior import built — the owner's pending pictures must not
		// vanish without a single image having been supplied.
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$first = '{"clubhouse_import":1,"content":{"home":{"hero":{"image":"https://e.test/gone.jpg"}}}}';
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ), $this->upload( $first ), $this->storage
		);
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_apply' => '1' ), array(), $this->storage
		);

		$second = '{"clubhouse_import":1,"content":{"about":{"hero":{"eyebrow":"Est. 1974"}}}}';
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ), $this->upload( $second ), $this->storage
		);
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_apply' => '1' ), array(), $this->storage
		);

		$needed = $this->storage->get( Blueworx_Clubhouse_Import_Controller::IMAGES_NEEDED_KEY, array() );
		$this->assertCount( 1, $needed );
		$this->assertSame( 'Home · Hero — Background image', $needed[0]['label'] );
	}

	public function test_the_same_still_needed_entry_is_not_duplicated_across_imports(): void {
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$json = '{"clubhouse_import":1,"content":{"home":{"hero":{"image":"https://e.test/gone.jpg"}}}}';
		foreach ( array( 1, 2 ) as $i ) {
			Blueworx_Clubhouse_Import_Controller::handle_request(
				array( 'clubhouse_import_upload' => '1' ), $this->upload( $json ), $this->storage
			);
			Blueworx_Clubhouse_Import_Controller::handle_request(
				array( 'clubhouse_import_apply' => '1' ), array(), $this->storage
			);
		}
		$needed = $this->storage->get( Blueworx_Clubhouse_Import_Controller::IMAGES_NEEDED_KEY, array() );
		$this->assertCount( 1, $needed );
	}

	public function test_enqueue_only_loads_on_its_own_page(): void {
		Blueworx_Clubhouse_Import_Controller::enqueue( 'club-content_page_' . Blueworx_Clubhouse_Import_Controller::PAGE_SLUG );
		$this->assertNotEmpty( wp_stub_calls( 'wp_enqueue_style' ) );

		wp_stub_reset();
		Blueworx_Clubhouse_Import_Controller::enqueue( 'some-other-page' );
		$this->assertEmpty( wp_stub_calls( 'wp_enqueue_style' ) );
	}

	public function test_a_preview_names_the_sections_that_would_be_switched_off(): void {
		$model = Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ),
			$this->upload( $this->valid_json() ),
			$this->storage
		);
		$this->assertContains( 'Home · News', $model['sections_off'] );
		$this->assertNotContains( 'Home · Hero', $model['sections_off'] );
	}

	public function test_applying_with_the_tidy_up_ticked_switches_uncovered_sections_off(): void {
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ), $this->upload( $this->valid_json() ), $this->storage
		);
		$model = Blueworx_Clubhouse_Import_Controller::handle_request(
			array(
				'clubhouse_import_apply'                            => '1',
				Blueworx_Clubhouse_Import_Controller::SECTIONS_FIELD => '1',
			),
			array(),
			$this->storage
		);

		$visibility = new Blueworx_Clubhouse_Visibility( $this->storage );
		$this->assertFalse( $visibility->is_section_visible( 'home', 'news' ) );
		$this->assertTrue( $visibility->is_section_visible( 'home', 'hero' ) );

		$labels = array_column( $model['rows'], 'detail', 'label' );
		$this->assertArrayHasKey( 'Sections', $labels );
	}

	/** Unticked, an import writes content and leaves every toggle where it was. */
	public function test_applying_without_the_tidy_up_leaves_every_section_where_it_was(): void {
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ), $this->upload( $this->valid_json() ), $this->storage
		);
		Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_apply' => '1' ), array(), $this->storage
		);

		$this->assertTrue( ( new Blueworx_Clubhouse_Visibility( $this->storage ) )->is_section_visible( 'home', 'news' ) );
	}

	/**
	 * The screen's hardcoded checkbox name and the constant the controller reads
	 * it back through must stay the same string, or the box silently does nothing.
	 */
	public function test_the_tidy_up_checkbox_posts_the_name_the_controller_reads(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( array(
			'state'        => 'preview',
			'action_url'   => 'https://club.test/wp-admin/admin.php?page=clubhouse-import',
			'nonce_field'  => '',
			'rows'         => array( array( 'label' => 'Home · Hero', 'detail' => '1 field' ) ),
			'sections_off' => array(),
		) );
		$this->assertStringContainsString(
			'name="' . Blueworx_Clubhouse_Import_Controller::SECTIONS_FIELD . '"',
			$html
		);
	}

	public function test_a_preview_names_the_demo_entries_a_collection_would_replace(): void {
		wp_stub_add_post( 'clubhouse_sport', 30, 'Rugby', array( Blueworx_Clubhouse_Collection_Seeder::DEMO_META => '1' ) );
		$json  = '{"clubhouse_import":1,"collections":{"clubhouse_sport":[{"title":"Squash"}]}}';
		$model = Blueworx_Clubhouse_Import_Controller::handle_request(
			array( 'clubhouse_import_upload' => '1' ),
			$this->upload( $json ),
			$this->storage
		);
		$this->assertSame( '1 entry, replacing 1 demo entry', $model['rows'][0]['detail'] );
	}
}
