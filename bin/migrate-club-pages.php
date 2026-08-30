<?php
declare(strict_types=1);
// bin/migrate-club-pages.php
//
// One-off, run once per club: moves this club's content out of the old
// option-backed store and onto the pages (and the global option) that now
// hold it. Deleted along with Blueworx_Clubhouse_Content_Migration in phase 4
// — there is no permanent upgrade path here, only the one run this club needs.
//
// Run it with WP-CLI, on the harness or the club's real site:
//
//   wp eval-file bin/migrate-club-pages.php
//
// Safe to run more than once — a second run reports the same values again and
// writes nothing new. The old option is never touched or deleted.

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress is not loaded — run this with `wp eval-file bin/migrate-club-pages.php`.\n" );
	exit( 1 );
}

if ( ! class_exists( 'Blueworx_Clubhouse_Content_Migration' ) ) {
	fwrite( STDERR, "Blueworx Labs Clubhouse is not active on this site.\n" );
	exit( 1 );
}

// Guarded, not `declare(strict_types=1)`-adjacent boilerplate: this file is
// `require`d twice in the course of testing it locally (once to bootstrap
// WordPress, once as the eval-file payload itself), and a bare `function`
// declaration would fatal the second time. wp-cli's own eval-file only ever
// includes a script once, but the guard costs nothing either way.
//
// Defined before first use deliberately: unlike a top-level `function`
// declaration (hoisted at compile time regardless of where it sits in the
// file), one guarded by `if` is only defined when that line actually runs —
// so it has to appear above the loop that calls it, not just above where
// PHP would otherwise find it convenient.
if ( ! function_exists( 'migrate_club_pages_reason' ) ) {
	/**
	 * Why one address was skipped — inferred the same way
	 * Blueworx_Clubhouse_Content_Migration itself decides it, since the report
	 * the migration returns names the address but not the reason.
	 */
	function migrate_club_pages_reason( string $address ): string {
		$parts   = explode( '/', $address, 3 );
		$area    = $parts[0] ?? $address;
		$section = $parts[1] ?? '';
		$slug    = 'home' === $area ? '' : $area;

		if ( 'global' !== $area && ! Blueworx_Clubhouse_Page_Map::is_available( $slug ) ) {
			return migrate_club_pages_integration_reason( $slug );
		}
		if ( 'global' !== $area && '' !== $section && ! Blueworx_Clubhouse_Integrations::section_available( $area, $section ) ) {
			// Today's only per-section gate (Calendar's booking slot) needs
			// LatePoint too, the same as a whole unavailable Bookings page.
			return 'the Bookings plugin is not active — activate it and run this again';
		}

		if ( 'global' === $area ) {
			return 'not a real attachment';
		}

		$has_page = null !== $slug && Blueworx_Clubhouse_Club_Pages::post_id( $slug ) > 0;

		return $has_page ? 'not a real attachment' : "the \"{$area}\" page hasn't been created yet";
	}

	/** Which integration a page needs, in an operator's own words. */
	function migrate_club_pages_integration_reason( string $slug ): string {
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			if ( $page['slug'] !== $slug ) {
				continue;
			}
			if ( ! empty( $page['requires_shop'] ) ) {
				return 'the shop is not active — activate SureCart and run this again';
			}
			if ( '' !== (string) ( $page['requires'] ?? '' ) ) {
				return 'the Bookings plugin is not active — activate it and run this again';
			}
		}
		return 'this page is not available on this site — run this again once it is';
	}
}

$storage = new Blueworx_Clubhouse_Options_Storage();

if ( Blueworx_Clubhouse_Content_Migration::has_run( $storage ) ) {
	echo "This has already been run on this site. Running it again is safe — it only re-places the same values.\n\n";
}

$result = Blueworx_Clubhouse_Content_Migration::run( $storage );

echo $result['moved'] . " value(s) moved onto the pages.\n";

$by_page = array_filter( $result['pages'] );
if ( array() !== $by_page ) {
	echo "\nBy page:\n";
	foreach ( $by_page as $page => $count ) {
		echo "  {$page}: {$count}\n";
	}
}

if ( array() === $result['skipped'] ) {
	echo "\nNothing was skipped.\n";
	exit( 0 );
}

echo "\n" . count( $result['skipped'] ) . " address(es) skipped:\n";
foreach ( $result['skipped'] as $address ) {
	echo '  ' . $address . ' — ' . migrate_club_pages_reason( $address ) . "\n";
}
echo "\nAn integration reason means the plugin that page needs isn't active — activate it and run this again; nothing was lost.\n"
	. "A page reason means Setup hasn't created that page yet — run Setup once, then run this again.\n"
	. "An image reason means the old value wasn't a real attachment (often a demo or preview URL) — re-upload it by hand in the new Club Pages editor.\n";
