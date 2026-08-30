<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One club, one run: copies every collection field from the bare meta key it
 * used to live under to the one the page editor library reads.
 *
 * A collection's fields were stored as `sport`, `venue`, `image`. The library's
 * post store derives its own key from the post type and the field id —
 * `clubhouse_fixture_venue` — and offers no way to override it, so the values
 * move rather than the convention bending.
 *
 * The old value is left where it is. It costs nothing, it is the only copy of
 * the previous state, and nothing reads it any more. Deleted in phase 6 with
 * this class.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Migration {

	/**
	 * Marks that run() has completed at least once — not that it moved
	 * anything, since a club with no collections yet is still a completed run.
	 */
	private const DONE_KEY = 'collection_migration_done';

	/**
	 * Copy every field of every collection record to its new address.
	 *
	 * A field that was never saved is not written: the library reads an absent
	 * key as the field's declared default, and writing an empty string here
	 * would turn "never answered" into "answered with nothing" for every
	 * record on the site.
	 *
	 * @return array{moved:int,records:int}
	 */
	public static function run( Blueworx_Clubhouse_Storage $storage ): array {
		$moved   = 0;
		$records = 0;

		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			foreach ( self::records_of( $type ) as $post_id ) {
				++$records;
				foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
					$moved += self::move( $post_id, $type, (string) $field['key'] ) ? 1 : 0;
				}
			}
		}

		$storage->set( self::DONE_KEY, true );

		return array( 'moved' => $moved, 'records' => $records );
	}

	public static function has_run( Blueworx_Clubhouse_Storage $storage ): bool {
		return (bool) $storage->get( self::DONE_KEY, false );
	}

	/**
	 * One field. Returns whether anything was written — an unsaved field, and
	 * one already at its new address, both leave the record alone, which is
	 * what makes a second run a no-op.
	 */
	private static function move( int $post_id, string $type, string $key ): bool {
		if ( ! function_exists( 'metadata_exists' ) ) {
			return false;
		}
		$new = Blueworx_Clubhouse_Collection_Meta::meta_key( $type, $key );
		if ( metadata_exists( 'post', $post_id, $new ) ) {
			return false;
		}
		if ( ! metadata_exists( 'post', $post_id, $key ) ) {
			return false;
		}

		$value = get_post_meta( $post_id, $key, true );
		update_post_meta( $post_id, $new, $value );

		return true;
	}

	/**
	 * Every record of a type, whatever its status — a club's drafts are as
	 * much their work as their published ones, and a migration that moved only
	 * the published ones would empty the rest on the first save.
	 *
	 * @return array<int,int>
	 */
	private static function records_of( string $type ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'numberposts'    => -1,
				'fields'         => 'ids',
				'suppress_filters' => true,
			)
		);
		return is_array( $posts ) ? array_map( 'intval', $posts ) : array();
	}
}
