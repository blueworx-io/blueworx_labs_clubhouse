<?php

use PHPUnit\Framework\TestCase;

final class TierGridCadenceTest extends TestCase {

	/** @return array<int,array<string,mixed>> */
	private function tiers(): array {
		return array(
			array(
				'name' => 'Adult', 'eyebrow' => '', 'features' => array(), 'recommended' => false,
				'price' => '£28', 'period' => '/mo', 'cta_label' => 'Join', 'cta_href' => '/join-monthly',
				'monthly' => array( 'price' => '£28', 'period' => '/mo', 'cta_label' => 'Join', 'cta_href' => '/join-monthly', 'available' => true ),
				'annual'  => array( 'price' => '£280', 'period' => '/yr', 'cta_label' => 'Join', 'cta_href' => '/join-annual', 'available' => true ),
				'saving'  => 'Save £56 a year',
			),
			array(
				'name' => 'Junior', 'eyebrow' => '', 'features' => array(), 'recommended' => false,
				'price' => '£12', 'period' => '/mo', 'cta_label' => 'Join', 'cta_href' => '/join-junior',
				'monthly' => array( 'price' => '£12', 'period' => '/mo', 'cta_label' => 'Join', 'cta_href' => '/join-junior', 'available' => true ),
				'annual'  => array( 'price' => '£12', 'period' => '/mo', 'cta_label' => 'Join', 'cta_href' => '/join-junior', 'available' => false ),
				'saving'  => '',
			),
		);
	}

	public function test_both_cadences_are_in_the_markup(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers() );
		$this->assertStringContainsString( '£28', $html );
		$this->assertStringContainsString( '£280', $html );
		$this->assertStringContainsString( '/join-annual', $html );
	}

	public function test_monthly_is_what_a_visitor_sees_first(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers() );
		$this->assertMatchesRegularExpression( '/aria-pressed="true"[^>]*>Monthly</', $html );
		$this->assertMatchesRegularExpression( '/aria-pressed="false"[^>]*>Annual</', $html );
	}

	public function test_the_annual_side_starts_hidden_and_the_monthly_side_does_not(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers() );
		$this->assertStringContainsString( 'ch-tier__price--annual ch-is-off', $html );
		$this->assertStringNotContainsString( 'ch-tier__price--monthly ch-is-off', $html );
	}

	public function test_a_tier_without_an_annual_price_is_labelled_not_hidden(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers() );
		$this->assertStringContainsString( 'Monthly only', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-tier__name' ), 'no card may disappear' );
	}

	public function test_the_saving_shows_on_the_annual_side_only(): void {
		$html   = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers() );
		$annual = (string) strstr( $html, 'ch-tier__price--annual' );
		$this->assertStringContainsString( 'Save £56 a year', $annual );
		$this->assertStringNotContainsString( 'Save £56 a year', str_replace( $annual, '', $html ) );
	}

	public function test_each_cadence_keeps_its_own_button(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers() );
		$this->assertStringContainsString( 'ch-tier__cta--monthly', $html );
		$this->assertStringContainsString( 'ch-tier__cta--annual', $html );
		$this->assertStringContainsString( 'href="/join-annual"', $html );
	}

	public function test_the_switcher_can_be_left_off(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers(), 3, false );
		$this->assertStringNotContainsString( 'ch-cadence__btn', $html );
		$this->assertStringContainsString( '£28', $html );
	}

	public function test_a_grid_with_nothing_to_switch_to_offers_no_switcher(): void {
		// Every tier monthly-only: a switch that changes nothing is a control
		// that lies about what the page can do.
		$tiers = $this->tiers();
		unset( $tiers[0] );
		$html = Blueworx_Clubhouse_Sections::tier_grid( array_values( $tiers ) );
		$this->assertStringNotContainsString( 'ch-cadence__btn', $html );
		$this->assertStringContainsString( '£12', $html );
	}

	public function test_a_grid_of_old_shape_tiers_still_renders(): void {
		// Defensive: a caller that has not been updated must not fatal.
		$html = Blueworx_Clubhouse_Sections::tier_grid( array( array(
			'name' => 'Adult', 'price' => '£28', 'period' => '/mo', 'features' => array(),
			'cta_label' => 'Join', 'cta_href' => '/join',
		) ) );
		$this->assertStringContainsString( '£28', $html );
		$this->assertStringContainsString( 'href="/join"', $html );
	}
}
