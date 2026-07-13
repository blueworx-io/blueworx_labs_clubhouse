<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class MediaTest extends TestCase {

	public function test_positive_id_resolves_to_a_url(): void {
		// The wp-stubs stub returns https://club.test/wp-content/uploads/att-{id}.png for a truthy id.
		$this->assertSame( 'https://club.test/wp-content/uploads/att-42.png', Blueworx_Clubhouse_Media::url( 42 ) );
	}

	public function test_zero_or_negative_id_is_empty(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Media::url( 0 ) );
		$this->assertSame( '', Blueworx_Clubhouse_Media::url( -5 ) );
	}
}
