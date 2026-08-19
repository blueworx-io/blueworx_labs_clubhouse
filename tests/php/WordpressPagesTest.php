<?php
// tests/php/WordpressPagesTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class WordpressPagesTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_the_menu_slug_is_wordpress_own_pages_screen(): void {
		$this->assertSame( 'edit.php?post_type=page', Blueworx_Clubhouse_Wordpress_Pages::MENU_SLUG );
	}

	/**
	 * Late, so every plugin has added its menus before ours has the last word —
	 * and so a menu another plugin adds back is still taken off.
	 */
	public function test_it_hides_the_menu_after_every_plugin_has_added_its_own(): void {
		Blueworx_Clubhouse_Wordpress_Pages::register();

		$args = array_map( static fn( array $c ): array => $c['args'], wp_stub_calls( 'add_action' ) );

		$this->assertContains(
			array( 'admin_menu', array( Blueworx_Clubhouse_Wordpress_Pages::class, 'hide_menu' ), 999 ),
			$args
		);
	}
}
