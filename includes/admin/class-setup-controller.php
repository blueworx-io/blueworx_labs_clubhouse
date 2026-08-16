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

	public const CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP; // manage_clubhouse — owner + admin.

	// The menu builder lives on this screen (issue #144), and editing the menu is
	// a Content Editor's job. So the menu ITEM is opened to the content
	// capability while every setup tab stays behind CAPABILITY: a Content Editor
	// reaching this page sees the Menu tab and nothing else.
	public const MENU_CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP;
	public const PAGE_SLUG  = 'clubhouse-setup';
	public const NONCE      = 'clubhouse_setup_save';
	// Set true on any setup save — marks the Visibility section "reviewed" for progress.
	public const VIS_SAVED_KEY = 'setup_visibility_saved';

	/**
	 * The preset swatches offered by every colour picker on this screen.
	 *
	 * Saturated across the hue wheel on purpose. A desaturated mid-luminance
	 * colour has no legible text colour at all on either polarity of shell (see
	 * Color_Engine::derive), so it would be refused on save — offering one as a
	 * preset would invite a club to pick a colour the screen then rejects.
	 *
	 * A starting point, not a limit: the spectrum and the hex field both remain,
	 * so any colour a club actually uses is still reachable.
	 *
	 * @var array<int,string>
	 */
	public const PALETTE = array(
		'#c6f24e', // Volt lime — the shipped default.
		// Pitch green. Deeper than the obvious mid-green: a mid-luminance green
		// (#1f8a5c, the first choice here) clears neither black nor white at AA, so
		// the screen would have offered a swatch it then refused on save.
		'#166534',
		'#0b6fd1', // Club blue.
		'#123a8c', // Navy.
		'#7b2ff2', // Violet.
		'#c2185b', // Magenta.
		'#d62828', // Club red.
		'#e2711d', // Orange.
		'#f2b705', // Gold.
		'#0f766e', // Teal.
	);

	/**
	 * Apply a setup POST to storage. Returns notices (error/warning/success).
	 *
	 * @param array<string,mixed> $post
	 * @return array<int,array{type:string,text:string}>
	 */
	public static function handle_save( array $post, Blueworx_Clubhouse_Storage $storage, bool $can_demo = false ): array {
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

		// 2b. Secondary. Empty clears it, which returns the club to a shade derived
		// from the primary — that is a legitimate choice, not a mistake, so it is
		// never treated as an error.
		//
		// An illegible secondary WARNS where an illegible primary is REFUSED. The
		// primary carries the site's main calls to action and its refusal has been
		// in place since the colour engine shipped; the secondary is spent on second
		// actions and section marks, where a club that insists on its real brand
		// colour should be told, not overruled. Every derived token is legibility-
		// clamped either way, so a warned secondary is still a readable page.
		if ( isset( $post['clubhouse_secondary'] ) ) {
			$raw = trim( (string) $post['clubhouse_secondary'] );
			if ( '' === $raw ) {
				$branding->set_secondary( '' );
			} else {
				$secondary = sanitize_hex_color( $raw );
				if ( '' === (string) $secondary ) {
					$notices[] = array( 'type' => 'error', 'text' => 'The secondary colour must be a 6-digit hex value like #1f8a5c, or empty to derive it from your primary.' );
				} else {
					$branding->set_secondary( (string) $secondary );
					if ( ! Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $active, (string) $secondary ) ) {
						$notices[] = array( 'type' => 'warning', 'text' => 'That secondary colour is low in contrast on the chosen look. It has been saved, but text on it may be hard to read — a stronger colour will look better.' );
					}
				}
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
		if ( isset( $post['clubhouse_linkedin'] ) ) {
			$branding->set_linkedin_url( esc_url_raw( (string) $post['clubhouse_linkedin'] ) );
		}
		if ( isset( $post['clubhouse_x'] ) ) {
			$branding->set_x_url( esc_url_raw( (string) $post['clubhouse_x'] ) );
		}
		if ( isset( $post['clubhouse_favicon'] ) ) {
			$branding->set_favicon( sanitize_text_field( (string) $post['clubhouse_favicon'] ) );
		}

		// 3b. Where members land after signing in and out. Stored as typed: an
		// off-site target is refused at redirect time, not at save time, so an owner
		// who pastes a full URL for their own site is not told they are wrong.
		$auth = new Blueworx_Clubhouse_Auth_Settings( $storage );
		if ( isset( $post['clubhouse_post_login'] ) ) {
			$auth->set_post_login( sanitize_text_field( (string) $post['clubhouse_post_login'] ) );
		}
		if ( isset( $post['clubhouse_post_logout'] ) ) {
			$auth->set_post_logout( sanitize_text_field( (string) $post['clubhouse_post_logout'] ) );
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

		// Demo mode is an admin-only control — only an admin's save (with the demo
		// panel present) may change it. An owner's save never touches Demo_State.
		if ( $can_demo ) {
			( new Blueworx_Clubhouse_Demo_State( $storage ) )->set( isset( $post['clubhouse_demo_active'] ) );
		}

		// 6. Mark the Visibility section reviewed (saving with the defaults counts).
		$storage->set( self::VIS_SAVED_KEY, true );

		// 7. Bust the composed :root cache so the new look/accent take effect.
		( new Blueworx_Clubhouse_Theme_Cache( $storage ) )->invalidate();

		// 8. Confirm the save to the owner (green success notice at the top).
		$notices[] = array( 'type' => 'success', 'text' => 'Your changes have been saved.' );

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
			self::MENU_CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' ),
			Blueworx_Clubhouse_Admin_Menu_Icons::data_uri( self::PAGE_SLUG ),
			3
		);
	}

	public static function enqueue( string $hook ): void {
		$is_setup_page = ( 'toplevel_page_' . self::PAGE_SLUG === $hook );
		$is_owner_dash = ( 'index.php' === $hook && Blueworx_Clubhouse_Owner_Role::is_owner( wp_get_current_user() ) );
		if ( ! $is_setup_page && ! $is_owner_dash ) {
			return;
		}
		wp_enqueue_media();
		// wp-color-picker is WordPress core's own Iris picker — spectrum, swatch,
		// palette and hex field in one control, already loaded everywhere else in
		// wp-admin. Preferred over a bespoke picker so the screen behaves the way
		// an admin already expects, with no new dependency. It brings its own
		// stylesheet, and depends on jQuery, which is declared here rather than
		// assumed.
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'clubhouse-admin-setup', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/admin-setup.css', array( 'wp-color-picker' ), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_enqueue_script( 'clubhouse-admin-setup', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/admin-setup.js', array( 'jquery', 'wp-color-picker' ), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	public static function render_page(): void {
		$can_setup = current_user_can( self::CAPABILITY );
		$can_menu  = current_user_can( self::MENU_CAPABILITY );
		if ( ! $can_setup && ! $can_menu ) {
			return;
		}
		$storage  = new Blueworx_Clubhouse_Options_Storage();
		$can_demo = $can_setup && current_user_can( 'manage_options' ); // demo mode is admin-only.
		$notices  = array();
		if ( $can_setup && isset( $_POST['clubhouse_setup_submit'] ) ) {
			check_admin_referer( self::NONCE );
			$notices = self::handle_save( wp_unslash( $_POST ), $storage, $can_demo );
		}
		// The menu builder saves through the content plumbing it has always used,
		// nonce and all — it moved screen, not owner.
		if ( $can_menu && isset( $_POST['clubhouse_content_submit'] ) ) {
			check_admin_referer( Blueworx_Clubhouse_Content_Controller::NONCE );
			$notices = Blueworx_Clubhouse_Content_Controller::handle_save( wp_unslash( $_POST ), $storage );
		}
		echo self::screen_html( $storage, $notices, $can_demo, $can_menu, $can_setup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Setup_Screen.
	}

	/**
	 * The Menu tab's own model. Built here rather than in build_model() because
	 * the link targets read the club's collections, which the owner dashboard
	 * and the tests have no reason to pay for.
	 *
	 * @return array<string,mixed>
	 */
	private static function menu_model( Blueworx_Clubhouse_Storage $storage, string $action_url ): array {
		return array(
			'tree'        => ( new Blueworx_Clubhouse_Menu( $storage ) )->tree(),
			'targets'     => Blueworx_Clubhouse_Link_Catalogue::targets( new Blueworx_Clubhouse_WP_Collections() ),
			'action_url'  => $action_url,
			'nonce_field' => wp_nonce_field( Blueworx_Clubhouse_Content_Controller::NONCE, '_wpnonce', true, false ),
		);
	}

	/** Render the Setup screen HTML for a storage + notices — shared by the page and the owner dashboard. */
	public static function screen_html( Blueworx_Clubhouse_Storage $storage, array $notices, bool $can_demo = false, bool $with_menu = false, bool $can_setup = true ): string {
		$nonce_field = wp_nonce_field( self::NONCE, '_wpnonce', true, false );
		$action_url  = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$model       = self::build_model( $storage, $notices, $nonce_field, $action_url, $can_demo );

		$model['can_setup'] = $can_setup;
		$model['menu']      = $with_menu ? self::menu_model( $storage, $action_url ) : null;

		return Blueworx_Clubhouse_Setup_Screen::render( $model );
	}

	/**
	 * @param array<int,array{type:string,text:string}> $notices
	 * @return array<string,mixed>
	 */
	public static function build_model( Blueworx_Clubhouse_Storage $storage, array $notices, string $nonce_field, string $action_url, bool $can_demo = false ): array {
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

		$favicon         = $branding->get_favicon();
		$favicon_preview = '';
		if ( '' !== $favicon ) {
			$favicon_preview = ctype_digit( $favicon ) ? (string) wp_get_attachment_image_url( (int) $favicon, 'medium' ) : $favicon;
		}
		$plugin_url = defined( 'BLUEWORX_LABS_CLUBHOUSE_URL' ) ? BLUEWORX_LABS_CLUBHOUSE_URL : '';
		$theming    = self::look_theming( $registry, $branding, $plugin_url );

		$pages_state    = array();
		$sections_state = array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $page ) {
			$pages_state[ $page['page'] ] = $vis->is_page_visible( $page['page'] );
			foreach ( $page['sections'] as $section ) {
				$sections_state[ $page['page'] . '.' . $section['key'] ] = $vis->is_section_visible( $page['page'], $section['key'] );
			}
		}

		$auth = new Blueworx_Clubhouse_Auth_Settings( $storage );

		return array(
			'nonce_field'   => $nonce_field,
			'action_url'    => $action_url,
			'notices'       => $notices,
			'members'       => array(
				'post_login'    => $auth->get_post_login(),
				'post_logout'   => $auth->get_post_logout(),
				// Empty unless the shop has a reachable dashboard, which is
				// exactly when a blank "after signing in" starts meaning it.
				'dashboard_url' => Blueworx_Clubhouse_Shop_Pages::url( 'dashboard' ),
			),
			'progress'      => Blueworx_Clubhouse_Setup_Progress::compute( $branding, $active_look ?? new Blueworx_Clubhouse_Court_Side(), '' !== $active_slug, (bool) $storage->get( self::VIS_SAVED_KEY, false ) ),
			'looks'         => $looks,
			'color_palette' => self::PALETTE,
			'branding'      => array(
				'accent'              => $branding->get_accent(),
				'accent_default'      => Blueworx_Clubhouse_Branding::default_accent(),
				'secondary'           => $branding->get_secondary(),
				// The reset target for the secondary picker is empty, not a colour:
				// clearing the field is what puts a club back on the derived default,
				// so offering a fixed hex as "the default" would be a lie.
				'secondary_default'   => '',
				'secondary_effective' => $branding->effective_secondary( $active_look ?? new Blueworx_Clubhouse_Court_Side() ),
				'club_name'           => $branding->get_club_name(),
				'logo'                => $logo,
				'logo_preview'        => $logo_preview,
				'favicon'             => $favicon,
				'favicon_preview'     => $favicon_preview,
				'facebook'            => $branding->get_facebook_url(),
				'instagram'           => $branding->get_instagram_url(),
				'linkedin'            => $branding->get_linkedin_url(),
				'x'                   => $branding->get_x_url(),
			),
			'inventory'     => Blueworx_Clubhouse_Setup_Sections::inventory(),
			'visibility'    => array( 'pages' => $pages_state, 'sections' => $sections_state ),
			'active_slug'   => null !== $active_look ? $active_look->slug() : '',
			'look_tokens'   => $theming['tokens'],
			'font_face_css' => $theming['faces'],
			'role_tags'     => Blueworx_Clubhouse_Access_Controller::role_tags_for( self::PAGE_SLUG ),
			'can_demo'      => $can_demo,
			'demo_active'   => $can_demo && ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on(),
		);
	}

	/**
	 * Compose each registered look's :root token map (at the current accent) plus
	 * the combined @font-face CSS for all looks — powers the live re-skin preview.
	 *
	 * @return array{tokens:array<string,array<string,string>>,faces:string}
	 */
	private static function look_theming( Blueworx_Clubhouse_Base_Look_Registry $registry, Blueworx_Clubhouse_Branding $branding, string $plugin_url ): array {
		$tokens = array();
		$faces  = '';
		foreach ( $registry->all() as $look ) {
			$tokens[ $look->slug() ] = Blueworx_Clubhouse_Theme_Css::compose( $look, $branding );
			$faces                  .= Blueworx_Clubhouse_Page_Renderer::font_face_css( $look, $plugin_url );
		}
		return array( 'tokens' => $tokens, 'faces' => $faces );
	}
}
