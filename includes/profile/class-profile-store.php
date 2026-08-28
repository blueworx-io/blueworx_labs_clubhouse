<?php
// includes/profile/class-profile-store.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the club's field definitions and each member's answers are kept.
 *
 * Definitions go in the Clubhouse options row, like every other Clubhouse
 * setting. Answers go in WordPress user meta, one key per field — so a club's
 * data sits beside a member's name and email, survives this plugin being
 * removed, and is readable by any export tool the club already has.
 *
 * Deleting a field does NOT delete its answers. An owner who removes a field by
 * accident gets everything back by adding it again; clearing answers for good
 * is forget(), which is only ever reached through an explicit confirmation.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Store {

	public const OPTION      = 'profile_fields';
	public const META_PREFIX = 'clubhouse_profile_';

	public function __construct( private Blueworx_Clubhouse_Storage $storage ) {}

	/** @return array<int,array<string,mixed>> */
	public function fields(): array {
		$raw = $this->storage->get( self::OPTION, array() );
		return is_array( $raw ) ? Blueworx_Clubhouse_Profile_Fields::sanitise_all( $raw ) : array();
	}

	/**
	 * Sanitising on the way IN as well as out: the option is the club's record,
	 * and a half-valid definition sitting in it would be read by anything that
	 * ever queries the option directly.
	 *
	 * @param array<int,mixed> $rows
	 */
	public function save_fields( array $rows ): void {
		$this->storage->set( self::OPTION, Blueworx_Clubhouse_Profile_Fields::sanitise_all( $rows ) );
	}

	public function meta_key( string $field_key ): string {
		return self::META_PREFIX . $field_key;
	}

	/**
	 * One member's answers, keyed by field, with every field present.
	 *
	 * Present-but-empty rather than absent, so callers never have to tell "not
	 * answered" from "field is new" — both draw the same empty control.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,string|array<int,string>>
	 */
	public function answers( int $user_id, array $fields ): array {
		$out = array();
		foreach ( $fields as $field ) {
			$key   = (string) $field['key'];
			$value = get_user_meta( $user_id, $this->meta_key( $key ), true );
			if ( 'multiselect' === (string) $field['type'] ) {
				$out[ $key ] = is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : array();
				continue;
			}
			$out[ $key ] = is_scalar( $value ) ? (string) $value : '';
		}
		return $out;
	}

	/** @param array<string,string|array<int,string>> $values */
	public function save_answers( int $user_id, array $values ): void {
		foreach ( $values as $key => $value ) {
			update_user_meta( $user_id, $this->meta_key( (string) $key ), $value );
		}
	}

	/** Clear one field's answers for every member. Irreversible, by design. */
	public function forget( string $field_key ): void {
		delete_metadata( 'user', 0, $this->meta_key( $field_key ), '', true );
	}
}
