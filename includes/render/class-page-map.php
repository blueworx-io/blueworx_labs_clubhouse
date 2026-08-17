<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for which pages the plugin serves and how each renders.
 * Slug '' is the site root (Home). Both the WordPress frontend and the DB-free
 * preview dispatch through here, so they render byte-identical bodies.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Map {

	/**
	 * Every page this plugin knows how to serve, including ones whose integration
	 * may not be installed. Rewrite registration reads THIS list, not available():
	 * rewrite rules are cached until they are flushed, so a rule that appeared and
	 * disappeared with a third-party plugin would leave the URL 404ing until
	 * someone re-saved permalinks. Registering the rule unconditionally costs
	 * nothing — resolve_slug still refuses to serve the page.
	 *
	 * A 'requires' key names the shortcode tag whose presence the page depends on.
	 *
	 * @return array<int,array{slug:string,label:string,requires?:string}>
	 */
	public static function pages(): array {
		return array(
			array( 'slug' => '',           'label' => 'Home' ),
			array( 'slug' => 'about',      'label' => 'About' ),
			array( 'slug' => 'membership', 'label' => 'Membership' ),
			array( 'slug' => 'contact',    'label' => 'Contact' ),
			array( 'slug' => 'login',      'label' => 'Log in' ),
			array( 'slug' => 'news',       'label' => 'News' ),
			array( 'slug' => 'sports',     'label' => 'Sports' ),
			array( 'slug' => 'teams',      'label' => 'Teams' ),
			array( 'slug' => 'events',     'label' => 'Events' ),
			array( 'slug' => 'calendar',   'label' => 'Calendar' ),
			array(
				'slug'     => 'booking',
				'label'    => 'Bookings',
				'requires' => Blueworx_Clubhouse_Integrations::LATEPOINT_TAG,
			),
			// Last, and linked from the footer rather than the nav: nobody comes to
			// a club site to read the terms, but a site whose forms collect names,
			// emails and phone numbers has to have somewhere to point at.
			array( 'slug' => 'privacy',    'label' => 'Privacy' ),
			array( 'slug' => 'terms',      'label' => 'Terms' ),
		);
	}

	/**
	 * The pages this site can actually offer — pages() minus any whose integration
	 * is absent. Everything an owner or a visitor sees reads this: the nav, the
	 * visibility toggles, the content editor and the render gate. A page that
	 * cannot work is not offered anywhere, rather than offered and then empty.
	 *
	 * Stored content and visibility for a filtered-out page are untouched, so
	 * installing the integration later brings the page back exactly as it was.
	 *
	 * @return array<int,array{slug:string,label:string,requires?:string}>
	 */
	public static function available(): array {
		return array_values(
			array_filter(
				self::pages(),
				static function ( array $page ): bool {
					$requires = (string) ( $page['requires'] ?? '' );
					return '' === $requires || Blueworx_Clubhouse_Integrations::provides( $requires );
				}
			)
		);
	}

	/** True when this site can serve the page — known slug, and its integration present. */
	public static function is_available( string $slug ): bool {
		foreach ( self::available() as $page ) {
			if ( $page['slug'] === $slug ) {
				return true;
			}
		}
		return false;
	}

	/** The human label for a slug — '' for one this map does not serve. */
	public static function label( string $slug ): string {
		foreach ( self::pages() as $page ) {
			if ( $page['slug'] === $slug ) {
				return $page['label'];
			}
		}
		return '';
	}

	/**
	 * The key a page is known by everywhere except its URL — where Home's slug
	 * is empty. Content addresses, visibility and block compositions all use
	 * the key, so this is the one place the two spellings meet.
	 */
	public static function page_key( string $slug ): string {
		return '' === $slug ? 'home' : $slug;
	}

	public static function has( string $slug ): bool {
		foreach ( self::pages() as $page ) {
			if ( $page['slug'] === $slug ) {
				return true;
			}
		}
		return false;
	}

	public static function render(
		string $slug,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		Blueworx_Clubhouse_Page_Composer $composer,
		string $logo_url = '',
		string $filter = '',
		string $item = ''
	): string {
		// A sport or a team named on the Sports or Teams URL gets its own page.
		// Falls through to the listing when the name matches nothing, so a stale
		// or mistyped link lands somewhere useful instead of on an empty page.
		if ( '' !== $item && in_array( $slug, array( 'sports', 'teams' ), true ) ) {
			$detail = 'sports' === $slug
				? Blueworx_Clubhouse_Page_Renderer::sport_page( $item, $branding, $visibility, $collections, $composer, $logo_url )
				: Blueworx_Clubhouse_Page_Renderer::team_page( $item, $branding, $visibility, $collections, $composer, $logo_url );
			if ( '' !== $detail ) {
				return $detail;
			}
		}
		return $composer->page( self::page_key( $slug ), $branding, $visibility, $collections, $logo_url, $filter );
	}
}
