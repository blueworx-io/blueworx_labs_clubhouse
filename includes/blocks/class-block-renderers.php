<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One renderer per block type: given a block's own content and the page it is
 * being rendered on, return the section's markup by calling Sections.
 *
 * This is the half of the old page methods that was not default copy. Nothing
 * here decides whether a block appears — that is the page's composition — and
 * nothing here reaches for global state beyond the seams the old methods
 * already used (the shop, the news source, the signed-in state).
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Renderers {

	/**
	 * Render one block. Returns '' for a type with nothing to draw, which the
	 * composer treats as "not on the page".
	 *
	 * @param array<string,mixed> $block
	 */
	public static function render( array $block, Blueworx_Clubhouse_Page_State $state ): string {
		$type     = (string) ( $block['type'] ?? '' );
		$content  = (array) ( $block['content'] ?? array() );
		$settings = (array) ( $block['settings'] ?? array() );
		$defaults = Blueworx_Clubhouse_Block_Defaults::for_key( (string) ( $block['defaults_key'] ?? '' ), $state );

		switch ( $type ) {
			case 'home_hero':
				return self::home_hero( $content, $defaults, $state );
			case 'hero':
				return self::hero( $content, $defaults );
			case 'hero_filter':
				return self::hero_filter( $content, $defaults, $state );
			case 'ticker':
				return self::ticker( $content, $defaults );
			case 'card_grid_switch':
				return self::card_grid_switch( $content, $defaults, $state );
			case 'image_band':
				return self::image_band( $content, $defaults );
			case 'band':
				return self::band( $content, $defaults );
			case 'tier_grid':
				return self::tier_grid( $state );
			case 'activity_tabs':
				return self::activity_tabs( $content, $defaults, $state );
			case 'news_cards':
				return self::news_cards( $content, $defaults, $state );
			case 'sponsors':
				return self::sponsors( $content, $defaults, $state );
			case 'closing_band':
				return self::closing_band( $content, $defaults, $settings, $state );
			case 'timeline':
				return self::timeline( $content, $defaults );
			case 'benefit_grid':
				return self::benefit_grid( $content, $defaults );
			case 'people_grid':
				return self::people_grid( $content, $defaults, $state );
			case 'list_split':
				return self::list_split( $content, $defaults );
			case 'step_grid':
				return self::step_grid( $content, $defaults );
			case 'faq':
				return self::faq( $content, $defaults );
			case 'stat_card_grid':
				return self::stat_card_grid( $content, $defaults, $state );
			case 'event_grid':
				return self::event_grid( $content, $defaults, $state );
			case 'event_archive':
				return self::event_archive( $content, $defaults, $state );
			case 'calendar_months':
				return self::calendar_months( $content, $defaults, $state );
			case 'news_head':
				return self::news_head( $content, $defaults );
			case 'news_featured':
				return self::news_featured( $content, $defaults, $state );
			case 'news_grid':
				return self::news_grid( $content, $defaults, $state );
			case 'contact_form':
				return self::contact_form( $content, $defaults, $state );
			case 'auth':
				return self::auth( $content, $defaults );
			case 'shortcode_block':
				return self::shortcode_block( $content, $defaults );
			case 'prose':
				return self::prose( $content, $defaults );
			case 'info_panel':
				return self::info_panel( $content, $defaults );
		}
		return '';
	}

	/** @param array<string,mixed> $c */
	private static function f( array $c, array $d, string $key, mixed $fallback = '' ): string {
		return Blueworx_Clubhouse_Block_Content::text( $c, $d, $key, $fallback );
	}

	/** @return array<int,array<string,mixed>> */
	private static function items( array $c, array $d ): array {
		return Blueworx_Clubhouse_Block_Content::items( $c, $d );
	}

	// -- Heroes ---------------------------------------------------------------

	private static function home_hero( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		return Blueworx_Clubhouse_Sections::home_hero( array(
			'eyebrow'            => self::f( $c, $d, 'eyebrow' ),
			'title_lead'         => self::f( $c, $d, 'title_lead' ),
			'title_highlight'    => self::f( $c, $d, 'title_highlight' ),
			'lede'               => self::f( $c, $d, 'lede' ),
			'cta_primary'        => self::f( $c, $d, 'cta_primary' ),
			'cta_primary_href'   => self::f( $c, $d, 'cta_primary_href' ),
			'cta_secondary'      => self::f( $c, $d, 'cta_secondary' ),
			'cta_secondary_href' => self::f( $c, $d, 'cta_secondary_href' ),
			'image'              => Blueworx_Clubhouse_Block_Content::media_src( self::f( $c, $d, 'image' ) ),
			'image_alt'          => self::f( $c, $d, 'image_alt' ),
			'tiles_id'           => self::f( $c, $d, 'tiles_id' ),
			'tiles'              => self::items( $c, $d ),
		) );
	}

	private static function hero( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow'            => self::f( $c, $d, 'eyebrow' ),
			'title_lead'         => self::f( $c, $d, 'title_lead' ),
			'title_highlight'    => self::f( $c, $d, 'title_highlight' ),
			'lede'               => self::f( $c, $d, 'lede' ),
			'cta_primary'        => self::f( $c, $d, 'cta_primary' ),
			'cta_primary_href'   => self::f( $c, $d, 'cta_primary_href' ),
			'cta_secondary'      => self::f( $c, $d, 'cta_secondary' ),
			'cta_secondary_href' => self::f( $c, $d, 'cta_secondary_href' ),
			'image'              => Blueworx_Clubhouse_Block_Content::media_src( self::f( $c, $d, 'image' ) ),
			'image_alt'          => self::f( $c, $d, 'image_alt' ),
			'image_caption'      => self::f( $c, $d, 'image_caption' ),
		) );
	}

	private static function hero_filter( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$label = self::f( $c, $d, 'filter_label' );
		return Blueworx_Clubhouse_Sections::hero_filter( array(
			'eyebrow'         => self::f( $c, $d, 'eyebrow' ),
			'title_lead'      => self::f( $c, $d, 'title_lead' ),
			'title_highlight' => self::f( $c, $d, 'title_highlight' ),
			'lede'            => self::f( $c, $d, 'lede' ),
			// No label means no pill row: the Calendar's pills belong with the
			// fixtures they filter, further down the page.
			'filter_label'    => $label,
			'filters'         => '' === $label ? array() : $state->filter_pills(),
		) );
	}

	// -- Home -----------------------------------------------------------------

	private static function ticker( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::ticker(
			array_values( array_map(
				static fn( array $i ): string => (string) ( $i['text'] ?? '' ),
				self::items( $c, $d )
			) )
		);
	}

	/**
	 * One section, two collections: the reader switches between the club's sports
	 * and its teams rather than the page picking one for them.
	 */
	private static function card_grid_switch( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$collections = $state->collections();
		return Blueworx_Clubhouse_Sections::card_grid_switch( array(
			'eyebrow' => self::f( $c, $d, 'eyebrow' ),
			'heading' => self::f( $c, $d, 'heading' ),
			'groups'  => array(
				'sports' => array(
					'label'      => 'Sports',
					'link_label' => 'All sections →',
					'link_href'  => Blueworx_Clubhouse_Links::url( 'sports' ),
					'cards'      => array_map(
						static function ( array $s ): array {
							return array(
								'image'     => $s['image'],
								'image_alt' => $s['title'],
								'tag'       => $s['label'],
								'title'     => $s['title'],
								'href'      => Blueworx_Clubhouse_Links::item_url( 'sports', Blueworx_Clubhouse_Page_Renderer::slugify( (string) $s['title'] ) ),
								'subtitle'  => $s['subtitle'],
							);
						},
						array_slice( $collections->sports(), 0, 4 )
					),
				),
				'teams'  => array(
					'label'      => 'Teams',
					'link_label' => 'All teams →',
					'link_href'  => Blueworx_Clubhouse_Links::url( 'teams' ),
					'cards'      => array_map(
						static function ( array $t ): array {
							return array(
								'image'     => $t['image'],
								'image_alt' => $t['sport'] . ' ' . $t['title'],
								'tag'       => $t['sport'],
								'title'     => $t['title'],
								'href'      => Blueworx_Clubhouse_Links::item_url( 'teams', Blueworx_Clubhouse_Page_Renderer::slugify( (string) $t['title'] ) ),
								'subtitle'  => $t['description'],
							);
						},
						array_slice( $collections->teams(), 0, 4 )
					),
				),
			),
		) );
	}

	private static function image_band( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::image_band( array(
			'eyebrow'   => self::f( $c, $d, 'eyebrow' ),
			'heading'   => self::f( $c, $d, 'heading' ),
			'image'     => Blueworx_Clubhouse_Block_Content::media_src( self::f( $c, $d, 'image' ) ),
			'image_alt' => self::f( $c, $d, 'image_alt' ),
			'cta_label' => self::f( $c, $d, 'cta_label' ),
			'cta_href'  => self::f( $c, $d, 'cta_href' ),
		) );
	}

	private static function band( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::band( array(
			'variant'   => self::f( $c, $d, 'variant', 'ink' ),
			'eyebrow'   => self::f( $c, $d, 'eyebrow' ),
			'heading'   => self::f( $c, $d, 'heading' ),
			'lede'      => self::f( $c, $d, 'lede' ),
			'cta_label' => self::f( $c, $d, 'cta_label' ),
			'cta_href'  => self::f( $c, $d, 'cta_href' ),
		) );
	}

	/**
	 * The tier cards. The club's tiers are one list wherever they appear, so the
	 * grid reads them off the page state rather than its own content.
	 *
	 * Two things differ by page, exactly as they did before: on Home every
	 * button funnels to the Membership page, where the fuller pitch and the
	 * checkout live; on Membership the grid follows the page h1 directly, with
	 * no section heading between them, so its names are h2.
	 */
	private static function tier_grid( Blueworx_Clubhouse_Page_State $state ): string {
		$tiers = $state->tiers();
		if ( 'membership' === $state->page() ) {
			return Blueworx_Clubhouse_Sections::tier_grid( $tiers, 2 );
		}
		return Blueworx_Clubhouse_Sections::tier_grid( array_map(
			static function ( array $t ): array {
				$t['cta_label'] = 'Join';
				$t['cta_href']  = Blueworx_Clubhouse_Links::url( 'membership' );
				return $t;
			},
			$tiers
		) );
	}

	private static function activity_tabs( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$collections = $state->collections();
		return Blueworx_Clubhouse_Sections::activity_tabs( array(
			'eyebrow'  => self::f( $c, $d, 'eyebrow' ),
			'heading'  => self::f( $c, $d, 'heading' ),
			'fixtures' => Blueworx_Clubhouse_Fixture_Projection::home_fixtures( $collections->fixtures() ),
			'events'   => array_map(
				static function ( array $e ): array {
					return array( 'tag' => $e['tag'], 'date' => $e['date'], 'title' => $e['title'], 'detail' => $e['detail'] );
				},
				array_slice( array_values( array_filter( $collections->events(), static fn( $e ) => 'upcoming' === $e['status'] ) ), 0, 3 )
			),
		) );
	}

	/**
	 * The club's news, on the Home page.
	 *
	 * Real posts first — the section is the club's news, so it shows the club's
	 * actual news. The editable items stay as the fallback for a site that has
	 * not published yet, and for one that has switched its news page off, where
	 * the articles are not clubhouse-dressed and a link would lead somewhere
	 * bare.
	 */
	private static function news_cards( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$news_on = $state->visibility()->is_page_visible( 'news' );
		$source  = $news_on ? Blueworx_Clubhouse_News::source() : null;
		$posts   = null !== $source ? $source->recent( 3 ) : array();
		$items   = array() !== $posts
			? array_map(
				static function ( array $p ): array {
					return array(
						'image'     => (string) ( $p['image'] ?? '' ),
						'image_alt' => (string) ( $p['image_alt'] ?? '' ),
						'tag'       => (string) ( $p['category'] ?? '' ),
						'date'      => (string) ( $p['date'] ?? '' ),
						'title'     => (string) ( $p['title'] ?? '' ),
						'href'      => (string) ( $p['href'] ?? '' ),
					);
				},
				$posts
			)
			: self::items( $c, $d );

		return Blueworx_Clubhouse_Sections::news_cards( array(
			'eyebrow'    => self::f( $c, $d, 'eyebrow' ),
			'heading'    => self::f( $c, $d, 'heading' ),
			// Only offered when the club has a news page to send people to.
			'link_label' => 'All news →',
			'link_href'  => $news_on ? Blueworx_Clubhouse_Links::url( 'news' ) : '',
			'cards'      => array_map(
				static function ( array $i ): array {
					return array(
						'image'     => Blueworx_Clubhouse_Block_Content::media_src( (string) ( $i['image'] ?? '' ) ),
						'image_alt' => (string) ( $i['image_alt'] ?? '' ),
						'tag'       => (string) ( $i['tag'] ?? '' ),
						'date'      => (string) ( $i['date'] ?? '' ),
						'title'     => (string) ( $i['title'] ?? '' ),
						// Empty for the editable fallback, which has no story behind it.
						'href'      => (string) ( $i['href'] ?? '' ),
					);
				},
				$items
			),
		) );
	}

	private static function sponsors( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		return Blueworx_Clubhouse_Sections::sponsors( array(
			'eyebrow'    => self::f( $c, $d, 'eyebrow' ),
			'heading'    => self::f( $c, $d, 'heading' ),
			'link_label' => self::f( $c, $d, 'link_label' ),
			'link_href'  => self::f( $c, $d, 'link_href' ),
			'names'      => array_map( static fn( array $s ): string => $s['name'], $state->collections()->sponsors() ),
		) );
	}

	/**
	 * The band that closes a page: the club's socials, and — on Home — the
	 * find-us columns beneath them. Either half can be switched off on its own,
	 * which is what the two settings are; with both off the band renders
	 * nothing and drops out of the page.
	 *
	 * @param array<string,mixed> $settings
	 */
	private static function closing_band( array $c, array $d, array $settings, Blueworx_Clubhouse_Page_State $state ): string {
		$social_on  = (bool) ( $settings['show_social'] ?? true );
		$columns_on = (bool) ( $settings['show_columns'] ?? true );
		$branding   = $state->branding();

		$items   = $columns_on ? self::items( $c, $d ) : array();
		$columns = array_map(
			static function ( array $i ): array {
				return array(
					'label'      => (string) ( $i['label'] ?? '' ),
					'lines'      => Blueworx_Clubhouse_Block_Content::lines( $i['lines'] ?? array() ),
					'link_label' => (string) ( $i['link_label'] ?? '' ),
					'link_href'  => (string) ( $i['link_href'] ?? '' ),
				);
			},
			$items
		);

		return Blueworx_Clubhouse_Sections::closing_band( array(
			'heading'       => $social_on ? self::f( $c, $d, 'heading' ) : '',
			'lede'          => $social_on ? self::f( $c, $d, 'lede' ) : '',
			'facebook_url'  => $social_on ? $branding->get_facebook_url() : '',
			'instagram_url' => $social_on ? $branding->get_instagram_url() : '',
			'linkedin_url'  => $social_on ? $branding->get_linkedin_url() : '',
			'x_url'         => $social_on ? $branding->get_x_url() : '',
			'columns'       => $columns,
			'cols_id'       => self::f( $c, $d, 'cols_id' ),
		) );
	}

	// -- About / Membership ---------------------------------------------------

	private static function timeline( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::timeline( array(
			'eyebrow'    => self::f( $c, $d, 'eyebrow' ),
			'heading'    => self::f( $c, $d, 'heading' ),
			'milestones' => array_map(
				static function ( array $m ): array {
					return array(
						'year'  => (string) ( $m['year'] ?? '' ),
						'title' => (string) ( $m['title'] ?? '' ),
						'desc'  => (string) ( $m['desc'] ?? '' ),
					);
				},
				self::items( $c, $d )
			),
		) );
	}

	private static function benefit_grid( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::benefit_grid( array(
			'eyebrow' => self::f( $c, $d, 'eyebrow' ),
			'heading' => self::f( $c, $d, 'heading' ),
			'cards'   => self::items( $c, $d ),
		) );
	}

	/**
	 * The people the club puts names to. Which people depends on the block: the
	 * committee that runs the club, or the directory of who to contact — the
	 * same distinction the two roles on a person record already carry.
	 */
	private static function people_grid( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$source    = self::f( $c, $d, 'source', 'committee' );
		$directory = 'directory' === $source;
		$field     = $directory ? 'directory_role' : 'committee_role';

		return Blueworx_Clubhouse_Sections::people_grid( array(
			'eyebrow' => self::f( $c, $d, 'eyebrow' ),
			'heading' => self::f( $c, $d, 'heading' ),
			'people'  => array_map(
				static function ( array $p ) use ( $field, $directory ): array {
					return array(
						'name'  => $p['name'],
						'role'  => $p[ $field ],
						'email' => $directory ? $p['email'] : '',
						'photo' => Blueworx_Clubhouse_Block_Content::media_src( (string) ( $p['photo'] ?? '' ) ),
					);
				},
				array_values( array_filter(
					$state->collections()->people(),
					static fn( array $p ): bool => '' !== $p[ $field ]
				) )
			),
		) );
	}

	private static function list_split( array $c, array $d ): string {
		$items = self::items( $c, $d );
		return Blueworx_Clubhouse_Sections::list_split( array(
			'eyebrow'            => self::f( $c, $d, 'eyebrow' ),
			'heading'            => self::f( $c, $d, 'heading' ),
			'included_label'     => self::f( $c, $d, 'included_label' ),
			'not_included_label' => self::f( $c, $d, 'not_included_label' ),
			'policies_label'     => self::f( $c, $d, 'policies_label' ),
			'included'           => array_values( array_map(
				static fn( array $i ): string => (string) ( $i['text'] ?? '' ),
				array_filter( $items, static fn( array $i ): bool => (bool) ( $i['included'] ?? false ) )
			) ),
			'not_included'       => array_values( array_map(
				static fn( array $i ): string => (string) ( $i['text'] ?? '' ),
				array_filter( $items, static fn( array $i ): bool => ! ( $i['included'] ?? false ) )
			) ),
			'policies'           => (array) ( $d['policies'] ?? array() ),
		) );
	}

	/** The step numbers are always the running order, whatever an owner typed. */
	private static function step_grid( array $c, array $d ): string {
		$items = array_values( self::items( $c, $d ) );
		return Blueworx_Clubhouse_Sections::step_grid( array(
			'eyebrow' => self::f( $c, $d, 'eyebrow' ),
			'heading' => self::f( $c, $d, 'heading' ),
			'steps'   => array_map(
				static function ( array $s, int $i ): array {
					return array(
						'number'      => sprintf( '%02d', $i + 1 ),
						'title'       => (string) ( $s['title'] ?? '' ),
						'description' => (string) ( $s['description'] ?? '' ),
					);
				},
				$items,
				array_keys( $items )
			),
		) );
	}

	private static function faq( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::faq( array(
			'eyebrow' => self::f( $c, $d, 'eyebrow' ),
			'heading' => self::f( $c, $d, 'heading' ),
			'items'   => array_map(
				static function ( array $i ): array {
					return array(
						'question' => (string) ( $i['question'] ?? '' ),
						'answer'   => (string) ( $i['answer'] ?? '' ),
						'open'     => (bool) ( $i['open'] ?? false ),
					);
				},
				self::items( $c, $d )
			),
		) );
	}

	// -- Directories ----------------------------------------------------------

	/**
	 * The sports or teams directory. Both are the same grid over different rows,
	 * which is why one block type serves both: the page it is on says which
	 * collection fills it.
	 */
	private static function stat_card_grid( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$teams = 'teams' === $state->page();
		$cards = array_map(
			static function ( array $row ) use ( $teams ): array {
				if ( $teams ) {
					return array(
						'image'       => $row['image'],
						'image_alt'   => $row['sport'] . ' ' . $row['title'],
						'chip'        => $row['sport'],
						'title'       => $row['title'],
						'href'        => Blueworx_Clubhouse_Links::item_url( 'teams', Blueworx_Clubhouse_Page_Renderer::slugify( (string) $row['title'] ) ),
						'description' => $row['description'],
						'stats'       => array(
							array( 'value' => $row['match_day'], 'label' => 'Match day' ),
							array( 'value' => $row['league'], 'label' => 'League' ),
						),
					);
				}
				return array(
					'image'       => $row['image'],
					'image_alt'   => $row['title'],
					'chip'        => $row['label'],
					'title'       => $row['title'],
					'href'        => Blueworx_Clubhouse_Links::item_url( 'sports', Blueworx_Clubhouse_Page_Renderer::slugify( (string) $row['title'] ) ),
					'description' => $row['description'],
					'stats'       => array(
						array( 'value' => $row['stat1_value'], 'label' => $row['stat1_label'] ),
						array( 'value' => $row['stat2_value'], 'label' => $row['stat2_label'] ),
					),
				);
			},
			$state->filtered_rows()
		);

		return Blueworx_Clubhouse_Sections::stat_card_grid( array(
			'eyebrow'    => self::f( $c, $d, 'eyebrow' ),
			'heading'    => self::f( $c, $d, 'heading' ),
			'empty_text' => '' !== $state->filter() ? (string) ( $d['empty_filter'] ?? '' ) : '',
			'link_label' => self::f( $c, $d, 'link_label' ),
			'link_href'  => self::f( $c, $d, 'link_href' ),
			'cards'      => $cards,
		) );
	}

	private static function event_grid( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$upcoming = array_values( array_filter( $state->filtered_rows(), static fn( array $e ): bool => 'upcoming' === $e['status'] ) );
		return Blueworx_Clubhouse_Sections::event_grid( array(
			'eyebrow'    => self::f( $c, $d, 'eyebrow' ),
			'heading'    => self::f( $c, $d, 'heading' ),
			'empty_text' => '' !== $state->filter() ? (string) ( $d['empty_filter'] ?? '' ) : '',
			'cards'      => array_map(
				static function ( array $e ): array {
					return array(
						'tag'       => $e['tag'],
						'date'      => $e['date'],
						'title'     => $e['title'],
						'detail'    => $e['detail'],
						'cta_label' => $e['cta_label'],
						'cta_href'  => $e['cta_href'],
					);
				},
				$upcoming
			),
		) );
	}

	private static function event_archive( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$past = array_values( array_filter( $state->filtered_rows(), static fn( array $e ): bool => 'past' === $e['status'] ) );
		return Blueworx_Clubhouse_Sections::event_archive( array(
			'heading' => self::f( $c, $d, 'heading' ),
			'rows'    => array_map(
				static function ( array $e ): array {
					return array( 'date' => $e['date'], 'tag' => $e['tag'], 'title' => $e['title'] );
				},
				$past
			),
		) );
	}

	/**
	 * The fixtures list. Unlike the other filtered lists this one says so when
	 * the club has entered no fixtures at all: the page is titled "Fixtures &
	 * results", and with the schedule silently absent the only thing left on it
	 * was the court-booking grid — which read as the club having no fixtures
	 * ever, or worse, as the bookings BEING the fixtures.
	 */
	private static function calendar_months( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$filtered = '' !== $state->filter();
		return Blueworx_Clubhouse_Sections::calendar_months( array(
			'eyebrow'      => self::f( $c, $d, 'eyebrow' ),
			'heading'      => self::f( $c, $d, 'heading' ),
			'filter_label' => self::f( $c, $d, 'filter_label' ),
			'filters'      => $state->filter_pills(),
			'empty_text'   => $filtered ? (string) ( $d['empty_filter'] ?? '' ) : self::f( $c, $d, 'empty_text' ),
			'months'       => Blueworx_Clubhouse_Fixture_Projection::calendar_months( $state->filtered_rows() ),
		) );
	}

	// -- News -----------------------------------------------------------------

	private static function news_head( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::news_head( array(
			'eyebrow'         => self::f( $c, $d, 'eyebrow' ),
			'title_lead'      => self::f( $c, $d, 'title_lead' ),
			'title_highlight' => self::f( $c, $d, 'title_highlight' ),
			'lede'            => self::f( $c, $d, 'lede' ),
		) );
	}

	private static function news_featured( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$featured = $state->news()['featured'];
		if ( null === $featured ) {
			return '';
		}
		return Blueworx_Clubhouse_Sections::news_featured( array(
			'post'  => $featured,
			'label' => self::f( $c, $d, 'label' ),
			'cta'   => self::f( $c, $d, 'cta' ),
		) );
	}

	private static function news_grid( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$news = $state->news();
		return Blueworx_Clubhouse_Sections::news_grid( array(
			'filter_label' => self::f( $c, $d, 'filter_label' ),
			'filters'      => self::news_filters( $news['categories'], $news['filter'] ),
			'count_label'  => 1 === $news['total'] ? '1 story' : $news['total'] . ' stories',
			'posts'        => $news['posts'],
			'empty_text'   => '' === $news['filter']
				? self::f( $c, $d, 'empty_text' )
				: (string) ( $d['empty_text_filter'] ?? '' ),
			'pager'        => self::pager_model( $news['paging'], $news['filter'] ),
		) );
	}

	/**
	 * The category pills, "All" first and always present — without it a reader
	 * who filters has no way back to everything short of editing the address.
	 *
	 * @param array<int,array{label:string,slug:string}> $categories
	 * @return array<int,array{label:string,href:string,active:bool}>
	 */
	private static function news_filters( array $categories, string $current ): array {
		if ( array() === $categories ) {
			return array();
		}
		$pills = array(
			array( 'label' => 'All', 'href' => Blueworx_Clubhouse_News::url(), 'active' => '' === $current ),
		);
		foreach ( $categories as $category ) {
			$pills[] = array(
				'label'  => (string) $category['label'],
				'href'   => Blueworx_Clubhouse_News::url( (string) $category['slug'] ),
				'active' => $current === (string) $category['slug'],
			);
		}
		return $pills;
	}

	/**
	 * @param array{page:int,pages:int,offset:int} $paging
	 * @return array{page:int,pages:int,prev_href:string,next_href:string,pages_list:array<int,array{label:string,href:string,active:bool}>}
	 */
	private static function pager_model( array $paging, string $filter ): array {
		$list = array();
		for ( $i = 1; $i <= $paging['pages']; $i++ ) {
			$list[] = array(
				'label'  => (string) $i,
				'href'   => Blueworx_Clubhouse_News::url( $filter, $i ),
				'active' => $i === $paging['page'],
			);
		}
		return array(
			'page'       => $paging['page'],
			'pages'      => $paging['pages'],
			'prev_href'  => $paging['page'] > 1 ? Blueworx_Clubhouse_News::url( $filter, $paging['page'] - 1 ) : '',
			'next_href'  => $paging['page'] < $paging['pages'] ? Blueworx_Clubhouse_News::url( $filter, $paging['page'] + 1 ) : '',
			'pages_list' => $list,
		);
	}

	// -- Contact, login, shortcodes, prose ------------------------------------

	private static function contact_form( array $c, array $d, Blueworx_Clubhouse_Page_State $state ): string {
		$branding = $state->branding();
		return Blueworx_Clubhouse_Sections::contact_form( array(
			'eyebrow'      => self::f( $c, $d, 'eyebrow' ),
			'heading'      => self::f( $c, $d, 'heading' ),
			'club_name'    => $branding->get_club_name(),
			'shortcode'    => self::f( $c, $d, 'shortcode' ),
			'offline_note' => self::f( $c, $d, 'offline_note' ),
			'submit_label' => self::f( $c, $d, 'submit_label' ),
			'info'         => array(
				'heading' => self::f( $c, $d, 'info_heading' ),
				'address' => Blueworx_Clubhouse_Block_Content::lines( Blueworx_Clubhouse_Block_Content::field( $c, $d, 'address' ) ),
				'email'   => self::f( $c, $d, 'email' ),
				'phone'   => self::f( $c, $d, 'phone' ),
				'map'     => Blueworx_Clubhouse_Block_Content::media_src( self::f( $c, $d, 'map_image' ) ),
				'socials' => array(
					'Facebook'  => $branding->get_facebook_url(),
					'Instagram' => $branding->get_instagram_url(),
					'LinkedIn'  => $branding->get_linkedin_url(),
					'X'         => $branding->get_x_url(),
				),
			),
		) );
	}

	/**
	 * The card draws whichever step of the account journey this request is on.
	 * Off WordPress the state seam is unset and returns the plain sign-in form a
	 * first-time visitor sees.
	 */
	private static function auth( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::auth( array(
			'eyebrow'        => self::f( $c, $d, 'eyebrow' ),
			'heading'        => self::f( $c, $d, 'heading' ),
			'lede'           => self::f( $c, $d, 'lede' ),
			'email_label'    => 'Email or username',
			'password_label' => 'Password',
			'remember_label' => 'Remember me',
			'forgot_label'   => 'Forgot password?',
			'forgot_href'    => Blueworx_Clubhouse_Links::auth_url( Blueworx_Clubhouse_Auth_View::FORGOT ),
			'signin_href'    => Blueworx_Clubhouse_Links::url( 'login' ),
			'submit_label'   => 'Log in',
			'join_prompt'    => 'Not a member yet?',
			'join_label'     => Blueworx_Clubhouse_Cta::JOIN,
			'join_href'      => Blueworx_Clubhouse_Links::url( 'membership' ),
			'state'          => Blueworx_Clubhouse_Auth_View::state(),
		) );
	}

	/**
	 * A third party's own markup, in the club's own dressing.
	 *
	 * Clearing the shortcode field does NOT drop the slot: '' is the unset
	 * sentinel every content field uses, so the default comes back. Taking the
	 * block off the page is how a slot goes away.
	 */
	private static function shortcode_block( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::shortcode_block( array(
			'eyebrow'    => self::f( $c, $d, 'eyebrow' ),
			'heading'    => self::f( $c, $d, 'heading' ),
			'shortcode'  => self::f( $c, $d, 'shortcode' ),
			'link_label' => self::f( $c, $d, 'link_label' ),
			'link_href'  => self::f( $c, $d, 'link_href' ),
		) );
	}

	private static function prose( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::prose( array(
			'heading' => self::f( $c, $d, 'heading' ),
			'blocks'  => array_map(
				static function ( array $b ): array {
					return array(
						'heading' => (string) ( $b['heading'] ?? '' ),
						'body'    => (string) ( $b['body'] ?? '' ),
					);
				},
				self::items( $c, $d )
			),
		) );
	}

	private static function info_panel( array $c, array $d ): string {
		return Blueworx_Clubhouse_Sections::info_panel( array(
			'eyebrow'       => self::f( $c, $d, 'eyebrow' ),
			'heading'       => self::f( $c, $d, 'heading' ),
			'training'      => Blueworx_Clubhouse_Block_Content::lines( Blueworx_Clubhouse_Block_Content::field( $c, $d, 'training' ) ),
			'contact_name'  => self::f( $c, $d, 'contact_name' ),
			'contact_email' => self::f( $c, $d, 'contact_email' ),
		) );
	}
}
