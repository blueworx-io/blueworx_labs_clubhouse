<?php
// includes/admin/class-access-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress glue for the admin-only access visibility: registers the read-only
 * "ClubHouse access" page under Settings, gathers the site's ClubHouse users,
 * and answers the one question every ClubHouse admin screen asks before drawing
 * its top-bar role tags.
 *
 * Administrators only, twice over: the page is registered with manage_options
 * AND re-checked on render, because a menu capability alone does not stop a
 * direct URL. It sits under Settings for the same reason — Settings is already
 * off both ClubHouse roles' menu allowlists, so the page is invisible to an
 * owner even before the capability check.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Access_Controller {

	public const CAPABILITY = 'manage_options';
	public const PAGE_SLUG  = 'clubhouse-access';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_menu(): void {
		add_submenu_page(
			'options-general.php',
			'ClubHouse users & access',
			'ClubHouse access',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		Blueworx_Clubhouse_Admin_Assets::enqueue();
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		echo Blueworx_Clubhouse_Access_Screen::render( self::build_model() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Access_Screen.
	}

	/**
	 * True when the viewer should see role tags in a ClubHouse page's top bar.
	 * The single place that decision is made, so every screen agrees and none of
	 * them has to know the capability.
	 */
	public static function may_see_role_tags(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( self::CAPABILITY );
	}

	/**
	 * The tag markup for a ClubHouse page, or '' when the viewer is not an
	 * administrator. Called by each screen's controller as it builds its model.
	 */
	public static function role_tags_for( string $page_slug ): string {
		if ( ! self::may_see_role_tags() ) {
			return '';
		}
		return Blueworx_Clubhouse_Access_Screen::role_tags(
			Blueworx_Clubhouse_Admin_Pages::access_labels( $page_slug )
		);
	}

	/**
	 * The same tags in the design system's chips, for a screen built from it.
	 * Replaces role_tags_for() screen by screen; the two converge when Setup
	 * and Club Pages move over.
	 */
	public static function role_chips_for( string $page_slug ): string {
		if ( ! self::may_see_role_tags() ) {
			return '';
		}
		return Blueworx_Clubhouse_Access_Screen::role_chips(
			Blueworx_Clubhouse_Admin_Pages::access_labels( $page_slug )
		);
	}

	/**
	 * @return array{
	 *   users:array<int,array{login:string,name:string,email:string,roles:array<int,string>,pages:array<int,string>}>,
	 *   roles:array<int,array{label:string,pages:array<int,string>}>,
	 *   pages:array<int,array{label:string,description:string,access:array<int,string>}>
	 * }
	 */
	public static function build_model(): array {
		return array(
			'users' => self::clubhouse_users(),
			'roles' => self::role_rows(),
			'pages' => self::page_rows(),
		);
	}

	/**
	 * The site's ClubHouse users: accounts holding one of the two ClubHouse roles.
	 *
	 * Deliberately not every WordPress user, and deliberately not administrators —
	 * an administrator's access is unlimited by definition and is reported in the
	 * per-role and per-section tables instead. This table answers "who have we
	 * given a ClubHouse role to", which is the question that actually needs
	 * checking.
	 *
	 * @return array<int,array{login:string,name:string,email:string,roles:array<int,string>,pages:array<int,string>}>
	 */
	private static function clubhouse_users(): array {
		if ( ! function_exists( 'get_users' ) ) {
			return array();
		}
		$found = get_users(
			array(
				'role__in' => Blueworx_Clubhouse_Owner_Capabilities::roles(),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);

		$rows = array();
		foreach ( (array) $found as $user ) {
			$roles = ( is_object( $user ) && isset( $user->roles ) && is_array( $user->roles ) ) ? $user->roles : array();

			// A user could in principle hold both ClubHouse roles. Report every one
			// they hold, and union the pages, rather than picking the first — the
			// point of the page is that it matches what they can actually open.
			$labels = array();
			$pages  = array();
			foreach ( Blueworx_Clubhouse_Owner_Capabilities::roles() as $role ) {
				if ( ! in_array( $role, $roles, true ) ) {
					continue;
				}
				$labels[] = Blueworx_Clubhouse_Admin_Pages::role_label( $role );
				foreach ( Blueworx_Clubhouse_Admin_Pages::pages_for_role( $role ) as $page ) {
					$pages[ $page['slug'] ] = $page['label'];
				}
			}

			$rows[] = array(
				'login' => (string) ( $user->user_login ?? '' ),
				'name'  => (string) ( $user->display_name ?? ( $user->user_login ?? '' ) ),
				'email' => (string) ( $user->user_email ?? '' ),
				'roles' => $labels,
				'pages' => array_values( $pages ),
			);
		}
		return $rows;
	}

	/** @return array<int,array{label:string,pages:array<int,string>}> */
	private static function role_rows(): array {
		$rows = array();
		foreach ( Blueworx_Clubhouse_Admin_Pages::roles() as $role ) {
			$labels = array();
			foreach ( Blueworx_Clubhouse_Admin_Pages::pages_for_role( $role ) as $page ) {
				$labels[] = $page['label'];
			}
			$rows[] = array(
				'label' => Blueworx_Clubhouse_Admin_Pages::role_label( $role ),
				'pages' => $labels,
			);
		}
		return $rows;
	}

	/** @return array<int,array{label:string,description:string,access:array<int,string>}> */
	private static function page_rows(): array {
		$rows = array();
		foreach ( Blueworx_Clubhouse_Admin_Pages::all() as $page ) {
			$rows[] = array(
				'label'       => (string) $page['label'],
				'description' => (string) $page['description'],
				'access'      => Blueworx_Clubhouse_Admin_Pages::access_labels( (string) $page['slug'] ),
			);
		}
		return $rows;
	}
}
