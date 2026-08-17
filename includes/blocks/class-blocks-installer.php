<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Puts a club's blocks in place, once.
 *
 * A site that has never been composed still has its content in the old
 * page-and-section store and its sections switched on and off in Visibility.
 * This turns that into a block library and a set of page compositions, so the
 * front end can build the same site out of blocks. It runs on activation and,
 * because an in-place update never fires that hook, on the first admin request
 * after the version changes — the same belt-and-braces Owner_Role uses for
 * capabilities.
 *
 * It runs at most once. A club that has since edited its pages must not have
 * that work overwritten by an upgrade, so a site that is already composed is
 * left alone and only the stamp moves.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Blocks_Installer {

	private const STAMP = 'clubhouse_blocks_version';

	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'maybe_install' ) );
	}

	public static function maybe_install(): void {
		$current = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : 'dev';
		if ( (string) get_option( self::STAMP, '' ) === $current ) {
			return;
		}
		self::install();
		update_option( self::STAMP, $current );
	}

	/**
	 * Compose this site from what it already has. Safe to call again: a site
	 * with a composition keeps it.
	 */
	public static function install(): void {
		$storage     = new Blueworx_Clubhouse_Options_Storage();
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );
		$seeder      = new Blueworx_Clubhouse_Block_Seeder(
			new Blueworx_Clubhouse_Block_Library( $storage ),
			$composition
		);
		$content    = new Blueworx_Clubhouse_Content_Store( $storage );
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );

		if ( $composition->is_configured() ) {
			// Composed already, so its pages are the owner's business and are left
			// alone. A block that goes on no page is not — see Block_Seeder::adopt().
			$seeder->adopt( $content, $visibility );
			return;
		}
		$seeder->migrate( $content, $visibility );
	}
}
