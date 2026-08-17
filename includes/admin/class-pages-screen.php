<?php
// includes/admin/class-pages-screen.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML for Content → Pages: the site's pages down the side, and what the
 * selected one is built from. The controller supplies the model; this class
 * makes no WordPress calls, reads no request data and touches no storage.
 *
 * Every row is its own small form. A page of blocks is a list of one-click
 * decisions — take this off, put that on — not a document to fill in and
 * submit, and one big form would make an owner press Save to see anything
 * happen.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Pages_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$out  = '<div class="wrap clubhouse-wrap"><div class="clubhouse-setup">';
		$out .= self::head( (string) ( $model['role_tags'] ?? '' ) );
		$out .= self::notices( $model['notices'] );
		$out .= '<div class="clubhouse-pages">';
		$out .= self::page_list( $model['pages'] );
		$out .= '<div class="clubhouse-pages__main">';
		$out .= self::page_switch( $model );
		$out .= self::blocks( $model );
		$out .= self::add_form( $model );
		$out .= '</div></div></div></div>';
		return $out;
	}

	private static function head( string $role_tags ): string {
		return '<header class="clubhouse-head"><div class="clubhouse-head__titles">'
			. '<p class="clubhouse-eyebrow">Clubhouse · Content</p>'
			. '<h1 class="clubhouse-head__h1">Pages</h1></div>' . $role_tags . '</header>'
			. '<p class="clubhouse-step__lede">What each page of your site is made of. Take a block off a page, '
			. 'or put another one on — the words inside a block are edited under Blocks.</p>';
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

	/** @param array<int,array{slug:string,label:string,enabled:bool,current:bool,url:string}> $pages */
	private static function page_list( array $pages ): string {
		$out = '<nav class="clubhouse-pages__list" aria-label="Pages">';
		foreach ( $pages as $page ) {
			$classes = 'clubhouse-tab clubhouse-pages__page' . ( $page['current'] ? ' is-current' : '' );
			$out    .= '<a class="' . $classes . '" href="' . self::esc( $page['url'] ) . '"'
				. ( $page['current'] ? ' aria-current="page"' : '' ) . '>'
				. self::esc( $page['label'] )
				. ( $page['enabled'] ? '' : '<span class="clubhouse-table__sub">Off the site</span>' )
				. '</a>';
		}
		return $out . '</nav>';
	}

	/** @param array<string,mixed> $model */
	private static function page_switch( array $model ): string {
		$current = $model['current'];
		$on      = (bool) $current['enabled'];

		return '<div class="clubhouse-step"><p class="clubhouse-step__k">' . self::esc( (string) $current['label'] ) . '</p>'
			. '<h2 class="clubhouse-step__h">Is this page on the site?</h2>'
			. '<p class="clubhouse-help">A page that is off keeps everything on it. Its address simply stops '
			. 'working, and it leaves the menu.</p>'
			. self::form( $model )
			. self::hidden( 'clubhouse_pages_page', (string) $current['slug'] )
			. self::hidden( 'clubhouse_pages_switch', '1' )
			. '<label class="clubhouse-label"><input type="checkbox" name="clubhouse_page_enabled" value="1"'
			. ( $on ? ' checked' : '' ) . '> On the site</label> '
			. '<button type="submit" name="clubhouse_pages_submit" value="1" class="clubhouse-btn clubhouse-btn--sm">Save</button>'
			. '</form></div>';
	}

	/** @param array<string,mixed> $model */
	private static function blocks( array $model ): string {
		$out = '<div class="clubhouse-step"><p class="clubhouse-step__k">Blocks</p>'
			. '<h2 class="clubhouse-step__h">What this page shows, top to bottom</h2>'
			. '<table class="clubhouse-table"><thead><tr>'
			. '<th scope="col">Block</th><th scope="col">Kind</th><th scope="col">Also on</th><th scope="col">Actions</th>'
			. '</tr></thead><tbody>';

		$out .= self::pinned_row( $model['pinned_top'], (string) $model['blocks_url'], 'Top of every page' );

		if ( array() === $model['rows'] ) {
			$out .= '<tr><td colspan="4" class="clubhouse-table__none">Nothing on this page yet. '
				. 'Add a block below.</td></tr>';
		}
		foreach ( $model['rows'] as $row ) {
			$out .= self::block_row( $row, $model );
		}

		$out .= self::pinned_row( $model['pinned_bottom'], (string) $model['blocks_url'], 'Foot of every page' );

		return $out . '</tbody></table></div>';
	}

	/**
	 * @param array{id:string,name:string,type_label:string} $block
	 */
	private static function pinned_row( array $block, string $blocks_url, string $note ): string {
		return '<tr><th scope="row"><span class="clubhouse-table__name">' . self::esc( $block['name'] ) . '</span>'
			. '<span class="clubhouse-table__sub">' . self::esc( $note ) . '</span></th>'
			. '<td>' . self::esc( $block['type_label'] ) . '</td>'
			. '<td class="clubhouse-table__none">Every page</td>'
			. '<td>' . self::edit_link( $block['id'], $blocks_url ) . '</td></tr>';
	}

	/**
	 * @param array{id:string,name:string,type:string,type_label:string,shared_with:array<int,string>} $row
	 * @param array<string,mixed> $model
	 */
	private static function block_row( array $row, array $model ): string {
		$shared = array() === $row['shared_with']
			? '<span class="clubhouse-table__none">This page only</span>'
			: self::chips( $row['shared_with'] );

		$remove = self::form( $model )
			. self::hidden( 'clubhouse_pages_page', (string) $model['current']['slug'] )
			. self::hidden( 'clubhouse_pages_remove', $row['id'] )
			. '<button type="submit" name="clubhouse_pages_submit" value="1" class="clubhouse-btn clubhouse-btn--sm">'
			. 'Remove<span class="screen-reader-text"> ' . self::esc( $row['name'] ) . ' from this page</span></button>'
			. '</form>';

		return '<tr><th scope="row"><span class="clubhouse-table__name">' . self::esc( $row['name'] ) . '</span></th>'
			. '<td>' . self::esc( $row['type_label'] ) . '</td>'
			. '<td>' . $shared . '</td>'
			. '<td>' . self::edit_link( $row['id'], (string) $model['blocks_url'] ) . ' ' . $remove . '</td></tr>';
	}

	private static function edit_link( string $id, string $blocks_url ): string {
		if ( '' === $id ) {
			return '';
		}
		return '<a class="clubhouse-btn clubhouse-btn--sm" href="'
			. self::esc( $blocks_url . '&block=' . rawurlencode( $id ) ) . '">Edit</a>';
	}

	/** @param array<string,mixed> $model */
	private static function add_form( array $model ): string {
		$out = '<div class="clubhouse-step"><p class="clubhouse-step__k">Add</p>'
			. '<h2 class="clubhouse-step__h">Put another block on this page</h2>'
			. '<p class="clubhouse-help">Pick a block you already have to show the same words in both places — '
			. 'edit it once and every page using it follows. Or make a new one of that kind.</p>'
			. self::form( $model )
			. self::hidden( 'clubhouse_pages_page', (string) $model['current']['slug'] )
			. '<label class="clubhouse-label" for="clubhouse-pages-add">Block</label>'
			. '<select class="clubhouse-input" id="clubhouse-pages-add" name="clubhouse_pages_add">';

		foreach ( $model['picker'] as $group ) {
			$out .= '<optgroup label="' . self::esc( (string) $group['label'] ) . '">';
			foreach ( $group['existing'] as $block ) {
				$out .= '<option value="have:' . self::esc( (string) $block['id'] ) . '">'
					. self::esc( (string) $block['name'] ) . '</option>';
			}
			$out .= '<option value="new:' . self::esc( (string) $group['type'] ) . '">'
				. self::esc( (string) $group['new_label'] ) . '</option></optgroup>';
		}

		return $out . '</select> '
			. '<button type="submit" name="clubhouse_pages_submit" value="1" class="clubhouse-btn clubhouse-btn--primary clubhouse-btn--sm">Add to this page</button>'
			. '</form></div>';
	}

	/**
	 * A form posting back to the page being edited, not to the screen's default.
	 * Without the page in the action, acting on About would land the owner back
	 * on Home and leave them wondering whether it worked.
	 *
	 * @param array<string,mixed> $model
	 */
	private static function form( array $model ): string {
		$action = (string) $model['action_url'] . '&club_page=' . rawurlencode( (string) $model['current']['slug'] );
		return '<form method="post" action="' . self::esc( $action ) . '" class="clubhouse-form">'
			. (string) $model['nonce_field'];
	}

	private static function hidden( string $name, string $value ): string {
		return '<input type="hidden" name="' . self::esc( $name ) . '" value="' . self::esc( $value ) . '">';
	}

	/** @param array<int,string> $labels */
	private static function chips( array $labels ): string {
		$out = '<span class="clubhouse-chips">';
		foreach ( $labels as $label ) {
			$out .= '<span class="clubhouse-roletag">' . self::esc( $label ) . '</span>';
		}
		return $out . '</span>';
	}
}
