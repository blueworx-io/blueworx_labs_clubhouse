<?php
// includes/import/class-import-sections.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Works out which sections an import should switch on and which it should
 * switch off, so an owner who imports their own content is not left with demo
 * sections still showing beneath it.
 *
 * Pure: it reads the plan and the catalogue and returns a list of visibility
 * changes. Nothing here writes — the applier does that.
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
	 * The visibility page that owns the catalogue's "Global" tab. The tab holds
	 * every Home-page section, so its visibility keys live under 'home' —
	 * Setup_Sections::MAP is the authority and lists them there.
	 */
	private const GLOBAL_TAB_PAGE = 'home';

	/**
	 * Every visibility change this plan implies, in catalogue order.
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
	 * The sections an import would actually switch off — the ones showing today
	 * that the file has no content for. Named for a human, for the preview:
	 * switching a section on is self-evident from the content rows above it,
	 * switching one off is not, and a section the owner already hid is not news.
	 *
	 * @return array<int,string>
	 */
	public static function switching_off( Blueworx_Clubhouse_Import_Plan $plan, Blueworx_Clubhouse_Storage $storage ): array {
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$labels     = array();
		foreach ( self::changes( $plan ) as $change ) {
			if ( ! $change['visible'] && $visibility->is_section_visible( $change['page'], $change['section'] ) ) {
				$labels[] = $change['label'];
			}
		}
		return $labels;
	}

	/**
	 * Write the changes. Returns how many sections were switched on and off,
	 * counting only real changes so the result never claims to have moved a
	 * toggle that was already where the import wanted it.
	 *
	 * @return array{on:int,off:int}
	 */
	public static function apply( Blueworx_Clubhouse_Import_Plan $plan, Blueworx_Clubhouse_Storage $storage ): array {
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$on         = 0;
		$off        = 0;
		foreach ( self::changes( $plan ) as $change ) {
			if ( $visibility->is_section_visible( $change['page'], $change['section'] ) === $change['visible'] ) {
				continue;
			}
			$visibility->set_section_visible( $change['page'], $change['section'], $change['visible'] );
			if ( $change['visible'] ) {
				++$on;
				continue;
			}
			++$off;
		}
		return array( 'on' => $on, 'off' => $off );
	}
}
