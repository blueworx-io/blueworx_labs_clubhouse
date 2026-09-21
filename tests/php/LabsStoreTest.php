<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LabsStoreTest extends TestCase {

	public function test_bookings_view_is_added_only_when_latepoint_is_present(): void {
		Blueworx_Clubhouse_Integrations::set_detector( static fn ( string $tag ): bool => 'latepoint_customer_dashboard' === $tag );
		$views = Blueworx_Clubhouse_Labs_Store::views( array( array( 'key' => 'dashboard' ) ) );
		$this->assertSame( array( 'dashboard', 'bookings' ), array_column( $views, 'key' ) );
		$this->assertSame( 'latepoint_customer_dashboard', $views[1]['shortcode'] );
		$this->assertSame( 'both', $views[1]['where'] );

		Blueworx_Clubhouse_Integrations::set_detector( static fn ( string $tag ): bool => false );
		$this->assertSame( array( 'dashboard' ), array_column( Blueworx_Clubhouse_Labs_Store::views( array( array( 'key' => 'dashboard' ) ) ), 'key' ) );
	}

	public function test_bookings_sits_after_dashboard_and_before_the_shop_views(): void {
		Blueworx_Clubhouse_Integrations::set_detector( static fn ( string $tag ): bool => true );
		$views = Blueworx_Clubhouse_Labs_Store::views( array( array( 'key' => 'dashboard' ), array( 'key' => 'orders' ) ) );
		$this->assertSame( array( 'dashboard', 'bookings', 'orders' ), array_column( $views, 'key' ) );
	}

	public function test_the_notice_names_what_is_missing(): void {
		$this->assertStringContainsString( 'BlueWorx Labs', Blueworx_Clubhouse_Labs_Store::notice_html( false, '' ) );
		$this->assertStringContainsString( 'is not active', Blueworx_Clubhouse_Labs_Store::notice_html( false, '' ) );
		$this->assertStringContainsString( '1.86.0', Blueworx_Clubhouse_Labs_Store::notice_html( true, '1.86.0' ) );
		$this->assertStringContainsString( '1.87.0', Blueworx_Clubhouse_Labs_Store::notice_html( true, '1.86.0' ) );
		$this->assertStringContainsString( 'Store pages feature is switched off', Blueworx_Clubhouse_Labs_Store::notice_html( true, '1.87.0', false ) );
	}

	public function test_dashboard_url_claim_is_the_member_area_when_it_is_served(): void {
		// Pure half: the claim is the address handed in when the member area is
		// served, and whatever Labs said otherwise.
		$this->assertSame( 'http://club.test/member-dashboard/', Blueworx_Clubhouse_Labs_Store::claim( true, 'http://club.test/member-dashboard/', '' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Labs_Store::claim( false, 'http://club.test/member-dashboard/', '' ) );
		$this->assertSame( 'http://other/', Blueworx_Clubhouse_Labs_Store::claim( false, 'http://club.test/member-dashboard/', 'http://other/' ) );
	}

	public function test_context_prefers_the_club_favicon_then_logo(): void {
		$this->assertSame( 'http://x/fav.png', Blueworx_Clubhouse_Labs_Store::crest( 'http://x/fav.png', 'http://x/logo.png' ) );
		$this->assertSame( 'http://x/logo.png', Blueworx_Clubhouse_Labs_Store::crest( '', 'http://x/logo.png' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Labs_Store::crest( '', '' ) );
	}

	public function test_the_checkout_footer_offers_only_pages_the_club_has_switched_on(): void {
		// A dead link on a payment page is the worst place for one. Terms and
		// privacy are switchable like every other club page, so the footer has
		// to ask rather than assume. Labs' own list is replaced, not added to:
		// its "Privacy policy" is the club's privacy notice under another name.
		wp_stub_reset();
		$visibility = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Options_Storage() );
		foreach ( array( 'terms', 'rules', 'contact' ) as $slug ) {
			$visibility->set_page_visible( $slug, false );
		}

		$links = Blueworx_Clubhouse_Labs_Store::checkout_links(
			array( array( 'label' => 'Privacy policy', 'href' => 'https://club.test/?page_id=3' ) )
		);

		$this->assertSame(
			array( array( 'label' => 'Privacy notice', 'href' => 'https://club.test/privacy/' ) ),
			$links
		);
		wp_stub_reset();
	}

	public function test_the_checkout_footer_carries_all_four_club_pages_in_order(): void {
		wp_stub_reset();
		$labels = array_column( Blueworx_Clubhouse_Labs_Store::checkout_links( array() ), 'label' );
		$this->assertSame( array( 'Terms and conditions', 'Privacy notice', 'Club rules', 'Contact the club' ), $labels );
	}

	public function test_a_club_with_nothing_switched_on_gets_no_checkout_links(): void {
		wp_stub_reset();
		$visibility = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Options_Storage() );
		foreach ( array( 'terms', 'privacy', 'rules', 'contact' ) as $slug ) {
			$visibility->set_page_visible( $slug, false );
		}
		$this->assertSame( array(), Blueworx_Clubhouse_Labs_Store::checkout_links( array() ) );
		wp_stub_reset();
	}

	public function test_without_labs_the_seam_answers_nothing(): void {
		// The test bootstrap never loads Labs, so this IS the "Labs missing" site.
		$this->assertFalse( Blueworx_Clubhouse_Labs_Store::available() );
		$this->assertSame( 0, Blueworx_Clubhouse_Labs_Store::page_id( 'dashboard' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Labs_Store::page_url( 'checkout' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Labs_Store::page_key( 5 ) );
		$this->assertStringContainsString( 'not available', Blueworx_Clubhouse_Labs_Store::screen( 'http://x/', 'http://x/' ) );
	}
}
