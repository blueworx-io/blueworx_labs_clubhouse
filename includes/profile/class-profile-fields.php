<?php
// includes/profile/class-profile-fields.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a custom member field can be, and how a submitted definition becomes a
 * valid one.
 *
 * Pure — no WordPress beyond the sanitisers. Every rule about what a field IS
 * lives here; what an ANSWER may be lives in Profile_Values.
 *
 * The key is generated once, from the label, and then never changes. That is
 * the whole reason a definition carries a key at all: an owner rewriting
 * "Shirt size" to "Kit size" must not detach every member's answer from it.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Fields {

	/** The seven types, in the order the builder offers them. */
	public const TYPES = array(
		'text'        => 'Short text',
		'textarea'    => 'Long text',
		'number'      => 'Number',
		'date'        => 'Date',
		'select'      => 'Dropdown (pick one)',
		'multiselect' => 'Dropdown (pick several)',
		'checkbox'    => 'Yes / no',
	);

	/**
	 * Who fills a field in.
	 *
	 * 'member'  — the member's own to write, on their Profile page and in wp-admin.
	 * 'club'    — the member sees the value and cannot change it; staff can.
	 * 'private' — the member never sees it at all; staff can.
	 */
	public const WHO = array(
		'member'  => 'The member fills this in',
		'club'    => 'The club fills this in, and the member can see it',
		'private' => 'The club fills this in, and the member never sees it',
	);

	public const DEFAULT_TYPE = 'text';
	public const DEFAULT_WHO  = 'member';

	/**
	 * Past this, the Profile page stops being a page anyone reads. A cap is a
	 * cheaper conversation than the page it prevents.
	 */
	public const MAX_FIELDS = 30;

	/** Only the two choice types have choices to offer. */
	public static function has_choices( string $type ): bool {
		return in_array( $type, array( 'select', 'multiselect' ), true );
	}

	/**
	 * A permanent key from a label, unique against the keys already taken.
	 *
	 * A label of pure punctuation still has to yield something storable, so the
	 * fallback is 'field' rather than an empty key that would collide with the
	 * next one.
	 *
	 * @param array<int,string> $taken
	 */
	public static function key_from_label( string $label, array $taken ): string {
		$base = strtolower( trim( $label ) );
		$base = (string) preg_replace( '/[^a-z0-9]+/', '_', $base );
		$base = trim( $base, '_' );
		if ( '' === $base ) {
			$base = 'field';
		}
		$base = substr( $base, 0, 40 );

		$key = $base;
		$n   = 1;
		while ( in_array( $key, $taken, true ) ) {
			++$n;
			$key = $base . '_' . $n;
		}
		return $key;
	}

	/**
	 * One submitted row into a complete definition, or null if it has no label.
	 *
	 * A row with no label is an empty row the owner never filled in — the add
	 * button leaves one behind every time — so it is dropped rather than saved
	 * as a nameless field.
	 *
	 * @param array<string,mixed> $raw
	 * @param array<int,string>   $taken
	 * @return array{key:string,label:string,type:string,choices:array<int,string>,help:string,required:bool,who:string}|null
	 */
	public static function sanitise_one( array $raw, array $taken ): ?array {
		$label = sanitize_text_field( (string) ( $raw['label'] ?? '' ) );
		if ( '' === $label ) {
			return null;
		}

		$type = (string) ( $raw['type'] ?? '' );
		if ( ! array_key_exists( $type, self::TYPES ) ) {
			$type = self::DEFAULT_TYPE;
		}

		$who = (string) ( $raw['who'] ?? '' );
		if ( ! array_key_exists( $who, self::WHO ) ) {
			$who = self::DEFAULT_WHO;
		}

		// An existing key is authoritative: this row is a field the club already
		// has, and its answers are stored under that key.
		$key = sanitize_key( (string) ( $raw['key'] ?? '' ) );
		if ( '' === $key || in_array( $key, $taken, true ) ) {
			$key = self::key_from_label( $label, $taken );
		}

		return array(
			'key'      => $key,
			'label'    => $label,
			'type'     => $type,
			'choices'  => self::has_choices( $type ) ? self::choices( $raw['choices'] ?? '' ) : array(),
			'help'     => sanitize_text_field( (string) ( $raw['help'] ?? '' ) ),
			'required' => ! empty( $raw['required'] ),
			'who'      => $who,
		);
	}

	/**
	 * Every submitted row into a definition list, capped and with unique keys.
	 *
	 * @param array<int,mixed> $rows
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitise_all( array $rows ): array {
		$out   = array();
		$taken = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$field = self::sanitise_one( $row, $taken );
			if ( null === $field ) {
				continue;
			}
			$taken[] = $field['key'];
			$out[]   = $field;
			if ( count( $out ) >= self::MAX_FIELDS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * The choices a field offers, blanks and duplicates dropped.
	 *
	 * Takes either shape, because both reach here: the builder posts one choice
	 * per line as text, and a definition already in storage carries the list it
	 * was last sanitised into. Reading the option re-sanitises it, so a
	 * text-only version would turn every stored list into the word "Array" the
	 * first time a club opened the screen.
	 *
	 * @param mixed $raw A newline-separated string, or a list.
	 * @return array<int,string>
	 */
	private static function choices( mixed $raw ): array {
		if ( is_array( $raw ) ) {
			$lines = $raw;
		} else {
			$split = is_scalar( $raw ) ? preg_split( '/\R/', (string) $raw ) : array();
			$lines = false === $split ? array() : $split;
		}

		$out = array();
		foreach ( $lines as $line ) {
			if ( ! is_scalar( $line ) ) {
				continue;
			}
			$line = sanitize_text_field( (string) $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
