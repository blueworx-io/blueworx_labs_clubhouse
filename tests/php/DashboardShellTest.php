<?php

use PHPUnit\Framework\TestCase;

final class DashboardShellTest extends TestCase {

	/** @return array<int,array<string,mixed>> */
	private function views(): array {
		return Blueworx_Clubhouse_Dashboard_Views::available( true, true );
	}

	/** @return array<string,mixed> */
	private function args( array $over = array() ): array {
		return array_merge(
			array(
				'views'   => array(
					array( 'key' => 'dashboard', 'label' => 'Dashboard', 'title' => 'Your account', 'lede' => 'All of it.', 'icon' => 'layout-dashboard' ),
					array( 'key' => 'orders', 'label' => 'Orders', 'title' => 'Orders', 'lede' => 'What you bought.', 'icon' => 'shopping-cart' ),
				),
				'current' => 'dashboard',
				'panels'  => array( 'dashboard' => '<p>overview</p>', 'orders' => '<p>orders</p>' ),
			),
			$over
		);
	}

	public function test_the_sidebar_carries_the_club_and_the_member(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array(
			'club_name'     => 'Crewe Vagrants',
			'member_name'   => 'Luke McFarland',
			'member_email'  => 'luke@example.com',
		) ) );
		$this->assertStringContainsString( 'clubhouse-member__side', $html );
		$this->assertStringContainsString( 'Crewe Vagrants', $html );
		$this->assertStringContainsString( 'bw-person__name', $html );
		$this->assertStringContainsString( 'Luke McFarland', $html );
		$this->assertStringContainsString( 'luke@example.com', $html );
		// Initials, drawn where the design puts an avatar.
		$this->assertStringContainsString( '>LM<', $html );
	}

	public function test_the_top_bar_shows_the_current_view_title(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array( 'current' => 'orders' ) ) );
		// data-member-title is what the switching script re-targets when a nav
		// link is followed without a full page load — see head()'s docblock.
		$this->assertStringContainsString( '<h1 class="bw-pagehead__h1" data-member-title>Orders</h1>', $html );
		$this->assertStringContainsString( 'What you bought.', $html );
	}

	public function test_the_top_bar_sits_inside_the_content_column_beside_the_sidebar(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args() );
		// The design puts the sidebar full height on the left, with the page head
		// to its right — so the head must open AFTER the sidebar has closed.
		$this->assertLessThan(
			strpos( $html, 'bw-pagehead' ),
			strpos( $html, 'clubhouse-member__side' ),
			'the sidebar must come before the top bar in source order'
		);
	}

	public function test_the_nav_marks_the_view_being_read(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array( 'current' => 'orders' ) ) );
		// Tied to the active view's own href, not just present anywhere on the page.
		$this->assertMatchesRegularExpression( '/href="\?view=orders"[^>]*aria-current="page"/', $html );
		// The side nav and the phone's bottom bar each mark the current view, and
		// both are always in the markup — so the count is 2, not 1.
		$this->assertSame( 2, substr_count( $html, 'aria-current="page"' ), 'the side nav and the tab bar both mark the current view' );
		$this->assertSame( 2, substr_count( $html, 'is-active' ), 'the side nav and the tab bar both mark the current view' );
	}

	public function test_the_way_back_and_the_way_out_are_both_offered(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array(
			'home_url'   => '/',
			'logout_url' => '/out/',
		) ) );
		$this->assertStringContainsString( '<a class="clubhouse-member__back" href="/">', $html );
		$this->assertStringContainsString( 'Back to the club site', $html );
		$this->assertStringContainsString( '<a class="bw-btn bw-btn--secondary bw-btn--sm" href="/out/">Sign out</a>', $html );
	}

	public function test_the_sidebar_escapes_the_club_and_the_member(): void {
		// Ported from the old page()-signature test of the same intent, plus the
		// member name and email — the sidebar is new code that prints both.
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array(
			'club_name'    => 'Bill & Ben\'s <script>',
			'member_name'  => 'Bill & Ben\'s <script>',
			'member_email' => 'bill&ben@<script>.test',
		) ) );
		$this->assertStringContainsString( 'Bill &amp; Ben&#039;s &lt;script&gt;', $html );
		$this->assertStringContainsString( 'bill&amp;ben@&lt;script&gt;.test', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_the_top_bar_escapes_the_title_and_lede(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array(
			'views'   => array(
				array( 'key' => 'dashboard', 'label' => 'Dashboard', 'title' => '<b>T</b>', 'lede' => '<i>L</i>', 'icon' => 'layout-dashboard' ),
			),
			'current' => 'dashboard',
			'panels'  => array( 'dashboard' => '<p>hello</p>' ),
		) ) );
		$this->assertStringContainsString( '&lt;b&gt;T&lt;/b&gt;', $html );
		$this->assertStringContainsString( '&lt;i&gt;L&lt;/i&gt;', $html );
		$this->assertStringNotContainsString( '<b>T</b>', $html );
	}

	public function test_the_way_home_is_escaped(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array(
			'home_url' => '" onmouseover="alert(1)',
		) ) );
		// The quotes are escaped, so the address cannot break out of its
		// attribute and become a handler. The literal text survives inside the
		// value, harmlessly, which is why asserting on it would fail here.
		$this->assertStringNotContainsString( '" onmouseover="', $html );
		$this->assertStringContainsString( '&quot; onmouseover=&quot;alert(1)', $html );
	}

	public function test_an_already_escaped_sign_out_address_is_not_escaped_twice(): void {
		// WordPress's own nonce helper hands back a URL with its ampersands
		// already written as entities. Escaping that again renames _wpnonce to
		// amp;_wpnonce and the sign-out silently stops working.
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array(
			'logout_url' => 'https://club.test/?clubhouse_logout=1&amp;_wpnonce=abc',
		) ) );
		$this->assertStringContainsString( 'href="https://club.test/?clubhouse_logout=1&amp;_wpnonce=abc"', $html );
		$this->assertStringNotContainsString( 'amp;amp;', $html );
	}

	public function test_a_panels_body_is_rendered_and_a_hidden_views_body_still_carries_it(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args() ); // current = dashboard
		$this->assertStringContainsString(
			'<div class="clubhouse-member__panel" data-view="dashboard" role="tabpanel" aria-labelledby="clubhouse-member-tab-dashboard"><p>overview</p></div>',
			$html
		);
		// Not the current view — hidden, but its body is still on the page,
		// because the panels are other plugins' web components that come alive
		// on page load and a panel fetched later would render as an empty box.
		$this->assertStringContainsString(
			'<div class="clubhouse-member__panel" data-view="orders" role="tabpanel" aria-labelledby="clubhouse-member-tab-orders" hidden><p>orders</p></div>',
			$html
		);
	}

	public function test_a_club_without_a_shop_has_no_dead_nav_items(): void {
		// Only two views on offer — orders is omitted, as available() does for a
		// club with no shop plugin active.
		$views = array(
			array( 'key' => 'dashboard', 'label' => 'Dashboard', 'title' => 'Your account', 'lede' => '', 'icon' => 'layout-dashboard' ),
			array( 'key' => 'invoices', 'label' => 'Invoices', 'title' => 'Invoices', 'lede' => '', 'icon' => 'file-spreadsheet' ),
		);
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array(
			'views'   => $views,
			'current' => 'dashboard',
			'panels'  => array( 'dashboard' => '<p>hello</p>', 'invoices' => '<p>invoices</p>' ),
		) ) );
		// Neither the side nav nor the phone's tab bar link to it — both use
		// data-view-link.
		$this->assertStringNotContainsString( 'data-view-link="orders"', $html );
		$this->assertStringNotContainsString( '?view=orders', $html );
		$this->assertStringContainsString( 'data-view-link="dashboard"', $html );
	}

	public function test_the_shell_emits_no_club_look_classes(): void {
		// The two design systems never meet. A ch-* class here would arrive
		// unstyled, because none of assets/looks/ is loaded on this page.
		$this->assertDoesNotMatchRegularExpression( '/class="[^"]*\bch-/', Blueworx_Clubhouse_Dashboard_Shell::page( $this->args() ) );
	}

	public function test_no_sign_out_link_is_drawn_without_an_address_for_it(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args() );
		$this->assertStringNotContainsString( 'Sign out', $html );
	}

	public function test_initials_cope_with_one_name_and_with_none(): void {
		$this->assertSame( 'L', Blueworx_Clubhouse_Dashboard_Shell::initials( 'Luke' ) );
		$this->assertSame( 'LM', Blueworx_Clubhouse_Dashboard_Shell::initials( '  luke   mcfarland ' ) );
		$this->assertSame( 'LB', Blueworx_Clubhouse_Dashboard_Shell::initials( 'Luke James Bell' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Dashboard_Shell::initials( '   ' ) );
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

	public function test_a_view_link_is_added_to_the_pages_own_address(): void {
		// A club with permalinks set to Plain has its dashboard at ?page_id=42.
		// A bare '?view=orders' replaces that query outright and lands the
		// member on the front page.
		$this->assertSame(
			'https://club.test/?page_id=42&view=orders',
			Blueworx_Clubhouse_Dashboard_Shell::view_url( 'orders', 'https://club.test/?page_id=42' )
		);
	}

	public function test_a_view_link_on_a_pretty_address_starts_the_query(): void {
		$this->assertSame(
			'https://club.test/account/?view=orders',
			Blueworx_Clubhouse_Dashboard_Shell::view_url( 'orders', 'https://club.test/account/' )
		);
	}

	public function test_a_view_link_with_no_address_to_build_on_still_works(): void {
		// Nothing knows the page's address — a relative link to the page being
		// read is right wherever it carries no query of its own.
		$this->assertSame( '?view=orders', Blueworx_Clubhouse_Dashboard_Shell::view_url( 'orders' ) );
		$this->assertSame( '?view=orders', Blueworx_Clubhouse_Dashboard_Shell::view_url( 'orders', '   ' ) );
	}

	public function test_the_checkout_shell_offers_no_sign_out(): void {
		// A guest can buy without an account, and someone mid-purchase has no
		// business being offered the way out of a session.
		$html = Blueworx_Clubhouse_Dashboard_Shell::bare( 'Checkout', '', '<form></form>', 'https://club.test/', 'Club' );
		$this->assertStringNotContainsString( 'Sign out', $html );
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
}
