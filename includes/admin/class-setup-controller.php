<?php
// includes/admin/class-setup-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress-coupled controller for the Clubhouse Setup admin screen: menu
 * registration, asset enqueue, and POST handling. All HTML is delegated to
 * Setup_Screen; persistence goes through the existing setters. handle_save takes
 * a Storage so it is unit-testable WP-free.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Controller {

	public const CAPABILITY = 'manage_options'; // Phase 4 swaps this for the owner cap.
	public const PAGE_SLUG  = 'clubhouse-setup';
	public const NONCE      = 'clubhouse_setup_save';

	/**
	 * Apply a setup POST to storage. Returns notices (error/warning/success).
	 *
	 * @param array<string,mixed> $post
	 * @return array<int,array{type:string,text:string}>
	 */
	public static function handle_save( array $post, Blueworx_Clubhouse_Storage $storage ): array {
		$notices  = array();
		$registry = Blueworx_Clubhouse_Frontend::registry( $storage );
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$vis      = new Blueworx_Clubhouse_Visibility( $storage );

		// 1. Look.
		if ( isset( $post['clubhouse_look'] ) ) {
			$slug = sanitize_text_field( (string) $post['clubhouse_look'] );
			if ( $registry->has( $slug ) ) {
				$registry->set_active( $slug );
			}
		}
		$active = $registry->active() ?? new Blueworx_Clubhouse_Court_Side();

		// 2. Accent — reject if illegible for the (now-active) look.
		if ( isset( $post['clubhouse_accent'] ) ) {
			$accent = sanitize_hex_color( (string) $post['clubhouse_accent'] );
			if ( '' === $accent ) {
				$notices[] = array( 'type' => 'error', 'text' => 'The accent colour must be a 6-digit hex value like #c6f24e.' );
			} elseif ( ! Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $active, $accent ) ) {
				$notices[] = array( 'type' => 'error', 'text' => 'That accent is too low in contrast for the chosen look and was not saved. Pick a stronger colour.' );
			} else {
				$branding->set_accent( $accent );
			}
		}

		// 3. Text/URL branding.
		if ( isset( $post['clubhouse_club_name'] ) ) {
			$branding->set_club_name( sanitize_text_field( (string) $post['clubhouse_club_name'] ) );
		}
		if ( isset( $post['clubhouse_logo'] ) ) {
			$branding->set_logo( sanitize_text_field( (string) $post['clubhouse_logo'] ) );
		}
		if ( isset( $post['clubhouse_facebook'] ) ) {
			$branding->set_facebook_url( esc_url_raw( (string) $post['clubhouse_facebook'] ) );
		}
		if ( isset( $post['clubhouse_instagram'] ) ) {
			$branding->set_instagram_url( esc_url_raw( (string) $post['clubhouse_instagram'] ) );
		}

		// 4. Visibility — a checkbox is present only when ticked; absence = hidden.
		$pages    = isset( $post['clubhouse_page'] ) && is_array( $post['clubhouse_page'] ) ? $post['clubhouse_page'] : array();
		$sections = isset( $post['clubhouse_section'] ) && is_array( $post['clubhouse_section'] ) ? $post['clubhouse_section'] : array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $page ) {
			$vis->set_page_visible( $page['page'], isset( $pages[ $page['page'] ] ) );
			foreach ( $page['sections'] as $section ) {
				$skey = $page['page'] . '.' . $section['key'];
				$vis->set_section_visible( $page['page'], $section['key'], isset( $sections[ $skey ] ) );
			}
		}

		// 5. Warn if the stored accent is now illegible for the active look.
		if ( ! Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $active, $branding->get_accent() ) ) {
			$notices[] = array( 'type' => 'warning', 'text' => 'Your saved accent colour is low-contrast on the selected look. Choose a new accent for best legibility.' );
		}

		// 6. Bust the composed :root cache so the new look/accent take effect.
		( new Blueworx_Clubhouse_Theme_Cache( $storage ) )->invalidate();

		return $notices;
	}
}
