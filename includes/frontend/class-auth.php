<?php
// includes/frontend/class-auth.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes the clubhouse login page a real WordPress login.
 *
 * Everything runs through the core auth stack — wp_signon, retrieve_password,
 * check_password_reset_key, reset_password, wp_logout — rather than a bespoke
 * password check, so anything else the site has installed still applies: two
 * factor plugins, login limiters, and any 'authenticate' filter that wants to
 * refuse a sign-in. Their WP_Error messages are shown as-is, because a plugin
 * that blocks a login is usually the only thing that can explain why.
 *
 * The account-recovery emails are rewritten to point back here, so a member who
 * clicks "forgot password" never lands on wp-login.php in a stock theme halfway
 * through a clubhouse journey.
 *
 * The pure decisions — which view to render, and whether a redirect target is
 * safe to honour — live in Auth_View and are unit-tested without WordPress.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Auth {

	public const NONCE = 'clubhouse_auth';

	/** The POST field naming which of the four journeys a submission belongs to. */
	public const FIELD = 'clubhouse_auth_action';

	public static function register(): void {
		// template_redirect: late enough that the query is resolved (so we know
		// this is the login page) and early enough that nothing has been sent, so
		// a successful sign-in can still redirect.
		add_action( 'template_redirect', array( self::class, 'handle' ) );
		add_filter( 'lostpassword_url', array( self::class, 'lostpassword_url' ), 10, 0 );
		add_filter( 'retrieve_password_message', array( self::class, 'reset_email' ), 10, 4 );
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

	/** Core asks for the "lost password" link in a few places; point them here. */
	public static function lostpassword_url(): string {
		return self::url( Blueworx_Clubhouse_Auth_View::FORGOT );
	}

	/**
	 * Swap the wp-login.php reset link in the standard email for the clubhouse
	 * one. The message is otherwise left exactly as WordPress (and any plugin
	 * filtering it before us) composed it.
	 *
	 * @param string $message The email body.
	 * @param string $key     The reset key.
	 * @param string $login   The user login the key belongs to.
	 */
	public static function reset_email( $message, $key, $login, $user_data = null ): string {
		$message = (string) $message;
		$ours    = self::url(
			Blueworx_Clubhouse_Auth_View::RESET,
			array(
				'key'   => (string) $key,
				'login' => rawurlencode( (string) $login ),
			)
		);
		// Core builds the link as network_site_url("wp-login.php?action=rp&key=…&login=…", 'login').
		// Replace the whole URL wherever it appears rather than rebuilding the
		// message, so translated and plugin-modified wording survives untouched.
		$pattern = '#https?://\S*wp-login\.php\?action=rp\S*#';
		$swapped = preg_replace( $pattern, $ours, $message );
		return is_string( $swapped ) ? $swapped : $message;
	}

	/** True when this request is the clubhouse login page. */
	private static function is_login_page(): bool {
		$qv = function_exists( 'get_query_var' ) ? get_query_var( Blueworx_Clubhouse_Frontend::QUERY_VAR ) : '';
		return 'login' === $qv;
	}

	private static function settings(): Blueworx_Clubhouse_Auth_Settings {
		return new Blueworx_Clubhouse_Auth_Settings( new Blueworx_Clubhouse_Options_Storage() );
	}

	/** The raw ?redirect_to from the request, unslashed and untrusted. */
	private static function requested_redirect(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only; the value is validated by Auth_View::safe_target before use.
		$raw = $_REQUEST['redirect_to'] ?? '';
		return is_string( $raw ) ? wp_unslash( $raw ) : '';
	}

	/**
	 * Handle a submission and publish the view state the renderer draws.
	 *
	 * Runs on every front-end request and bails immediately off the login page,
	 * except for logout, which has to work from the header link on any page.
	 */
	public static function handle(): void {
		if ( isset( $_GET['clubhouse_logout'] ) ) {
			self::logout();
			return;
		}
		// Published on every front-end request, not just the login page: the header
		// on every page has to know whether to offer "Log in" or "Log out".
		self::publish( array() );
		if ( ! self::is_login_page() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- view selection only; every state change below verifies its own nonce.
		$view  = Blueworx_Clubhouse_Auth_View::view( $_GET[ Blueworx_Clubhouse_Auth_View::ACTION ] ?? '' );
		$error = '';
		$notice = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line before anything is read.
		$submitted = isset( $_POST[ self::FIELD ] ) ? sanitize_key( wp_unslash( (string) $_POST[ self::FIELD ] ) ) : '';
		if ( '' !== $submitted ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), self::NONCE ) ) {
				$error = 'That form expired before it was sent. Please try again.';
				$view  = 'reset' === $submitted ? Blueworx_Clubhouse_Auth_View::RESET : $view;
			} else {
				$result = self::dispatch( $submitted );
				$view   = $result['view'];
				$error  = $result['error'];
				$notice = $result['notice'];
			}
		}

		self::publish(
			array(
				'view'        => $view,
				'error'       => $error,
				'notice'      => $notice,
				'form_action' => self::url(),
				'hidden'      => wp_nonce_field( self::NONCE, '_wpnonce', true, false ) . self::reset_fields( $view ),
				'redirect_to' => self::requested_redirect(),
			)
		);
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

	/**
	 * The key and login a "set a new password" submission has to carry.
	 *
	 * They ride in the form rather than in a cookie: this page is served by the
	 * plugin's own route, not wp-login.php, so core's reset cookie is not in play
	 * and reproducing it would be more moving parts than the journey needs.
	 */
	private static function reset_fields( string $view ): string {
		if ( Blueworx_Clubhouse_Auth_View::RESET !== $view ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the key itself is the credential and is validated by check_password_reset_key.
		$key = isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['key'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		$login = isset( $_REQUEST['login'] ) ? sanitize_user( wp_unslash( (string) $_REQUEST['login'] ) ) : '';
		return '<input type="hidden" name="key" value="' . esc_attr( $key ) . '">'
			. '<input type="hidden" name="login" value="' . esc_attr( $login ) . '">';
	}

	/**
	 * @return array{view:string,error:string,notice:string}
	 */
	private static function dispatch( string $action ): array {
		switch ( $action ) {
			case 'signin':
				return self::sign_in();
			case 'forgot':
				return self::forgot();
			case 'reset':
				return self::reset_password();
		}
		return array(
			'view'   => Blueworx_Clubhouse_Auth_View::SIGN_IN,
			'error'  => '',
			'notice' => '',
		);
	}

	/** @return array{view:string,error:string,notice:string} */
	private static function sign_in(): array {
		$creds = array(
			// Not sanitize_user: an email address is a valid WordPress login and
			// sanitize_user would quietly mangle it into a different account name.
			'user_login'    => isset( $_POST['user_login'] ) ? trim( wp_unslash( (string) $_POST['user_login'] ) ) : '',
			'user_password' => isset( $_POST['user_password'] ) ? (string) wp_unslash( (string) $_POST['user_password'] ) : '',
			'remember'      => isset( $_POST['remember'] ),
		);

		$user = wp_signon( $creds, is_ssl() );
		if ( is_wp_error( $user ) ) {
			return array(
				'view'   => Blueworx_Clubhouse_Auth_View::SIGN_IN,
				// WordPress's own wording, including whatever a security or 2FA
				// plugin returned — it knows why it refused and we do not.
				'error'  => wp_strip_all_tags( (string) $user->get_error_message() ),
				'notice' => '',
			);
		}

		wp_set_current_user( $user->ID );
		self::go(
			Blueworx_Clubhouse_Auth_View::post_login_target(
				self::settings()->get_post_login(),
				Blueworx_Clubhouse_Shop_Pages::url( 'dashboard' )
			)
		);
		return array(
			'view'   => Blueworx_Clubhouse_Auth_View::SIGN_IN,
			'error'  => '',
			'notice' => '',
		);
	}

	/** @return array{view:string,error:string,notice:string} */
	private static function forgot(): array {
		if ( isset( $_POST['user_login'] ) ) {
			$_POST['user_login'] = trim( wp_unslash( (string) $_POST['user_login'] ) );
		}
		$sent = retrieve_password();
		if ( is_wp_error( $sent ) ) {
			return array(
				'view'   => Blueworx_Clubhouse_Auth_View::FORGOT,
				'error'  => wp_strip_all_tags( (string) $sent->get_error_message() ),
				'notice' => '',
			);
		}
		return array(
			'view'   => Blueworx_Clubhouse_Auth_View::SENT,
			'error'  => '',
			'notice' => 'Check your inbox — if that account exists we have sent a link to set a new password.',
		);
	}

	/** @return array{view:string,error:string,notice:string} */
	private static function reset_password(): array {
		$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['key'] ) ) : '';
		$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( (string) $_POST['login'] ) ) : '';
		$pass  = isset( $_POST['pass1'] ) ? (string) wp_unslash( (string) $_POST['pass1'] ) : '';
		$pass2 = isset( $_POST['pass2'] ) ? (string) wp_unslash( (string) $_POST['pass2'] ) : '';

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			return array(
				'view'   => Blueworx_Clubhouse_Auth_View::FORGOT,
				'error'  => 'That reset link has expired or has already been used. Request a new one below.',
				'notice' => '',
			);
		}
		if ( '' === $pass ) {
			return array(
				'view'   => Blueworx_Clubhouse_Auth_View::RESET,
				'error'  => 'Enter a new password.',
				'notice' => '',
			);
		}
		if ( $pass !== $pass2 ) {
			return array(
				'view'   => Blueworx_Clubhouse_Auth_View::RESET,
				'error'  => 'Those passwords do not match.',
				'notice' => '',
			);
		}

		reset_password( $user, $pass );
		return array(
			'view'   => Blueworx_Clubhouse_Auth_View::RESET_OK,
			'error'  => '',
			'notice' => 'Password updated. You can sign in with it now.',
		);
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
