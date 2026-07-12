<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps a raw post record ({title, meta:array}) to a canonical collection array.
 * Pure — no WordPress. WP_Collections fetches the raw records; this fills the
 * canonical shape and defaults missing meta to empty strings.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Mappers {

	private static function m( array $post, string $key ): string {
		return isset( $post['meta'][ $key ] ) ? (string) $post['meta'][ $key ] : '';
	}

	public static function sport( array $post ): array {
		return array(
			'title'       => (string) $post['title'],
			'label'       => self::m( $post, 'label' ),
			'subtitle'    => self::m( $post, 'subtitle' ),
			'description' => self::m( $post, 'description' ),
			'stat1_value' => self::m( $post, 'stat1_value' ),
			'stat1_label' => self::m( $post, 'stat1_label' ),
			'stat2_value' => self::m( $post, 'stat2_value' ),
			'stat2_label' => self::m( $post, 'stat2_label' ),
			'image'       => self::m( $post, 'image' ),
		);
	}

	public static function team( array $post ): array {
		return array(
			'title'       => (string) $post['title'],
			'sport'       => self::m( $post, 'sport' ),
			'description' => self::m( $post, 'description' ),
			'match_day'   => self::m( $post, 'match_day' ),
			'league'      => self::m( $post, 'league' ),
			'image'       => self::m( $post, 'image' ),
		);
	}

	public static function fixture( array $post ): array {
		return array(
			'sport'          => self::m( $post, 'sport' ),
			'match_date'     => self::m( $post, 'match_date' ),
			'kickoff_time'   => self::m( $post, 'kickoff_time' ),
			'venue'          => self::m( $post, 'venue' ),
			'home'           => self::m( $post, 'home_team' ),
			'away'           => self::m( $post, 'away_team' ),
			'score'          => self::m( $post, 'score' ),
			'outcome'        => self::m( $post, 'outcome' ),
			'result_summary' => self::m( $post, 'result_summary' ),
		);
	}

	public static function event( array $post ): array {
		return array(
			'title'     => (string) $post['title'],
			'tag'       => self::m( $post, 'tag' ),
			'date'      => self::m( $post, 'date' ),
			'detail'    => self::m( $post, 'detail' ),
			'cta_label' => self::m( $post, 'cta_label' ),
			'cta_href'  => self::m( $post, 'cta_href' ),
			'status'    => '' === self::m( $post, 'status' ) ? 'upcoming' : self::m( $post, 'status' ),
		);
	}

	public static function sponsor( array $post ): array {
		return array( 'name' => (string) $post['title'], 'url' => self::m( $post, 'url' ) );
	}

	public static function person( array $post ): array {
		return array(
			'name'           => (string) $post['title'],
			'committee_role' => self::m( $post, 'committee_role' ),
			'directory_role' => self::m( $post, 'directory_role' ),
			'email'          => self::m( $post, 'email' ),
		);
	}
}
