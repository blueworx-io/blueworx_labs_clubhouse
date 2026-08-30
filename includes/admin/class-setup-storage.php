<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Setup screen's own read and write, supplied to the page editor library
 * in place of a store.
 *
 * Setup's values were never one option. They live in the look registry, in
 * Branding, Visibility, Menu, Profile_Store, Auth_Settings, Mail_Settings and
 * Demo_State — each with its own sanitising and its own side effects, like a
 * page's status following its switch. An OptionStore would have made a second
 * copy of every one of them, so the screen brings its own pair of callbacks
 * instead: the library keeps the schema, the capability filtering, the
 * validation and the save bar, and this class keeps the stores.
 *
 * That is also why phase 4 has no migration. Nothing moves.
 *
 * Every write is guarded by array_key_exists, not by a truthiness test: a
 * Content Editor's save carries the menu and nothing else, because the library
 * has already dropped every field they may not write, and an unguarded write
 * would blank the rest of the screen on their behalf.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Storage {

	public function __construct( private Blueworx_Clubhouse_Storage $storage ) {}

	/**
	 * Every field id on the screen, with the value the store that owns it
	 * currently holds.
	 *
	 * @return array<string,mixed>
	 */
	public function read(): array {
		$branding = new Blueworx_Clubhouse_Branding( $this->storage );
		$auth     = new Blueworx_Clubhouse_Auth_Settings( $this->storage );
		$mail     = new Blueworx_Clubhouse_Mail_Settings( $this->storage );
		$active   = Blueworx_Clubhouse_Frontend::registry( $this->storage )->active();

		$values = array(
			'look'              => null !== $active ? $active->slug() : '',
			'club_name'         => $branding->get_club_name(),
			'accent'            => $branding->get_accent(),
			'secondary'         => $branding->get_secondary(),
			'logo'              => $branding->get_logo(),
			'favicon'           => $branding->get_favicon(),
			'facebook'          => $branding->get_facebook_url(),
			'instagram'         => $branding->get_instagram_url(),
			'linkedin'          => $branding->get_linkedin_url(),
			'x'                 => $branding->get_x_url(),
			'post_login'        => $auth->get_post_login(),
			'post_logout'       => $auth->get_post_logout(),
			'mail_from_name'    => $mail->get_from_name(),
			'mail_from_address' => $mail->get_from_address(),
			'menu'              => self::menu_rows( new Blueworx_Clubhouse_Menu( $this->storage ) ),
			'profile_fields'    => ( new Blueworx_Clubhouse_Profile_Store( $this->storage ) )->fields(),
			'demo_active'       => ( new Blueworx_Clubhouse_Demo_State( $this->storage ) )->is_on(),
		);

		// The page's own status is the fact; the stored flag is the fallback
		// for a site whose pages have not been created yet. See
		// Visibility::page_status_is_visible().
		$vis = new Blueworx_Clubhouse_Visibility( $this->storage );
		foreach ( Blueworx_Clubhouse_Setup_Editor::pages() as $page ) {
			$status = $vis->page_status_is_visible( $page['page'] );
			$values[ Blueworx_Clubhouse_Setup_Fields::PAGE_FIELD_PREFIX . $page['page'] ]
				= null === $status ? $vis->is_page_visible( $page['page'] ) : $status;
		}

		return $values;
	}

	/**
	 * @param array<string,mixed> $values
	 */
	public function write( array $values ): bool {
		$branding = new Blueworx_Clubhouse_Branding( $this->storage );

		if ( array_key_exists( 'look', $values ) ) {
			$registry = Blueworx_Clubhouse_Frontend::registry( $this->storage );
			if ( $registry->has( (string) $values['look'] ) ) {
				$registry->set_active( (string) $values['look'] );
			}
		}

		foreach ( array(
			'accent'    => 'set_accent',
			'secondary' => 'set_secondary',
			'club_name' => 'set_club_name',
			'logo'      => 'set_logo',
			'favicon'   => 'set_favicon',
			'facebook'  => 'set_facebook_url',
			'instagram' => 'set_instagram_url',
			'linkedin'  => 'set_linkedin_url',
			'x'         => 'set_x_url',
		) as $id => $setter ) {
			if ( array_key_exists( $id, $values ) ) {
				$branding->{$setter}( (string) $values[ $id ] );
			}
		}

		$auth = new Blueworx_Clubhouse_Auth_Settings( $this->storage );
		if ( array_key_exists( 'post_login', $values ) ) {
			$auth->set_post_login( (string) $values['post_login'] );
		}
		if ( array_key_exists( 'post_logout', $values ) ) {
			$auth->set_post_logout( (string) $values['post_logout'] );
		}

		$mail = new Blueworx_Clubhouse_Mail_Settings( $this->storage );
		if ( array_key_exists( 'mail_from_name', $values ) ) {
			$mail->set_from_name( (string) $values['mail_from_name'] );
		}
		if ( array_key_exists( 'mail_from_address', $values ) ) {
			$mail->set_from_address( (string) $values['mail_from_address'] );
		}

		$vis = new Blueworx_Clubhouse_Visibility( $this->storage );
		foreach ( Blueworx_Clubhouse_Setup_Editor::pages() as $page ) {
			$id = Blueworx_Clubhouse_Setup_Fields::PAGE_FIELD_PREFIX . $page['page'];
			if ( array_key_exists( $id, $values ) ) {
				$vis->set_page_visible( $page['page'], (bool) $values[ $id ] );
			}
		}

		if ( array_key_exists( 'menu', $values ) && is_array( $values['menu'] ) ) {
			( new Blueworx_Clubhouse_Menu( $this->storage ) )->save( self::menu_tree( $values['menu'] ) );
		}

		// A removed row takes the question off the form and nothing else. It
		// never erases what members already answered — a row that can be
		// removed by accident must not be able to wipe member data.
		// Profile_Store::forget() still does that, deliberately, elsewhere.
		if ( array_key_exists( 'profile_fields', $values ) && is_array( $values['profile_fields'] ) ) {
			( new Blueworx_Clubhouse_Profile_Store( $this->storage ) )->save_fields( $values['profile_fields'] );
		}

		if ( array_key_exists( 'demo_active', $values ) ) {
			( new Blueworx_Clubhouse_Demo_State( $this->storage ) )->set( (bool) $values['demo_active'] );
		}

		// The composed :root cache holds the look and the accent, so it has to
		// go whatever was saved — a club that changed only its accent would
		// otherwise keep seeing the old one.
		( new Blueworx_Clubhouse_Theme_Cache( $this->storage ) )->invalidate();

		return true;
	}

	/**
	 * The two-level menu tree, flattened to rows the library's repeater can
	 * hold. Indent is a flag on the row — a child is the row after its parent
	 * with `nested` on — which is what the old drag handle meant anyway.
	 *
	 * @return array<int,array{label:string,target:string,nested:bool}>
	 */
	private static function menu_rows( Blueworx_Clubhouse_Menu $menu ): array {
		$rows = array();
		foreach ( $menu->tree() as $item ) {
			$rows[] = array( 'label' => $item['label'], 'target' => $item['target'], 'nested' => false );
			foreach ( $item['children'] as $child ) {
				$rows[] = array( 'label' => $child['label'], 'target' => $child['target'], 'nested' => true );
			}
		}
		return $rows;
	}

	/**
	 * Rows back to a tree. A nested row with nothing above it is promoted
	 * rather than dropped: an owner who over-indented should lose the nesting,
	 * not the item — the same rule Menu::sanitise() already applies to a third
	 * level.
	 *
	 * @param array<int,mixed> $rows
	 * @return array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	private static function menu_tree( array $rows ): array {
		$tree = array();
		foreach ( $rows as $row ) {
			$row  = (array) $row;
			$item = array(
				'label'  => (string) ( $row['label'] ?? '' ),
				'target' => (string) ( $row['target'] ?? '' ),
			);
			if ( ! empty( $row['nested'] ) && array() !== $tree ) {
				$tree[ array_key_last( $tree ) ]['children'][] = $item;
				continue;
			}
			$item['children'] = array();
			$tree[]           = $item;
		}
		return $tree;
	}
}
