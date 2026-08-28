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
			'cookies'     => 'Cookie notice',
			'header'      => 'Header',
			'hero'        => 'Hero',
			'quick_tiles' => 'Quick tiles',
			'ticker'      => 'Ticker',
			'sports'      => 'Sports grid',
			'clubhouse'   => 'Clubhouse band',
			'membership'  => 'Membership tiers',
			'activity'    => 'Activity tabs',
			'news'        => 'News',
			'social_feed' => 'Social feed',
			'info'        => 'Find us details',
			'sponsors'    => 'Sponsors',
			'social'      => 'Social',
			'footer'      => 'Footer',
			// Sitewide rather than Home, like the header, footer and cookie notice
			// above it: those live here too because the visibility inventory is
			// keyed by the pages this plugin serves, and none of the four belong
			// to one page.
			'welcome'     => 'Welcome pack',
		),
		'about' => array(
			'hero'         => 'Hero',
			'history'      => 'History',
			'values'       => 'Values',
			'facilities'   => 'Facilities',
			'committee'    => 'Committee',
			'get_involved' => 'Get involved',
			'cta'          => 'Call to action',
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
		'news' => array(
			'head'     => 'Page head',
			'featured' => 'Featured story',
			'posts'    => 'All stories',
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
			'booking'  => 'Bookings',
			'schedule' => 'Schedule',
			'cta'      => 'Call to action',
		),
		'booking' => array(
			'hero'      => 'Hero',
			'services'  => 'Sessions and services',
			'locations' => 'Courts and locations',
			'agents'    => 'Coaches and staff',
		),
		// No sections of its own — the panels inside it belong to the shop and
		// the booking plugin. The page switch is the point: a club that does not
		// want a member area can take the address off.
		'member-dashboard' => array(),
		'privacy' => array(
			'hero' => 'Hero',
			'body' => 'Policy',
		),
		'terms' => array(
			'hero' => 'Hero',
			'body' => 'Terms',
		),
		'rules' => array(
			'hero' => 'Hero',
			'body' => 'Rules',
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
			// A page whose integration is absent is not offered here at all — an
			// owner should not be given show/hide switches for sections that cannot
			// render. Their stored state is left alone, so installing the plugin
			// later brings the page back exactly as it was configured.
			if ( ! Blueworx_Clubhouse_Page_Map::is_available( 'home' === $page ? '' : $page ) ) {
				continue;
			}
			$section_list = array();
			foreach ( $sections as $key => $label ) {
				// A section needing an absent integration is not offered either.
				if ( ! Blueworx_Clubhouse_Integrations::section_available( $page, $key ) ) {
					continue;
				}
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
