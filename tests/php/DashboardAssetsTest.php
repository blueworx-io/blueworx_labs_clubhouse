<?php

use PHPUnit\Framework\TestCase;

final class DashboardAssetsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	protected function tearDown(): void {
		wp_stub_reset();
	}

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	/** The handles put on the page so far. */
	private function enqueued(): array {
		return array_map(
			static fn ( array $c ): string => (string) ( $c['args'][0] ?? '' ),
			wp_stub_calls( 'wp_enqueue_style' )
		);
	}

	/** Record which page of the shop's is which, and which one is being read. */
	private function reading( string $page ): void {
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( 'dashboard' ), 42 );
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( 'checkout' ), 43 );
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( 'order-confirmation' ), 44 );
		$ids                                  = array(
			'dashboard'          => 42,
			'checkout'           => 43,
			'order-confirmation' => 44,
			'something-else'     => 99,
		);
		$GLOBALS['wp_stub_queried_object_id'] = $ids[ $page ];
	}

	public function test_only_the_commerce_pages_are_taken_over_by_post_id(): void {
		// The member area moved to a Clubhouse route, so no post id maps to it.
		// Checkout and the thank-you page are still SureCart's own pages.
		$this->assertSame( '', Blueworx_Clubhouse_Dashboard_Assets::page_key( 0 ) );
		$this->assertSame( '', Blueworx_Clubhouse_Dashboard_Assets::page_key( 4242 ) );
	}

	public function test_the_stylesheet_is_asked_for_before_the_page_is_drawn(): void {
		// Queued while WordPress is still collecting styles for the head. Left
		// to the content filter it would arrive in the footer instead, and the
		// member would watch the page snap into shape after it had loaded.
		foreach ( array( 'checkout', 'order-confirmation' ) as $page ) {
			wp_stub_reset();
			$this->reading( $page );
			Blueworx_Clubhouse_Dashboard_Assets::declare_style();
			$this->assertContains( Blueworx_Clubhouse_Dashboard_Assets::handle(), $this->enqueued(), $page . ' renders unstyled' );
		}
	}

	public function test_no_other_page_on_the_club_site_is_given_it(): void {
		// The two design systems never meet.
		$this->reading( 'something-else' );
		Blueworx_Clubhouse_Dashboard_Assets::declare_style();
		$this->assertNotContains( Blueworx_Clubhouse_Dashboard_Assets::handle(), $this->enqueued() );
	}

	public function test_a_site_with_no_shop_pages_recorded_is_left_alone(): void {
		$GLOBALS['wp_stub_queried_object_id'] = 0;
		Blueworx_Clubhouse_Dashboard_Assets::declare_style();
		$this->assertNotContains( Blueworx_Clubhouse_Dashboard_Assets::handle(), $this->enqueued() );
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

	/**
	 * The club's own pages must be untouchable from here. Every selector has
	 * to be a .bw- class, a .clubhouse-member class, or (in surecart.css only)
	 * one of that file's own .clubhouse-checkout frame selectors — sit under
	 * .bw-admin, or a bare element/tag selector would restyle whatever page
	 * this is ever loaded on, including SureCart's own controls inside our
	 * panels or a light-DOM component like sc-button.
	 *
	 * @param array<int,string> $extra_allowed_prefixes Selector prefixes this
	 *                                                   file is allowed to use
	 *                                                   beyond the shared set.
	 */
	private function assert_nothing_unscoped( string $relative_path, array $extra_allowed_prefixes = array() ): void {
		$css = (string) file_get_contents( $this->root() . '/' . $relative_path );
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		// @keyframes nests one level deeper than every other rule here (its
		// body is itself made of percentage/from/to blocks), which the flat
		// brace-splitter below can't follow — so drop whole @keyframes blocks
		// outright. Their stops (e.g. "50%") aren't selectors that can ever
		// reach page markup on their own; only font-face/media/supports have
		// a single-brace header to strip.
		$css = (string) preg_replace( '/@keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $css );
		$css = (string) preg_replace( '/@(font-face|media|supports)[^{]*\{/', '{', $css );

		$allowed_prefixes = array_merge( array( '.bw-', '.clubhouse-member' ), $extra_allowed_prefixes );

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
				if ( ':root' === $selector ) {
					continue;
				}
				$allowed = false;
				foreach ( $allowed_prefixes as $prefix ) {
					if ( str_starts_with( $selector, $prefix ) ) {
						$allowed = true;
						break;
					}
				}
				if ( ! $allowed ) {
					$offenders[] = $selector;
				}
			}
		}
		$this->assertSame( array(), array_unique( $offenders ), 'unscoped selectors in ' . $relative_path );
	}

	public function test_nothing_in_it_can_reach_markup_that_has_not_opted_in(): void {
		$this->assert_nothing_unscoped( 'assets/bw/bw.css' );
	}

	public function test_nothing_in_the_surecart_stylesheet_can_reach_markup_that_has_not_opted_in(): void {
		// Its own frame selectors are legitimate — they draw the checkout's
		// own header, footer and pay bar, not a club's public pages.
		$this->assert_nothing_unscoped( 'assets/bw/surecart.css', array( '.clubhouse-checkout' ) );
	}

	public function test_the_handle_and_path_agree(): void {
		$this->assertSame( 'blueworx-clubhouse-bw', Blueworx_Clubhouse_Dashboard_Assets::handle() );
		$this->assertSame( 'assets/bw/bw.css', Blueworx_Clubhouse_Dashboard_Assets::relative_path() );
		$this->assertFileExists( $this->root() . '/' . Blueworx_Clubhouse_Dashboard_Assets::relative_path() );
	}

	public function test_the_surecart_stylesheet_is_queued_on_checkout_only(): void {
		// The token mapping is the only thing that makes SureCart's own fields
		// look like the member area, so it has to be on the page where they
		// render — and on no other, because it would otherwise leak the
		// member-area look onto a club's public shop pages.
		$this->assertTrue(
			Blueworx_Clubhouse_Dashboard_Assets::wants_surecart_style( 'checkout' )
		);
		$this->assertFalse(
			Blueworx_Clubhouse_Dashboard_Assets::wants_surecart_style( 'order-confirmation' )
		);
		$this->assertFalse(
			Blueworx_Clubhouse_Dashboard_Assets::wants_surecart_style( '' )
		);
	}

	public function test_the_pay_button_gets_a_44px_target_on_a_phone(): void {
		// Every other tappable target in the narrow-viewport block is at least
		// 44px so a finger can hit it; the pay button is the primary action on
		// the page and must not be the one target smaller than that.
		$css   = (string) file_get_contents( $this->root() . '/assets/bw/surecart.css' );
		$start = strpos( $css, '@media (max-width: 640px)' );
		$this->assertIsInt( $start, 'no narrow-viewport block found in the stylesheet' );
		$end = strpos( $css, '@media (max-width: 900px)', $start );
		$this->assertIsInt( $end, 'no boundary found after the narrow-viewport block' );
		$narrow = substr( $css, $start, $end - $start );

		$this->assertMatchesRegularExpression(
			'/sc-order-submit sc-button\s*\{[^}]*--sc-input-height-large:\s*44px/',
			$narrow,
			'the pay button is not raised to a 44px target inside the narrow-viewport block'
		);
	}

	public function test_the_surecart_stylesheet_is_a_real_file(): void {
		// A handle pointing at nothing registers happily and 404s in the
		// browser, which looks like SureCart's default rather than a bug.
		$this->assertFileExists(
			dirname( __DIR__, 2 ) . '/' . Blueworx_Clubhouse_Dashboard_Assets::surecart_relative_path()
		);
	}
}
