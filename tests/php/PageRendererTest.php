<?php
// tests/php/PageRendererTest.php

use PHPUnit\Framework\TestCase;

final class PageRendererTest extends TestCase {

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}
	private function collections(): Blueworx_Clubhouse_Demo_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	public function test_font_face_css_emits_a_rule_per_weight(): void {
		$css = Blueworx_Clubhouse_Page_Renderer::font_face_css(
			new Blueworx_Clubhouse_Court_Side(),
			'/wp-content/plugins/clubhouse/'
		);
		// One @font-face per declared weight: Syne 600/700/800 + Inter 400/500/600 = 6.
		$this->assertSame( 6, substr_count( $css, '@font-face' ) );
		$this->assertStringContainsString( "font-family:'Syne'", $css );
		$this->assertStringContainsString( 'font-weight:700', $css );
		$this->assertStringContainsString( 'font-display:swap', $css );
		$this->assertStringContainsString(
			"src:url(/wp-content/plugins/clubhouse/assets/fonts/syne-700.woff2) format('woff2')",
			$css
		);
		$this->assertStringContainsString(
			"src:url(/wp-content/plugins/clubhouse/assets/fonts/inter-400.woff2) format('woff2')",
			$css
		);
		$this->assertStringNotContainsString( 'googleapis', $css );
	}

	public function test_document_head_carries_tokens_fonts_and_stylesheet(): void {
		$b = $this->branding();
		$b->set_accent( '#3b5bdb' );
		$doc = Blueworx_Clubhouse_Page_Renderer::document(
			new Blueworx_Clubhouse_Court_Side(), $b, '<main>hi</main>', '/wp-content/plugins/clubhouse/'
		);
		$this->assertStringContainsString( '<!doctype html>', $doc );
		$this->assertStringContainsString( ':root{', $doc );
		$this->assertStringContainsString( '--color-bg:#faf8f3;', $doc );
		$this->assertStringContainsString( '--color-accent:#3b5bdb;', $doc );
		$this->assertStringContainsString( '@font-face', $doc );
		$this->assertStringContainsString(
			"src:url(/wp-content/plugins/clubhouse/assets/fonts/syne-700.woff2) format('woff2')",
			$doc
		);
		$this->assertStringNotContainsString( 'googleapis', $doc );
		$this->assertStringContainsString( '/wp-content/plugins/clubhouse/assets/looks/court-side.css', $doc );
		$this->assertStringContainsString( '<main>hi</main>', $doc );
	}

	public function test_home_includes_the_shell_sections(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $vis, $this->collections() );
		$this->assertStringContainsString( 'class="ch-nav"', $body );
		$this->assertStringContainsString( 'class="ch-hero"', $body );
		$this->assertStringContainsString( 'class="ch-tiles"', $body );
		$this->assertStringContainsString( 'class="ch-stats"', $body );
		$this->assertStringContainsString( 'class="ch-cards"', $body );
		$this->assertStringContainsString( 'class="ch-tiers"', $body );
		$this->assertStringContainsString( 'class="ch-footer"', $body );
	}

	public function test_home_respects_visibility(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_section_visible( 'home', 'stats', false );
		$body = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $vis, $this->collections() );
		$this->assertStringNotContainsString( 'class="ch-stats"', $body );
		$this->assertStringContainsString( 'class="ch-hero"', $body ); // others still present
	}

	public function test_about_composes_its_sections(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::about( $this->branding(), $vis, $this->collections() );
		$this->assertStringContainsString( 'class="ch-nav"', $body );
		$this->assertStringContainsString( 'class="ch-timeline"', $body );
		$this->assertStringContainsString( 'class="ch-benefits"', $body );
		$this->assertStringContainsString( 'class="ch-people"', $body );
		$this->assertStringContainsString( 'class="ch-band-img"', $body );
		$this->assertStringContainsString( 'class="ch-footer"', $body );
		$this->assertStringContainsString( 'ch-nav__link--active', $body );
	}

	public function test_membership_composes_its_sections(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::membership( $this->branding(), $vis, $this->collections() );
		$this->assertStringContainsString( 'class="ch-benefits"', $body );
		$this->assertStringContainsString( 'class="ch-tiers"', $body );
		$this->assertStringContainsString( 'class="ch-splits"', $body );
		$this->assertStringContainsString( 'class="ch-steps"', $body );
		$this->assertStringContainsString( 'class="ch-faq"', $body );
		$this->assertSame( 4, substr_count( $body, 'ch-tier"' ) + substr_count( $body, 'ch-tier ch-tier--pop"' ) );
	}

	public function test_contact_composes_form_and_directory_without_cta_band(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::contact( $this->branding(), $vis, $this->collections() );
		$this->assertStringContainsString( 'class="ch-contact"', $body );
		$this->assertStringContainsString( 'class="ch-people"', $body );
		$this->assertStringContainsString( 'class="ch-footer"', $body );
		$this->assertStringNotContainsString( 'ch-band--ink', $body ); // no CTA band on Contact
	}

	public function test_sports_composes_filter_hero_and_stat_cards(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::sports( $this->branding(), $vis, $this->collections() );
		$this->assertStringContainsString( 'class="ch-nav"', $body );
		$this->assertStringContainsString( 'class="ch-hero-f"', $body );
		$this->assertStringContainsString( 'class="ch-scards"', $body );
		$this->assertStringContainsString( 'class="ch-footer"', $body );
		$this->assertStringContainsString( 'ch-band--ink', $body ); // shared CTA band
		// Sports nav item is the active one.
		$this->assertStringContainsString( 'ch-nav__link--active" href="?page=sports"', $body );
	}

	public function test_teams_composes_filter_hero_and_stat_cards(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::teams( $this->branding(), $vis, $this->collections() );
		$this->assertStringContainsString( 'class="ch-hero-f"', $body );
		$this->assertStringContainsString( 'class="ch-scards"', $body );
		$this->assertStringContainsString( 'ch-nav__link--active" href="?page=teams"', $body );
	}

	public function test_sports_respects_visibility(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_section_visible( 'sports', 'directory', false );
		$body = Blueworx_Clubhouse_Page_Renderer::sports( $this->branding(), $vis, $this->collections() );
		$this->assertStringNotContainsString( 'class="ch-scards"', $body );
		$this->assertStringContainsString( 'class="ch-hero-f"', $body ); // hero still present
	}

	public function test_events_composes_upcoming_and_archive(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::events( $this->branding(), $vis, $this->collections() );
		$this->assertStringContainsString( 'class="ch-hero-f"', $body );
		$this->assertStringContainsString( 'class="ch-events"', $body );
		$this->assertStringContainsString( 'class="ch-archive"', $body );
		$this->assertStringContainsString( 'ch-nav__link--active" href="?page=events"', $body );
		$this->assertStringContainsString( 'ch-band--ink', $body );
	}

	public function test_calendar_composes_month_schedule(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::calendar( $this->branding(), $vis, $this->collections() );
		$this->assertStringContainsString( 'class="ch-hero-f"', $body );
		$this->assertStringContainsString( 'class="ch-cal"', $body );
		$this->assertStringContainsString( 'ch-cal__month', $body );
		$this->assertStringContainsString( 'ch-nav__link--active" href="?page=calendar"', $body );
	}

	public function test_calendar_respects_visibility(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_section_visible( 'calendar', 'schedule', false );
		$body = Blueworx_Clubhouse_Page_Renderer::calendar( $this->branding(), $vis, $this->collections() );
		$this->assertStringNotContainsString( 'ch-cal__month', $body );
		$this->assertStringContainsString( 'class="ch-hero-f"', $body );
	}

	public function test_document_inlines_reveal_script_from_file(): void {
		$look     = new Blueworx_Clubhouse_Court_Side();
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$html     = Blueworx_Clubhouse_Page_Renderer::document( $look, $branding, '<main></main>', '/' );
		$this->assertStringContainsString( 'IntersectionObserver', $html );
		$this->assertStringContainsString( "querySelectorAll('.ch-main > *:not(.ch-hero)')", $html );
	}

	public function test_sports_page_renders_collection_sports(): void {
		$html = Blueworx_Clubhouse_Page_Renderer::sports(
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections()
		);
		// Sports page shows all six sports with the stat-card chip + description.
		$this->assertStringContainsString( 'Rugby', $html );
		$this->assertStringContainsString( 'Netball', $html );
		$this->assertStringContainsString( 'Senior, colts and touch rugby, from minis upward.', $html );
		$this->assertSame( 6, substr_count( $html, 'ch-scard__title' ) );
	}

	public function test_home_shows_first_four_sports_as_cards(): void {
		$html = Blueworx_Clubhouse_Page_Renderer::home(
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections()
		);
		$this->assertStringContainsString( 'Senior · colts · touch', $html );  // Home uses the short subtitle
		$this->assertStringNotContainsString( 'Netball', substr( $html, strpos( $html, 'Our sports' ), 600 ) );  // only first 4
	}

	private function render( string $page ): string {
		return Blueworx_Clubhouse_Page_Map::render(
			$page,
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections()
		);
	}

	public function test_teams_projected(): void {
		$html = $this->render( 'teams' );
		$this->assertStringContainsString( '1st XV', $html );
		$this->assertStringContainsString( 'Saturday league rugby, Division 3 South.', $html );
		$this->assertStringContainsString( 'Match day', $html );
	}

	public function test_events_upcoming_and_archive_projected(): void {
		$html = $this->render( 'events' );
		$this->assertStringContainsString( 'Club Open Day', $html );          // upcoming
		$this->assertStringContainsString( 'Register interest', $html );      // upcoming CTA
		$this->assertStringContainsString( 'Summer BBQ &amp; Family Day', $html ); // past (escaped &)
	}

	public function test_sponsors_projected(): void {
		$this->assertStringContainsString( 'Sponsor 01', $this->render( 'home' ) );
	}

	public function test_committee_blanks_email_directory_shows_it(): void {
		$about = $this->render( 'about' );
		$this->assertStringContainsString( 'Priya Nair', $about );
		$this->assertStringNotContainsString( 'press@clubhouse.example', $about ); // committee blanks email
		$contact = $this->render( 'contact' );
		$this->assertStringContainsString( 'membership@clubhouse.example', $contact ); // directory shows email
	}

	public function test_hidden_page_is_omitted_from_nav_and_footer(): void {
		$shown      = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body_shown = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $shown, $this->collections() );
		// Control: all pages visible → About appears in both the nav and the footer link list.
		$this->assertStringContainsString( 'class="ch-nav__link" href="?page=about"', $body_shown );
		$this->assertStringContainsString( 'class="ch-footer__link" href="?page=about"', $body_shown );

		$hidden = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$hidden->set_page_visible( 'about', false );
		$body_hidden = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $hidden, $this->collections() );
		// Hiding About removes it from the primary nav and the footer link list...
		$this->assertStringNotContainsString( 'class="ch-nav__link" href="?page=about"', $body_hidden );
		$this->assertStringNotContainsString( 'class="ch-footer__link" href="?page=about"', $body_hidden );
		// ...while a still-visible page keeps its nav link.
		$this->assertStringContainsString( 'class="ch-nav__link" href="?page=sports"', $body_hidden );
	}

	public function test_home_renders_the_logo_when_threaded(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $vis, $this->collections(), 'https://club.test/logo.png' );
		$this->assertStringContainsString( 'ch-brand__logo', $body );
		$this->assertStringContainsString( 'src="https://club.test/logo.png"', $body );
	}
}
