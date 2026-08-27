<?php

use PHPUnit\Framework\TestCase;

final class ProfileStoreTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Storage $storage;
	private Blueworx_Clubhouse_Profile_Store $store;

	protected function setUp(): void {
		wp_stub_reset();
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->store   = new Blueworx_Clubhouse_Profile_Store( $this->storage );
	}

	public function test_a_club_that_has_defined_nothing_has_no_fields(): void {
		$this->assertSame( array(), $this->store->fields() );
	}

	public function test_what_is_saved_comes_back_sanitised(): void {
		$this->store->save_fields( array( array( 'label' => 'Shirt size', 'type' => 'select', 'choices' => "Small\nMedium" ) ) );
		$fields = $this->store->fields();
		$this->assertCount( 1, $fields );
		$this->assertSame( 'shirt_size', $fields[0]['key'] );
		$this->assertSame( array( 'Small', 'Medium' ), $fields[0]['choices'] );
	}

	public function test_rubbish_in_the_option_reads_as_no_fields(): void {
		$this->storage->set( 'profile_fields', 'not-an-array' );
		$this->assertSame( array(), $this->store->fields() );
	}

	public function test_a_field_key_becomes_a_prefixed_meta_key(): void {
		$this->assertSame( 'clubhouse_profile_shirt_size', $this->store->meta_key( 'shirt_size' ) );
	}

	public function test_answers_round_trip_for_one_member(): void {
		$this->store->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		$fields = $this->store->fields();
		$this->store->save_answers( 7, array( 'shirt_size' => 'Medium' ) );
		$this->assertSame( array( 'shirt_size' => 'Medium' ), $this->store->answers( 7, $fields ) );
	}

	public function test_a_member_who_has_answered_nothing_reads_as_empty_not_missing(): void {
		$this->store->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		$this->assertSame( array( 'shirt_size' => '' ), $this->store->answers( 7, $this->store->fields() ) );
	}

	public function test_a_multi_select_answer_comes_back_as_a_list(): void {
		$this->store->save_fields( array( array( 'label' => 'Allergies', 'type' => 'multiselect', 'choices' => "Nuts\nDairy" ) ) );
		$fields = $this->store->fields();
		$this->store->save_answers( 7, array( 'allergies' => array( 'Nuts' ) ) );
		$this->assertSame( array( 'allergies' => array( 'Nuts' ) ), $this->store->answers( 7, $fields ) );
	}

	public function test_deleting_a_field_leaves_the_answers_alone(): void {
		$this->store->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		$this->store->save_answers( 7, array( 'shirt_size' => 'Medium' ) );

		$this->store->save_fields( array() );
		$this->assertSame( array(), $this->store->fields() );

		// Re-adding the field finds the answer still attached.
		$this->store->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		$this->assertSame( array( 'shirt_size' => 'Medium' ), $this->store->answers( 7, $this->store->fields() ) );
	}

	public function test_forgetting_a_field_clears_it_for_every_member(): void {
		$this->store->save_answers( 7, array( 'shirt_size' => 'Medium' ) );
		$this->store->forget( 'shirt_size' );

		$calls = wp_stub_calls( 'delete_metadata' );
		$this->assertSame( array( 'user', 0, 'clubhouse_profile_shirt_size', '', true ), $calls[0]['args'] );

		$this->store->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		$this->assertSame( array( 'shirt_size' => '' ), $this->store->answers( 7, $this->store->fields() ) );
	}
}
