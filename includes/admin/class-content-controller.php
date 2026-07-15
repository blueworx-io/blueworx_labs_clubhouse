<?php
// includes/admin/class-content-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress-coupled controller for the Clubhouse Site Content admin screen:
 * menu registration, asset enqueue, view-model building, and POST handling.
 * All HTML is delegated to Content_Screen. handle_save/build_model take a
 * Storage so they are unit-testable WP-free, mirroring Setup_Controller.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Content_Controller {

	public const CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP; // manage_clubhouse — owner + admin.
	public const PAGE_SLUG  = 'clubhouse-site-content';
	public const NONCE      = 'clubhouse_content_save';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_menu(): void {
		add_menu_page(
			'Site Content',
			'Site Content',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' ),
			'dashicons-edit',
			5
		);
	}

	public static function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'clubhouse-admin-content', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/admin-content.css', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_enqueue_script( 'clubhouse-admin-content', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/admin-content.js', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$storage = new Blueworx_Clubhouse_Options_Storage();
		$notices = array();
		if ( isset( $_POST['clubhouse_content_submit'] ) ) {
			check_admin_referer( self::NONCE );
			$notices = self::handle_save( wp_unslash( $_POST ), $storage );
		}
		echo self::screen_html( $storage, $notices ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Content_Screen.
	}

	/** Render the Content screen HTML for a storage + notices. */
	public static function screen_html( Blueworx_Clubhouse_Storage $storage, array $notices ): string {
		$nonce_field = wp_nonce_field( self::NONCE, '_wpnonce', true, false );
		$action_url  = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		return Blueworx_Clubhouse_Content_Screen::render( self::build_model( $storage, $notices, $nonce_field, $action_url ) );
	}

	/**
	 * Apply a content-editor POST to storage, scoped to the submitted tab only
	 * (so editing Global never blanks another tab's sections). Returns notices.
	 *
	 * @param array<string,mixed> $post
	 * @return array<int,array{type:string,text:string}>
	 */
	public static function handle_save( array $post, Blueworx_Clubhouse_Storage $storage ): array {
		$tab_slug = isset( $post['clubhouse_content_tab'] ) ? (string) $post['clubhouse_content_tab'] : '';
		$page     = self::find_page( $tab_slug );
		if ( null === $page ) {
			return array();
		}

		$content_store = new Blueworx_Clubhouse_Content_Store( $storage );
		$vis           = new Blueworx_Clubhouse_Visibility( $storage );
		$vis_page      = self::vis_page_for_tab( $page['tab'] );

		$fields_post  = self::as_array( $post['field'] ?? null );
		$items_post   = self::as_array( $post['item'] ?? null );
		$hidden_post  = self::as_array( $post['hidden'] ?? null );
		$add_post     = self::as_array( $post['clubhouse_content_add'] ?? null );
		$remove_post  = self::as_array( $post['clubhouse_content_remove'] ?? null );

		foreach ( $page['sections'] as $section ) {
			$store_page  = (string) $section['store_page'];
			$section_key = (string) $section['key'];

			if ( ! empty( $section['fields'] ) ) {
				// Invariant: a wholly-absent field group (the section's key never
				// appears under field[<store_page>]) must NOT blank stored content —
				// PHP's max_input_vars silently truncates large POSTs, and treating a
				// truncated request as "every field was cleared" is silent data loss.
				// Only once the group is present does an individual absent key mean
				// "cleared" (real unchecked-checkbox / emptied-input form semantics).
				$store_scope = self::as_array( $fields_post[ $store_page ] ?? null );
				if ( array_key_exists( $section_key, $store_scope ) ) {
					$group = self::as_array( $store_scope[ $section_key ] );
					foreach ( $section['fields'] as $field_def ) {
						$fkey    = (string) $field_def['key'];
						$present = array_key_exists( $fkey, $group );
						$value   = self::sanitise_field( $field_def, $present ? $group[ $fkey ] : null, $present );
						$content_store->set( $store_page, $section_key, $fkey, $value );
					}
				}
			}

			if ( ! empty( $section['loop'] ) ) {
				$loop_fields   = $section['loop']['fields'];
				$raw_items     = $items_post[ $store_page ][ $section_key ] ?? null;
				$submitted     = is_array( $raw_items );
				$items         = $submitted ? self::sanitise_items( $loop_fields, $raw_items ) : $content_store->get_items( $store_page, $section_key );
				$mutated       = false;

				if ( array_key_exists( $section_key, self::as_array( $add_post[ $store_page ] ?? null ) ) ) {
					$blank = array();
					foreach ( $loop_fields as $field_def ) {
						$blank[ $field_def['key'] ] = self::sanitise_field( $field_def, null, false );
					}
					$items[] = $blank;
					$mutated = true;
				}

				if ( array_key_exists( $section_key, self::as_array( $remove_post[ $store_page ] ?? null ) ) ) {
					$raw_idx = $remove_post[ $store_page ][ $section_key ];
					// An empty/non-numeric value (e.g. '') must not resolve to index 0
					// via (int) '' === 0 — that would delete the first item outright.
					if ( is_numeric( $raw_idx ) ) {
						$idx = (int) $raw_idx;
						if ( array_key_exists( $idx, $items ) ) {
							unset( $items[ $idx ] );
							$items = array_values( $items );
						}
						$mutated = true;
					}
				}

				if ( $submitted || $mutated ) {
					$content_store->set_items( $store_page, $section_key, $items );
				}
			}

			$hidden = array_key_exists( $section_key, self::as_array( $hidden_post[ $vis_page ] ?? null ) );
			$vis->set_section_visible( $vis_page, $section_key, ! $hidden );
		}

		return array( array( 'type' => 'success', 'text' => 'Your changes have been saved.' ) );
	}

	/**
	 * Build the view-model consumed by Content_Screen: the catalogue with
	 * current stored values, loop items, and per-section hidden state merged
	 * in, plus the active look's theming tokens.
	 *
	 * @param array<int,array{type:string,text:string}> $notices
	 * @return array<string,mixed>
	 */
	public static function build_model( Blueworx_Clubhouse_Storage $storage, array $notices, string $nonce_field, string $action_url ): array {
		$content_store = new Blueworx_Clubhouse_Content_Store( $storage );
		$vis           = new Blueworx_Clubhouse_Visibility( $storage );
		$registry      = Blueworx_Clubhouse_Frontend::registry( $storage );
		$branding      = new Blueworx_Clubhouse_Branding( $storage );
		$active_look   = $registry->active();
		$plugin_url    = defined( 'BLUEWORX_LABS_CLUBHOUSE_URL' ) ? BLUEWORX_LABS_CLUBHOUSE_URL : '';
		$theming       = self::look_theming( $registry, $branding, $plugin_url );

		$catalogue = array();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$vis_page = self::vis_page_for_tab( $page['tab'] );
			$sections = array();
			foreach ( $page['sections'] as $section ) {
				$store_page  = (string) $section['store_page'];
				$section_key = (string) $section['key'];

				$values = array();
				if ( ! empty( $section['fields'] ) ) {
					foreach ( $section['fields'] as $field_def ) {
						$values[ $field_def['key'] ] = $content_store->get( $store_page, $section_key, $field_def['key'], '' );
					}
				}

				$items = ! empty( $section['loop'] ) ? $content_store->get_items( $store_page, $section_key ) : array();

				$sections[] = $section + array(
					'values'   => $values,
					'items'    => $items,
					'hidden'   => ! $vis->is_section_visible( $vis_page, $section_key ),
					// Visibility's inventory key — distinct from 'store_page' when a
					// section's content lives on one page but its show/hide flag is
					// keyed to another (e.g. Global tab's Header/Footer store under
					// 'global' but hide under 'home'). Task 7 must key hide inputs by
					// this, not 'store_page', or unticking "show" silently no-ops.
					'vis_page' => $vis_page,
				);
			}
			$catalogue[] = array(
				'tab'      => $page['tab'],
				'label'    => $page['label'],
				'vis_page' => $vis_page,
				'sections' => $sections,
			);
		}

		return array(
			'nonce_field'   => $nonce_field,
			'action_url'    => $action_url,
			'notices'       => $notices,
			'catalogue'     => $catalogue,
			'active_slug'   => null !== $active_look ? $active_look->slug() : '',
			'look_tokens'   => $theming['tokens'],
			'font_face_css' => $theming['faces'],
		);
	}

	/** The Content_Catalogue page entry for a tab slug, or null if unknown. */
	private static function find_page( string $tab_slug ): ?array {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			if ( $page['tab'] === $tab_slug ) {
				return $page;
			}
		}
		return null;
	}

	/**
	 * Visibility's inventory is keyed by page slug ('home', 'about', …); the
	 * catalogue's 'global' tab maps onto Visibility's 'home' page — every
	 * other tab slug matches its Visibility page slug directly.
	 */
	private static function vis_page_for_tab( string $tab ): string {
		return 'global' === $tab ? 'home' : $tab;
	}

	/** @return array<string,mixed> */
	private static function as_array( mixed $value ): array {
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Sanitise a single field's posted value by its catalogue type.
	 *
	 * @param array<string,mixed> $field_def
	 */
	private static function sanitise_field( array $field_def, mixed $raw, bool $present ): mixed {
		// A posted value that isn't scalar (e.g. field[key][]=x submitted as an
		// array, or a nested array under an image/select field) must never reach
		// string coercion below — PHP would emit "Array to string conversion" and
		// store the literal "Array". Treat it as though the field were absent.
		if ( $present && ! is_scalar( $raw ) ) {
			$present = false;
		}
		switch ( $field_def['type'] ) {
			case 'text':
				return $present ? sanitize_text_field( (string) $raw ) : '';
			case 'textarea':
				return $present ? sanitize_textarea_field( (string) $raw ) : '';
			case 'url':
				return $present ? esc_url_raw( (string) $raw ) : '';
			case 'image':
				return $present ? absint( $raw ) : 0;
			case 'toggle':
				return $present;
			case 'select':
				$value   = $present ? (string) $raw : '';
				$options = $field_def['options'] ?? array();
				return array_key_exists( $value, $options ) ? $value : '';
			default:
				return '';
		}
	}

	/**
	 * Sanitise every posted item of a loop section by its field definitions.
	 *
	 * @param array<int,array<string,mixed>> $loop_fields
	 * @param array<int,mixed>               $raw_items
	 * @return array<int,array<string,mixed>>
	 */
	private static function sanitise_items( array $loop_fields, array $raw_items ): array {
		$items = array();
		foreach ( $raw_items as $raw_item ) {
			$raw_item = self::as_array( $raw_item );
			$item     = array();
			foreach ( $loop_fields as $field_def ) {
				$fkey            = (string) $field_def['key'];
				$present         = array_key_exists( $fkey, $raw_item );
				$item[ $fkey ]   = self::sanitise_field( $field_def, $present ? $raw_item[ $fkey ] : null, $present );
			}
			$items[] = $item;
		}
		return $items;
	}

	/**
	 * Compose each registered look's :root token map (at the current accent)
	 * plus the combined @font-face CSS — powers the live re-skin preview.
	 * Mirrors Setup_Controller::look_theming.
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
