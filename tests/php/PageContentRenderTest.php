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
}
