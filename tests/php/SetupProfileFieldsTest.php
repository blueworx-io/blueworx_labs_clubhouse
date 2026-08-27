<?php

use PHPUnit\Framework\TestCase;

final class SetupProfileFieldsTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Storage $storage;

	protected function setUp(): void {
		wp_stub_reset();
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
	}

	/** @return array<int,array<string,mixed>> */
	private function fields(): array {
		return ( new Blueworx_Clubhouse_Profile_Store( $this->storage ) )->fields();
	}

	/** @return array<string,mixed> */
	private function one( string $key = 'shirt_size', string $label = 'Shirt size' ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'text', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'member' );
	}

	public function test_a_club_with_no_fields_is_still_offered_a_row_to_fill_in(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::profile_fields_area( array() );
		$this->assertStringContainsString( 'clubhouse_profile_field[0][label]', $html );
		$this->assertStringContainsString( 'clubhouse_profile_field_add', $html );
	}

	public function test_every_type_and_every_who_setting_is_offered(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::profile_fields_area( array( $this->one() ) );
		foreach ( array_keys( Blueworx_Clubhouse_Profile_Fields::TYPES ) as $type ) {
			$this->assertStringContainsString( 'value="' . $type . '"', $html );
		}
		foreach ( array_keys( Blueworx_Clubhouse_Profile_Fields::WHO ) as $who ) {
			$this->assertStringContainsString( 'value="' . $who . '"', $html );
		}
	}

	public function test_the_key_travels_with_the_row_so_a_rename_keeps_the_answers(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::profile_fields_area( array( $this->one() ) );
		$this->assertStringContainsString( 'name="clubhouse_profile_field[0][key]"', $html );
		$this->assertStringContainsString( 'value="shirt_size"', $html );
	}

	public function test_the_blank_row_at_the_end_carries_no_key_and_cannot_be_removed(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::profile_fields_area( array( $this->one() ) );
		$this->assertStringContainsString( 'clubhouse_profile_field[1][label]', $html );
		$this->assertStringNotContainsString( 'name="clubhouse_profile_field[1][key]"', $html );
		// One existing field, so exactly one remove control.
		$this->assertSame( 1, substr_count( $html, 'clubhouse_profile_field_remove' ) );
	}

	public function test_a_club_at_the_cap_is_told_rather_than_offered_another(): void {
		$fields = array();
		for ( $i = 1; $i <= Blueworx_Clubhouse_Profile_Fields::MAX_FIELDS; $i++ ) {
			$fields[] = $this->one( 'f' . $i, 'Field ' . $i );
		}
		$html = Blueworx_Clubhouse_Setup_Screen::profile_fields_area( $fields );
		$this->assertStringNotContainsString( 'clubhouse_profile_field_add', $html );
		$this->assertStringContainsString( '30 fields', $html );
	}

	public function test_saving_the_setup_form_stores_the_fields(): void {
		Blueworx_Clubhouse_Setup_Controller::handle_save(
			array( 'clubhouse_profile_field' => array( array( 'label' => 'Shirt size', 'type' => 'select', 'choices' => "Small\nMedium", 'who' => 'member' ) ) ),
			$this->storage
		);
		$fields = $this->fields();
		$this->assertSame( 'shirt_size', $fields[0]['key'] );
		$this->assertSame( 'select', $fields[0]['type'] );
	}

	public function test_the_blank_row_is_not_saved_as_a_nameless_field(): void {
		Blueworx_Clubhouse_Setup_Controller::handle_save(
			array(
				'clubhouse_profile_field'     => array( array( 'label' => 'Shirt size' ), array( 'label' => '' ) ),
				'clubhouse_profile_field_add' => '1',
			),
			$this->storage
		);
		$this->assertCount( 1, $this->fields() );
	}

	public function test_remove_takes_the_named_row_out(): void {
		Blueworx_Clubhouse_Setup_Controller::handle_save(
			array(
				'clubhouse_profile_field'        => array(
					array( 'key' => 'shirt_size', 'label' => 'Shirt size' ),
					array( 'key' => 'squad', 'label' => 'Squad number' ),
				),
				'clubhouse_profile_field_remove' => '0',
			),
			$this->storage
		);
		$this->assertSame( array( 'squad' ), array_column( $this->fields(), 'key' ) );
	}

	public function test_removing_a_field_does_not_clear_the_answers(): void {
		Blueworx_Clubhouse_Setup_Controller::handle_save(
			array(
				'clubhouse_profile_field'        => array( array( 'key' => 'shirt_size', 'label' => 'Shirt size' ) ),
				'clubhouse_profile_field_remove' => '0',
			),
			$this->storage
		);
		$this->assertSame( array(), wp_stub_calls( 'delete_metadata' ) );
	}

	public function test_forget_clears_the_answers_takes_the_row_out_and_says_so(): void {
		$notices = Blueworx_Clubhouse_Setup_Controller::handle_save(
			array(
				'clubhouse_profile_field'        => array( array( 'key' => 'shirt_size', 'label' => 'Shirt size' ) ),
				'clubhouse_profile_field_forget' => 'shirt_size',
			),
			$this->storage
		);
		$calls = wp_stub_calls( 'delete_metadata' );
		$this->assertSame( 'clubhouse_profile_shirt_size', $calls[0]['args'][2] );
		$this->assertSame( array(), $this->fields() );

		$texts = array_column( $notices, 'text' );
		$this->assertNotEmpty( array_filter( $texts, static fn( $t ) => str_contains( $t, 'cleared' ) ) );
	}

	public function test_a_setup_save_that_never_mentions_profile_fields_leaves_them_alone(): void {
		( new Blueworx_Clubhouse_Profile_Store( $this->storage ) )->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		Blueworx_Clubhouse_Setup_Controller::handle_save( array( 'clubhouse_post_login' => '/members/' ), $this->storage );
		$this->assertCount( 1, $this->fields() );
	}

	public function test_the_model_carries_the_clubs_fields_to_the_screen(): void {
		( new Blueworx_Clubhouse_Profile_Store( $this->storage ) )->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		$model = Blueworx_Clubhouse_Setup_Controller::build_model( $this->storage, array(), '', '' );
		$this->assertSame( array( 'shirt_size' ), array_column( (array) $model['profile_fields'], 'key' ) );
	}
}
