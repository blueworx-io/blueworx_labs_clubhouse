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
		// Priority 1: ahead of core's own canonical at 10, so render() can take
		// that one off before it runs when this is a page we speak for.
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

	/**
	 * Whether this page should tell a search engine to leave it out of results —
	 * a filtered view (a convenience URL for the same page) or a private page
	 * (nothing here for a signed-out visitor, so nothing worth ranking). Pure, so
	 * the rule is testable without a WordPress runtime under render().
	 */
	public static function noindex( bool $filtered_view, bool $private_page ): bool {
		return $filtered_view || $private_page;
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

		// Now that a club page is a real WordPress page, core emits a canonical
		// of its own at priority 10. Two canonicals is worse than either alone —
		// a crawler is entitled to ignore both — and ours is the one that knows a
		// filtered view should point at the page it is a view of. So core's comes
		// off, here rather than in register(), where we do not yet know whether
		// this is our page or whether a rival SEO plugin has the job.
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}

		// A filtered view is the same page with some of it hidden — a convenience
		// for a reader, not a page of its own. Every one of them produced a
		// crawlable address (/sports/?clubhouse_filter=hockey) sharing the title
		// and canonical of the unfiltered page. The canonical already pointed the
		// right way; this stops the filtered address being indexed in its own
		// right, while still letting a crawler follow the links on it.
		//
		// A private page (the member area) is the same idea for a different
		// reason: Page_Map marks it members-only, but marking it is not the same
		// as telling a search engine to leave it alone — without this a page with
		// nothing for a signed-out visitor still showed up in results.
		if ( self::noindex( self::is_filtered_view(), Blueworx_Clubhouse_Page_Map::is_private( self::slug() ) ) ) {
			echo "\n<meta name=\"robots\" content=\"noindex, follow\">\n";
		}

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
		return array(
			'title'        => Blueworx_Clubhouse_Frontend::page_title(),
			'description'  => self::description( $ctx, $page, $club ),
			// A sport or team page is its own address, not the listing's. Pointing
			// its canonical at /sports/ told search engines the page was a duplicate
			// of the list and to drop it — the opposite of what it is for.
			'url'          => self::canonical_url( $slug, $page ),
			'site_name'    => $club,
			'type'         => 'website',
			'image'        => $logo,
			'image_width'  => 0,
			'image_height' => 0,
			'image_alt'    => '' !== $logo ? $club : '',
			'locale'       => str_replace( '-', '_', (string) get_bloginfo( 'language' ) ),
		);
	}

	/**
	 * The best description this page can offer, in order of how specific it is.
	 *
	 * A club that has not written its own copy yet still has pages, and a page
	 * with no description at all is the one case a search engine handles worst —
	 * it invents one from whatever text it finds first. So the chain ends
	 * somewhere real rather than empty: the page's own opening sentence, then its
	 * heading, then the site's tagline, then the club's name. The report says when
	 * what came out is too thin to work as a description, which is the honest way
	 * to ask an owner for better copy.
	 */
	public static function description( Blueworx_Clubhouse_Clubhouse_Context $ctx, string $page, string $club ): string {
		$candidates = array(
			(string) $ctx->content->get( $page, 'hero', 'lede', '' ),
			trim(
				(string) $ctx->content->get( $page, 'hero', 'title_lead', '' ) . ' '
				. (string) $ctx->content->get( $page, 'hero', 'title_highlight', '' )
			),
			(string) get_bloginfo( 'description' ),
			$club,
		);
		foreach ( $candidates as $candidate ) {
			if ( '' !== trim( $candidate ) ) {
				return $candidate;
			}
		}
		return '';
	}

	private static function slug(): string {
		// The one answer to "which club page is this request?", which knows both
		// the old query-var form and a real page reached at its own address.
		// Reading the query var here instead made every club page describe
		// itself as the home page once the pages became real.
		return (string) ( Blueworx_Clubhouse_Frontend::current_page_slug() ?? '' );
	}

	/**
	 * Whether a filter is narrowing what this page shows. Read from the request
	 * rather than the rendered output, so the decision is made before any markup
	 * exists to inspect.
	 */
	private static function is_filtered_view(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter, no state change.
		$raw = $_GET[ Blueworx_Clubhouse_Links::FILTER_PARAM ] ?? '';
		return is_string( $raw ) && '' !== Blueworx_Clubhouse_Frontend::sanitize_filter( wp_unslash( $raw ) );
	}

	/**
	 * The address this page should claim as its own: a sport or team page claims
	 * itself, everything else claims its listing (which is what makes a filtered
	 * view consolidate rather than compete).
	 */
	private static function canonical_url( string $slug, string $page ): string {
		$item = Blueworx_Clubhouse_Frontend::current_item();
		if ( '' !== $item && '' !== Blueworx_Clubhouse_Frontend::current_item_title( $slug ) ) {
			return Blueworx_Clubhouse_Frontend::item_link_url( $slug, $item );
		}
		return Blueworx_Clubhouse_Frontend::link_url( $page );
	}
}
