<?php

use PHPUnit\Framework\TestCase;

final class PluginSlotTest extends TestCase {

	protected function tearDown(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources( null, null );
	}

	public function test_with_nothing_installed_a_slot_renders_nothing(): void {
		// The default, and the safe way round: an environment that has not opted
		// in shows no third-party panels at all rather than broken ones.
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-orders' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Slot::shortcode( 'latepoint_customer_dashboard' ) );
	}

	public function test_a_block_the_shop_provides_is_rendered(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static fn ( string $name ): ?string => 'surecart/customer-orders' === $name ? '<div>orders</div>' : null,
			null
		);
		$this->assertSame( '<div>orders</div>', Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-orders' ) );
	}

	public function test_a_block_the_shop_does_not_provide_renders_nothing(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static fn ( string $name ): ?string => 'surecart/customer-orders' === $name ? '<div>orders</div>' : null,
			null
		);
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-licenses' ) );
	}

	public function test_a_shortcode_that_is_registered_is_rendered(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			null,
			static fn ( string $tag ): ?string => 'latepoint_customer_dashboard' === $tag ? '<div>bookings</div>' : null
		);
		$this->assertSame( '<div>bookings</div>', Blueworx_Clubhouse_Plugin_Slot::shortcode( 'latepoint_customer_dashboard' ) );
	}

	public function test_a_plugin_that_renders_only_whitespace_counts_as_nothing(): void {
		// A registered block that returns an empty string is a plugin with
		// nothing to show. The caller must be able to tell that from real
		// output, or it draws an empty card.
		Blueworx_Clubhouse_Plugin_Slot::set_sources( static fn ( string $n ): ?string => "  \n ", null );
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-orders' ) );
	}

	public function test_a_plugin_that_throws_does_not_take_the_page_down(): void {
		// Another plugin's panel failing must cost that panel, not the member's
		// whole account page.
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static function ( string $n ): ?string {
				throw new RuntimeException( 'boom' );
			},
			null
		);
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-orders' ) );
	}
}
