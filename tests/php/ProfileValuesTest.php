<?php

use PHPUnit\Framework\TestCase;

final class ProfileValuesTest extends TestCase {

	/** @return array<string,mixed> */
	private function field( string $type, string $who = 'member', bool $required = false, array $choices = array(), string $key = 'f' ): array {
		return array(
			'key'      => $key,
			'label'    => 'A field',
			'type'     => $type,
			'choices'  => $choices,
			'help'     => '',
			'required' => $required,
			'who'      => $who,
		);
	}

	public function test_short_text_is_trimmed_and_stripped_of_markup(): void {
		$this->assertSame( 'Medium', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'text' ), '  <b>Medium</b> ' ) );
	}

	public function test_long_text_keeps_its_line_breaks(): void {
		$this->assertSame( "One\nTwo", Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'textarea' ), "One\nTwo" ) );
	}

	public function test_a_number_that_is_not_a_number_is_discarded(): void {
		$this->assertSame( '12', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'number' ), ' 12 ' ) );
		$this->assertSame( '-3.5', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'number' ), '-3.5' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'number' ), 'twelve' ) );
	}

	public function test_a_date_must_be_a_real_calendar_date(): void {
		$this->assertSame( '2026-02-28', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'date' ), '2026-02-28' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'date' ), '2026-02-30' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'date' ), '28/02/2026' ) );
	}

	public function test_a_dropdown_answer_the_club_never_offered_is_discarded(): void {
		$field = $this->field( 'select', 'member', false, array( 'Small', 'Medium' ) );
		$this->assertSame( 'Medium', Blueworx_Clubhouse_Profile_Values::clean( $field, 'Medium' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Values::clean( $field, 'Enormous' ) );
	}

	public function test_multi_select_keeps_only_offered_choices_and_returns_a_list(): void {
		$field = $this->field( 'multiselect', 'member', false, array( 'Nuts', 'Dairy', 'Gluten' ) );
		$this->assertSame(
			array( 'Nuts', 'Gluten' ),
			Blueworx_Clubhouse_Profile_Values::clean( $field, array( 'Nuts', 'Enormous', 'Gluten' ) )
		);
		$this->assertSame( array(), Blueworx_Clubhouse_Profile_Values::clean( $field, 'not-a-list' ) );
	}

	public function test_a_tick_is_one_or_empty(): void {
		$this->assertSame( '1', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'checkbox' ), '1' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'checkbox' ), '' ) );
	}

	public function test_blankness_understands_a_list(): void {
		$multi = $this->field( 'multiselect', 'member', true, array( 'Nuts' ) );
		$this->assertTrue( Blueworx_Clubhouse_Profile_Values::is_blank( $multi, array() ) );
		$this->assertFalse( Blueworx_Clubhouse_Profile_Values::is_blank( $multi, array( 'Nuts' ) ) );
		$this->assertTrue( Blueworx_Clubhouse_Profile_Values::is_blank( $this->field( 'text' ), '' ) );
	}

	public function test_a_private_field_is_not_visible_to_the_member(): void {
		$fields = array(
			$this->field( 'text', 'member', false, array(), 'a' ),
			$this->field( 'text', 'club', false, array(), 'b' ),
			$this->field( 'text', 'private', false, array(), 'c' ),
		);

		$this->assertSame( array( 'a', 'b' ), array_column( Blueworx_Clubhouse_Profile_Values::visible_to_member( $fields ), 'key' ) );
		$this->assertSame( array( 'a' ), array_column( Blueworx_Clubhouse_Profile_Values::writable_by_member( $fields ), 'key' ) );
	}

	public function test_a_member_cannot_write_a_club_field_by_posting_it(): void {
		$fields = array(
			array( 'key' => 'shirt', 'label' => 'Shirt size', 'type' => 'text', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'member' ),
			array( 'key' => 'squad', 'label' => 'Squad number', 'type' => 'number', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'club' ),
			array( 'key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'private' ),
		);
		$result = Blueworx_Clubhouse_Profile_Values::from_member_post(
			$fields,
			array( 'clubhouse_profile' => array( 'shirt' => 'Medium', 'squad' => '9', 'notes' => 'Anything' ) )
		);
		$this->assertSame( array( 'shirt' => 'Medium' ), $result['values'] );
		$this->assertSame( array(), $result['missing'] );
	}

	public function test_a_required_field_left_blank_is_reported_by_its_label(): void {
		$fields = array(
			array( 'key' => 'contact', 'label' => 'Emergency contact', 'type' => 'text', 'choices' => array(), 'help' => '', 'required' => true, 'who' => 'member' ),
		);
		$result = Blueworx_Clubhouse_Profile_Values::from_member_post( $fields, array( 'clubhouse_profile' => array( 'contact' => '   ' ) ) );
		$this->assertSame( array( 'Emergency contact' ), $result['missing'] );
	}

	public function test_staff_may_write_every_field_and_are_never_blocked_by_required(): void {
		$fields = array(
			array( 'key' => 'shirt', 'label' => 'Shirt size', 'type' => 'text', 'choices' => array(), 'help' => '', 'required' => true, 'who' => 'member' ),
			array( 'key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'private' ),
		);
		$values = Blueworx_Clubhouse_Profile_Values::from_admin_post(
			$fields,
			array( 'clubhouse_profile' => array( 'shirt' => '', 'notes' => 'Paid in cash' ) )
		);
		$this->assertSame( array( 'shirt' => '', 'notes' => 'Paid in cash' ), $values );
	}
}
