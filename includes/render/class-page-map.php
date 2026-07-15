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

	/** @return array<int,array{slug:string,label:string,method:string}> */
	public static function pages(): array {
		return array(
			array( 'slug' => '',           'label' => 'Home',       'method' => 'home' ),
			array( 'slug' => 'about',      'label' => 'About',      'method' => 'about' ),
			array( 'slug' => 'membership', 'label' => 'Membership', 'method' => 'membership' ),
			array( 'slug' => 'contact',    'label' => 'Contact',    'method' => 'contact' ),
			array( 'slug' => 'login',      'label' => 'Log in',     'method' => 'login' ),
			array( 'slug' => 'sports',     'label' => 'Sports',     'method' => 'sports' ),
			array( 'slug' => 'teams',      'label' => 'Teams',      'method' => 'teams' ),
			array( 'slug' => 'events',     'label' => 'Events',     'method' => 'events' ),
			array( 'slug' => 'calendar',   'label' => 'Calendar',   'method' => 'calendar' ),
		);
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
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		$method = 'home';
		foreach ( self::pages() as $page ) {
			if ( $page['slug'] === $slug ) {
				$method = $page['method'];
				break;
			}
		}
		return call_user_func(
			array( Blueworx_Clubhouse_Page_Renderer::class, $method ),
			$branding,
			$visibility,
			$collections,
			$logo_url,
			$content
		);
	}
}
