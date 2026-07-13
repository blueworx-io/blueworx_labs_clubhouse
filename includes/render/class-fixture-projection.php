<?php
// includes/render/class-fixture-projection.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Projects canonical fixtures to the three renderer shapes: Home upcoming
 * (activity_tabs fixtures), Home played (results), and Calendar month groups.
 * Pure — no WordPress, no ambient time; ordering is by the stored match_date.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Fixture_Projection {

	/** Strict parse; null for empty/invalid input so a bad date never becomes "now". */
	private static function try_date( string $iso ): ?DateTimeImmutable {
		$iso = trim( $iso );
		if ( '' === $iso ) {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $iso );
		return ( false !== $date && $date->format( 'Y-m-d' ) === $iso ) ? $date : null;
	}

	/** @param array<int,array<string,mixed>> $fixtures @return array<int,array<string,string>> */
	public static function home_fixtures( array $fixtures, int $limit = 3 ): array {
		$upcoming = array_values( array_filter( $fixtures, static fn( $f ) => '' === $f['outcome'] && null !== self::try_date( $f['match_date'] ) ) );
		usort( $upcoming, static fn( $a, $b ) => strcmp( $a['match_date'], $b['match_date'] ) );
		$upcoming = array_slice( $upcoming, 0, $limit );
		return array_map(
			static function ( array $f ): array {
				$d = self::try_date( $f['match_date'] );
				return array(
					'month'       => strtoupper( $d->format( 'M' ) ),
					'day'         => $d->format( 'j' ),
					'competition' => $f['sport'],
					'time'        => $f['kickoff_time'],
					'matchup'     => $f['home'] . ' vs ' . $f['away'],
				);
			},
			$upcoming
		);
	}

	/** @param array<int,array<string,mixed>> $fixtures @return array<int,array<string,string>> */
	public static function home_results( array $fixtures, int $limit = 3 ): array {
		$played = array_values( array_filter( $fixtures, static fn( $f ) => '' !== $f['outcome'] && null !== self::try_date( $f['match_date'] ) ) );
		usort( $played, static fn( $a, $b ) => strcmp( $b['match_date'], $a['match_date'] ) );
		$played = array_slice( $played, 0, $limit );
		return array_map(
			static function ( array $f ): array {
				$d = self::try_date( $f['match_date'] );
				return array(
					'date'    => strtoupper( $d->format( 'M' ) ) . ' ' . $d->format( 'j' ),
					'home'    => $f['home'],
					'away'    => $f['away'],
					'score'   => $f['score'],
					'outcome' => $f['outcome'],
				);
			},
			$played
		);
	}

	/** @param array<int,array<string,mixed>> $fixtures @return array<int,array{label:string,rows:array<int,array<string,string>>}> */
	public static function calendar_months( array $fixtures ): array {
		$sorted = $fixtures;
		usort( $sorted, static fn( $a, $b ) => strcmp( $b['match_date'], $a['match_date'] ) );
		$groups = array();
		$labels = array();
		$order  = array();
		foreach ( $sorted as $f ) {
			$date = self::try_date( $f['match_date'] );
			// '~' sorts after any 'YYYY-MM' so undated fixtures land last.
			$sort_key = null !== $date ? $date->format( 'Y-m' ) : '~';
			$label    = null !== $date ? $date->format( 'F Y' ) : 'Date TBC';
			if ( ! isset( $groups[ $sort_key ] ) ) {
				$groups[ $sort_key ] = array();
				$labels[ $sort_key ] = $label;
				$order[]             = $sort_key;
			}
			$detail = '' === $f['outcome']
				? $f['venue'] . ' · ' . $f['kickoff_time']
				: $f['result_summary'];
			$groups[ $sort_key ][] = array(
				'date'        => null !== $date ? $date->format( 'D j' ) : 'TBC',
				'competition' => $f['sport'],
				'matchup'     => $f['home'] . ' vs ' . $f['away'],
				'detail'      => $detail,
				'outcome'     => $f['outcome'],
			);
		}
		// Sort 'YYYY-MM' descending; keep '~' (undated) at the end.
		$dated_keys = array_filter( $order, static fn( $k ) => '~' !== $k );
		rsort( $dated_keys );
		if ( in_array( '~', $order, true ) ) {
			$dated_keys[] = '~';
		}
		$out = array();
		foreach ( $dated_keys as $sort_key ) {
			$out[] = array( 'label' => $labels[ $sort_key ], 'rows' => $groups[ $sort_key ] );
		}
		return $out;
	}
}
