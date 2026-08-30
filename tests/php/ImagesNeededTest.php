<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The pictures an import could not fetch, and the reminder an owner gets until
 * they have added them. This lived on the Club Pages screen, which is gone; it
 * is now a notice on the page editors themselves, and it prunes itself as it is
 * read rather than as a screen saves.
 */
final class ImagesNeededTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_stub_postmeta'] = array();
		$GLOBALS['wp_stub_options']  = array();
		update_option( 'clubhouse_page_id_home', 42 );
		update_option( 'clubhouse_page_id_about', 43 );
	}

	private function storage(): Blueworx_Clubhouse_Fake_Storage {
		return new Blueworx_Clubhouse_Fake_Storage();
	}

	/** @param array<int,array<string,mixed>> $entries */
	private function seed( Blueworx_Clubhouse_Storage $s, array $entries ): void {
		$s->set( Blueworx_Clubhouse_Import_Controller::IMAGES_NEEDED_KEY, $entries );
	}

	public function test_nothing_outstanding_is_no_notice(): void {
		$this->assertSame( array(), Blueworx_Clubhouse_Images_Needed::outstanding( $this->storage() ) );
	}

	public function test_an_outstanding_picture_is_named_and_linked_to_its_editor(): void {
		$s = $this->storage();
		$this->seed( $s, array(
			array( 'label' => 'Home · Hero — Background image', 'page' => 'home', 'section' => 'hero', 'field' => 'image' ),
		) );

		$out = Blueworx_Clubhouse_Images_Needed::outstanding( $s );

		$this->assertCount( 1, $out );
		$this->assertSame( 'Home · Hero — Background image', $out[0]['label'] );
		$this->assertStringContainsString( Blueworx_Clubhouse_Page_Editors::slug_for( 'home' ), $out[0]['url'] );
	}

	/**
	 * An entry whose label was never stored still has to read as something —
	 * the address named the way the editor names it, not "home/hero".
	 */
	public function test_an_entry_with_no_label_is_named_from_the_address(): void {
		$s = $this->storage();
		$this->seed( $s, array( array( 'page' => 'home', 'section' => 'hero', 'field' => 'image' ) ) );

		$out = Blueworx_Clubhouse_Images_Needed::outstanding( $s );

		$this->assertSame( Blueworx_Clubhouse_Page_Fields::address_label( 'home/hero' ), $out[0]['label'] );
	}

	/** A section this plugin no longer declares cannot be linked to, so it is dropped rather than shown as a dead link. */
	public function test_an_unknown_address_is_dropped(): void {
		$s = $this->storage();
		$this->seed( $s, array( array( 'page' => 'nowhere', 'section' => 'nothing', 'field' => 'image' ) ) );

		$this->assertSame( array(), Blueworx_Clubhouse_Images_Needed::outstanding( $s ) );
	}

	public function test_a_picture_since_added_stops_being_outstanding_and_is_forgotten(): void {
		$s = $this->storage();
		$this->seed( $s, array(
			array( 'label' => 'Home · Hero', 'page' => 'home', 'section' => 'hero', 'field' => 'image' ),
			array( 'label' => 'About · Facilities', 'page' => 'about', 'section' => 'facilities', 'field' => 'image' ),
		) );
		( new Blueworx_Clubhouse_Page_Content( $s ) )->set( 'home', 'hero', 'image', 42 );

		$out = Blueworx_Clubhouse_Images_Needed::outstanding( $s );

		$this->assertCount( 1, $out );
		$this->assertSame( 'About · Facilities', $out[0]['label'] );
		// Pruned in storage too, so it is not re-checked on every admin page load.
		$left = $s->get( Blueworx_Clubhouse_Import_Controller::IMAGES_NEEDED_KEY, array() );
		$this->assertCount( 1, $left );
		$this->assertSame( 'about', $left[0]['page'] );
	}

	/**
	 * A loop-item slot (index >= 0) lives at items[index][field], not at the
	 * section field a section-level entry (index < 0) reads — filling one item
	 * must not clear a different item's entry in the same section.
	 */
	public function test_a_filled_loop_item_clears_only_that_items_entry(): void {
		$s = $this->storage();
		$this->seed( $s, array(
			array( 'label' => 'First', 'page' => 'home', 'section' => 'news', 'field' => 'image', 'index' => 0 ),
			array( 'label' => 'Second', 'page' => 'home', 'section' => 'news', 'field' => 'image', 'index' => 1 ),
		) );
		( new Blueworx_Clubhouse_Page_Content( $s ) )->set_items( 'home', 'news', array(
			array( 'title' => 'First', 'image' => '' ),
			array( 'title' => 'Second', 'image' => 42 ),
		) );

		$out = Blueworx_Clubhouse_Images_Needed::outstanding( $s );

		$this->assertCount( 1, $out );
		$this->assertSame( 'First', $out[0]['label'] );
	}

	/** An entry stored before 'index' existed has no such key; it must still behave as section-level rather than being lost. */
	public function test_an_entry_with_no_index_key_is_treated_as_section_level(): void {
		$s = $this->storage();
		$this->seed( $s, array( array( 'label' => 'Home · Hero', 'page' => 'home', 'section' => 'hero', 'field' => 'image' ) ) );
		( new Blueworx_Clubhouse_Page_Content( $s ) )->set( 'home', 'hero', 'image', 42 );

		$this->assertSame( array(), Blueworx_Clubhouse_Images_Needed::outstanding( $s ) );
	}

	public function test_the_sentence_counts_in_words_an_owner_would_use(): void {
		$this->assertStringContainsString( '1 picture', Blueworx_Clubhouse_Images_Needed::text( 1 ) );
		$this->assertStringContainsString( 'it', Blueworx_Clubhouse_Images_Needed::text( 1 ) );
		$this->assertStringContainsString( '3 pictures', Blueworx_Clubhouse_Images_Needed::text( 3 ) );
		$this->assertStringContainsString( 'them', Blueworx_Clubhouse_Images_Needed::text( 3 ) );
	}

	/** The label comes out of an import file, so it is escaped where it is rendered. */
	public function test_a_label_is_escaped_in_the_rendered_notice(): void {
		$s = $this->storage();
		$this->seed( $s, array(
			array( 'label' => 'Home <script>alert(1)</script>', 'page' => 'home', 'section' => 'hero', 'field' => 'image' ),
		) );

		$html = Blueworx_Clubhouse_Images_Needed::html( Blueworx_Clubhouse_Images_Needed::outstanding( $s ) );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'notice notice-warning', $html );
	}

	public function test_no_outstanding_pictures_render_nothing(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Images_Needed::html( array() ) );
	}
}
