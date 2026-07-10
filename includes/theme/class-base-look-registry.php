<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Base Look packs and resolves the active one. The active slug is
 * persisted via storage; when unset or stale it falls back to the first
 * registered look so a fresh install always renders.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Base_Look_Registry {

	private const ACTIVE_KEY = 'active_base_look';

	private Blueworx_Clubhouse_Registry $looks;
	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
		$this->looks   = new Blueworx_Clubhouse_Registry();
	}

	public function register( Blueworx_Clubhouse_Base_Look $look ): void {
		$this->looks->register( $look->slug(), $look );
	}

	public function has( string $slug ): bool {
		return $this->looks->has( $slug );
	}

	public function get( string $slug ): ?Blueworx_Clubhouse_Base_Look {
		$look = $this->looks->get( $slug );
		return $look instanceof Blueworx_Clubhouse_Base_Look ? $look : null;
	}

	/** @return array<string, Blueworx_Clubhouse_Base_Look> */
	public function all(): array {
		return $this->looks->all();
	}

	public function active(): ?Blueworx_Clubhouse_Base_Look {
		$slug = $this->storage->get( self::ACTIVE_KEY, '' );
		if ( is_string( $slug ) && $this->has( $slug ) ) {
			return $this->get( $slug );
		}
		$keys = $this->looks->keys();
		return $keys === array() ? null : $this->get( $keys[0] );
	}

	public function set_active( string $slug ): void {
		$this->storage->set( self::ACTIVE_KEY, $slug );
	}
}
