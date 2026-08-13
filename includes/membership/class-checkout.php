<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The link from a membership tier to a checkout with that tier in the basket.
 *
 * Pure: the environment installs the checkout page's URL (WordPress asks the
 * shop where its checkout lives; the preview and tests set their own), and this
 * builds the link. Empty base or empty price means no link at all — a tier then
 * falls back to the contact page rather than offering a dead button.
 *
 * The parameter names below are what the shop was observed to accept — see
 * docs/integrations/surecart-notes.md. They live here as constants because they
 * are the one thing in this plugin that a shop update could change.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Checkout {

	/** The query parameter naming the price to buy. */
	public const PRICE_PARAM = 'line_items[0][price_id]';

	/** The query parameter naming how many of it. */
	public const QUANTITY_PARAM = 'line_items[0][quantity]';

	private static string $base_url = '';

	public static function set_base_url( string $url ): void {
		self::$base_url = $url;
	}

	public static function base_url(): string {
		return self::$base_url;
	}

	/** The checkout URL for a price, or '' when we cannot build one. */
	public static function url( string $price_id ): string {
		if ( '' === self::$base_url || '' === $price_id ) {
			return '';
		}
		$separator = ( false !== strpos( self::$base_url, '?' ) ) ? '&' : '?';

		return self::$base_url . $separator
			. rawurlencode( self::PRICE_PARAM ) . '=' . rawurlencode( $price_id )
			. '&' . rawurlencode( self::QUANTITY_PARAM ) . '=1';
	}
}
