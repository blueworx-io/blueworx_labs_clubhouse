<?php
// tests/php/ImportApplierContentTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportApplierContentTest extends TestCase {

	private Blueworx_Clubhouse_Storage $storage;

	protected function setUp(): void {
		wp_stub_reset();
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
		// The import writes onto blocks, so there have to be blocks to write to.
		Blueworx_Clubhouse_Test_Site::composer( $this->storage );
	}

	/** What the import left on the block that holds this address. */
	private function stored( string $address, string $field, mixed $default = null ): mixed {
		return Blueworx_Clubhouse_Test_Site::read( $this->storage, $address, $field, $default );
	}

	/** @return array<int,array<string,mixed>> */
	private function items( string $address ): array {
		return Blueworx_Clubhouse_Test_Site::items( $this->storage, $address );
	}

	public function test_fields_are_written_to_the_block_that_holds_them(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 'Est. 1974', $this->stored( 'home/hero', 'eyebrow' ) );
	}

	public function test_items_are_written_to_the_block_that_holds_them(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'membership', 'faq', array( array( 'question' => 'Q', 'answer' => 'A' ) ) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 'Q', $this->items( 'membership/faq' )[0]['question'] );
	}

	public function test_absent_sections_are_left_untouched(): void {
		Blueworx_Clubhouse_Test_Site::write( $this->storage, 'about/hero', array( 'eyebrow' => 'Do not clobber me' ) );
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 'Do not clobber me', $this->stored( 'about/hero', 'eyebrow' ) );
	}

	/**
	 * An address whose block this site has not got is skipped rather than
	 * recreated — an import is not the place to put a deleted block back.
	 */
	public function test_an_address_with_no_block_is_skipped_rather_than_recreated(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::composer( $storage );
		$library = new Blueworx_Clubhouse_Block_Library( $storage );
		$library->delete( $library->by_address( 'about/hero' ) );

		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'about', 'hero', 'eyebrow', 'Est. 1974' );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $storage );

		$this->assertSame( '', $library->by_address( 'about/hero' ) );
	}

	public function test_a_section_image_is_sideloaded_and_its_id_stored(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/a.jpg', 'Pavilion', 'Global · Hero — Background image' );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$call = wp_stub_calls( 'media_sideload_image' )[0];
		$this->assertSame( 'https://e.test/a.jpg', $call['args'][0] );
		$this->assertSame( 'Pavilion', $call['args'][2] );
		$this->assertSame( 'id', $call['args'][3] );
		$this->assertSame( 500, $this->stored( 'home/hero', 'image' ) );
	}

	public function test_a_loop_item_image_is_written_into_its_item(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'home', 'news', array(
			array( 'title' => 'First', 'image' => '' ),
			array( 'title' => 'Second', 'image' => '' ),
		) );
		$plan->add_image( 'home', 'news', 'image', 'https://e.test/n.jpg', '', 'Global · News — Image', 1 );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$items = $this->items( 'home/news' );
		$this->assertSame( '', $items[0]['image'] );
		$this->assertSame( 500, $items[1]['image'] );
	}

	public function test_a_failed_image_warns_and_lands_on_the_still_needed_list(): void {
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/gone.jpg', '', 'Global · Hero — Background image' );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$this->assertSame( '', $this->stored( 'home/hero', 'image', '' ) );
		$this->assertSame( 'Global · Hero — Background image', $out['images_needed'][0]['label'] );
		$this->assertStringContainsString( 'https://e.test/gone.jpg', $out['warnings'][0] );
	}

	/**
	 * The still-needed entry names the block and the field on it, because that
	 * is what the Blocks screen can open — the old page-and-section address
	 * cannot be linked to any more.
	 */
	public function test_a_still_needed_entry_names_the_block_to_fix_it_on(): void {
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/gone.jpg', '', 'Global · Hero — Background image' );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$library = new Blueworx_Clubhouse_Block_Library( $this->storage );
		$this->assertSame( $library->by_address( 'home/hero' ), $out['images_needed'][0]['block'] );
		$this->assertSame( 'image', $out['images_needed'][0]['field'] );
	}

	public function test_a_failed_image_does_not_stop_the_rest_of_the_import(): void {
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/gone.jpg', '', 'Global · Hero — Background image' );
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 'Est. 1974', $this->stored( 'home/hero', 'eyebrow' ) );
	}

	public function test_an_image_for_an_item_index_that_does_not_exist_is_skipped_safely(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'home', 'news', array( array( 'title' => 'Only one', 'image' => '' ) ) );
		$plan->add_image( 'home', 'news', 'image', 'https://e.test/n.jpg', '', 'Global · News — Image', 9 );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertCount( 1, $this->items( 'home/news' ) );
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
	 * entry — the images-needed notice needs it to tell a loop-item slot
	 * (items[index][field]) apart from a section-level one.
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
		$this->assertSame( 500, $this->stored( 'home/hero', 'image' ) );
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

	/**
	 * The cookie notice is stored on the footer block, under its own prefix, so
	 * importing its wording must not land on the footer's own text fields.
	 */
	public function test_the_cookie_notice_is_written_alongside_the_footer_not_over_it(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'global', 'cookies', 'text', 'We use cookies.' );
		$plan->add_field( 'global', 'footer', 'tagline', 'Since 1974' );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$this->assertSame( 'We use cookies.', $this->stored( 'global/cookies', 'text' ) );
		$this->assertSame( 'Since 1974', $this->stored( 'global/footer', 'tagline' ) );
	}
}
