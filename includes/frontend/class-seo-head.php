<?php
// includes/frontend/class-seo-head.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits the description, canonical and social-card meta for clubhouse pages,
 * unless something better already is.
 *
 * A club that installs Yoast or Rank Math has chosen a tool for exactly this
 * job, and two plugins both writing og:title is worse than either doing it
 * alone — scrapers pick one, usually the first, and the owner has no way to
 * tell which. So detection comes first and this stands down entirely rather
 * than trying to merge.
 *
 * Only clubhouse pages are touched. A native WordPress post is the theme's to
 * describe, and this plugin does not own its markup.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Seo_Head {

	/**
	 * Classes and constants that exist only when a dedicated SEO plugin is
	 * running. Checked by symbol rather than by plugin file, so a renamed folder
	 * or a bundled copy is still detected.
	 *
	 * @var array<int,string>
	 */
	private const RIVAL_CLASSES = array(
		'WPSEO_Options',         // Yoast SEO.
		'RankMath',              // Rank Math.
		'All_in_One_SEO_Pack',   // AIOSEO (legacy).
		'AIOSEO\\Plugin\\AIOSEO', // AIOSEO (current).
		'The_SEO_Framework\\Load',
		'SEOPress',
	);

	public static function register(): void {
		// Priority 1: before wp_head's own canonical (10) so ours is the one that
		// lands when we are the plugin doing this job.
		add_action( 'wp_head', array( self::class, 'render' ), 1 );
	}

	/**
	 * True when another SEO plugin is active and should be left to it.
	 *
	 * Pure given the symbol list, so the deference rule is testable: the check
	 * that matters is that a page emits NOTHING when a rival is present.
	 *
	 * @param array<int,string> $present Symbols this site actually has.
	 */
	public static function defers_to( array $present ): bool {
		foreach ( self::RIVAL_CLASSES as $symbol ) {
			if ( in_array( $symbol, $present, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** @return array<int,string> The rival symbols this WordPress install has loaded. */
	private static function present(): array {
		$found = array();
		foreach ( self::RIVAL_CLASSES as $symbol ) {
			if ( class_exists( $symbol ) ) {
				$found[] = $symbol;
			}
		}
		return $found;
	}

	public static function render(): void {
		if ( ! Blueworx_Clubhouse_Frontend::is_clubhouse_page() ) {
			return;
		}
		if ( self::defers_to( self::present() ) ) {
			return;
		}

		$ctx  = self::context();
		$tags = Blueworx_Clubhouse_Seo::tags( $ctx );

		echo "\n<link rel=\"canonical\" href=\"" . esc_url( $ctx['url'] ) . "\">\n";
		// Values are escaped inside render(); esc_html would double-encode them.
		echo Blueworx_Clubhouse_Seo::render( $tags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Seo::render.
	}

	/**
	 * The facts about this request that a search result or a social card needs.
	 *
	 * The description comes from the page's own opening copy — its hero lede —
	 * rather than a separate field: it is the sentence the owner already wrote to
	 * explain the page, and a second one to maintain would drift from it. The
	 * SEO report says when it is too short or too long to work as a description.
	 *
	 * @return array<string,mixed>
	 */
	private static function context(): array {
		$ctx      = Blueworx_Clubhouse_Frontend::context();
		$slug     = self::slug();
		$page     = '' === $slug ? 'home' : $slug;
		$club     = $ctx->branding->get_club_name();
		$logo     = Blueworx_Clubhouse_Frontend::resolve_logo( $ctx->branding->get_logo() );
		$lede     = (string) $ctx->content->get( $page, 'hero', 'lede', '' );
		$fallback = $ctx->content->get( $page, 'hero', 'title_lead', '' ) . $ctx->content->get( $page, 'hero', 'title_highlight', '' );

		return array(
			'title'        => Blueworx_Clubhouse_Frontend::page_title(),
			'description'  => '' !== trim( $lede ) ? $lede : (string) $fallback,
			'url'          => Blueworx_Clubhouse_Frontend::link_url( $page ),
			'site_name'    => $club,
			'type'         => 'website',
			'image'        => $logo,
			'image_width'  => 0,
			'image_height' => 0,
			'image_alt'    => '' !== $logo ? $club : '',
			'locale'       => str_replace( '-', '_', (string) get_bloginfo( 'language' ) ),
		);
	}

	private static function slug(): string {
		$qv = get_query_var( Blueworx_Clubhouse_Frontend::QUERY_VAR );
		return is_string( $qv ) ? $qv : '';
	}
}
