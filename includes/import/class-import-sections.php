<?php
// includes/import/class-import-sections.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Works out which blocks an import should put on a page and which it should
 * take off, so an owner who imports their own content is not left with demo
 * sections still showing beneath it.
 *
 * changes() is pure: it reads the plan and the catalogue and says what the file
 * covered. Only switching_off() and apply() go near the club's own blocks.
 *
 * Two rules keep it from over-reaching:
 *
 * - Only pages the file actually touched are considered. The prompt encourages
 *   importing a tab at a time, so a file covering only Home must leave About
 *   exactly as the owner left it.
 * - The two Global-stored sections (Header and Footer) are never switched off
 *   and never count as touching a page. They are site chrome, not page content;
 *   a file that happens not to mention the header must not take the header off
 *   the site.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Sections {

	/**
	 * The content-store page whose sections are chrome rather than page content.
	 */
	private const CHROME_STORE_PAGE = 'global';

	/**
	 * The page that owns the catalogue's "Global" tab. The tab holds every
	 * Home-page section, so its entries are keyed under 'home'.
	 */
	private const GLOBAL_TAB_PAGE = 'home';

	/**
	 * Every block move this plan implies, in catalogue order. `visible` is
	 * whether the file covered that section, not whether the site shows it.
	 *
	 * @return array<int,array{page:string,section:string,label:string,visible:bool}>
	 */
	public static function changes( Blueworx_Clubhouse_Import_Plan $plan ): array {
		$by_page = array();

		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $tab ) {
			$page = (string) $tab['tab'];
			$page = 'global' === $page ? self::GLOBAL_TAB_PAGE : $page;

			foreach ( $tab['sections'] as $section ) {
				if ( self::CHROME_STORE_PAGE === (string) $section['store_page'] ) {
					continue;
				}
				$by_page[ $page ][] = array(
					'page'    => $page,
					'section' => (string) $section['key'],
					'label'   => Blueworx_Clubhouse_Content_Catalogue::address_label(
						(string) $section['store_page'] . '/' . (string) $section['key']
					),
					'visible' => self::is_covered( $plan, $section ),
				);
			}
		}

		$changes = array();
		foreach ( $by_page as $sections ) {
			$touched = false;
			foreach ( $sections as $entry ) {
				$touched = $touched || $entry['visible'];
			}
			if ( ! $touched ) {
				continue;
			}
			$changes = array_merge( $changes, $sections );
		}
		return $changes;
	}

	/**
	 * Did the file supply anything this section renders? A section with editable
	 * fields is covered when the plan carries a field, a list of items or an
	 * image for it. A section that renders a collection instead (Sponsors, the
	 * Committee, the directories, the activity tabs) is covered when the file
	 * supplied that collection — those sections have nothing of their own to
	 * fill in, so their collection is the only honest signal.
	 *
	 * @param array<string,mixed> $section
	 */
	private static function is_covered( Blueworx_Clubhouse_Import_Plan $plan, array $section ): bool {
		$page = (string) $section['store_page'];
		$key  = (string) $section['key'];

		if ( isset( $plan->fields()[ $page ][ $key ] ) || isset( $plan->items()[ $page ][ $key ] ) ) {
			return true;
		}
		foreach ( $plan->images() as $image ) {
			if ( $page === ( $image['page'] ?? '' ) && $key === ( $image['section'] ?? '' ) ) {
				return true;
			}
		}

		$cpt = '';
		if ( isset( $section['link']['cpt'] ) ) {
			$cpt = (string) $section['link']['cpt'];
		} elseif ( isset( $section['auto']['cpt'] ) ) {
			$cpt = (string) $section['auto']['cpt'];
		}
		return '' !== $cpt && isset( $plan->collections()[ $cpt ] );
	}

	/**
	 * The blocks an import would actually take off a page — the ones showing
	 * today that the file has no content for. Named for a human, for the
	 * preview: putting a block on a page is self-evident from the content rows
	 * above it, taking one off is not, and a block the owner had already removed
	 * is not news.
	 *
	 * @return array<int,string>
	 */
	public static function switching_off( Blueworx_Clubhouse_Import_Plan $plan, Blueworx_Clubhouse_Storage $storage ): array {
		$library     = new Blueworx_Clubhouse_Block_Library( $storage );
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );
		$labels      = array();
		foreach ( self::changes( $plan ) as $change ) {
			$id = self::block_for( $change, $library );
			if ( '' === $id || $change['visible'] ) {
				continue;
			}
			if ( in_array( $change['page'], $composition->uses( $id ), true ) ) {
				$labels[] = $change['label'];
			}
		}
		return $labels;
	}

	/**
	 * Write the changes: a block whose section the file filled goes onto its
	 * page, one it did not comes off. The block stays in the library either way,
	 * so a club's words survive an import that did not mention them.
	 *
	 * Counts only real moves, so the result never claims to have changed a page
	 * that was already the way the import wanted it.
	 *
	 * @return array{on:int,off:int}
	 */
	public static function apply( Blueworx_Clubhouse_Import_Plan $plan, Blueworx_Clubhouse_Storage $storage ): array {
		$library     = new Blueworx_Clubhouse_Block_Library( $storage );
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );
		$on          = 0;
		$off         = 0;
		foreach ( self::changes( $plan ) as $change ) {
			$id = self::block_for( $change, $library );
			if ( '' === $id ) {
				continue;
			}
			$page = $change['page'];
			if ( in_array( $page, $composition->uses( $id ), true ) === $change['visible'] ) {
				continue;
			}
			if ( $change['visible'] ) {
				$composition->add( $page, $id );
				++$on;
				continue;
			}
			$composition->remove( $page, $id );
			++$off;
		}

		// The Home tier grid has never had a switch of its own: it appears with
		// the membership band above it and goes with it, which is how the page
		// has always behaved.
		foreach ( self::changes( $plan ) as $change ) {
			if ( 'home' !== $change['page'] || 'membership' !== $change['section'] ) {
				continue;
			}
			$tiers = $library->by_address( 'home/tiers' );
			if ( '' === $tiers || in_array( 'home', $composition->uses( $tiers ), true ) === $change['visible'] ) {
				continue;
			}
			if ( $change['visible'] ) {
				$composition->add( 'home', $tiers );
				continue;
			}
			$composition->remove( 'home', $tiers );
		}

		return array( 'on' => $on, 'off' => $off );
	}

	/**
	 * The block a change moves, or '' when this site has none for it.
	 *
	 * A folded address has no block of its own — Home's find-us columns are part
	 * of the closing band — so it cannot be moved on or off a page separately
	 * and is skipped here.
	 *
	 * @param array{page:string,section:string,label:string,visible:bool} $change
	 */
	private static function block_for( array $change, Blueworx_Clubhouse_Block_Library $library ): string {
		$address = $change['page'] . '/' . $change['section'];
		if ( Blueworx_Clubhouse_Block_Addresses::host( $address ) !== $address ) {
			return '';
		}
		return $library->by_address( $address );
	}
}
