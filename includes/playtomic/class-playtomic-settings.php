<?php
// includes/playtomic/class-playtomic-settings.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything the Playtomic screen saves, in one option, sanitised on the way
 * in and out. The secret is never returned by read(); ask secret() for it.
 *
 * Pure beyond Storage and the WordPress sanitisers.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Settings {

	public const OPTION = 'playtomic';

	public const WHO_ALL           = 'all';
	public const WHO_SUBSCRIPTIONS = 'subscriptions';
	public const EXPIRY_DATE       = 'date';
	public const EXPIRY_MONTHS     = 'months';
	public const DUE_NONE          = 'none';
	public const DUE_EXPIRY        = 'expiry';
	public const DUE_MONTHS        = 'months';

	public function __construct( private Blueworx_Clubhouse_Storage $storage, private Blueworx_Clubhouse_Playtomic_Secret $secret ) {}

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'client_id'  => '',
			'secret'     => '',
			'who'        => self::WHO_ALL,
			'price_ids'  => array(),
			'venues'     => array(),
			'last_test'  => '',
			'last_error' => '',
		);
	}

	/** @return array<string,mixed> */
	public static function venue_defaults(): array {
		return array(
			'id'            => '',
			'name'          => '',
			'club'          => '',
			'category'      => '',
			'expiry_mode'   => self::EXPIRY_MONTHS,
			'expiry_date'   => '',
			'expiry_months' => 12,
			'price_id'      => '',
			'due_mode'      => self::DUE_NONE,
			'due_months'    => 1,
		);
	}

	/** The stored settings, sanitised. The secret comes back as ciphertext. @return array<string,mixed> */
	public function read(): array {
		$raw = $this->storage->get( self::OPTION, array() );
		return self::sanitise( is_array( $raw ) ? $raw : array(), '' );
	}

	/**
	 * Sanitise a submission and store it. A blank secret keeps the one stored;
	 * a new one replaces it.
	 *
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public function save( array $raw ): array {
		$current = $this->read();
		$plain   = isset( $raw['secret'] ) && is_scalar( $raw['secret'] ) ? trim( (string) $raw['secret'] ) : '';
		$raw['secret'] = '' === $plain ? $current['secret'] : $this->secret->encrypt( $plain );
		// Only the form's own keys change; the last test and error are kept.
		$raw['last_test']  = $current['last_test'];
		$raw['last_error'] = $current['last_error'];
		$clean = self::sanitise( $raw, (string) $current['secret'] );
		$this->storage->set( self::OPTION, $clean );
		return $clean;
	}

	public function client_id(): string {
		return (string) $this->read()['client_id'];
	}

	public function secret(): string {
		return $this->secret->decrypt( (string) $this->read()['secret'] );
	}

	public function has_secret(): bool {
		return '' !== $this->secret();
	}

	/** @return array<int,array<string,mixed>> */
	public function venues(): array {
		return $this->read()['venues'];
	}

	/** Add any venue id the token named that the club has not listed yet. @param array<int,string> $ids */
	public function merge_venues( array $ids ): void {
		$all   = $this->read();
		$known = array_column( $all['venues'], 'id' );
		foreach ( $ids as $id ) {
			$id = trim( (string) $id );
			if ( '' !== $id && ! in_array( $id, $known, true ) ) {
				$all['venues'][] = array_merge( self::venue_defaults(), array( 'id' => $id ) );
				$known[]         = $id;
			}
		}
		$this->storage->set( self::OPTION, $all );
	}

	public function set_last_test( string $text ): void {
		$all              = $this->read();
		$all['last_test'] = sanitize_text_field( $text );
		$this->storage->set( self::OPTION, $all );
	}

	public function set_last_error( string $text ): void {
		$all               = $this->read();
		$all['last_error'] = sanitize_text_field( $text );
		$this->storage->set( self::OPTION, $all );
	}

	/**
	 * @param array<string,mixed> $raw
	 * @param string              $fallback_secret Ciphertext to keep if the raw secret is not a string.
	 * @return array<string,mixed>
	 */
	public static function sanitise( array $raw, string $fallback_secret ): array {
		$out = self::defaults();

		$out['client_id'] = isset( $raw['client_id'] ) && is_scalar( $raw['client_id'] ) ? sanitize_text_field( (string) $raw['client_id'] ) : '';
		$out['secret']    = isset( $raw['secret'] ) && is_string( $raw['secret'] ) ? $raw['secret'] : $fallback_secret;

		$who        = (string) ( $raw['who'] ?? '' );
		$out['who'] = in_array( $who, array( self::WHO_ALL, self::WHO_SUBSCRIPTIONS ), true ) ? $who : self::WHO_ALL;

		$ids = array();
		foreach ( (array) ( $raw['price_ids'] ?? array() ) as $id ) {
			if ( is_scalar( $id ) ) {
				$id = sanitize_text_field( (string) $id );
				if ( '' !== $id && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
		}
		$out['price_ids'] = $ids;

		$venues = array();
		$seen   = array();
		foreach ( (array) ( $raw['venues'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$venue = self::sanitise_venue( $row );
			if ( '' === $venue['id'] || in_array( $venue['id'], $seen, true ) ) {
				continue;
			}
			$seen[]   = $venue['id'];
			$venues[] = $venue;
		}
		$out['venues'] = $venues;

		$out['last_test']  = isset( $raw['last_test'] ) && is_scalar( $raw['last_test'] ) ? sanitize_text_field( (string) $raw['last_test'] ) : '';
		$out['last_error'] = isset( $raw['last_error'] ) && is_scalar( $raw['last_error'] ) ? sanitize_text_field( (string) $raw['last_error'] ) : '';
		return $out;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private static function sanitise_venue( array $row ): array {
		$v = self::venue_defaults();
		foreach ( array( 'id', 'name', 'club', 'category', 'price_id', 'expiry_date' ) as $key ) {
			$v[ $key ] = isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? sanitize_text_field( (string) $row[ $key ] ) : '';
		}
		if ( ! self::is_date( $v['expiry_date'] ) ) {
			$v['expiry_date'] = '';
		}
		$mode             = (string) ( $row['expiry_mode'] ?? '' );
		$v['expiry_mode'] = in_array( $mode, array( self::EXPIRY_DATE, self::EXPIRY_MONTHS ), true ) ? $mode : self::EXPIRY_MONTHS;
		$due              = (string) ( $row['due_mode'] ?? '' );
		$v['due_mode']    = in_array( $due, array( self::DUE_NONE, self::DUE_EXPIRY, self::DUE_MONTHS ), true ) ? $due : self::DUE_NONE;
		$v['expiry_months'] = self::months( $row['expiry_months'] ?? null, 12 );
		$v['due_months']    = self::months( $row['due_months'] ?? null, 1 );
		return $v;
	}

	private static function months( mixed $raw, int $default ): int {
		if ( ! is_scalar( $raw ) || ! is_numeric( (string) $raw ) ) {
			return $default;
		}
		$n = (int) $raw;
		return ( $n >= 1 && $n <= 120 ) ? $n : $default;
	}

	public static function is_date( string $value ): bool {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}
}
