<?php
// includes/admin/class-access-screen.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML for the two admin-only access surfaces:
 *
 *   - the read-only "ClubHouse access" Settings page, listing every ClubHouse
 *     user and the sections each can reach; and
 *   - the role tags that sit in the top bar of every ClubHouse admin page.
 *
 * Read-only throughout. There is no form, no nonce and no action URL, because
 * nothing here can be changed from here — roles are managed on the Users
 * screen, and a page that both reports access and edits it invites somebody to
 * grant it by accident. The controller supplies the model; this class makes no
 * WordPress calls and touches no storage.
 *
 * Styling follows the existing Clubhouse admin screens (clubhouse-wrap,
 * clubhouse-head) so it reads as part of the same product rather than a bare
 * core table.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Access_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * The role tags for a page's top bar: one chip per role that can reach it.
	 *
	 * Rendered by every ClubHouse admin screen, but only when the viewer is an
	 * administrator — the screens take that as a model flag rather than deciding
	 * it, so they stay WordPress-free.
	 *
	 * @param array<int,string> $labels Role display labels, seniority order.
	 */
	public static function role_tags( array $labels ): string {
		if ( array() === $labels ) {
			return '';
		}
		$out = '<div class="clubhouse-roletags" aria-label="Roles with access to this page">'
			. '<span class="clubhouse-roletags__k">Access</span>';
		foreach ( $labels as $label ) {
			$out .= '<span class="clubhouse-roletag">' . self::esc( (string) $label ) . '</span>';
		}
		return $out . '</div>';
	}

	/**
	 * @param array{
	 *   users:array<int,array{login:string,name:string,email:string,roles:array<int,string>,pages:array<int,string>}>,
	 *   roles:array<int,array{label:string,pages:array<int,string>}>,
	 *   pages:array<int,array{label:string,description:string,access:array<int,string>}>
	 * } $model
	 */
	public static function render( array $model ): string {
		$out  = '<div class="wrap clubhouse-wrap"><div class="clubhouse-setup">';
		$out .= '<header class="clubhouse-head"><div class="clubhouse-head__titles">'
			. '<p class="clubhouse-eyebrow">Clubhouse · Administrators only</p>'
			. '<h1 class="clubhouse-head__h1">ClubHouse users &amp; access</h1></div></header>';
		$out .= '<p class="clubhouse-step__lede">Who holds a ClubHouse role on this site, and which parts of the '
			. 'Clubhouse each of them can open. This page reports access — it does not change it. Roles are set on '
			. 'the Users screen.</p>';
		$out .= self::users_table( $model['users'] );
		$out .= self::roles_table( $model['roles'] );
		$out .= self::pages_table( $model['pages'] );
		$out .= '</div></div>';
		return $out;
	}

	/**
	 * @param array<int,array{login:string,name:string,email:string,roles:array<int,string>,pages:array<int,string>}> $users
	 */
	private static function users_table( array $users ): string {
		$out = '<div class="clubhouse-step"><p class="clubhouse-step__k">People</p>'
			. '<h2 class="clubhouse-step__h">ClubHouse users</h2>';

		if ( array() === $users ) {
			// Not an error state: a site can legitimately be run by its administrators
			// alone. Say which roles would qualify, so the emptiness is readable.
			$out .= '<p class="clubhouse-help">Nobody holds a ClubHouse role yet. Assign '
				. self::esc( Blueworx_Clubhouse_Owner_Capabilities::DISPLAY ) . ' or '
				. self::esc( Blueworx_Clubhouse_Owner_Capabilities::EDITOR_DISPLAY )
				. ' on the Users screen and they will appear here.</p></div>';
			return $out;
		}

		$out .= '<table class="clubhouse-table"><thead><tr>'
			. '<th scope="col">User</th><th scope="col">ClubHouse role</th><th scope="col">Can open</th>'
			. '</tr></thead><tbody>';
		foreach ( $users as $user ) {
			$out .= '<tr><th scope="row"><span class="clubhouse-table__name">' . self::esc( $user['name'] ) . '</span>'
				. '<span class="clubhouse-table__sub">' . self::esc( $user['login'] ) . '</span></th>'
				. '<td>' . self::chips( $user['roles'] ) . '</td>'
				. '<td>' . self::chips( $user['pages'], 'No ClubHouse sections' ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/** @param array<int,array{label:string,pages:array<int,string>}> $roles */
	private static function roles_table( array $roles ): string {
		$out = '<div class="clubhouse-step"><p class="clubhouse-step__k">Roles</p>'
			. '<h2 class="clubhouse-step__h">What each role can open</h2>'
			. '<table class="clubhouse-table"><thead><tr>'
			. '<th scope="col">Role</th><th scope="col">Sections</th></tr></thead><tbody>';
		foreach ( $roles as $role ) {
			$out .= '<tr><th scope="row">' . self::esc( $role['label'] ) . '</th>'
				. '<td>' . self::chips( $role['pages'], 'No ClubHouse sections' ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/** @param array<int,array{label:string,description:string,access:array<int,string>}> $pages */
	private static function pages_table( array $pages ): string {
		$out = '<div class="clubhouse-step"><p class="clubhouse-step__k">Sections</p>'
			. '<h2 class="clubhouse-step__h">Who can open each section</h2>'
			. '<table class="clubhouse-table"><thead><tr>'
			. '<th scope="col">Section</th><th scope="col">Roles with access</th></tr></thead><tbody>';
		foreach ( $pages as $page ) {
			$out .= '<tr><th scope="row"><span class="clubhouse-table__name">' . self::esc( $page['label'] ) . '</span>'
				. '<span class="clubhouse-table__sub">' . self::esc( $page['description'] ) . '</span></th>'
				. '<td>' . self::chips( $page['access'], 'Nobody' ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * A row of chips, or a plain note when the list is empty — an empty cell reads
	 * as missing data rather than as "none".
	 *
	 * @param array<int,string> $items
	 */
	private static function chips( array $items, string $empty = '—' ): string {
		if ( array() === $items ) {
			return '<span class="clubhouse-table__none">' . self::esc( $empty ) . '</span>';
		}
		$out = '<span class="clubhouse-chips">';
		foreach ( $items as $item ) {
			$out .= '<span class="clubhouse-roletag">' . self::esc( (string) $item ) . '</span>';
		}
		return $out . '</span>';
	}
}
