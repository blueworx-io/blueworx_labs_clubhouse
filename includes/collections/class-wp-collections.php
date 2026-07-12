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
		$out = array();
		foreach ( $posts as $post ) {
			$id    = is_object( $post ) ? $post->ID : (int) $post;
			$out[] = $mapper( array(
				'title' => get_the_title( $post ),
				'meta'  => self::flatten_meta( $id ),
			) );
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
