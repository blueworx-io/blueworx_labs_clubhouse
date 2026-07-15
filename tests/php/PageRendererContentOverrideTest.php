<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageRendererContentOverrideTest extends TestCase {

	/** @return array{0:Blueworx_Clubhouse_Branding,1:Blueworx_Clubhouse_Visibility,2:Blueworx_Clubhouse_Demo_Collections,3:Blueworx_Clubhouse_Content_Store} */
	private function ctx(): array {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		return array(
			new Blueworx_Clubhouse_Branding( $s ),
			new Blueworx_Clubhouse_Visibility( $s ),
			new Blueworx_Clubhouse_Demo_Collections(),
			new Blueworx_Clubhouse_Content_Store( $s ),
		);
	}

	public function test_null_content_renders_default_hero(): void {
		[ $b, $v, $c ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::home( $b, $v, $c, '', null );
		$this->assertStringContainsString( 'One community.', $html ); // today's default highlight
	}

	public function test_content_override_replaces_hero_heading(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'home', 'hero', 'title_highlight', 'One club.' );
		$html = Blueworx_Clubhouse_Page_Renderer::home( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'One club.', $html );
		$this->assertStringNotContainsString( 'One community.', $html );
	}

	public function test_page_map_render_threads_content(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'home', 'hero', 'title_highlight', 'Threaded!' );
		$html = Blueworx_Clubhouse_Page_Map::render( '', $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'Threaded!', $html );
	}
}
