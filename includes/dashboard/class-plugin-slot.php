<?php
// includes/dashboard/class-plugin-slot.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders another plugin's output — a block or a shortcode — or nothing at all.
 *
 * The one place that knows do_blocks() and do_shortcode() exist, so no view has
 * to think about whether SureCart or LatePoint is installed. A slot with nobody
 * to fill it answers '', and the caller draws its honest empty state instead.
 *
 * A seam, like Integrations: the rules are pure and unit-tested, and WordPress
 * installs the real renderers at boot. The default is "nothing is installed",
 * which is the safe way round — a missing panel is obvious and recoverable, a
 * broken one is neither.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Plugin_Slot {

	/** @var (callable(string):?string)|null */
	private static $blocks = null;

	/** @var (callable(string):?string)|null */
	private static $shortcodes = null;

	/**
	 * @param (callable(string):?string)|null $blocks     Returns markup, or null when unregistered.
	 * @param (callable(string):?string)|null $shortcodes Same, for shortcode tags.
	 */
	public static function set_sources( ?callable $blocks, ?callable $shortcodes ): void {
		self::$blocks     = $blocks;
		self::$shortcodes = $shortcodes;
	}

	/** One block's rendered output, or '' when the plugin providing it is absent. */
	public static function block( string $name ): string {
		return self::render( self::$blocks, $name );
	}

	/** One shortcode's rendered output, or '' when it is not registered. */
	public static function shortcode( string $tag ): string {
		return self::render( self::$shortcodes, $tag );
	}

	/**
	 * @param (callable(string):?string)|null $source
	 */
	private static function render( ?callable $source, string $name ): string {
		if ( null === $source ) {
			return '';
		}
		try {
			$out = $source( $name );
		} catch ( \Throwable $e ) {
			// The page must survive a broken source, so the failure is swallowed
			// here rather than left to propagate. But swallowed silently, it is
			// not diagnosable: a club reporting "my orders page is blank" would
			// leave nobody anything to go on. Record one line and move on.
			self::log_failure( $name, $e );
			return '';
		}
		if ( ! is_string( $out ) || '' === trim( $out ) ) {
			return '';
		}
		return $out;
	}

	/**
	 * Write one line to the PHP error log naming the slot that failed and why.
	 * Guarded the same way the WordPress calls elsewhere in this file are, so a
	 * host without error_log() available cannot turn a recorded failure into a
	 * second one.
	 */
	private static function log_failure( string $name, \Throwable $e ): void {
		if ( ! function_exists( 'error_log' ) ) {
			return;
		}
		error_log( sprintf( 'Blueworx Clubhouse: plugin slot "%s" failed to render: %s', $name, $e->getMessage() ) );
	}

	/**
	 * Wire the real WordPress renderers.
	 *
	 * A block is asked for by name and rendered from a block comment, which is
	 * how WordPress renders a dynamic block outside the editor. It is only asked
	 * for when the registry says it exists, so an uninstalled plugin leaves the
	 * comment unrendered rather than printing it.
	 *
	 * A shortcode is checked the same way Integrations checks one — by tag,
	 * because the question is "will this render?", not "is a directory there?".
	 */
	public static function install_wordpress(): void {
		self::set_sources(
			static function ( string $name ): ?string {
				if ( ! class_exists( 'WP_Block_Type_Registry' ) || ! function_exists( 'do_blocks' ) ) {
					return null;
				}
				if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
					return null;
				}
				return do_blocks( '<!-- wp:' . $name . ' /-->' );
			},
			static function ( string $tag ): ?string {
				if ( ! function_exists( 'shortcode_exists' ) || ! function_exists( 'do_shortcode' ) ) {
					return null;
				}
				if ( ! shortcode_exists( $tag ) ) {
					return null;
				}
				return do_shortcode( '[' . $tag . ']' );
			}
		);
	}
}
