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

	/**
	 * The capability the Collections menu is registered with. Named so the access
	 * registry can report on this page from the same value that gates it, rather
	 * than a second copy that could drift.
	 */
	public const CONTENT_CAP = 'edit_posts';

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
		'clubhouse_person'  => array( 'committee_role', 'directory_role', 'email', 'photo' ),
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
				// Under the Clubhouse menu, beside everything else a club
				// edits. The six used to nest under a "Collections" menu of
				// their own, which meant two Clubhouse menus in the sidebar
				// and two plausible places to look for a fixture.
				'show_in_menu' => Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG,
				'menu_icon'    => 'dashicons-groups',
				'supports'     => array( 'title', 'page-attributes' ),
				'has_archive'  => false,
				'rewrite'      => false,
			) );
			foreach ( self::META[ $type ] as $key ) {
				// The address the page editor library reads and writes. The
				// bare key these used to be registered under is left
				// unregistered on purpose: it still holds the value it held
				// before the migration, and nothing reads it.
				register_post_meta( $type, Blueworx_Clubhouse_Collection_Meta::meta_key( $type, $key ), array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => false,
					'default'      => '',
				) );
			}
		}
	}

}
