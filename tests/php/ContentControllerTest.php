<?php
// tests/php/ContentControllerTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ContentControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	private function storage(): Blueworx_Clubhouse_Storage {
		return new Blueworx_Clubhouse_Fake_Storage();
	}

	public function test_saves_and_sanitises_a_text_field(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'hero' => array( 'title_highlight' => '  One club <script>  ' ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( 'One club', $store->get( 'home', 'hero', 'title_highlight' ) ); // tags stripped, trimmed
	}

	public function test_saves_loop_items_for_a_section(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'membership',
			'item' => array( 'membership' => array( 'faq' => array(
				array( 'question' => 'Q1', 'answer' => 'A1' ),
				array( 'question' => 'Q2', 'answer' => 'A2' ),
			) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertCount( 2, $store->get_items( 'membership', 'faq' ) );
		$this->assertSame( 'Q2', $store->get_items( 'membership', 'faq' )[1]['question'] );
	}

	public function test_unknown_field_keys_are_ignored(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'hero' => array( 'evil' => 'x', 'eyebrow' => 'ok' ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( 'ok', $store->get( 'home', 'hero', 'eyebrow' ) );
		$this->assertNull( $store->get( 'home', 'hero', 'evil' ) );
	}

	public function test_section_visibility_toggle_persists(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'hidden' => array( 'home' => array( 'ticker' => '1' ) ), // present = hide
		), $s );
		$vis = new Blueworx_Clubhouse_Visibility( $s );
		$this->assertFalse( $vis->is_section_visible( 'home', 'ticker' ) );
	}

	public function test_visibility_defaults_to_shown_when_hidden_flag_absent(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
		), $s );
		$vis = new Blueworx_Clubhouse_Visibility( $s );
		$this->assertTrue( $vis->is_section_visible( 'home', 'ticker' ) );
	}

	public function test_image_field_stored_as_attachment_id(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'clubhouse' => array( 'image' => '42abc' ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( 42, $store->get( 'home', 'clubhouse', 'image' ) );
	}

	public function test_select_field_only_accepts_known_option_keys(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'item' => array( 'home' => array( 'quick_tiles' => array(
				array( 'label' => 'Join', 'href' => 'https://x.test/join', 'icon' => 'join' ),
				array( 'label' => 'Bad', 'href' => 'https://x.test/bad', 'icon' => 'not-a-real-option' ),
			) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$items = $store->get_items( 'home', 'quick_tiles' );
		$this->assertSame( 'join', $items[0]['icon'] );
		$this->assertSame( '', $items[1]['icon'] );
	}

	public function test_toggle_field_absent_in_item_is_false(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'item' => array( 'home' => array( 'stats' => array(
				array( 'value' => '10', 'label' => 'Teams' ), // no 'featured' key => unchecked
			) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertFalse( $store->get_items( 'home', 'stats' )[0]['featured'] );
	}

	public function test_clubhouse_content_add_appends_a_blank_loop_item(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab'   => 'membership',
			'item'                    => array( 'membership' => array( 'faq' => array(
				array( 'question' => 'Q1', 'answer' => 'A1' ),
			) ) ),
			'clubhouse_content_add'   => array( 'membership' => array( 'faq' => '1' ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$items = $store->get_items( 'membership', 'faq' );
		$this->assertCount( 2, $items );
		$this->assertSame( '', $items[1]['question'] );
	}

	public function test_clubhouse_content_remove_deletes_item_by_index(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab'    => 'membership',
			'item'                     => array( 'membership' => array( 'faq' => array(
				array( 'question' => 'Q1', 'answer' => 'A1' ),
				array( 'question' => 'Q2', 'answer' => 'A2' ),
			) ) ),
			'clubhouse_content_remove' => array( 'membership' => array( 'faq' => '0' ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$items = $store->get_items( 'membership', 'faq' );
		$this->assertCount( 1, $items );
		$this->assertSame( 'Q2', $items[0]['question'] );
	}

	public function test_only_the_submitted_tab_sections_are_persisted(): void {
		$s = $this->storage();
		// Pre-seed the about hero title so we can confirm a global-tab save leaves it alone.
		( new Blueworx_Clubhouse_Content_Store( $s ) )->set( 'about', 'hero', 'title_lead', 'Existing About' );
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'hero' => array( 'title_lead' => 'New Home' ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( 'New Home', $store->get( 'home', 'hero', 'title_lead' ) );
		$this->assertSame( 'Existing About', $store->get( 'about', 'hero', 'title_lead' ) );
	}

	public function test_unknown_tab_returns_no_notices_and_saves_nothing(): void {
		$s = $this->storage();
		$notices = Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'not-a-real-tab',
		), $s );
		$this->assertSame( array(), $notices );
	}

	public function test_save_returns_a_success_notice(): void {
		$s = $this->storage();
		$notices = Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
		), $s );
		$this->assertSame( array( array( 'type' => 'success', 'text' => 'Your changes have been saved.' ) ), $notices );
	}

	public function test_constants(): void {
		$this->assertSame( 'clubhouse-site-content', Blueworx_Clubhouse_Content_Controller::PAGE_SLUG );
		$this->assertSame( 'clubhouse_content_save', Blueworx_Clubhouse_Content_Controller::NONCE );
		$this->assertSame( Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP, Blueworx_Clubhouse_Content_Controller::CAPABILITY );
	}

	public function test_owner_menu_allowlist_includes_site_content(): void {
		$this->assertContains( 'clubhouse-site-content', Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist() );
	}

	public function test_add_menu_registers_the_page(): void {
		Blueworx_Clubhouse_Content_Controller::add_menu();
		$calls = wp_stub_calls( 'add_menu_page' );
		$this->assertNotEmpty( $calls );
		$this->assertSame( Blueworx_Clubhouse_Content_Controller::PAGE_SLUG, $calls[0]['args'][3] );
		$this->assertSame( Blueworx_Clubhouse_Content_Controller::CAPABILITY, $calls[0]['args'][2] );
	}

	public function test_enqueue_only_loads_on_its_own_page(): void {
		Blueworx_Clubhouse_Content_Controller::enqueue( 'toplevel_page_' . Blueworx_Clubhouse_Content_Controller::PAGE_SLUG );
		$this->assertNotEmpty( wp_stub_calls( 'wp_enqueue_media' ) );
		$this->assertNotEmpty( wp_stub_calls( 'wp_enqueue_style' ) );
		$this->assertNotEmpty( wp_stub_calls( 'wp_enqueue_script' ) );

		wp_stub_reset();
		Blueworx_Clubhouse_Content_Controller::enqueue( 'some-other-page' );
		$this->assertEmpty( wp_stub_calls( 'wp_enqueue_media' ) );
		$this->assertEmpty( wp_stub_calls( 'wp_enqueue_style' ) );
		$this->assertEmpty( wp_stub_calls( 'wp_enqueue_script' ) );
	}

	public function test_build_model_merges_stored_values_and_hidden_state(): void {
		$s = $this->storage();
		( new Blueworx_Clubhouse_Content_Store( $s ) )->set( 'home', 'hero', 'title_lead', 'Stored Value' );
		( new Blueworx_Clubhouse_Visibility( $s ) )->set_section_visible( 'home', 'ticker', false );

		$model = Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '<nonce>', 'https://x.test/admin.php?page=clubhouse-site-content' );

		$this->assertSame( '<nonce>', $model['nonce_field'] );
		$this->assertSame( 'https://x.test/admin.php?page=clubhouse-site-content', $model['action_url'] );
		$this->assertArrayHasKey( 'catalogue', $model );

		$global_page = null;
		foreach ( $model['catalogue'] as $page ) {
			if ( 'global' === $page['tab'] ) {
				$global_page = $page;
			}
		}
		$this->assertNotNull( $global_page );

		$hero = null;
		$ticker = null;
		foreach ( $global_page['sections'] as $section ) {
			if ( 'hero' === $section['key'] ) { $hero = $section; }
			if ( 'ticker' === $section['key'] ) { $ticker = $section; }
		}
		$this->assertSame( 'Stored Value', $hero['values']['title_lead'] );
		$this->assertTrue( $ticker['hidden'] );
	}

	public function test_url_field_rejects_a_javascript_scheme(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'hero' => array( 'cta_primary_href' => 'javascript:alert(1)' ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( '', $store->get( 'home', 'hero', 'cta_primary_href' ) );
	}

	public function test_url_field_keeps_a_valid_http_url(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'hero' => array( 'cta_primary_href' => 'https://x.test/join' ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( 'https://x.test/join', $store->get( 'home', 'hero', 'cta_primary_href' ) );
	}

	public function test_textarea_field_is_sanitised(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'hero' => array( 'lede' => "  Line one  \n<script>bad</script>Line two  " ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( "Line one\nbadLine two", $store->get( 'home', 'hero', 'lede' ) );
	}

	/** Regression for Important 1: build_model must expose the Visibility page key per section. */
	public function test_build_model_exposes_vis_page_for_global_tab_sections(): void {
		$s     = $this->storage();
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '<nonce>', 'https://x.test/admin.php' );

		$global_page = null;
		foreach ( $model['catalogue'] as $page ) {
			if ( 'global' === $page['tab'] ) {
				$global_page = $page;
			}
		}
		$this->assertNotNull( $global_page );
		$this->assertSame( 'home', $global_page['vis_page'] ); // Global tab's Visibility page is 'home'.

		$header = null;
		$hero   = null;
		foreach ( $global_page['sections'] as $section ) {
			if ( 'header' === $section['key'] ) { $header = $section; }
			if ( 'hero' === $section['key'] ) { $hero = $section; }
		}
		$this->assertNotNull( $header );
		$this->assertNotNull( $hero );
		// Header's store_page is 'global' but its hide flag lives under 'home' —
		// vis_page must reflect that even though store_page differs.
		$this->assertSame( 'global', $header['store_page'] );
		$this->assertSame( 'home', $header['vis_page'] );
		$this->assertSame( 'home', $hero['store_page'] );
		$this->assertSame( 'home', $hero['vis_page'] );
	}

	/** Regression for Important 1: non-global tabs have a matching store_page/vis_page. */
	public function test_build_model_vis_page_matches_store_page_on_non_global_tabs(): void {
		$s     = $this->storage();
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '<nonce>', 'https://x.test/admin.php' );

		$about_page = null;
		foreach ( $model['catalogue'] as $page ) {
			if ( 'about' === $page['tab'] ) {
				$about_page = $page;
			}
		}
		$this->assertNotNull( $about_page );
		$this->assertSame( 'about', $about_page['vis_page'] );
	}

	/** Regression for Important 2: a wholly-absent field group must not blank stored content. */
	public function test_wholly_absent_field_group_does_not_blank_stored_values(): void {
		$s     = $this->storage();
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$store->set( 'home', 'hero', 'title_lead', 'Existing Lead' );
		$store->set( 'home', 'hero', 'title_highlight', 'Existing Highlight' );

		// Simulate a truncated POST: the 'field' key is present for other data
		// but the hero section's group never appears at all.
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'clubhouse' => array( 'eyebrow' => 'x' ) ) ),
		), $s );

		$this->assertSame( 'Existing Lead', $store->get( 'home', 'hero', 'title_lead' ) );
		$this->assertSame( 'Existing Highlight', $store->get( 'home', 'hero', 'title_highlight' ) );
	}

	/** Regression for Important 2: once a group IS present, an absent key within it still clears (real form semantics). */
	public function test_present_field_group_still_blanks_its_own_absent_keys(): void {
		$s     = $this->storage();
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$store->set( 'home', 'hero', 'title_lead', 'Existing Lead' );
		$store->set( 'home', 'hero', 'title_highlight', 'Existing Highlight' );

		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'hero' => array( 'title_lead' => 'New Lead' ) ) ),
		), $s );

		$this->assertSame( 'New Lead', $store->get( 'home', 'hero', 'title_lead' ) );
		$this->assertSame( '', $store->get( 'home', 'hero', 'title_highlight' ) ); // absent within a present group => cleared.
	}

	/** Regression for Minor 3: a non-scalar posted value must not become the literal "Array". */
	public function test_non_scalar_text_field_sanitises_to_empty_string(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'hero' => array( 'title_lead' => array( 'x' ) ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( '', $store->get( 'home', 'hero', 'title_lead' ) );
	}

	/** Regression for Minor 3: a non-scalar posted image value must not become the literal "Array". */
	public function test_non_scalar_image_field_sanitises_to_zero(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'clubhouse' => array( 'image' => array( '1' ) ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( 0, $store->get( 'home', 'clubhouse', 'image' ) );
	}

	/** Regression for Minor 4: an empty remove value must not delete item 0. */
	public function test_empty_remove_value_does_not_delete_item_zero(): void {
		$s = $this->storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab'    => 'membership',
			'item'                     => array( 'membership' => array( 'faq' => array(
				array( 'question' => 'Q1', 'answer' => 'A1' ),
				array( 'question' => 'Q2', 'answer' => 'A2' ),
			) ) ),
			'clubhouse_content_remove' => array( 'membership' => array( 'faq' => '' ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$items = $store->get_items( 'membership', 'faq' );
		$this->assertCount( 2, $items );
		$this->assertSame( 'Q1', $items[0]['question'] );
	}
}
