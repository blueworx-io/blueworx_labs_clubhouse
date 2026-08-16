<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Menu tab of the Clubhouse screen: the nav tree as a list of rows, each
 * a label, a target picker and the buttons that move it.
 *
 * Order and nesting live in the field names (menu[0][children][1][label]), so
 * the submitted form *is* the tree — no client-side state, and reordering works
 * with JavaScript off. Pure string building, like Content_Screen; the
 * controller supplies the model and handles the post.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Menu_Panel {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * The two screens tab in different ways — Club Pages uses
	 * `.clubhouse-pagepanel[data-pagepanel]`, the Clubhouse screen
	 * `.clubhouse-panel[data-panel]` — so the host names the shell it needs and
	 * the panel's contents stay identical wherever it is shown.
	 *
	 * @param array{tree:array<int,array<string,mixed>>,targets:array<int,array{target:string,label:string,group:string,url:string}>,action_url:string,nonce_field:string,panel_class?:string,panel_attr?:string} $model
	 */
	public static function render( array $model ): string {
		$tree  = $model['tree'];
		$class = (string) ( $model['panel_class'] ?? 'clubhouse-pagepanel is-active' );
		$attr  = (string) ( $model['panel_attr'] ?? 'data-pagepanel="menu"' );

		$out  = '<section class="' . self::esc( $class ) . '" id="clubhouse-tab-menu" ' . $attr . ' role="tabpanel">';
		$out .= '<form method="post" action="' . self::esc( (string) $model['action_url'] ) . '" class="clubhouse-form">';
		$out .= (string) $model['nonce_field'];
		$out .= '<input type="hidden" name="clubhouse_content_tab" value="menu">';
		// Same reason as Content_Screen's: only the activated submit button
		// contributes its name, so a Move/Remove click carries no submit key.
		$out .= '<input type="hidden" name="clubhouse_content_submit" value="1">';
		$out .= '<div class="clubhouse-body"><div class="clubhouse-panels">';
		$out .= '<div class="clubhouse-section"><h2 class="clubhouse-section__h2">Header menu</h2>';
		$out .= '<p class="clubhouse-help">The order here is the order in the site header. Indent an item to hang it under the one above.</p>';
		$out .= '<ol class="clubhouse-menu">';

		foreach ( $tree as $i => $row ) {
			$out .= self::row( $model, (string) $row['label'], (string) $row['target'], (string) $i, false, 0 === $i, count( $tree ) - 1 === $i );
			$children = is_array( $row['children'] ?? null ) ? $row['children'] : array();
			foreach ( $children as $j => $child ) {
				$out .= self::row(
					$model,
					(string) $child['label'],
					(string) $child['target'],
					$i . '-' . $j,
					true,
					0 === $j,
					count( $children ) - 1 === $j
				);
			}
		}

		$out .= '</ol>';
		$out .= '<p><button type="submit" class="button" name="clubhouse_menu_add" value="1">Add item</button></p>';
		$out .= '</div></div></div>';
		// The same save bar as every other Clubhouse screen: its own button, not
		// WordPress's, and pinned right so Save is always in the same place.
		$out .= '<div class="clubhouse-bar">'
			. '<button type="submit" name="clubhouse_content_submit" value="1" class="clubhouse-btn clubhouse-btn--primary">Save menu</button></div>';
		$out .= '</form></section>';
		return $out;
	}

	/**
	 * One row. $path is '<i>' or '<i>-<j>'; the field-name prefix is derived
	 * from it so the names and the button paths can never disagree.
	 */
	private static function row(
		array $model,
		string $label,
		string $target,
		string $path,
		bool $is_child,
		bool $is_first,
		bool $is_last
	): string {
		$parts  = explode( '-', $path );
		$prefix = $is_child
			? 'menu[' . $parts[0] . '][children][' . $parts[1] . ']'
			: 'menu[' . $parts[0] . ']';

		$known  = self::is_known( $model['targets'], $target );
		$custom = 0 === strpos( $target, 'url:' ) ? substr( $target, 4 ) : '';

		$out  = '<li class="clubhouse-menu__row' . ( $is_child ? ' clubhouse-menu__row--child' : '' ) . '">';
		$out .= '<input type="text" class="clubhouse-input" name="' . self::esc( $prefix . '[label]' ) . '" value="' . self::esc( $label ) . '" aria-label="Menu item label">';
		$out .= self::picker( $model['targets'], $prefix . '[target]', $target );
		$out .= '<input type="url" class="clubhouse-input clubhouse-menu__custom" name="' . self::esc( $prefix . '[custom]' ) . '" value="' . self::esc( $custom ) . '" placeholder="https://…" aria-label="Custom URL">';

		if ( ! $known && '' === $custom ) {
			$out .= '<span class="clubhouse-menu__warn">target unavailable</span>';
		}

		$out .= self::button( 'clubhouse_menu_up', $path, 'Move up', '↑', $is_first );
		$out .= self::button( 'clubhouse_menu_down', $path, 'Move down', '↓', $is_last );
		$out .= self::button( 'clubhouse_menu_indent', $path, 'Indent', '→', $is_child || ( ! $is_child && '0' === $path ) );
		$out .= self::button( 'clubhouse_menu_outdent', $path, 'Outdent', '←', ! $is_child );
		$out .= self::button( 'clubhouse_menu_remove', $path, 'Remove', '✕', false );
		$out .= '</li>';
		return $out;
	}

	private static function button( string $name, string $path, string $title, string $glyph, bool $disabled ): string {
		return '<button type="submit" class="button clubhouse-menu__btn" name="' . self::esc( $name . '[' . $path . ']' ) . '" value="1"'
			. ' title="' . self::esc( $title ) . '" aria-label="' . self::esc( $title ) . '"'
			. ( $disabled ? ' disabled' : '' ) . '>' . self::esc( $glyph ) . '</button>';
	}

	/**
	 * The target picker: every catalogue entry, grouped, plus a "Custom URL"
	 * option whose value is the bare 'url:' tag — the adjacent text input
	 * carries the address.
	 *
	 * @param array<int,array{target:string,label:string,group:string,url:string}> $targets
	 */
	private static function picker( array $targets, string $name, string $selected ): string {
		$is_custom = 0 === strpos( $selected, 'url:' );
		$known     = self::is_known( $targets, $selected );

		$out   = '<select class="clubhouse-input" name="' . self::esc( $name ) . '" aria-label="Links to">';
		$group = '';
		foreach ( $targets as $entry ) {
			if ( $entry['group'] !== $group ) {
				$out  .= '' === $group ? '' : '</optgroup>';
				$group = $entry['group'];
				$out  .= '<optgroup label="' . self::esc( $group ) . '">';
			}
			$sel  = ( ! $is_custom && $entry['target'] === $selected ) ? ' selected' : '';
			$out .= '<option value="' . self::esc( $entry['target'] ) . '"' . $sel . '>' . self::esc( $entry['label'] ) . '</option>';
		}
		$out .= '' === $group ? '' : '</optgroup>';
		if ( ! $is_custom && '' !== $selected && ! $known ) {
			// The stored target no longer resolves to any catalogue entry (its
			// page was deleted, or an integration it depended on is gone). Emit
			// it as its own selected option — not just the "target unavailable"
			// flag — so that re-saving this form (for any reason, on any row)
			// posts back the same target instead of the browser's preselected
			// first option silently overwriting it.
			$out .= '<option value="' . self::esc( $selected ) . '" selected>' . self::esc( $selected ) . ' (unavailable)</option>';
		}
		$out .= '<option value="url:"' . ( $is_custom ? ' selected' : '' ) . '>Custom URL…</option>';
		return $out . '</select>';
	}

	/** @param array<int,array{target:string,label:string,group:string,url:string}> $targets */
	private static function is_known( array $targets, string $target ): bool {
		foreach ( $targets as $entry ) {
			if ( $entry['target'] === $target ) {
				return true;
			}
		}
		return false;
	}
}
