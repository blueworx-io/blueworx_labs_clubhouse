<?php
// tests/php/NativePagesTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class NativePagesTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_register_hooks_admin_menu_and_admin_bar(): void {
		Blueworx_Clubhouse_Native_Pages::register();
		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'admin_menu', $actions );
		$this->assertContains( 'admin_bar_menu', $actions );
	}

	public function test_hide_pages_menu_removes_the_pages_top_level_item(): void {
		Blueworx_Clubhouse_Native_Pages::hide_pages_menu();
		$removed = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'remove_menu_page' ) );
		$this->assertSame( array( 'edit.php?post_type=page' ), $removed );
	}

	public function test_hide_new_page_node_removes_the_admin_bar_new_page_item(): void {
		$bar = new class() {
			/** @var array<int,string> */
			public array $removed = array();
			public function remove_node( string $id ): void {
				$this->removed[] = $id;
			}
		};
		Blueworx_Clubhouse_Native_Pages::hide_new_page_node( $bar );
		$this->assertSame( array( 'new-page' ), $bar->removed );
	}
}
