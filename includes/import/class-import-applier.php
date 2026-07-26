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
	 * @return array{rows:array<int,array{label:string,detail:string}>,images_needed:array<int,array{label:string,page:string,section:string,field:string}>,warnings:array<int,string>}
	 */
	public static function apply( Blueworx_Clubhouse_Import_Plan $plan, Blueworx_Clubhouse_Storage $storage ): array {
		$store    = new Blueworx_Clubhouse_Content_Store( $storage );
		$rows     = array();
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
	 * Write a fetched attachment ID where the plan said it belongs. Returns
	 * false when the target item no longer exists, which can only happen if the
	 * plan was hand-edited between preview and apply.
	 *
	 * @param array{page:string,section:string,field:string,url:string,alt:string,label:string,index:int} $image
	 */
	private static function place_image( Blueworx_Clubhouse_Content_Store $store, array $image, int $id ): bool {
		if ( $image['index'] < 0 ) {
			$store->set( $image['page'], $image['section'], $image['field'], $id );
			return true;
		}
		$items = $store->get_items( $image['page'], $image['section'] );
		if ( ! array_key_exists( $image['index'], $items ) ) {
			return false;
		}
		$items[ $image['index'] ][ $image['field'] ] = $id;
		$store->set_items( $image['page'], $image['section'], $items );
		return true;
	}

	/**
	 * @param array{page:string,section:string,field:string,url:string,alt:string,label:string,index:int} $image
	 * @return array{label:string,page:string,section:string,field:string}
	 */
	private static function needed_entry( array $image ): array {
		return array(
			'label'   => $image['label'],
			'page'    => $image['page'],
			'section' => $image['section'],
			'field'   => $image['field'],
		);
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
