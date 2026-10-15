<?php
// tests/php/fakes/class-fake-playtomic-transport.php
declare(strict_types=1);

/** Scripted responses, in order; records every request it saw. */
final class Blueworx_Clubhouse_Fake_Playtomic_Transport implements Blueworx_Clubhouse_Playtomic_Transport {

	/** @var array<int,array{status:int,body:string,headers?:array<string,string>}|Throwable> */
	public array $queue = array();
	/** @var array<int,array{method:string,url:string,headers:array<string,string>,body:?string}> */
	public array $seen = array();

	public function request( string $method, string $url, array $headers, ?string $body ): array {
		$this->seen[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body );
		$next = array_shift( $this->queue );
		if ( null === $next ) {
			throw new RuntimeException( 'fake transport ran out of responses for ' . $url );
		}
		if ( $next instanceof Throwable ) {
			throw $next;
		}
		return array( 'status' => $next['status'], 'body' => $next['body'], 'headers' => $next['headers'] ?? array() );
	}
}

if ( ! function_exists( 'fake_playtomic_jwt' ) ) {
	function fake_playtomic_jwt( array $tenants ): string {
		$b64 = static fn( string $s ): string => rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
		return $b64( '{"alg":"HS256"}' ) . '.' . $b64( json_encode( array( 'tenant_ids' => $tenants, 'exp' => 9999999999 ) ) ) . '.sig';
	}
}
