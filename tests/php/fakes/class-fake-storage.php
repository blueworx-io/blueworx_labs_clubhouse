<?php
declare(strict_types=1);

/**
 * In-memory Storage double for unit tests.
 */
final class Blueworx_Clubhouse_Fake_Storage implements Blueworx_Clubhouse_Storage {

	/** @var array<string, mixed> */
	private array $data = array();

	public function get( string $key, mixed $default = null ): mixed {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	public function set( string $key, mixed $value ): void {
		$this->data[ $key ] = $value;
	}

	public function delete( string $key ): void {
		unset( $this->data[ $key ] );
	}
}
