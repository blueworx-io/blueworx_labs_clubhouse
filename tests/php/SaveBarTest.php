<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Issue #148: the save bar was laid out with `space-between`, so the Save
 * button only sat right while the "You have unsaved changes" hint was on
 * screen. Untouched form, no hint, button jumps left. The menu panel had its
 * own bar entirely — a plain WordPress button, unstyled, in a class nothing
 * else used.
 *
 * One bar, one class, Save pinned right on every screen.
 */
final class SaveBarTest extends TestCase {

	private function css( string $file ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/' . $file );
	}

	/** @return array<int,string> */
	public static function stylesheets(): array {
		return array( array( 'admin-setup.css' ) );
	}

	#[DataProvider( 'stylesheets' )]
	public function test_the_bar_pins_its_button_right( string $file ): void {
		$css = $this->css( $file );
		$this->assertMatchesRegularExpression(
			'/\.clubhouse-bar \{[^}]*justify-content: flex-end/',
			$css,
			"{$file} pins the bar's contents right"
		);
		$this->assertDoesNotMatchRegularExpression(
			'/\.clubhouse-bar \{[^}]*space-between/',
			$css,
			"{$file} no longer depends on a second item being present"
		);
	}

	/**
	 * The hint takes the leftover room itself, so it still reads as sitting on
	 * the left of the bar rather than crowding the button.
	 */
	#[DataProvider( 'stylesheets' )]
	public function test_the_hint_pushes_itself_left( string $file ): void {
		$this->assertMatchesRegularExpression(
			'/\.clubhouse-bar__hint \{[^}]*margin: 0 auto 0 0/',
			$this->css( $file )
		);
	}

	#[DataProvider( 'stylesheets' )]
	public function test_the_bar_wraps_on_a_narrow_screen( string $file ): void {
		$this->assertMatchesRegularExpression(
			'/\.clubhouse-bar \{[^}]*flex-wrap: wrap/',
			$this->css( $file )
		);
	}

	/** Every screen's save bar is the same bar. */
	public function test_no_screen_rolls_its_own_save_bar(): void {
		$found = array();
		foreach ( (array) glob( dirname( __DIR__, 2 ) . '/includes/admin/*.php' ) as $path ) {
			$php = (string) file_get_contents( (string) $path );
			if ( str_contains( $php, 'clubhouse-savebar' ) ) {
				$found[] = basename( (string) $path );
			}
		}
		$this->assertSame( array(), $found, 'these still use their own save bar' );
	}

	public function test_the_menu_panel_uses_the_shared_bar_and_button(): void {
		$php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/class-menu-panel.php' );
		$this->assertStringContainsString( '<div class="clubhouse-bar">', $php );
		$this->assertStringContainsString( 'class="clubhouse-btn clubhouse-btn--primary"', $php );
		// WordPress's own button classes would style it differently from the
		// Save on every other Clubhouse screen.
		$this->assertStringNotContainsString( 'class="button button-primary"', $php );
	}
}
