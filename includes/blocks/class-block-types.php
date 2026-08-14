<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every kind of block a page can be built from — one entry per renderer on
 * Sections. This is the single source of truth the page editor, the library,
 * the migration and the render loop all read, so none of them can disagree
 * about what a block is.
 *
 * 'rank' is the default position for a block of this type created fresh. It is
 * not the whole ordering story: a block carries its own position, because one
 * rank per type cannot reproduce a page like About, which runs the same type
 * either side of two others. See the design spec.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Types {

	/**
	 * @param string $key       Stable identifier, matching the Sections renderer.
	 * @param string $label     Owner-facing name.
	 * @param int    $rank      Default position for a fresh block of this type.
	 * @param string $source    'content', 'collection' or 'mixed'.
	 * @param bool   $singleton True for the header and footer only.
	 * @param string $requires  Shortcode tag of a required integration, or ''.
	 */
	private static function type( string $key, string $label, int $rank, string $source, bool $singleton = false, string $requires = '' ): array {
		return array(
			'key'       => $key,
			'label'     => $label,
			'rank'      => $rank,
			'source'    => $source,
			'singleton' => $singleton,
			'requires'  => $requires,
		);
	}

	/** @return array<string,array{key:string,label:string,rank:int,source:string,singleton:bool,requires:string}> */
	public static function all(): array {
		$types = array(
			self::type( 'header', 'Header', 0, 'content', true ),

			self::type( 'home_hero', 'Home hero', 100, 'mixed' ),
			self::type( 'hero', 'Hero', 100, 'content' ),
			self::type( 'hero_filter', 'Filtered hero', 100, 'mixed' ),
			self::type( 'news_head', 'News page head', 105, 'content' ),
			self::type( 'ticker', 'Ticker', 110, 'content' ),

			self::type( 'card_grid_switch', 'Sports and teams grid', 200, 'collection' ),
			self::type( 'news_featured', 'Featured story', 205, 'collection' ),
			self::type( 'timeline', 'Timeline', 210, 'content' ),
			self::type( 'benefit_grid', 'Benefit grid', 220, 'content' ),
			self::type( 'image_band', 'Image band', 230, 'content' ),
			self::type( 'tier_grid', 'Membership tiers', 240, 'content' ),
			self::type( 'list_split', 'Included and excluded', 250, 'content' ),
			self::type( 'step_grid', 'Steps', 260, 'content' ),
			self::type( 'faq', 'FAQ', 270, 'content' ),
			self::type( 'activity_tabs', 'Fixtures, results and events', 280, 'collection' ),
			self::type( 'stat_card_grid', 'Directory cards', 290, 'collection' ),
			self::type( 'event_grid', 'Upcoming events', 300, 'collection' ),
			self::type( 'event_archive', 'Past events', 310, 'collection' ),
			self::type( 'calendar_months', 'Calendar', 320, 'collection' ),
			self::type( 'news_cards', 'News cards', 330, 'collection' ),
			self::type( 'news_grid', 'All stories', 335, 'collection' ),
			self::type( 'people_grid', 'People', 340, 'collection' ),
			self::type( 'contact_form', 'Contact form', 350, 'content' ),
			self::type( 'info_panel', 'Find us details', 360, 'content' ),
			self::type( 'sponsors', 'Sponsors', 370, 'collection' ),
			self::type( 'auth', 'Log in form', 390, 'content' ),
			self::type( 'prose', 'Document text', 395, 'content' ),
			self::type( 'band', 'Call to action band', 400, 'content' ),
			self::type( 'closing_band', 'Social band', 410, 'mixed' ),

			// A booking slot is a heading plus a third-party shortcode; without
			// LatePoint installed there is nothing to put in it, so the type is
			// not offered at all. Same rule Integrations::section_available applies.
			self::type( 'shortcode_block', 'Booking slot', 380, 'content', false, Blueworx_Clubhouse_Integrations::LATEPOINT_TAG ),

			self::type( 'footer', 'Footer', 500, 'content', true ),
		);

		$keyed = array();
		foreach ( $types as $type ) {
			$keyed[ $type['key'] ] = $type;
		}
		return $keyed;
	}

	/** @return array{key:string,label:string,rank:int,source:string,singleton:bool,requires:string}|null */
	public static function get( string $key ): ?array {
		return self::all()[ $key ] ?? null;
	}

	public static function has( string $key ): bool {
		return isset( self::all()[ $key ] );
	}

	/** The default position for a fresh block of this type; last for an unknown key. */
	public static function rank( string $key ): int {
		return (int) ( self::all()[ $key ]['rank'] ?? 500 );
	}
}
