<?php

use PHPUnit\Framework\TestCase;

/**
 * The one-off that moves a club's collection fields to the addresses the page
 * editor library reads.
 *
 * What matters is that nothing is lost and nothing is invented: a value
 * arrives intact, a field never answered stays unanswered, and running it
 * twice does nothing the second time.
 */
final class CollectionMigrationTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	private function storage(): Blueworx_Clubhouse_Fake_Storage {
		return new Blueworx_Clubhouse_Fake_Storage();
	}

	/** @param array<string,string> $meta */
	private function given_a_record( string $type, int $post_id, array $meta ): void {
		$GLOBALS['wp_stub_posts'][ $type ][] = (object) array( 'ID' => $post_id, 'post_type' => $type );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	private function key( string $type, string $field ): string {
		return Blueworx_Clubhouse_Collection_Meta::meta_key( $type, $field );
	}

	public function test_a_value_arrives_at_its_new_address(): void {
		$this->given_a_record( 'clubhouse_team', 11, array( 'sport' => 'Hockey', 'league' => 'Division 2' ) );

		Blueworx_Clubhouse_Collection_Migration::run( $this->storage() );

		$this->assertSame( 'Hockey', get_post_meta( 11, $this->key( 'clubhouse_team', 'sport' ), true ) );
		$this->assertSame( 'Division 2', get_post_meta( 11, $this->key( 'clubhouse_team', 'league' ), true ) );
	}

	public function test_the_old_value_is_left_where_it_was(): void {
		$this->given_a_record( 'clubhouse_team', 11, array( 'sport' => 'Hockey' ) );

		Blueworx_Clubhouse_Collection_Migration::run( $this->storage() );

		$this->assertSame( 'Hockey', get_post_meta( 11, 'sport', true ) );
	}

	/**
	 * An unanswered field must stay unanswered. Written as an empty string it
	 * would read back as "answered with nothing", and the library would stop
	 * offering the field's declared default.
	 */
	public function test_a_field_never_answered_is_not_invented(): void {
		$this->given_a_record( 'clubhouse_team', 11, array( 'sport' => 'Hockey' ) );

		Blueworx_Clubhouse_Collection_Migration::run( $this->storage() );

		$this->assertFalse( metadata_exists( 'post', 11, $this->key( 'clubhouse_team', 'league' ) ) );
	}

	public function test_an_empty_answer_a_club_actually_gave_is_carried_across(): void {
		$this->given_a_record( 'clubhouse_team', 11, array( 'league' => '' ) );

		Blueworx_Clubhouse_Collection_Migration::run( $this->storage() );

		$this->assertTrue( metadata_exists( 'post', 11, $this->key( 'clubhouse_team', 'league' ) ) );
		$this->assertSame( '', get_post_meta( 11, $this->key( 'clubhouse_team', 'league' ), true ) );
	}

	public function test_every_collection_is_walked(): void {
		$this->given_a_record( 'clubhouse_fixture', 1, array( 'venue' => 'Home ground' ) );
		$this->given_a_record( 'clubhouse_person', 2, array( 'email' => 'chair@club.test' ) );
		$this->given_a_record( 'clubhouse_sponsor', 3, array( 'url' => 'https://sponsor.test' ) );

		$report = Blueworx_Clubhouse_Collection_Migration::run( $this->storage() );

		$this->assertSame( 'Home ground', get_post_meta( 1, $this->key( 'clubhouse_fixture', 'venue' ), true ) );
		$this->assertSame( 'chair@club.test', get_post_meta( 2, $this->key( 'clubhouse_person', 'email' ), true ) );
		$this->assertSame( 'https://sponsor.test', get_post_meta( 3, $this->key( 'clubhouse_sponsor', 'url' ), true ) );
		$this->assertSame( 3, $report['records'] );
		$this->assertSame( 3, $report['moved'] );
	}

	public function test_a_second_run_moves_nothing(): void {
		$this->given_a_record( 'clubhouse_team', 11, array( 'sport' => 'Hockey' ) );
		$storage = $this->storage();
		Blueworx_Clubhouse_Collection_Migration::run( $storage );

		$again = Blueworx_Clubhouse_Collection_Migration::run( $storage );

		$this->assertSame( 0, $again['moved'] );
		$this->assertSame( 'Hockey', get_post_meta( 11, $this->key( 'clubhouse_team', 'sport' ), true ) );
	}

	/** A value already edited on the new screen is never overwritten by the old one. */
	public function test_it_never_overwrites_what_is_already_there(): void {
		$this->given_a_record( 'clubhouse_team', 11, array( 'sport' => 'Hockey' ) );
		update_post_meta( 11, $this->key( 'clubhouse_team', 'sport' ), 'Netball' );

		Blueworx_Clubhouse_Collection_Migration::run( $this->storage() );

		$this->assertSame( 'Netball', get_post_meta( 11, $this->key( 'clubhouse_team', 'sport' ), true ) );
	}

	public function test_it_records_that_it_has_run(): void {
		$storage = $this->storage();
		$this->assertFalse( Blueworx_Clubhouse_Collection_Migration::has_run( $storage ) );

		Blueworx_Clubhouse_Collection_Migration::run( $storage );

		$this->assertTrue( Blueworx_Clubhouse_Collection_Migration::has_run( $storage ) );
	}
}
