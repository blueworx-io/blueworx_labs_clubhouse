<?php
// includes/admin/class-menu-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The header menu's own save handler. Lives on the Setup screen's Menu tab,
 * which is where an owner edits it — but the plumbing was Club Pages' until
 * that screen was deleted, so the menu now owns it outright.
 *
 * The posted field names still read "content" (clubhouse_content_submit,
 * clubhouse_content_tab, and the nonce action clubhouse_content_save). They
 * are wire names, not descriptions: renaming them would break a form already
 * open in somebody's browser and change nothing an owner can see.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Menu_Controller {

	public const NONCE = 'clubhouse_content_save';

	/** The Menu tab's slug, as posted in clubhouse_content_tab. */
	public const TAB = 'menu';

	/**
	 * Apply a posted menu form. Returns notices, mirroring
	 * Setup_Controller::handle_save().
	 *
	 * @param array<string,mixed> $post
	 * @return array<int,array{type:string,text:string}>
	 */
	public static function handle_save( array $post, Blueworx_Clubhouse_Storage $storage ): array {
		if ( self::TAB !== ( isset( $post['clubhouse_content_tab'] ) ? (string) $post['clubhouse_content_tab'] : '' ) ) {
			return array();
		}
		return self::save_menu( $post, $storage );
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

	/** @return array<int|string,mixed> */
	private static function as_array( mixed $value ): array {
		return is_array( $value ) ? $value : array();
	}
}
