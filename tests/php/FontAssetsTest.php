<?php
// tests/php/FontAssetsTest.php

use PHPUnit\Framework\TestCase;

final class FontAssetsTest extends TestCase {

	/** @return array<int,Blueworx_Clubhouse_Base_Look> */
	private function looks(): array {
		return array(
			new Blueworx_Clubhouse_Court_Side(),
			new Blueworx_Clubhouse_Members_House(),
			new Blueworx_Clubhouse_Floodlight(),
		);
	}

	public function test_every_declared_weight_has_a_bundled_woff2(): void {
		$root = dirname( __DIR__, 2 );
		foreach ( $this->looks() as $look ) {
			foreach ( $look->fonts() as $font ) {
				foreach ( $font['weights'] as $weight ) {
					$path = $root . '/assets/fonts/' . $font['stem'] . '-' . $weight . '.woff2';
					$this->assertFileExists( $path );
					$this->assertGreaterThan( 1000, (int) filesize( $path ), "$path looks empty" );
				}
			}
		}
	}
}
