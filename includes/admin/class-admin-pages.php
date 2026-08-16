<?php
// includes/admin/class-admin-pages.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The declared registry of ClubHouse-specific admin pages, and who can reach
 * each one.
 *
 * Access was previously only knowable by reading three things at once — a
 * page's capability constant, the role capability maps, and the per-role menu
 * allowlist — and only ever from the outside, by logging in as somebody. This
 * makes it a single answerable question, which is what both halves of the
 * access-visibility feature ask: the Settings page asks it per user, the
 * top-bar tags ask it per page.
 *
 * A declared registry rather than pure capability derivation, because a
 * capability alone cannot name a page, and because two of the four pages are
 * gated by BOTH a capability and the role's menu allowlist. A page a role holds
 * the capability for but which is stripped from its menu is not a page that
 * role can reach in practice, and reporting otherwise would be worse than
 * reporting nothing.
 *
 * Pure — no WordPress. The controller supplies the users; this decides access.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Admin_Pages {

	public const ADMINISTRATOR = 'administrator';

	/**
	 * Every ClubHouse-specific admin page.
	 *
	 * 'cap'  is the capability the page itself is registered with — the same
	 *        constant the controller passes to add_menu_page and re-checks in
	 *        render_page, so this cannot drift from what is enforced.
	 * 'menu' is the TOP-LEVEL menu slug the page hangs from, which is what the
	 *        role menu allowlists are written in terms of. Import and the guide
	 *        are submenus of Clubhouse, so their menu is their parent's.
	 *
	 * @return array<int,array{slug:string,label:string,cap:string,menu:string,description:string}>
	 */
	public static function all(): array {
		return array(
			array(
				'slug'        => Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
				'label'       => 'Clubhouse Setup',
				// Registered with the content capability, not manage_clubhouse:
				// the Menu tab on this screen belongs to the Content Editor. Every
				// setup tab is still gated on CAPABILITY inside the screen.
				'cap'         => Blueworx_Clubhouse_Setup_Controller::MENU_CAPABILITY,
				'menu'        => Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
				'description' => 'Base look, branding, page visibility and the header menu.',
			),
			array(
				'slug'        => Blueworx_Clubhouse_Content_Controller::PAGE_SLUG,
				'label'       => 'Club Pages',
				'cap'         => Blueworx_Clubhouse_Content_Controller::CAPABILITY,
				'menu'        => Blueworx_Clubhouse_Content_Controller::PAGE_SLUG,
				'description' => 'The words and images on every page.',
			),
			array(
				'slug'        => Blueworx_Clubhouse_Import_Controller::PAGE_SLUG,
				'label'       => 'Import',
				'cap'         => Blueworx_Clubhouse_Import_Controller::CAPABILITY,
				'menu'        => Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
				'description' => 'Bring a club\'s existing content in, under Clubhouse.',
			),
			array(
				'slug'        => Blueworx_Clubhouse_Seo_Controller::PAGE_SLUG,
				'label'       => 'Search &amp; sharing',
				'cap'         => Blueworx_Clubhouse_Seo_Controller::CAPABILITY,
				'menu'        => Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
				'description' => 'How each page reads in search results and when it is shared.',
			),
			array(
				'slug'        => Blueworx_Clubhouse_Guide_Controller::PAGE_SLUG,
				'label'       => 'User guide',
				'cap'         => Blueworx_Clubhouse_Guide_Controller::CAPABILITY,
				'menu'        => Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
				'description' => 'How ClubHouse works, built from this site as it stands.',
			),
			array(
				'slug'        => Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG,
				'label'       => 'Collections',
				'cap'         => Blueworx_Clubhouse_Collection_Types::CONTENT_CAP,
				'menu'        => Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG,
				'description' => 'Sports, teams, events, fixtures and the rest.',
			),
		);
	}

	/** One page by slug, or null. @return array<string,mixed>|null */
	public static function find( string $slug ): ?array {
		foreach ( self::all() as $page ) {
			if ( $page['slug'] === $slug ) {
				return $page;
			}
		}
		return null;
	}

	/**
	 * Every role this feature reports on, senior first: the administrator, then
	 * the two ClubHouse roles.
	 *
	 * @return array<int,string>
	 */
	public static function roles(): array {
		return array_merge( array( self::ADMINISTRATOR ), Blueworx_Clubhouse_Owner_Capabilities::roles() );
	}

	/** The display label for any role this feature reports on. */
	public static function role_label( string $role ): string {
		return self::ADMINISTRATOR === $role
			? 'Administrator'
			: Blueworx_Clubhouse_Owner_Capabilities::display( $role );
	}

	/**
	 * The capability map each role is judged against.
	 *
	 * The two ClubHouse roles come from the same source of truth that registers
	 * them, so this reports what the site actually grants rather than a second
	 * copy of the rules that could drift.
	 *
	 * The administrator's is modelled rather than read, because an administrator's
	 * real map is whatever the site has made it and is not knowable here. The
	 * model is the floor WordPress guarantees plus the capability this plugin
	 * grants administrators on activation — enough to decide every page in the
	 * registry, and honest about being a floor.
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function role_capabilities(): array {
		$admin = Blueworx_Clubhouse_Owner_Capabilities::stock_editor_caps();
		$admin['manage_options'] = true;
		foreach ( Blueworx_Clubhouse_Owner_Capabilities::admin_cap_grants() as $cap ) {
			$admin[ $cap ] = true;
		}

		$maps = array( self::ADMINISTRATOR => $admin );
		foreach ( Blueworx_Clubhouse_Owner_Capabilities::roles() as $role ) {
			$maps[ $role ] = Blueworx_Clubhouse_Owner_Capabilities::capabilities_for( $role );
		}
		return $maps;
	}

	/**
	 * True when this role can actually open this page: it holds the capability the
	 * page is registered with, AND the page's top-level menu survives its menu
	 * allowlist.
	 *
	 * The administrator has no allowlist — nothing is removed from an
	 * administrator's menu — so the capability alone decides.
	 *
	 * @param array<string,array<string,bool>> $role_caps Override for testing.
	 */
	public static function role_can( string $role, string $page_slug, array $role_caps = array() ): bool {
		$page = self::find( $page_slug );
		if ( null === $page ) {
			return false;
		}
		$caps = ( array() === $role_caps ? self::role_capabilities() : $role_caps )[ $role ] ?? array();
		if ( empty( $caps[ $page['cap'] ] ) ) {
			return false;
		}
		if ( self::ADMINISTRATOR === $role ) {
			return true;
		}
		return in_array(
			$page['menu'],
			Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist_for( $role ),
			true
		);
	}

	/**
	 * The roles that can reach a page, in seniority order — what the top-bar tags
	 * render.
	 *
	 * @param array<string,array<string,bool>> $role_caps
	 * @return array<int,string>
	 */
	public static function roles_with_access( string $page_slug, array $role_caps = array() ): array {
		$out = array();
		foreach ( self::roles() as $role ) {
			if ( self::role_can( $role, $page_slug, $role_caps ) ) {
				$out[] = $role;
			}
		}
		return $out;
	}

	/**
	 * The pages a role can reach, as {slug,label} in registry order — what the
	 * Settings page lists against each user.
	 *
	 * @param array<string,array<string,bool>> $role_caps
	 * @return array<int,array{slug:string,label:string}>
	 */
	public static function pages_for_role( string $role, array $role_caps = array() ): array {
		$out = array();
		foreach ( self::all() as $page ) {
			if ( self::role_can( $role, $page['slug'], $role_caps ) ) {
				$out[] = array( 'slug' => $page['slug'], 'label' => $page['label'] );
			}
		}
		return $out;
	}

	/**
	 * The role display labels for a page's tags. Kept separate from
	 * roles_with_access() so the screen never has to know a role key.
	 *
	 * @param array<string,array<string,bool>> $role_caps
	 * @return array<int,string>
	 */
	public static function access_labels( string $page_slug, array $role_caps = array() ): array {
		return array_map(
			array( self::class, 'role_label' ),
			self::roles_with_access( $page_slug, $role_caps )
		);
	}
}
