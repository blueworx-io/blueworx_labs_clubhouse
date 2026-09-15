<?php
// includes/admin/class-guides-registrar.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hands ClubHouse's guides to the Guides page in the WordPress Enhancements
 * plugin.
 *
 * That page assembles itself through four filters — the products along the
 * top, the topic tabs, which product each tab belongs to, and the guides
 * themselves — and this answers each of them. Nothing is drawn here: if the
 * Enhancements plugin is not running the filters never fire, and ClubHouse
 * shows no guides rather than a screen of its own.
 *
 * The site's facts are read once, when the guides are asked for, so the shop
 * and bookings guides follow what is actually installed.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Guides_Registrar {

	/** @var array{shop:bool,bookings:bool}|null A test's stand-in for the live site. */
	private static ?array $site = null;

	public static function register(): void {
		add_filter( 'blueworx_guide_products', array( self::class, 'products' ) );
		add_filter( 'blueworx_guide_tabs', array( self::class, 'tabs' ) );
		add_filter( 'blueworx_guide_tab_products', array( self::class, 'tab_products' ) );
		add_filter( 'blueworx_guides', array( self::class, 'guides' ) );
	}

	/** @param array{shop:bool,bookings:bool}|null $site Null reads the live site again. */
	public static function set_site( ?array $site ): void {
		self::$site = $site;
	}

	/** @param array<string,string> $products */
	public static function products( array $products ): array {
		$products[ Blueworx_Clubhouse_Guides::PRODUCT ] = Blueworx_Clubhouse_Guides::LABEL;
		return $products;
	}

	/** @param array<string,string> $tabs */
	public static function tabs( array $tabs ): array {
		return $tabs + Blueworx_Clubhouse_Guides::tabs();
	}

	/** @param array<string,string> $map */
	public static function tab_products( array $map ): array {
		foreach ( array_keys( Blueworx_Clubhouse_Guides::tabs() ) as $tab ) {
			$map[ $tab ] = Blueworx_Clubhouse_Guides::PRODUCT;
		}
		return $map;
	}

	/** @param array<int,array<string,mixed>> $guides */
	public static function guides( array $guides ): array {
		foreach ( Blueworx_Clubhouse_Guides::catalogue( self::site() ) as $guide ) {
			$guides[] = array(
				'id'         => $guide['id'],
				'title'      => $guide['title'],
				'tab'        => $guide['tab'],
				'product'    => $guide['product'],
				'capability' => $guide['capability'],
				'body'       => Blueworx_Clubhouse_Guides::body( $guide['parts'] ),
			);
		}
		return $guides;
	}

	/** @return array{shop:bool,bookings:bool} */
	private static function site(): array {
		return self::$site ?? array(
			'shop'     => Blueworx_Clubhouse_SureCart_Products::is_active(),
			'bookings' => Blueworx_Clubhouse_Integrations::has_latepoint(),
		);
	}
}
