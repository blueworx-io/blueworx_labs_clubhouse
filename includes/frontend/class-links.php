<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal-link resolver seam. The shared renderer refers to pages by key
 * ('home', 'about', …) via Links::url(); the environment decides the actual
 * href. Default (preview / tests) emits the '?page=<key>' query form the
 * preview server routes on; WordPress installs a resolver (see Frontend) that
 * returns real permalinks, so the same rendered markup works in both.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Links {

	/** @var (callable(string):string)|null */
	private static $resolver = null;

	/** @param (callable(string):string)|null $resolver */
	public static function set_resolver( ?callable $resolver ): void {
		self::$resolver = $resolver;
	}

	public static function url( string $key ): string {
		if ( null !== self::$resolver ) {
			return ( self::$resolver )( $key );
		}
		return '?page=' . $key;
	}
}
