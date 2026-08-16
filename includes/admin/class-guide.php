<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the ClubHouse user guide from the site it is running on.
 *
 * The guide is derived, never written out by hand. Every chapter reads the same
 * registries the product itself reads — the page map, the section inventory,
 * the admin page registry, the collection types, the look registry — so a page
 * that is added, a screen that appears, a section that is switched off or an
 * integration that is missing changes the guide on the next page load, with
 * nobody remembering to update it. A hand-written guide would be wrong within
 * a release and would then quietly teach people the wrong thing.
 *
 * It covers ClubHouse only. Whatever else the club has installed is that
 * plugin's to explain, and guessing at it would be worse than silence.
 *
 * Pure: the controller gathers the facts, this decides what to say about them.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Guide {

	/**
	 * @param array{club_name:string,looks:array<int,array{name:string,description:string,active:bool}>,
	 *   pages:array<int,array{key:string,label:string,visible:bool,sections:array<int,array{label:string,visible:bool}>}>,
	 *   screens:array<int,array{label:string,description:string,url:string}>,
	 *   collections:array<int,array{plural:string,count:int,url:string}>,
	 *   setup_url:string,content_url:string} $site
	 * @return array{club:string,intro:string,chapters:array<int,array<string,mixed>>}
	 */
	public static function build( array $site ): array {
		$club = trim( (string) ( $site['club_name'] ?? '' ) );

		return array(
			'club'     => '' !== $club ? $club : 'your club',
			'intro'    => 'Everything ClubHouse can do on this site, and where to do it. This guide is built from '
				. 'the site as it stands right now, so it changes when your site does — pages you switch off drop out of it, '
				. 'and anything new appears here on its own.',
			'chapters' => array_values(
				array_filter(
					array(
						self::screens_chapter( $site ),
						self::pages_chapter( $site ),
						self::content_chapter( $site ),
						self::collections_chapter( $site ),
						self::look_chapter( $site ),
					),
					static fn( ?array $chapter ): bool => null !== $chapter && array() !== $chapter['entries']
				)
			),
		);
	}

	/**
	 * @param array<string,mixed> $site
	 * @return array<string,mixed>
	 */
	private static function screens_chapter( array $site ): array {
		$entries = array();
		foreach ( (array) ( $site['screens'] ?? array() ) as $screen ) {
			$entries[] = array(
				'title' => (string) $screen['label'],
				'body'  => array( (string) $screen['description'] ),
				'steps' => array(),
				'state' => '',
				'url'   => (string) $screen['url'],
			);
		}
		return array(
			'key'     => 'screens',
			'title'   => 'Where everything lives',
			'lede'    => 'The ClubHouse screens you can open. Anything not listed here is either part of WordPress itself or belongs to another plugin.',
			'entries' => $entries,
		);
	}

	/**
	 * @param array<string,mixed> $site
	 * @return array<string,mixed>
	 */
	private static function pages_chapter( array $site ): array {
		$entries = array();
		foreach ( (array) ( $site['pages'] ?? array() ) as $page ) {
			$sections = (array) $page['sections'];
			$on       = array();
			$off      = array();
			foreach ( $sections as $section ) {
				if ( (bool) $section['visible'] ) {
					$on[] = (string) $section['label'];
				} else {
					$off[] = (string) $section['label'];
				}
			}

			$body = array();
			if ( ! (bool) $page['visible'] ) {
				$body[] = 'This page is switched off, so nobody can reach it and it is not in your menu. '
					. 'Switch it back on under Clubhouse Setup → Visibility.';
			} elseif ( array() === $sections ) {
				$body[] = 'This page has no sections to switch on and off — what it shows is decided by the page itself.';
			} else {
				$body[] = 'This page is made of ' . self::count_words( count( $sections ) ) . ' you can switch on and off: '
					. self::list_words( array_map( static fn( array $s ): string => (string) $s['label'], $sections ) ) . '.';
				if ( array() !== $off ) {
					// The most common "where has it gone" question there is, answered
					// before it is asked and named specifically.
					$body[] = 'Hidden right now: ' . self::list_words( $off ) . '. Nothing has been deleted — '
						. 'switching a section back on brings its words and pictures back exactly as they were.';
				}
			}

			$entries[] = array(
				'title' => (string) $page['label'],
				'body'  => $body,
				'steps' => array(),
				'state' => (bool) $page['visible'] ? 'Live' : 'Switched off',
				'url'   => (string) ( $site['setup_url'] ?? '' ),
			);
		}

		return array(
			'key'     => 'pages',
			'title'   => 'The pages on your site',
			'lede'    => 'Every page ClubHouse serves, what it is built from, and whether it is live. '
				. 'A page that needs a plugin you do not have is not listed at all, because it could not be served.',
			'entries' => $entries,
		);
	}

	/**
	 * @param array<string,mixed> $site
	 * @return array<string,mixed>
	 */
	private static function content_chapter( array $site ): array {
		$content_url = (string) ( $site['content_url'] ?? '' );
		return array(
			'key'     => 'content',
			'title'   => 'Changing the words and pictures',
			'lede'    => 'Everything a visitor reads is editable without touching the design.',
			'entries' => array(
				array(
					'title' => 'Editing a page',
					'body'  => array( 'Club Pages holds the words and pictures for every page, grouped the same way the pages are.' ),
					'steps' => array(
						'Open Club Pages.',
						'Pick the page across the top, then the section down the side.',
						'Change the wording, or choose a picture from your media library.',
						'Save. The change is live immediately — there is nothing to publish.',
					),
					'state' => '',
					'url'   => $content_url,
				),
				array(
					'title' => 'The menu',
					'body'  => array( 'The menu across the top of your site is yours to arrange — you choose which pages appear, in what order, and what each one is called.' ),
					'steps' => array( 'Open Clubhouse.', 'Go to the Menu tab.', 'Add, rename, reorder or remove items, then save.' ),
					'state' => '',
					'url'   => (string) ( $site['setup_url'] ?? '' ),
				),
				array(
					'title' => 'Bringing in an existing site',
					'body'  => array(
						'If the club already has a website, Import reads what you paste in and fills the pages for you.',
						'It replaces the content on every page it recognises, so it is meant for setting a site up rather than for '
						. 'small changes later on. You are shown what it is going to do before anything changes.',
					),
					'steps' => array(),
					'state' => '',
					'url'   => $content_url,
				),
			),
		);
	}

	/**
	 * @param array<string,mixed> $site
	 * @return array<string,mixed>
	 */
	private static function collections_chapter( array $site ): array {
		$entries = array();
		foreach ( (array) ( $site['collections'] ?? array() ) as $collection ) {
			$count = (int) $collection['count'];
			$body  = array( (string) $collection['description'] );
			if ( 0 === $count ) {
				$body[] = 'You have none yet. Anywhere your site would list them shows nothing until you add the first one.';
			}
			$entries[] = array(
				'title' => (string) $collection['plural'],
				'body'  => $body,
				'steps' => array(),
				'state' => 1 === $count ? '1 item' : $count . ' items',
				'url'   => (string) $collection['url'],
			);
		}

		return array(
			'key'     => 'collections',
			'title'   => 'Your teams, fixtures and the rest',
			'lede'    => 'These are lists rather than pages. You add items to a list once, and every part of the site '
				. 'that shows them updates by itself — a fixture added here appears on the calendar, the events page and the home page.',
			'entries' => $entries,
		);
	}

	/**
	 * @param array<string,mixed> $site
	 * @return array<string,mixed>
	 */
	private static function look_chapter( array $site ): array {
		$looks   = (array) ( $site['looks'] ?? array() );
		$active  = null;
		$others  = array();
		foreach ( $looks as $look ) {
			if ( (bool) $look['active'] ) {
				$active = $look;
				continue;
			}
			$others[] = (string) $look['name'];
		}

		$body = array();
		if ( null !== $active ) {
			$body[] = 'Your site is currently using ' . (string) $active['name'] . ' — ' . (string) $active['description'];
		} else {
			$body[] = 'You have not chosen a look yet, so your site is using the one ClubHouse starts with.';
		}
		if ( array() !== $others ) {
			$body[] = 'You can switch to ' . self::list_words( $others ) . ' at any time. Changing look changes nothing you have written — '
				. 'the same pages and the same words are simply dressed differently.';
		}

		return array(
			'key'     => 'look',
			'title'   => 'How your site looks',
			'lede'    => 'Your colours, your typefaces and your logo, applied everywhere at once.',
			'entries' => array(
				array(
					'title' => 'Choosing a look',
					'body'  => $body,
					'steps' => array(),
					'state' => null !== $active ? (string) $active['name'] : 'Not chosen',
					'url'   => (string) ( $site['setup_url'] ?? '' ),
				),
				array(
					'title' => 'Your colours and logo',
					'body'  => array(
						'Your club colour is used for buttons, links and highlights across the whole site. A second colour is '
						. 'optional — leave it empty and one is worked out from the first.',
						'A colour that would leave text too faint to read is refused rather than saved, so nothing you pick can '
						. 'make the site unreadable.',
					),
					'steps' => array( 'Open Clubhouse Setup.', 'Set your colours, club name, logo and favicon under Branding.', 'Save.' ),
					'state' => '',
					'url'   => (string) ( $site['setup_url'] ?? '' ),
				),
			),
		);
	}

	/** "three sections" / "one section" — a count read as a sentence, not a number. */
	private static function count_words( int $n ): string {
		$words = array( 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten' );
		$word  = $words[ $n ] ?? (string) $n;
		return $word . ' ' . ( 1 === $n ? 'section' : 'sections' );
	}

	/**
	 * "A, B and C" — an Oxford-comma-free list, because this is British English
	 * and the guide is read as prose rather than scanned as data.
	 *
	 * @param array<int,string> $items
	 */
	private static function list_words( array $items ): string {
		$items = array_values( array_filter( array_map( 'strval', $items ), static fn( string $i ): bool => '' !== trim( $i ) ) );
		$count = count( $items );
		if ( 0 === $count ) {
			return '';
		}
		if ( 1 === $count ) {
			return $items[0];
		}
		$last = array_pop( $items );
		return implode( ', ', $items ) . ' and ' . $last;
	}
}
