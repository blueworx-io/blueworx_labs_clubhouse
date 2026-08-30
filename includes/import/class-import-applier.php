<?php
// includes/import/class-import-applier.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes an Import_Plan against WordPress. This is the only part of the
 * import path that writes anything, and the only part that touches the media
 * library or the posts table — the plan reaching it has already been validated
 * and sanitised, so nothing here re-decides what is allowed.
 *
 * Every failure is collected rather than thrown: a dead image URL must not cost
 * the owner the rest of an import they have just approved.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Applier {

	/**
	 * @param bool $sync_sections Switch page sections on or off to match what the
	 *                            file supplied — see Import_Sections. Opt-in: the
	 *                            owner ticks it on the preview screen.
	 * @return array{rows:array<int,array{label:string,detail:string}>,images_needed:array<int,array{label:string,page:string,section:string,field:string,index:int}>,warnings:array<int,string>}
	 */
	public static function apply( Blueworx_Clubhouse_Import_Plan $plan, Blueworx_Clubhouse_Storage $storage, bool $sync_sections = false ): array {
		$store    = new Blueworx_Clubhouse_Page_Content( $storage );
		$needed   = array();
		$warnings = array();

		// Items first: a loop-item image has to have an item to be written into.
		foreach ( $plan->items() as $page => $sections ) {
			foreach ( $sections as $section => $items ) {
				$store->set_items( (string) $page, (string) $section, $items );
			}
		}
		foreach ( $plan->fields() as $page => $sections ) {
			foreach ( $sections as $section => $fields ) {
				foreach ( $fields as $field => $value ) {
					$store->set( (string) $page, (string) $section, (string) $field, $value );
				}
			}
		}

		$rows = self::content_rows( $plan );

		foreach ( $plan->collections() as $type => $items ) {
			$result   = self::apply_collection( (string) $type, $items );
			$rows     = array_merge( $rows, $result['rows'] );
			$warnings = array_merge( $warnings, $result['warnings'] );
		}

		$fetched = 0;
		foreach ( $plan->images() as $image ) {
			$id = self::sideload( $image['url'], $image['alt'] );
			if ( 0 === $id ) {
				$warnings[] = sprintf( 'Could not fetch the image at %s — %s is still empty.', $image['url'], $image['label'] );
				$needed[]   = self::needed_entry( $image );
				continue;
			}
			if ( ! self::place_image( $store, $image, $id ) ) {
				$warnings[] = sprintf( 'Fetched the image for %s but could not place it.', $image['label'] );
				$needed[]   = self::needed_entry( $image );
				continue;
			}
			++$fetched;
		}

		if ( $fetched > 0 ) {
			$rows[] = array( 'label' => 'Images', 'detail' => $fetched . ' fetched' );
		}

		if ( $sync_sections ) {
			$moved = Blueworx_Clubhouse_Import_Sections::apply( $plan, $storage );
			$row   = self::sections_row( $moved );
			if ( null !== $row ) {
				$rows[] = $row;
			}
		}

		return array( 'rows' => $rows, 'images_needed' => $needed, 'warnings' => $warnings );
	}

	/**
	 * Fetch a remote image into the media library. media_sideload_image() runs
	 * through download_url()/wp_safe_remote_get(), which already refuses
	 * internal and private hosts — this is deliberately WordPress's vetted
	 * fetch path rather than a bespoke one.
	 *
	 * @return int attachment ID, or 0 on any failure
	 */
	private static function sideload( string $url, string $alt ): int {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$id = media_sideload_image( $url, 0, '' === $alt ? null : $alt, 'id' );
		if ( is_wp_error( $id ) || ! is_int( $id ) || $id < 1 ) {
			return 0;
		}
		return $id;
	}

	/**
	 * Count the demo posts of each type, so the preview can tell an owner what
	 * an import would replace before they approve it.
	 *
	 * @param array<int,string> $types
	 * @return array<string,int>
	 */
	public static function demo_counts( array $types ): array {
		$counts = array();
		foreach ( $types as $type ) {
			$counts[ $type ] = count( self::partition( $type )['demo'] );
		}
		return $counts;
	}

	/**
	 * Split a type's existing posts into demo and real. A post is demo if the
	 * seeder stamped it. The title fallback is only trusted for installs
	 * seeded before the marker existed — Collection_Seeder only ever seeds a
	 * type when it is empty, so for any given type its demo posts are either
	 * all marked or all unmarked; the moment one marked post is found, the
	 * type is known to be post-marker and title-matching is dropped entirely.
	 * Skipping that guard would delete a club's own "Tennis" or "Rugby" post
	 * the instant it shared a title with the seeded demo data.
	 *
	 * @return array{demo:array<int,object>,real:array<int,object>}
	 */
	private static function partition( string $type ): array {
		$demo_titles = Blueworx_Clubhouse_Demo_Content::titles( $type );
		$posts       = get_posts( array(
			'post_type'   => $type,
			'post_status' => 'any',
			'numberposts' => -1,
		) );

		$marks      = array();
		$any_marked = false;
		foreach ( $posts as $post ) {
			$id           = (int) ( $post->ID ?? 0 );
			$marked       = '1' === (string) get_post_meta( $id, Blueworx_Clubhouse_Collection_Seeder::DEMO_META, true );
			$marks[ $id ] = $marked;
			$any_marked   = $any_marked || $marked;
		}

		$demo = array();
		$real = array();
		foreach ( $posts as $post ) {
			$id     = (int) ( $post->ID ?? 0 );
			$marked = $marks[ $id ] ?? false;
			$titled = ! $any_marked && in_array( (string) ( $post->post_title ?? '' ), $demo_titles, true );
			if ( $marked || $titled ) {
				$demo[] = $post;
				continue;
			}
			$real[] = $post;
		}
		return array( 'demo' => $demo, 'real' => $real );
	}

	/**
	 * Replace demo, keep real: delete this type's demo posts, update any real
	 * post whose title matches an incoming item, create the rest. Real posts the
	 * file does not mention are left alone — an import is not a purge.
	 *
	 * @param array<int,array{title:string,meta:array<string,string>,images:array<string,array{url:string,alt:string}>}> $items
	 * @return array{rows:array<int,array{label:string,detail:string}>,warnings:array<int,string>}
	 */
	private static function apply_collection( string $type, array $items ): array {
		$split    = self::partition( $type );
		$warnings = array();

		$removed = 0;
		foreach ( $split['demo'] as $post ) {
			if ( wp_delete_post( (int) $post->ID, true ) ) {
				++$removed;
			}
		}

		$by_title = array();
		foreach ( $split['real'] as $post ) {
			$by_title[ (string) ( $post->post_title ?? '' ) ] = (int) ( $post->ID ?? 0 );
		}

		$created = 0;
		$updated = 0;
		$order   = 0;
		foreach ( $items as $item ) {
			$title    = (string) $item['title'];
			$updating = isset( $by_title[ $title ] );
			if ( $updating ) {
				$id = (int) wp_update_post( array( 'ID' => $by_title[ $title ], 'menu_order' => $order ) );
			} else {
				$id = (int) wp_insert_post( array(
					'post_type'   => $type,
					'post_status' => 'publish',
					'post_title'  => $title,
					'menu_order'  => $order,
				) );
			}
			++$order;

			if ( $id < 1 ) {
				$warnings[] = sprintf( 'Could not save the %s entry "%s".', Blueworx_Clubhouse_Collection_Meta::label( $type ), $title );
				continue;
			}

			if ( $updating ) {
				++$updated;
			} else {
				++$created;
				// So a second item in this same file sharing this title updates
				// it rather than creating a duplicate that $by_title never learns
				// about and the owner can never reach again by name.
				$by_title[ $title ] = $id;
			}

			// Through Collection_Meta::meta_key(): an import writes the same
			// addresses the collection's own editor reads, or a club would
			// import a season of fixtures and find every screen empty.
			foreach ( $item['meta'] as $key => $value ) {
				update_post_meta(
					$id,
					Blueworx_Clubhouse_Collection_Meta::meta_key( $type, (string) $key ),
					(string) $value
				);
			}
			foreach ( $item['images'] as $key => $ref ) {
				$attachment = self::sideload( (string) $ref['url'], (string) $ref['alt'] );
				if ( 0 === $attachment ) {
					$warnings[] = sprintf( 'Could not fetch the image at %s for "%s".', $ref['url'], $title );
					continue;
				}
				update_post_meta(
					$id,
					Blueworx_Clubhouse_Collection_Meta::meta_key( $type, (string) $key ),
					$attachment
				);
			}
		}

		$detail = array();
		if ( $created > 0 ) {
			$detail[] = $created . ' created';
		}
		if ( $updated > 0 ) {
			$detail[] = $updated . ' updated';
		}
		if ( $removed > 0 ) {
			$detail[] = $removed . ' demo ' . ( 1 === $removed ? 'entry' : 'entries' ) . ' removed';
		}

		return array(
			'rows'     => array( array(
				'label'  => Blueworx_Clubhouse_Collection_Meta::label( $type ),
				'detail' => implode( ', ', $detail ),
			) ),
			'warnings' => $warnings,
		);
	}

	/**
	 * Write a fetched attachment ID where the plan said it belongs. Returns
	 * false when the target item no longer exists, which can only happen if the
	 * plan was hand-edited between preview and apply.
	 *
	 * @param array{page:string,section:string,field:string,url:string,alt:string,label:string,index:int} $image
	 */
	private static function place_image( Blueworx_Clubhouse_Page_Content $store, array $image, int $id ): bool {
		// A plan stored before 'index' existed (a transient can outlive an
		// upgrade by up to an hour) has no such key; default it to -1 so it
		// keeps behaving as a section-level image rather than warning three
		// times and silently dropping the fetch.
		$index = $image['index'] ?? -1;
		if ( $index < 0 ) {
			$store->set( $image['page'], $image['section'], $image['field'], $id );
			return true;
		}
		$items = $store->get_items( $image['page'], $image['section'] );
		if ( ! array_key_exists( $index, $items ) ) {
			return false;
		}
		$items[ $index ][ $image['field'] ] = $id;
		$store->set_items( $image['page'], $image['section'], $items );
		return true;
	}

	/**
	 * Keep 'index' alongside the label/page/section/field: a loop-item image
	 * (index >= 0) lives at items[index][field], not at the section field
	 * place_image() uses for a section-level image (index < 0) — the same
	 * distinction place_image() itself branches on just above. Dropping it
	 * here would leave a stuck "still needed" entry for a loop-item image
	 * with no way to ever tell it has since been filled.
	 *
	 * @param array{page:string,section:string,field:string,url:string,alt:string,label:string,index:int} $image
	 * @return array{label:string,page:string,section:string,field:string,index:int}
	 */
	private static function needed_entry( array $image ): array {
		return array(
			'label'   => $image['label'],
			'page'    => $image['page'],
			'section' => $image['section'],
			'field'   => $image['field'],
			'index'   => $image['index'] ?? -1,
		);
	}

	/**
	 * @param array{on:int,off:int} $moved
	 * @return array{label:string,detail:string}|null null when nothing moved
	 */
	private static function sections_row( array $moved ): ?array {
		$detail = array();
		if ( $moved['off'] > 0 ) {
			$detail[] = $moved['off'] . ' switched off';
		}
		if ( $moved['on'] > 0 ) {
			$detail[] = $moved['on'] . ' switched on';
		}
		if ( array() === $detail ) {
			return null;
		}
		return array( 'label' => 'Sections', 'detail' => implode( ', ', $detail ) );
	}

	/** @return array<int,array{label:string,detail:string}> */
	private static function content_rows( Blueworx_Clubhouse_Import_Plan $plan ): array {
		$rows = array();
		foreach ( $plan->fields() as $page => $sections ) {
			foreach ( $sections as $section => $fields ) {
				$count  = count( $fields );
				$rows[] = array(
					'label'  => Blueworx_Clubhouse_Content_Catalogue::address_label( $page . '/' . $section ),
					'detail' => $count . ' ' . ( 1 === $count ? 'field' : 'fields' ) . ' saved',
				);
			}
		}
		foreach ( $plan->items() as $page => $sections ) {
			foreach ( $sections as $section => $items ) {
				$count  = count( $items );
				$rows[] = array(
					'label'  => Blueworx_Clubhouse_Content_Catalogue::address_label( $page . '/' . $section ),
					'detail' => $count . ' ' . ( 1 === $count ? 'entry' : 'entries' ) . ' saved',
				);
			}
		}
		return $rows;
	}
}
