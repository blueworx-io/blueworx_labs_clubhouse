<?php
// includes/profile/class-profile-values.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a member's answer may be, and who is allowed to see or write it.
 *
 * Pure. Two jobs that belong together because both are answered per field and
 * both must agree: an answer is only cleaned against the definition that
 * allowed it, and a submission is only read for the fields its sender may
 * write.
 *
 * The filtering is deliberately positive — writable_by_member() KEEPS what is
 * allowed rather than removing what is not. A new 'who' setting added later
 * therefore defaults to unwritable and invisible, which is the failure worth
 * having.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Values {

	/** Where a submission carries its answers. */
	public const POST_KEY = 'clubhouse_profile';

	/**
	 * One answer, normalised to its field's type.
	 *
	 * Anything that does not fit the type becomes empty rather than an error.
	 * A member is stopped at the browser by the control's own type, and a
	 * hand-crafted post is not worth a screen of its own.
	 *
	 * @param array<string,mixed> $field
	 * @return string|array<int,string>
	 */
	public static function clean( array $field, mixed $raw ): string|array {
		$type    = (string) ( $field['type'] ?? 'text' );
		$choices = array_map( 'strval', (array) ( $field['choices'] ?? array() ) );

		if ( 'multiselect' === $type ) {
			if ( ! is_array( $raw ) ) {
				return array();
			}
			$out = array();
			foreach ( $raw as $one ) {
				if ( ! is_scalar( $one ) ) {
					continue;
				}
				$one = sanitize_text_field( (string) $one );
				if ( in_array( $one, $choices, true ) ) {
					$out[] = $one;
				}
			}
			return array_values( array_unique( $out ) );
		}

		if ( is_array( $raw ) || ! is_scalar( $raw ) ) {
			return '';
		}
		$value = (string) $raw;

		switch ( $type ) {
			case 'textarea':
				return trim( sanitize_textarea_field( $value ) );
			case 'number':
				$value = trim( $value );
				return is_numeric( $value ) ? $value : '';
			case 'date':
				$value = trim( $value );
				if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
					return '';
				}
				return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
			case 'select':
				$value = sanitize_text_field( $value );
				return in_array( $value, $choices, true ) ? $value : '';
			case 'checkbox':
				return '' !== trim( $value ) ? '1' : '';
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * @param array<string,mixed>      $field
	 * @param string|array<int,string> $value
	 */
	public static function is_blank( array $field, string|array $value ): bool {
		unset( $field );
		return is_array( $value ) ? array() === $value : '' === trim( $value );
	}

	/**
	 * The fields a member is shown. Private fields are not merely hidden by
	 * CSS — they never enter the HTML at all.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>
	 */
	public static function visible_to_member( array $fields ): array {
		return array_values(
			array_filter(
				$fields,
				static function ( array $field ): bool {
					return in_array( (string) ( $field['who'] ?? '' ), array( 'member', 'club' ), true );
				}
			)
		);
	}

	/**
	 * The fields a member may write. Everything else in their submission is
	 * discarded, so a tampered form cannot set a squad number.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>
	 */
	public static function writable_by_member( array $fields ): array {
		return array_values(
			array_filter(
				$fields,
				static function ( array $field ): bool {
					return 'member' === (string) ( $field['who'] ?? '' );
				}
			)
		);
	}

	/**
	 * A member's submission: the answers they are allowed to write, and the
	 * labels of any required field they left blank.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @param array<string,mixed>            $post
	 * @return array{values:array<string,string|array<int,string>>,missing:array<int,string>}
	 */
	public static function from_member_post( array $fields, array $post ): array {
		$sent    = is_array( $post[ self::POST_KEY ] ?? null ) ? (array) $post[ self::POST_KEY ] : array();
		$values  = array();
		$missing = array();

		foreach ( self::writable_by_member( $fields ) as $field ) {
			$key   = (string) $field['key'];
			$value = self::clean( $field, $sent[ $key ] ?? '' );
			if ( ! empty( $field['required'] ) && self::is_blank( $field, $value ) ) {
				$missing[] = (string) $field['label'];
			}
			$values[ $key ] = $value;
		}

		return array( 'values' => $values, 'missing' => $missing );
	}

	/**
	 * A staff submission from wp-admin: every field, and required is not
	 * enforced. Staff routinely change one thing about a member, and blocking
	 * that on an unrelated required field would make the screen unusable.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @param array<string,mixed>            $post
	 * @return array<string,string|array<int,string>>
	 */
	public static function from_admin_post( array $fields, array $post ): array {
		$sent   = is_array( $post[ self::POST_KEY ] ?? null ) ? (array) $post[ self::POST_KEY ] : array();
		$values = array();
		foreach ( $fields as $field ) {
			$key            = (string) $field['key'];
			$values[ $key ] = self::clean( $field, $sent[ $key ] ?? '' );
		}
		return $values;
	}
}
