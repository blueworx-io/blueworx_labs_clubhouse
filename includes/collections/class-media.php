<?php
// includes/collections/class-media.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a WordPress attachment ID to a full-size image URL. Thin WP glue used by
 * WP_Collections (collection image fields) and Frontend (the header logo), so the
 * pure render path only ever receives a URL string. Returns '' for a missing,
 * deleted, or non-image attachment, which degrades to the renderer's empty-media
 * placeholder rather than a broken src.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Media {

	public static function url( int $id ): string {
		if ( $id <= 0 ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, 'full' );
		return is_string( $url ) ? $url : '';
	}
}
