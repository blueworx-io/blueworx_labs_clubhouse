<?php

use PHPUnit\Framework\TestCase;

final class PluginSlotTest extends TestCase {

	/** @var string */
	private $log_file;

	/** @var string|false */
	private $previous_error_log;

	protected function setUp(): void {
		// error_log() with no explicit destination writes to the SAPI's default
		// (stderr on CLI), which would land in the test runner's own output.
		// Point it at a private file for the duration of each test so a real
		// call to error_log() can be asserted on without polluting test output.
		$this->log_file          = tempnam( sys_get_temp_dir(), 'bwx-plugin-slot-log' );
		$this->previous_error_log = ini_set( 'error_log', $this->log_file );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources( null, null );
		ini_set( 'error_log', $this->previous_error_log );
		@unlink( $this->log_file );
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

	public function test_a_plugin_that_throws_leaves_a_trace_in_the_error_log(): void {
		// Isolating the failure is right and must stay — but swallowed with no
		// trace, a club reporting "my orders page is blank" leaves nobody
		// anything to go on. One line naming the slot and the cause must reach
		// the real PHP error log.
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static function ( string $n ): ?string {
				throw new RuntimeException( 'boom' );
			},
			null
		);

		Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-orders' );

		$logged = file_get_contents( $this->log_file );
		$this->assertStringContainsString( 'surecart/customer-orders', $logged );
		$this->assertStringContainsString( 'boom', $logged );
	}
}
