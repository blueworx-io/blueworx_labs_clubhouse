<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The update checker installs a release into the folder named by the slug it
 * is given. If that slug ever drifts from the plugin's real folder name, an
 * update lands as a second copy and the original is deactivated. Nothing in
 * the pipeline checks the two agree, so this does.
 */
final class AutoUpdatesTest extends TestCase {

	private function main_file(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/blueworx-labs-clubhouse.php' );
	}

	public function test_the_update_checker_watches_this_repo_under_the_plugins_own_slug(): void {
		$source = $this->main_file();
		$this->assertStringContainsString( "'https://github.com/blueworx-io/blueworx_labs_clubhouse/'", $source );
		$this->assertMatchesRegularExpression( "/buildUpdateChecker\(\s*'[^']+',\s*__FILE__,\s*'blueworx-labs-clubhouse'/s", $source );
	}

	public function test_updates_install_the_release_zip_not_the_source_tarball(): void {
		$this->assertStringContainsString( 'enableReleaseAssets()', $this->main_file() );
	}

	public function test_the_update_checker_ships_in_the_plugin(): void {
		$this->assertFileExists( dirname( __DIR__, 2 ) . '/plugin-update-checker/plugin-update-checker.php' );
	}
}
