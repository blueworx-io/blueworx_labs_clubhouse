<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lucide line icons for the plugin's three top-level admin menus.
 *
 * WordPress' add_menu_page() only accepts a dashicon slug or an image URL, so
 * each menu is registered with a base64 SVG data URI (data_uri()) — a self-
 * coloured idle-grey glyph that shows even if styles fail to load. This class
 * then prints an admin <style> that redraws the same glyph as a CSS mask tinted
 * with currentColor, so the icon brightens on hover / active / current state
 * exactly like the native menu icons around it.
 *
 * Icons (lucide.dev): setup → trophy, content → panels-top-left, collections
 * → library. Vectors are the exact current Lucide geometry.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Admin_Menu_Icons {

	/** Menu slug => inner Lucide SVG paths (24×24 viewBox). */
	private const ICONS = array(
		'clubhouse-setup'        => '<path d="M10 14.66v1.626a2 2 0 0 1-.976 1.696A5 5 0 0 0 7 21.978"/><path d="M14 14.66v1.626a2 2 0 0 0 .976 1.696A5 5 0 0 1 17 21.978"/><path d="M18 9h1.5a1 1 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M6 9a6 6 0 0 0 12 0V3a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1z"/><path d="M6 9H4.5a1 1 0 0 1 0-5H6"/>',
		'clubhouse-site-content' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>',
		'clubhouse-content'      => '<path d="m16 6 4 14"/><path d="M12 6v14"/><path d="M8 8v12"/><path d="M4 4v16"/>',
	);

	public static function register(): void {
		add_action( 'admin_head', array( self::class, 'print_style' ) );
	}

	/** Full SVG markup for a menu slug, stroked in the given colour. */
	private static function svg( string $slug, string $stroke ): string {
		$inner = self::ICONS[ $slug ] ?? '';
		return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"'
			. ' fill="none" stroke="' . $stroke . '" stroke-width="2" stroke-linecap="round"'
			. ' stroke-linejoin="round">' . $inner . '</svg>';
	}

	/** base64 data URI for add_menu_page()'s $icon_url — idle-grey fallback glyph. */
	public static function data_uri( string $slug ): string {
		return 'data:image/svg+xml;base64,' . base64_encode( self::svg( $slug, '#a7aaad' ) );
	}

	/**
	 * Redraw each menu icon as a currentColor-tinted CSS mask so it recolours with
	 * the menu link's own state, matching the surrounding native icons. The mask
	 * shape is colour-agnostic (alpha only), so the black fill here is irrelevant.
	 */
	public static function print_style(): void {
		$css = '';
		foreach ( array_keys( self::ICONS ) as $slug ) {
			$uri  = 'data:image/svg+xml;base64,' . base64_encode( self::svg( $slug, '#000' ) );
			$sel  = '#adminmenu #toplevel_page_' . $slug . ' .wp-menu-image';
			$css .= $sel . '{background-image:none !important;}'
				. $sel . '::before{content:"";display:block;width:20px;height:34px;margin:0 auto;'
				. 'background-color:currentColor;'
				. '-webkit-mask:url(\'' . $uri . '\') no-repeat center;mask:url(\'' . $uri . '\') no-repeat center;'
				. '-webkit-mask-size:20px auto;mask-size:20px auto;}';
		}
		// Static, self-authored SVG/CSS — no user input, safe to emit verbatim.
		echo '<style id="clubhouse-admin-menu-icons">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
