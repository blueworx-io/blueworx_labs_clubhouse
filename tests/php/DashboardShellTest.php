<?php

use PHPUnit\Framework\TestCase;

/**
 * The checkout page's frame. Pure, so it is asserted directly rather than
 * through a rendered page.
 */
final class DashboardShellTest extends TestCase {

	/** @return array<string,mixed> */
	private function args(): array {
		return array(
			'club_name'  => 'Crewe Vagrants',
			'logo_url'   => '',
			'home_url'   => 'https://club.test/',
			'home_label' => 'Back to Crewe Vagrants',
			'body'       => '<p id="form">FORM</p>',
			'footnote'   => 'Crewe Vagrants Sports Club, registered in England 04128877',
			'links'      => array(
				array( 'label' => 'Terms', 'href' => 'https://club.test/terms/' ),
				array( 'label' => 'Privacy', 'href' => 'https://club.test/privacy/' ),
			),
		);
	}

	public function test_the_shop_content_is_passed_through_untouched(): void {
		// The shop renders the shop. The frame must never rewrite what is
		// inside it, or a SureCart update silently breaks the form.
		$this->assertStringContainsString(
			'<p id="form">FORM</p>',
			Blueworx_Clubhouse_Dashboard_Shell::checkout( $this->args() )
		);
	}

	public function test_the_header_carries_the_club_and_the_footer_the_legals(): void {
		$out = Blueworx_Clubhouse_Dashboard_Shell::checkout( $this->args() );
		$this->assertStringContainsString( 'Crewe Vagrants', $out );
		$this->assertStringContainsString( 'https://club.test/terms/', $out );
		$this->assertStringContainsString( 'registered in England 04128877', $out );
	}

	public function test_there_is_exactly_one_h1(): void {
		// The page heading is the checkout itself. A second one would leave a
		// screen reader with two competing titles on a payment page.
		$this->assertSame(
			1,
			substr_count( Blueworx_Clubhouse_Dashboard_Shell::checkout( $this->args() ), '<h1' )
		);
	}

	public function test_no_nav_is_offered(): void {
		// Someone mid-purchase should not be handed six places to wander off
		// to — the same reasoning as bare().
		$out = Blueworx_Clubhouse_Dashboard_Shell::checkout( $this->args() );
		$this->assertStringNotContainsString( 'bw-secnav', $out );
		$this->assertStringNotContainsString( 'clubhouse-member__tabbar', $out );
	}

	public function test_a_club_with_no_legal_pages_gets_no_empty_nav(): void {
		// A dead link is worse than no link, and an empty <nav> is worse than
		// no nav — it announces a navigation landmark holding nothing.
		$args          = $this->args();
		$args['links'] = array();
		$this->assertStringNotContainsString(
			'clubhouse-checkout__links',
			Blueworx_Clubhouse_Dashboard_Shell::checkout( $args )
		);
	}

	public function test_everything_drawn_is_escaped(): void {
		$args              = $this->args();
		$args['club_name'] = '<script>x</script>';
		$this->assertStringNotContainsString(
			'<script>x</script>',
			Blueworx_Clubhouse_Dashboard_Shell::checkout( $args )
		);
	}

	public function test_the_crest_falls_back_to_initials(): void {
		// Most clubs never upload a square logo. The corner box has to hold
		// something either way.
		$this->assertStringContainsString(
			'CV',
			Blueworx_Clubhouse_Dashboard_Shell::checkout( $this->args() )
		);
	}
}
