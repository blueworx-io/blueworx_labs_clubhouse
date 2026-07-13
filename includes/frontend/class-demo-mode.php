<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure decisions and markup for site-wide Demo mode: which registered Base Look
 * a viewer's cookie selects while demo is on, and the floating switcher bar's
 * HTML (with an optional admin-only deactivate control). No WordPress calls;
 * output escaped with htmlspecialchars.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Mode {

	public const COOKIE_LOOK = 'clubhouse_demo_look';

	/**
	 * The Base Look slug to render in place of the saved active look, or null to
	 * fall through to the saved look. Unknown/stale slugs fall through (never fatal).
	 * Capability is NOT a factor here — while demo mode is on, any viewer's cookie
	 * selects their own preview look.
	 *
	 * @param array<int,string> $available_slugs
	 */
	public static function resolve_look_slug( bool $demo_on, ?string $look_cookie, array $available_slugs ): ?string {
		if ( ! $demo_on || null === $look_cookie ) {
			return null;
		}
		return in_array( $look_cookie, $available_slugs, true ) ? $look_cookie : null;
	}

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Floating switcher bar, shown to every viewer while demo mode is on. Neutral
	 * chrome — styled by demo.css, no colour literals, no accent tokens. Each look
	 * control carries the slug for demo.js; the current look is flagged. The
	 * "Turn off demo mode" control is emitted only when $deactivate_url is given
	 * (admins only); non-admins get the look controls without it.
	 *
	 * @param array<int,array{slug:string,name:string}> $looks
	 */
	public static function switcher_html( array $looks, ?string $current_slug, ?string $deactivate_url ): string {
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
		if ( null !== $deactivate_url ) {
			$out .= '<a class="clubhouse-demo__exit" href="' . self::esc( $deactivate_url ) . '">Turn off demo mode</a>';
		}
		$out .= '</div>';
		return $out;
	}
}
