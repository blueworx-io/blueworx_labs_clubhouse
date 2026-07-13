<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declarative catalogue of the visibility-toggleable sections per page, for the
 * Clubhouse Setup screen. Pure: page labels come from Page_Map; the section
 * keys are the exact keys the renderers gate on via Visibility::is_section_visible.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Sections {

	/** @var array<string, array<string,string>> page-slug => (section-key => label) */
	private const MAP = array(
		'home' => array(
			'header'      => 'Header',
			'hero'        => 'Hero',
			'quick_tiles' => 'Quick tiles',
			'ticker'      => 'Ticker',
			'stats'       => 'Stats',
			'sports'      => 'Sports grid',
			'clubhouse'   => 'Clubhouse band',
			'membership'  => 'Membership tiers',
			'activity'    => 'Activity tabs',
			'news'        => 'News',
			'info'        => 'Info strip',
			'sponsors'    => 'Sponsors',
			'social'      => 'Social',
			'footer'      => 'Footer',
		),
		'about' => array(
			'hero'       => 'Hero',
			'history'    => 'History',
			'values'     => 'Values',
			'committee'  => 'Committee',
			'facilities' => 'Facilities',
			'cta'        => 'Call to action',
		),
		'membership' => array(
			'hero'   => 'Hero',
			'why'    => 'Why join',
			'tiers'  => 'Tiers',
			'detail' => 'Included / excluded',
			'steps'  => 'How to join',
			'faq'    => 'FAQ',
			'cta'    => 'Call to action',
		),
		'contact' => array(
			'hero'      => 'Hero',
			'form'      => 'Contact form',
			'directory' => 'Directory',
			'social'    => 'Social',
		),
		'login' => array(
			'form' => 'Login form',
		),
		'sports' => array(
			'hero'      => 'Hero',
			'directory' => 'Sports directory',
			'cta'       => 'Call to action',
		),
		'teams' => array(
			'hero'      => 'Hero',
			'directory' => 'Teams directory',
			'cta'       => 'Call to action',
		),
		'events' => array(
			'hero'     => 'Hero',
			'upcoming' => 'Upcoming events',
			'past'     => 'Past events',
			'cta'      => 'Call to action',
		),
		'calendar' => array(
			'hero'     => 'Hero',
			'schedule' => 'Schedule',
			'cta'      => 'Call to action',
		),
	);

	/**
	 * @return array<int, array{page:string,label:string,sections:array<int,array{key:string,label:string}>}>
	 */
	public static function inventory(): array {
		$labels = array();
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			$slug            = '' === $page['slug'] ? 'home' : $page['slug'];
			$labels[ $slug ] = $page['label'];
		}

		$out = array();
		foreach ( self::MAP as $page => $sections ) {
			$section_list = array();
			foreach ( $sections as $key => $label ) {
				$section_list[] = array( 'key' => $key, 'label' => $label );
			}
			$out[] = array(
				'page'     => $page,
				'label'    => $labels[ $page ] ?? ucfirst( $page ),
				'sections' => $section_list,
			);
		}
		return $out;
	}
}
