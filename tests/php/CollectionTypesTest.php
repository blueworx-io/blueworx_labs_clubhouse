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

	/**
	 * The six lists have a menu of their own, directly under Clubhouse.
	 *
	 * They spent v0.101.0 to v0.101.5 inside the Clubhouse menu instead, which
	 * buried the Setup screen: WordPress opens a menu at its first child, and
	 * the first child had become the Sports list.
	 */
	public function test_the_lists_sit_under_the_collections_menu(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Collection_Types::register();
		$calls = wp_stub_calls( 'register_post_type' );
		$this->assertNotEmpty( $calls );
		foreach ( $calls as $call ) {
			$this->assertSame( Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG, $call['args'][1]['show_in_menu'] );
		}
	}

	/** Each field is registered where the page editor library reads it. */
	public function test_meta_is_registered_at_the_address_the_library_uses(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Collection_Types::register();

		$keys = array_map( static fn( $c ) => $c['args'][1], wp_stub_calls( 'register_post_meta' ) );

		$this->assertContains( Blueworx_Clubhouse_Collection_Meta::meta_key( 'clubhouse_team', 'sport' ), $keys );
		$this->assertNotContains( 'sport', $keys );
	}
}
