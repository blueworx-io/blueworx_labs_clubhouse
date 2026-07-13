<?php
// includes/collections/class-collection-meta.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for the six collections' editable fields: per-CPT field
 * definitions (key, label, input type, select options), pure sanitisers, media-key
 * lists, and admin list-column maps. Pure — no WordPress. Shared by the meta-box
 * glue (render + save), by WP_Collections (which keys are media), and asserted in
 * tests. The keys here match the mapper reads and the seeder writes exactly.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Meta {

	/** @var array<string,array<int,array{key:string,label:string,type:string,options?:array<int,string>,default?:string}>> */
	private const FIELDS = array(
		'clubhouse_fixture' => array(
			array( 'key' => 'sport',          'label' => 'Sport',        'type' => 'text' ),
			array( 'key' => 'match_date',     'label' => 'Match date',   'type' => 'date' ),
			array( 'key' => 'kickoff_time',   'label' => 'Kick-off',     'type' => 'time' ),
			array( 'key' => 'venue',          'label' => 'Venue',        'type' => 'text' ),
			array( 'key' => 'home_team',      'label' => 'Home team',    'type' => 'text' ),
			array( 'key' => 'away_team',      'label' => 'Away team',    'type' => 'text' ),
			array( 'key' => 'score',          'label' => 'Score',        'type' => 'text' ),
			array( 'key' => 'outcome',        'label' => 'Outcome',      'type' => 'select', 'options' => array( '', 'W', 'D', 'L' ), 'default' => '' ),
			array( 'key' => 'result_summary', 'label' => 'Result note',  'type' => 'text' ),
		),
		'clubhouse_person' => array(
			array( 'key' => 'committee_role', 'label' => 'Committee role', 'type' => 'text' ),
			array( 'key' => 'directory_role', 'label' => 'Directory role', 'type' => 'text' ),
			array( 'key' => 'email',          'label' => 'Email',          'type' => 'email' ),
		),
		'clubhouse_sponsor' => array(
			array( 'key' => 'url', 'label' => 'Website URL', 'type' => 'url' ),
		),
		'clubhouse_sport' => array(
			array( 'key' => 'label',       'label' => 'Short label',  'type' => 'text' ),
			array( 'key' => 'subtitle',    'label' => 'Subtitle',     'type' => 'text' ),
			array( 'key' => 'description', 'label' => 'Description',   'type' => 'textarea' ),
			array( 'key' => 'stat1_value', 'label' => 'Stat 1 value', 'type' => 'text' ),
			array( 'key' => 'stat1_label', 'label' => 'Stat 1 label', 'type' => 'text' ),
			array( 'key' => 'stat2_value', 'label' => 'Stat 2 value', 'type' => 'text' ),
			array( 'key' => 'stat2_label', 'label' => 'Stat 2 label', 'type' => 'text' ),
			array( 'key' => 'image',       'label' => 'Image',        'type' => 'media' ),
		),
		'clubhouse_team' => array(
			array( 'key' => 'sport',       'label' => 'Sport',       'type' => 'text' ),
			array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea' ),
			array( 'key' => 'match_day',   'label' => 'Match day',   'type' => 'text' ),
			array( 'key' => 'league',      'label' => 'League',      'type' => 'text' ),
			array( 'key' => 'image',       'label' => 'Image',       'type' => 'media' ),
		),
		'clubhouse_event' => array(
			array( 'key' => 'tag',       'label' => 'Tag',          'type' => 'text' ),
			array( 'key' => 'date',      'label' => 'Date label',   'type' => 'text' ),
			array( 'key' => 'detail',    'label' => 'Detail',       'type' => 'textarea' ),
			array( 'key' => 'cta_label', 'label' => 'Button label', 'type' => 'text' ),
			array( 'key' => 'cta_href',  'label' => 'Button link',  'type' => 'href' ),
			array( 'key' => 'status',    'label' => 'Status',       'type' => 'select', 'options' => array( 'upcoming', 'past' ), 'default' => 'upcoming' ),
		),
	);

	/**
	 * Admin list columns per type. Keys are column identifiers (not always a single
	 * meta key): 'matchup' and 'result' are composed by the glue from other meta.
	 *
	 * @var array<string,array<string,string>>
	 */
	private const COLUMNS = array(
		'clubhouse_fixture' => array( 'match_date' => 'Date', 'matchup' => 'Home v Away', 'result' => 'Result' ),
		'clubhouse_team'    => array( 'sport' => 'Sport', 'league' => 'League', 'match_day' => 'Match day' ),
		'clubhouse_person'  => array( 'committee_role' => 'Committee', 'directory_role' => 'Directory', 'email' => 'Email' ),
		'clubhouse_event'   => array( 'date' => 'Date', 'tag' => 'Tag', 'status' => 'Status' ),
		'clubhouse_sport'   => array( 'subtitle' => 'Subtitle', 'stat1_value' => 'Stat 1' ),
		'clubhouse_sponsor' => array( 'url' => 'URL' ),
	);

	/** @return array<int,string> */
	public static function types(): array {
		return array_keys( self::FIELDS );
	}

	/** @return array<int,array{key:string,label:string,type:string,options?:array<int,string>,default?:string}> */
	public static function fields( string $type ): array {
		return self::FIELDS[ $type ] ?? array();
	}

	/** @return array<int,string> */
	public static function media_keys( string $type ): array {
		$keys = array();
		foreach ( self::fields( $type ) as $field ) {
			if ( 'media' === $field['type'] ) {
				$keys[] = $field['key'];
			}
		}
		return $keys;
	}

	/** @return array<string,string> */
	public static function columns( string $type ): array {
		return self::COLUMNS[ $type ] ?? array();
	}

	public static function sanitise( string $type, string $key, string $raw ): string {
		$field = null;
		foreach ( self::fields( $type ) as $candidate ) {
			if ( $candidate['key'] === $key ) {
				$field = $candidate;
				break;
			}
		}
		if ( null === $field ) {
			return '';
		}
		switch ( $field['type'] ) {
			case 'textarea':
				return trim( strip_tags( $raw ) );
			case 'date':
				return self::valid_format( 'Y-m-d', $raw );
			case 'time':
				return self::valid_format( 'H:i', $raw );
			case 'email':
				$email = filter_var( trim( $raw ), FILTER_VALIDATE_EMAIL );
				return is_string( $email ) ? $email : '';
			case 'url':
				$url = filter_var( trim( $raw ), FILTER_VALIDATE_URL );
				return is_string( $url ) ? $url : '';
			case 'href':
				return self::href( $raw );
			case 'select':
				$options = $field['options'] ?? array();
				return in_array( $raw, $options, true ) ? $raw : (string) ( $field['default'] ?? '' );
			case 'media':
				$id = (int) $raw;
				return $id > 0 ? (string) $id : '';
			case 'text':
			default:
				return trim( (string) preg_replace( '/\s+/', ' ', strip_tags( $raw ) ) );
		}
	}

	/** Strict format check: accepts only input that round-trips through the format. */
	private static function valid_format( string $format, string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		$date = DateTimeImmutable::createFromFormat( '!' . $format, $raw );
		return ( false !== $date && $date->format( $format ) === $raw ) ? $raw : '';
	}

	/** Permissive link: keeps site-relative (?page=…, /path, #frag) and absolute URLs; blocks script schemes. */
	private static function href( string $raw ): string {
		$url = trim( strip_tags( $raw ) );
		if ( '' === $url || preg_match( '/^\s*(javascript|data|vbscript):/i', $url ) ) {
			return '';
		}
		return $url;
	}
}
