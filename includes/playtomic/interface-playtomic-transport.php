<?php
// includes/playtomic/interface-playtomic-transport.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One HTTP call. Production wraps wp_remote_request; tests script answers.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Playtomic_Transport {

	/**
	 * @param array<string,string> $headers
	 * @return array{status:int,body:string,headers:array<string,string>}
	 * @throws Blueworx_Clubhouse_Playtomic_Exception When no response came back at all.
	 */
	public function request( string $method, string $url, array $headers, ?string $body ): array;
}
