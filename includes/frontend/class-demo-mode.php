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

	public const COOKIE_ACCENT = 'clubhouse_demo_accent';

	/**
	 * The demo swatch set, in display order. Curated rather than a free colour
	 * input: an arbitrary accent can fail the engine's legibility gate (the admin
	 * Setup screen already rejects some), which would need a warning UI. These five
	 * are known-good on every shipped look.
	 *
	 * @var array<string,array{name:string,hex:string}>
	 */
	public const SWATCHES = array(
		'volt-lime'     => array( 'name' => 'Volt Lime', 'hex' => '#c6f24e' ),
		'signal-orange' => array( 'name' => 'Signal Orange', 'hex' => '#ff5b23' ),
		'court-teal'    => array( 'name' => 'Court Teal', 'hex' => '#12c3b0' ),
		'cobalt'        => array( 'name' => 'Cobalt', 'hex' => '#3b5bdb' ),
		'berry'         => array( 'name' => 'Berry', 'hex' => '#c2337a' ),
	);

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

	/**
	 * Derive each swatch's accent token set for a look, through the real engine.
	 * Keyed by slug so the client can look up a cookie'd choice directly.
	 *
	 * Derivation depends on the look's shell, so these must be recomputed per look —
	 * which is exactly why the cookie stores a slug and not a hex: on a look switch
	 * the server re-derives for the new shell instead of the client replaying a
	 * colour computed for the old one.
	 *
	 * @return array<string,array{name:string,hex:string,tokens:array<string,string>}>
	 */
	public static function palettes( Blueworx_Clubhouse_Base_Look $look ): array {
		$shell = $look->tokens();
		$out   = array();
		foreach ( self::SWATCHES as $slug => $swatch ) {
			$out[ $slug ] = array(
				'name'   => $swatch['name'],
				'hex'    => $swatch['hex'],
				'tokens' => Blueworx_Clubhouse_Color_Engine::derive(
					$swatch['hex'],
					$shell['--color-bg'],
					$shell['--color-ink']
				),
			);
		}
		return $out;
	}

	/**
	 * Whether a clubhouse response must skip the page cache. While demo mode is on,
	 * a visitor switches looks by setting a cookie and reloading, so the server has
	 * to re-render every time — a full-page cache would otherwise serve stale HTML
	 * and the swap would never appear. Gated to clubhouse pages, mirroring where the
	 * rest of demo mode's furniture renders; off, normal caching stands.
	 */
	public static function should_bypass_cache( bool $demo_on, bool $is_clubhouse_page ): bool {
		return $demo_on && $is_clubhouse_page;
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
		$out .= '<div class="clubhouse-demo__accents" role="group" aria-label="Try an accent colour">';
		foreach ( self::SWATCHES as $slug => $swatch ) {
			// No colour here by design: demo.js paints the swatch from the palettes
			// global, so this markup stays free of colour literals. aria-pressed
			// starts false for the same reason the colour does not appear: the
			// accent cookie never reaches PHP, so the client flags the chosen one.
			$out .= '<button type="button" class="clubhouse-demo__swatch"'
				. ' data-clubhouse-accent="' . self::esc( $slug ) . '"'
				. ' title="' . self::esc( $swatch['name'] ) . '"'
				. ' aria-pressed="false"'
				. ' aria-label="Accent: ' . self::esc( $swatch['name'] ) . '"></button>';
		}
		$out .= '</div>';
		if ( null !== $deactivate_url ) {
			$out .= '<a class="clubhouse-demo__exit" href="' . self::esc( $deactivate_url ) . '">Turn off demo mode</a>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Inline JS for wp_head: publishes the palettes and applies the viewer's cookie'd
	 * accent BEFORE first paint. This must run in the head — demo.js is a footer
	 * script, so applying there would flash the club's saved colour first.
	 *
	 * JSON_HEX_TAG makes the payload safe to inline inside a <script> element.
	 *
	 * Also publishes the applied slug as window.clubhouseDemoAccent so demo.js can
	 * flag the chosen swatch without parsing the cookie a second time. It is set
	 * only once a palette has actually resolved, so it never names an accent that
	 * was not applied.
	 *
	 * Fails closed on a hostile or mangled cookie: a stray '%' makes
	 * decodeURIComponent throw (an uncaught URIError would lose the pre-paint apply
	 * entirely), and '__proto__' resolves to a truthy Object.prototype with no
	 * .tokens — both return before anything is applied or published.
	 *
	 * @param array<string,array{name:string,hex:string,tokens:array<string,string>}> $palettes
	 */
	public static function head_script( array $palettes ): string {
		$json = json_encode( $palettes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		return '(function(){var P=' . ( false === $json ? '{}' : $json ) . ';'
			. 'window.clubhouseDemoPalettes=P;'
			. 'var m=document.cookie.match(/(?:^|;\s*)' . self::COOKIE_ACCENT . '=([^;]*)/);'
			. 'if(!m){return;}'
			. 'var s;try{s=decodeURIComponent(m[1]);}catch(e){return;}'
			. 'var p=P[s];'
			. 'if(!p||!p.tokens){return;}'
			. 'window.clubhouseDemoAccent=s;'
			. 'var r=document.documentElement.style;'
			. 'for(var k in p.tokens){r.setProperty(k,p.tokens[k]);}'
			. '})();';
	}
}
