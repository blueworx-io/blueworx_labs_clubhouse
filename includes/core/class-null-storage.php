<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A storage backend that stores nothing. get() always returns the caller's
 * default; set() and delete() are no-ops. This is what Menu::current() falls
 * back to when no provider has been installed — the preview and any code
 * running before Frontend wires up real options — so it always reads as
 * "nothing has ever been saved" and yields Menu::DEFAULTS.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Null_Storage implements Blueworx_Clubhouse_Storage {

	public function get( string $key, mixed $default = null ): mixed {
		return $default;
	}

	public function set( string $key, mixed $value ): void {
		// Intentionally does nothing.
	}

	public function delete( string $key ): void {
		// Intentionally does nothing.
	}
}
