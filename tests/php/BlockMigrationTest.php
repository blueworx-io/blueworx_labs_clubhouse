<?php

use PHPUnit\Framework\TestCase;

/**
 * Turning a club's existing site into blocks, and keeping it in step afterwards.
 *
 * The parity test proves the pages come out the same; this proves the library
 * they come out of is the right shape — a club's words carried over, its hidden
 * sections off the page but not thrown away, and one tier list serving both
 * pages that show it.
 */
final class BlockMigrationTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Storage $storage;
	private Blueworx_Clubhouse_Content_Store $content;
	private Blueworx_Clubhouse_Visibility $visibility;
	private Blueworx_Clubhouse_Block_Library $library;
	private Blueworx_Clubhouse_Page_Composition $composition;

	protected function setUp(): void {
		$this->storage     = new Blueworx_Clubhouse_Fake_Storage();
		$this->content     = new Blueworx_Clubhouse_Content_Store( $this->storage );
		$this->visibility  = new Blueworx_Clubhouse_Visibility( $this->storage );
		$this->library     = new Blueworx_Clubhouse_Block_Library( $this->storage );
		$this->composition = new Blueworx_Clubhouse_Page_Composition( $this->storage );
	}

	private function seeder(): Blueworx_Clubhouse_Block_Seeder {
		return new Blueworx_Clubhouse_Block_Seeder( $this->library, $this->composition );
	}

	/** @return array<string,array<string,mixed>> Blocks keyed by the address they came from. */
	private function by_address(): array {
		$out = array();
		foreach ( $this->library->all() as $block ) {
			$out[ (string) $block['defaults_key'] ] = $block;
		}
		return $out;
	}

	public function test_a_fresh_site_gets_every_block_on_its_page(): void {
		$this->seeder()->seed();

		$blocks = $this->by_address();
		$this->assertArrayHasKey( 'home/hero', $blocks );
		$this->assertArrayHasKey( 'global/header', $blocks );
		$this->assertSame( array(), $blocks['home/hero']['content'] );

		$this->assertTrue( $this->composition->is_configured() );
		$this->assertContains( $blocks['home/hero']['id'], $this->composition->blocks( 'home' ) );
	}

	/** A folded address has no block of its own — its content belongs to the one it renders inside. */
	public function test_folded_addresses_do_not_become_blocks_of_their_own(): void {
		$this->seeder()->seed();
		$blocks = $this->by_address();

		$this->assertArrayNotHasKey( 'home/quick_tiles', $blocks );
		$this->assertArrayNotHasKey( 'home/info', $blocks );
		$this->assertArrayNotHasKey( 'global/cookies', $blocks );
	}

	/**
	 * The welcome pack does get a block, even though it renders on the shop's
	 * dashboard rather than on any clubhouse page — that is where its wording
	 * and its on/off switch now live, the Setup screen that held them having gone.
	 */
	public function test_the_welcome_pack_becomes_a_block_that_sits_on_no_page(): void {
		$this->seeder()->seed();
		$block = $this->by_address()['global/welcome'];

		$this->assertNotSame( '', (string) $block['id'] );
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			$key = Blueworx_Clubhouse_Page_Map::page_key( (string) $page['slug'] );
			$this->assertNotContains( $block['id'], $this->composition->blocks( $key ), $key );
		}
	}

	/** A club that had the pack switched off on the old Setup screen keeps it off. */
	public function test_a_switched_off_welcome_pack_stays_off_as_a_field_on_its_block(): void {
		Blueworx_Clubhouse_Test_Site::legacy_section_off( $this->storage, 'home', 'welcome' );
		$this->seeder()->migrate( $this->content, $this->visibility );

		$this->assertFalse( $this->by_address()['global/welcome']['content']['show'] );
	}

	public function test_a_clubs_words_come_across(): void {
		Blueworx_Clubhouse_Test_Site::legacy_content( $this->storage, 'home', 'hero', array( 'title_lead' => 'Marlow rowing, ' ) );
		Blueworx_Clubhouse_Test_Site::legacy_content( $this->storage, 'home', 'quick_tiles', array(
			'items' => array( array( 'label' => 'Join us', 'href' => '/membership', 'icon' => 'join' ) ),
		) );
		Blueworx_Clubhouse_Test_Site::legacy_content( $this->storage, 'global', 'cookies', array( 'text' => 'One cookie, for signing in.' ) );

		$this->seeder()->migrate( $this->content, $this->visibility );
		$blocks = $this->by_address();

		$this->assertSame( 'Marlow rowing, ', $blocks['home/hero']['content']['title_lead'] );
		// The quick tiles fold into the hero block that renders them.
		$this->assertSame( 'Join us', $blocks['home/hero']['content']['items'][0]['label'] );
		// The cookie notice folds into the footer, under its own key.
		$this->assertSame( 'One cookie, for signing in.', $blocks['global/footer']['content']['cookie_text'] );
	}

	public function test_a_hidden_section_stays_in_the_library_but_leaves_the_page(): void {
		Blueworx_Clubhouse_Test_Site::legacy_section_off( $this->storage, 'about', 'committee' );
		$this->seeder()->migrate( $this->content, $this->visibility );

		$id = $this->by_address()['about/committee']['id'];
		$this->assertTrue( $this->library->has( $id ) );
		$this->assertNotContains( $id, $this->composition->blocks( 'about' ) );
	}

	public function test_a_hidden_page_is_recorded_as_off(): void {
		$this->visibility->set_page_visible( 'news', false );
		$this->seeder()->migrate( $this->content, $this->visibility );

		$this->assertFalse( $this->composition->is_enabled( 'news' ) );
		$this->assertTrue( $this->composition->is_enabled( 'home' ) );
	}

	/**
	 * The Home tier grid shows the Membership page's tiers. Two blocks, because
	 * they sit in different places on their pages, but one list of tiers — so
	 * editing them once still changes both.
	 */
	public function test_the_home_tier_grid_mirrors_the_membership_one(): void {
		$this->seeder()->seed();
		$blocks = $this->by_address();

		$this->assertSame(
			$blocks['membership/tiers']['id'],
			$blocks['home/tiers']['settings']['mirror']
		);
	}

	/** The Home tier grid has no switch of its own: it goes with the band above it. */
	public function test_hiding_the_home_membership_band_takes_its_tiers_with_it(): void {
		Blueworx_Clubhouse_Test_Site::legacy_section_off( $this->storage, 'home', 'membership' );
		$this->seeder()->migrate( $this->content, $this->visibility );

		$home   = $this->composition->blocks( 'home' );
		$blocks = $this->by_address();
		$this->assertNotContains( $blocks['home/membership']['id'], $home );
		$this->assertNotContains( $blocks['home/tiers']['id'], $home );
	}

	/** Either half of the Home closing band can be off on its own. */
	public function test_the_closing_bands_two_halves_become_settings(): void {
		Blueworx_Clubhouse_Test_Site::legacy_section_off( $this->storage, 'home', 'social' );
		$this->seeder()->migrate( $this->content, $this->visibility );

		$settings = $this->by_address()['home/social']['settings'];
		$this->assertFalse( $settings['show_social'] );
		$this->assertTrue( $settings['show_columns'] );
	}

}
