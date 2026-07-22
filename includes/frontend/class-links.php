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

	/** The query param the filtered pages (sports/teams/events/calendar) read. */
	public const FILTER_PARAM = 'clubhouse_filter';

	/**
	 * A page URL carrying a filter slug — the href for a filter pill. An empty
	 * slug returns the bare page URL (the "All" pill). Appends with the correct
	 * separator so it works on both the preview's '?page=' form and a real
	 * permalink.
	 */
	public static function filtered_url( string $key, string $filter ): string {
		$url = self::url( $key );
		if ( '' === $filter ) {
			return $url;
		}
		$sep = ( false !== strpos( $url, '?' ) ) ? '&' : '?';
		return $url . $sep . self::FILTER_PARAM . '=' . rawurlencode( $filter );
	}
}
