<?php
// tests/php/PlaytomicWpTransportTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Shaped like WordPress's CaseInsensitiveDictionary: iterable, with getAll(). */
final class Playtomic_Test_Header_Dictionary implements IteratorAggregate {
	/** @param array<string,mixed> $data */
	public function __construct( private array $data ) {}
	public function getAll(): array { return $this->data; }
	public function getIterator(): Traversable { return new ArrayIterator( $this->data ); }
}

final class PlaytomicWpTransportTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_dictionary_headers_come_back_lower_cased(): void {
		$GLOBALS['wp_stub_remote_response'] = array(
			'response' => array( 'code' => 429 ),
			'body'     => 'slow down',
			'headers'  => new Playtomic_Test_Header_Dictionary( array( 'Retry-After' => '30', 'X-Multi' => array( 'a', 'b' ) ) ),
		);
		$out = ( new Blueworx_Clubhouse_Playtomic_Wp_Transport() )->request( 'GET', 'https://example.test/x', array( 'A' => 'b' ), null );
		$this->assertSame( 429, $out['status'] );
		$this->assertSame( 'slow down', $out['body'] );
		$this->assertSame( '30', $out['headers']['retry-after'] );
		$this->assertSame( 'a, b', $out['headers']['x-multi'] );
	}

	public function test_plain_array_headers_work(): void {
		$GLOBALS['wp_stub_remote_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{}',
			'headers'  => array( 'Content-Type' => 'application/json' ),
		);
		$out = ( new Blueworx_Clubhouse_Playtomic_Wp_Transport() )->request( 'POST', 'https://example.test/x', array(), '{"a":1}' );
		$this->assertSame( 'application/json', $out['headers']['content-type'] );
		$this->assertSame( '{"a":1}', $GLOBALS['wp_stub_calls'][0]['args'][1]['body'] );
	}

	public function test_a_wp_error_becomes_a_playtomic_exception(): void {
		$GLOBALS['wp_stub_remote_response'] = new WP_Error( 'http_request_failed', 'timed out' );
		$this->expectException( Blueworx_Clubhouse_Playtomic_Exception::class );
		$this->expectExceptionMessage( 'Could not reach Playtomic: timed out.' );
		( new Blueworx_Clubhouse_Playtomic_Wp_Transport() )->request( 'GET', 'https://example.test/x', array(), null );
	}
}
