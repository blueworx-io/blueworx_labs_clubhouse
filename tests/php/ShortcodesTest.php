<?php
// tests/php/ShortcodesTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ShortcodesTest extends TestCase {

	protected function tearDown(): void {
		// The seam is global state, exactly like Links::set_resolver. A test that
		// installs an expander and leaves it installed would silently turn on
		// shortcode execution for every test that runs after it.
		Blueworx_Clubhouse_Shortcodes::set_expander( null );
	}

	public function test_without_an_expander_the_text_is_escaped_not_executed(): void {
		$this->assertFalse( Blueworx_Clubhouse_Shortcodes::is_live() );
		$this->assertSame(
			'[surecart_checkout id=&quot;1&quot;]',
			Blueworx_Clubhouse_Shortcodes::expand( '[surecart_checkout id="1"]' )
		);
	}

	/**
	 * The safe default matters more than the useful one: the preview, the tests
	 * and any environment that forgets to install the seam must never execute
	 * stored text just because the seam was left unset.
	 */
	public function test_markup_in_an_unexpanded_value_cannot_reach_the_page_as_html(): void {
		$out = Blueworx_Clubhouse_Shortcodes::expand( '<script>alert(1)</script>' );
		$this->assertStringNotContainsString( '<script>', $out );
		$this->assertStringContainsString( '&lt;script&gt;', $out );
	}

	public function test_an_installed_expander_receives_the_raw_text_and_its_html_is_kept(): void {
		$seen = null;
		Blueworx_Clubhouse_Shortcodes::set_expander(
			static function ( string $text ) use ( &$seen ): string {
				$seen = $text;
				return '<div class="sc">expanded</div>';
			}
		);
		$this->assertTrue( Blueworx_Clubhouse_Shortcodes::is_live() );
		$out = Blueworx_Clubhouse_Shortcodes::expand( '[surecart_checkout id="1"]' );

		$this->assertSame( '[surecart_checkout id="1"]', $seen, 'the expander gets the value unescaped' );
		$this->assertSame( '<div class="sc">expanded</div>', $out, 'its HTML is returned as-is' );
	}

	public function test_empty_and_whitespace_values_expand_to_nothing(): void {
		Blueworx_Clubhouse_Shortcodes::set_expander( static fn( string $t ): string => 'SHOULD NOT RUN' );
		$this->assertSame( '', Blueworx_Clubhouse_Shortcodes::expand( '' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Shortcodes::expand( "  \n " ) );
	}

	public function test_setting_null_restores_the_escaping_default(): void {
		Blueworx_Clubhouse_Shortcodes::set_expander( static fn( string $t ): string => '<b>live</b>' );
		Blueworx_Clubhouse_Shortcodes::set_expander( null );
		$this->assertFalse( Blueworx_Clubhouse_Shortcodes::is_live() );
		$this->assertSame( '[x]', Blueworx_Clubhouse_Shortcodes::expand( '[x]' ) );
	}
}
