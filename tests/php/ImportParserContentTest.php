<?php
// tests/php/ImportParserContentTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportParserContentTest extends TestCase {

	/** @param array<string,mixed> $content */
	private function parse( array $content ): array {
		return Blueworx_Clubhouse_Import_Parser::parse( array(
			'clubhouse_import' => 1,
			'content'          => $content,
		) );
	}

	public function test_a_non_array_file_is_a_hard_error(): void {
		$out = Blueworx_Clubhouse_Import_Parser::parse( 'nope' );
		$this->assertNull( $out['plan'] );
		$this->assertSame( 'This file is not a ClubHouse import file.', $out['error'] );
	}

	public function test_a_missing_format_marker_is_a_hard_error(): void {
		$out = Blueworx_Clubhouse_Import_Parser::parse( array( 'content' => array() ) );
		$this->assertNull( $out['plan'] );
		$this->assertSame( 'This file is missing its "clubhouse_import" format marker.', $out['error'] );
	}

	public function test_an_unsupported_format_version_is_a_hard_error(): void {
		$out = Blueworx_Clubhouse_Import_Parser::parse( array( 'clubhouse_import' => 99, 'content' => array() ) );
		$this->assertNull( $out['plan'] );
		$this->assertStringContainsString( 'version 99', $out['error'] );
	}

	public function test_a_file_with_neither_content_nor_collections_is_a_hard_error(): void {
		$out = Blueworx_Clubhouse_Import_Parser::parse( array( 'clubhouse_import' => 1 ) );
		$this->assertNull( $out['plan'] );
		$this->assertSame( 'This file contains no content or collections to import.', $out['error'] );
	}

	public function test_a_known_field_is_sanitised_onto_the_plan(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array( 'eyebrow' => '  Est. 1974 <script> ' ) ) ) );
		$this->assertSame( 'Est. 1974', $out['plan']->fields()['home']['hero']['eyebrow'] );
		$this->assertSame( '', $out['error'] );
	}

	public function test_content_is_keyed_by_store_page_not_tab(): void {
		// The Global tab's Header section stores under the 'global' store_page.
		$out = $this->parse( array( 'global' => array( 'header' => array( 'join' => 'Join us' ) ) ) );
		$this->assertSame( 'Join us', $out['plan']->fields()['global']['header']['join'] );
	}

	public function test_an_unknown_section_is_warned_and_dropped(): void {
		$out = $this->parse( array( 'home' => array( 'nope' => array( 'x' => 'y' ) ) ) );
		$this->assertTrue( $out['plan']->is_empty() );
		$this->assertSame( array( 'Ignored unknown section "home/nope".' ), $out['plan']->warnings() );
	}

	public function test_an_unknown_field_is_warned_and_dropped_without_losing_its_siblings(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array( 'evil' => 'x', 'eyebrow' => 'ok' ) ) ) );
		$this->assertSame( 'ok', $out['plan']->fields()['home']['hero']['eyebrow'] );
		$this->assertArrayNotHasKey( 'evil', $out['plan']->fields()['home']['hero'] );
		$this->assertSame( array( 'Ignored unknown field "home/hero/evil".' ), $out['plan']->warnings() );
	}

	public function test_a_page_that_is_not_an_object_is_warned(): void {
		$out = $this->parse( array( 'home' => 'nope' ) );
		$this->assertSame( array( 'Ignored "home": expected a group of sections.' ), $out['plan']->warnings() );
	}

	public function test_loop_items_are_sanitised_onto_the_plan(): void {
		$out = $this->parse( array( 'membership' => array( 'faq' => array( 'items' => array(
			array( 'question' => 'When?', 'answer' => 'Now' ),
		) ) ) ) );
		$this->assertSame( 'When?', $out['plan']->items()['membership']['faq'][0]['question'] );
	}

	public function test_loop_items_gain_every_declared_field(): void {
		$out = $this->parse( array( 'home' => array( 'stats' => array( 'items' => array(
			array( 'value' => '450', 'label' => 'Members' ),
		) ) ) ) );
		$this->assertFalse( $out['plan']->items()['home']['stats'][0]['featured'] );
	}

	public function test_items_on_a_non_loop_section_is_warned(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array( 'items' => array( array( 'a' => 'b' ) ) ) ) ) );
		$this->assertSame( array( 'Ignored "home/hero/items": this section is not a repeatable list.' ), $out['plan']->warnings() );
	}

	public function test_non_list_items_are_warned(): void {
		$out = $this->parse( array( 'membership' => array( 'faq' => array( 'items' => 'nope' ) ) ) );
		$this->assertSame( array( 'Ignored "membership/faq/items": expected a list of items.' ), $out['plan']->warnings() );
	}

	public function test_an_empty_items_list_is_not_planned(): void {
		// An empty list must not silently clear the section: Import_Preview skips
		// zero-count sections, so applying this would delete existing entries with
		// nothing in the preview to show it was going to happen.
		$out = $this->parse( array( 'membership' => array( 'faq' => array( 'items' => array() ) ) ) );
		$this->assertTrue( $out['plan']->is_empty() );
		$this->assertSame( array( 'Ignored "membership/faq/items": the list is empty.' ), $out['plan']->warnings() );
	}

	public function test_a_section_level_toggle_explicit_false_is_read_as_false(): void {
		// Presence-means-true (correct for a form POST) is wrong for a JSON file,
		// where the key is present and genuinely carries `false`.
		$out = $this->parse( array( 'global' => array( 'header' => array( 'banner_show' => false ) ) ) );
		$this->assertFalse( $out['plan']->fields()['global']['header']['banner_show'] );
	}

	public function test_a_section_level_toggle_explicit_true_is_read_as_true(): void {
		$out = $this->parse( array( 'global' => array( 'header' => array( 'banner_show' => true ) ) ) );
		$this->assertTrue( $out['plan']->fields()['global']['header']['banner_show'] );
	}

	public function test_a_section_level_toggle_absent_is_left_out_of_the_plan(): void {
		$out = $this->parse( array( 'global' => array( 'header' => array( 'join' => 'Join us' ) ) ) );
		$this->assertArrayNotHasKey( 'banner_show', $out['plan']->fields()['global']['header'] );
	}

	public function test_a_loop_item_toggle_explicit_false_is_read_as_false(): void {
		$out = $this->parse( array( 'home' => array( 'stats' => array( 'items' => array(
			array( 'value' => '450', 'label' => 'Members', 'featured' => false ),
		) ) ) ) );
		$this->assertFalse( $out['plan']->items()['home']['stats'][0]['featured'] );
	}

	public function test_a_loop_item_toggle_explicit_true_is_read_as_true(): void {
		$out = $this->parse( array( 'home' => array( 'stats' => array( 'items' => array(
			array( 'value' => '450', 'label' => 'Members', 'featured' => true ),
		) ) ) ) );
		$this->assertTrue( $out['plan']->items()['home']['stats'][0]['featured'] );
	}

	public function test_a_loop_item_toggle_absent_is_read_as_false(): void {
		$out = $this->parse( array( 'home' => array( 'stats' => array( 'items' => array(
			array( 'value' => '450', 'label' => 'Members' ),
		) ) ) ) );
		$this->assertFalse( $out['plan']->items()['home']['stats'][0]['featured'] );
	}

	public function test_an_image_object_is_queued_not_stored(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array(
			'image' => array( 'url' => 'https://e.test/a.jpg', 'alt' => 'Pavilion' ),
		) ) ) );
		$this->assertSame( array(), $out['plan']->fields() ); // nothing written directly
		$img = $out['plan']->images()[0];
		$this->assertSame( 'https://e.test/a.jpg', $img['url'] );
		$this->assertSame( 'Pavilion', $img['alt'] );
		$this->assertSame( 'Global · Hero — Background image', $img['label'] );
		$this->assertSame( -1, $img['index'] );
	}

	public function test_a_bare_image_string_is_accepted(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array( 'image' => 'https://e.test/a.jpg' ) ) ) );
		$this->assertSame( 'https://e.test/a.jpg', $out['plan']->images()[0]['url'] );
	}

	public function test_a_non_http_image_url_is_warned(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array( 'image' => 'javascript:alert(1)' ) ) ) );
		$this->assertSame( array(), $out['plan']->images() );
		$this->assertSame( array( 'Ignored "home/hero/image": expected an image URL.' ), $out['plan']->warnings() );
	}

	public function test_a_loop_item_image_is_queued_with_its_index(): void {
		$out = $this->parse( array( 'home' => array( 'news' => array( 'items' => array(
			array( 'title' => 'First' ),
			array( 'title' => 'Second', 'image' => 'https://e.test/n.jpg' ),
		) ) ) ) );
		$this->assertSame( 1, $out['plan']->images()[0]['index'] );
		$this->assertSame( 'news', $out['plan']->images()[0]['section'] );
		// The item itself keeps the empty sentinel until the applier fills it in.
		$this->assertSame( '', $out['plan']->items()['home']['news'][1]['image'] );
	}

	public function test_a_select_value_outside_its_options_falls_back(): void {
		$out = $this->parse( array( 'home' => array( 'quick_tiles' => array( 'items' => array(
			array( 'label' => 'Join', 'icon' => 'evil' ),
		) ) ) ) );
		$this->assertSame( '', $out['plan']->items()['home']['quick_tiles'][0]['icon'] );
	}

	public function test_a_non_url_scalar_loop_image_is_warned_and_leaves_the_sentinel(): void {
		$out = $this->parse( array( 'home' => array( 'news' => array( 'items' => array(
			array( 'title' => 'First', 'image' => '12345' ),
		) ) ) ) );
		$this->assertSame( '', $out['plan']->items()['home']['news'][0]['image'] );
		$this->assertSame( array(), $out['plan']->images() );
		$this->assertNotSame( array(), $out['plan']->warnings() );
	}

	public function test_a_boolean_loop_image_is_warned_and_leaves_the_sentinel(): void {
		$out = $this->parse( array( 'home' => array( 'news' => array( 'items' => array(
			array( 'title' => 'First', 'image' => true ),
		) ) ) ) );
		$this->assertSame( '', $out['plan']->items()['home']['news'][0]['image'] );
		$this->assertSame( array(), $out['plan']->images() );
		$this->assertNotSame( array(), $out['plan']->warnings() );
	}
}
