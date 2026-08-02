<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The login page's state, and the pure decisions behind it.
 *
 * The login slug serves four screens — sign in, request a reset link, set a new
 * password, and the confirmations in between — because they are one journey and
 * splitting them across slugs would put half of it outside the clubhouse look.
 * Which one renders is a query-param decision, made here so it can be tested
 * without a WordPress runtime.
 *
 * State is set by the WordPress request handler and read by the renderer, the
 * same seam Links and Menu use: the preview and the unit tests leave it unset
 * and get the plain sign-in form, which is what a visitor sees anyway.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Auth_View {

	public const SIGN_IN  = 'signin';
	public const FORGOT   = 'forgot';
	public const RESET    = 'reset';
	public const SIGNED   = 'signedout';
	public const ACTION   = 'clubhouse_auth';
	public const SENT     = 'sent';
	public const RESET_OK = 'resetok';

	/** Every view the login slug can render. Anything else falls back to sign-in. */
	private const VIEWS = array( self::SIGN_IN, self::FORGOT, self::RESET, self::SIGNED, self::SENT, self::RESET_OK );

	/** @var array<string,mixed>|null */
	private static ?array $state = null;

	/**
	 * Resolve a raw ?clubhouse_auth= value to a view name. Unknown, absent or
	 * non-string values are the sign-in form — a mistyped URL should show the
	 * login page, not an error.
	 */
	public static function view( mixed $raw ): string {
		if ( ! is_string( $raw ) ) {
			return self::SIGN_IN;
		}
		$raw = strtolower( trim( $raw ) );
		return in_array( $raw, self::VIEWS, true ) ? $raw : self::SIGN_IN;
	}

	/**
	 * Whether a redirect target may be honoured, and what to use instead when it
	 * may not.
	 *
	 * Order is deliberate: an explicit ?redirect_to (the page a member was trying
	 * to reach) beats the owner's configured default, which beats the home page.
	 * Every candidate is checked against the site's own host, so a link like
	 * /login/?redirect_to=https://evil.example cannot turn a club login into an
	 * open redirect — it silently falls through to the safe default instead.
	 *
	 * Pure: hosts and the home URL are passed in rather than read from WordPress.
	 *
	 * @param string $requested  Raw ?redirect_to from the request; may be empty.
	 * @param string $configured The owner's setting; may be empty, a path or a URL.
	 * @param string $home       Absolute home URL, used as the last resort.
	 */
	public static function safe_target( string $requested, string $configured, string $home ): string {
		$host = self::host_of( $home );
		foreach ( array( $requested, $configured ) as $candidate ) {
			$candidate = trim( $candidate );
			if ( '' === $candidate ) {
				continue;
			}
			if ( self::is_same_site( $candidate, $host ) ) {
				return self::absolutise( $candidate, $home );
			}
		}
		return $home;
	}

	/**
	 * A relative path is always same-site; an absolute URL only when its host
	 * matches. Protocol-relative ('//evil.example/x') is rejected outright: it
	 * reads as a path but navigates off-site, which is exactly the case a naive
	 * "starts with /" check lets through.
	 */
	private static function is_same_site( string $candidate, string $host ): bool {
		if ( str_starts_with( $candidate, '//' ) ) {
			return false;
		}
		if ( str_starts_with( $candidate, '/' ) ) {
			return true;
		}
		$candidate_host = self::host_of( $candidate );
		if ( '' === $candidate_host ) {
			// No scheme and no leading slash — a bare page key or relative path.
			return ! str_contains( $candidate, ':' );
		}
		return '' !== $host && strtolower( $candidate_host ) === strtolower( $host );
	}

	private static function absolutise( string $candidate, string $home ): string {
		if ( '' !== self::host_of( $candidate ) ) {
			return $candidate;
		}
		return rtrim( $home, '/' ) . '/' . ltrim( $candidate, '/' );
	}

	private static function host_of( string $url ): string {
		$host = parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? $host : '';
	}

	/** @param array<string,mixed> $state */
	public static function set_state( array $state ): void {
		self::$state = $state;
	}

	public static function reset(): void {
		self::$state = null;
	}

	/**
	 * The state the renderer draws. Defaults to a plain sign-in form so the
	 * preview and the unit tests render the same page a first-time visitor sees.
	 *
	 * @return array{view:string,error:string,notice:string,form_action:string,hidden:string,redirect_to:string,logged_in:string,logout_url:string}
	 */
	public static function state(): array {
		$state = self::$state ?? array();
		return array(
			'view'        => self::view( $state['view'] ?? self::SIGN_IN ),
			'error'       => (string) ( $state['error'] ?? '' ),
			'notice'      => (string) ( $state['notice'] ?? '' ),
			'form_action' => (string) ( $state['form_action'] ?? '' ),
			'hidden'      => (string) ( $state['hidden'] ?? '' ),
			'redirect_to' => (string) ( $state['redirect_to'] ?? '' ),
			'logged_in'   => (string) ( $state['logged_in'] ?? '' ),
			'logout_url'  => (string) ( $state['logout_url'] ?? '' ),
		);
	}
}
