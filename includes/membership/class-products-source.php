<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the environment's products adapter. WordPress installs the SureCart one,
 * the preview installs the demo one, and tests install whichever they need.
 *
 * A static seam rather than a parameter threaded through every page method, for
 * the same reason Links is one: the renderer is shared by WordPress and the
 * preview, and one optional dependency does not justify changing eleven page
 * signatures.
 *
 * Null — nothing installed — is a first-class state: no shop, and every tier
 * falls back to the price its club typed.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Products_Source {

	private static ?Blueworx_Clubhouse_Products $products = null;

	public static function set( ?Blueworx_Clubhouse_Products $products ): void {
		self::$products = $products;
	}

	public static function get(): ?Blueworx_Clubhouse_Products {
		return self::$products;
	}
}
