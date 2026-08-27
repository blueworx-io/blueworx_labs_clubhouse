<?php

use PHPUnit\Framework\TestCase;

final class ProfileFieldsTest extends TestCase {

	public function test_the_seven_types_the_spec_names_are_the_types_offered(): void {
		$this->assertSame(
			array( 'text', 'textarea', 'number', 'date', 'select', 'multiselect', 'checkbox' ),
			array_keys( Blueworx_Clubhouse_Profile_Fields::TYPES )
		);
	}

	public function test_who_fills_it_in_has_exactly_three_settings(): void {
		$this->assertSame( array( 'member', 'club', 'private' ), array_keys( Blueworx_Clubhouse_Profile_Fields::WHO ) );
	}

	public function test_only_the_two_choice_types_take_choices(): void {
		$this->assertTrue( Blueworx_Clubhouse_Profile_Fields::has_choices( 'select' ) );
		$this->assertTrue( Blueworx_Clubhouse_Profile_Fields::has_choices( 'multiselect' ) );
		$this->assertFalse( Blueworx_Clubhouse_Profile_Fields::has_choices( 'text' ) );
	}

	public function test_a_key_is_made_from_the_label(): void {
		$this->assertSame( 'shirt_size', Blueworx_Clubhouse_Profile_Fields::key_from_label( 'Shirt size', array() ) );
	}

	public function test_a_second_field_with_the_same_label_gets_its_own_key(): void {
		$this->assertSame( 'shirt_size_2', Blueworx_Clubhouse_Profile_Fields::key_from_label( 'Shirt size', array( 'shirt_size' ) ) );
	}

	public function test_a_label_of_pure_punctuation_still_yields_a_usable_key(): void {
		$this->assertSame( 'field', Blueworx_Clubhouse_Profile_Fields::key_from_label( '!!!', array() ) );
	}

	public function test_a_field_with_no_label_is_dropped(): void {
		$this->assertNull( Blueworx_Clubhouse_Profile_Fields::sanitise_one( array( 'label' => '   ' ), array() ) );
	}

	public function test_a_bare_row_becomes_a_complete_definition(): void {
		$field = Blueworx_Clubhouse_Profile_Fields::sanitise_one( array( 'label' => 'Shirt size' ), array() );
		$this->assertSame(
			array(
				'key'      => 'shirt_size',
				'label'    => 'Shirt size',
				'type'     => 'text',
				'choices'  => array(),
				'help'     => '',
				'required' => false,
				'who'      => 'member',
			),
			$field
		);
	}

	public function test_an_unknown_type_or_who_falls_back_to_the_safe_default(): void {
		$field = Blueworx_Clubhouse_Profile_Fields::sanitise_one(
			array( 'label' => 'Squad number', 'type' => 'nonsense', 'who' => 'nonsense' ),
			array()
		);
		$this->assertSame( 'text', $field['type'] );
		// 'member' is the safe default: a field nobody declared private must not
		// become private by accident, and a member-editable field leaks nothing.
		$this->assertSame( 'member', $field['who'] );
	}

	public function test_choices_are_one_per_line_and_blank_lines_are_dropped(): void {
		$field = Blueworx_Clubhouse_Profile_Fields::sanitise_one(
			array( 'label' => 'Shirt size', 'type' => 'select', 'choices' => "Small\n\n Medium \nLarge\n" ),
			array()
		);
		$this->assertSame( array( 'Small', 'Medium', 'Large' ), $field['choices'] );
	}

	public function test_a_non_choice_type_keeps_no_choices_even_if_some_were_typed(): void {
		$field = Blueworx_Clubhouse_Profile_Fields::sanitise_one(
			array( 'label' => 'Notes', 'type' => 'textarea', 'choices' => "One\nTwo" ),
			array()
		);
		$this->assertSame( array(), $field['choices'] );
	}

	public function test_an_existing_key_is_kept_so_a_rename_does_not_lose_the_answers(): void {
		$field = Blueworx_Clubhouse_Profile_Fields::sanitise_one(
			array( 'key' => 'shirt_size', 'label' => 'Kit size' ),
			array()
		);
		$this->assertSame( 'shirt_size', $field['key'] );
		$this->assertSame( 'Kit size', $field['label'] );
	}

	public function test_sanitise_all_drops_empty_rows_and_keeps_keys_unique(): void {
		$fields = Blueworx_Clubhouse_Profile_Fields::sanitise_all(
			array(
				array( 'label' => 'Shirt size' ),
				array( 'label' => '' ),
				array( 'label' => 'Shirt size' ),
			)
		);
		$this->assertSame( array( 'shirt_size', 'shirt_size_2' ), array_column( $fields, 'key' ) );
	}

	public function test_no_more_than_thirty_fields_survive(): void {
		$rows = array();
		for ( $i = 1; $i <= 35; $i++ ) {
			$rows[] = array( 'label' => 'Field ' . $i );
		}
		$this->assertCount( 30, Blueworx_Clubhouse_Profile_Fields::sanitise_all( $rows ) );
	}
}
