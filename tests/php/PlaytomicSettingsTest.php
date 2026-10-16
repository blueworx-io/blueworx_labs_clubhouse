<?php
// tests/php/PlaytomicSettingsTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicSettingsTest extends TestCase {

	private function settings( ?Blueworx_Clubhouse_Fake_Storage $storage = null ): Blueworx_Clubhouse_Playtomic_Settings {
		return new Blueworx_Clubhouse_Playtomic_Settings( $storage ?? new Blueworx_Clubhouse_Fake_Storage(), new Blueworx_Clubhouse_Playtomic_Secret( 'k' ) );
	}

	public function test_defaults_when_nothing_is_stored(): void {
		$s = $this->settings()->read();
		$this->assertSame( '', $s['client_id'] );
		$this->assertSame( 'all', $s['who'] );
		$this->assertSame( array(), $s['venues'] );
	}

	public function test_secret_is_stored_encrypted_and_kept_when_left_blank(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$s       = $this->settings( $storage );
		$s->save( array( 'client_id' => ' abc ', 'secret' => 'hunter2' ) );
		$this->assertStringNotContainsString( 'hunter2', serialize( $storage->get( 'playtomic' ) ) );
		$this->assertSame( 'hunter2', $s->secret() );
		$this->assertTrue( $s->has_secret() );
		$this->assertSame( 'abc', $s->client_id() );

		$s->save( array( 'client_id' => 'abc', 'secret' => '' ) );
		$this->assertSame( 'hunter2', $s->secret(), 'a blank secret field means keep the one we have' );
	}

	public function test_blank_venue_rows_are_dropped(): void {
		$s = $this->settings();
		$s->save( array( 'venues' => array(
			array( 'id' => '', 'name' => '', 'club' => '' ),
			array( 'id' => '  ten-1  ', 'name' => 'North', 'club' => 'PadlX North', 'category' => 'Founding', 'expiry_mode' => 'date', 'expiry_date' => '2027-01-31', 'expiry_months' => '', 'due_mode' => 'expiry' ),
		) ) );
		$venues = $s->venues();
		$this->assertCount( 1, $venues );
		$this->assertSame( 'ten-1', $venues[0]['id'] );
		$this->assertSame( 'date', $venues[0]['expiry_mode'] );
		$this->assertSame( '2027-01-31', $venues[0]['expiry_date'] );
		$this->assertSame( 'expiry', $venues[0]['due_mode'] );
	}

	public function test_bad_modes_and_dates_fall_back(): void {
		$s = $this->settings();
		$s->save( array( 'who' => 'nonsense', 'venues' => array( array( 'id' => 'v', 'expiry_mode' => 'weird', 'expiry_date' => '31/01/2027', 'expiry_months' => '-4', 'due_mode' => 'weird', 'due_months' => '0' ) ) ) );
		$v = $s->venues()[0];
		$this->assertSame( 'all', $s->read()['who'] );
		$this->assertSame( 'months', $v['expiry_mode'] );
		$this->assertSame( '', $v['expiry_date'] );
		$this->assertSame( 12, $v['expiry_months'] );
		$this->assertSame( 'none', $v['due_mode'] );
		$this->assertSame( 1, $v['due_months'] );
	}

	public function test_merge_venues_adds_only_new_ids(): void {
		$s = $this->settings();
		$s->save( array( 'venues' => array( array( 'id' => 'a', 'name' => 'A' ) ) ) );
		$s->merge_venues( array( 'a', 'b' ) );
		$this->assertSame( array( 'a', 'b' ), array_column( $s->venues(), 'id' ) );
		$this->assertSame( 'A', $s->venues()[0]['name'] );
		$this->assertSame( '', $s->venues()[1]['name'] );
	}

	public function test_price_ids_are_a_clean_list(): void {
		$s = $this->settings();
		$s->save( array( 'who' => 'subscriptions', 'price_ids' => array( ' p1 ', '', 'p1', 'p2' ) ) );
		$this->assertSame( array( 'p1', 'p2' ), $s->read()['price_ids'] );
	}
	public function test_recipient_email_is_kept_only_when_valid(): void {
		$s = $this->settings();
		$s->save( array( 'email' => ' imports@playtomic.example ' ) );
		$this->assertSame( 'imports@playtomic.example', $s->read()['email'] );
		$s->save( array( 'email' => 'not an address' ) );
		$this->assertSame( '', $s->read()['email'] );
		$this->assertSame( '', $this->settings()->read()['email'] );
	}
}
