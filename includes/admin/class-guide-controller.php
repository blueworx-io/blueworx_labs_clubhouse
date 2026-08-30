<?php
// includes/admin/class-guide-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The User Guide screen, under Clubhouse.
 *
 * Gathers the facts about this site — which pages it serves, which sections are
 * on, which screens this user can open, how many of each collection exist, which
 * look is active — and hands them to Guide, which decides what to say. Nothing
 * about the guide's wording lives here, and nothing about WordPress lives there.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Guide_Controller {

	public const CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP;
	public const PAGE_SLUG  = 'clubhouse-guide';

	public static function register(): void {
		// Priority 12: after Setup (10) and the other Clubhouse submenus, so the
		// guide sits at the bottom of the menu where a reference belongs.
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 12 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Hangs off Clubhouse (issue #145), where the rest of the club's tooling now
	 * lives. That menu used to be stripped from the Content Editor's menu, which
	 * is why the guide was parented at Club Pages instead; it no longer is — the
	 * role reaches the Clubhouse screen for the menu builder — so the guide is
	 * still visible to exactly the person most likely to need it.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
			'User guide',
			'User guide',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		Blueworx_Clubhouse_Admin_Assets::enqueue();
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		// The chapters are built from the site; the access chips are about who is
		// reading, so they are merged in here rather than threaded through Guide.
		$model              = Blueworx_Clubhouse_Guide::build( self::site() );
		$model['role_tags'] = Blueworx_Clubhouse_Access_Controller::role_chips_for( self::PAGE_SLUG );
		echo Blueworx_Clubhouse_Guide_Screen::render( $model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Guide_Screen.
	}

	/**
	 * This site, as the guide needs to see it.
	 *
	 * @return array<string,mixed>
	 */
	public static function site(): array {
		$storage    = new Blueworx_Clubhouse_Options_Storage();
		$branding   = new Blueworx_Clubhouse_Branding( $storage );
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$registry   = Blueworx_Clubhouse_Frontend::registry( $storage );
		$active     = $registry->active();

		$looks = array();
		foreach ( $registry->all() as $look ) {
			$looks[] = array(
				'name'        => $look->name(),
				'description' => $look->description(),
				'active'      => null !== $active && $look->slug() === $active->slug(),
			);
		}

		// Keyed by page so the panels can be matched to the page map without
		// either of them having to know the other's order. The switchable
		// sections of a page are its hideable panels, read from the same
		// declaration the page's own editor is built from.
		$content          = new Blueworx_Clubhouse_Page_Content( $storage );
		$sections_by_page = array();
		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $area => $spec ) {
			// Header, footer, welcome pack and cookie notice are edited under
			// Global content, but they show on Home like everything else here,
			// and an owner reading this guide is looking for them under Home.
			$key = Blueworx_Clubhouse_Page_Content::GLOBAL_AREA === $area ? 'home' : (string) $area;
			foreach ( $spec['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					if ( empty( $panel['hideable'] ) ) {
						continue;
					}
					$sections_by_page[ $key ][] = array(
						'label'   => (string) $panel['title'],
						'visible' => $content->is_section_shown( (string) $area, (string) $panel['id'] ),
					);
				}
			}
		}

		$pages = array();
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $page ) {
			$key     = '' === (string) $page['slug'] ? 'home' : (string) $page['slug'];
			$pages[] = array(
				'key'      => $key,
				'label'    => (string) $page['label'],
				'visible'  => $visibility->is_page_visible( $key ),
				'sections' => $sections_by_page[ $key ] ?? array(),
			);
		}

		return array(
			'club_name'   => $branding->get_club_name(),
			'looks'       => $looks,
			'pages'       => $pages,
			'screens'     => self::screens(),
			'collections' => self::collections(),
			'setup_url'   => admin_url( 'admin.php?page=' . Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG ),
			// The Pages list, not a screen of this plugin's own: a club page is a
			// real WordPress page now, and its Edit button opens its own editor.
			'content_url' => admin_url( 'edit.php?post_type=page' ),
		);
	}

	/**
	 * The ClubHouse screens this user can actually open. Listing one they cannot
	 * would be telling them to click something that is not on their menu.
	 *
	 * @return array<int,array{label:string,description:string,url:string}>
	 */
	private static function screens(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Admin_Pages::all() as $page ) {
			$slug = (string) $page['slug'];
			if ( self::PAGE_SLUG === $slug || ! current_user_can( (string) $page['cap'] ) ) {
				continue;
			}
			$out[] = array(
				'label'       => html_entity_decode( (string) $page['label'], ENT_QUOTES, 'UTF-8' ),
				'description' => (string) $page['description'],
				// A post-type screen is edit.php?post_type=…, everything else is a
				// Clubhouse admin page — the slug says which.
				'url'         => 0 === strpos( $slug, 'clubhouse-' ) || 0 === strpos( $slug, 'clubhouse_' )
					? admin_url( 'admin.php?page=' . $slug )
					: admin_url( $slug ),
			);
		}
		return $out;
	}

	/**
	 * @return array<int,array{plural:string,description:string,count:int,url:string}>
	 */
	private static function collections(): array {
		$notes = array(
			'clubhouse_sport'   => 'The sports your club plays. Each one gets its own card on the sports page.',
			'clubhouse_team'    => 'The teams within each sport, with their league and match day.',
			'clubhouse_fixture' => 'Matches, past and upcoming. Add a score and a result to a past one and it shows as a result instead.',
			'clubhouse_event'   => 'Anything happening at the club that is not a match — socials, quiz nights, AGMs.',
			'clubhouse_sponsor' => 'The businesses that back the club, shown in the sponsors band.',
			'clubhouse_person'  => 'Committee members and the people a visitor might need to contact.',
		);

		$out = array();
		foreach ( Blueworx_Clubhouse_Collection_Types::POST_TYPES as $type ) {
			$object = get_post_type_object( $type );
			if ( null === $object ) {
				continue;
			}
			$counts = wp_count_posts( $type );
			$out[]  = array(
				'plural'      => (string) $object->labels->name,
				'description' => (string) ( $notes[ $type ] ?? '' ),
				'count'       => (int) ( $counts->publish ?? 0 ) + (int) ( $counts->draft ?? 0 ),
				'url'         => admin_url( 'edit.php?post_type=' . $type ),
			);
		}
		return $out;
	}
}
