<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes the six setup-progress booleans for the Clubhouse Setup screen.
 * Pure. An item counts when its value differs from the plugin's demo default
 * (logo: any non-empty value); the accent must additionally be legible for the
 * active look (look-aware: text-bearing looks need ink+deep, glow-only need deep).
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Progress {

	// Mirror of Blueworx_Clubhouse_Branding::DEFAULTS (kept explicit for the check).
	private const DEMO_ACCENT    = '#c6f24e';
	private const DEMO_CLUB_NAME = 'ClubHouse';
	private const DEMO_FACEBOOK  = 'https://facebook.com/clubhouse';
	private const DEMO_INSTAGRAM = 'https://instagram.com/clubhouse';

	/**
	 * @return array{items:array{look:bool,accent:bool,club_name:bool,logo:bool,facebook:bool,instagram:bool},completed:int,total:int}
	 */
	public static function compute(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Base_Look $active_look,
		bool $look_chosen
	): array {
		$accent = $branding->get_accent();

		$items = array(
			'look'      => $look_chosen,
			'accent'    => self::DEMO_ACCENT !== $accent
				&& Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $active_look, $accent ),
			'club_name' => '' !== $branding->get_club_name() && self::DEMO_CLUB_NAME !== $branding->get_club_name(),
			'logo'      => '' !== $branding->get_logo(),
			'facebook'  => '' !== $branding->get_facebook_url() && self::DEMO_FACEBOOK !== $branding->get_facebook_url(),
			'instagram' => '' !== $branding->get_instagram_url() && self::DEMO_INSTAGRAM !== $branding->get_instagram_url(),
		);

		return array(
			'items'     => $items,
			'completed' => count( array_filter( $items ) ),
			'total'     => count( $items ),
		);
	}
}
