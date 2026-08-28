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
 * Built from the BlueWorx admin design system, through Admin_Shell — the page
 * header, the panels and the table all come from there, so this screen looks
 * like every other BlueWorx plugin's rather than like a bare core table.
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
	 * Still on the old classes, on purpose. Its callers include Setup and Club
	 * Pages, which the page editor library replaces in the next two phases and
	 * which are still styled by admin-setup.css. Moving these chips to the
	 * design system now would leave those top bars unstyled for two releases.
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
	 * The same tags, in the design system's chips, for a screen that has been
	 * moved onto it.
	 *
	 * Two versions rather than one because the six screens showing these tags
	 * are moving over across three releases, and a single markup cannot be
	 * right on both sides of that. role_tags() and this method converge into
	 * one when Setup and Club Pages become page editor screens.
	 *
	 * @param array<int,string> $labels Role display labels, seniority order.
	 */
	public static function role_chips( array $labels ): string {
		if ( array() === $labels ) {
			return '';
		}
		$out = '<span class="bw-chips" aria-label="Roles with access to this page">';
		foreach ( $labels as $label ) {
			$out .= '<span class="bw-chip bw-chip--plain">' . self::esc( (string) $label ) . '</span>';
		}
		return $out . '</span>';
	}

	/**
	 * @param array{
	 *   users:array<int,array{login:string,name:string,email:string,roles:array<int,string>,pages:array<int,string>}>,
	 *   roles:array<int,array{label:string,pages:array<int,string>}>,
	 *   pages:array<int,array{label:string,description:string,access:array<int,string>}>
	 * } $model
	 */
	public static function render( array $model ): string {
		$out  = Blueworx_Clubhouse_Admin_Shell::open(
			'Clubhouse · Administrators only',
			'ClubHouse users and access',
			'Who holds a ClubHouse role on this site, and which parts of the Clubhouse each of them can open. '
				. 'This page reports access — it does not change it. Roles are set on the Users screen.'
		);
		$out .= self::users_table( $model['users'] );
		$out .= self::roles_table( $model['roles'] );
		$out .= self::pages_table( $model['pages'] );
		return $out . Blueworx_Clubhouse_Admin_Shell::close();
	}

	/**
	 * @param array<int,array{login:string,name:string,email:string,roles:array<int,string>,pages:array<int,string>}> $users
	 */
	private static function users_table( array $users ): string {
		if ( array() === $users ) {
			// Not an error state: a site can legitimately be run by its administrators
			// alone. Say which roles would qualify, so the emptiness is readable.
			$body = '<div class="bw-empty"><i class="bw-icon bw-icon--28 bw-empty__icon" data-lucide="users"></i>'
				. '<p class="bw-empty__title">Nobody holds a ClubHouse role yet</p>'
				. '<p class="bw-empty__text">Assign '
				. self::esc( Blueworx_Clubhouse_Owner_Capabilities::DISPLAY ) . ' or '
				. self::esc( Blueworx_Clubhouse_Owner_Capabilities::EDITOR_DISPLAY )
				. ' on the Users screen and they will appear here.</p></div>';
			return Blueworx_Clubhouse_Admin_Shell::card( 'People', 'ClubHouse users', '', $body );
		}

		$body = '<table class="bw-table"><thead><tr>'
			. '<th scope="col">User</th><th scope="col">ClubHouse role</th><th scope="col">Can open</th>'
			. '</tr></thead><tbody>';
		foreach ( $users as $user ) {
			$body .= '<tr><th scope="row"><span class="bw-table__primary">' . self::esc( $user['name'] ) . '</span>'
				. '<span class="bw-table__sub">' . self::esc( $user['login'] ) . '</span></th>'
				. '<td>' . self::chips( $user['roles'] ) . '</td>'
				. '<td>' . self::chips( $user['pages'], 'No ClubHouse sections' ) . '</td></tr>';
		}
		$body .= '</tbody></table>';
		return Blueworx_Clubhouse_Admin_Shell::card( 'People', 'ClubHouse users', '', $body );
	}

	/** @param array<int,array{label:string,pages:array<int,string>}> $roles */
	private static function roles_table( array $roles ): string {
		$body = '<table class="bw-table"><thead><tr>'
			. '<th scope="col">Role</th><th scope="col">Sections</th></tr></thead><tbody>';
		foreach ( $roles as $role ) {
			$body .= '<tr><th scope="row">' . self::esc( $role['label'] ) . '</th>'
				. '<td>' . self::chips( $role['pages'], 'No ClubHouse sections' ) . '</td></tr>';
		}
		$body .= '</tbody></table>';
		return Blueworx_Clubhouse_Admin_Shell::card( 'Roles', 'What each role can open', '', $body );
	}

	/** @param array<int,array{label:string,description:string,access:array<int,string>}> $pages */
	private static function pages_table( array $pages ): string {
		$body = '<table class="bw-table"><thead><tr>'
			. '<th scope="col">Section</th><th scope="col">Roles with access</th></tr></thead><tbody>';
		foreach ( $pages as $page ) {
			$body .= '<tr><th scope="row"><span class="bw-table__primary">' . self::esc( $page['label'] ) . '</span>'
				. '<span class="bw-table__sub">' . self::esc( $page['description'] ) . '</span></th>'
				. '<td>' . self::chips( $page['access'], 'Nobody' ) . '</td></tr>';
		}
		$body .= '</tbody></table>';
		return Blueworx_Clubhouse_Admin_Shell::card( 'Sections', 'Who can open each section', '', $body );
	}

	/**
	 * A row of chips, or a plain note when the list is empty — an empty cell reads
	 * as missing data rather than as "none".
	 *
	 * @param array<int,string> $items
	 */
	private static function chips( array $items, string $empty = '—' ): string {
		if ( array() === $items ) {
			return '<span class="bw-table__sub">' . self::esc( $empty ) . '</span>';
		}
		$out = '<span class="bw-chips">';
		foreach ( $items as $item ) {
			$out .= '<span class="bw-chip bw-chip--plain">' . self::esc( (string) $item ) . '</span>';
		}
		return $out . '</span>';
	}
}
