<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Key/value storage contract. Production uses WordPress options; tests use an
 * in-memory fake. Keeps engine logic free of the WordPress runtime.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Storage {

	public function get( string $key, mixed $default = null ): mixed;

	public function set( string $key, mixed $value ): void;

	public function delete( string $key ): void;
}
