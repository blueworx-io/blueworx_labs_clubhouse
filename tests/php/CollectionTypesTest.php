<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CollectionTypesTest extends TestCase {
	protected function setUp(): void { wp_stub_reset(); }

	public function test_registers_six_post_types(): void {
		Blueworx_Clubhouse_Collection_Types::register();
		$registered = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'register_post_type' ) );
		foreach ( Blueworx_Clubhouse_Collection_Types::POST_TYPES as $type ) {
			$this->assertContains( $type, $registered );
		}
		$this->assertCount( 6, wp_stub_calls( 'register_post_type' ) );
	}
}
