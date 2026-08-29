<?php
// tests/php/ImportApplierContentTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportApplierContentTest extends TestCase {

	private Blueworx_Clubhouse_Storage $storage;

	protected function setUp(): void {
		wp_stub_reset();
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
		// Import_Applier now writes through Page_Content, which resolves a page
		// key to a post id and silently no-ops when there is none. Every page
		// this suite writes to needs a post id fixture, or its assertions would
		// pass vacuously against a write that never landed anywhere.
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 101 );
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 102 );
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'membership' ), 103 );
	}

	private function store(): Blueworx_Clubhouse_Page_Content {
		return new Blueworx_Clubhouse_Page_Content( $this->storage );
	}

	public function test_fields_are_written_to_the_content_store(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 'Est. 1974', $this->store()->get( 'home', 'hero', 'eyebrow' ) );
	}

	public function test_items_are_written_to_the_content_store(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'membership', 'faq', array( array( 'question' => 'Q', 'answer' => 'A' ) ) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 'Q', $this->store()->get_items( 'membership', 'faq' )[0]['question'] );
	}

	public function test_absent_sections_are_left_untouched(): void {
		$this->store()->set( 'about', 'hero', 'eyebrow', 'Do not clobber me' );
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 'Do not clobber me', $this->store()->get( 'about', 'hero', 'eyebrow' ) );
	}

	public function test_a_section_image_is_sideloaded_and_its_id_stored(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/a.jpg', 'Pavilion', 'Global · Hero — Background image' );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$call = wp_stub_calls( 'media_sideload_image' )[0];
		$this->assertSame( 'https://e.test/a.jpg', $call['args'][0] );
		$this->assertSame( 'Pavilion', $call['args'][2] );
		$this->assertSame( 'id', $call['args'][3] );
		$this->assertSame( 500, $this->store()->get( 'home', 'hero', 'image' ) );
	}

	public function test_a_loop_item_image_is_written_into_its_item(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'home', 'news', array(
			array( 'title' => 'First', 'image' => '' ),
			array( 'title' => 'Second', 'image' => '' ),
		) );
		$plan->add_image( 'home', 'news', 'image', 'https://e.test/n.jpg', '', 'Global · News — Image', 1 );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$items = $this->store()->get_items( 'home', 'news' );
		$this->assertSame( '', $items[0]['image'] );
		$this->assertSame( 500, $items[1]['image'] );
	}

	public function test_a_failed_image_warns_and_lands_on_the_still_needed_list(): void {
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/gone.jpg', '', 'Global · Hero — Background image' );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$this->assertSame( '', $this->store()->get( 'home', 'hero', 'image', '' ) );
		$this->assertSame( 'Global · Hero — Background image', $out['images_needed'][0]['label'] );
		$this->assertStringContainsString( 'https://e.test/gone.jpg', $out['warnings'][0] );
	}

	public function test_a_failed_image_does_not_stop_the_rest_of_the_import(): void {
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/gone.jpg', '', 'Global · Hero — Background image' );
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 'Est. 1974', $this->store()->get( 'home', 'hero', 'eyebrow' ) );
	}

	public function test_an_image_for_an_item_index_that_does_not_exist_is_skipped_safely(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'home', 'news', array( array( 'title' => 'Only one', 'image' => '' ) ) );
		$plan->add_image( 'home', 'news', 'image', 'https://e.test/n.jpg', '', 'Global · News — Image', 9 );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertCount( 1, $this->store()->get_items( 'home', 'news' ) );
		$this->assertNotSame( array(), $out['warnings'] );
	}

	public function test_the_result_reports_what_it_changed(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$plan->add_field( 'home', 'hero', 'lede', 'All welcome' );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 'Home · Hero', $out['rows'][0]['label'] );
		$this->assertSame( '2 fields saved', $out['rows'][0]['detail'] );
	}

	/**
	 * A failed loop-item image must keep its 'index' in the still-needed
	 * entry — Content_Controller::clear_filled_images() needs it to tell a
	 * loop-item slot (items[index][field]) apart from a section-level one.
	 */
	public function test_a_failed_loop_item_image_records_its_index(): void {
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'home', 'news', array(
			array( 'title' => 'First', 'image' => '' ),
			array( 'title' => 'Second', 'image' => '' ),
		) );
		$plan->add_image( 'home', 'news', 'image', 'https://e.test/gone.jpg', '', 'Global · News — Image', 1 );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 1, $out['images_needed'][0]['index'] );
	}

	/**
	 * A plan can sit in the transient across a plugin upgrade (up to an hour):
	 * one stored before 'index' existed has images with no such key at all.
	 * place_image() must default it to -1 (section-level) rather than emit
	 * undefined-array-key warnings and silently drop the image.
	 */
	public function test_an_image_missing_its_index_key_is_treated_as_section_level(): void {
		$plan = Blueworx_Clubhouse_Import_Plan::from_array( array(
			'images' => array( array(
				'page' => 'home', 'section' => 'hero', 'field' => 'image',
				'url' => 'https://e.test/a.jpg', 'alt' => '', 'label' => 'Global · Hero — Background image',
			) ),
		) );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 500, $this->store()->get( 'home', 'hero', 'image' ) );
		$this->assertSame( array(), $out['images_needed'] );
	}

	/** Same defensive default, on the failure path that records a still-needed entry. */
	public function test_a_failed_image_missing_its_index_key_is_recorded_as_section_level(): void {
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$plan = Blueworx_Clubhouse_Import_Plan::from_array( array(
			'images' => array( array(
				'page' => 'home', 'section' => 'hero', 'field' => 'image',
				'url' => 'https://e.test/gone.jpg', 'alt' => '', 'label' => 'Global · Hero — Background image',
			) ),
		) );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( -1, $out['images_needed'][0]['index'] );
	}

	public function test_a_successful_image_is_reported(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/a.jpg', '', 'Global · Hero — Background image' );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$last = end( $out['rows'] );
		$this->assertSame( 'Images', $last['label'] );
		$this->assertSame( '1 fetched', $last['detail'] );
	}
}
