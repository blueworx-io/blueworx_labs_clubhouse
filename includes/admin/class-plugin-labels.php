<?php
// includes/admin/class-plugin-labels.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plain-English display names for the bundled plugins. A club administrator
 * reads "Bookings", not "LatePoint" — the vendor product names say who wrote
 * the plugin, not what it does.
 *
 * Display names ONLY. This is not a white-label: nothing here touches plugin
 * internals, assets, in-plugin screens, vendor branding or licensing links, and
 * nothing renames a slug, text domain, option key or file path. The rename is
 * cosmetic by construction — it is a transform applied to strings on their way
 * to the screen, so updates, licensing and activation are untouched, and a
 * vendor update cannot wipe it because no vendor file was ever edited.
 *
 * Pure — no WordPress. The controller installs these transforms on the plugins
 * list, the admin menu and the page title; everything decidable without
 * WordPress is decided here so it can be asserted directly.
 *
 * Matching is deliberately belt-and-braces, on TWO independent signals:
 *
 *   - the plugin's directory slug, for the plugins list; and
 *   - the vendor name as it appears in a title, for menus and page titles.
 *
 * Menu slugs are the one thing NOT matched on: they differ per plugin, several
 * are undocumented, and they move between versions. A vendor that renames its
 * menu slug would silently lose its label; a vendor that renames its own
 * product is the only thing that defeats the name match, and that is a rename
 * we would want to notice anyway.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Plugin_Labels {

	/**
	 * Vendor product name => the name an admin reads. The single map the whole
	 * feature is driven from: extend or amend this and every surface follows.
	 *
	 * @var array<string,string>
	 */
	private const MAP = array(
		'SureCart'     => 'eCommerce',
		'SureForms'    => 'Form Builder',
		'LatePoint'    => 'Bookings',
		'SureRank'     => 'SEO Rank',
		'SureDonation' => 'Donations',
		'SureContact'  => 'CRM Management',
		'SureMail'     => 'Mail Reports',
	);

	/** @return array<string,string> The rename map, vendor name => display name. */
	public static function map(): array {
		return self::MAP;
	}

	/**
	 * Reduce a name or slug to the key both signals agree on: lowercase, letters
	 * and digits only, and singular.
	 *
	 * The plural fold is what lets one map entry cover the several spellings a
	 * single product ships under — SureMail is directory "suremails", SureDonation
	 * is "suredonations". Safe at this size: the map holds seven keys, and a
	 * collision would need a real plugin named the plural of one of them.
	 */
	private static function key( string $value ): string {
		$value = strtolower( preg_replace( '/[^a-z0-9]/i', '', $value ) ?? '' );
		return '' !== $value && str_ends_with( $value, 's' ) ? substr( $value, 0, -1 ) : $value;
	}

	/** The map re-keyed for lookup. @return array<string,string> */
	private static function lookup(): array {
		$out = array();
		foreach ( self::MAP as $vendor => $label ) {
			$out[ self::key( $vendor ) ] = $label;
		}
		return $out;
	}

	/**
	 * The display name for a plugin file as WordPress keys it —
	 * "surecart/surecart.php" — or '' when it is not one of ours.
	 *
	 * Read off the directory, not the filename: a plugin's entry file is not
	 * reliably named after the plugin, but the directory is the plugin.
	 */
	public static function label_for_file( string $plugin_file ): string {
		$dir = strtok( str_replace( '\\', '/', trim( $plugin_file, '/' ) ), '/' );
		if ( false === $dir || '' === $dir ) {
			return '';
		}
		return self::lookup()[ self::key( $dir ) ] ?? '';
	}

	/**
	 * Rewrite any vendor name appearing in a title — a menu label, a page title, a
	 * breadcrumb. "SureCart" becomes "eCommerce"; "LatePoint Settings" becomes
	 * "Bookings Settings", so a vendor's own submenu wording survives intact.
	 *
	 * Idempotent: once replaced, no vendor name remains to match, so running over
	 * the same string twice yields the same result. That is what makes it safe to
	 * hook late on a menu another plugin may have rebuilt.
	 *
	 * Word-bounded with an optional plural, so "SureMails" is caught while a
	 * longer unrelated name that merely starts the same way is not.
	 */
	public static function label_for_title( string $title ): string {
		foreach ( self::MAP as $vendor => $label ) {
			$title = (string) preg_replace(
				'/\b' . preg_quote( $vendor, '/' ) . 's?\b/i',
				$label,
				$title
			);
		}
		return $title;
	}

	/**
	 * Rewrite the titles in a WordPress $menu array, leaving every other field —
	 * capability, slug, classes, hook name, icon — exactly as registered.
	 *
	 * Index 0 is the menu title and index 3 the page title. An item is renamed
	 * only if it actually carries a vendor name, so a menu of unrelated plugins
	 * comes back identical.
	 *
	 * @param array<int|string,array<int,mixed>> $menu
	 * @return array<int|string,array<int,mixed>>
	 */
	public static function rename_menu( array $menu ): array {
		foreach ( $menu as $position => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			foreach ( array( 0, 3 ) as $field ) {
				if ( isset( $item[ $field ] ) && is_string( $item[ $field ] ) ) {
					$item[ $field ] = self::label_for_title( $item[ $field ] );
				}
			}
			$menu[ $position ] = $item;
		}
		return $menu;
	}

	/**
	 * The same rewrite over a $submenu array — parent slug => list of items. The
	 * parent slug is a key, never a title, and is not touched.
	 *
	 * @param array<string,array<int|string,array<int,mixed>>> $submenu
	 * @return array<string,array<int|string,array<int,mixed>>>
	 */
	public static function rename_submenu( array $submenu ): array {
		foreach ( $submenu as $parent => $items ) {
			if ( ! is_array( $items ) ) {
				continue;
			}
			$submenu[ $parent ] = self::rename_menu( $items );
		}
		return $submenu;
	}

	/**
	 * Rewrite the Name column of a WordPress get_plugins()-shaped list. Matched on
	 * the directory first — the authoritative signal — and on the registered Name
	 * second, which covers a vendor shipping under a directory we do not know.
	 *
	 * Only 'Name' changes. Version, author, update and licensing metadata are the
	 * plugin's own and are left alone, so the plugins list still updates, licenses
	 * and activates against the real product.
	 *
	 * @param array<string,array<string,mixed>> $plugins
	 * @return array<string,array<string,mixed>>
	 */
	public static function rename_plugins( array $plugins ): array {
		foreach ( $plugins as $file => $data ) {
			if ( ! is_array( $data ) || ! isset( $data['Name'] ) || ! is_string( $data['Name'] ) ) {
				continue;
			}
			$by_dir          = self::label_for_file( (string) $file );
			$data['Name']    = '' !== $by_dir ? $by_dir : self::label_for_title( $data['Name'] );
			$plugins[ $file ] = $data;
		}
		return $plugins;
	}
}
