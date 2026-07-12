<?php
// includes/collections/class-collection-seeder.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds each empty collection with the ClubHouse demo content on activation, so
 * a fresh install renders a populated site before any data is entered.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Seeder {

	public static function seed(): void {
		self::seed_type( 'clubhouse_sport', Blueworx_Clubhouse_Demo_Content::sports(), 'title', array( 'label', 'subtitle', 'description', 'stat1_value', 'stat1_label', 'stat2_value', 'stat2_label', 'image' ) );
		self::seed_type( 'clubhouse_team', Blueworx_Clubhouse_Demo_Content::teams(), 'title', array( 'sport', 'description', 'match_day', 'league', 'image' ) );
		self::seed_fixtures();
		self::seed_type( 'clubhouse_event', Blueworx_Clubhouse_Demo_Content::events(), 'title', array( 'tag', 'date', 'detail', 'cta_label', 'cta_href', 'status' ) );
		self::seed_type( 'clubhouse_sponsor', Blueworx_Clubhouse_Demo_Content::sponsors(), 'name', array( 'url' ) );
		self::seed_type( 'clubhouse_person', Blueworx_Clubhouse_Demo_Content::people(), 'name', array( 'committee_role', 'directory_role', 'email' ) );
	}

	/** @param array<int,array<string,mixed>> $items */
	private static function seed_type( string $type, array $items, string $title_key, array $meta_keys ): void {
		if ( self::has_posts( $type ) ) {
			return;
		}
		$order = 0;
		foreach ( $items as $item ) {
			$id = wp_insert_post( array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => (string) $item[ $title_key ],
				'menu_order'  => $order++,
			) );
			foreach ( $meta_keys as $key ) {
				add_post_meta( (int) $id, $key, isset( $item[ $key ] ) ? (string) $item[ $key ] : '' );
			}
		}
	}

	private static function seed_fixtures(): void {
		if ( self::has_posts( 'clubhouse_fixture' ) ) {
			return;
		}
		$order = 0;
		foreach ( Blueworx_Clubhouse_Demo_Content::fixtures() as $f ) {
			$id = wp_insert_post( array(
				'post_type'   => 'clubhouse_fixture',
				'post_status' => 'publish',
				'post_title'  => $f['home'] . ' vs ' . $f['away'],
				'menu_order'  => $order++,
			) );
			$meta = array(
				'sport' => $f['sport'], 'match_date' => $f['match_date'], 'kickoff_time' => $f['kickoff_time'],
				'venue' => $f['venue'], 'home_team' => $f['home'], 'away_team' => $f['away'],
				'score' => $f['score'], 'outcome' => $f['outcome'], 'result_summary' => $f['result_summary'],
			);
			foreach ( $meta as $key => $value ) {
				add_post_meta( (int) $id, $key, (string) $value );
			}
		}
	}

	private static function has_posts( string $type ): bool {
		$existing = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'numberposts' => 1 ) );
		return ! empty( $existing );
	}
}
