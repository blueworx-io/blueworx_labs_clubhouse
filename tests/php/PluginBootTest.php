<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The main plugin file is the only place the plugin's parts are switched on,
 * and it is the file most likely to take a merge conflict. A part whose
 * register() call is dropped there still exists, still passes its own tests,
 * and never runs — which is exactly what happened to the content migration in
 * v0.101.11: the code shipped, the hook did not, and a live site showed the
 * design's default words. This reads the file rather than booting WordPress
 * because PHPUnit here has no add_action to observe.
 */
final class PluginBootTest extends TestCase {

	private function main_file(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/blueworx-labs-clubhouse.php' );
	}

	public function test_the_content_migration_is_switched_on_at_boot(): void {
		$this->assertStringContainsString(
			'Blueworx_Clubhouse_Content_Migration::register();',
			$this->main_file(),
			'The content migration is never called unless the main plugin file registers it.'
		);
	}

	public function test_the_collection_migration_is_switched_on_at_boot(): void {
		$this->assertStringContainsString(
			'Blueworx_Clubhouse_Collection_Editors::register();',
			$this->main_file()
		);
	}
}
