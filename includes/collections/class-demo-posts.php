<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Club news for the preview and the tests — a plausible season's worth of
 * posts, with no WordPress behind them.
 *
 * The copy is deliberately specific (a promotion, a refurbishment, a coaching
 * course) rather than lorem ipsum: a news layout only shows its problems when
 * the headlines are real lengths and the categories are real words.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Posts implements Blueworx_Clubhouse_Post_Source {

	/** The post the preview treats as "the one being read". */
	private int $current_id;

	public function __construct( int $current_id = 1 ) {
		$this->current_id = $current_id;
	}

	/** @return array<int,array<string,mixed>> */
	private function all(): array {
		$rows = array(
			array(
				'id'       => 1,
				'title'    => '1st XV promoted to Division 3 South',
				'excerpt'  => 'A last-minute penalty at Riverside sealed a season nobody at the club will forget — and a first promotion in eleven years.',
				'category' => 'Rugby',
				'date'     => '24 July 2026',
				'read'     => '4 min read',
			),
			array(
				'id'       => 2,
				'title'    => 'Clubhouse refurbishment complete',
				'excerpt'  => 'New changing rooms, a bigger bar and a function room that finally fits a full presentation night.',
				'category' => 'Club news',
				'date'     => '18 July 2026',
				'read'     => '3 min read',
			),
			array(
				'id'       => 3,
				'title'    => 'How our ladies 1s turned their season around',
				'excerpt'  => 'Bottom of the table in November, third by April. The players explain what changed.',
				'category' => 'Hockey',
				'date'     => '6 July 2026',
				'read'     => '6 min read',
			),
			array(
				'id'       => 4,
				'title'    => 'Five things we learned from pre-season',
				'excerpt'  => 'Fitness is up, the front row is younger, and somebody needs to buy some new cones.',
				'category' => 'Rugby',
				'date'     => '14 June 2026',
				'read'     => '4 min read',
			),
			array(
				'id'       => 5,
				'title'    => 'Twelve new coaches qualified this summer',
				'excerpt'  => 'The club funded Level 1 and Level 2 courses across four sections. Here is who came through.',
				'category' => 'Coaching',
				'date'     => '2 June 2026',
				'read'     => '2 min read',
			),
			array(
				'id'       => 6,
				'title'    => 'Junior open day draws record numbers',
				'excerpt'  => 'Two hundred and forty children through the gate, and eleven new volunteers signed up on the day.',
				'category' => 'Juniors',
				'date'     => '21 May 2026',
				'read'     => '3 min read',
			),
			array(
				'id'       => 7,
				'title'    => 'Netball section adds a fourth team',
				'excerpt'  => 'Demand outgrew the courts, so Thursday nights now run to nine o\'clock.',
				'category' => 'Netball',
				'date'     => '9 May 2026',
				'read'     => '2 min read',
			),
			array(
				'id'       => 8,
				'title'    => 'Sponsors evening raises £6,400 for the junior fund',
				'excerpt'  => 'Every penny goes to kit and coaching for the sections that cannot charge for it.',
				'category' => 'Club news',
				'date'     => '28 April 2026',
				'read'     => '3 min read',
			),
		);

		return array_map(
			function ( array $row ): array {
				$row['category_slug'] = Blueworx_Clubhouse_Page_Renderer::slugify( (string) $row['category'] );
				$row['href']          = Blueworx_Clubhouse_Links::url( 'news' ) . '#post-' . $row['id'];
				$row['image']         = '';
				$row['image_alt']     = (string) $row['title'];
				return $row;
			},
			$rows
		);
	}

	public function recent( int $limit, int $offset = 0, string $category = '' ): array {
		$rows = $this->filtered( $category );
		return array_slice( $rows, max( 0, $offset ), max( 0, $limit ) );
	}

	public function count( string $category = '' ): int {
		return count( $this->filtered( $category ) );
	}

	/** @return array<int,array<string,mixed>> */
	private function filtered( string $category ): array {
		$rows = $this->all();
		if ( '' === $category ) {
			return $rows;
		}
		return array_values( array_filter( $rows, static fn( array $r ): bool => $r['category_slug'] === $category ) );
	}

	public function categories(): array {
		$seen = array();
		foreach ( $this->all() as $row ) {
			$seen[ (string) $row['category_slug'] ] = (string) $row['category'];
		}
		$out = array();
		foreach ( $seen as $slug => $label ) {
			$out[] = array( 'label' => $label, 'slug' => $slug );
		}
		return $out;
	}

	public function current(): ?array {
		foreach ( $this->all() as $row ) {
			if ( (int) $row['id'] !== $this->current_id ) {
				continue;
			}
			return array_merge(
				$row,
				array(
					'standfirst'    => (string) $row['excerpt'],
					'html'          => $this->body(),
					'image_caption' => 'Full time at Riverside: the squad after Hollis\'s penalty went over.',
					'tags'          => array( 'Rugby', '1st XV', 'Promotion', 'Season review' ),
					'author'        => array(
						'name'     => 'Tom Brennan',
						'role'     => 'Rugby section secretary',
						'initials' => 'TB',
						'bio'      => 'Rugby section secretary since 2019, prop until his shoulder said otherwise. Writes the match reports and runs the Saturday raffle.',
					),
				)
			);
		}
		return null;
	}

	public function related( int $limit ): array {
		$out = array();
		foreach ( $this->all() as $row ) {
			if ( (int) $row['id'] === $this->current_id ) {
				continue;
			}
			$out[] = $row;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	public function adjacent(): array {
		$rows = array_values( $this->all() );
		$at   = null;
		foreach ( $rows as $i => $row ) {
			if ( (int) $row['id'] === $this->current_id ) {
				$at = $i;
				break;
			}
		}
		if ( null === $at ) {
			return array( 'previous' => null, 'next' => null );
		}
		// all() is newest first, so the row after this one is the older story.
		return array(
			'previous' => self::step( $rows[ $at + 1 ] ?? null ),
			'next'     => self::step( $rows[ $at - 1 ] ?? null ),
		);
	}

	/**
	 * @param array<string,mixed>|null $row
	 * @return array{title:string,href:string}|null
	 */
	private static function step( ?array $row ): ?array {
		if ( null === $row ) {
			return null;
		}
		return array(
			'title' => (string) $row['title'],
			'href'  => (string) $row['href'],
		);
	}

	/** Post body markup, in the shapes the article stylesheet has to cope with. */
	private function body(): string {
		return '<p>It came down to eighty-two minutes and a kick from thirty-eight metres out. Riverside had clawed back a '
			. 'fourteen-point deficit inside the final quarter, and when the whistle went for the penalty, the touchline went '
			. 'quiet in a way it hadn\'t all season.</p>'
			. '<p>He put it through the middle. The club finish the 2025/26 campaign second in Division 4 South and go up for '
			. 'the first time since 2015.</p>'
			. '<h2>A season built in November</h2>'
			. '<p>The table doesn\'t show how close this came to being an ordinary year. Three defeats in the first five, a knee '
			. 'injury to the captain, and a squad that had never played together.</p>'
			. '<blockquote><p>Nobody won this in April. They won it standing in the rain in November when it didn\'t count for '
			. 'anything yet.</p><cite>Dev Raman · Head of rugby</cite></blockquote>'
			. '<p>Six of the matchday twenty-three came through the club\'s own minis and junior sections — the highest '
			. 'proportion the 1st XV has fielded in a decade.</p>'
			. '<h2>What Division 3 looks like</h2>'
			. '<p>Longer travel, bigger front rows, and a fixture list that starts on 5 September. Pre-season begins 4 August, '
			. 'Tuesdays and Thursdays at 7pm, and the section is actively recruiting in the back three and at hooker.</p>';
	}
}
