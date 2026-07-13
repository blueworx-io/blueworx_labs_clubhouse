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

	public function test_cpts_mount_under_the_content_parent(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Collection_Types::register();
		$calls = wp_stub_calls( 'register_post_type' );
		$this->assertNotEmpty( $calls );
		foreach ( $calls as $call ) {
			$this->assertSame( 'clubhouse-content', $call['args'][1]['show_in_menu'] );
		}
	}

	public function test_register_content_menu_adds_parent_and_drops_duplicate(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Collection_Types::register_content_menu();
		$menu = wp_stub_calls( 'add_menu_page' );
		$this->assertNotEmpty( $menu );
		$this->assertSame( 'clubhouse-content', $menu[0]['args'][3] );
		$dropped = wp_stub_calls( 'remove_submenu_page' );
		$this->assertSame( array( 'clubhouse-content', 'clubhouse-content' ), $dropped[0]['args'] );
	}
}
