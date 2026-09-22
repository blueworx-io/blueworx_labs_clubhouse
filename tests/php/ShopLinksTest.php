<?php

use PHPUnit\Framework\TestCase;

/**
 * Linking to the shop's own pages.
 *
 * Issue #131: a club's products were in the sitemap and findable from a search
 * engine while nothing on the site linked to them, so a member browsing the
 * club could never reach the shop. Issue #170 has the same shape for the
 * customer dashboard.
 *
 * The addresses come from the BlueWorx Labs plugin now, through Labs_Store,
 * and the test bootstrap never loads Labs — so only the "no shop" half can be
 * asserted here. That a reachable page becomes a target, and a trashed one
 * does not, is covered on the harness, where Labs answers for real.
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

	public function test_without_labs_no_shop_page_is_offered_even_with_a_shop(): void {
		// Labs is what knows where the shop's pages are. Without it the answer
		// is "no address", and a link to nowhere is never offered.
		Blueworx_Clubhouse_SureCart_Products::set_active_for_tests( true );
		$this->assertSame( '', Blueworx_Clubhouse_Labs_Store::page_url( 'shop' ) );
		$this->assertNotContains( 'shop:shop', $this->target_tags() );
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( 'shop:shop', $this->collections() ) );
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
}
