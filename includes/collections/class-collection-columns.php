<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The extra columns on each collection's own WordPress list — a fixture's date
 * and result, a team's league, a person's roles.
 *
 * All that is left of the old "Details" meta box. The editing half of that
 * class was replaced by the six page editor screens; these columns were never
 * part of it, they are WordPress's own list, and a club reads them to find the
 * record it wants before opening anything.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Columns {

	/** Column identifiers are prefixed so they cannot collide with WordPress's own. */
	private const PREFIX = 'clubhouse_';

	public static function register(): void {
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			add_filter(
				"manage_{$type}_posts_columns",
				static function ( $cols ) use ( $type ) {
					return self::merge_columns( $type, is_array( $cols ) ? $cols : array() );
				}
			);
			add_action(
				"manage_{$type}_posts_custom_column",
				static function ( $col, $post_id ) use ( $type ) {
					echo self::column_value( $type, (string) $col, (int) $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in column_value.
				},
				10,
				2
			);
		}
	}

	/**
	 * Our columns between the title and the date, which is where a reader
	 * expects what the row is about.
	 *
	 * @param array<string,string> $cols
	 * @return array<string,string>
	 */
	public static function merge_columns( string $type, array $cols ): array {
		$out = array();
		if ( isset( $cols['cb'] ) ) {
			$out['cb'] = $cols['cb'];
		}
		if ( isset( $cols['title'] ) ) {
			$out['title'] = $cols['title'];
		}
		foreach ( Blueworx_Clubhouse_Collection_Meta::columns( $type ) as $key => $col_label ) {
			$out[ self::PREFIX . $key ] = $col_label;
		}
		if ( isset( $cols['date'] ) ) {
			$out['date'] = $cols['date'];
		}
		return $out;
	}

	public static function column_value( string $type, string $col, int $post_id ): string {
		if ( 0 !== strpos( $col, self::PREFIX ) ) {
			return '';
		}
		$key = substr( $col, strlen( self::PREFIX ) );

		// Two columns are composed rather than stored: a fixture's matchup and
		// its result each read more than one field.
		if ( 'clubhouse_fixture' === $type && 'matchup' === $key ) {
			return self::e( self::field( $post_id, $type, 'home_team' ) . ' v ' . self::field( $post_id, $type, 'away_team' ) );
		}
		if ( 'clubhouse_fixture' === $type && 'result' === $key ) {
			$score   = self::field( $post_id, $type, 'score' );
			$outcome = self::field( $post_id, $type, 'outcome' );
			return self::e( trim( $score . ( '' !== $outcome ? ' (' . $outcome . ')' : '' ) ) );
		}
		return self::e( self::field( $post_id, $type, $key ) );
	}

	/**
	 * One field's stored value, at the address the page editor library reads.
	 *
	 * Falls back to the bare key a value used to live under, so a list is not
	 * blank between a plugin update and the migration that runs on the next
	 * admin request.
	 */
	private static function field( int $post_id, string $type, string $key ): string {
		$value = (string) get_post_meta( $post_id, Blueworx_Clubhouse_Collection_Meta::meta_key( $type, $key ), true );
		return '' !== $value ? $value : (string) get_post_meta( $post_id, $key, true );
	}

	private static function e( string $s ): string {
		return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
	}
}
