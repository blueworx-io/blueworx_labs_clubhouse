<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where every piece of content the plugin ships lives today: which block type
 * renders it, and where on its page it sits. Read by the seeder and the
 * migration, so a club upgrading gets exactly the site it had.
 *
 * Positions step by ten within a page — the running order the page methods in
 * Page_Renderer produce — leaving room to slot something between two later.
 *
 * The map holds one entry per section a page visibility toggle controls,
 * including the header and footer — which are entries here too, singleton
 * blocks shown on every page rather than sections of any one page. Some
 * addresses share a rendered block with another address on the same page;
 * see folds() for which.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Addresses {

	/** @return array<string,array{type:string,position:int}> */
	public static function map(): array {
		$pages = array(
			'global' => array(
				'header'  => 'header',
				'footer'  => 'footer',
				// Rendered by the footer block, like the footer's own newsletter
				// column — it is not a block anyone places on a page.
				'cookies' => 'footer',
				// Prose in shape — a heading and paragraphs — but never placed on
				// a clubhouse page: it renders on the shop's customer dashboard,
				// which this plugin does not own. It is here because it is an
				// address a visibility toggle controls, which is what this map
				// enumerates, and a migration must not try to seed it onto a page.
				'welcome' => 'prose',
			),
			'home' => array(
				'hero'        => 'home_hero',
				'quick_tiles' => 'home_hero',
				'ticker'      => 'ticker',
				'sports'      => 'card_grid_switch',
				'clubhouse'   => 'image_band',
				'membership'  => 'band',
				// Mirrors the Membership page's tiers — the same tier content
				// rendered twice, with the Home copy's CTAs pointed at the
				// Membership page. A later migration must put the SAME block
				// on both pages, not create a second one.
				'tiers'       => 'tier_grid',
				'activity'    => 'activity_tabs',
				'news'        => 'news_cards',
				'sponsors'    => 'sponsors',
				'social'      => 'closing_band',
				'info'        => 'closing_band',
			),
			'about' => array(
				'hero'         => 'hero',
				'history'      => 'timeline',
				'values'       => 'benefit_grid',
				'facilities'   => 'image_band',
				'committee'    => 'people_grid',
				'get_involved' => 'benefit_grid',
				'cta'          => 'band',
			),
			'membership' => array(
				'hero'   => 'hero',
				'tiers'  => 'tier_grid',
				'why'    => 'benefit_grid',
				'detail' => 'list_split',
				'steps'  => 'step_grid',
				'faq'    => 'faq',
				'cta'    => 'band',
			),
			'contact' => array(
				'hero'      => 'hero',
				'form'      => 'contact_form',
				'directory' => 'people_grid',
				'social'    => 'closing_band',
			),
			'login' => array(
				'form' => 'auth',
			),
			'news' => array(
				'head'     => 'news_head',
				'featured' => 'news_featured',
				'posts'    => 'news_grid',
			),
			'sports' => array(
				'hero'      => 'hero_filter',
				'directory' => 'stat_card_grid',
				'cta'       => 'band',
			),
			'teams' => array(
				'hero'      => 'hero_filter',
				'directory' => 'stat_card_grid',
				'cta'       => 'band',
			),
			'events' => array(
				'hero'     => 'hero_filter',
				'upcoming' => 'event_grid',
				'past'     => 'event_archive',
				'cta'      => 'band',
			),
			'calendar' => array(
				'hero'     => 'hero_filter',
				'booking'  => 'shortcode_block',
				'schedule' => 'calendar_months',
				'cta'      => 'band',
			),
			'booking' => array(
				'hero'      => 'hero',
				'services'  => 'shortcode_block',
				'locations' => 'shortcode_block',
				'agents'    => 'shortcode_block',
			),
			'privacy' => array(
				'hero' => 'hero',
				'body' => 'prose',
			),
			'terms' => array(
				'hero' => 'hero',
				'body' => 'prose',
			),
		);

		$out = array();
		foreach ( $pages as $page => $sections ) {
			$position = 0;
			foreach ( $sections as $section => $type ) {
				$position         += 10;
				$out[ $page . '/' . $section ] = array(
					'type'     => $type,
					'position' => $position,
				);
			}
		}
		return $out;
	}

	/** The block type an address renders as, or '' when the address is unknown. */
	public static function type( string $address ): string {
		return (string) ( self::map()[ $address ]['type'] ?? '' );
	}

	/**
	 * Addresses whose block carries no anchor id of its own.
	 *
	 * The Home tier grid has never had one: it is the Membership page's tiers
	 * shown a second time, and an owner links to the tiers on the page that
	 * sells them. Stamping one on now would be a new id in the markup, which is
	 * exactly what the parity check exists to catch.
	 *
	 * @return array<int,string>
	 */
	public static function unanchored(): array {
		return array( 'home/tiers' );
	}

	/**
	 * Addresses whose content renders inside another address's block rather than
	 * as a section of its own — folded address => the address it renders inside.
	 * `home/quick_tiles` renders inside `home/hero`'s home_hero block, and
	 * `home/info` renders inside `home/social`'s closing_band. Both sides of a
	 * fold still appear in map(), so a migration seeding from map() at face value
	 * must consult this first or it will create duplicate sections and break
	 * existing anchors such as `#ch-home-quick-tiles`.
	 *
	 * @return array<string,string>
	 */
	public static function folds(): array {
		return array(
			'home/quick_tiles' => 'home/hero',
			'home/info'        => 'home/social',
		);
	}
}
