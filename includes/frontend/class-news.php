<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The seam between the news templates and wherever the posts come from.
 *
 * Same shape as the Links and Menu seams: WordPress installs a source that
 * reads real posts, the preview installs the demo one, and anything that
 * installs neither renders the empty state rather than failing. That last case
 * is not hypothetical — the SEO report renders every page in-process, and it
 * has no business booting a post query to do it.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_News {

	/** How many posts a news page shows before it pages. */
	public const PER_PAGE = 6;

	/** How many posts the "keep reading" band under an article shows. */
	public const RELATED = 3;

	/** The query param the pager reads. */
	public const PAGE_PARAM = 'clubhouse_page_no';

	private static ?Blueworx_Clubhouse_Post_Source $source = null;

	/**
	 * The page of the archive being asked for.
	 *
	 * Held here rather than passed through the renderer, because every page shares
	 * one render signature and eight pages that never page would have to carry it.
	 * Same reasoning as the source itself.
	 */
	private static int $page = 1;

	public static function set_source( ?Blueworx_Clubhouse_Post_Source $source ): void {
		self::$source = $source;
	}

	public static function source(): ?Blueworx_Clubhouse_Post_Source {
		return self::$source;
	}

	/** Sanitise and remember a raw ?page value. Anything unreadable is page one. */
	public static function set_page( mixed $raw ): void {
		$page       = is_numeric( $raw ) ? (int) $raw : 1;
		self::$page = max( 1, $page );
	}

	public static function requested_page(): int {
		return self::$page;
	}

	/** Forget the source and the page — the tests and the preview start clean. */
	public static function reset(): void {
		self::$source = null;
		self::$page   = 1;
	}

	/**
	 * A news URL carrying a category and a page number. Both are dropped when they
	 * are the default, so the first page of everything is the bare /news/ address
	 * rather than /news/?category=&page=1.
	 */
	public static function url( string $category = '', int $page = 1 ): string {
		// The category rides on the same param the sports and events filters use,
		// so the swap-in-place filter script and the front end's own sanitiser both
		// apply here without a second mechanism to keep in step.
		$url = Blueworx_Clubhouse_Links::filtered_url( 'news', $category );
		if ( $page < 2 ) {
			return $url;
		}
		$sep = ( false !== strpos( $url, '?' ) ) ? '&' : '?';
		return $url . $sep . self::PAGE_PARAM . '=' . $page;
	}

	/**
	 * The page numbers to offer, given how many posts there are.
	 *
	 * Pure, and clamped: a ?page=99 on a two-page archive comes back as page 2
	 * rather than an empty grid with a "no posts" message, which reads as though
	 * the club has deleted everything.
	 *
	 * @return array{page:int,pages:int,offset:int}
	 */
	public static function paging( int $total, int $requested, int $per_page = self::PER_PAGE ): array {
		$per_page = max( 1, $per_page );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = min( max( 1, $requested ), $pages );
		return array(
			'page'   => $page,
			'pages'  => $pages,
			'offset' => ( $page - 1 ) * $per_page,
		);
	}
}
