<?php

use PHPUnit\Framework\TestCase;

/**
 * Where a collection's field is stored.
 *
 * The six collections are edited through the page editor library now, and its
 * post store derives a meta key from the post type and the field id. This is
 * the one place in the plugin that knows that — everything that reads or
 * writes a collection field goes through it, so the convention cannot drift
 * into six copies that disagree.
 */
final class CollectionMetaKeyTest extends TestCase {

	public function test_a_key_is_the_post_type_and_the_field(): void {
		$this->assertSame(
			'clubhouse_team_sport',
			Blueworx_Clubhouse_Collection_Meta::meta_key( 'clubhouse_team', 'sport' )
		);
	}

	public function test_the_same_field_on_two_collections_is_two_keys(): void {
		$this->assertNotSame(
			Blueworx_Clubhouse_Collection_Meta::meta_key( 'clubhouse_team', 'sport' ),
			Blueworx_Clubhouse_Collection_Meta::meta_key( 'clubhouse_fixture', 'sport' )
		);
	}

	/**
	 * The convention is the library's, not ours. Asserted against a real
	 * screen the library has normalised rather than against a literal repeated
	 * from its source — if the library ever changes how it derives a key, this
	 * fails here rather than on a club's site.
	 */
	public function test_it_matches_what_the_library_actually_writes(): void {
		$screen = \Blueworx\PageEditor\v1\Schema::validate(
			array(
				'slug'      => 'test-screen',
				'title'     => 'Test',
				'store'     => 'post',
				'post_type' => 'clubhouse_team',
				'tabs'      => array(
					array(
						'id'     => 'details',
						'label'  => 'Details',
						'panels' => array(
							array(
								'id'     => 'details',
								'title'  => 'Details',
								'fields' => array(
									array( 'id' => 'sport', 'kind' => 'text', 'label' => 'Sport' ),
								),
							),
						),
					),
				),
			)
		);

		wp_stub_reset();
		$store = \Blueworx\PageEditor\v1\Store::for( $screen );
		$store->write( array( 'sport' => 'Hockey' ), 7 );

		$this->assertSame(
			'Hockey',
			get_post_meta( 7, Blueworx_Clubhouse_Collection_Meta::meta_key( 'clubhouse_team', 'sport' ), true )
		);
	}
}
