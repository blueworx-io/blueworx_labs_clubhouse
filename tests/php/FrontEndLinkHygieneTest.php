<?php
use PHPUnit\Framework\TestCase;

/**
 * Enforces the outcomes of the cross-cutting consistency slice on every rendered
 * page, so none can silently return: no dead links, no retired social treatment,
 * and the membership-join label kept canonical.
 */
final class FrontEndLinkHygieneTest extends TestCase {

	/** @return array<int,string> every front-end slug ('' = home) */
	private function slugs(): array {
		return array_map(
			static fn( $p ) => $p['slug'],
			Blueworx_Clubhouse_Page_Map::pages()
		);
	}

	private function render( string $slug ): string {
		return Blueworx_Clubhouse_Page_Map::render(
			$slug,
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections()
		);
	}

	public function test_no_dead_links_on_any_page(): void {
		foreach ( $this->slugs() as $slug ) {
			$this->assertStringNotContainsString( 'href="#"', $this->render( $slug ), "dead link on '$slug'" );
		}
	}

	public function test_no_retired_social_treatment_on_any_page(): void {
		foreach ( $this->slugs() as $slug ) {
			$html = $this->render( $slug );
			// Quote-anchored: the exact old singular classes, so this doesn't
			// false-match the still-present plural container class
			// "ch-footer__socials" (see SectionsTest::test_footer_uses_social_pills_not_letter_circles).
			$this->assertStringNotContainsString( 'class="ch-footer__social"', $html, "letter-circle on '$slug'" );
			$this->assertStringNotContainsString( 'class="ch-contact__social"', $html, "letter-circle on '$slug'" );
		}
	}

	public function test_membership_join_label_is_present_and_canonical(): void {
		// Positive: the canonical label survives somewhere (home has it).
		$this->assertStringContainsString( Blueworx_Clubhouse_Cta::JOIN, $this->render( '' ) );
		// Negative: no retired variant anywhere.
		foreach ( $this->slugs() as $slug ) {
			$html = $this->render( $slug );
			$this->assertStringNotContainsString( 'Explore membership', $html, "sprawl on '$slug'" );
			$this->assertStringNotContainsString( 'Choose your tier', $html, "sprawl on '$slug'" );
		}
	}
}
