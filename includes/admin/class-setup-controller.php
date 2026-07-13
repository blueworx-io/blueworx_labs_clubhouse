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

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_menu(): void {
		add_menu_page(
			'Clubhouse Setup',
			'Clubhouse',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' ),
			'dashicons-megaphone',
			3
		);
	}

	public static function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'clubhouse-admin-setup', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/admin-setup.css', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_enqueue_script( 'clubhouse-admin-setup', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/admin-setup.js', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$storage = new Blueworx_Clubhouse_Options_Storage();
		$notices = array();
		if ( isset( $_POST['clubhouse_setup_submit'] ) ) {
			check_admin_referer( self::NONCE );
			$notices = self::handle_save( wp_unslash( $_POST ), $storage );
		}
		$nonce_field = wp_nonce_field( self::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="clubhouse_setup_submit" value="1">';
		$action_url  = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		echo Blueworx_Clubhouse_Setup_Screen::render( self::build_model( $storage, $notices, $nonce_field, $action_url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array<int,array{type:string,text:string}> $notices
	 * @return array<string,mixed>
	 */
	public static function build_model( Blueworx_Clubhouse_Storage $storage, array $notices, string $nonce_field, string $action_url ): array {
		$registry    = Blueworx_Clubhouse_Frontend::registry( $storage );
		$branding    = new Blueworx_Clubhouse_Branding( $storage );
		$vis         = new Blueworx_Clubhouse_Visibility( $storage );
		$active_slug = (string) $storage->get( 'active_base_look', '' );
		$active_look = $registry->active();

		$looks = array();
		foreach ( $registry->all() as $look ) {
			$looks[] = array(
				'slug'        => $look->slug(),
				'name'        => $look->name(),
				'description' => $look->description(),
				'active'      => null !== $active_look && $look->slug() === $active_look->slug(),
			);
		}

		$logo         = $branding->get_logo();
		$logo_preview = '';
		if ( '' !== $logo ) {
			$logo_preview = ctype_digit( $logo ) ? (string) wp_get_attachment_image_url( (int) $logo, 'medium' ) : $logo;
		}

		$pages_state    = array();
		$sections_state = array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $page ) {
			$pages_state[ $page['page'] ] = $vis->is_page_visible( $page['page'] );
			foreach ( $page['sections'] as $section ) {
				$sections_state[ $page['page'] . '.' . $section['key'] ] = $vis->is_section_visible( $page['page'], $section['key'] );
			}
		}

		return array(
			'nonce_field' => $nonce_field,
			'action_url'  => $action_url,
			'notices'     => $notices,
			'progress'    => Blueworx_Clubhouse_Setup_Progress::compute( $branding, $active_look ?? new Blueworx_Clubhouse_Court_Side(), '' !== $active_slug ),
			'looks'       => $looks,
			'branding'    => array(
				'accent'       => $branding->get_accent(),
				'club_name'    => $branding->get_club_name(),
				'logo'         => $logo,
				'logo_preview' => $logo_preview,
				'facebook'     => $branding->get_facebook_url(),
				'instagram'    => $branding->get_instagram_url(),
			),
			'inventory'   => Blueworx_Clubhouse_Setup_Sections::inventory(),
			'visibility'  => array( 'pages' => $pages_state, 'sections' => $sections_state ),
		);
	}
}
