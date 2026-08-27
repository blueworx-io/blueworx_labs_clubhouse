<?php

use PHPUnit\Framework\TestCase;

final class ProfileFormTest extends TestCase {

	private Blueworx_Clubhouse_Profile_Store $store;

	protected function setUp(): void {
		wp_stub_reset();
		$this->store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$this->store->save_fields(
			array(
				array( 'label' => 'Shirt size', 'type' => 'text', 'who' => 'member' ),
				array( 'label' => 'Emergency contact', 'type' => 'text', 'who' => 'member', 'required' => '1' ),
				array( 'label' => 'Squad number', 'type' => 'number', 'who' => 'club' ),
				array( 'label' => 'Notes', 'type' => 'textarea', 'who' => 'private' ),
			)
		);
	}

	/** @return array<string,string|array<int,string>> */
	private function answers(): array {
		return $this->store->answers( 7, $this->store->fields() );
	}

	public function test_a_complete_submission_is_saved(): void {
		$result = Blueworx_Clubhouse_Profile_Form::apply(
			$this->store,
			7,
			array( 'clubhouse_profile' => array( 'shirt_size' => 'Medium', 'emergency_contact' => 'Jo 07000 000000' ) )
		);
		$this->assertTrue( $result['saved'] );
		$this->assertSame( array(), $result['missing'] );
		$this->assertSame( 'Medium', $this->answers()['shirt_size'] );
	}

	public function test_a_required_field_left_blank_saves_nothing_at_all(): void {
		$result = Blueworx_Clubhouse_Profile_Form::apply(
			$this->store,
			7,
			array( 'clubhouse_profile' => array( 'shirt_size' => 'Medium', 'emergency_contact' => '' ) )
		);
		$this->assertFalse( $result['saved'] );
		$this->assertSame( array( 'Emergency contact' ), $result['missing'] );
		// Nothing partial: a member who has to come back should find the page as
		// they left it, not half-written.
		$this->assertSame( '', $this->answers()['shirt_size'] );
	}

	public function test_a_tampered_submission_cannot_set_a_club_field(): void {
		Blueworx_Clubhouse_Profile_Form::apply(
			$this->store,
			7,
			array( 'clubhouse_profile' => array( 'emergency_contact' => 'Jo', 'squad_number' => '9', 'notes' => 'Anything' ) )
		);
		$answers = $this->answers();
		$this->assertSame( '', $answers['squad_number'] );
		$this->assertSame( '', $answers['notes'] );
	}

	public function test_a_submission_for_nobody_saves_nothing(): void {
		$result = Blueworx_Clubhouse_Profile_Form::apply( $this->store, 0, array( 'clubhouse_profile' => array( 'shirt_size' => 'Medium' ) ) );
		$this->assertFalse( $result['saved'] );
		$this->assertSame( array(), wp_stub_calls( 'update_user_meta' ) );
	}

	public function test_the_renderer_answers_only_to_its_own_panel_name(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Form::panel( 'something-else' ) );
	}

	public function test_a_club_with_no_fields_gets_no_card(): void {
		$empty = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertSame( array(), $empty->fields() );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Form::panel( 'profile' ) );
	}

	public function test_a_saved_member_is_thanked(): void {
		$_GET[ Blueworx_Clubhouse_Profile_Form::RESULT_ARG ] = 'saved';
		$notices = Blueworx_Clubhouse_Profile_Form::notices();
		unset( $_GET[ Blueworx_Clubhouse_Profile_Form::RESULT_ARG ] );

		$this->assertSame( 'success', $notices[0]['type'] );
	}

	public function test_a_member_is_told_which_answers_the_club_still_needs(): void {
		$_GET[ Blueworx_Clubhouse_Profile_Form::RESULT_ARG ] = 'Emergency contact|Shirt size';
		$notices = Blueworx_Clubhouse_Profile_Form::notices();
		unset( $_GET[ Blueworx_Clubhouse_Profile_Form::RESULT_ARG ] );

		$this->assertSame( 'error', $notices[0]['type'] );
		$this->assertStringContainsString( 'Emergency contact', $notices[0]['text'] );
		$this->assertStringContainsString( 'Shirt size', $notices[0]['text'] );
	}

	public function test_an_address_with_nothing_to_report_shows_no_notice(): void {
		$this->assertSame( array(), Blueworx_Clubhouse_Profile_Form::notices() );
	}
}
