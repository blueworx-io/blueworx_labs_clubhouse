<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fixed prices for the DB-free preview and the tests — the Demo_Collections of
 * memberships. Deliberately a runtime class rather than a test fake: the
 * preview is a real caller and must render connected tiers without a shop.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Products implements Blueworx_Clubhouse_Products {

	/** @return array<int,array{id:string,product:string,label:string,amount:string,period:string}> */
	public function prices(): array {
		return array(
			array( 'id' => 'price_junior_monthly', 'product' => 'Junior membership', 'label' => 'Junior membership — £12/mo', 'amount' => '£12', 'period' => '/mo' ),
			array( 'id' => 'price_adult_monthly', 'product' => 'Adult membership', 'label' => 'Adult membership — £28/mo', 'amount' => '£28', 'period' => '/mo' ),
			array( 'id' => 'price_adult_yearly', 'product' => 'Adult membership', 'label' => 'Adult membership — £300/yr', 'amount' => '£300', 'period' => '/yr' ),
		);
	}

	/** @return array{id:string,product:string,label:string,amount:string,period:string}|null */
	public function price( string $id ): ?array {
		foreach ( $this->prices() as $price ) {
			if ( $price['id'] === $id ) {
				return $price;
			}
		}
		return null;
	}
}
