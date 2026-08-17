<?php
// includes/admin/class-seo-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "How your site looks in search" screen, under Clubhouse.
 *
 * The report is built by rendering each clubhouse page in-process and reading
 * the markup back, rather than by asking the settings what they hold. A page
 * with a section switched off, or a look that changed a heading level, is
 * judged exactly as a visitor and a search engine receive it — and no HTTP
 * request leaves the site to do it.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Seo_Controller {

	public const CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;
	public const PAGE_SLUG  = 'clubhouse-seo';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_menu(): void {
		add_submenu_page(
			Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
			'Search &amp; sharing',
			'Search &amp; sharing',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'clubhouse-admin-setup', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/admin-setup.css', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		echo Blueworx_Clubhouse_Seo_Screen::render( self::build_model() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Seo_Screen.
	}

	/** @return array{deferring_to:string,role_tags:string,pages:array<int,array<string,mixed>>} */
	public static function build_model(): array {
		$ctx      = Blueworx_Clubhouse_Frontend::context();
		$logo_url = Blueworx_Clubhouse_Frontend::resolve_logo( $ctx->branding->get_logo() );
		$noindex  = '1' !== (string) get_option( 'blog_public', '1' );

		Blueworx_Clubhouse_Links::set_resolver( array( Blueworx_Clubhouse_Frontend::class, 'link_url' ) );
		Blueworx_Clubhouse_Menu::set_provider(
			static fn(): Blueworx_Clubhouse_Menu => new Blueworx_Clubhouse_Menu( new Blueworx_Clubhouse_Options_Storage() )
		);

		$pages = array();
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $page ) {
			$slug = (string) $page['slug'];
			$key  = '' === $slug ? 'home' : $slug;
			if ( ! $ctx->visibility->is_page_visible( $key ) ) {
				// A hidden page is not served, so reporting on it would be reporting on
				// something nobody can reach.
				continue;
			}

			$body = Blueworx_Clubhouse_Page_Map::render(
				$slug,
				$ctx->branding,
				$ctx->visibility,
				$ctx->collections,
				$ctx->composer,
				$logo_url
			);

			$url  = Blueworx_Clubhouse_Frontend::link_url( $key );
			// The same chain the head emits, so the report judges what is actually
			// on the page rather than making a second guess at it.
			$lede = Blueworx_Clubhouse_Seo_Head::description( $ctx, $key, $ctx->branding->get_club_name() );
			$doc  = Blueworx_Clubhouse_Seo::inspect( $body );
			// The title, description and canonical live in <head>, which Page_Map does
			// not render — the template and Seo_Head own those. Fill them in from the
			// same sources those two use, so the report judges the whole document.
			$doc['title']       = Blueworx_Clubhouse_Frontend::document_title( $ctx->branding->get_club_name(), (string) $page['label'] );
			$doc['description'] = Blueworx_Clubhouse_Seo::description( $lede );
			$doc['canonical']   = $url;
			$doc['noindex']     = $noindex;

			$checks  = Blueworx_Clubhouse_Seo::checks( $doc );
			$pages[] = array(
				'label'  => (string) $page['label'],
				'url'    => $url,
				'status' => Blueworx_Clubhouse_Seo::worst( $checks ),
				'checks' => $checks,
			);
		}

		return array(
			'deferring_to' => self::rival_name(),
			'role_tags'    => Blueworx_Clubhouse_Access_Controller::role_tags_for( self::PAGE_SLUG ),
			'pages'        => $pages,
		);
	}

	/** The SEO plugin this site runs instead, named for the report — '' when none. */
	private static function rival_name(): string {
		$known = array(
			'WPSEO_Options'           => 'Yoast SEO',
			'RankMath'                => 'Rank Math',
			'All_in_One_SEO_Pack'     => 'All in One SEO',
			'AIOSEO\\Plugin\\AIOSEO'  => 'All in One SEO',
			'The_SEO_Framework\\Load' => 'The SEO Framework',
			'SEOPress'                => 'SEOPress',
		);
		foreach ( $known as $symbol => $name ) {
			if ( class_exists( $symbol ) ) {
				return $name;
			}
		}
		return '';
	}
}
