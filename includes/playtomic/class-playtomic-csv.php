<?php
// includes/playtomic/class-playtomic-csv.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The file Playtomic Manager's chain import takes, built from member rows and
 * the club's venue settings. Headers live here and nowhere else: when the
 * official sample differs, this is the one file to change.
 *
 * Pure. No WordPress.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Csv {

	/** The club's country, not the player's — Playtomic's rule. Australia for now. */
	public const COUNTRY_CODE = '61';

	public const FILENAME_PREFIX = 'playtomic-members-';

	private const STANDARD = array( 'name', 'email', 'phone_number', 'gender', 'birthdate' );
	private const PER_CLUB = array( 'cat_name', 'exp_date', 'benefit_price_id', 'next_due_date' );

	/** @param array<int,array<string,mixed>> $venues @return array<int,string> */
	public static function headers( array $venues ): array {
		$out = self::STANDARD;
		foreach ( $venues as $venue ) {
			foreach ( self::PER_CLUB as $column ) {
				$out[] = 'CAT_IMP-' . (string) $venue['club'] . '-' . $column;
			}
		}
		return $out;
	}

	/**
	 * @param array{id:int,name:string,email:string,phone:string,gender:string,birthdate:string} $member
	 * @param array<int,array<string,mixed>> $venues
	 * @return array<int,string>
	 */
	public static function row( array $member, array $venues, string $today ): array {
		$out = array(
			(string) $member['name'],
			(string) $member['email'],
			self::phone( (string) $member['phone'] ),
			self::gender( (string) $member['gender'] ),
			(string) $member['birthdate'],
		);
		foreach ( $venues as $venue ) {
			$expiry = self::expiry( $venue, $today );
			$out[]  = (string) $venue['category'];
			$out[]  = $expiry;
			$out[]  = (string) $venue['price_id'];
			$out[]  = self::due( $venue, $today, $expiry );
		}
		return $out;
	}

	/** @param array<int,array<string,mixed>> $members @param array<int,array<string,mixed>> $venues */
	public static function build( array $members, array $venues, string $today ): string {
		$lines = array( self::line( self::headers( $venues ) ) );
		foreach ( $members as $member ) {
			$lines[] = self::line( self::row( $member, $venues, $today ) );
		}
		return implode( "\r\n", $lines ) . "\r\n";
	}

	/** Digits only, the country code once, a plus sign in front. Empty stays empty. */
	public static function phone( string $raw ): string {
		$digits = (string) preg_replace( '/\D+/', '', $raw );
		$digits = ltrim( $digits, '0' );
		if ( '' === $digits ) {
			return '';
		}
		if ( str_starts_with( $digits, self::COUNTRY_CODE ) && strlen( $digits ) > 9 ) {
			$digits = substr( $digits, strlen( self::COUNTRY_CODE ) );
		}
		return '+' . self::COUNTRY_CODE . $digits;
	}

	/** The two words Playtomic's own player records use; anything else is left blank. */
	public static function gender( string $stored ): string {
		return match ( $stored ) {
			'Male'   => 'MALE',
			'Female' => 'FEMALE',
			default  => '',
		};
	}

	/** @param array<string,mixed> $venue */
	public static function expiry( array $venue, string $today ): string {
		if ( Blueworx_Clubhouse_Playtomic_Settings::EXPIRY_DATE === (string) $venue['expiry_mode'] ) {
			return (string) $venue['expiry_date'];
		}
		return self::add_months( $today, (int) $venue['expiry_months'] );
	}

	/** @param array<string,mixed> $venue */
	public static function due( array $venue, string $today, string $expiry ): string {
		return match ( (string) $venue['due_mode'] ) {
			Blueworx_Clubhouse_Playtomic_Settings::DUE_EXPIRY => $expiry,
			Blueworx_Clubhouse_Playtomic_Settings::DUE_MONTHS => self::add_months( $today, (int) $venue['due_months'] ),
			default => '',
		};
	}

	/** Month arithmetic that stops at the month's end rather than rolling over. */
	public static function add_months( string $date, int $months ): string {
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		if ( false === $start ) {
			return '';
		}
		$first = $start->modify( 'first day of this month' )->modify( '+' . $months . ' months' );
		$day   = min( (int) $start->format( 'j' ), (int) $first->format( 't' ) );
		return $first->setDate( (int) $first->format( 'Y' ), (int) $first->format( 'n' ), $day )->format( 'Y-m-d' );
	}

	/** @param array<int,string> $cells */
	private static function line( array $cells ): string {
		return implode( ',', array_map( array( self::class, 'cell' ), $cells ) );
	}

	private static function cell( string $value ): string {
		if ( 1 === preg_match( '/[",\r\n]/', $value ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}
}
