<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The content a club wrote before its site was built from blocks, keyed
 * page -> section -> field. One storage entry per page.
 *
 * Read-only, and read by exactly one thing: the migration that turns those
 * words into blocks the first time a site is composed. Nothing writes here any
 * more, and nothing renders from it. The options themselves are left in place
 * rather than deleted, so a migration that went wrong can still be looked at.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Content_Store {

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	private function page_key( string $page ): string {
		return 'content_' . $page;
	}

	/** @return array<string, mixed> */
	public function get_section( string $page, string $section ): array {
		$all = $this->storage->get( $this->page_key( $page ), array() );
		if ( is_array( $all ) && isset( $all[ $section ] ) && is_array( $all[ $section ] ) ) {
			return $all[ $section ];
		}
		return array();
	}

	public function get( string $page, string $section, string $field, mixed $default = null ): mixed {
		$fields = $this->get_section( $page, $section );
		return array_key_exists( $field, $fields ) ? $fields[ $field ] : $default;
	}

	private const ITEMS_KEY = 'items';

	/** @return array<int,array<string,mixed>> */
	public function get_items( string $page, string $section ): array {
		$val = $this->get( $page, $section, self::ITEMS_KEY, array() );
		return is_array( $val ) ? array_values( $val ) : array();
	}

}
