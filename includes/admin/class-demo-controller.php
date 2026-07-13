<?php
// includes/admin/class-demo-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress-coupled glue for admin Demo mode. Reads the per-admin cookie +
 * capability, exposes the effective demo look slug to Frontend::context(), and
 * renders the admin-bar toggle + floating switcher on the front end. All
 * decisions and markup live in the pure Blueworx_Clubhouse_Demo_Mode; this class
 * only touches WordPress. Never persists — the club's saved look is untouched.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Controller {

	public const CAPABILITY = 'manage_options';

	public static function register(): void {
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar_node' ), 100 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_footer', array( self::class, 'render_switcher' ) );
	}

	private static function can_manage(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( self::CAPABILITY );
	}

	private static function cookie( string $name ): ?string {
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return null;
		}
		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.NoNonce -- read-only per-admin UI preference, cap-gated below.
	}

	public static function is_active(): bool {
		return Blueworx_Clubhouse_Demo_Mode::is_active( self::can_manage(), self::cookie( Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ) );
	}

	public static function look_slug( Blueworx_Clubhouse_Base_Look_Registry $registry ): ?string {
		return Blueworx_Clubhouse_Demo_Mode::resolve_look_slug(
			self::is_active(),
			self::cookie( Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ),
			array_keys( $registry->all() )
		);
	}

	public static function enqueue(): void {
		if ( ! self::can_manage() ) {
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
		$on    = self::is_active();
		$label = $on ? 'Demo mode: On' : 'Demo mode: Off';
		$wp_admin_bar->add_node( array(
			'id'    => 'clubhouse-demo-toggle',
			'title' => '⚡ ' . $label,
			'href'  => '#',
			'meta'  => array( 'class' => $on ? 'clubhouse-demo-on' : 'clubhouse-demo-off' ),
		) );
	}

	public static function render_switcher(): void {
		if ( ! self::is_active() ) {
			return;
		}
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		$current  = self::look_slug( $registry ) ?? ( $registry->active() ? $registry->active()->slug() : null );
		$looks    = array();
		foreach ( $registry->all() as $look ) {
			$looks[] = array( 'slug' => $look->slug(), 'name' => $look->name() );
		}
		echo Blueworx_Clubhouse_Demo_Mode::switcher_html( $looks, $current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Demo_Mode escapes all dynamic text.
	}
}
