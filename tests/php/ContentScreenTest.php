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
		foreach ( array( 'Global', 'Home', 'About', 'Membership', 'Contact', 'Log in', 'Sports', 'Teams', 'Events', 'Calendar' ) as $name ) {
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

	/**
	 * Regression guard: handle_save() only runs when clubhouse_content_submit is
	 * posted. Per the HTML spec only the *activated* submit button contributes its
	 * name/value, so the Save button alone does NOT cover the Add/Remove buttons —
	 * each form must also carry the key as a HIDDEN input, or those clicks are
	 * silent no-ops. Asserting the hidden input specifically (not just the name
	 * anywhere) is what makes this guard real: the earlier count-of-9 assertion
	 * passed while Add/Remove were broken.
	 */
	public function test_every_page_form_posts_the_submit_key_as_a_hidden_input(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		// +1: the Menu tab's own form (Blueworx_Clubhouse_Menu_Panel) carries the
		// same hidden submit-gate input as every catalogue page's form.
		$this->assertSame(
			count( Blueworx_Clubhouse_Content_Catalogue::pages() ) + 1,
			preg_match_all( '/<input type="hidden" name="clubhouse_content_submit" value="1">/', $html ),
			'every page form (including the Menu tab) must post the submit key regardless of which button was activated'
		);
	}

	/** The hidden inputs of the first <form> — i.e. what any submission from it carries. */
	private function hidden_inputs_of_first_form( string $html ): array {
		$start = strpos( $html, '<form' );
		$end   = strpos( $html, '</form>', (int) $start );
		$form  = substr( $html, (int) $start, (int) $end - (int) $start );
		preg_match_all( '/<input type="hidden" name="([^"]+)" value="([^"]*)">/', $form, $m, PREG_SET_ORDER );
		$post = array();
		foreach ( $m as $hit ) {
			$post[ html_entity_decode( $hit[1], ENT_QUOTES ) ] = html_entity_decode( $hit[2], ENT_QUOTES );
		}
		return $post;
	}

	/**
	 * The hidden inputs of the form belonging to one tab. The screen renders a form
	 * per tab, and handle_save() scopes to the tab the POST names, so a round-trip
	 * test has to post the form that actually owns the section under test.
	 *
	 * @return array<string,string>
	 */
	private function hidden_inputs_of_tab( string $html, string $tab ): array {
		$offset = 0;
		while ( false !== ( $start = strpos( $html, '<form', $offset ) ) ) {
			$end  = (int) strpos( $html, '</form>', $start );
			$form = substr( $html, $start, $end - $start );
			// Parse the extracted form directly — hidden_inputs_of_first_form() would
			// re-cut it at a '</form>' this substring no longer contains.
			preg_match_all( '/<input type="hidden" name="([^"]+)" value="([^"]*)">/', $form, $m, PREG_SET_ORDER );
			$post = array();
			foreach ( $m as $hit ) {
				$post[ html_entity_decode( $hit[1], ENT_QUOTES ) ] = html_entity_decode( $hit[2], ENT_QUOTES );
			}
			if ( ( $post['clubhouse_content_tab'] ?? '' ) === $tab ) {
				return $post;
			}
			$offset = $end + 1;
		}
		$this->fail( 'no form found for tab ' . $tab );
	}

	/**
	 * End-to-end guard for the defect this screen shipped with: render the real
	 * screen, derive the $_POST an "Add item" click actually produces (the form's
	 * hidden inputs + only the activated button, per the HTML spec), and feed it to
	 * the real controller. Before the fix this POST carried no clubhouse_content_submit,
	 * so handle_save() never ran and the row was never added — with no error shown.
	 */
	public function test_add_item_click_round_trips_through_handle_save(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$model   = Blueworx_Clubhouse_Content_Controller::build_model( $storage, array(), '', 'http://x.test/admin.php?page=clubhouse-site-content' );
		$html    = Blueworx_Clubhouse_Content_Screen::render( $model );

		$post = $this->hidden_inputs_of_tab( $html, 'home' );
		$this->assertArrayHasKey( 'clubhouse_content_submit', $post, 'an Add click must still post the save gate' );
		$this->assertSame( 'home', $post['clubhouse_content_tab'] );
		$post['clubhouse_content_add'] = array( 'home' => array( 'ticker' => '1' ) );

		Blueworx_Clubhouse_Content_Controller::handle_save( $post, $storage );
		$this->assertCount( 1, ( new Blueworx_Clubhouse_Content_Store( $storage ) )->get_items( 'home', 'ticker' ) );
	}

	/** The mirror of the Add round-trip: a Remove click must also reach handle_save. */
	public function test_remove_item_click_round_trips_through_handle_save(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Content_Store( $storage ) )->set_items( 'home', 'ticker', array(
			array( 'text' => 'First message' ),
			array( 'text' => 'Second message' ),
		) );
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $storage, array(), '', 'http://x.test/admin.php?page=clubhouse-site-content' );
		$html  = Blueworx_Clubhouse_Content_Screen::render( $model );

		$post = $this->hidden_inputs_of_tab( $html, 'home' );
		$post['clubhouse_content_remove'] = array( 'home' => array( 'ticker' => '0' ) );

		Blueworx_Clubhouse_Content_Controller::handle_save( $post, $storage );
		$items = ( new Blueworx_Clubhouse_Content_Store( $storage ) )->get_items( 'home', 'ticker' );
		$this->assertCount( 1, $items );
		$this->assertSame( 'Second message', $items[0]['text'], 'item 0 removed, item 1 survives' );
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
		( new Blueworx_Clubhouse_Content_Store( $s ) )->set_items( 'membership', 'tiers', array(
			array( 'name' => 'Adult', 'price' => '£295', 'featured' => false ),
		) );
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '', '' );
		$html  = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertStringContainsString( 'name="item[membership][tiers][0][name]"', $html );
		$this->assertStringContainsString( 'name="item[membership][tiers][0][price]"', $html );
		$this->assertStringContainsString( 'name="item[membership][tiers][0][featured]"', $html );
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

	/**
	 * Task 8's stylesheet must be tokens-only (var(--color-*)/var(--font-*)/
	 * var(--radius-*)) — no literal hex colours, no hardcoded look font names.
	 * Mirrors the existing AdminSetupStylesheetTest guard: intentionally omits
	 * "Inter" from the font blocklist, since it's a substring of the entirely
	 * legitimate CSS value "cursor: pointer" (used throughout admin-setup.css
	 * too) and would otherwise false-positive on ordinary, token-only CSS.
	 */
	public function test_stylesheet_file_has_no_literal_colours_or_font_names(): void {
		$css = file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/admin-content.css' );
		$this->assertDoesNotMatchRegularExpression( '/(?<!&)#[0-9a-fA-F]{3,6}\b/', $css );
		$this->assertDoesNotMatchRegularExpression( '/(Syne|Fraunces|Bricolage|Mulish|Hanken)/i', $css );
	}

	/**
	 * The guarantee that guards this whole class of defect: pressing Save without
	 * editing anything must leave the rendered site byte-identical.
	 *
	 * Every field on a tab posts on every Save, so each type's "untouched" value
	 * has to sanitise to the same sentinel cget() falls back on. When it doesn't,
	 * a no-edit Save silently rewrites the site while reporting success — which is
	 * exactly what shipped: `image` stored 0, cget treated 0 as a real override,
	 * and the Home hero rendered <img src="0"> with its fallback panel gone.
	 *
	 * Asserts the full document, so it covers every field type on the tab at once
	 * rather than one hand-picked field.
	 */
	public function test_a_no_edit_save_leaves_the_rendered_site_byte_identical(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$vis      = new Blueworx_Clubhouse_Visibility( $storage );
		$coll     = new Blueworx_Clubhouse_Demo_Collections();

		$before = Blueworx_Clubhouse_Page_Map::render( '', $branding, $vis, $coll, '', new Blueworx_Clubhouse_Content_Store( $storage ) );

		// The $_POST a real "Save" click produces on the Global tab, with nothing edited:
		// every rendered input posts its current (empty) value.
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $storage, array(), '', 'http://x.test/admin.php?page=clubhouse-site-content' );
		// The Home tab, not Global: Global is header/footer only now, too few fields
		// for this guard to prove anything.
		$home = null;
		foreach ( $model['catalogue'] as $page ) {
			if ( 'home' === $page['tab'] ) {
				$home = $page;
			}
		}
		$this->assertNotNull( $home );
		$posted = 0;
		$post   = array( 'clubhouse_content_tab' => 'home', 'clubhouse_content_submit' => '1', 'field' => array(), 'hidden' => array() );
		foreach ( $home['sections'] as $section ) {
			foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
				$post['field'][ (string) $section['store_page'] ][ (string) $section['key'] ][ (string) $field['key'] ] = '';
				++$posted;
			}
			// The screen renders a Hidden switch per section, so a real no-edit POST
			// carries back the ones already switched on. Omitting them would post
			// "nothing is hidden" and silently re-show every default-hidden section.
			if ( ! empty( $section['hidden'] ) ) {
				$post['hidden'][ (string) $section['vis_page'] ][ (string) $section['key'] ] = '1';
			}
		}
		// Guard against this test silently passing by posting nothing at all.
		$this->assertGreaterThan( 20, $posted, 'the Global tab must actually post its fields for this to prove anything' );
		Blueworx_Clubhouse_Content_Controller::handle_save( $post, $storage );

		$after = Blueworx_Clubhouse_Page_Map::render( '', $branding, $vis, $coll, '', new Blueworx_Clubhouse_Content_Store( $storage ) );
		$this->assertSame( $before, $after, 'a Save with no edits must not change the rendered page' );
	}

	public function test_a_notice_can_carry_links_to_sections(): void {
		$model = $this->model(); // the existing helper in this file
		$model['notices'] = array( array(
			'type'  => 'warning',
			'text'  => 'Two pictures are still missing.',
			'links' => array(
				array( 'label' => 'Global · Hero', 'tab' => 'global', 'sec' => 'hero' ),
			),
		) );
		$html = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertStringContainsString( 'Two pictures are still missing.', $html );
		$this->assertStringContainsString( 'Global · Hero', $html );
		$this->assertStringContainsString( 'clubhouse-sec-global-hero', $html );
	}

	public function test_notice_links_are_escaped(): void {
		$model = $this->model();
		$model['notices'] = array( array(
			'type'  => 'warning',
			'text'  => 'Missing.',
			'links' => array( array( 'label' => '<img src=x>', 'tab' => 'global', 'sec' => 'hero' ) ),
		) );
		$html = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertStringNotContainsString( '<img src=x>', $html );
	}

	public function test_a_notice_without_links_is_unchanged(): void {
		$model = $this->model();
		$model['notices'] = array( array( 'type' => 'success', 'text' => 'Your changes have been saved.' ) );
		$html = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertStringContainsString( 'Your changes have been saved.', $html );
	}

	/**
	 * Club Content carries the same top-bar role tags as every other ClubHouse
	 * page, and the same rule: prebuilt markup from the controller, so an owner's
	 * screen never shows the access map.
	 */
	public function test_role_tags_render_in_the_top_bar_when_supplied(): void {
		$model              = $this->model();
		$model['role_tags'] = Blueworx_Clubhouse_Access_Screen::role_tags( array( 'Administrator' ) );
		$html               = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertStringContainsString( 'class="clubhouse-roletags"', $html );
		$this->assertMatchesRegularExpression( '/clubhouse-head__titles.*clubhouse-roletags.*<\/div>/s', $html );
	}

	public function test_no_role_tags_for_anyone_but_an_administrator(): void {
		$model              = $this->model();
		$model['role_tags'] = '';
		$this->assertStringNotContainsString( 'clubhouse-roletag', Blueworx_Clubhouse_Content_Screen::render( $model ) );
	}
}
