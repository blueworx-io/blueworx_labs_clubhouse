<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ordered, keyed registry of items (pages, sections, collections, features).
 *
 * @package BlueworxLabsClubhouse
 */
class Blueworx_Clubhouse_Registry {

	/** @var array<string, mixed> Items keyed by slug, in registration order. */
	private array $items = array();

	public function register( string $key, mixed $item ): void {
		$this->items[ $key ] = $item;
	}

	public function has( string $key ): bool {
		return array_key_exists( $key, $this->items );
	}

	public function get( string $key ): mixed {
		return $this->items[ $key ] ?? null;
	}

	/** @return array<string, mixed> */
	public function all(): array {
		return $this->items;
	}

	/** @return list<string> */
	public function keys(): array {
		return array_keys( $this->items );
	}
}
