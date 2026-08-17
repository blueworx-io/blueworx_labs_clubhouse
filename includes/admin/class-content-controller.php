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

	// edit_clubhouse_content — owner, content editor + admin. NOT manage_clubhouse:
	// that is the one capability separating the two Clubhouse roles, so locking
	// Club Pages with it shut the Content Editor out of the only job the role
	// exists for.
	public const CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP;
	public const PAGE_SLUG  = 'clubhouse-site-content';
	public const NONCE      = 'clubhouse_content_save';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_menu(): void {
		add_menu_page(
			'Club Pages',
			'Club Pages',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' ),
			Blueworx_Clubhouse_Admin_Menu_Icons::data_uri( self::PAGE_SLUG ),
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

		if ( 'menu' === $tab_slug ) {
			return self::save_menu( $post, $storage );
		}

		$page = self::find_page( $tab_slug );
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

		self::clear_filled_images( $storage, $content_store );
		// The site is rendered from blocks, and this screen still writes to the
		// content store, so a save has to reach the blocks or an owner's change
		// would not appear on the site.
		self::sync_blocks( $storage, $content_store, $vis );

		return array( array( 'type' => 'success', 'text' => 'Your changes have been saved.' ) );
	}

	/**
	 * Project the content and visibility a screen has just saved onto the club's
	 * blocks. A site that has not been composed yet is left alone: it is still
	 * rendering from the page methods, and composing it is the installer's job.
	 */
	public static function sync_blocks(
		Blueworx_Clubhouse_Storage $storage,
		Blueworx_Clubhouse_Content_Store $content,
		Blueworx_Clubhouse_Visibility $visibility
	): void {
		$seeder = new Blueworx_Clubhouse_Block_Seeder(
			new Blueworx_Clubhouse_Block_Library( $storage ),
			new Blueworx_Clubhouse_Page_Composition( $storage )
		);
		$seeder->sync( $content, $visibility );
	}

	/**
	 * Apply a Menu-tab post. The submitted form is already the tree — order and
	 * nesting live in the field names — so this reads it, applies whichever
	 * single move button was activated, and stores the result. One move per
	 * request: a form submits exactly one activated button.
	 *
	 * @param array<string,mixed> $post
	 * @return array<int,array{type:string,text:string}>
	 */
	private static function save_menu( array $post, Blueworx_Clubhouse_Storage $storage ): array {
		if ( ! array_key_exists( 'menu', $post ) ) {
			// Same invariant as the field-group check above: PHP's max_input_vars
			// can truncate a large POST before the 'menu' key ever arrives, and a
			// wholly-absent key must not be read as "every row was deleted" —
			// Menu::tree() treats a stored empty array as exactly that. An
			// explicitly submitted empty array (the owner really did empty it)
			// still reaches menu_from_post() below and saves as empty.
			return array();
		}

		$tree = self::menu_from_post( self::as_array( $post['menu'] ?? null ) );

		$tree = self::menu_move( $tree, 'up', self::first_key( $post['clubhouse_menu_up'] ?? null ) );
		$tree = self::menu_move( $tree, 'down', self::first_key( $post['clubhouse_menu_down'] ?? null ) );
		$tree = self::menu_move( $tree, 'indent', self::first_key( $post['clubhouse_menu_indent'] ?? null ) );
		$tree = self::menu_move( $tree, 'outdent', self::first_key( $post['clubhouse_menu_outdent'] ?? null ) );
		$tree = self::menu_move( $tree, 'remove', self::first_key( $post['clubhouse_menu_remove'] ?? null ) );

		if ( isset( $post['clubhouse_menu_add'] ) ) {
			$tree[] = array( 'label' => 'New item', 'target' => 'page:home', 'children' => array() );
		}

		( new Blueworx_Clubhouse_Menu( $storage ) )->save( $tree );
		return array( array( 'type' => 'success', 'text' => 'Your menu has been saved.' ) );
	}

	/** The single path a move button submitted, or '' when none did. */
	private static function first_key( $raw ): string {
		if ( ! is_array( $raw ) ) {
			return '';
		}
		foreach ( $raw as $key => $ignored ) {
			return (string) $key;
		}
		return '';
	}

	/**
	 * Turn the posted rows into the stored tree, folding each row's target
	 * select and its custom-URL box into one target tag.
	 *
	 * @param array<int|string,mixed> $rows
	 * @return array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	private static function menu_from_post( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$children = array();
			foreach ( self::as_array( $row['children'] ?? null ) as $child ) {
				if ( is_array( $child ) ) {
					$children[] = array(
						'label'  => (string) ( $child['label'] ?? '' ),
						'target' => self::menu_target( $child ),
					);
				}
			}
			$out[] = array(
				'label'    => (string) ( $row['label'] ?? '' ),
				'target'   => self::menu_target( $row ),
				'children' => $children,
			);
		}
		return $out;
	}

	/**
	 * A row's target. The picker's "Custom URL…" option posts the bare tag
	 * 'url:', which only means anything once the adjacent box is filled in — so
	 * an empty box yields an empty target and Menu::save() drops the row.
	 *
	 * @param array<string,mixed> $row
	 */
	private static function menu_target( array $row ): string {
		$target = trim( (string) ( $row['target'] ?? '' ) );
		if ( 'url:' !== $target ) {
			return $target;
		}
		$custom = trim( (string) ( $row['custom'] ?? '' ) );
		return '' === $custom ? '' : 'url:' . $custom;
	}

	/**
	 * Apply one move to the tree. $path is '<i>' for a top-level row or
	 * '<i>-<j>' for a child; '' is a no-op, which is the normal case for four
	 * of the five buttons on any given request.
	 *
	 * @param array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}> $tree
	 * @return array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	private static function menu_move( array $tree, string $op, string $path ): array {
		if ( '' === $path ) {
			return $tree;
		}
		$parts  = explode( '-', $path );
		$i      = (int) $parts[0];
		$j      = isset( $parts[1] ) ? (int) $parts[1] : null;
		$child  = null !== $j;
		if ( ! isset( $tree[ $i ] ) || ( $child && ! isset( $tree[ $i ]['children'][ $j ] ) ) ) {
			return $tree;
		}

		if ( 'remove' === $op ) {
			if ( $child ) {
				unset( $tree[ $i ]['children'][ $j ] );
				$tree[ $i ]['children'] = array_values( $tree[ $i ]['children'] );
			} else {
				unset( $tree[ $i ] );
				$tree = array_values( $tree );
			}
			return $tree;
		}

		if ( 'indent' === $op && ! $child && $i > 0 ) {
			// The row and everything under it hang off the row above; a third
			// level cannot exist, so its own children are promoted alongside it.
			$row   = $tree[ $i ];
			$moved = array_merge(
				array( array( 'label' => $row['label'], 'target' => $row['target'] ) ),
				array_map(
					static fn( array $c ): array => array( 'label' => $c['label'], 'target' => $c['target'] ),
					$row['children']
				)
			);
			$tree[ $i - 1 ]['children'] = array_merge( $tree[ $i - 1 ]['children'], $moved );
			unset( $tree[ $i ] );
			return array_values( $tree );
		}

		if ( 'outdent' === $op && $child ) {
			$row = $tree[ $i ]['children'][ $j ];
			unset( $tree[ $i ]['children'][ $j ] );
			$tree[ $i ]['children'] = array_values( $tree[ $i ]['children'] );
			array_splice( $tree, $i + 1, 0, array( array( 'label' => $row['label'], 'target' => $row['target'], 'children' => array() ) ) );
			return array_values( $tree );
		}

		$delta = 'up' === $op ? -1 : ( 'down' === $op ? 1 : 0 );
		if ( 0 === $delta ) {
			return $tree;
		}

		if ( $child ) {
			$to = $j + $delta;
			if ( isset( $tree[ $i ]['children'][ $to ] ) ) {
				$swap                          = $tree[ $i ]['children'][ $to ];
				$tree[ $i ]['children'][ $to ] = $tree[ $i ]['children'][ $j ];
				$tree[ $i ]['children'][ $j ]  = $swap;
			}
			return $tree;
		}

		$to = $i + $delta;
		if ( isset( $tree[ $to ] ) ) {
			$swap        = $tree[ $to ];
			$tree[ $to ] = $tree[ $i ];
			$tree[ $i ]  = $swap;
		}
		return $tree;
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

		$notices = array_merge( self::images_needed_notice( $storage ), $notices );

		$catalogue = array();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages( Blueworx_Clubhouse_Products_Source::get() ) as $page ) {
			$vis_page = self::vis_page_for_tab( $page['tab'] );
			$sections = array();
			foreach ( $page['sections'] as $section ) {
				$store_page  = (string) $section['store_page'];
				$section_key = (string) $section['key'];

				$values = array();
				if ( ! empty( $section['fields'] ) ) {
					foreach ( $section['fields'] as $field_def ) {
						// null, not '': the screen has to tell "never saved" from
						// "saved empty" so a switch nobody has touched can draw its
						// declared default. Every other field type casts to string,
						// so null reads the same as '' for them.
						$values[ $field_def['key'] ] = $content_store->get( $store_page, $section_key, $field_def['key'], null );
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
			'menu_tree'     => ( new Blueworx_Clubhouse_Menu( $storage ) )->tree(),
			'menu_targets'  => Blueworx_Clubhouse_Link_Catalogue::targets( new Blueworx_Clubhouse_WP_Collections() ),
			'role_tags'     => Blueworx_Clubhouse_Access_Controller::role_tags_for( self::PAGE_SLUG ),
		);
	}

	/**
	 * Turn any image slots an import could not fill into a notice that links
	 * straight to the sections that need them. Read from the same storage key
	 * the importer writes, so the list survives until the owner actually
	 * supplies the pictures.
	 *
	 * @return array<int,array{type:string,text:string,links:array<int,array{label:string,tab:string,sec:string}>}>
	 */
	private static function images_needed_notice( Blueworx_Clubhouse_Storage $storage ): array {
		$needed = $storage->get( Blueworx_Clubhouse_Import_Controller::IMAGES_NEEDED_KEY, array() );
		if ( ! is_array( $needed ) || array() === $needed ) {
			return array();
		}
		$index = Blueworx_Clubhouse_Content_Catalogue::index();
		$links = array();
		foreach ( $needed as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$address = (string) ( $entry['page'] ?? '' ) . '/' . (string) ( $entry['section'] ?? '' );
			if ( ! isset( $index[ $address ] ) ) {
				continue;
			}
			$links[] = array(
				'label' => (string) ( $entry['label'] ?? $address ),
				'tab'   => $index[ $address ]['tab'],
				'sec'   => $index[ $address ]['section_key'],
			);
		}
		if ( array() === $links ) {
			return array();
		}
		$count = count( $links );
		return array( array(
			'type'  => 'warning',
			'text'  => sprintf(
				'%d %s from your import could not be fetched. Add %s here whenever you have the files:',
				$count,
				1 === $count ? 'picture' : 'pictures',
				1 === $count ? 'it' : 'them'
			),
			'links' => $links,
		) );
	}

	/**
	 * Drop any outstanding image slot the owner has now filled. Keyed on the
	 * stored value rather than on which tab was saved, so it stays correct
	 * whether the picture arrived through this screen or another.
	 *
	 * Branches on 'index' exactly as Import_Applier::place_image() does: a
	 * section-level image (index < 0) lives at a plain content field, while a
	 * loop-item image (index >= 0) lives at items[index][field] — reading the
	 * section field for a loop-item entry would always see '' and the entry
	 * would never clear. An entry stored before 'index' existed (or a
	 * hand-edited option) has no such key; default it to -1 so it keeps
	 * behaving as a section-level entry rather than being lost.
	 */
	private static function clear_filled_images( Blueworx_Clubhouse_Storage $storage, Blueworx_Clubhouse_Content_Store $content_store ): void {
		$needed = $storage->get( Blueworx_Clubhouse_Import_Controller::IMAGES_NEEDED_KEY, array() );
		if ( ! is_array( $needed ) || array() === $needed ) {
			return;
		}
		$left = array();
		foreach ( $needed as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$page    = (string) ( $entry['page'] ?? '' );
			$section = (string) ( $entry['section'] ?? '' );
			$field   = (string) ( $entry['field'] ?? '' );
			$index   = isset( $entry['index'] ) ? (int) $entry['index'] : -1;

			if ( $index < 0 ) {
				$value = $content_store->get( $page, $section, $field, '' );
			} else {
				$items = $content_store->get_items( $page, $section );
				$value = array_key_exists( $index, $items ) ? ( $items[ $index ][ $field ] ?? '' ) : '';
			}

			if ( '' === $value || 0 === $value ) {
				$left[] = $entry;
			}
		}
		$storage->set( Blueworx_Clubhouse_Import_Controller::IMAGES_NEEDED_KEY, array_values( $left ) );
	}

	/** The Content_Catalogue page entry for a tab slug, or null if unknown. */
	private static function find_page( string $tab_slug ): ?array {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages( Blueworx_Clubhouse_Products_Source::get() ) as $page ) {
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
	 * Delegates to the shared pure sanitiser (also used by the AI import path).
	 *
	 * @param array<string,mixed> $field_def
	 */
	private static function sanitise_field( array $field_def, mixed $raw, bool $present ): mixed {
		return Blueworx_Clubhouse_Content_Sanitiser::field( $field_def, $raw, $present );
	}

	/**
	 * Sanitise every posted item of a loop section by its field definitions.
	 *
	 * @param array<int,array<string,mixed>> $loop_fields
	 * @param array<int,mixed>               $raw_items
	 * @return array<int,array<string,mixed>>
	 */
	private static function sanitise_items( array $loop_fields, array $raw_items ): array {
		return Blueworx_Clubhouse_Content_Sanitiser::items( $loop_fields, $raw_items );
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
