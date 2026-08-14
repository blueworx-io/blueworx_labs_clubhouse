<?php

use PHPUnit\Framework\TestCase;

/**
 * Linking to the shop's own pages.
 *
 * Issue #131: a club's products were in the sitemap and findable from a search
 * engine while nothing on the site linked to them, so a member browsing the
 * club could never reach the shop. Issue #170 has the same shape for the
 * customer dashboard.
 */
final class ShopLinksTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Link_Catalogue::forget_shop_targets();
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( null );
		Blueworx_Clubhouse_Link_Catalogue::forget_shop_targets();
		wp_stub_reset();
	}

	private function shop_page( string $key, int $id, string $url ): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		update_option( Blueworx_Clubhouse_Shop_Pages::option_name( $key ), $id );
		$GLOBALS['wp_stub_post_status'][ $id ] = 'publish';
		$GLOBALS['wp_stub_permalinks'][ $id ]  = $url;
		Blueworx_Clubhouse_Link_Catalogue::forget_shop_targets();
	}

	private function collections(): Blueworx_Clubhouse_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	/** @return array<int,string> */
	private function target_tags(): array {
		return array_map(
			static fn ( array $t ): string => $t['target'],
			Blueworx_Clubhouse_Link_Catalogue::targets( $this->collections() )
		);
	}

	public function test_a_club_with_no_shop_is_offered_no_shop_links(): void {
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( false );
		$this->assertNotContains( 'shop:shop', $this->target_tags() );
		$this->assertNotContains( 'shop:dashboard', $this->target_tags() );
	}

	public function test_a_reachable_shop_page_becomes_something_a_link_can_point_at(): void {
		$this->shop_page( 'shop', 12, 'https://club.test/shop/' );
		$this->assertContains( 'shop:shop', $this->target_tags() );
		$this->assertSame(
			'https://club.test/shop/',
			Blueworx_Clubhouse_Link_Catalogue::resolve( 'shop:shop', $this->collections() )
		);
	}

	public function test_the_customer_dashboard_is_offered_too(): void {
		$this->shop_page( 'dashboard', 13, 'https://club.test/customer-dashboard/' );
		$this->assertContains( 'shop:dashboard', $this->target_tags() );
	}

	public function test_a_trashed_shop_page_is_not_offered(): void {
		$this->shop_page( 'shop', 12, 'https://club.test/shop/' );
		$GLOBALS['wp_stub_post_status'][12] = 'trash';
		Blueworx_Clubhouse_Link_Catalogue::forget_shop_targets();
		$this->assertNotContains( 'shop:shop', $this->target_tags() );
	}

	public function test_the_default_nav_shows_shop_only_when_there_is_one(): void {
		// The item ships in the defaults, and disappears on every site that
		// cannot serve it — the same way Bookings does without LatePoint.
		$this->assertContains(
			'shop:shop',
			array_map( static fn ( array $r ): string => $r['target'], Blueworx_Clubhouse_Menu::DEFAULTS )
		);

		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( false );
		$menu   = new Blueworx_Clubhouse_Menu( new Blueworx_Clubhouse_Fake_Storage() );
		$labels = array_map(
			static fn ( array $i ): string => $i['label'],
			$menu->items( $this->collections(), new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ) )
		);
		$this->assertNotContains( 'Shop', $labels );
	}

	public function test_a_club_with_a_shop_gets_the_link_in_its_nav(): void {
		$this->shop_page( 'shop', 12, 'https://club.test/shop/' );
		$menu  = new Blueworx_Clubhouse_Menu( new Blueworx_Clubhouse_Fake_Storage() );
		$items = $menu->items( $this->collections(), new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ) );

		$shop = array_values( array_filter( $items, static fn ( array $i ): bool => 'Shop' === $i['label'] ) );
		$this->assertCount( 1, $shop );
		$this->assertSame( 'https://club.test/shop/', $shop[0]['href'] );
	}
}
