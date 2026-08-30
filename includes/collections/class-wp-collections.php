<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the six collections from their custom-post-type posts and maps each to
 * the canonical shape via Collection_Mappers. Thin WordPress glue — the mapping
 * logic is pure and unit-tested.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_WP_Collections implements Blueworx_Clubhouse_Collections {

	/** @param callable(array):array $mapper */
	private function fetch( string $post_type, callable $mapper ): array {
		$posts = get_posts( array(
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		) );
		$media_keys = Blueworx_Clubhouse_Collection_Meta::media_keys( $post_type );
		$out        = array();
		foreach ( $posts as $post ) {
			$id   = is_object( $post ) ? $post->ID : (int) $post;
			$meta = self::field_values( $post_type, self::flatten_meta( $id ) );
			foreach ( $media_keys as $key ) {
				if ( isset( $meta[ $key ] ) && ctype_digit( $meta[ $key ] ) ) {
					$meta[ $key ] = Blueworx_Clubhouse_Media::url( (int) $meta[ $key ] );
				}
			}
			$out[] = $mapper( array(
				'title' => get_the_title( $post ),
				'meta'  => $meta,
			) );
		}
		return $out;
	}

	/**
	 * A record's meta, keyed the way the rest of this plugin talks about a
	 * collection field: `sport`, not `clubhouse_team_sport`.
	 *
	 * The page editor library derives its own meta key from the post type and
	 * the field id, and this is where that stops mattering. Everything
	 * downstream — the mappers, the renderers, the preview, the tests — goes on
	 * reading a plain field name, so the storage convention is known in two
	 * places (Collection_Meta::meta_key(), and here) rather than in every one
	 * of them.
	 *
	 * A value still under the old bare key is used when the new one is absent:
	 * the front end must not go blank between a plugin update and the
	 * migration running on the first admin request after it.
	 *
	 * @param array<string,string> $meta
	 * @return array<string,string>
	 */
	private static function field_values( string $type, array $meta ): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
			$key = (string) $field['key'];
			$new = Blueworx_Clubhouse_Collection_Meta::meta_key( $type, $key );
			if ( array_key_exists( $new, $meta ) ) {
				$out[ $key ] = $meta[ $new ];
				continue;
			}
			if ( array_key_exists( $key, $meta ) ) {
				$out[ $key ] = $meta[ $key ];
			}
		}
		return $out;
	}

	/** @return array<string,string> */
	private static function flatten_meta( int $id ): array {
		$raw  = get_post_meta( $id );
		$flat = array();
		foreach ( is_array( $raw ) ? $raw : array() as $key => $vals ) {
			$flat[ $key ] = is_array( $vals ) ? (string) ( $vals[0] ?? '' ) : (string) $vals;
		}
		return $flat;
	}

	public function sports(): array {
		return $this->fetch( 'clubhouse_sport', array( Blueworx_Clubhouse_Collection_Mappers::class, 'sport' ) );
	}
	public function teams(): array {
		return $this->fetch( 'clubhouse_team', array( Blueworx_Clubhouse_Collection_Mappers::class, 'team' ) );
	}
	public function fixtures(): array {
		return $this->fetch( 'clubhouse_fixture', array( Blueworx_Clubhouse_Collection_Mappers::class, 'fixture' ) );
	}
	public function events(): array {
		return $this->fetch( 'clubhouse_event', array( Blueworx_Clubhouse_Collection_Mappers::class, 'event' ) );
	}
	public function sponsors(): array {
		return $this->fetch( 'clubhouse_sponsor', array( Blueworx_Clubhouse_Collection_Mappers::class, 'sponsor' ) );
	}
	public function people(): array {
		return $this->fetch( 'clubhouse_person', array( Blueworx_Clubhouse_Collection_Mappers::class, 'person' ) );
	}
}
