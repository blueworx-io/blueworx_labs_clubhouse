<?php
// tests/php/PlaytomicSecretTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicSecretTest extends TestCase {

	public function test_round_trip(): void {
		$s = new Blueworx_Clubhouse_Playtomic_Secret( 'unit-test-key-material' );
		$stored = $s->encrypt( 'hunter2' );
		$this->assertNotSame( 'hunter2', $stored );
		$this->assertStringNotContainsString( 'hunter2', $stored );
		$this->assertSame( 'hunter2', $s->decrypt( $stored ) );
	}

	public function test_a_different_key_cannot_read_it(): void {
		$stored = ( new Blueworx_Clubhouse_Playtomic_Secret( 'one' ) )->encrypt( 'hunter2' );
		$this->assertSame( '', ( new Blueworx_Clubhouse_Playtomic_Secret( 'two' ) )->decrypt( $stored ) );
	}

	public function test_empty_in_empty_out(): void {
		$s = new Blueworx_Clubhouse_Playtomic_Secret( 'k' );
		$this->assertSame( '', $s->encrypt( '' ) );
		$this->assertSame( '', $s->decrypt( '' ) );
		$this->assertSame( '', $s->decrypt( 'not-base64-ciphertext' ) );
	}
}
