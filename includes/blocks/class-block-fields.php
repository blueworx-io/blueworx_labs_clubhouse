<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What is editable on a block: its fields, and its repeatable items if it has
 * any.
 *
 * Derived from the Content_Catalogue rather than declared again, because the
 * catalogue is still the single source of truth for what a section holds and
 * two hand-kept copies would drift the first time a field was added. The
 * derivation follows Block_Seeder::content_for exactly — the same folding, the
 * same cookie prefix — so the keys this offers to edit are the keys a block
 * actually stores. A test holds the two together.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Fields {

	/**
	 * The editable shape of one of the plugin's own content addresses.
	 *
	 * @return array{fields:array<int,array<string,mixed>>,loop:?array{name:string,plural:string,fields:array<int,array<string,mixed>>}}
	 */
	public static function for_address( string $address ): array {
		$section = self::section( $address );
		if ( null === $section ) {
			return array( 'fields' => array(), 'loop' => null );
		}

		$fields = (array) ( $section['fields'] ?? array() );
		$loop   = isset( $section['loop'] ) ? (array) $section['loop'] : null;

		// A folded address has no block of its own: its content is stored on the
		// block it renders inside. Home's quick tiles are the hero block's items.
		foreach ( Blueworx_Clubhouse_Block_Addresses::folds() as $folded => $into ) {
			if ( $into !== $address ) {
				continue;
			}
			$fold = self::section( (string) $folded );
			if ( null === $fold ) {
				continue;
			}
			if ( isset( $fold['loop'] ) ) {
				$loop = (array) $fold['loop'];
			}
			$fields = array_merge( $fields, (array) ( $fold['fields'] ?? array() ) );
		}

		// The cookie notice renders inside the footer, so its wording is stored on
		// the footer block under a prefix rather than becoming a block of its own.
		if ( 'global/footer' === $address ) {
			$cookies = self::section( 'global/cookies' );
			foreach ( (array) ( $cookies['fields'] ?? array() ) as $field ) {
				$field['key']   = 'cookie_' . (string) $field['key'];
				$field['label'] = 'Cookie notice: ' . (string) $field['label'];
				$fields[]       = $field;
			}
		}

		return array(
			'fields' => array_values( $fields ),
			'loop'   => null === $loop ? null : array(
				'name'   => (string) ( $loop['name'] ?? 'Item' ),
				'plural' => (string) ( $loop['plural'] ?? 'Items' ),
				'fields' => array_values( (array) ( $loop['fields'] ?? array() ) ),
			),
		);
	}

	/**
	 * The editable shape of a block type, for a block an owner made rather than
	 * one the plugin shipped.
	 *
	 * A type's fields are the fields of the first address that renders as that
	 * type — every address of a type renders through the same code, so they hold
	 * the same keys, and picking the first keeps this a derivation rather than a
	 * second opinion.
	 *
	 * Addresses the catalogue never offered are skipped rather than accepted
	 * empty: Home's tier grid mirrors the Membership page's and has no editor of
	 * its own, so taking it as the type's shape would leave every tier block
	 * with nothing to edit.
	 *
	 * @return array{fields:array<int,array<string,mixed>>,loop:?array{name:string,plural:string,fields:array<int,array<string,mixed>>}}
	 */
	public static function for_type( string $type ): array {
		$folds = Blueworx_Clubhouse_Block_Addresses::folds();
		foreach ( Blueworx_Clubhouse_Block_Addresses::map() as $address => $entry ) {
			if ( isset( $folds[ (string) $address ] ) || $entry['type'] !== $type ) {
				continue;
			}
			if ( null === self::section( (string) $address ) ) {
				continue;
			}
			return self::for_address( (string) $address );
		}
		return array( 'fields' => array(), 'loop' => null );
	}

	/**
	 * The editable shape of a stored block: its own address when it came from
	 * one, its type otherwise.
	 *
	 * @param array<string,mixed> $block
	 * @return array{fields:array<int,array<string,mixed>>,loop:?array{name:string,plural:string,fields:array<int,array<string,mixed>>}}
	 */
	public static function for_block( array $block ): array {
		$key = (string) ( $block['defaults_key'] ?? '' );
		if ( '' !== $key && null !== self::section( $key ) ) {
			return self::for_address( $key );
		}
		return self::for_type( (string) ( $block['type'] ?? '' ) );
	}

	/**
	 * The catalogue's entry for an address, or null when the catalogue has none —
	 * which is an ordinary answer for an address the editor never offered.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function section( string $address ): ?array {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages( Blueworx_Clubhouse_Products_Source::get() ) as $page ) {
			foreach ( $page['sections'] as $section ) {
				if ( (string) $section['store_page'] . '/' . (string) $section['key'] === $address ) {
					return $section;
				}
			}
		}
		return null;
	}
}
