<?php
use PHPUnit\Framework\TestCase;

final class CtaLabelsTest extends TestCase {
	public function test_join_label_is_the_single_canonical_string(): void {
		$this->assertSame( 'Join the club', Blueworx_Clubhouse_Cta::JOIN );
	}

	/**
	 * The membership-join sprawl the UX review flagged must be gone from every
	 * rendered page. These strings were the variants; their absence proves the
	 * canonicalisation held. (The positive check — that JOIN still appears — is in
	 * the Task 4 link-hygiene guardrail, which renders every page.)
	 */
	public function test_retired_join_variants_do_not_appear_in_home_or_membership(): void {
		$branding    = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$visibility  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$collections = new Blueworx_Clubhouse_Demo_Collections();
		foreach ( array( '', 'membership' ) as $slug ) {
			$html = Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility, $collections );
			$this->assertStringNotContainsString( 'Explore membership', $html, "slug '$slug'" );
			$this->assertStringNotContainsString( 'Choose your tier', $html, "slug '$slug'" );
			$this->assertStringNotContainsString( 'Join the Club', $html, "capitalised variant, slug '$slug'" );
		}
	}
}
