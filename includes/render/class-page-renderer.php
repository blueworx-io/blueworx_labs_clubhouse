<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles a full HTML document for a Base Look + branding: <head> carries the
 * Google-fonts link, the look stylesheet, and the derived :root variables; <body>
 * is a string of rendered sections. home() composes the demo Home shell, honouring
 * per-section visibility. The same output is what WordPress template_include will
 * later echo — the preview is just an earlier caller.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Renderer {

	public static function google_fonts_url( Blueworx_Clubhouse_Base_Look $look ): string {
		$families = array();
		foreach ( $look->fonts() as $font ) {
			$families[] = 'family=' . rawurlencode( $font['family'] )
				. ':wght@' . implode( ';', $font['weights'] );
		}
		return 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
	}

	public static function document(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding,
		string $body,
		string $plugin_url = ''
	): string {
		$vars = Blueworx_Clubhouse_Theme_Css::compose( $look, $branding );
		$css  = Blueworx_Clubhouse_Theme_Css::to_css( $vars );
		$font = htmlspecialchars( self::google_fonts_url( $look ), ENT_QUOTES, 'UTF-8' );
		$sheet = htmlspecialchars( $plugin_url . $look->stylesheet(), ENT_QUOTES, 'UTF-8' );

		return '<!doctype html><html lang="en"><head>'
			. '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . htmlspecialchars( $branding->get_club_name(), ENT_QUOTES, 'UTF-8' ) . '</title>'
			. '<link rel="preconnect" href="https://fonts.googleapis.com">'
			. '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
			. '<link rel="stylesheet" href="' . $font . '">'
			. '<link rel="stylesheet" href="' . $sheet . '">'
			. '<style>' . $css . '</style>'
			. '</head><body>' . $body . '</body></html>';
	}

	public static function home(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = '';

		if ( $visibility->is_section_visible( 'home', 'header' ) ) {
			$out .= Blueworx_Clubhouse_Sections::header( array(
				'club_name'   => $club,
				'banner'      => 'Summer sign-ups are open — register your interest for 2026/27 →',
				'banner_href' => '?page=membership',
				'nav'         => array(
					array( 'label' => 'Home', 'href' => '?page=home' ),
					array( 'label' => 'About', 'href' => '?page=about' ),
					array( 'label' => 'Sports', 'href' => '?page=sports' ),
					array( 'label' => 'Teams', 'href' => '?page=teams' ),
					array( 'label' => 'Membership', 'href' => '?page=membership' ),
					array( 'label' => 'Events', 'href' => '?page=events' ),
					array( 'label' => 'Calendar', 'href' => '?page=calendar' ),
					array( 'label' => 'Contact', 'href' => '?page=contact' ),
				),
				'active'      => '?page=home',
				'login'       => 'Log in',
				'join'        => 'Join the Club',
				'join_href'   => '?page=membership',
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'Est. 1974 · Marlow, UK',
				'title_lead'         => 'Every sport. Every age. ',
				'title_highlight'    => 'One community.',
				'lede'               => "Nine sports, twenty-four teams, and a clubhouse that's always open. Come for the game — stay for the people.",
				'cta_primary'        => 'Explore membership',
				'cta_primary_href'   => '?page=membership',
				'cta_secondary'      => 'Take a tour →',
				'cta_secondary_href' => '?page=about',
				'image'              => '',
				'image_alt'          => 'ClubHouse floodlit pitch on a Saturday',
				'image_caption'      => 'Saturday, floodlights on',
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'stats' ) ) {
			$out .= Blueworx_Clubhouse_Sections::stat_strip( array(
				array( 'value' => '900+', 'label' => 'Members' ),
				array( 'value' => '9', 'label' => 'Sports' ),
				array( 'value' => '24', 'label' => 'Teams' ),
				array( 'value' => '1974', 'label' => 'Founded' ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'footer' ) ) {
			$out .= Blueworx_Clubhouse_Sections::footer( array(
				'club_name' => $club,
				'tagline'   => 'A home ground for every team, and everyone who follows them.',
			) );
		}
		return $out;
	}
}
