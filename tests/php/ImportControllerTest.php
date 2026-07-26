<?php
// tests/php/ImportControllerTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportControllerTest extends TestCase {

	private Blueworx_Clubhouse_Storage $storage;

	protected function setUp(): void {
		wp_stub_reset();
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
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

	public function test_it_registers_a_submenu_under_club_content(): void {
		Blueworx_Clubhouse_Import_Controller::add_menu();
		$call = wp_stub_calls( 'add_submenu_page' )[0]['args'];
		$this->assertSame( Blueworx_Clubhouse_Content_Controller::PAGE_SLUG, $call[0] );
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
		$this->assertSame( 'Global · Hero', $model['rows'][0]['label'] );

		$store = new Blueworx_Clubhouse_Content_Store( $this->storage );
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
		$store = new Blueworx_Clubhouse_Content_Store( $this->storage );
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
		$this->assertSame( 'Global · Hero — Background image', $needed[0]['label'] );
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
