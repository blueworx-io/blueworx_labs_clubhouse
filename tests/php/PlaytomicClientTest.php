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
}
