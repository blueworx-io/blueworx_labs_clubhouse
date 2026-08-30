<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clubhouse Setup, declared to the page editor library.
 *
 * The slug is the one the hand-built screen used, so every link, every
 * submenu that names it as a parent, and every redirect still lands where it
 * did.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Editor {

	public const PAGE_SLUG = 'clubhouse-setup';

	/**
	 * The pages the Visibility tab offers a switch for: every page this site
	 * can actually serve, in Page_Map's own order. A page whose integration is
	 * absent is not offered at all — an owner should not be given a switch for
	 * a page that cannot render — and its stored state is left alone, so
	 * installing the integration later brings the page back exactly as it was.
	 *
	 * Home's slug is '' everywhere in Page_Map and 'home' everywhere
	 * visibility is stored; that one remap is why this is a method rather than
	 * a call to Page_Map::available() at each site.
	 *
	 * @return array<int,array{page:string,label:string}>
	 */
	public static function pages(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $page ) {
			$slug  = '' === $page['slug'] ? 'home' : (string) $page['slug'];
			$out[] = array( 'page' => $slug, 'label' => (string) $page['label'] );
		}
		return $out;
	}
}
