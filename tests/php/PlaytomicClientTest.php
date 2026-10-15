<?php
// tests/php/PlaytomicClientTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicClientTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Playtomic_Transport $t;
	private int $now = 1_000_000;

	protected function setUp(): void {
		wp_stub_reset();
		$this->t = new Blueworx_Clubhouse_Fake_Playtomic_Transport();
	}

	private function client(): Blueworx_Clubhouse_Playtomic_Client {
		return new Blueworx_Clubhouse_Playtomic_Client( $this->t, 'cid', 'shh', fn(): int => $this->now );
	}

	private function token_ok( array $tenants = array( 'ten-a', 'ten-b' ) ): array {
		return array( 'status' => 200, 'body' => json_encode( array( 'token' => fake_playtomic_jwt( $tenants ), 'token_type' => 'BEARER', 'expires_in' => 3600 ) ) );
	}

	public function test_token_posts_client_id_and_secret_as_json(): void {
		$this->t->queue[] = $this->token_ok();
		$token = $this->client()->token();
		$this->assertStringStartsWith( 'eyJ', $token );
		$req = $this->t->seen[0];
		$this->assertSame( 'POST', $req['method'] );
		$this->assertSame( 'https://thirdparty.playtomic.io/api/v1/oauth/token', $req['url'] );
		$this->assertSame( array( 'client_id' => 'cid', 'secret' => 'shh' ), json_decode( (string) $req['body'], true ) );
		$this->assertSame( 'application/json', $req['headers']['Content-Type'] );
	}

	public function test_token_is_cached_until_five_minutes_before_expiry(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = $this->token_ok( array( 'ten-z' ) );
		$c = $this->client();
		$first = $c->token();
		$this->now += 3600 - 301;
		$this->assertSame( $first, $c->token(), 'still cached' );
		$this->now += 2;
		$this->assertNotSame( $first, $c->token(), 'refreshed early' );
		$this->assertCount( 2, $this->t->seen );
	}

	public function test_venues_come_from_the_token(): void {
		$this->t->queue[] = $this->token_ok( array( 'ten-a', 'ten-b' ) );
		$this->assertSame( array( 'ten-a', 'ten-b' ), $this->client()->venues() );
	}

	public function test_wrong_credentials_are_a_plain_sentence(): void {
		$this->t->queue[] = array( 'status' => 401, 'body' => '{"error":"UNAUTHORIZED"}' );
		$this->expectException( Blueworx_Clubhouse_Playtomic_Exception::class );
		$this->expectExceptionMessage( 'Playtomic did not accept the Client ID and Secret.' );
		$this->client()->token();
	}

	public function test_network_failure_is_a_plain_sentence(): void {
		$this->t->queue[] = new Blueworx_Clubhouse_Playtomic_Exception( 'Could not reach Playtomic: cURL error 28.' );
		$this->expectExceptionMessage( 'Could not reach Playtomic' );
		$this->client()->token();
	}

	public function test_missing_credentials_never_hit_the_network(): void {
		$c = new Blueworx_Clubhouse_Playtomic_Client( $this->t, '', '', fn(): int => $this->now );
		$this->expectExceptionMessage( 'Enter the Client ID and Secret first.' );
		$c->token();
		$this->assertSame( array(), $this->t->seen );
	}

	public function test_scrub_hides_secret_and_token(): void {
		$text = 'sent shh and got eyJabc.def.ghi back';
		$this->assertSame( 'sent [hidden] and got [hidden] back', Blueworx_Clubhouse_Playtomic_Client::scrub( $text, array( 'shh', 'eyJabc.def.ghi' ) ) );
		$this->assertSame( 'nothing', Blueworx_Clubhouse_Playtomic_Client::scrub( 'nothing', array( '' ) ) );
	}

	public function test_get_sends_bearer_and_query(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 200, 'body' => '{"ok":true}' );
		$data = $this->client()->get( '/api/v1/anything', array( 'a' => '1', 'b' => array( 'x', 'y' ) ) );
		$this->assertSame( array( 'ok' => true ), $data );
		$req = $this->t->seen[1];
		$this->assertSame( 'GET', $req['method'] );
		$this->assertSame( 'https://thirdparty.playtomic.io/api/v1/anything?a=1&b=x&b=y', $req['url'] );
		$this->assertStringStartsWith( 'Bearer eyJ', $req['headers']['Authorization'] );
	}

	public function test_401_refreshes_once_then_gives_up(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 401, 'body' => '' );
		$this->t->queue[] = $this->token_ok( array( 'ten-a' ) );
		$this->t->queue[] = array( 'status' => 401, 'body' => '' );
		$this->expectExceptionMessage( 'Playtomic refused the request' );
		try {
			$this->client()->get( '/api/v1/x' );
		} finally {
			$this->assertCount( 4, $this->t->seen, 'token, call, new token, call — and no more' );
		}
	}

	public function test_bookings_follows_pages_until_a_short_one(): void {
		$full = json_encode( array_fill( 0, 200, array( 'booking_id' => 'b' ) ) );
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 200, 'body' => $full );
		$this->t->queue[] = array( 'status' => 200, 'body' => json_encode( array( array( 'booking_id' => 'last' ) ) ) );
		$all = $this->client()->bookings( 'ten-a', '2026-10-01T00:00:00', '2026-10-31T23:59:59' );
		$this->assertCount( 201, $all );
		$this->assertStringContainsString( 'tenant_id=ten-a', $this->t->seen[1]['url'] );
		$this->assertStringContainsString( 'start_booking_date=2026-10-01T00%3A00%3A00', $this->t->seen[1]['url'] );
		$this->assertStringContainsString( 'page=0', $this->t->seen[1]['url'] );
		$this->assertStringContainsString( 'size=200', $this->t->seen[1]['url'] );
		$this->assertStringContainsString( 'page=1', $this->t->seen[2]['url'] );
	}

	public function test_bookings_refuses_a_bad_date_before_calling(): void {
		$this->expectExceptionMessage( 'Dates must look like 2026-10-01T00:00:00' );
		$this->client()->bookings( 'ten-a', '2026-10-01', '2026-10-31' );
		$this->assertSame( array(), $this->t->seen );
	}

	public function test_players_follows_cursors_and_falls_back_to_the_singular_path(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 404, 'body' => '' );
		$this->t->queue[] = array( 'status' => 200, 'body' => json_encode( array( 'has_more' => true, 'next_cursor_id' => 'c2', 'data' => array( array( 'player_id' => '1' ) ) ) ) );
		$this->t->queue[] = array( 'status' => 200, 'body' => json_encode( array( 'has_more' => false, 'next_cursor_id' => null, 'data' => array( array( 'player_id' => '2' ) ) ) ) );
		$all = $this->client()->players( 'ten-a', array( 'BENEFITS' ) );
		$this->assertSame( array( '1', '2' ), array_column( $all, 'player_id' ) );
		$this->assertStringContainsString( '/api/v1/venues/ten-a/players?', $this->t->seen[1]['url'] );
		$this->assertStringContainsString( '/api/v1/venue/ten-a/players?', $this->t->seen[2]['url'] );
		$this->assertStringContainsString( 'include=BENEFITS', $this->t->seen[2]['url'] );
		$this->assertStringContainsString( 'cursor_id=c2', $this->t->seen[3]['url'] );
		$this->assertStringContainsString( '/api/v1/venue/', $this->t->seen[3]['url'], 'once the singular path answered, it is kept' );
	}

	public function test_player_fetches_one(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 200, 'body' => '{"player_id":"9","name":"Nine"}' );
		$this->assertSame( 'Nine', $this->client()->player( 'ten-a', '9' )['name'] );
		$this->assertStringEndsWith( '/api/v1/venues/ten-a/players/9', $this->t->seen[1]['url'] );
	}

	public function test_payments_follows_cursors_and_says_when_still_preparing(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 202, 'body' => '{"status":"PROCESSING"}', 'headers' => array( 'retry-after' => '30' ) );
		try {
			$this->client()->payments( 'ten-a' );
			$this->fail( 'expected an exception' );
		} catch ( Blueworx_Clubhouse_Playtomic_Exception $e ) {
			$this->assertSame( 202, $e->status() );
			$this->assertStringContainsString( '30 seconds', $e->getMessage() );
		}

		$this->t->queue[] = array( 'status' => 200, 'body' => json_encode( array( 'has_more' => true, 'next_cursor_id' => 'n', 'data' => array( array( 'payment_id' => 'p1' ) ) ) ) );
		$this->t->queue[] = array( 'status' => 200, 'body' => json_encode( array( 'has_more' => false, 'next_cursor_id' => null, 'data' => array( array( 'payment_id' => 'p2' ) ) ) ) );
		$all = $this->client()->payments( 'ten-a', array( 'start_payment_date' => '2026-09-01T00:00:00Z' ) );
		$this->assertSame( array( 'p1', 'p2' ), array_column( $all, 'payment_id' ) );
		$this->assertStringContainsString( 'start_payment_date=', $this->t->seen[2]['url'] );
		$this->assertStringContainsString( 'limit=100', $this->t->seen[2]['url'] );
	}
}
