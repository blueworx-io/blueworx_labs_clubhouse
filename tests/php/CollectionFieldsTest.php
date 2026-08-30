<?php

use PHPUnit\Framework\TestCase;

/**
 * The six collection editors, as the library receives them.
 */
final class CollectionFieldsTest extends TestCase {

	/** @return array<int,array<int,string>> */
	public static function types(): array {
		return array_map(
			static fn( string $type ): array => array( $type ),
			Blueworx_Clubhouse_Collection_Meta::types()
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'types' )]
	public function test_every_collection_passes_the_librarys_own_shape_check( string $type ): void {
		$this->assertIsArray(
			\Blueworx\PageEditor\v1\Schema::validate( Blueworx_Clubhouse_Collection_Fields::screen( $type ) ),
			$type
		);
	}

	/**
	 * A field Collection_Meta declares and this screen forgets is a field a
	 * club can no longer edit, with nothing to say it has gone.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'types' )]
	public function test_every_field_the_collection_has_is_on_its_screen( string $type ): void {
		$ids = array_column( Blueworx_Clubhouse_Collection_Fields::fields( $type ), 'id' );

		foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
			$this->assertContains( $field['key'], $ids, $type . '/' . $field['key'] );
		}
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'types' )]
	public function test_the_records_own_name_is_the_first_thing_on_the_screen( string $type ): void {
		$first = Blueworx_Clubhouse_Collection_Fields::fields( $type )[0];

		$this->assertSame( 'post_title', $first['id'] );
		$this->assertSame( 'title', $first['kind'] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'types' )]
	public function test_a_collection_is_reachable_by_a_content_editor( string $type ): void {
		$this->assertSame(
			Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			Blueworx_Clubhouse_Collection_Fields::screen( $type )['capability']
		);
	}

	public function test_a_fixtures_outcome_offers_the_three_results_in_words(): void {
		$fields = array_column( Blueworx_Clubhouse_Collection_Fields::fields( 'clubhouse_fixture' ), null, 'id' );

		$this->assertSame(
			array( 'W', 'D', 'L' ),
			array_column( $fields['outcome']['options'], 'value' )
		);
		$this->assertSame(
			array( 'Won', 'Drew', 'Lost' ),
			array_column( $fields['outcome']['options'], 'label' )
		);
	}

	/** The library's select draws its own blank; a second one is a choice nobody means to make. */
	public function test_a_select_offers_no_blank_of_its_own(): void {
		$fields = array_column( Blueworx_Clubhouse_Collection_Fields::fields( 'clubhouse_fixture' ), null, 'id' );

		$this->assertNotContains( '', array_column( $fields['outcome']['options'], 'value' ) );
	}

	public function test_a_kick_off_time_says_what_shape_it_wants(): void {
		$fields = array_column( Blueworx_Clubhouse_Collection_Fields::fields( 'clubhouse_fixture' ), null, 'id' );

		$this->assertSame( 'text', $fields['kickoff_time']['kind'] );
		$this->assertStringContainsString( '14:30', $fields['kickoff_time']['help'] );
	}

	public function test_a_photo_is_a_media_field_and_an_email_is_checked(): void {
		$fields = array_column( Blueworx_Clubhouse_Collection_Fields::fields( 'clubhouse_person' ), null, 'id' );

		$this->assertSame( 'media', $fields['photo']['kind'] );
		$this->assertSame( 'email', $fields['email']['format'] );
	}

	/** A sponsor's website is a real address; an event's button link may be site-relative. */
	public function test_a_url_is_checked_and_a_permissive_link_is_not(): void {
		$sponsor = array_column( Blueworx_Clubhouse_Collection_Fields::fields( 'clubhouse_sponsor' ), null, 'id' );
		$event   = array_column( Blueworx_Clubhouse_Collection_Fields::fields( 'clubhouse_event' ), null, 'id' );

		$this->assertSame( 'url', $sponsor['url']['format'] );
		$this->assertArrayNotHasKey( 'format', $event['cta_href'] );
	}

	public function test_each_collection_has_its_own_slug(): void {
		$slugs = array_map(
			array( Blueworx_Clubhouse_Collection_Fields::class, 'slug_for' ),
			Blueworx_Clubhouse_Collection_Meta::types()
		);

		$this->assertSame( $slugs, array_unique( $slugs ) );
		$this->assertSame( 'clubhouse-edit-clubhouse_team', Blueworx_Clubhouse_Collection_Fields::slug_for( 'clubhouse_team' ) );
	}
}
