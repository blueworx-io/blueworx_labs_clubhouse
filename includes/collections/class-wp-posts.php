<?php
// includes/collections/class-wp-posts.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Club news read from real WordPress posts.
 *
 * Everything projected here is what the templates draw and nothing more, so the
 * renderers never see a WP_Post and stay testable without WordPress.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_WP_Posts implements Blueworx_Clubhouse_Post_Source {

	/** Words a minute, for the "4 min read" line. */
	private const READING_SPEED = 200;

	public function recent( int $limit, int $offset = 0, string $category = '' ): array {
		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, $limit ),
			'offset'              => max( 0, $offset ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( '' !== $category ) {
			$args['category_name'] = $category;
		}
		return array_map( array( $this, 'project' ), get_posts( $args ) );
	}

	public function count( string $category = '' ): int {
		if ( '' === $category ) {
			$counts = wp_count_posts( 'post' );
			return (int) ( $counts->publish ?? 0 );
		}
		$term = get_category_by_slug( $category );
		return false === $term ? 0 : (int) $term->count;
	}

	public function categories(): array {
		$terms = get_categories(
			array(
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		$out = array();
		foreach ( $terms as $term ) {
			$out[] = array( 'label' => (string) $term->name, 'slug' => (string) $term->slug );
		}
		return $out;
	}

	public function current(): ?array {
		if ( ! is_singular( 'post' ) ) {
			return null;
		}
		$post = get_post();
		if ( null === $post ) {
			return null;
		}

		$row               = $this->project( $post );
		$row['standfirst'] = $row['excerpt'];
		// the_content, not the raw column: shortcodes, embeds, blocks and every
		// other plugin's content filter all live on this hook, and skipping it
		// would render the post differently from everywhere else on the site.
		$row['html']          = (string) apply_filters( 'the_content', $post->post_content );
		$row['image_caption'] = (string) wp_get_attachment_caption( (int) get_post_thumbnail_id( $post ) );
		// get_the_tags returns false for an untagged post and a WP_Error for a
		// taxonomy that does not exist. Casting either to an array gives one
		// junk element rather than none — which is how every untagged post ended
		// up drawing a single blank tag chip.
		$tags                 = get_the_tags( $post );
		$row['tags']          = is_array( $tags ) ? array_map(
			static fn( $tag ): string => (string) $tag->name,
			$tags
		) : array();
		$row['author']        = $this->author( (int) $post->post_author );
		return $row;
	}

	public function related( int $limit ): array {
		$post = get_post();
		if ( null === $post ) {
			return array();
		}
		$categories = wp_get_post_categories( $post->ID );
		$args       = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, $limit ),
			'post__not_in'        => array( $post->ID ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		// Same section first; a club with one busy section and one quiet one should
		// not send a rugby reader to the only hockey post on the site.
		$same = array() !== $categories
			? get_posts( array_merge( $args, array( 'category__in' => $categories ) ) )
			: array();

		if ( count( $same ) < $limit ) {
			$exclude = array_merge( array( $post->ID ), array_map( static fn( $p ): int => (int) $p->ID, $same ) );
			$filler  = get_posts(
				array_merge(
					$args,
					array(
						'post__not_in'   => $exclude,
						'posts_per_page' => $limit - count( $same ),
					)
				)
			);
			$same = array_merge( $same, $filler );
		}

		return array_map( array( $this, 'project' ), $same );
	}

	/**
	 * @param WP_Post $post
	 * @return array<string,mixed>
	 */
	private function project( $post ): array {
		$categories = get_the_category( $post->ID );
		$primary    = $categories[0] ?? null;
		$thumb      = (int) get_post_thumbnail_id( $post );

		return array(
			'id'            => (int) $post->ID,
			'title'         => (string) get_the_title( $post ),
			'href'          => (string) get_permalink( $post ),
			'excerpt'       => wp_strip_all_tags( (string) get_the_excerpt( $post ) ),
			'category'      => null !== $primary ? (string) $primary->name : '',
			'category_slug' => null !== $primary ? (string) $primary->slug : '',
			'date'          => (string) get_the_date( 'j F Y', $post ),
			'read'          => $this->reading_time( (string) $post->post_content ),
			'image'         => 0 !== $thumb ? (string) wp_get_attachment_image_url( $thumb, 'large' ) : '',
			'image_alt'     => 0 !== $thumb
				? (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true )
				: '',
		);
	}

	/**
	 * "4 min read". Rounded up and floored at one, because "0 min read" reads as a
	 * bug and a two-hundred-word match report is still a minute of somebody's day.
	 */
	private function reading_time( string $content ): string {
		$words   = str_word_count( wp_strip_all_tags( $content ) );
		$minutes = max( 1, (int) ceil( $words / self::READING_SPEED ) );
		return $minutes . ' min read';
	}

	/** @return array{name:string,role:string,initials:string,bio:string} */
	private function author( int $user_id ): array {
		$name = (string) get_the_author_meta( 'display_name', $user_id );
		return array(
			'name'     => $name,
			// The author's WordPress "position" is not a thing, so the role line is
			// their description's first clause when they have written one, and empty
			// otherwise — better blank than invented.
			'role'     => '',
			'initials' => self::initials( $name ),
			'bio'      => (string) get_the_author_meta( 'description', $user_id ),
		);
	}

	/** "Tom Brennan" → "TB". Pure, so the avatar rule is testable. */
	public static function initials( string $name ): string {
		$parts = preg_split( '/\s+/u', trim( $name ) ) ?: array();
		$parts = array_values( array_filter( $parts ) );
		if ( array() === $parts ) {
			return '';
		}
		$first = mb_strtoupper( mb_substr( (string) $parts[0], 0, 1 ) );
		if ( count( $parts ) < 2 ) {
			return $first;
		}
		return $first . mb_strtoupper( mb_substr( (string) $parts[ count( $parts ) - 1 ], 0, 1 ) );
	}
}
