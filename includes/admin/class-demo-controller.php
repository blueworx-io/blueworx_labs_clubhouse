<?php
// includes/admin/class-demo-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress-coupled glue for site-wide Demo mode. Reads the stored on/off flag
 * (Demo_State) for every visitor and exposes the per-viewer demo look to
 * Frontend::context(); renders the admin-bar toggle (a nonce'd link, works
 * front-end and in wp-admin) and the floating switcher. Only capability-gated
 * admins may flip the flag; the switcher and demo assets are shown to all
 * viewers while on. Never writes the club's saved look.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Controller {

	public const CAPABILITY     = 'manage_options';
	public const TOGGLE_ACTION  = 'clubhouse_demo_toggle';
	public const NONCE          = 'clubhouse_demo_toggle';

	public static function register(): void {
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar_node' ), 100 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_head', array( self::class, 'render_head_script' ), 1 );
		add_action( 'wp_footer', array( self::class, 'render_switcher' ) );
		add_action( 'admin_post_' . self::TOGGLE_ACTION, array( self::class, 'handle_toggle' ) );
	}

	private static function can_manage(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( self::CAPABILITY );
	}

	private static function cookie( string $name ): ?string {
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return null;
		}
		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.NoNonce -- read-only per-viewer preview preference; changes nothing server-side.
	}

	public static function is_on(): bool {
		return ( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->is_on();
	}

	/**
	 * Whether this request should carry demo mode's front-end furniture: the bar,
	 * its assets, and the accent tokens. All three gate on this together.
	 *
	 * Demo mode decorates the clubhouse look, so it follows the look stylesheet's
	 * rule (Frontend::enqueue_assets) rather than being site-wide: off a clubhouse
	 * page no clubhouse CSS is loaded, so --color-accent* has nothing to act on and
	 * would only risk colliding with a host theme's own tokens of the same name.
	 *
	 * The admin-bar toggle deliberately does NOT gate on this — it is how demo mode
	 * is turned off, and must stay reachable from wherever the admin happens to be.
	 */
	private static function shows_furniture(): bool {
		return self::is_on() && Blueworx_Clubhouse_Frontend::is_clubhouse_page();
	}

	public static function look_slug( Blueworx_Clubhouse_Base_Look_Registry $registry ): ?string {
		return Blueworx_Clubhouse_Demo_Mode::resolve_look_slug(
			self::is_on(),
			self::cookie( Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ),
			array_keys( $registry->all() )
		);
	}

	/** Flip the stored flag; returns the new state. Pure glue — unit-testable with any Storage. */
	public static function apply_toggle( Blueworx_Clubhouse_Storage $storage ): bool {
		$state = new Blueworx_Clubhouse_Demo_State( $storage );
		$next  = ! $state->is_on();
		$state->set( $next );
		return $next;
	}

	public static function handle_toggle(): void {
		if ( ! self::can_manage() ) {
			return;
		}
		check_admin_referer( self::NONCE );
		self::apply_toggle( new Blueworx_Clubhouse_Options_Storage() );
		$back = wp_get_referer();
		wp_safe_redirect( false !== $back ? $back : home_url( '/' ) );
		exit;
	}

	private static function toggle_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TOGGLE_ACTION ), self::NONCE );
	}

	public static function enqueue(): void {
		if ( ! self::shows_furniture() ) {
			return;
		}
		wp_enqueue_style( 'clubhouse-demo', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/demo.css', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_enqueue_script( 'clubhouse-demo', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/demo.js', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	/** @param mixed $wp_admin_bar The WP_Admin_Bar instance. */
	public static function admin_bar_node( $wp_admin_bar ): void {
		if ( ! self::can_manage() || ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'add_node' ) ) {
			return;
		}
		$on = self::is_on();
		$wp_admin_bar->add_node( array(
			'id'    => 'clubhouse-demo-toggle',
			'title' => '⚡ ' . ( $on ? 'Demo mode: On' : 'Demo mode: Off' ),
			'href'  => self::toggle_url(),
			'meta'  => array( 'class' => $on ? 'clubhouse-demo-on' : 'clubhouse-demo-off' ),
		) );
	}

	/**
	 * Publish the palettes and re-apply the viewer's accent before first paint.
	 * Priority 1 on wp_head, not the footer bundle: demo.js is a footer script, so
	 * applying there would flash the club's saved colour before the demo one.
	 */
	public static function render_head_script(): void {
		if ( ! self::shows_furniture() ) {
			return;
		}
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		// Resolve the look the VIEWER is seeing, not the club's saved one. The demo
		// look never becomes the registry's active look — Frontend::context() keeps
		// them apart the same way, and render_switcher() below already resolves it
		// like this. Deriving from active() here would emit palettes for the wrong
		// shell whenever a viewer is demoing a look.
		$slug = self::look_slug( $registry );
		$look = null !== $slug ? $registry->get( $slug ) : $registry->active();
		if ( ! $look instanceof Blueworx_Clubhouse_Base_Look ) {
			return;
		}
		echo '<script id="clubhouse-demo-accent">'
			. Blueworx_Clubhouse_Demo_Mode::head_script( Blueworx_Clubhouse_Demo_Mode::palettes( $look ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- head_script JSON-encodes with JSON_HEX_TAG; asserted tag-safe by DemoModeSwitcherTest.
			. '</script>';
	}

	public static function render_switcher(): void {
		if ( ! self::shows_furniture() ) {
			return;
		}
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		$current  = self::look_slug( $registry ) ?? ( $registry->active() ? $registry->active()->slug() : null );
		$looks    = array();
		foreach ( $registry->all() as $look ) {
			$looks[] = array( 'slug' => $look->slug(), 'name' => $look->name() );
		}
		$deactivate = self::can_manage() ? self::toggle_url() : null;
		echo Blueworx_Clubhouse_Demo_Mode::switcher_html( $looks, $current, $deactivate ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Demo_Mode escapes all dynamic text.
	}
}
