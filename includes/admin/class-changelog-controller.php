<?php
// includes/admin/class-changelog-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The What's New screen, under Clubhouse.
 *
 * Reads the changelog that ships inside the plugin and hands it to the screen.
 * Nothing about wording lives here, and nothing about WordPress lives in the
 * parser or the screen.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Changelog_Controller {

	// The same capability as the user guide. Knowing what changed is reference
	// material, not a setting — a Content Editor who notices a screen has moved
	// should be able to find out why without asking an administrator.
	public const CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP;
	public const PAGE_SLUG  = 'clubhouse-whats-new';

	public static function register(): void {
		// Priority 13: after the user guide at 12, so the two reference screens
		// sit together at the bottom of the menu.
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 13 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_menu(): void {
		add_submenu_page(
			Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
			"What's new",
			"What's new",
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		Blueworx_Clubhouse_Admin_Assets::enqueue();
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		echo Blueworx_Clubhouse_Changelog_Screen::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Changelog_Screen.
			array(
				'running'   => BLUEWORX_LABS_CLUBHOUSE_VERSION,
				'releases'  => Blueworx_Clubhouse_Changelog::parse( self::markdown() ),
				'role_tags' => Blueworx_Clubhouse_Access_Controller::role_chips_for( self::PAGE_SLUG ),
			)
		);
	}

	/**
	 * The changelog as it shipped, or '' when it cannot be read.
	 *
	 * It is on the zip's allowlist, so '' means a host that cannot read a file
	 * inside the plugin directory. The screen says so rather than drawing an
	 * empty page that reads as "nothing has ever changed".
	 */
	private static function markdown(): string {
		$path = BLUEWORX_LABS_CLUBHOUSE_DIR . 'CHANGELOG.md';
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file inside this plugin, not a remote request.
		return is_string( $raw ) ? $raw : '';
	}
}
