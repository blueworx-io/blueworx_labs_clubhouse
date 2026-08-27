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
	 * @return array<int,array{slug:string,label:string,method:string,requires?:string,requires_shop?:bool,private?:bool}>
	 */
	public static function pages(): array {
		return array(
			array( 'slug' => '',           'label' => 'Home',       'method' => 'home' ),
			array( 'slug' => 'about',      'label' => 'About',      'method' => 'about' ),
			array( 'slug' => 'membership', 'label' => 'Membership', 'method' => 'membership' ),
			array( 'slug' => 'contact',    'label' => 'Contact',    'method' => 'contact' ),
			// Signing in is for members, and a member is somebody the shop knows. With
			// no shop there is no membership to sign in to and nothing behind the door,
			// so the door is not there — the same reasoning as 'member-dashboard' below.
			array( 'slug' => 'login',      'label' => 'Log in',     'method' => 'login', 'requires_shop' => true ),
			// 'blog' is the internal key; the URL and the label say News, which is
			// what a club calls it. Renamed here would break stored content and
			// visibility addresses for no gain.
			array( 'slug' => 'news',       'label' => 'News',       'method' => 'blog' ),
			array( 'slug' => 'sports',     'label' => 'Sports',     'method' => 'sports' ),
			array( 'slug' => 'teams',      'label' => 'Teams',      'method' => 'teams' ),
			array( 'slug' => 'events',     'label' => 'Events',     'method' => 'events' ),
			array( 'slug' => 'calendar',   'label' => 'Calendar',   'method' => 'calendar' ),
			array(
				'slug'     => 'booking',
				'label'    => 'Bookings',
				'method'   => 'booking',
				'requires' => Blueworx_Clubhouse_Integrations::LATEPOINT_TAG,
			),
			// The member's own area. Private: nothing on it is for a signed-out
			// visitor, so it is kept out of the SEO report rather than scored as
			// a page a search engine should be finding.
			array(
				'slug'          => 'member-dashboard',
				'label'         => 'Member area',
				'method'        => 'member_dashboard',
				'private'       => true,
				'requires_shop' => true,
			),
			// Last, and linked from the footer rather than the nav: nobody comes to
			// a club site to read the terms, but a site whose forms collect names,
			// emails and phone numbers has to have somewhere to point at.
			array( 'slug' => 'privacy',    'label' => 'Privacy',    'method' => 'privacy' ),
			array( 'slug' => 'terms',      'label' => 'Terms',      'method' => 'terms' ),
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
	 * @return array<int,array{slug:string,label:string,method:string,requires?:string,requires_shop?:bool,private?:bool}>
	 */
	public static function available(): array {
		return array_values(
			array_filter(
				self::pages(),
				static function ( array $page ): bool {
					// The shop is detected by a constant and a class rather than by
					// a shortcode tag, so it cannot go through Integrations like
					// the booking plugin does.
					if ( ! empty( $page['requires_shop'] ) && ! Blueworx_Clubhouse_SureCart_Products::is_active() ) {
						return false;
					}
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

	/** True for a members-only page — one no visitor should be sent to and no search engine should be scored on. */
	public static function is_private( string $slug ): bool {
		foreach ( self::pages() as $page ) {
			if ( $page['slug'] === $slug ) {
				return (bool) ( $page['private'] ?? false );
			}
		}
		return false;
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
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null,
		string $filter = '',
		string $item = ''
	): string {
		// A sport or a team named on the Sports or Teams URL gets its own page.
		// Falls through to the listing when the name matches nothing, so a stale
		// or mistyped link lands somewhere useful instead of on an empty page.
		if ( '' !== $item && in_array( $slug, array( 'sports', 'teams' ), true ) ) {
			$detail = 'sports' === $slug
				? Blueworx_Clubhouse_Page_Renderer::sport_page( $item, $branding, $visibility, $collections, $logo_url, $content )
				: Blueworx_Clubhouse_Page_Renderer::team_page( $item, $branding, $visibility, $collections, $logo_url, $content );
			if ( '' !== $detail ) {
				return $detail;
			}
		}
		$method = 'home';
		foreach ( self::pages() as $page ) {
			if ( $page['slug'] === $slug ) {
				$method = $page['method'];
				break;
			}
		}
		// $filter is consumed only by the filtered pages (sports/teams/events/
		// calendar); the other page methods accept it as an ignored trailing arg.
		return call_user_func(
			array( Blueworx_Clubhouse_Page_Renderer::class, $method ),
			$branding,
			$visibility,
			$collections,
			$logo_url,
			$content,
			$filter
		);
	}
}
