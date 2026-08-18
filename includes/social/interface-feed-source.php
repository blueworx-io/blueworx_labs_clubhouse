<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the social feed's posts come from. One method, deliberately: this is
 * the seam the staged plan rests on. Stage one implements it from links a club
 * pastes; stage two implements it from a Meta connection, and nothing above
 * this line changes.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Feed_Source {

	/**
	 * Recent posts, newest first, or null when the fetch itself failed.
	 *
	 * null and array() are different facts and callers depend on the
	 * difference: array() is "asked, there is nothing", null is "could not
	 * ask". Caching the second as the first is what would make an outage look
	 * like a club that never connected — see Social_Feed.
	 *
	 * @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}>|null
	 */
	public function posts(): ?array;
}
