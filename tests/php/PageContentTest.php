<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageContentTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_stub_postmeta'] = array();
		$GLOBALS['wp_stub_options']  = array();
		update_option( 'clubhouse_page_id_home', 42 );
	}

	private function content(): Blueworx_Clubhouse_Page_Content {
		return new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_a_value_is_stored_at_the_key_the_library_would_use(): void {
		$this->content()->set( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$this->assertSame( 'Est. 1974', $GLOBALS['wp_stub_postmeta'][42]['page_hero_eyebrow'] );
	}

	public function test_a_value_round_trips(): void {
		$c = $this->content();
		$c->set( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$this->assertSame( 'Est. 1974', $c->get( 'home', 'hero', 'eyebrow' ) );
	}

	/**
	 * The reason this class casts at all. WordPress stores boolean false as an
	 * empty string, so a switch an owner turned off would read back from post
	 * meta as '' — which Page_Renderer::cget() treats as "never set" and would
	 * silently switch it back on. The meta is seeded directly, the way
	 * WordPress itself would leave it, and read through get() rather than
	 * is_section_shown() — that method applies its own (bool) cast and would
	 * pass this assertion even if cast() had no 'toggle' case at all, so it
	 * cannot be what proves this.
	 */
	public function test_a_toggle_switched_off_reads_back_as_false_and_not_as_unset(): void {
		$GLOBALS['wp_stub_postmeta'][42]['page_hero__shown'] = '';
		$this->assertFalse( $this->content()->get( 'home', 'hero', '_shown' ) );
	}

	/** Same cast, the option-backed global path. */
	public function test_a_global_toggle_switched_off_reads_back_as_false(): void {
		$c = $this->content();
		$c->set( 'global', 'cookies', 'show', false );
		$this->assertFalse( $c->get( 'global', 'cookies', 'show' ) );
	}

	public function test_a_field_never_written_reads_back_as_the_given_default(): void {
		$this->assertSame( 'fallback', $this->content()->get( 'home', 'hero', 'eyebrow', 'fallback' ) );
	}

	public function test_a_media_field_reads_back_as_an_integer(): void {
		$c = $this->content();
		$c->set( 'home', 'clubhouse', 'image', '77' );
		$this->assertSame( 77, $c->get( 'home', 'clubhouse', 'image' ) );
	}

	public function test_rows_round_trip(): void {
		$c    = $this->content();
		$rows = array( array( 'label' => 'Join', 'href' => '/join/', 'icon' => 'join' ) );
		$c->set_items( 'home', 'quick_tiles', $rows );
		$this->assertSame( $rows, $c->get_items( 'home', 'quick_tiles' ) );
	}

	public function test_rows_never_written_read_back_as_an_empty_list(): void {
		$this->assertSame( array(), $this->content()->get_items( 'home', 'quick_tiles' ) );
	}

	public function test_global_content_lives_in_an_option_and_not_on_a_page(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$c       = new Blueworx_Clubhouse_Page_Content( $storage );
		$c->set( 'global', 'header', 'join', 'Join us' );
		$this->assertSame( array( 'header_join' => 'Join us' ), $storage->get( 'global_content', array() ) );
		$this->assertSame( array(), $GLOBALS['wp_stub_postmeta'] );
	}

	public function test_a_section_nobody_has_hidden_is_shown(): void {
		$this->assertTrue( $this->content()->is_section_shown( 'home', 'hero' ) );
	}

	public function test_a_section_switched_off_is_hidden(): void {
		$GLOBALS['wp_stub_postmeta'][42]['page_hero__shown'] = '';
		$this->assertFalse( $this->content()->is_section_shown( 'home', 'hero' ) );
	}

	public function test_a_page_with_no_post_behind_it_reads_defaults_and_writes_nothing(): void {
		delete_option( 'clubhouse_page_id_home' );
		$c = $this->content();
		$c->set( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$this->assertSame( 'fallback', $c->get( 'home', 'hero', 'eyebrow', 'fallback' ) );
	}
}
