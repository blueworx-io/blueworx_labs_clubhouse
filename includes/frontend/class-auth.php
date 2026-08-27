<?php
// includes/frontend/class-auth.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signing out, and where a member goes after signing in.
 *
 * Signing IN is the shop's. Its form sits on the club's own login page — see
 * Sections::auth() — and posts to its own route, which is wp_authenticate
 * underneath, so every plugin the site has that guards a login still applies.
 *
 * This class used to carry a whole second front door beside it: a sign-in form,
 * a forgot-password screen, a set-a-new-password screen, and a rewrite of the
 * reset email to point at them. All of it did the same job as the shop's, and
 * all of it was ours to keep working. Issue #261 took it out.
 *
 * What is left is the two things that were never the shop's to answer. Signing
 * out, because the link is in the club's header on every page and where it
 * lands is the club's setting. And where a member goes once the shop has signed
 * them in, which is also the club's setting — carried by the shop's own
 * sc_login_redirect_url filter, since its form is a web component and there is
 * no field of ours to put in it.
 *
 * The pure decisions — whether a redirect target is safe to honour — live in
 * Auth_View and are unit-tested without WordPress.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Auth {

	public static function register(): void {
		// template_redirect: late enough that the query is resolved (so we know
		// this is the login page) and early enough that nothing has been sent, so
		// a successful sign-in can still redirect.
		add_action( 'template_redirect', array( self::class, 'handle' ) );
		// Where a member lands after the shop's form has signed them in. The shop
		// validates the redirect; the setting and what it means are ours, and
		// this is the only hook that carries it — its form is a web component
		// posting to a REST route, so there is no form field of ours to add.
		add_filter( 'sc_login_redirect_url', array( self::class, 'login_redirect' ), 10, 1 );
	}

	/** The clubhouse login page URL, optionally carrying a view and extra args. */
	public static function url( string $view = '', array $args = array() ): string {
		$url = Blueworx_Clubhouse_Links::url( 'login' );
		if ( '' !== $view ) {
			$args = array_merge( array( Blueworx_Clubhouse_Auth_View::ACTION => $view ), $args );
		}
		if ( array() === $args ) {
			return $url;
		}
		return add_query_arg( $args, $url );
	}

	/**
	 * Where the shop should send a member it has just signed in.
	 *
	 * The shop offers whatever `redirect_to` was on the address, already
	 * validated, or null. Where it has nothing, the club's Setup setting
	 * decides — and where that is empty too, the member area, which is the
	 * useful default and the same one this plugin has always used.
	 *
	 * @param string|null $theirs The shop's own answer.
	 */
	public static function login_redirect( $theirs ): string {
		$requested = is_string( $theirs ) ? $theirs : '';
		return Blueworx_Clubhouse_Auth_View::safe_target(
			$requested,
			Blueworx_Clubhouse_Auth_View::post_login_target(
				self::settings()->get_post_login(),
				self::default_dashboard_url()
			),
			home_url( '/' )
		);
	}

	private static function settings(): Blueworx_Clubhouse_Auth_Settings {
		return new Blueworx_Clubhouse_Auth_Settings( new Blueworx_Clubhouse_Options_Storage() );
	}

	/**
	 * Where a member with no configured post-login setting lands.
	 *
	 * The member area now exists as our own route, so it is the useful default —
	 * the front page was a dead end on a club with no shop, and the shop's own
	 * account page was a pointless second hop even on a club with one. Only this
	 * default changes: post_login_target() still lets a configured setting win
	 * outright, and a club that has switched the member area off keeps today's
	 * behaviour (the shop's dashboard, or the front page with no shop either).
	 *
	 * Same condition Page_Renderer::header_account()'s call site uses to decide
	 * whether the header itself can offer the member area.
	 */
	private static function default_dashboard_url(): string {
		$serving = Blueworx_Clubhouse_Page_Map::is_available( Blueworx_Clubhouse_Frontend::MEMBER_AREA )
			&& ( new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Options_Storage() ) )->is_page_visible( Blueworx_Clubhouse_Frontend::MEMBER_AREA );
		return $serving
			? Blueworx_Clubhouse_Frontend::link_url( Blueworx_Clubhouse_Frontend::MEMBER_AREA )
			: Blueworx_Clubhouse_Shop_Pages::url( 'dashboard' );
	}

	/**
	 * Sign out, and tell the header who is signed in.
	 *
	 * Signing IN is the shop's — its form on the club's login page, posting to
	 * its own route. Nothing is handled here for it. Signing out is still ours,
	 * because the link is in the header on every page and the destination is the
	 * club's setting.
	 */
	public static function handle(): void {
		if ( isset( $_GET['clubhouse_logout'] ) ) {
			self::logout();
			return;
		}
		// Published on every front-end request: the header on every page has to
		// know whether to offer "Log in" or the member area.
		self::publish( array() );
	}

	/**
	 * Hand the renderer the state it draws, with the session facts filled in.
	 *
	 * @param array<string,mixed> $state
	 */
	private static function publish( array $state ): void {
		$state['logged_in']  = is_user_logged_in() ? (string) wp_get_current_user()->display_name : '';
		$state['logout_url'] = self::logout_url();
		Blueworx_Clubhouse_Auth_View::set_state( $state );
	}

	/** The raw ?redirect_to from the request, unslashed and untrusted. */
	private static function requested_redirect(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only; validated by Auth_View::safe_target before use.
		$raw = $_REQUEST['redirect_to'] ?? '';
		return is_string( $raw ) ? wp_unslash( $raw ) : '';
	}

	/** Sign out and return to the configured destination. */
	private static function logout(): void {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'log-out' ) ) {
			// A logout link that can be triggered from anywhere is a CSRF hole, so
			// an unsigned one does nothing rather than silently ending the session.
			return;
		}
		wp_logout();
		// Blank means the front page, which is what the Members screen has
		// always told owners it means. It used to mean the login page's
		// signed-out view instead — a screen that says "you have signed out"
		// and then leaves someone on a login form they no longer need.
		self::go( self::settings()->get_post_logout() );
	}

	/** The signed logout URL for the header link. */
	public static function logout_url(): string {
		return wp_nonce_url( add_query_arg( 'clubhouse_logout', '1', home_url( '/' ) ), 'log-out' );
	}

	/**
	 * Send the browser somewhere safe and stop. Off-site targets are dropped by
	 * safe_target, and wp_safe_redirect is a second, independent guard — belt and
	 * braces on the one behaviour here that could be turned against the club.
	 */
	private static function go( string $configured ): void {
		$target = Blueworx_Clubhouse_Auth_View::safe_target(
			self::requested_redirect(),
			$configured,
			home_url( '/' )
		);
		wp_safe_redirect( $target );
		exit;
	}
}
