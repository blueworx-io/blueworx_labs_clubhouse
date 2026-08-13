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
 * The header and footer are absent on purpose: they are singleton blocks shown
 * on every page, not entries on any one page.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Addresses {

	/** @return array<string,array{type:string,position:int}> */
	public static function map(): array {
		$pages = array(
			'global' => array(
				'header' => 'header',
				'footer' => 'footer',
			),
			'home' => array(
				'hero'        => 'home_hero',
				'quick_tiles' => 'home_hero',
				'ticker'      => 'ticker',
				'sports'      => 'card_grid_switch',
				'clubhouse'   => 'image_band',
				'membership'  => 'band',
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
}
