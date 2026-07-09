<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores singular content for template pages, keyed page -> section -> field.
 * One storage entry per page keeps reads to a single autoloaded option.
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

	public function set( string $page, string $section, string $field, mixed $value ): void {
		$all = $this->storage->get( $this->page_key( $page ), array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		if ( ! isset( $all[ $section ] ) || ! is_array( $all[ $section ] ) ) {
			$all[ $section ] = array();
		}
		$all[ $section ][ $field ] = $value;
		$this->storage->set( $this->page_key( $page ), $all );
	}
}
