<?php

use PHPUnit\Framework\TestCase;

/**
 * The club rules page, and the footer column that now carries the policies.
 *
 * The footer's "Get involved" column was replaced by the policies rather than
 * added beside them — a club's own decision, and the reason the legal strip at
 * the very bottom no longer repeats the same three links.
 */
final class ClubRulesTest extends TestCase {

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function visibility(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function collections(): Blueworx_Clubhouse_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	private function rules( ?Blueworx_Clubhouse_Visibility $vis = null, ?Blueworx_Clubhouse_Content_Store $content = null ): string {
		return Blueworx_Clubhouse_Page_Renderer::rules( $this->branding(), $vis ?? $this->visibility(), $this->collections(), '', $content );
	}

	public function test_the_club_rules_page_is_one_of_the_pages_this_plugin_serves(): void {
		$slugs = array_column( Blueworx_Clubhouse_Page_Map::pages(), 'slug' );
		$this->assertContains( 'rules', $slugs );
	}

	public function test_it_renders_a_real_page_with_a_heading_and_body(): void {
		$html = $this->rules();
		$this->assertStringContainsString( 'ch-prose', $html );
		$this->assertStringContainsString( 'Club rules', $html );
	}

	public function test_every_starter_section_says_it_is_an_example(): void {
		// Unlike a privacy policy, none of this is true of a club until they
		// have written it, so nobody may mistake the shipped copy for their own.
		$html = $this->rules();
		$this->assertStringContainsString( Blueworx_Clubhouse_Page_Renderer::EXAMPLE_LEAD, $html );
	}

	public function test_a_club_can_replace_the_wording(): void {
		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'rules', 'body', array(
			array( 'heading' => 'Boots off', 'body' => 'Studs stay outside the clubhouse.' ),
		) );
		$html = $this->rules( null, $content );
		$this->assertStringContainsString( 'Boots off', $html );
		$this->assertStringContainsString( 'Studs stay outside the clubhouse.', $html );
	}

	public function test_it_does_not_honour_markup_pasted_into_it(): void {
		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'rules', 'body', array(
			array( 'heading' => 'X', 'body' => '<script>alert(1)</script>' ),
		) );
		$this->assertStringNotContainsString( '<script>', $this->rules( null, $content ) );
	}

	public function test_the_footer_offers_the_policies_where_get_involved_used_to_be(): void {
		$html = $this->rules();
		$this->assertStringContainsString( 'Policies', $html );
		$this->assertStringNotContainsString( 'Get involved', $html );
	}

	public function test_the_policies_column_carries_all_three_pages(): void {
		$html = Blueworx_Clubhouse_Sections::footer( $this->footer_data() );
		foreach ( array( 'Privacy', 'Terms', 'Club rules' ) as $label ) {
			$this->assertStringContainsString( '>' . $label . '<', $html );
		}
	}

	public function test_the_bottom_strip_no_longer_repeats_the_same_links(): void {
		// Three links in a column and the same three again underneath is noise.
		$html = $this->rules();
		$this->assertStringNotContainsString( 'ch-footer__legal-link', $html );
		// The copyright still stands on its own.
		$this->assertStringContainsString( 'All rights reserved', $html );
	}

	public function test_a_switched_off_policy_is_not_linked(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_page_visible( 'terms', false );

		$html = Blueworx_Clubhouse_Page_Renderer::privacy( $this->branding(), $vis, $this->collections(), '', null );
		$this->assertStringContainsString( '>Privacy<', $html );
		$this->assertStringNotContainsString( '>Terms<', $html );
	}

	public function test_a_column_with_nothing_left_in_it_draws_no_heading(): void {
		// A club that switches every policy off must not be left with a
		// "Policies" heading and nothing under it.
		$data              = $this->footer_data();
		$data['columns'][] = array( 'title' => 'Empty', 'links' => array() );
		$html              = Blueworx_Clubhouse_Sections::footer( $data );
		$this->assertStringNotContainsString( 'Empty', $html );
	}

	public function test_the_checkout_footer_offers_the_club_rules_too(): void {
		$links = Blueworx_Clubhouse_Commerce_Pages::footer_links(
			static fn( string $slug ): bool => true,
			static fn( string $slug ): string => 'https://club.test/' . $slug . '/'
		);
		$this->assertContains( 'Club rules', array_column( $links, 'label' ) );
	}

	public function test_the_club_rules_page_has_switchable_sections_like_the_others(): void {
		$inventory = Blueworx_Clubhouse_Setup_Sections::inventory();
		$pages     = array_column( $inventory, 'page' );
		$this->assertContains( 'rules', $pages );
	}

	public function test_the_page_builder_offers_a_club_rules_tab(): void {
		$tabs = array_column( Blueworx_Clubhouse_Content_Catalogue::pages(), 'tab' );
		$this->assertContains( 'rules', $tabs );
	}

	/** @return array<string,mixed> */
	private function footer_data(): array {
		return array(
			'club_name'  => 'Club',
			'tagline'    => '',
			'heading'    => 'Club',
			'lede'       => '',
			'socials'    => array(),
			'columns'    => array(
				array( 'title' => 'Policies', 'links' => array(
					array( 'label' => 'Privacy', 'href' => '/privacy/' ),
					array( 'label' => 'Terms', 'href' => '/terms/' ),
					array( 'label' => 'Club rules', 'href' => '/rules/' ),
				) ),
			),
			'newsletter' => array( 'heading' => '', 'lede' => '', 'shortcode' => '' ),
			'copyright'  => '© 2026 Club. All rights reserved.',
			'legal'      => array(),
			'cookie'     => '',
		);
	}
}
