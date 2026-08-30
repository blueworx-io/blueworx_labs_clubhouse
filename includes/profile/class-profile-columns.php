<?php
// includes/profile/class-profile-columns.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A club's own member fields, as columns on the WordPress Users screen.
 *
 * Reading an answer back used to mean opening members one at a time. A club
 * ordering kit wants every under-14's shirt size on one screen (issue #278).
 *
 * Which fields get a column is the owner's choice, field by field, on the
 * Clubhouse Members tab — thirty columns would make the screen unreadable, and
 * the field a club wants to scan is rarely the field it wants to record.
 *
 * The decisions are pure and take what they need to know; only register() and
 * the four methods it hooks ask WordPress anything.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Columns {

	/** Namespaced, so a field keyed "email" cannot take over WordPress's own column. */
	private const PREFIX = 'clubhouse_';

	/** Past this a cell starts pushing the other columns off the screen. */
	private const MAX_CHARS = 60;

	/** What an unanswered field says. Not a blank cell, which reads as a broken screen. */
	private const UNANSWERED = '—';

	/**
	 * The types worth ordering by.
	 *
	 * 'multiselect' is absent on purpose: its answers are stored as a list, and
	 * ordering members by a serialised array puts them in an order nobody asked
	 * for and nobody can explain. 'checkbox' is absent for a smaller reason —
	 * two values sort into two heaps, which the eye does faster than a click.
	 *
	 * @var array<int,string>
	 */
	private const SORTABLE_TYPES = array( 'text', 'textarea', 'number', 'date', 'select' );

	/** The types that sort as numbers rather than as text, so 10 comes after 9. */
	private const NUMERIC_TYPES = array( 'number' );

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'manage_users_columns', array( self::class, 'columns' ) );
		add_filter( 'manage_users_sortable_columns', array( self::class, 'sortable_columns' ) );
		add_filter( 'manage_users_custom_column', array( self::class, 'custom_column' ), 10, 3 );
		add_action( 'pre_get_users', array( self::class, 'order' ) );
	}

	// ---------------------------------------------------------------------
	// The decisions
	// ---------------------------------------------------------------------

	public static function column_id( string $field_key ): string {
		return self::PREFIX . $field_key;
	}

	/** The field a column id stands for, or '' for a column that is not one of ours. */
	public static function field_key( string $column_id ): string {
		return str_starts_with( $column_id, self::PREFIX ) ? substr( $column_id, strlen( self::PREFIX ) ) : '';
	}

	/**
	 * The fields that get a column, in the order the club arranged them.
	 *
	 * A private field is the club's own note about a member. The member never
	 * sees it, and neither does anybody who could not already open that
	 * member's screen and read it there — so it is not merely hidden here, it
	 * is never put in the table at all.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>
	 */
	public static function shown( array $fields, bool $can_see_private ): array {
		return array_values(
			array_filter(
				$fields,
				static function ( array $field ) use ( $can_see_private ): bool {
					if ( empty( $field['column'] ) ) {
						return false;
					}
					return $can_see_private || 'private' !== (string) ( $field['who'] ?? '' );
				}
			)
		);
	}

	/**
	 * WordPress's columns, with the club's added after them. After, because the
	 * screen is still WordPress's Users list — a club's own fields are what it
	 * came for, not what it navigates by.
	 *
	 * @param array<string,string>           $columns
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,string>
	 */
	public static function add( array $columns, array $fields, bool $can_see_private ): array {
		foreach ( self::shown( $fields, $can_see_private ) as $field ) {
			$columns[ self::column_id( (string) $field['key'] ) ] = (string) $field['label'];
		}
		return $columns;
	}

	/**
	 * One cell. Takes the answer as stored, and says something for every shape
	 * it can be in.
	 *
	 * @param array<string,mixed>       $field
	 * @param string|array<int,string>  $answer
	 */
	public static function cell( array $field, string|array $answer ): string {
		if ( 'checkbox' === (string) ( $field['type'] ?? '' ) ) {
			return '' === $answer || array() === $answer ? 'No' : 'Yes';
		}

		$text = is_array( $answer ) ? implode( ', ', array_map( 'strval', $answer ) ) : $answer;
		if ( '' === trim( $text ) ) {
			return self::UNANSWERED;
		}
		// One line: a long-text answer holds newlines, and a cell that grows to
		// four lines makes the row it is in impossible to scan across.
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( mb_strlen( $text ) > self::MAX_CHARS ) {
			$text = mb_substr( $text, 0, self::MAX_CHARS ) . '…';
		}
		return esc_html( $text );
	}

	/**
	 * The columns that offer sorting, added to whatever WordPress already
	 * offers. Each maps to its own column id, which is what comes back as
	 * `orderby` and what order_by() below reads.
	 *
	 * @param array<string,mixed>            $sortable
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,mixed>
	 */
	public static function sortable( array $sortable, array $fields, bool $can_see_private ): array {
		foreach ( self::shown( $fields, $can_see_private ) as $field ) {
			if ( ! in_array( (string) $field['type'], self::SORTABLE_TYPES, true ) ) {
				continue;
			}
			$id              = self::column_id( (string) $field['key'] );
			$sortable[ $id ] = $id;
		}
		return $sortable;
	}

	/**
	 * How to order the query for a requested column, or an empty array when the
	 * request is not ours to answer — an unknown column, one the club never
	 * made a column, or a private one this viewer may not see. That last case
	 * is why this re-checks rather than trusting sortable(): `orderby` arrives
	 * in the address bar, and anybody can type one.
	 *
	 * Ordered through a NAMED meta query clause, not through `meta_key` plus
	 * `orderby => meta_value`. That shorter form makes WordPress INNER JOIN on
	 * the key, and every member who has not answered drops out of the list —
	 * which reads as members having been deleted. Verified against real
	 * WordPress: the short form lost one of three members, this keeps all three
	 * and puts the unanswered one at the end.
	 *
	 * The NOT EXISTS half of the OR is what turns that join into a LEFT JOIN.
	 * It looks redundant beside EXISTS and is not.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,mixed>
	 */
	public static function order_by( string $orderby, array $fields, bool $can_see_private ): array {
		$key = self::field_key( $orderby );
		if ( '' === $key ) {
			return array();
		}
		foreach ( self::shown( $fields, $can_see_private ) as $field ) {
			if ( (string) $field['key'] !== $key || ! in_array( (string) $field['type'], self::SORTABLE_TYPES, true ) ) {
				continue;
			}
			$meta_key = Blueworx_Clubhouse_Profile_Store::META_PREFIX . $key;
			$numeric  = in_array( (string) $field['type'], self::NUMERIC_TYPES, true );
			return array(
				// Named 'answer' so orderby can point at this clause rather than
				// at a bare meta_value, which would be ambiguous once two
				// clauses exist.
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- ordering a hand-driven admin list.
					'relation' => 'OR',
					'answer'   => array( 'key' => $meta_key, 'compare' => 'EXISTS', 'type' => $numeric ? 'NUMERIC' : 'CHAR' ),
					'unanswered' => array( 'key' => $meta_key, 'compare' => 'NOT EXISTS' ),
				),
				'orderby'    => 'answer',
			);
		}
		return array();
	}

	// ---------------------------------------------------------------------
	// The WordPress side
	// ---------------------------------------------------------------------

	/** @return array<int,array<string,mixed>> */
	private static function fields(): array {
		return ( new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Options_Storage() ) )->fields();
	}

	/**
	 * Whether this viewer sees private fields. edit_users is the capability that
	 * lets somebody open another member's screen, where those fields are already
	 * shown in full — so a column tells them nothing the screen would not.
	 */
	private static function can_see_private(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( 'edit_users' );
	}

	/**
	 * @param array<string,string> $columns
	 * @return array<string,string>
	 */
	public static function columns( $columns ): array {
		return self::add( is_array( $columns ) ? $columns : array(), self::fields(), self::can_see_private() );
	}

	/**
	 * @param array<string,mixed> $sortable
	 * @return array<string,mixed>
	 */
	public static function sortable_columns( $sortable ): array {
		return self::sortable( is_array( $sortable ) ? $sortable : array(), self::fields(), self::can_see_private() );
	}

	/**
	 * @param mixed $output What an earlier filter made of this cell.
	 */
	public static function custom_column( $output, $column, $user_id ): string {
		$key = self::field_key( (string) $column );
		if ( '' === $key ) {
			return (string) $output;
		}
		foreach ( self::shown( self::fields(), self::can_see_private() ) as $field ) {
			if ( (string) $field['key'] === $key ) {
				$store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Options_Storage() );
				return self::cell( $field, $store->answers( (int) $user_id, array( $field ) )[ $key ] ?? '' );
			}
		}
		return (string) $output;
	}

	/** @param object $query The WP_User_Query being prepared. */
	public static function order( $query ): void {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}
		$order = self::order_by( (string) $query->get( 'orderby' ), self::fields(), self::can_see_private() );
		foreach ( $order as $name => $value ) {
			$query->set( $name, $value );
		}
	}
}
