<?php
// tests/php/ExternalChromeTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * The chrome wrapped around pages another plugin owns. Both halves of the
 * decision — whether to dress a request, and where the chrome goes in the
 * response — are pure, so they are tested without a WordPress runtime.
 */
final class ExternalChromeTest extends TestCase {

	/** SureCart's dashboard is the page this exists for. */
	public function test_a_surecart_templated_page_is_dressed(): void {
		$this->assertTrue(
			Blueworx_Clubhouse_External_Chrome::dresses( false, true, 'pages/template-surecart-dashboard.php' )
		);
	}

	/**
	 * A clubhouse page renders its own complete document. Dressing it would
	 * print a second header and a second footer inside the first.
	 */
	public function test_a_clubhouse_page_is_never_dressed(): void {
		$this->assertFalse(
			Blueworx_Clubhouse_External_Chrome::dresses( true, true, 'pages/template-surecart-dashboard.php' )
		);
	}

	public function test_an_ordinary_theme_page_is_left_alone(): void {
		$this->assertFalse( Blueworx_Clubhouse_External_Chrome::dresses( false, true, '' ) );
		$this->assertFalse( Blueworx_Clubhouse_External_Chrome::dresses( false, true, 'page-wide.php' ) );
	}

	/** Archives, feeds and the blog index are the theme's business, not ours. */
	public function test_a_non_singular_request_is_left_alone(): void {
		$this->assertFalse(
			Blueworx_Clubhouse_External_Chrome::dresses( false, false, 'pages/template-surecart-dashboard.php' )
		);
	}

	/** SureCart's checkout carries no template slug, so it opts in by filter instead. */
	public function test_the_filter_opts_a_page_in_without_a_template_slug(): void {
		$this->assertTrue( Blueworx_Clubhouse_External_Chrome::dresses( false, true, '', true ) );
		$this->assertFalse(
			Blueworx_Clubhouse_External_Chrome::dresses( true, true, '', true ),
			'the filter must not override the clubhouse-page exclusion'
		);
	}

	public function test_chrome_goes_inside_the_body_not_around_it(): void {
		$html = Blueworx_Clubhouse_External_Chrome::inject(
			'<!doctype html><html><head></head><body class="page"><p>dash</p></body></html>',
			'<header>H</header>',
			'<footer>F</footer>'
		);

		$this->assertSame(
			'<!doctype html><html><head></head><body class="page"><header>H</header><p>dash</p><footer>F</footer></body></html>',
			$html
		);
	}

	/** A <body> mentioned in the markup before the real tag must not fool the split. */
	public function test_injection_uses_the_first_body_tag_and_the_last_closing_one(): void {
		$html = Blueworx_Clubhouse_External_Chrome::inject(
			'<body><div>a</div><!-- </body> --></body>',
			'[H]',
			'[F]'
		);
		$this->assertSame( '<body>[H]<div>a</div><!-- </body> -->[F]</body>', $html );
	}

	/**
	 * A response with no body — a feed, a JSON payload, a redirect — comes back
	 * untouched. Half-injected chrome would be worse than none.
	 */
	public function test_a_response_without_a_body_is_returned_unchanged(): void {
		$json = '{"ok":true}';
		$this->assertSame( $json, Blueworx_Clubhouse_External_Chrome::inject( $json, '[H]', '[F]' ) );
		$this->assertSame(
			'<body>unclosed',
			Blueworx_Clubhouse_External_Chrome::inject( '<body>unclosed', '[H]', '[F]' )
		);
	}

	/**
	 * The content well must not be .ch-main: reveal.js hides every child of
	 * .ch-main until it scrolls into view, and the looks add flow margins to
	 * them. Either would be us styling another plugin's markup.
	 */
	public function test_the_content_well_is_not_ch_main(): void {
		$open = Blueworx_Clubhouse_External_Chrome::open_content();
		$this->assertStringContainsString( 'class="ch-external"', $open );
		$this->assertStringNotContainsString( 'class="ch-main', $open );
		// The id stays: the header's skip link points at #ch-main, and skipping to
		// the content has to work on these pages too.
		$this->assertStringContainsString( 'id="ch-main"', $open );
	}

	public function test_the_content_well_closes_every_element_it_opens(): void {
		$markup = Blueworx_Clubhouse_External_Chrome::open_content() . Blueworx_Clubhouse_External_Chrome::close_content();
		$this->assertSame(
			substr_count( $markup, '<div' ),
			substr_count( $markup, '</div>' )
		);
		$this->assertSame( 1, substr_count( $markup, '<main' ) );
		$this->assertSame( 1, substr_count( $markup, '</main>' ) );
	}

	public function test_register_buffers_the_response_and_enqueues_the_look(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_External_Chrome::register();

		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'template_redirect', $actions );
		$this->assertContains( 'wp_enqueue_scripts', $actions );

		$enqueue = array_values( array_filter(
			wp_stub_calls( 'add_action' ),
			static fn( $c ) => 'wp_enqueue_scripts' === $c['args'][0]
		) );
		$this->assertGreaterThan(
			10,
			$enqueue[0]['args'][2] ?? 10,
			'the look stylesheet must print after the theme stylesheet, same as on clubhouse pages'
		);
	}
}
