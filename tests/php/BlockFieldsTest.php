<?php
// tests/php/BlockFieldsTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * What the Blocks screen offers to edit has to be exactly what a block stores.
 * Offer too little and a club's words become uneditable without anyone noticing;
 * offer too much and a save writes keys nothing renders.
 */
final class BlockFieldsTest extends TestCase {

	/** @return array<string,array<string,mixed>> The seeded library, keyed by id. */
	private function seeded(): array {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Block_Seeder(
			new Blueworx_Clubhouse_Block_Library( $storage ),
			new Blueworx_Clubhouse_Page_Composition( $storage )
		) )->seed();
		return ( new Blueworx_Clubhouse_Block_Library( $storage ) )->all();
	}

	/**
	 * The lockstep test. A club's stored content is projected onto blocks by
	 * Block_Seeder, so every key that lands on a block must be a key this screen
	 * knows how to draw — otherwise the migration would quietly hide words the
	 * club had already written.
	 */
	public function test_every_key_a_migration_writes_is_editable(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();

		// A club that has typed into every box the old editor offered.
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$fields = array();
				foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
					$fields[ (string) $field['key'] ] = 'typed';
				}
				if ( isset( $section['loop'] ) ) {
					$fields['items'] = array( array( 'x' => 'y' ) );
				}
				Blueworx_Clubhouse_Test_Site::legacy_content(
					$storage,
					(string) $section['store_page'],
					(string) $section['key'],
					$fields
				);
			}
		}

		$content = new Blueworx_Clubhouse_Content_Store( $storage );

		$library = new Blueworx_Clubhouse_Block_Library( $storage );
		( new Blueworx_Clubhouse_Block_Seeder( $library, new Blueworx_Clubhouse_Page_Composition( $storage ) ) )
			->migrate( $content, new Blueworx_Clubhouse_Visibility( $storage ) );

		foreach ( $library->all() as $id => $block ) {
			$shape    = Blueworx_Clubhouse_Block_Fields::for_block( $block );
			$editable = array_column( $shape['fields'], 'key' );
			if ( null !== $shape['loop'] ) {
				$editable[] = 'items';
			}
			foreach ( array_keys( (array) $block['content'] ) as $key ) {
				$this->assertContains( (string) $key, $editable, "block $id stores $key but nothing can edit it" );
			}
		}
	}

	/**
	 * Every block the old editor could edit stays editable.
	 *
	 * Not every block: one drawn entirely from a collection — the fixtures tabs,
	 * the events grid — has nothing to type, and neither has a booking slot,
	 * which is a third party's shortcode. Those had no editor before blocks
	 * either, so the promise being kept here is "nothing that was editable
	 * becomes uneditable", which is the one that matters to a club.
	 */
	public function test_every_block_the_old_editor_could_edit_is_still_editable(): void {
		$folds = Blueworx_Clubhouse_Block_Addresses::folds();

		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$address = (string) $section['store_page'] . '/' . (string) $section['key'];
				// A folded address has no block: what it holds is offered on the
				// block it folds into, which the fold assertions below cover.
				if ( isset( $folds[ $address ] ) ) {
					continue;
				}
				$shape = Blueworx_Clubhouse_Block_Fields::for_address( $address );
				foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
					$this->assertContains(
						(string) $field['key'],
						array_column( $shape['fields'], 'key' ),
						"$address offered {$field['key']} and no block does"
					);
				}
				if ( isset( $section['loop'] ) ) {
					$this->assertNotNull( $shape['loop'], "$address had repeatable items and no block does" );
				}
			}
		}
	}

	public function test_the_home_hero_carries_the_quick_tiles_as_its_items(): void {
		$shape = Blueworx_Clubhouse_Block_Fields::for_address( 'home/hero' );
		$this->assertNotNull( $shape['loop'] );
		$this->assertContains( 'label', array_column( $shape['loop']['fields'], 'key' ) );
	}

	public function test_the_footer_carries_the_cookie_notice_under_a_prefix(): void {
		$keys = array_column( Blueworx_Clubhouse_Block_Fields::for_address( 'global/footer' )['fields'], 'key' );
		$this->assertContains( 'cookie_text', $keys );
		$this->assertContains( 'cookie_show', $keys );
		$this->assertContains( 'tagline', $keys );
	}

	public function test_a_block_made_fresh_takes_its_types_shape(): void {
		$shape = Blueworx_Clubhouse_Block_Fields::for_block(
			array( 'type' => 'faq', 'defaults_key' => '', 'content' => array() )
		);
		// An FAQ is entirely repeated questions, so its shape is a loop and no
		// plain fields at all.
		$this->assertNotNull( $shape['loop'] );

		$hero = Blueworx_Clubhouse_Block_Fields::for_block(
			array( 'type' => 'hero', 'defaults_key' => '', 'content' => array() )
		);
		$this->assertNotSame( array(), $hero['fields'] );
	}

	/**
	 * Home's tier grid mirrors the Membership page's and has no editor of its
	 * own, so a tier block must not take it as its shape and end up blank.
	 */
	public function test_an_address_with_no_editor_falls_through_to_one_that_has(): void {
		$shape = Blueworx_Clubhouse_Block_Fields::for_type( 'tier_grid' );
		$this->assertTrue( array() !== $shape['fields'] || null !== $shape['loop'] );
	}

	public function test_an_address_the_catalogue_never_had_falls_back_to_the_type(): void {
		$shape = Blueworx_Clubhouse_Block_Fields::for_block(
			array( 'type' => 'hero', 'defaults_key' => 'nowhere/at-all', 'content' => array() )
		);
		$this->assertSame(
			array_column( Blueworx_Clubhouse_Block_Fields::for_type( 'hero' )['fields'], 'key' ),
			array_column( $shape['fields'], 'key' )
		);
	}
}
