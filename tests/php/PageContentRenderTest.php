<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Proves the front end now reads through Page_Content rather than
 * Content_Store: a value written to the page it belongs to reaches the
 * rendered HTML, and a section's own Shown switch — read from the same
 * page — actually drops it from the page.
 */
final class PageContentRenderTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function visibility(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function collections(): Blueworx_Clubhouse_Demo_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	public function test_the_home_hero_renders_what_the_page_stores(): void {
		update_option( 'clubhouse_page_id_home', 42 );
		$content = new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set( 'home', 'hero', 'title_lead', 'Crewe Vagrants' );
		$html = Blueworx_Clubhouse_Page_Map::render( '', $this->branding(), $this->visibility(), $this->collections(), '', $content );
		$this->assertStringContainsString( 'Crewe Vagrants', $html );
	}

	public function test_a_section_switched_off_on_its_own_panel_does_not_render(): void {
		update_option( 'clubhouse_page_id_home', 42 );

		// Positive control first: with the Shown switch untouched, the ticker
		// renders by default — proving the negative assertion below actually
		// means something, rather than passing because the ticker never
		// renders at all.
		$shown = Blueworx_Clubhouse_Page_Map::render( '', $this->branding(), $this->visibility(), $this->collections(), '', new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() ) );
		$this->assertStringContainsString( 'ch-ticker', $shown );

		$GLOBALS['wp_stub_postmeta'][42]['page_ticker__shown'] = '';
		$content = new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
		$html    = Blueworx_Clubhouse_Page_Map::render( '', $this->branding(), $this->visibility(), $this->collections(), '', $content );
		$this->assertStringNotContainsString( 'ch-ticker', $html );
	}

	/**
	 * Issue #320. The demo messages stand in only while a club has never
	 * written its own. A list the club has emptied is empty — it must not
	 * fall back to the demo words it just deleted.
	 */
	public function test_a_list_the_club_has_emptied_does_not_show_the_demo_items(): void {
		update_option( 'clubhouse_page_id_home', 42 );

		$untouched = Blueworx_Clubhouse_Page_Map::render( '', $this->branding(), $this->visibility(), $this->collections(), '', new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() ) );
		$this->assertStringContainsString( '1st XV promoted', $untouched, 'positive control: the demo words stand in until the club writes its own' );

		$content = new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'home', 'ticker', array() );
		$html = Blueworx_Clubhouse_Page_Map::render( '', $this->branding(), $this->visibility(), $this->collections(), '', $content );
		$this->assertStringNotContainsString( '1st XV promoted', $html );
		$this->assertStringNotContainsString( 'ch-ticker', $html, 'an emptied ticker is not drawn at all' );
	}

	/**
	 * The other half of #320. A list whose editor declares no default rows
	 * shows "No rows yet" over a site that is showing demo words, and a save
	 * that touched nothing writes it as empty. That empty must go on meaning
	 * "use the demo words" — or one visit to the About editor would blank its
	 * values cards.
	 */
	public function test_an_empty_save_of_a_list_with_no_editor_default_keeps_the_demo_words(): void {
		update_option( 'clubhouse_page_id_about', 43 );
		$this->assertNull( Blueworx_Clubhouse_Page_Fields::list_default( 'about', 'values' ), 'precondition: values declares no default rows' );

		$content = new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'about', 'values', array() );
		$html = Blueworx_Clubhouse_Page_Map::render( 'about', $this->branding(), $this->visibility(), $this->collections(), '', $content );
		$this->assertStringContainsString( 'Everyone plays', $html, 'the values cards are still drawn' );
	}

	/** The editor and the site start from the same four ticker messages. */
	public function test_the_ticker_editor_default_is_what_the_site_shows(): void {
		$this->assertSame( Blueworx_Clubhouse_Page_Fields::ticker_default(), Blueworx_Clubhouse_Page_Fields::list_default( 'home', 'ticker' ) );
		$this->assertCount( 4, Blueworx_Clubhouse_Page_Fields::ticker_default() );
	}
}
