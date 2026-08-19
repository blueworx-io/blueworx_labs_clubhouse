<?php

use PHPUnit\Framework\TestCase;

final class DashboardShellTest extends TestCase {

	/** @return array<int,array<string,mixed>> */
	private function views(): array {
		return Blueworx_Clubhouse_Dashboard_Views::available( true, true );
	}

	private function page( string $current = 'dashboard', string $body = '<p>hello</p>' ): string {
		return Blueworx_Clubhouse_Dashboard_Shell::page(
			$this->views(),
			$current,
			'Your account',
			'Everything the club keeps for you.',
			$body,
			'https://club.test/',
			'Crewe Vagrants'
		);
	}

	public function test_the_page_opts_in_to_the_member_area_look(): void {
		// Every rule in the vendored stylesheet is scoped to .bw-admin; without
		// this class the page renders as bare theme output.
		$this->assertStringContainsString( 'bw-admin', $this->page() );
	}

	public function test_every_available_view_is_a_link_in_the_nav(): void {
		$html = $this->page();
		foreach ( $this->views() as $view ) {
			$this->assertStringContainsString( '?view=' . $view['key'], $html, $view['key'] . ' is not reachable' );
			$this->assertStringContainsString( '>' . $view['label'] . '<', $html );
		}
	}

	public function test_the_nav_is_links_not_buttons(): void {
		// Every view is its own address, so each has to be linkable, openable in
		// a new tab and reachable without JavaScript.
		$this->assertMatchesRegularExpression( '/<a[^>]*class="bw-secnav__item[^"]*"[^>]*href="\?view=orders"/', $this->page() );
	}

	public function test_the_view_being_read_is_the_one_marked_current(): void {
		$html = $this->page( 'invoices' );
		$this->assertMatchesRegularExpression( '/href="\?view=invoices"[^>]*aria-current="page"/', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ), 'exactly one nav item is current' );
		$this->assertSame( 1, substr_count( $html, 'is-active' ) );
	}

	public function test_a_club_without_a_shop_has_no_dead_nav_items(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page(
			Blueworx_Clubhouse_Dashboard_Views::available( false, false ),
			'dashboard',
			'Your account',
			'',
			'<p>hello</p>',
			'https://club.test/',
			'Crewe Vagrants'
		);
		$this->assertStringNotContainsString( '?view=orders', $html );
		$this->assertStringNotContainsString( '?view=bookings', $html );
		$this->assertStringContainsString( '?view=dashboard', $html );
	}

	public function test_the_body_is_placed_as_given(): void {
		$this->assertStringContainsString( '<p>hello</p>', $this->page() );
	}

	public function test_there_is_a_way_back_to_the_club(): void {
		// A member area with no exit is a trap; the theme around this page has
		// no header of its own.
		$this->assertStringContainsString( 'href="https://club.test/"', $this->page() );
	}

	public function test_the_club_name_is_shown_and_escaped(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page(
			$this->views(),
			'dashboard',
			'T',
			'L',
			'B',
			'https://club.test/',
			'Bill & Ben\'s <script>'
		);
		$this->assertStringContainsString( 'Bill &amp; Ben&#039;s &lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_the_title_and_lede_are_escaped(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page(
			$this->views(),
			'dashboard',
			'<b>T</b>',
			'<i>L</i>',
			'B',
			'https://club.test/',
			'Club'
		);
		$this->assertStringContainsString( '&lt;b&gt;T&lt;/b&gt;', $html );
		$this->assertStringContainsString( '&lt;i&gt;L&lt;/i&gt;', $html );
	}

	public function test_the_way_home_is_escaped(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page(
			$this->views(), 'dashboard', 'T', 'L', 'B', '" onmouseover="alert(1)', 'Club'
		);
		// The quotes are escaped, so the address cannot break out of its
		// attribute and become a handler. The literal text survives inside the
		// value, harmlessly, which is why asserting on it would fail here.
		$this->assertStringNotContainsString( '" onmouseover="', $html );
		$this->assertStringContainsString( '&quot; onmouseover=&quot;alert(1)', $html );
	}

	public function test_the_lede_is_left_out_when_there_is_none(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->views(), 'dashboard', 'T', '', 'B', '/', 'Club' );
		$this->assertStringNotContainsString( 'bw-pagehead__lede', $html );
	}

	public function test_the_bare_shell_has_the_look_but_no_nav(): void {
		// Checkout: a member mid-purchase should not be offered six places to
		// wander off to.
		$html = Blueworx_Clubhouse_Dashboard_Shell::bare( 'Checkout', 'Nearly there.', '<form></form>', 'https://club.test/', 'Club' );
		$this->assertStringContainsString( 'bw-admin', $html );
		$this->assertStringContainsString( '<form></form>', $html );
		$this->assertStringNotContainsString( 'bw-secnav', $html );
		$this->assertStringNotContainsString( '?view=', $html );
	}

	public function test_a_card_carries_its_title_and_its_body(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::card( 'Orders', '<table></table>' );
		$this->assertStringContainsString( 'bw-card', $html );
		$this->assertStringContainsString( 'Orders', $html );
		$this->assertStringContainsString( '<table></table>', $html );
	}

	public function test_a_card_with_no_title_has_no_head(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::card( '', '<table></table>' );
		$this->assertStringNotContainsString( 'bw-card__head', $html );
		$this->assertStringContainsString( '<table></table>', $html );
	}

	public function test_an_empty_state_says_what_is_missing_and_offers_the_way_out(): void {
		// Never a blank frame: a member who reaches a view the club has not set
		// up is told plainly, and given the way back.
		$html = Blueworx_Clubhouse_Dashboard_Shell::empty_state(
			'Nothing here yet',
			'The club has not set this part up.',
			'https://club.test/',
			'Back to the club'
		);
		$this->assertStringContainsString( 'Nothing here yet', $html );
		$this->assertStringContainsString( 'The club has not set this part up.', $html );
		$this->assertStringContainsString( 'href="https://club.test/"', $html );
		$this->assertStringContainsString( 'Back to the club', $html );
	}

	public function test_every_view_has_a_glyph_and_an_unknown_name_draws_none(): void {
		foreach ( Blueworx_Clubhouse_Dashboard_Views::all() as $view ) {
			$this->assertStringContainsString(
				'<svg',
				Blueworx_Clubhouse_Dashboard_Shell::icon( (string) $view['icon'] ),
				$view['icon'] . ' has no glyph'
			);
		}
		$this->assertSame( '', Blueworx_Clubhouse_Dashboard_Shell::icon( 'no-such-icon' ) );
	}

	public function test_the_shell_emits_no_club_look_classes(): void {
		// The two design systems never meet. A ch-* class here would arrive
		// unstyled, because none of assets/looks/ is loaded on this page.
		$this->assertDoesNotMatchRegularExpression( '/class="[^"]*\bch-/', $this->page() );
	}
}
