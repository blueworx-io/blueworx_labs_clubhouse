<?php

use PHPUnit\Framework\TestCase;

/**
 * The extra columns on a collection's own WordPress list.
 */
final class CollectionColumnsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	private function given( int $post_id, string $type, string $key, string $value ): void {
		update_post_meta( $post_id, Blueworx_Clubhouse_Collection_Meta::meta_key( $type, $key ), $value );
	}

	public function test_our_columns_sit_between_the_title_and_the_date(): void {
		$cols = Blueworx_Clubhouse_Collection_Columns::merge_columns(
			'clubhouse_team',
			array( 'cb' => 'x', 'title' => 'Title', 'date' => 'Date' )
		);

		$this->assertSame(
			array( 'cb', 'title', 'clubhouse_sport', 'clubhouse_league', 'clubhouse_match_day', 'date' ),
			array_keys( $cols )
		);
	}

	public function test_a_column_reads_the_address_the_editor_writes(): void {
		$this->given( 7, 'clubhouse_team', 'league', 'Division 2' );

		$this->assertSame(
			'Division 2',
			Blueworx_Clubhouse_Collection_Columns::column_value( 'clubhouse_team', 'clubhouse_league', 7 )
		);
	}

	/**
	 * A club updating the plugin sees its lists before the migration has run
	 * on the next admin request. They must not be blank in between.
	 */
	public function test_a_value_still_at_its_old_address_is_shown(): void {
		update_post_meta( 7, 'league', 'Division 2' );

		$this->assertSame(
			'Division 2',
			Blueworx_Clubhouse_Collection_Columns::column_value( 'clubhouse_team', 'clubhouse_league', 7 )
		);
	}

	public function test_a_fixtures_matchup_and_result_are_composed(): void {
		$this->given( 9, 'clubhouse_fixture', 'home_team', 'Ashwood 1st' );
		$this->given( 9, 'clubhouse_fixture', 'away_team', 'Marlow' );
		$this->given( 9, 'clubhouse_fixture', 'score', '3-1' );
		$this->given( 9, 'clubhouse_fixture', 'outcome', 'W' );

		$this->assertSame(
			'Ashwood 1st v Marlow',
			Blueworx_Clubhouse_Collection_Columns::column_value( 'clubhouse_fixture', 'clubhouse_matchup', 9 )
		);
		$this->assertSame(
			'3-1 (W)',
			Blueworx_Clubhouse_Collection_Columns::column_value( 'clubhouse_fixture', 'clubhouse_result', 9 )
		);
	}

	public function test_a_column_that_is_not_ours_is_left_alone(): void {
		$this->assertSame(
			'',
			Blueworx_Clubhouse_Collection_Columns::column_value( 'clubhouse_team', 'title', 7 )
		);
	}

	public function test_a_value_is_escaped(): void {
		$this->given( 7, 'clubhouse_team', 'league', '<script>alert(1)</script>' );

		$html = Blueworx_Clubhouse_Collection_Columns::column_value( 'clubhouse_team', 'clubhouse_league', 7 );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_it_hooks_every_collections_list(): void {
		Blueworx_Clubhouse_Collection_Columns::register();

		$filters = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_filter' ) );

		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			$this->assertContains( "manage_{$type}_posts_columns", $filters, $type );
		}
	}
}
