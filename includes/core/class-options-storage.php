<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress options-backed storage. Every value is stored as an autoloaded
 * option so reads add no extra queries on a normal page load.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Options_Storage implements Blueworx_Clubhouse_Storage {

	private string $prefix;

	public function __construct( string $prefix = 'clubhouse_' ) {
		$this->prefix = $prefix;
	}

	public function get( string $key, mixed $default = null ): mixed {
		return get_option( $this->prefix . $key, $default );
	}

	public function set( string $key, mixed $value ): void {
		update_option( $this->prefix . $key, $value, true );
	}

	public function delete( string $key ): void {
		delete_option( $this->prefix . $key );
	}
}
