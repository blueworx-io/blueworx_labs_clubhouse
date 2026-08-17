<?php
// includes/admin/class-blocks-screen.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML for Content → Blocks: the club's library down the side, grouped by
 * kind, and the chosen block's form beside it. The controller supplies the
 * model; this class makes no WordPress calls, reads no request data and touches
 * no storage.
 *
 * The individual field controls are drawn by Content_Screen::field_html — the
 * same markup, the same media picker and the same link suggestions Club Pages
 * has always had, which is what makes this screen feel like the one it replaces
 * rather than a second, poorer editor.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Blocks_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Look tokens, raw inside a <style> block — see Pages_Screen for why they are
	 * not entity-escaped.
	 *
	 * @param array<string,string> $tokens
	 */
	private static function css_tokens( array $tokens ): string {
		$out = '';
		foreach ( $tokens as $name => $value ) {
			$out .= str_replace( array( '<', '}' ), '', (string) $name )
				. ':' . str_replace( array( '<', '}' ), '', (string) $value ) . ';';
		}
		return $out;
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$out  = '<div class="wrap clubhouse-wrap">';
		$out .= '<style>' . (string) ( $model['font_face_css'] ?? '' )
			. '.clubhouse-setup{' . self::css_tokens( (array) ( $model['tokens'] ?? array() ) ) . '}</style>';
		$out .= '<div class="clubhouse-setup">';
		$out .= self::head( (string) ( $model['role_tags'] ?? '' ) );
		$out .= self::notices( $model['notices'] );
		$out .= '<div class="clubhouse-pages">';
		$out .= self::library( $model );
		$out .= '<div class="clubhouse-pages__main">';
		$out .= self::confirm( $model );
		$out .= self::form( $model );
		$out .= '</div></div>';
		$out .= self::links_datalist( (array) ( $model['menu_targets'] ?? array() ) );
		$out .= '</div></div>';
		return $out;
	}

	private static function head( string $role_tags ): string {
		return '<header class="clubhouse-head"><div class="clubhouse-head__titles">'
			. '<p class="clubhouse-eyebrow">Clubhouse · Content</p>'
			. '<h1 class="clubhouse-head__h1">Blocks</h1></div>' . $role_tags . '</header>'
			. '<p class="clubhouse-step__lede">Every block your site is built from, and what each one says. '
			. 'A block used on more than one page is edited once, and every page using it follows.</p>';
	}

	/** @param array<int,array{type:string,text:string}> $notices */
	private static function notices( array $notices ): string {
		$out = '';
		foreach ( $notices as $notice ) {
			$out .= '<div class="notice notice-' . self::esc( (string) $notice['type'] ) . ' is-dismissible"><p>'
				. self::esc( (string) $notice['text'] ) . '</p></div>';
		}
		return $out;
	}

	/** @param array<string,mixed> $model */
	private static function library( array $model ): string {
		$out = '<nav class="clubhouse-pages__list" aria-label="Blocks">';
		foreach ( $model['groups'] as $group ) {
			$out .= '<p class="clubhouse-step__k clubhouse-blocks__kind">' . self::esc( (string) $group['label'] ) . '</p>';
			foreach ( $group['blocks'] as $block ) {
				// The header and footer are on no page's list because they are on
				// every page. Saying "on no page" would read as an orphan.
				$used = $block['singleton']
					? 'Every page'
					: ( array() === $block['used_on'] ? 'On no page' : implode( ', ', $block['used_on'] ) );
				$out .= '<a class="clubhouse-tab clubhouse-pages__page' . ( $block['current'] ? ' is-current' : '' ) . '"'
					. ' href="' . self::esc( (string) $block['url'] ) . '"'
					. ( $block['current'] ? ' aria-current="page"' : '' ) . '>'
					. self::esc( (string) $block['name'] )
					. '<span class="clubhouse-table__sub">' . self::esc( $used ) . '</span></a>';
			}
		}
		return $out . self::new_block( $model ) . '</nav>';
	}

	/** @param array<string,mixed> $model */
	private static function new_block( array $model ): string {
		$out = '<div class="clubhouse-blocks__new">' . self::open_form( $model )
			. '<label class="clubhouse-label" for="clubhouse-blocks-new">Make a block</label>'
			. '<select class="clubhouse-input" id="clubhouse-blocks-new" name="clubhouse_blocks_new">';
		foreach ( $model['new_types'] as $type ) {
			$out .= '<option value="' . self::esc( (string) $type['type'] ) . '">' . self::esc( (string) $type['label'] ) . '</option>';
		}
		return $out . '</select> '
			. self::button( 'Make', 'clubhouse-btn clubhouse-btn--sm' )
			. '</form></div>';
	}

	/** @param array<string,mixed> $model */
	private static function confirm( array $model ): string {
		$confirm = $model['confirm'] ?? null;
		if ( null === $confirm ) {
			return '';
		}
		$out = '<div class="clubhouse-step clubhouse-step--warn"><p class="clubhouse-step__k">Careful</p>'
			. '<h2 class="clubhouse-step__h">Delete &ldquo;' . self::esc( (string) $confirm['name'] ) . '&rdquo;?</h2>'
			. '<p class="clubhouse-help">It is on ' . self::esc( implode( ', ', $confirm['used_on'] ) )
			. '. Deleting it takes it off ' . ( count( $confirm['used_on'] ) > 1 ? 'those pages' : 'that page' )
			. ', and its words go with it. To keep the words but clear the page, take it off under Content &rarr; Pages instead.</p>'
			. self::open_form( $model )
			. self::hidden( 'clubhouse_blocks_block', (string) $confirm['id'] )
			. self::hidden( 'clubhouse_blocks_confirm', '1' )
			. self::button( 'Yes, delete it', 'clubhouse-btn clubhouse-btn--sm', 'clubhouse_blocks_delete' )
			. '</form> <a class="clubhouse-btn clubhouse-btn--sm" href="'
			. self::esc( (string) $model['action_url'] . '&block=' . rawurlencode( (string) $confirm['id'] ) ) . '">Keep it</a>'
			. '</div>';
		return $out;
	}

	/** @param array<string,mixed> $model */
	private static function form( array $model ): string {
		$block = $model['current'] ?? null;
		if ( null === $block ) {
			return '<div class="clubhouse-step"><p class="clubhouse-step__k">Blocks</p>'
				. '<h2 class="clubhouse-step__h">Pick a block to edit</h2>'
				. '<p class="clubhouse-help">Choose one from the list, or make a new one. '
				. 'Putting a block on a page is done under Content &rarr; Pages.</p></div>';
		}

		$out = '<div class="clubhouse-step"><p class="clubhouse-step__k">'
			. self::esc( (string) $block['type_label'] ) . '</p>'
			. '<h2 class="clubhouse-step__h">' . self::esc( (string) $block['name'] ) . '</h2>'
			. self::used_on( $block, (string) $model['pages_url'] )
			. self::open_form( $model )
			. self::hidden( 'clubhouse_blocks_block', (string) $block['id'] )
			. '<div class="clubhouse-field"><label class="clubhouse-label" for="clubhouse-blocks-name">Name</label>'
			. '<input type="text" id="clubhouse-blocks-name" name="clubhouse_blocks_name" class="clubhouse-input" value="'
			. self::esc( (string) $block['name'] ) . '">'
			. '<p class="clubhouse-help">Only you see this. It is how the block is listed here and on Pages.</p></div>';

		$out .= self::fields( $block );
		$out .= self::loop( $block );

		$out .= '<div class="clubhouse-bar">' . self::button( 'Save', 'clubhouse-btn clubhouse-btn--primary' );
		if ( ! $block['singleton'] ) {
			$out .= ' ' . self::button( 'Duplicate', 'clubhouse-btn clubhouse-btn--sm', 'clubhouse_blocks_duplicate' )
				. ' ' . self::button( 'Delete', 'clubhouse-btn clubhouse-btn--sm', 'clubhouse_blocks_delete' );
		}
		$out .= '</div></form></div>';

		return $out;
	}

	/**
	 * Who else this form is about to change. Said before the fields rather than
	 * after them, because an owner who is about to retype a heading needs to
	 * know it is the heading on three pages before they start.
	 *
	 * @param array<string,mixed> $block
	 */
	private static function used_on( array $block, string $pages_url ): string {
		if ( $block['singleton'] ) {
			return '<p class="clubhouse-help clubhouse-blocks__used">Shown on every page of your site.</p>';
		}
		if ( array() === $block['used_on'] ) {
			return '<p class="clubhouse-help clubhouse-blocks__used">Not on any page yet. Put it on one under '
				. '<a href="' . self::esc( $pages_url ) . '">Content &rarr; Pages</a>.</p>';
		}
		if ( count( $block['used_on'] ) === 1 ) {
			return '<p class="clubhouse-help clubhouse-blocks__used">On ' . self::esc( $block['used_on'][0] ) . '.</p>';
		}
		return '<p class="clubhouse-help clubhouse-blocks__used clubhouse-help--strong">Shared: this block is on '
			. self::esc( implode( ', ', $block['used_on'] ) )
			. '. Changing it changes all of them. To make one of them differ, duplicate it below.</p>';
	}

	/** @param array<string,mixed> $block */
	private static function fields( array $block ): string {
		if ( array() === $block['fields'] ) {
			return '';
		}
		$out = '<div class="clubhouse-fields">';
		foreach ( $block['fields'] as $field ) {
			$key  = (string) $field['key'];
			$out .= Blueworx_Clubhouse_Content_Screen::field_html(
				$field,
				$block['content'][ $key ] ?? null,
				'field[' . $key . ']'
			);
		}
		return $out . '</div>';
	}

	/** @param array<string,mixed> $block */
	private static function loop( array $block ): string {
		$loop = $block['loop'] ?? null;
		if ( null === $loop ) {
			return '';
		}
		$items = is_array( $block['content']['items'] ?? null ) ? array_values( (array) $block['content']['items'] ) : array();

		$out = '<h3 class="clubhouse-step__h">' . self::esc( (string) $loop['plural'] ) . '</h3><div class="clubhouse-loop">';
		foreach ( $items as $index => $item ) {
			$out .= '<div class="clubhouse-loop__item">';
			foreach ( $loop['fields'] as $field ) {
				$key  = (string) $field['key'];
				$out .= Blueworx_Clubhouse_Content_Screen::field_html(
					$field,
					is_array( $item ) ? ( $item[ $key ] ?? null ) : null,
					'item[' . (int) $index . '][' . $key . ']'
				);
			}
			$out .= '<button type="submit" name="clubhouse_blocks_remove_item" value="' . (int) $index
				. '" class="clubhouse-btn-link clubhouse-btn-link--danger">Remove</button></div>';
		}
		return $out . '<button type="submit" name="clubhouse_blocks_add_item" value="1" class="clubhouse-btn clubhouse-btn--sm">Add '
			. self::esc( (string) $loop['name'] ) . '</button></div>';
	}

	/**
	 * The suggestion list every URL field points at — the same one Club Pages
	 * offers, so a link an owner can pick there is a link they can pick here.
	 *
	 * @param array<int,array{target:string,label:string,group:string,url:string}> $targets
	 */
	private static function links_datalist( array $targets ): string {
		$out  = '<datalist id="clubhouse-links">';
		$seen = array();
		foreach ( $targets as $entry ) {
			if ( '' === $entry['url'] || in_array( $entry['url'], $seen, true ) ) {
				continue;
			}
			$seen[] = $entry['url'];
			$out   .= '<option value="' . self::esc( $entry['url'] ) . '" label="' . self::esc( $entry['label'] ) . '">'
				. self::esc( $entry['label'] ) . '</option>';
		}
		return $out . '</datalist>';
	}

	/**
	 * The submit marker is a hidden field rather than the button's own name, so
	 * each button is free to carry the action it performs. A button carrying both
	 * is not possible, and putting the actions in hidden fields instead would
	 * submit every one of them whatever was pressed — Save would also delete.
	 *
	 * @param array<string,mixed> $model
	 */
	private static function open_form( array $model ): string {
		return '<form method="post" action="' . self::esc( (string) $model['action_url'] ) . '" class="clubhouse-form">'
			. (string) $model['nonce_field']
			. self::hidden( 'clubhouse_blocks_submit', '1' );
	}

	private static function button( string $label, string $class, string $name = '' ): string {
		$attr = '' === $name ? '' : ' name="' . self::esc( $name ) . '" value="1"';
		return '<button type="submit"' . $attr . ' class="' . $class . '">' . self::esc( $label ) . '</button>';
	}

	private static function hidden( string $name, string $value ): string {
		return '<input type="hidden" name="' . self::esc( $name ) . '" value="' . self::esc( $value ) . '">';
	}
}
