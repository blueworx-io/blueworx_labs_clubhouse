<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageEditorLibraryTest extends TestCase {

	public function test_the_vendored_library_is_the_one_the_skill_folder_carries(): void {
		$root   = dirname( __DIR__, 2 );
		$mine   = $this->hash_tree( $root . '/blueworx-page-editor' );
		$theirs = $this->hash_tree( $root . '/.claude/skills/blueworx-admin-design/editor/php' );
		$this->assertSame( $theirs, $mine, 'The vendored library has drifted from the design system copy. Re-pull it; never edit it.' );
	}

	public function test_the_browser_half_is_the_one_the_skill_folder_carries(): void {
		$root = dirname( __DIR__, 2 );
		$this->assertSame(
			md5_file( $root . '/.claude/skills/blueworx-admin-design/editor/blueworx-page-editor.js' ),
			md5_file( $root . '/assets/blueworx-page-editor.js' )
		);
	}

	/** @return array<string,string> relative path => hash, sorted */
	private function hash_tree( string $dir ): array {
		$out   = array();
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			$out[ str_replace( '\\', '/', substr( (string) $file, strlen( $dir ) + 1 ) ) ] = md5_file( (string) $file );
		}
		ksort( $out );
		return $out;
	}
}
