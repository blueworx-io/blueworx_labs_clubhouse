<?php
// includes/playtomic/class-playtomic-members.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The members the Playtomic screen lists, and the mark that says one has
 * already gone across.
 *
 * Pure apart from user meta (mark, clear, from_users), which the stubs cover.
 * Who the members ARE comes in through a callable so the rules can be tested
 * without a users table; the controller passes get_users().
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Members {

	public const META_KEY   = 'clubhouse_playtomic_exported';
	public const FILTER_NEW = 'new';
	public const FILTER_ALL = 'all';

	/** @var callable():array<int,array<string,mixed>> */
	private $source;
	/** @var (callable(int):bool)|null */
	private $qualifies;

	public function __construct( callable $source, ?callable $qualifies = null ) {
		$this->source    = $source;
		$this->qualifies = $qualifies;
	}

	/**
	 * The rows to show: those who qualify, joined within the range, and — for
	 * the 'new' filter — not yet exported.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function rows( string $filter, string $from, string $to ): array {
		$out = array();
		foreach ( ( $this->source )() as $person ) {
			$row = self::shape( $person );
			if ( null !== $this->qualifies && ! ( $this->qualifies )( $row['id'] ) ) {
				continue;
			}
			if ( '' !== $from && $row['registered'] < $from ) {
				continue;
			}
			if ( '' !== $to && $row['registered'] > $to ) {
				continue;
			}
			if ( self::FILTER_ALL !== $filter && '' !== $row['exported'] ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Of the ticked ids, the rows that may actually be written: complete, and
	 * qualifying. Unknown ids are ignored rather than refused.
	 *
	 * @param array<int,int> $ids
	 * @return array<int,array<string,mixed>>
	 */
	public function exportable( array $ids, string $from = '', string $to = '' ): array {
		$wanted = array_map( 'intval', $ids );
		return array_values(
			array_filter(
				$this->rows( self::FILTER_ALL, $from, $to ),
				static fn( array $row ): bool => $row['complete'] && in_array( $row['id'], $wanted, true )
			)
		);
	}

	/** @param array<int,int> $ids */
	public static function mark( array $ids, string $today ): void {
		foreach ( $ids as $id ) {
			update_user_meta( (int) $id, self::META_KEY, $today );
		}
	}

	public static function clear( int $id ): void {
		delete_user_meta( $id, self::META_KEY );
	}

	/**
	 * WordPress users into source rows. Objects, not WP_User, so a test can
	 * hand in stdClass.
	 *
	 * @param array<int,object> $users
	 * @return array<int,array<string,mixed>>
	 */
	public static function from_users( array $users ): array {
		$prefix = Blueworx_Clubhouse_Profile_Store::META_PREFIX;
		$out    = array();
		foreach ( $users as $user ) {
			$id    = (int) ( $user->ID ?? 0 );
			$out[] = array(
				'id'         => $id,
				'first'      => (string) ( $user->first_name ?? '' ),
				'last'       => (string) ( $user->last_name ?? '' ),
				'display'    => (string) ( $user->display_name ?? '' ),
				'email'      => (string) ( $user->user_email ?? '' ),
				'registered' => substr( (string) ( $user->user_registered ?? '' ), 0, 10 ),
				'phone'      => (string) get_user_meta( $id, $prefix . 'phone', true ),
				'gender'     => (string) get_user_meta( $id, $prefix . 'gender', true ),
				'birthdate'  => (string) get_user_meta( $id, $prefix . 'birthdate', true ),
				'exported'   => (string) get_user_meta( $id, self::META_KEY, true ),
			);
		}
		return $out;
	}

	/** @param array<string,mixed> $p @return array<string,mixed> */
	private static function shape( array $p ): array {
		$name = trim( trim( (string) ( $p['first'] ?? '' ) ) . ' ' . trim( (string) ( $p['last'] ?? '' ) ) );
		if ( '' === $name ) {
			$name = trim( (string) ( $p['display'] ?? '' ) );
		}
		$email  = trim( (string) ( $p['email'] ?? '' ) );
		$reason = '';
		if ( '' === $name ) {
			$reason = 'No name on their account.';
		} elseif ( '' === $email ) {
			$reason = 'No email address on their account.';
		}
		return array(
			'id'         => (int) ( $p['id'] ?? 0 ),
			'name'       => $name,
			'email'      => $email,
			'phone'      => (string) ( $p['phone'] ?? '' ),
			'gender'     => (string) ( $p['gender'] ?? '' ),
			'birthdate'  => (string) ( $p['birthdate'] ?? '' ),
			'registered' => (string) ( $p['registered'] ?? '' ),
			'exported'   => (string) ( $p['exported'] ?? '' ),
			'complete'   => '' === $reason,
			'reason'     => $reason,
		);
	}
}
