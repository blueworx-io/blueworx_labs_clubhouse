<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure decisions and markup for admin Demo mode: whether the current request is
 * a live demo, which registered Base Look to render, and the floating switcher
 * bar's HTML. No WordPress calls, no persistence, no cookie reads — the
 * controller supplies the capability flag and cookie values. Output is escaped
 * with htmlspecialchars (matches Setup_Screen), so this stays skin-agnostic and
 * WP-free.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Mode {

	public const COOKIE_FLAG = 'clubhouse_demo';
	public const COOKIE_LOOK = 'clubhouse_demo_look';

	/** Demo is live only for a capable admin whose on/off cookie is set. */
	public static function is_active( bool $can_manage, ?string $flag_cookie ): bool {
		return $can_manage && '1' === $flag_cookie;
	}

	/**
	 * The Base Look slug to render in place of the saved active look, or null to
	 * fall through to the saved look. Unknown/stale slugs fall through (never fatal).
	 *
	 * @param array<int,string> $available_slugs
	 */
	public static function resolve_look_slug( bool $active, ?string $look_cookie, array $available_slugs ): ?string {
		if ( ! $active || null === $look_cookie ) {
			return null;
		}
		return in_array( $look_cookie, $available_slugs, true ) ? $look_cookie : null;
	}
}
