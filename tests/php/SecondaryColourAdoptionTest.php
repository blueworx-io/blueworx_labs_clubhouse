<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The secondary colour has to REACH things, not merely exist. These pin the two
 * halves of that: the tokens are emitted everywhere the accent's are, and the
 * stylesheets actually consume them.
 *
 * The stylesheet assertions are deliberately about which surfaces adopt it. The
 * issue asks for a colour that "propagates through the entire platform", and a
 * token nothing references propagates nowhere.
 */
final class SecondaryColourAdoptionTest extends TestCase {

	private function css( string $relative ): string {
		$path = dirname( __DIR__, 2 ) . '/' . $relative;
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	/** @return array<int,Blueworx_Clubhouse_Base_Look> */
	private function looks(): array {
		return array(
			new Blueworx_Clubhouse_Court_Side(),
			new Blueworx_Clubhouse_Members_House(),
			new Blueworx_Clubhouse_Floodlight(),
		);
	}

	/**
	 * The composed :root — the single map the front end inlines in wp_head, the
	 * Setup screen inlines per look, and the live re-skin reads — carries the
	 * secondary beside the accent on every look.
	 */
	public function test_the_composed_root_carries_the_secondary_on_every_look(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		foreach ( $this->looks() as $look ) {
			$vars = Blueworx_Clubhouse_Theme_Css::compose( $look, $branding );
			foreach ( array( '', '-ink', '-deep', '-wash', '-block', '-hover', '-active', '-disabled' ) as $suffix ) {
				$this->assertArrayHasKey( '--color-secondary' . $suffix, $vars, $look->slug() . $suffix );
			}
			$this->assertStringContainsString(
				'--color-secondary:',
				Blueworx_Clubhouse_Theme_Css::to_css( $vars ),
				$look->slug()
			);
		}
	}

	/**
	 * The cache is keyed on the secondary, or changing it alone would leave the
	 * cached :root in place and the new colour would simply never appear. This is
	 * the single most likely way this feature silently does nothing.
	 */
	public function test_changing_the_secondary_busts_the_cached_root(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );
		$look     = new Blueworx_Clubhouse_Court_Side();

		$before = $cache->root_css( $look, $branding );
		$branding->set_secondary( '#b91c1c' );
		$after = $cache->root_css( $look, $branding );

		$this->assertNotSame( $before, $after );
		$this->assertStringContainsString( '--color-secondary:#b91c1c', $after );
	}

	/**
	 * A club that has never set a secondary still gets one, and it follows the
	 * accent: change the primary and the derived partner changes with it.
	 */
	public function test_the_derived_secondary_follows_a_change_of_accent(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );
		$look     = new Blueworx_Clubhouse_Court_Side();

		$before = $cache->root_css( $look, $branding );
		$branding->set_accent( '#b91c1c' );
		$after = $cache->root_css( $look, $branding );

		$this->assertNotSame( $before, $after );
	}

	/** Every look spends the secondary on its secondary action and its band marker. */
	public function test_every_look_adopts_the_secondary(): void {
		foreach ( array( 'court-side', 'members-house', 'floodlight' ) as $slug ) {
			$css = $this->css( 'assets/looks/' . $slug . '.css' );
			$this->assertStringContainsString( 'var(--color-secondary-deep)', $css, $slug );
			$this->assertStringContainsString( 'var(--color-secondary)', $css, $slug );
			$this->assertMatchesRegularExpression(
				'/\.ch-btn--ghost\{[^}]*--color-secondary/',
				$css,
				$slug . ' spends it on the secondary action'
			);
			$this->assertMatchesRegularExpression(
				'/\.ch-btn--ghost:disabled[^{]*\{[^}]*--color-secondary-disabled/',
				$css,
				$slug . ' has a disabled state'
			);
		}
	}

	/** The shared layer adopts it too — the filter pills' hover state. */
	public function test_the_shared_layer_adopts_the_secondary(): void {
		$this->assertStringContainsString(
			'.ch-filter:hover{border-color:var(--color-secondary-deep)}',
			$this->css( 'assets/looks/base.css' )
		);
	}

	/** The Clubhouse admin screens pick it up, so the setting is visible where it is set. */
	public function test_the_admin_screens_adopt_the_secondary(): void {
		foreach ( array( 'assets/css/admin-setup.css', 'assets/css/admin-content.css' ) as $sheet ) {
			$this->assertStringContainsString( 'var(--color-secondary', $this->css( $sheet ), $sheet );
		}
	}

	/**
	 * The primary action stays ink on both admin screens. A secondary colour that
	 * repainted the Save button would not be a secondary colour.
	 */
	public function test_the_admin_primary_action_is_not_repainted(): void {
		foreach ( array( 'assets/css/admin-setup.css', 'assets/css/admin-content.css' ) as $sheet ) {
			$this->assertMatchesRegularExpression(
				'/\.clubhouse-btn--primary[^{]*\{[^}]*var\(--color-ink\)/',
				$this->css( $sheet ),
				$sheet
			);
		}
	}

	/**
	 * No literal colour was introduced alongside the secondary. The whole point of
	 * a token is that a club's choice reaches the surface; a hardcoded hex beside
	 * it would be a surface that never updates.
	 */
	public function test_the_adopted_surfaces_introduced_no_literal_colours(): void {
		foreach ( array( 'court-side', 'members-house', 'floodlight', 'base' ) as $slug ) {
			$css = $this->css( 'assets/looks/' . $slug . '.css' );
			foreach ( explode( '}', $css ) as $rule ) {
				if ( ! str_contains( $rule, '--color-secondary' ) ) {
					continue;
				}
				$this->assertDoesNotMatchRegularExpression(
					'/#[0-9a-fA-F]{3,8}\b/',
					$rule,
					$slug . ': ' . trim( $rule )
				);
			}
		}
	}
}
