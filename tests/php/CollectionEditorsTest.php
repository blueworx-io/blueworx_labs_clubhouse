<?php

use PHPUnit\Framework\TestCase;

/**
 * How a collection record is reached, and what is closed off.
 */
final class CollectionEditorsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_the_six_collections_are_collections(): void {
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			$this->assertTrue( Blueworx_Clubhouse_Collection_Editors::is_collection( $type ), $type );
		}
	}

	public function test_an_ordinary_post_is_not(): void {
		$this->assertFalse( Blueworx_Clubhouse_Collection_Editors::is_collection( 'post' ) );
		$this->assertFalse( Blueworx_Clubhouse_Collection_Editors::is_collection( 'page' ) );
	}

	public function test_the_editor_address_carries_the_record(): void {
		$url = Blueworx_Clubhouse_Collection_Editors::editor_url( 'clubhouse_team', 42 );

		$this->assertStringContainsString( 'page=clubhouse-edit-clubhouse_team', $url );
		$this->assertStringContainsString( 'id=42', $url );
	}

	public function test_edit_on_a_collection_goes_to_its_own_editor(): void {
		$this->assertStringContainsString(
			'page=clubhouse-edit-clubhouse_fixture',
			Blueworx_Clubhouse_Collection_Editors::filter_edit_link( 'https://club.test/wp-admin/post.php?post=9', 'clubhouse_fixture', 9 )
		);
	}

	/** Every other post on the site keeps WordPress's own edit link. */
	public function test_edit_on_anything_else_is_left_alone(): void {
		$link = 'https://club.test/wp-admin/post.php?post=9&action=edit';

		$this->assertSame( $link, Blueworx_Clubhouse_Collection_Editors::filter_edit_link( $link, 'post', 9 ) );
	}

	public function test_the_block_editor_is_shut_for_a_collection(): void {
		$this->assertFalse( Blueworx_Clubhouse_Collection_Editors::wants_block_editor( true, 'clubhouse_sport' ) );
	}

	public function test_the_block_editor_is_left_alone_for_everything_else(): void {
		$this->assertTrue( Blueworx_Clubhouse_Collection_Editors::wants_block_editor( true, 'post' ) );
		$this->assertFalse( Blueworx_Clubhouse_Collection_Editors::wants_block_editor( false, 'post' ) );
	}

	public function test_registering_declares_all_six_to_the_library(): void {
		\Blueworx\PageEditor\v1\Editor::reset();
		// The library refuses to open a screen for a record type the site does
		// not have, so the types have to exist before the screens are declared
		// — which is the order the plugin itself boots in.
		Blueworx_Clubhouse_Collection_Types::register();

		Blueworx_Clubhouse_Collection_Editors::declare_screens();

		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			$slug = Blueworx_Clubhouse_Collection_Fields::slug_for( $type );
			$this->assertNotNull( \Blueworx\PageEditor\v1\Editor::get( $slug ), $type );
			$this->assertTrue( \Blueworx\PageEditor\v1\Editor::ready( $slug ), $type . ': ' . \Blueworx\PageEditor\v1\Editor::problem( $slug ) );
		}
	}
}
