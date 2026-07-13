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

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Floating admin-only switcher bar. Neutral chrome — styled by demo.css, no
	 * colour literals, no accent tokens. Each control carries the look slug for
	 * demo.js; the current look is flagged for both styling and a11y.
	 *
	 * @param array<int,array{slug:string,name:string}> $looks
	 */
	public static function switcher_html( array $looks, ?string $current_slug ): string {
		$out  = '<div id="clubhouse-demo" class="clubhouse-demo" role="region" aria-label="Demo mode look switcher">';
		$out .= '<span class="clubhouse-demo__title">Demo mode</span>';
		$out .= '<div class="clubhouse-demo__looks" role="group" aria-label="Choose a Base Look">';
		foreach ( $looks as $look ) {
			$is_current = $look['slug'] === $current_slug;
			$class      = 'clubhouse-demo__look' . ( $is_current ? ' is-current' : '' );
			$pressed    = $is_current ? 'true' : 'false';
			$out       .= '<button type="button" class="' . self::esc( $class ) . '"'
				. ' data-clubhouse-look="' . self::esc( $look['slug'] ) . '"'
				. ' aria-pressed="' . $pressed . '">'
				. self::esc( $look['name'] ) . '</button>';
		}
		$out .= '</div>';
		$out .= '<button type="button" class="clubhouse-demo__exit" data-clubhouse-demo-exit>Exit demo</button>';
		$out .= '</div>';
		return $out;
	}
}
