<?php
// includes/collections/class-collection-types.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the six collection custom post types and their meta keys. Editing UI
 * (custom-field meta boxes) is the admin-flow plan's job; these register with a
 * basic admin UI so seeded posts are visible/manageable.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Types {

	public const CONTENT_SLUG = 'clubhouse-content';

	public const POST_TYPES = array(
		'clubhouse_sport',
		'clubhouse_team',
		'clubhouse_fixture',
		'clubhouse_event',
		'clubhouse_sponsor',
		'clubhouse_person',
	);

	/** @var array<string,array<int,string>> meta keys per type */
	private const META = array(
		'clubhouse_sport'   => array( 'label', 'subtitle', 'description', 'stat1_value', 'stat1_label', 'stat2_value', 'stat2_label', 'image' ),
		'clubhouse_team'    => array( 'sport', 'description', 'match_day', 'league', 'image' ),
		'clubhouse_fixture' => array( 'sport', 'match_date', 'kickoff_time', 'venue', 'home_team', 'away_team', 'score', 'outcome', 'result_summary' ),
		'clubhouse_event'   => array( 'tag', 'date', 'detail', 'cta_label', 'cta_href', 'status' ),
		'clubhouse_sponsor' => array( 'url' ),
		'clubhouse_person'  => array( 'committee_role', 'directory_role', 'email' ),
	);

	private const LABELS = array(
		'clubhouse_sport'   => array( 'Sport', 'Sports' ),
		'clubhouse_team'    => array( 'Team', 'Teams' ),
		'clubhouse_fixture' => array( 'Fixture', 'Fixtures' ),
		'clubhouse_event'   => array( 'Event', 'Events' ),
		'clubhouse_sponsor' => array( 'Sponsor', 'Sponsors' ),
		'clubhouse_person'  => array( 'Person', 'People' ),
	);

	public static function register(): void {
		foreach ( self::POST_TYPES as $type ) {
			list( $singular, $plural ) = self::LABELS[ $type ];
			register_post_type( $type, array(
				'labels'       => array( 'name' => $plural, 'singular_name' => $singular ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => self::CONTENT_SLUG,
				'menu_icon'    => 'dashicons-groups',
				'supports'     => array( 'title', 'page-attributes' ),
				'has_archive'  => false,
				'rewrite'      => false,
			) );
			foreach ( self::META[ $type ] as $key ) {
				register_post_meta( $type, $key, array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => false,
					'default'      => '',
				) );
			}
		}
	}

	/**
	 * Registers the top-level "Content" menu the six CPTs nest under, and removes
	 * the auto-created duplicate submenu so the parent link opens the first CPT.
	 * Hooked on admin_menu.
	 */
	public static function register_content_menu(): void {
		add_menu_page( 'Content', 'Content', 'edit_posts', self::CONTENT_SLUG, '', 'dashicons-clipboard', 4 );
		remove_submenu_page( self::CONTENT_SLUG, self::CONTENT_SLUG );
	}
}
