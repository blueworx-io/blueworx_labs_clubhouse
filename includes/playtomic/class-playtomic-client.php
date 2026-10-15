<?php
// includes/playtomic/class-playtomic-client.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one class that talks to Playtomic. Auth, the token cache, paging and
 * errors live here, so a feature built later calls bookings() and never
 * thinks about HTTP.
 *
 * Pure apart from transients (the token cache) and error_log.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Client {

	public const BASE            = 'https://thirdparty.playtomic.io';
	public const TOKEN_TRANSIENT = 'clubhouse_playtomic_token';
	public const PLAYERS_PATH          = '/api/v1/venues/%s/players';
	public const PLAYERS_PATH_SINGULAR = '/api/v1/venue/%s/players';

	/** Refresh this long before Playtomic would reject the token. */
	private const EARLY       = 300;
	private const PAGE_SIZE   = 200;
	private const CURSOR_SIZE = 100;

	/** @var callable():int */
	private $now;

	/** Which players path answered last; the docs show both. */
	private ?string $players_path = null;

	public function __construct(
		private Blueworx_Clubhouse_Playtomic_Transport $transport,
		private string $client_id,
		private string $secret,
		?callable $now = null
	) {
		$this->now = $now ?? static fn(): int => time();
	}

	/** A live bearer token, fetched or from the cache. */
	public function token(): string {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['token'], $cached['expires'] ) && (int) $cached['expires'] - self::EARLY > ( $this->now )() ) {
			return (string) $cached['token'];
		}
		return $this->fetch_token();
	}

	public function forget_token(): void {
		delete_transient( self::TOKEN_TRANSIENT );
	}

	/** The venue ids the credentials cover, read from the token's own payload. @return array<int,string> */
	public function venues(): array {
		$payload = self::jwt_payload( $this->token() );
		$ids     = $payload['tenant_ids'] ?? array();
		return is_array( $ids ) ? array_values( array_map( 'strval', $ids ) ) : array();
	}

	/**
	 * One authorised GET, decoded. Retries once with a fresh token on a 401.
	 *
	 * @param array<string,string|array<int,string>> $query
	 * @return array<mixed>
	 */
	public function get( string $path, array $query = array() ): array {
		$response = $this->send( $path, $query );
		if ( 401 === $response['status'] ) {
			$this->forget_token();
			$response = $this->send( $path, $query );
		}
		return $this->decode( $response, $path );
	}

	/**
	 * Every booking at a venue between two UTC moments (YYYY-MM-DDTHH:MM:SS,
	 * at most 365 days apart; Playtomic keeps three months of the past).
	 *
	 * @param array<string,string|array<int,string>> $extra Any other filter the docs list, e.g. status, sport_id.
	 * @return array<int,array<mixed>>
	 */
	public function bookings( string $venue, string $from, string $to, array $extra = array() ): array {
		foreach ( array( $from, $to ) as $date ) {
			if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $date ) ) {
				throw new Blueworx_Clubhouse_Playtomic_Exception( 'Dates must look like 2026-10-01T00:00:00 (UTC).' );
			}
		}
		$all  = array();
		$page = 0;
		do {
			$batch = $this->get(
				'/api/v1/bookings',
				array_merge( $extra, array( 'tenant_id' => $venue, 'start_booking_date' => $from, 'end_booking_date' => $to, 'page' => (string) $page, 'size' => (string) self::PAGE_SIZE ) )
			);
			$batch = array_values( array_filter( $batch, 'is_array' ) );
			$all   = array_merge( $all, $batch );
			++$page;
		} while ( count( $batch ) >= self::PAGE_SIZE );
		return $all;
	}

	/** Every player registered at a venue. @param array<int,string> $include BENEFITS, SPORTS, WALLETS. @return array<int,array<mixed>> */
	public function players( string $venue, array $include = array() ): array {
		$query = array( 'limit' => (string) self::CURSOR_SIZE );
		if ( array() !== $include ) {
			$query['include'] = $include;
		}
		return $this->cursor_pages( sprintf( $this->players_path(), rawurlencode( $venue ) ), $query, $venue );
	}

	/** @param array<int,string> $include @return array<mixed> */
	public function player( string $venue, string $id, array $include = array() ): array {
		$query = array() === $include ? array() : array( 'include' => $include );
		return $this->get( sprintf( $this->players_path(), rawurlencode( $venue ) ) . '/' . rawurlencode( $id ), $query );
	}

	/**
	 * Every payment at a venue. Filters pass straight through — see the
	 * Payments page: one date axis only, 13 months at most.
	 *
	 * @param array<string,string> $filters
	 * @return array<int,array<mixed>>
	 */
	public function payments( string $venue, array $filters = array() ): array {
		return $this->cursor_pages( '/api/v1/payments', array_merge( $filters, array( 'tenant_id' => $venue, 'limit' => (string) self::CURSOR_SIZE ) ), null );
	}

	/** Replace every secret with [hidden]. @param array<int,string> $secrets */
	public static function scrub( string $text, array $secrets ): string {
		foreach ( $secrets as $secret ) {
			if ( '' !== $secret ) {
				$text = str_replace( $secret, '[hidden]', $text );
			}
		}
		return $text;
	}

	/** One line in the PHP error log, already scrubbed by the caller. */
	public static function log( string $message ): void {
		error_log( '[Clubhouse Playtomic] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the one place API failures are recorded.
	}

	/* ---------------------------------------------------------------- */

	private function players_path(): string {
		return $this->players_path ?? self::PLAYERS_PATH;
	}

	/**
	 * Follow has_more / next_cursor_id to the end. When $venue is given, a 404
	 * on the first page tries the docs' singular path once and remembers it.
	 *
	 * @param array<string,string|array<int,string>> $query
	 * @return array<int,array<mixed>>
	 */
	private function cursor_pages( string $path, array $query, ?string $venue ): array {
		$all    = array();
		$cursor = '';
		do {
			$q = $query;
			if ( '' !== $cursor ) {
				$q['cursor_id'] = $cursor;
			}
			try {
				$page = $this->get( $path, $q );
			} catch ( Blueworx_Clubhouse_Playtomic_Exception $e ) {
				if ( 404 === $e->status() && null !== $venue && '' === $cursor && null === $this->players_path ) {
					$this->players_path = self::PLAYERS_PATH_SINGULAR;
					$path               = sprintf( self::PLAYERS_PATH_SINGULAR, rawurlencode( $venue ) );
					$page               = $this->get( $path, $q );
				} else {
					throw $e;
				}
			}
			$data   = is_array( $page['data'] ?? null ) ? array_values( array_filter( $page['data'], 'is_array' ) ) : array();
			$all    = array_merge( $all, $data );
			$cursor = ! empty( $page['has_more'] ) ? (string) ( $page['next_cursor_id'] ?? '' ) : '';
		} while ( '' !== $cursor );
		return $all;
	}

	private function fetch_token(): string {
		if ( '' === $this->client_id || '' === $this->secret ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Enter the Client ID and Secret first.' );
		}
		$response = $this->transport->request(
			'POST',
			self::BASE . '/api/v1/oauth/token',
			array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			(string) json_encode( array( 'client_id' => $this->client_id, 'secret' => $this->secret ) )
		);
		if ( 401 === $response['status'] || 403 === $response['status'] ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic did not accept the Client ID and Secret.', $response['status'], $this->safe( $response['body'] ) );
		}
		$data = $this->decode( $response, '/api/v1/oauth/token' );
		$token = (string) ( $data['token'] ?? '' );
		if ( '' === $token ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic answered without a token.', $response['status'], $this->safe( $response['body'] ) );
		}
		$ttl = max( 60, (int) ( $data['expires_in'] ?? 3600 ) );
		set_transient( self::TOKEN_TRANSIENT, array( 'token' => $token, 'expires' => ( $this->now )() + $ttl ), $ttl );
		return $token;
	}

	/** @param array<string,string|array<int,string>> $query @return array{status:int,body:string,headers:array<string,string>} */
	private function send( string $path, array $query ): array {
		$url = self::BASE . $path;
		if ( array() !== $query ) {
			$url .= '?' . self::query( $query );
		}
		return $this->transport->request(
			'GET',
			$url,
			array( 'Authorization' => 'Bearer ' . $this->token(), 'Accept' => 'application/json' ),
			null
		);
	}

	/** Repeated keys, not PHP's key[] form — the API reads b=x&b=y. @param array<string,string|array<int,string>> $query */
	private static function query( array $query ): string {
		$parts = array();
		foreach ( $query as $key => $value ) {
			foreach ( (array) $value as $one ) {
				$parts[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $one );
			}
		}
		return implode( '&', $parts );
	}

	/** @param array{status:int,body:string,headers:array<string,string>} $response @return array<mixed> */
	private function decode( array $response, string $path ): array {
		$status = $response['status'];
		$detail = $this->safe( $response['body'] );
		if ( 401 === $status || 403 === $status ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic refused the request: the token was rejected or these credentials cannot see that venue.', $status, $detail );
		}
		if ( 429 === $status ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic is rate-limiting this site (400 requests per 10 minutes). Try again in a few minutes.', $status, $detail );
		}
		if ( 400 === $status ) {
			$data = json_decode( $response['body'], true );
			$code = is_array( $data ) ? (string) ( $data['error'] ?? $data['code'] ?? '' ) : '';
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic rejected the request' . ( '' !== $code ? ' (' . $code . ')' : '' ) . '. Check the dates and venue id.', $status, $detail );
		}
		if ( 202 === $status ) {
			$after = (int) ( $response['headers']['retry-after'] ?? $response['headers']['Retry-After'] ?? 60 );
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic is still preparing that venue\'s data. Try again in about ' . max( 1, $after ) . ' seconds.', $status, $detail );
		}
		if ( $status < 200 || $status >= 300 ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic answered with an error (HTTP ' . $status . ') for ' . $path . '.', $status, $detail );
		}
		$data = json_decode( $response['body'], true );
		if ( ! is_array( $data ) ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic answered with something that was not JSON.', $status, $detail );
		}
		return $data;
	}

	private function safe( string $body ): string {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		$token  = is_array( $cached ) ? (string) ( $cached['token'] ?? '' ) : '';
		return substr( self::scrub( $body, array( $this->secret, $token ) ), 0, 500 );
	}

	/** @return array<string,mixed> */
	private static function jwt_payload( string $token ): array {
		$parts = explode( '.', $token );
		if ( count( $parts ) < 2 ) {
			return array();
		}
		$json = base64_decode( strtr( $parts[1], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $parts[1] ) % 4 ) % 4 ), true );
		$data = false === $json ? null : json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}
}
