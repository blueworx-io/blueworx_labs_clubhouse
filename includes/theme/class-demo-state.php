<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide Demo-mode on/off flag, persisted via the storage abstraction. The
 * single source of truth for "is demo mode on" — read by the front end for
 * every visitor, written only by capability-gated admin controls.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_State {

	public const KEY = 'demo_active';

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	public function is_on(): bool {
		return (bool) $this->storage->get( self::KEY, false );
	}

	public function set( bool $on ): void {
		$this->storage->set( self::KEY, $on );
	}
}
