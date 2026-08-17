<?php
// includes/admin/class-pages-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress glue for Content → Pages: menu mounting, capability and nonce
 * checks, and turning a POST into a change of composition. The screen class
 * draws; this decides.
 *
 * Two things are deliberately written on a page switch. The front end still
 * asks Visibility whether a page is on, and will keep asking until the old
 * path goes (issue #207), so that is what the switch has to move. The
 * composition's own flag is written alongside it, so the two never disagree
 * and the day the front end changes reader nothing has to be migrated.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Pages_Controller {

	public const CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;
	public const PAGE_SLUG  = 'clubhouse-pages';
	public const NONCE      = 'clubhouse_pages_save';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_menu(): void {
		add_menu_page(
			'Content',
			'Content',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' ),
			Blueworx_Clubhouse_Admin_Menu_Icons::data_uri( self::PAGE_SLUG ),
			4
		);
		add_submenu_page(
			self::PAGE_SLUG,
			'Pages',
			'Pages',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( ! str_contains( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'clubhouse-admin-setup', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/admin-setup.css', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$storage = new Blueworx_Clubhouse_Options_Storage();
		$notices = array();

		if ( isset( $_POST['clubhouse_pages_submit'] ) ) {
			check_admin_referer( self::NONCE );
			$notices = self::handle_post( wp_unslash( $_POST ), $storage ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- handle_post validates every value against the page map, the block library and the type registry.
		}

		// The page being edited comes from the query string, and is only ever used
		// after Page_Map has agreed it is a page this site serves.
		$page = isset( $_GET['club_page'] ) ? sanitize_key( (string) wp_unslash( $_GET['club_page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing which page to look at changes nothing.

		$model = self::build_model(
			$storage,
			$notices,
			wp_nonce_field( self::NONCE, '_wpnonce', true, false ),
			admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			$page
		);
		$model['role_tags'] = Blueworx_Clubhouse_Access_Controller::role_tags_for( self::PAGE_SLUG );

		echo Blueworx_Clubhouse_Pages_Screen::render( $model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Pages_Screen.
	}

	// -- Deciding -------------------------------------------------------------

	/**
	 * Apply one POST from this screen.
	 *
	 * Every branch validates before it writes: a page this site does not serve, a
	 * block the library has never heard of and a type that is not in the registry
	 * all fall through without touching storage, because the values arrive from a
	 * form and a form can say anything.
	 *
	 * @param array<string,mixed> $post
	 * @return array<int,array{type:string,text:string}> Notices for the screen.
	 */
	public static function handle_post( array $post, Blueworx_Clubhouse_Storage $storage ): array {
		$page = self::page_key( (string) ( $post['clubhouse_pages_page'] ?? '' ) );
		if ( '' === $page ) {
			return array();
		}

		$library     = new Blueworx_Clubhouse_Block_Library( $storage );
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );

		if ( isset( $post['clubhouse_pages_switch'] ) ) {
			$on = '' !== (string) ( $post['clubhouse_page_enabled'] ?? '' );
			( new Blueworx_Clubhouse_Visibility( $storage ) )->set_page_visible( $page, $on );
			$composition->set_enabled( $page, $on );
			return array( self::notice( $on ? 'This page is on the site.' : 'This page is off the site. Its address now goes nowhere.' ) );
		}

		$remove = (string) ( $post['clubhouse_pages_remove'] ?? '' );
		if ( '' !== $remove ) {
			$block = $library->get( $remove );
			if ( null === $block ) {
				return array();
			}
			$composition->remove( $page, $remove );
			return array( self::notice( sprintf( '"%s" is off this page. It is still in your blocks.', (string) $block['name'] ) ) );
		}

		$add = (string) ( $post['clubhouse_pages_add'] ?? '' );
		if ( str_starts_with( $add, 'have:' ) ) {
			$id = substr( $add, 5 );
			if ( ! $library->has( $id ) ) {
				return array();
			}
			$composition->add( $page, $id );
			$block = $library->get( $id );
			return array( self::notice( sprintf( '"%s" is on this page.', (string) $block['name'] ) ) );
		}
		if ( str_starts_with( $add, 'new:' ) ) {
			return self::add_new( substr( $add, 4 ), $page, $library, $composition );
		}

		return array();
	}

	/**
	 * Make a block of this type and put it on this page.
	 *
	 * It is named after the page it was made on, so the library reads as a list
	 * of places rather than a list of types — "About FAQ", not "FAQ 3". The name
	 * is the owner's to change on the Blocks screen.
	 *
	 * @return array<int,array{type:string,text:string}>
	 */
	private static function add_new(
		string $type,
		string $page,
		Blueworx_Clubhouse_Block_Library $library,
		Blueworx_Clubhouse_Page_Composition $composition
	): array {
		if ( ! self::type_offered( $type ) ) {
			return array();
		}
		$name = self::page_label( $page ) . ' ' . Blueworx_Clubhouse_Block_Types::get( $type )['label'];
		$id   = $library->add( $type, $name );
		$composition->add( $page, $id );
		return array( self::notice( sprintf( '"%s" is on this page. Add its words under Content → Blocks.', $name ) ) );
	}

	/** @return array{type:string,text:string} */
	private static function notice( string $text ): array {
		return array( 'type' => 'success', 'text' => $text );
	}

	// -- The model ------------------------------------------------------------

	/**
	 * @param array<int,array{type:string,text:string}> $notices
	 * @return array<string,mixed>
	 */
	public static function build_model(
		Blueworx_Clubhouse_Storage $storage,
		array $notices,
		string $nonce_field,
		string $action_url,
		string $page = ''
	): array {
		$library     = new Blueworx_Clubhouse_Block_Library( $storage );
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );
		$visibility  = new Blueworx_Clubhouse_Visibility( $storage );

		$current = self::page_key( $page );
		if ( '' === $current ) {
			$current = 'home';
		}

		$pages = array();
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $entry ) {
			$key     = self::key_for_slug( (string) $entry['slug'] );
			$pages[] = array(
				'slug'    => $key,
				'label'   => (string) $entry['label'],
				'enabled' => $visibility->is_page_visible( $key ),
				'current' => $key === $current,
				'url'     => $action_url . '&club_page=' . $key,
			);
		}

		return array(
			'pages'         => $pages,
			'current'       => array(
				'slug'    => $current,
				'label'   => self::page_label( $current ),
				'enabled' => $visibility->is_page_visible( $current ),
			),
			'rows'          => self::rows( $current, $library, $composition ),
			'pinned_top'    => self::pinned( 'header', $library ),
			'pinned_bottom' => self::pinned( 'footer', $library ),
			'picker'        => self::picker( $library ),
			'blocks_url'    => str_replace( self::PAGE_SLUG, 'clubhouse-blocks', $action_url ),
			'notices'       => $notices,
			'nonce_field'   => $nonce_field,
			'action_url'    => $action_url,
			'role_tags'     => '',
		);
	}

	/**
	 * The page's blocks in the order the site renders them.
	 *
	 * Ordered by Page_Composer, so the editor cannot drift out of step with the
	 * page — one place decides what "render order" means, and it is the place
	 * that does the rendering.
	 *
	 * @return array<int,array{id:string,name:string,type:string,type_label:string,shared_with:array<int,string>}>
	 */
	private static function rows(
		string $page,
		Blueworx_Clubhouse_Block_Library $library,
		Blueworx_Clubhouse_Page_Composition $composition
	): array {
		$rows = array();
		foreach ( ( new Blueworx_Clubhouse_Page_Composer( $library, $composition ) )->blocks_for( $page ) as $block ) {
			$type   = Blueworx_Clubhouse_Block_Types::get( (string) $block['type'] );
			$shared = array();
			foreach ( $composition->uses( (string) $block['id'] ) as $other ) {
				if ( $other !== $page ) {
					$shared[] = self::page_label( $other );
				}
			}
			$rows[] = array(
				'id'          => (string) $block['id'],
				'name'        => (string) $block['name'],
				'type'        => (string) $block['type'],
				'type_label'  => null === $type ? (string) $block['type'] : $type['label'],
				'shared_with' => $shared,
			);
		}
		return $rows;
	}

	/**
	 * The header or footer: one block, shown on every page, on no page's list.
	 *
	 * @return array{id:string,name:string,type_label:string}
	 */
	private static function pinned( string $type, Blueworx_Clubhouse_Block_Library $library ): array {
		$label = (string) Blueworx_Clubhouse_Block_Types::get( $type )['label'];
		foreach ( $library->of_type( $type ) as $block ) {
			return array(
				'id'         => (string) $block['id'],
				'name'       => (string) $block['name'],
				'type_label' => $label,
			);
		}
		return array( 'id' => '', 'name' => $label, 'type_label' => $label );
	}

	/**
	 * What can go on a page: every block type this site can render, each with the
	 * blocks of that type the club already has.
	 *
	 * Reusing one is the first option offered because it is the one that keeps
	 * "edit it once" true; making a new one is the fallback.
	 *
	 * @return array<int,array{type:string,label:string,new_label:string,existing:array<int,array{id:string,name:string}>}>
	 */
	private static function picker( Blueworx_Clubhouse_Block_Library $library ): array {
		$groups = array();
		foreach ( Blueworx_Clubhouse_Block_Types::all() as $key => $type ) {
			if ( ! self::type_offered( (string) $key ) ) {
				continue;
			}
			$existing = array();
			foreach ( $library->of_type( (string) $key ) as $block ) {
				$existing[] = array( 'id' => (string) $block['id'], 'name' => (string) $block['name'] );
			}
			$groups[] = array(
				'type'      => (string) $key,
				'label'     => (string) $type['label'],
				'new_label' => 'New ' . self::lower_label( (string) $type['label'] ),
				'existing'  => $existing,
			);
		}
		return $groups;
	}

	/**
	 * Whether a type can be put on a page at all: the header and footer cannot,
	 * being the shell rather than content, and neither can a type whose
	 * integration this site has not got.
	 */
	private static function type_offered( string $key ): bool {
		$type = Blueworx_Clubhouse_Block_Types::get( $key );
		if ( null === $type || $type['singleton'] ) {
			return false;
		}
		return '' === $type['requires'] || Blueworx_Clubhouse_Integrations::provides( $type['requires'] );
	}

	/**
	 * A type label in mid-sentence: "Call to action band" reads better after
	 * "New" in lower case, but "FAQ" does not become "fAQ".
	 */
	private static function lower_label( string $label ): string {
		$first = strtok( $label, ' ' );
		if ( is_string( $first ) && strlen( $first ) > 1 && strtoupper( $first ) === $first ) {
			return $label;
		}
		return lcfirst( $label );
	}

	// -- Pages ----------------------------------------------------------------

	/** The composition key for a Page_Map slug: home is '' as a URL, 'home' as a key. */
	private static function key_for_slug( string $slug ): string {
		return '' === $slug ? 'home' : $slug;
	}

	/** The given page if this site serves it, or '' — the guard every write goes through. */
	private static function page_key( string $page ): string {
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $entry ) {
			if ( self::key_for_slug( (string) $entry['slug'] ) === $page ) {
				return $page;
			}
		}
		return '';
	}

	private static function page_label( string $page ): string {
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $entry ) {
			if ( self::key_for_slug( (string) $entry['slug'] ) === $page ) {
				return (string) $entry['label'];
			}
		}
		return ucfirst( $page );
	}
}
