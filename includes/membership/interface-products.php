<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The only two questions the plugin asks about what a membership costs.
 *
 * A seam, for the same reason Collections and Links are: the renderer, the
 * admin screen, the unit tests and the DB-free preview all have to work with no
 * shop plugin present. The implementation that knows SureCart exists is the one
 * class allowed to; everything else sees the plain arrays described below.
 *
 * A price array is display-ready:
 *   id      the store's own identifier, stored on the tier
 *   product the product's name, e.g. "Adult membership"
 *   label   for the admin picker, e.g. "Adult membership — £28/mo"
 *   amount  with its currency symbol, e.g. "£28"
 *   period  the suffix beside the price: "/mo", "/yr", or "" for a one-off
 *
 * Formatting lives in the implementation because only it knows the currency and
 * the minor units the store keeps money in.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Products {

	/**
	 * Every price an owner may connect a tier to.
	 *
	 * @return array<int,array{id:string,product:string,label:string,amount:string,period:string}>
	 */
	public function prices(): array;

	/**
	 * One price, or null when it is unknown, archived or the store is gone. Null
	 * is the fallback signal: the card then shows the club's typed price.
	 *
	 * @return array{id:string,product:string,label:string,amount:string,period:string}|null
	 */
	public function price( string $id ): ?array;
}
