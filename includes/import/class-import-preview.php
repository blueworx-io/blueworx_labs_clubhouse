<?php
// includes/import/class-import-preview.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns an Import_Plan into the rows an owner reads before applying it: what
 * will change, in catalogue order, named the way the admin screens name things.
 * Pure — the demo-post counts come from the controller, which is the only layer
 * allowed to ask WordPress anything.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Preview {

	/**
	 * @param array<string,int> $demo_counts collection type => existing demo posts
	 * @return array{rows:array<int,array{label:string,detail:string}>,warnings:array<int,string>}
	 */
	public static function summary( Blueworx_Clubhouse_Import_Plan $plan, array $demo_counts ): array {
		// Whether the plan is empty is not a separate fact this contract needs to
		// carry: it is exactly "rows is empty", and Import_Screen already derives
		// its "nothing to import" message from an empty rows array. Plan::is_empty()
		// itself stays — it is the pre-preview guard the parser and its tests use —
		// this only removes the unused duplicate of it from the summary.
		$rows = array_merge(
			self::content_rows( $plan ),
			self::collection_rows( $plan, $demo_counts ),
			self::image_rows( $plan )
		);

		return array(
			'rows'     => $rows,
			'warnings' => $plan->warnings(),
		);
	}

	/** @return array<int,array{label:string,detail:string}> */
	private static function content_rows( Blueworx_Clubhouse_Import_Plan $plan ): array {
		$fields = $plan->fields();
		$items  = $plan->items();

		// The editors' own order, so the preview reads in the same order as the
		// screens and the site, rather than in whatever order the file happened to use.
		$addresses = array_keys( Blueworx_Clubhouse_Page_Fields::sections() );
		foreach ( array_keys( $fields ) as $page ) {
			foreach ( array_keys( $fields[ $page ] ) as $section ) {
				$addresses[] = $page . '/' . $section;
			}
		}
		foreach ( array_keys( $items ) as $page ) {
			foreach ( array_keys( $items[ $page ] ) as $section ) {
				$addresses[] = $page . '/' . $section;
			}
		}
		$addresses = array_values( array_unique( $addresses ) );

		$rows = array();
		foreach ( $addresses as $address ) {
			$parts   = explode( '/', $address, 2 );
			$page    = $parts[0];
			$section = $parts[1] ?? '';

			$field_count = isset( $fields[ $page ][ $section ] ) ? count( $fields[ $page ][ $section ] ) : 0;
			$item_count  = isset( $items[ $page ][ $section ] ) ? count( $items[ $page ][ $section ] ) : 0;
			if ( 0 === $field_count && 0 === $item_count ) {
				continue;
			}

			$detail = array();
			if ( $field_count > 0 ) {
				$detail[] = self::plural( $field_count, 'field', 'fields' );
			}
			if ( $item_count > 0 ) {
				$detail[] = self::plural( $item_count, 'entry', 'entries' );
			}

			$rows[] = array(
				'label'  => Blueworx_Clubhouse_Page_Fields::address_label( $address ),
				'detail' => implode( ', ', $detail ),
			);
		}
		return $rows;
	}

	/**
	 * @param array<string,int> $demo_counts
	 * @return array<int,array{label:string,detail:string}>
	 */
	private static function collection_rows( Blueworx_Clubhouse_Import_Plan $plan, array $demo_counts ): array {
		$collections = $plan->collections();
		$rows        = array();
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			if ( ! isset( $collections[ $type ] ) ) {
				continue;
			}
			$detail = self::plural( count( $collections[ $type ] ), 'entry', 'entries' );
			$demo   = (int) ( $demo_counts[ $type ] ?? 0 );
			if ( $demo > 0 ) {
				$detail .= ', replacing ' . self::plural( $demo, 'demo entry', 'demo entries' );
			}
			$rows[] = array( 'label' => Blueworx_Clubhouse_Collection_Meta::label( $type ), 'detail' => $detail );
		}
		return $rows;
	}

	/** @return array<int,array{label:string,detail:string}> */
	private static function image_rows( Blueworx_Clubhouse_Import_Plan $plan ): array {
		$count = count( $plan->images() );
		foreach ( $plan->collections() as $items ) {
			foreach ( $items as $item ) {
				$count += count( $item['images'] ?? array() );
			}
		}
		if ( 0 === $count ) {
			return array();
		}
		return array( array(
			'label'  => 'Images',
			'detail' => self::plural( $count, 'image', 'images' ) . ' to fetch',
		) );
	}

	private static function plural( int $n, string $one, string $many ): string {
		return $n . ' ' . ( 1 === $n ? $one : $many );
	}
}
