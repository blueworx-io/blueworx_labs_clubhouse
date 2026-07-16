<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML builder for the Clubhouse Site Content admin screen: a bespoke,
 * tabbed, look-inheriting form over the Content_Catalogue. The controller
 * supplies the model (catalogue merged with current values/hidden state, the
 * active look's tokens, and the WP nonce/action); this class makes no
 * WordPress calls, reads no request data, and has no persistence.
 *
 * Every page is rendered as its own <form> (one per catalogue page), each
 * carrying its own hidden `clubhouse_content_tab` and its own Save button —
 * Content_Controller::handle_save() only ever persists the single tab named
 * by that field, so a shared form across every page would let edits to a
 * page other than the one whose hidden tab value was submitted silently
 * vanish. All pages/sections render at once (no request-driven filtering,
 * since this class never reads $_GET) so the screen is fully usable and
 * keyboard-reachable with JavaScript disabled; Task 8's script upgrades this
 * into a true single-panel tabbed view.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Content_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Escape a URL for use in an href/src attribute, rejecting dangerous
	 * schemes (javascript:, data:, vbscript:, etc.) before entity-escaping.
	 * Every href in this screen is currently built from trusted, server-side
	 * strings (the controller's admin_url() action_url, or catalogue tab/sec/
	 * cpt slugs) — never a value pulled straight from Content_Store — but the
	 * name "esc_url" promises scheme-level URL safety, not just character
	 * escaping, so it must actually provide that guarantee rather than trap
	 * the first caller who routes a stored value into an href.
	 */
	private static function esc_url( string $v ): string {
		if ( preg_match( '/^\s*([a-zA-Z][a-zA-Z0-9+.\-]*):/', $v, $m ) ) {
			$scheme = strtolower( $m[1] );
			if ( ! in_array( $scheme, array( 'http', 'https', 'mailto' ), true ) ) {
				return '';
			}
		}
		return self::esc( $v );
	}

	/**
	 * Join a token map into a raw CSS declaration string for embedding inside a
	 * <style> block: "--k:v;--k2:v2;". Unlike esc() this does NOT run values
	 * through entity-escaping — a <style> element is raw text, so HTML
	 * character references are not decoded by the browser and would corrupt
	 * values such as font-family names. Values here are fully server-controlled
	 * (hardcoded look tokens plus a sanitize_hex_color-validated accent); as
	 * defense-in-depth, strip characters that could break out of the block.
	 */
	private static function css_tokens( array $tokens ): string {
		$out = '';
		foreach ( $tokens as $name => $value ) {
			$safe_name  = str_replace( array( '<', '}' ), '', (string) $name );
			$safe_value = str_replace( array( '<', '}' ), '', (string) $value );
			$out       .= $safe_name . ':' . $safe_value . ';';
		}
		return $out;
	}

	/** A DOM-safe id/anchor fragment built from arbitrary key parts. */
	private static function slug_id( string ...$parts ): string {
		$joined = implode( '-', $parts );
		return (string) preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $joined );
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$active_tokens = $model['look_tokens'][ $model['active_slug'] ] ?? array();
		$catalogue     = $model['catalogue'];
		$action_url    = (string) $model['action_url'];

		$out  = '<div class="wrap clubhouse-wrap">';
		$out .= '<style>' . (string) $model['font_face_css']
			. '.clubhouse-content{' . self::css_tokens( $active_tokens ) . '}</style>';
		$out .= '<div class="clubhouse-content">';
		$out .= self::header();
		$out .= self::notices( $model['notices'] );
		$out .= self::page_tabs( $catalogue, $action_url );

		foreach ( $catalogue as $index => $page ) {
			$out .= self::page_block( $page, 0 === $index, $action_url, (string) $model['nonce_field'] );
		}

		$out .= '</div></div>';
		return $out;
	}

	private static function header(): string {
		return '<header class="clubhouse-head">'
			. '<div class="clubhouse-head__titles"><p class="clubhouse-eyebrow">Clubhouse · Club content</p>'
			. '<h1 class="clubhouse-head__h1">Clubhouse Content</h1></div>'
			. '<a class="clubhouse-btn-link" href="admin.php?page=clubhouse-setup">Site setup →</a>'
			. '</header>';
	}

	/** @param array<int,array{type:string,text:string}> $notices */
	private static function notices( array $notices ): string {
		$out = '';
		foreach ( $notices as $n ) {
			$type = in_array( $n['type'], array( 'error', 'warning', 'success' ), true ) ? $n['type'] : 'info';
			$out .= '<div class="notice notice-' . self::esc( $type ) . '"><p>' . self::esc( $n['text'] ) . '</p></div>';
		}
		return $out;
	}

	/** @param array<int,array{tab:string,label:string,vis_page:string,sections:array<int,array<string,mixed>>}> $catalogue */
	private static function page_tabs( array $catalogue, string $action_url ): string {
		$out = '<nav class="clubhouse-pagetabs" role="tablist">';
		foreach ( $catalogue as $index => $page ) {
			$tab      = (string) $page['tab'];
			$cls      = 0 === $index ? ' is-active' : '';
			$selected = 0 === $index ? 'true' : 'false';
			$href     = self::tab_href( $action_url, $tab );
			$out     .= '<a class="clubhouse-pagetab' . $cls . '" href="' . self::esc_url( $href ) . '" data-tab="' . self::esc( $tab ) . '" role="tab" aria-selected="' . $selected . '">'
				. self::esc( (string) $page['label'] ) . '</a>';
		}
		$out .= '</nav>';
		return $out;
	}

	private static function tab_href( string $action_url, string $tab ): string {
		$sep = str_contains( $action_url, '?' ) ? '&' : '?';
		return $action_url . $sep . 'tab=' . $tab . '#' . self::slug_id( 'clubhouse-tab', $tab );
	}

	private static function sec_href( string $action_url, string $tab, string $sec ): string {
		$sep = str_contains( $action_url, '?' ) ? '&' : '?';
		return $action_url . $sep . 'tab=' . $tab . '&sec=' . $sec . '#' . self::slug_id( 'clubhouse-sec', $tab, $sec );
	}

	/** @param array{tab:string,label:string,vis_page:string,sections:array<int,array<string,mixed>>} $page */
	private static function page_block( array $page, bool $is_active, string $action_url, string $nonce_field ): string {
		$tab  = (string) $page['tab'];
		$cls  = $is_active ? ' is-active' : '';
		$out  = '<section class="clubhouse-pagepanel' . $cls . '" id="' . self::esc( self::slug_id( 'clubhouse-tab', $tab ) ) . '" data-pagepanel="' . self::esc( $tab ) . '" role="tabpanel">';
		$out .= '<form method="post" action="' . self::esc_url( $action_url ) . '" class="clubhouse-form">';
		$out .= $nonce_field;
		$out .= '<input type="hidden" name="clubhouse_content_tab" value="' . self::esc( $tab ) . '">';
		// Per the HTML spec, only the activated submit button contributes its
		// name/value to a submission — so a click on the Add/Remove loop buttons
		// (named clubhouse_content_add[…]/clubhouse_content_remove[…]) carries no
		// clubhouse_content_submit, and Content_Controller::render_page() only
		// calls handle_save() when that key is present. This hidden field makes
		// every submission path from this form persist, regardless of which
		// submit button was activated; the Save button below shares the same
		// name so its own click still works identically.
		$out .= '<input type="hidden" name="clubhouse_content_submit" value="1">';
		$out .= '<div class="clubhouse-body">';
		$out .= self::section_nav( $page, $action_url );
		$out .= '<div class="clubhouse-panels">';
		foreach ( $page['sections'] as $sindex => $section ) {
			$out .= self::section_panel( $section, $tab, 0 === $sindex, $action_url );
		}
		$out .= '</div></div>';
		$out .= self::save_bar();
		$out .= '</form></section>';
		return $out;
	}

	/**
	 * Section jump-nav for one page. These anchors are plain in-page links —
	 * every section panel renders simultaneously (no request-driven filtering
	 * and, absent Task 8's JS, no single-panel switching), so `role="tab"` /
	 * `role="tablist"` would announce a tab widget that doesn't exist to a
	 * screen reader. Left as plain links; Task 8 can layer real tab semantics
	 * (aria-selected, aria-controls) back on once it drives single-panel
	 * visibility.
	 *
	 * @param array{tab:string,sections:array<int,array<string,mixed>>} $page
	 */
	private static function section_nav( array $page, string $action_url ): string {
		$tab = (string) $page['tab'];
		$out = '<nav class="clubhouse-secnav">';
		foreach ( $page['sections'] as $index => $section ) {
			$key   = (string) $section['key'];
			$cls   = 0 === $index ? ' is-active' : '';
			$href  = self::sec_href( $action_url, $tab, $key );
			$meta  = self::section_meta_badge( $section );
			$out  .= '<a class="clubhouse-secnav__item' . $cls . '" href="' . self::esc_url( $href ) . '" data-sec="' . self::esc( $key ) . '">'
				. self::esc( (string) $section['label'] );
			if ( '' !== $meta ) {
				$out .= '<span class="clubhouse-secnav__meta">' . self::esc( $meta ) . '</span>';
			}
			$out .= '</a>';
		}
		$out .= '</nav>';
		return $out;
	}

	/** @param array<string,mixed> $section */
	private static function section_meta_badge( array $section ): string {
		if ( ! empty( $section['hidden'] ) ) {
			return 'off';
		}
		if ( 'loop' === $section['type'] ) {
			return (string) count( (array) ( $section['items'] ?? array() ) );
		}
		if ( 'auto' === $section['type'] ) {
			return 'auto';
		}
		return '';
	}

	/**
	 * Renders one section panel. Not given `role="tabpanel"`: every section
	 * panel on the page renders simultaneously (see section_nav()'s docblock),
	 * so that role would be false ARIA. The section title is a real `<h2>` —
	 * this is the only heading level below the screen's single `<h1>`.
	 *
	 * @param array<string,mixed> $section
	 */
	private static function section_panel( array $section, string $tab, bool $is_active, string $action_url ): string {
		$key         = (string) $section['key'];
		$store_page  = (string) $section['store_page'];
		$vis_page    = (string) $section['vis_page'];
		$hidden      = (bool) ( $section['hidden'] ?? false );
		$cls         = $is_active ? ' is-active' : '';

		$out  = '<div class="clubhouse-panel' . $cls . '" id="' . self::esc( self::slug_id( 'clubhouse-sec', $tab, $key ) ) . '" data-panel="' . self::esc( $key ) . '">';
		$out .= '<div class="clubhouse-panel__head">'
			. '<h2 class="clubhouse-panel__eyebrow">' . self::esc( $tab ) . ' page · ' . self::esc( strtoupper( (string) $section['label'] ) ) . '</h2>'
			. self::visibility_toggle( $vis_page, $key, $hidden )
			. '</div>';

		if ( ! empty( $section['note'] ) ) {
			$out .= '<p class="clubhouse-panel__note">' . self::esc( (string) $section['note'] ) . '</p>';
		}

		if ( ! empty( $section['fields'] ) ) {
			$out .= self::fields_grid( $section['fields'], (array) $section['values'], $store_page, $key );
		}

		if ( ! empty( $section['loop'] ) ) {
			$out .= self::loop_area( $section['loop'], (array) $section['items'], $store_page, $key );
		}

		if ( ! empty( $section['link'] ) ) {
			$out .= self::linkout_card( $section['link'], $action_url );
		}

		if ( ! empty( $section['auto'] ) ) {
			$out .= self::auto_note( $section['auto'] );
		}

		$out .= '</div>';
		return $out;
	}

	private static function visibility_toggle( string $vis_page, string $key, bool $hidden ): string {
		$name    = 'hidden[' . $vis_page . '][' . $key . ']';
		$checked = $hidden ? ' checked' : '';
		return '<label class="clubhouse-toggle clubhouse-toggle--visibility">'
			. '<input type="checkbox" name="' . self::esc( $name ) . '" value="1"' . $checked . '>'
			. '<span class="clubhouse-toggle__track"><span class="clubhouse-toggle__thumb"></span></span>'
			. '<span class="clubhouse-toggle__label">' . ( $hidden ? 'Hidden' : 'Shown' ) . '</span>'
			. '</label>';
	}

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @param array<string,mixed>            $values
	 */
	private static function fields_grid( array $fields, array $values, string $store_page, string $section_key ): string {
		$out = '<div class="clubhouse-fields">';
		foreach ( $fields as $field_def ) {
			$fkey  = (string) $field_def['key'];
			$name  = 'field[' . $store_page . '][' . $section_key . '][' . $fkey . ']';
			$value = $values[ $fkey ] ?? '';
			$out  .= self::field_row( $field_def, $name, $value );
		}
		$out .= '</div>';
		return $out;
	}

	/** @param array<string,mixed> $field_def */
	private static function field_row( array $field_def, string $name, mixed $value ): string {
		$id    = self::slug_id( 'field', $name );
		$label = (string) $field_def['label'];
		$type  = (string) $field_def['type'];

		if ( 'toggle' === $type ) {
			$checked = ( true === $value || '1' === $value || 1 === $value ) ? ' checked' : '';
			return '<label class="clubhouse-field clubhouse-field--toggle">'
				. '<input type="checkbox" id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" value="1"' . $checked . '>'
				. '<span class="clubhouse-field__label">' . self::esc( $label ) . '</span></label>';
		}

		$out = '<div class="clubhouse-field"><label class="clubhouse-label" for="' . self::esc( $id ) . '">' . self::esc( $label ) . '</label>';
		switch ( $type ) {
			case 'textarea':
				$rows = isset( $field_def['rows'] ) ? (int) $field_def['rows'] : 3;
				$out .= '<textarea id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" rows="' . $rows . '" placeholder="' . self::esc( (string) ( $field_def['placeholder'] ?? '' ) ) . '" class="clubhouse-input">'
					. self::esc( (string) $value ) . '</textarea>';
				break;
			case 'url':
				$out .= '<input type="url" id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( (string) $value ) . '" placeholder="' . self::esc( (string) ( $field_def['placeholder'] ?? '' ) ) . '" class="clubhouse-input">';
				break;
			case 'image':
				$out .= '<div class="clubhouse-media" data-media="' . self::esc( $name ) . '">'
					. '<input type="hidden" id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( (string) $value ) . '">'
					. '<span class="clubhouse-media__hint">' . ( '' !== (string) $value && '0' !== (string) $value ? 'Image set (#' . self::esc( (string) $value ) . ')' : 'No image set' ) . '</span>'
					. '<button type="button" class="clubhouse-btn clubhouse-btn--sm" data-media-pick>Choose image</button>'
					. '<button type="button" class="clubhouse-btn-link" data-media-clear>Remove</button>'
					. '</div>';
				break;
			case 'select':
				$options = (array) ( $field_def['options'] ?? array() );
				$out    .= '<select id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" class="clubhouse-input">';
				foreach ( $options as $opt_value => $opt_label ) {
					$selected = ( (string) $value === (string) $opt_value ) ? ' selected' : '';
					$out     .= '<option value="' . self::esc( (string) $opt_value ) . '"' . $selected . '>' . self::esc( (string) $opt_label ) . '</option>';
				}
				$out .= '</select>';
				break;
			default: // text
				$out .= '<input type="text" id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( (string) $value ) . '" placeholder="' . self::esc( (string) ( $field_def['placeholder'] ?? '' ) ) . '" class="clubhouse-input">';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * @param array{name:string,plural:string,fields:array<int,array<string,mixed>>} $loop_def
	 * @param array<int,array<string,mixed>>                                        $items
	 */
	private static function loop_area( array $loop_def, array $items, string $store_page, string $section_key ): string {
		$out = '<div class="clubhouse-loop">';
		foreach ( $items as $idx => $item ) {
			$out .= self::loop_item( $loop_def['fields'], (array) $item, (int) $idx, $store_page, $section_key );
		}
		$add_name = 'clubhouse_content_add[' . $store_page . '][' . $section_key . ']';
		$out     .= '<button type="submit" name="' . self::esc( $add_name ) . '" value="1" class="clubhouse-btn clubhouse-btn--sm">Add ' . self::esc( (string) $loop_def['name'] ) . '</button>';
		$out     .= '</div>';
		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $loop_fields
	 * @param array<string,mixed>            $item
	 */
	private static function loop_item( array $loop_fields, array $item, int $idx, string $store_page, string $section_key ): string {
		$out = '<div class="clubhouse-loop__item">';
		foreach ( $loop_fields as $field_def ) {
			$fkey  = (string) $field_def['key'];
			$name  = 'item[' . $store_page . '][' . $section_key . '][' . $idx . '][' . $fkey . ']';
			$value = $item[ $fkey ] ?? '';
			$out  .= self::field_row( $field_def, $name, $value );
		}
		$remove_name = 'clubhouse_content_remove[' . $store_page . '][' . $section_key . ']';
		$out        .= '<button type="submit" name="' . self::esc( $remove_name ) . '" value="' . $idx . '" class="clubhouse-btn-link clubhouse-btn-link--danger">Remove</button>';
		$out        .= '</div>';
		return $out;
	}

	/**
	 * @param array{kind:string,label:string,text:string,cpt?:string,tab?:string,sec?:string} $link
	 */
	private static function linkout_card( array $link, string $action_url ): string {
		// A 'section' link jumps to another catalogue page/section within this
		// same screen, so it must be built against this screen's own action_url
		// (like tab_href/sec_href elsewhere) — an empty base here would drop the
		// page= query param and land on a bare WordPress error page.
		$href = 'cpt' === $link['kind']
			? 'edit.php?post_type=' . (string) ( $link['cpt'] ?? '' )
			: self::sec_href( $action_url, (string) ( $link['tab'] ?? '' ), (string) ( $link['sec'] ?? '' ) );
		return '<div class="clubhouse-linkout">'
			. '<p class="clubhouse-linkout__text">' . self::esc( (string) $link['text'] ) . '</p>'
			. '<a class="clubhouse-btn clubhouse-btn--primary" href="' . self::esc_url( $href ) . '">' . self::esc( (string) $link['label'] ) . '</a>'
			. '</div>';
	}

	/** @param array{text:string,cpt?:string} $auto */
	private static function auto_note( array $auto ): string {
		$out = '<div class="clubhouse-autonote"><span class="clubhouse-badge clubhouse-badge--auto">Auto</span>'
			. '<p class="clubhouse-autonote__text">' . self::esc( (string) $auto['text'] ) . '</p>';
		if ( ! empty( $auto['cpt'] ) ) {
			$out .= '<a class="clubhouse-btn-link" href="edit.php?post_type=' . self::esc_url( (string) $auto['cpt'] ) . '">Manage →</a>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Hidden by default via the native `hidden` attribute — true on first load
	 * and with JavaScript disabled, so an owner is never told about "unsaved
	 * changes" they haven't made. Task 8's JS is expected to clear this
	 * attribute once it observes an actual edit (mirrors the project's
	 * `--js`-gated-visibility pattern used in admin-setup.css/admin-setup.js,
	 * adapted here since this markup ships ahead of its own CSS/JS bundle).
	 */
	private static function save_bar(): string {
		return '<div class="clubhouse-bar"><p class="clubhouse-bar__hint" hidden>You have unsaved changes.</p>'
			. '<button type="submit" name="clubhouse_content_submit" value="1" class="clubhouse-btn clubhouse-btn--primary">Save changes</button></div>';
	}
}
