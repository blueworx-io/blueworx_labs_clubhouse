<?php
// tests/php/LinkCatalogueTest.php

use PHPUnit\Framework\TestCase;

final class LinkCatalogueTest extends TestCase {

	/** The link picker installs this club's blocks on a static seam; put it back. */
	protected function tearDown(): void {
		Blueworx_Clubhouse_Link_Catalogue::set_composer( null );
	}

	private function collections(): Blueworx_Clubhouse_Demo_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	/** @return array<int,array{target:string,label:string,group:string,url:string}> */
	private function targets(): array {
		return Blueworx_Clubhouse_Link_Catalogue::targets( $this->collections() );
	}

	private function find( string $target ): ?array {
		foreach ( $this->targets() as $entry ) {
			if ( $entry['target'] === $target ) {
				return $entry;
			}
		}
		return null;
	}

	public function test_every_available_page_is_offered_as_a_page_target(): void {
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $page ) {
			$key   = '' === $page['slug'] ? 'home' : $page['slug'];
			$entry = $this->find( 'page:' . $key );
			$this->assertNotNull( $entry, "missing page target for {$key}" );
			$this->assertSame( 'Pages', $entry['group'] );
			$this->assertSame( $page['label'], $entry['label'] );
		}
	}

	public function test_anchor_targets_exist_for_catalogue_sections(): void {
		$entry = $this->find( 'anchor:about.history' );
		$this->assertNotNull( $entry );
		$this->assertSame( 'Sections', $entry['group'] );
		$this->assertSame( 'About → History', $entry['label'] );
		$this->assertStringContainsString( '#ch-about-history', $entry['url'] );
	}

	public function test_anchor_id_is_derived_from_page_and_section_keys(): void {
		$this->assertSame( 'ch-about-history', Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'about', 'history' ) );
		$this->assertSame( 'ch-home-quick-tiles', Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'home', 'quick_tiles' ) );
	}

	public function test_resolve_returns_the_url_for_a_known_page(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Links::url( 'about' ),
			Blueworx_Clubhouse_Link_Catalogue::resolve( 'page:about', $this->collections() )
		);
	}

	public function test_resolve_returns_empty_for_an_unknown_page(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( 'page:nope', $this->collections() ) );
	}

	public function test_resolve_returns_empty_for_an_unknown_anchor(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( 'anchor:about.nope', $this->collections() ) );
	}

	public function test_resolve_passes_a_custom_url_through(): void {
		$this->assertSame(
			'https://example.test/x',
			Blueworx_Clubhouse_Link_Catalogue::resolve( 'url:https://example.test/x', $this->collections() )
		);
	}

	public function test_resolve_rejects_a_javascript_url(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( 'url:javascript:alert(1)', $this->collections() ) );
	}

	public function test_resolve_rejects_a_protocol_relative_url(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( 'url://evil.com', $this->collections() ) );
	}

	public function test_resolve_passes_a_genuine_site_relative_url_through(): void {
		$this->assertSame( '/about', Blueworx_Clubhouse_Link_Catalogue::resolve( 'url:/about', $this->collections() ) );
	}

	public function test_resolve_returns_empty_for_a_malformed_target(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( 'nonsense', $this->collections() ) );
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( '', $this->collections() ) );
	}

	public function test_sports_targets_come_from_sport_titles(): void {
		$entry = $this->find( 'filter:sports:rugby' );
		$this->assertNotNull( $entry );
		$this->assertSame( 'Sports', $entry['group'] );
		$this->assertSame( 'Sports → Rugby', $entry['label'] );
		$this->assertStringContainsString( 'clubhouse_filter=rugby', $entry['url'] );
	}

	public function test_teams_targets_come_from_the_team_sport_not_the_team_name(): void {
		$this->assertNotNull( $this->find( 'filter:teams:rugby' ) );
		$this->assertNull( $this->find( 'filter:teams:1st-xv' ) );
	}

	public function test_collection_targets_are_deduplicated(): void {
		$seen = array();
		foreach ( $this->targets() as $entry ) {
			$this->assertNotContains( $entry['target'], $seen, 'duplicate target ' . $entry['target'] );
			$seen[] = $entry['target'];
		}
	}

	public function test_resolve_returns_empty_for_a_collection_item_that_is_gone(): void {
		$this->assertSame(
			'',
			Blueworx_Clubhouse_Link_Catalogue::resolve( 'filter:sports:quidditch', $this->collections() )
		);
	}
}
