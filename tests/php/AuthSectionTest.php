<?php

use PHPUnit\Framework\TestCase;

/**
 * The member sign-in card.
 *
 * The form inside it is the shop's, so what is tested here is the part that
 * stays ours: the club's own wording around it, the way through to joining,
 * and that the shop's form is actually on the page. What the form does once a
 * member types into it is the shop's to get right, and is covered end to end
 * by the Playwright suite against a real WordPress with a real SureCart.
 */
final class AuthSectionTest extends TestCase {

	/** @param array<string,mixed> $over */
	private function card( array $over = array() ): string {
		return Blueworx_Clubhouse_Sections::auth( array_merge(
			array(
				'eyebrow'     => 'Members',
				'heading'     => 'Log in to your account',
				'lede'        => 'Access your membership, bookings and club events.',
				'join_prompt' => 'Not a member yet?',
				'join_label'  => 'Join the club',
				'join_href'   => '/membership/',
			),
			$over
		) );
	}

	public function test_the_shops_sign_in_form_is_what_a_member_gets(): void {
		$this->assertStringContainsString( '<sc-login-form>', $this->card() );
	}

	public function test_the_clubs_own_wording_is_what_is_read(): void {
		$html = $this->card( array( 'heading' => 'Members of Crewe Vagrants' ) );
		$this->assertStringContainsString( '<h1 class="ch-auth__title">Members of Crewe Vagrants</h1>', $html );
		$this->assertStringContainsString( 'Access your membership', $html );
	}

	public function test_the_shops_form_is_titled_by_the_club_not_by_the_shop(): void {
		// Left alone it says "Sign in to your account" — a different product's
		// wording halfway down the club's own page.
		$html = $this->card( array( 'heading' => 'Members of Crewe Vagrants' ) );
		$this->assertStringContainsString( '<span slot="title">Members of Crewe Vagrants</span>', $html );
	}

	public function test_somebody_who_is_not_a_member_yet_is_shown_the_way(): void {
		$html = $this->card();
		$this->assertStringContainsString( 'Not a member yet?', $html );
		$this->assertStringContainsString( 'href="/membership/"', $html );
		$this->assertStringContainsString( 'Join the club', $html );
	}

	public function test_a_clubs_wording_cannot_become_markup(): void {
		$html = $this->card( array( 'heading' => '<script>alert(1)</script>' ) );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_the_card_carries_the_pages_only_heading(): void {
		// The login page has no hero, so this is the page's h1 — losing it would
		// leave the page with no heading at all.
		$this->assertSame( 1, substr_count( $this->card(), '<h1' ) );
	}
}
