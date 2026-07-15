<?php
// tests/php/ContentScreenTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ContentScreenTest extends TestCase {

	private function model(): array {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		return Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '<input type="hidden" name="_wpnonce" value="NONCE123">', 'http://x.test/admin.php?page=clubhouse-site-content' );
	}

	public function test_renders_a_tab_per_page(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		foreach ( array( 'Global', 'About', 'Membership', 'Contact', 'Log in', 'Sports', 'Teams', 'Events', 'Calendar' ) as $name ) {
			$this->assertStringContainsString( $name, $html );
		}
	}

	public function test_escapes_stored_values(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Content_Store( $s ) )->set( 'home', 'hero', 'title_lead', '<script>alert(1)</script>' );
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '', '' );
		$html  = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * The active look's :root tokens + @font-face are emitted raw inside a single
	 * <style> block (same approach as Setup_Screen — HTML-entity escaping would
	 * corrupt font-family quoting there). Outside that one block, the constraint
	 * ("no inline style= attributes, no literal hex/font names") must hold: no
	 * element carries a style="" attribute, and no hex colour or bare font-family
	 * declaration leaks into the markup itself.
	 */
	public function test_no_inline_styles_or_literal_hex_font_names_outside_the_style_block(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		$this->assertStringNotContainsString( ' style="', $html );

		$without_style = (string) preg_replace( '#<style>.*?</style>#s', '', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $without_style );
		$this->assertDoesNotMatchRegularExpression( '/font-family:\s*[\'"]?(Syne|Inter|Fraunces|Bricolage)/i', $without_style );
	}

	public function test_linkout_section_renders_manage_button(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		$this->assertStringContainsString( 'Manage sports', $html );
		$this->assertStringContainsString( 'post_type=clubhouse_sport', $html );
	}

	public function test_js_off_tab_links_and_save_present(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		$this->assertStringContainsString( 'clubhouse_content_submit', $html ); // save submit
		$this->assertStringContainsString( 'tab=about', $html );                 // tab link carries state
	}

	/** Regression guard: hide inputs MUST key off vis_page, not store_page — Global's Header stores under 'global' but hides under 'home'. */
	public function test_hidden_visibility_input_uses_vis_page_not_store_page(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="hidden[home][header]"', $html );
		$this->assertStringNotContainsString( 'name="hidden[global][header]"', $html );
	}

	/** Regression guard: handle_save only runs when this POST key is present. */
	public function test_save_submit_key_present_once_per_page_form(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		$this->assertSame( 9, substr_count( $html, 'name="clubhouse_content_submit"' ) );
	}

	/** Regression guard: the 'select' field type must render a <select> with the option map, current value selected. */
	public function test_select_field_renders_options_with_current_value_selected(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Content_Store( $s ) )->set_items( 'home', 'quick_tiles', array(
			array( 'label' => 'Join', 'href' => 'https://x.test/join', 'icon' => 'join' ),
		) );
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '', '' );
		$html  = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertMatchesRegularExpression( '/<select[^>]*name="item\[home\]\[quick_tiles\]\[0\]\[icon\]"/', $html );
		$this->assertMatchesRegularExpression( '/<option value="join"[^>]* selected/', $html );
		$this->assertStringContainsString( '<option value="">No icon</option>', $html );
	}

	/** Regression guard: every declared field of a section's field group must be rendered so the group always posts in full. */
	public function test_every_declared_field_of_a_fields_section_is_rendered(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		// About > Hero uses the shared 9-field hero set.
		$this->assertSame( 9, substr_count( $html, 'name="field[about][hero][' ) );
	}

	/** Regression guard: a loop item's every declared field (incl. toggles) is rendered, so an unticked toggle still posts within a present group. */
	public function test_loop_item_renders_every_field_including_its_toggle(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Content_Store( $s ) )->set_items( 'home', 'stats', array(
			array( 'value' => '10', 'label' => 'Teams', 'featured' => false ),
		) );
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '', '' );
		$html  = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertStringContainsString( 'name="item[home][stats][0][value]"', $html );
		$this->assertStringContainsString( 'name="item[home][stats][0][label]"', $html );
		$this->assertStringContainsString( 'name="item[home][stats][0][featured]"', $html );
	}

	/** Auto sections are read-only: an explanatory note, no editable inputs for that section. */
	public function test_auto_section_has_no_editable_fields(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		$this->assertStringContainsString( 'Built from each sport', $html );
		$this->assertStringNotContainsString( 'name="field[home][activity]', $html );
	}

	public function test_notices_are_rendered(): void {
		$s     = new Blueworx_Clubhouse_Fake_Storage();
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $s, array( array( 'type' => 'success', 'text' => 'Your changes have been saved.' ) ), '', '' );
		$html  = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertStringContainsString( 'notice notice-success', $html );
		$this->assertStringContainsString( 'Your changes have been saved.', $html );
	}

	public function test_nonce_field_and_action_url_present(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="_wpnonce" value="NONCE123"', $html );
		$this->assertStringContainsString( 'action="http://x.test/admin.php?page=clubhouse-site-content"', $html );
	}

	/** Accessibility: every rendered text-style input has a real <label for="…"> bound to its id. */
	public function test_a_field_label_is_bound_to_its_input_via_for_and_id(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		$this->assertMatchesRegularExpression( '/<label[^>]*for="([^"]+)"[^>]*>Eyebrow<\/label>.*?<input[^>]*id="\1"/s', $html );
	}

	public function test_shown_hidden_toggle_reflects_hidden_state(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Visibility( $s ) )->set_section_visible( 'home', 'ticker', false );
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '', '' );
		$html  = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertMatchesRegularExpression( '/name="hidden\[home\]\[ticker\]"[^>]*value="1"[^>]*checked/', $html );
	}
}
