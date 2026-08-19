<?php

use PHPUnit\Framework\TestCase;

final class DashboardAssetsTest extends TestCase {

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	public function test_the_vendored_stylesheet_is_on_disk(): void {
		$this->assertFileExists( $this->root() . '/assets/bw/bw.css' );
	}

	public function test_the_fonts_it_asks_for_are_beside_it(): void {
		// A font url that resolves to nothing is a silent fallback to a system
		// face — the page still renders, so nothing else would ever catch it.
		$css = (string) file_get_contents( $this->root() . '/assets/bw/bw.css' );
		preg_match_all( '/url\(\s*["\']?([^"\')]+)["\']?\s*\)/', $css, $found );
		$this->assertNotSame( array(), $found[1], 'the stylesheet declares no fonts at all' );
		foreach ( $found[1] as $url ) {
			if ( str_starts_with( $url, 'data:' ) ) {
				continue;
			}
			$this->assertFileExists( $this->root() . '/assets/bw/' . $url );
		}
	}

	public function test_every_font_is_a_whole_file(): void {
		// A WOFF2 records its own total length in bytes 8-11. A file that
		// disagrees with itself was truncated or garbled in transit — the
		// browser rejects it and silently falls back to a system face, so
		// nothing else here would ever notice.
		$fonts = glob( $this->root() . '/assets/bw/fonts/*.woff2' );
		$this->assertNotSame( array(), $fonts, 'no fonts are vendored at all' );
		foreach ( $fonts as $path ) {
			$head = (string) file_get_contents( $path, false, null, 0, 12 );
			$this->assertSame( 'wOF2', substr( $head, 0, 4 ), basename( $path ) . ' is not a WOFF2 file' );
			$declared = unpack( 'N', substr( $head, 8, 4 ) )[1];
			$this->assertSame( filesize( $path ), $declared, basename( $path ) . ' disagrees with its own recorded length' );
		}
	}

	public function test_nothing_in_it_can_reach_markup_that_has_not_opted_in(): void {
		// The club's own pages must be untouchable from here. Every selector has
		// to be a .bw- class or sit under .bw-admin; a bare element selector
		// would restyle whatever page this is ever loaded on, including
		// SureCart's own controls inside our panels.
		$css = (string) file_get_contents( $this->root() . '/assets/bw/bw.css' );
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		// @keyframes nests one level deeper than every other rule here (its
		// body is itself made of percentage/from/to blocks), which the flat
		// brace-splitter below can't follow — so drop whole @keyframes blocks
		// outright. Their stops (e.g. "50%") aren't selectors that can ever
		// reach page markup on their own; only font-face/media/supports have
		// a single-brace header to strip.
		$css = (string) preg_replace( '/@keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $css );
		$css = (string) preg_replace( '/@(font-face|media|supports)[^{]*\{/', '{', $css );

		$offenders = array();
		foreach ( explode( '}', $css ) as $chunk ) {
			$brace = strpos( $chunk, '{' );
			if ( false === $brace ) {
				continue;
			}
			$selectors = substr( $chunk, 0, $brace );
			foreach ( explode( ',', $selectors ) as $selector ) {
				$selector = trim( $selector );
				if ( '' === $selector || str_starts_with( $selector, '@' ) ) {
					continue;
				}
				if ( ':root' === $selector || str_starts_with( $selector, '.bw-' ) ) {
					continue;
				}
				$offenders[] = $selector;
			}
		}
		$this->assertSame( array(), array_unique( $offenders ), 'unscoped selectors in the vendored stylesheet' );
	}

	public function test_the_handle_and_path_agree(): void {
		$this->assertSame( 'blueworx-clubhouse-bw', Blueworx_Clubhouse_Dashboard_Assets::handle() );
		$this->assertSame( 'assets/bw/bw.css', Blueworx_Clubhouse_Dashboard_Assets::relative_path() );
		$this->assertFileExists( $this->root() . '/' . Blueworx_Clubhouse_Dashboard_Assets::relative_path() );
	}
}
