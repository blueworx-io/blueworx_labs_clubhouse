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

	public function test_membership_faq_loop_override(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set_items( 'membership', 'faq', array( array( 'question' => 'Custom Q?', 'answer' => 'Custom A.' ) ) );
		$html = Blueworx_Clubhouse_Page_Renderer::membership( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'Custom Q?', $html );
	}

	public function test_about_history_heading_override(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'about', 'history', 'heading', 'Our custom story' );
		$html = Blueworx_Clubhouse_Page_Renderer::about( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'Our custom story', $html );
	}

	public function test_header_menu_cta_override_applies_across_pages(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		// Catalogue key for the header's menu CTA is 'join' (class-content-catalogue.php).
		$content->set( 'global', 'header', 'join', 'Sign up' );
		$html = Blueworx_Clubhouse_Page_Renderer::contact( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'Sign up', $html );
	}

	public function test_sports_hero_override(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'sports', 'hero', 'title_highlight', 'one custom club.' );
		$html = Blueworx_Clubhouse_Page_Renderer::sports( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'one custom club.', $html );
	}

	public function test_teams_hero_override(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'teams', 'hero', 'title_highlight', 'every custom level.' );
		$html = Blueworx_Clubhouse_Page_Renderer::teams( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'every custom level.', $html );
	}

	public function test_events_hero_override(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'events', 'hero', 'title_highlight', 'custom open days.' );
		$html = Blueworx_Clubhouse_Page_Renderer::events( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'custom open days.', $html );
	}

	public function test_calendar_hero_override(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'calendar', 'hero', 'title_highlight', 'custom all season.' );
		$html = Blueworx_Clubhouse_Page_Renderer::calendar( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'custom all season.', $html );
	}

	public function test_login_form_heading_override(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'login', 'form', 'heading', 'Welcome back, custom.' );
		$html = Blueworx_Clubhouse_Page_Renderer::login( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'Welcome back, custom.', $html );
	}

	public function test_home_stats_loop_override(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set_items( 'home', 'stats', array( array( 'value' => '1234', 'label' => 'Custom stat', 'featured' => true ) ) );
		$html = Blueworx_Clubhouse_Page_Renderer::home( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'Custom stat', $html );
	}

	/** Reconciliation with v0.23.0: Home's quick_tiles loop has no quick_tiles() call of
	 *  its own any more — it threads into home_hero()'s 'tiles' argument instead. */
	public function test_home_quick_tiles_loop_threads_into_hero_foot(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set_items( 'home', 'quick_tiles', array( array( 'label' => 'Custom tile', 'href' => '#custom', 'icon' => 'join' ) ) );
		$html = Blueworx_Clubhouse_Page_Renderer::home( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'Custom tile', $html );
	}

	/**
	 * Regression for the "dead field" defect: about.facilities was declared as a
	 * loop but Sections::image_band() renders a single band — Page_Renderer now
	 * wires each of the band's fields (mirroring home's clubhouse band).
	 */
	public function test_about_facilities_band_overrides_reach_output(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'about', 'facilities', 'eyebrow', 'Custom facilities eyebrow' );
		$content->set( 'about', 'facilities', 'heading', 'Custom facilities heading' );
		$content->set( 'about', 'facilities', 'cta_label', 'Custom facilities CTA' );
		$content->set( 'about', 'facilities', 'cta_href', '/custom-facilities' );
		$html = Blueworx_Clubhouse_Page_Renderer::about( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'Custom facilities eyebrow', $html );
		$this->assertStringContainsString( 'Custom facilities heading', $html );
		$this->assertStringContainsString( 'Custom facilities CTA', $html );
		$this->assertStringContainsString( '/custom-facilities', $html );
	}

	/**
	 * Regression: contact.form used to declare intro/submissions_email/success_message,
	 * none of which Sections::contact_form() accepts. It now offers eyebrow/heading/
	 * submit_label, which Page_Renderer threads through.
	 */
	public function test_contact_form_overrides_reach_output(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'contact', 'form', 'eyebrow', 'Custom form eyebrow' );
		$content->set( 'contact', 'form', 'heading', 'Custom form heading' );
		$content->set( 'contact', 'form', 'submit_label', 'Custom submit label' );
		$html = Blueworx_Clubhouse_Page_Renderer::contact( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'Custom form eyebrow', $html );
		$this->assertStringContainsString( 'Custom form heading', $html );
		$this->assertStringContainsString( 'Custom submit label', $html );
	}
}
