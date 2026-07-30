<?php
// tests/php/MenuPanelTest.php

use PHPUnit\Framework\TestCase;

final class MenuPanelTest extends TestCase {

	private function model( array $tree ): array {
		return array(
			'tree'        => $tree,
			'targets'     => Blueworx_Clubhouse_Link_Catalogue::targets( new Blueworx_Clubhouse_Demo_Collections() ),
			'action_url'  => 'http://x.test/wp-admin/admin.php?page=clubhouse-site-content',
			'nonce_field' => '<input type="hidden" name="_wpnonce" value="abc">',
		);
	}

	public function test_a_row_renders_its_label_and_selected_target(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'Say hello', 'target' => 'page:contact', 'children' => array() ),
		) ) );
		$this->assertStringContainsString( 'name="menu[0][label]"', $html );
		$this->assertStringContainsString( 'value="Say hello"', $html );
		$this->assertStringContainsString( '<option value="page:contact" selected>', $html );
	}

	public function test_targets_are_grouped_in_the_picker(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( Blueworx_Clubhouse_Menu::DEFAULTS ) );
		$this->assertStringContainsString( '<optgroup label="Pages">', $html );
		$this->assertStringContainsString( '<optgroup label="Sections">', $html );
		$this->assertStringContainsString( '<optgroup label="Sports">', $html );
	}

	public function test_a_child_row_uses_the_nested_field_names(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'About', 'target' => 'page:about', 'children' => array(
				array( 'label' => 'History', 'target' => 'anchor:about.history' ),
			) ),
		) ) );
		$this->assertStringContainsString( 'name="menu[0][children][0][label]"', $html );
		$this->assertStringContainsString( 'clubhouse_menu_outdent[0-0]', $html );
	}

	public function test_the_first_row_cannot_move_up_or_indent(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'Home', 'target' => 'page:home', 'children' => array() ),
			array( 'label' => 'About', 'target' => 'page:about', 'children' => array() ),
		) ) );
		$this->assertMatchesRegularExpression(
			'/name="clubhouse_menu_up\[0\]"[^>]*\bdisabled\b/',
			$html
		);
		$this->assertMatchesRegularExpression(
			'/name="clubhouse_menu_indent\[0\]"[^>]*\bdisabled\b/',
			$html
		);
	}

	public function test_a_custom_url_target_shows_its_url(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'Shop', 'target' => 'url:https://shop.test', 'children' => array() ),
		) ) );
		$this->assertStringContainsString( '<option value="url:" selected>', $html );
		$this->assertStringContainsString( 'name="menu[0][custom]"', $html );
		$this->assertStringContainsString( 'value="https://shop.test"', $html );
	}

	public function test_a_stored_target_that_no_longer_exists_is_flagged(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'Ghost', 'target' => 'filter:sports:ghost', 'children' => array() ),
		) ) );
		$this->assertStringContainsString( 'target unavailable', $html );
	}

	/**
	 * Marking no option selected would leave the browser preselecting the
	 * first option in the list — and saving the form would silently overwrite
	 * the real (if currently unresolvable) target. The unknown target must be
	 * its own selected option so a resave round-trips it unchanged.
	 */
	public function test_a_stored_target_that_no_longer_exists_is_still_the_selected_option(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'Ghost', 'target' => 'filter:sports:ghost', 'children' => array() ),
		) ) );
		$this->assertStringContainsString( '<option value="filter:sports:ghost" selected>', $html );
	}

	public function test_labels_and_urls_are_escaped(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => '"><script>x</script>', 'target' => 'page:home', 'children' => array() ),
		) ) );
		$this->assertStringNotContainsString( '<script>', $html );
	}
}
