<?php

use PHPUnit\Framework\TestCase;

final class ProfilePanelTest extends TestCase {

	/** @return array<string,mixed> */
	private function field( string $key, string $type, string $who, array $extra = array() ): array {
		return array_merge(
			array( 'key' => $key, 'label' => ucfirst( $key ), 'type' => $type, 'choices' => array(), 'help' => '', 'required' => false, 'who' => $who ),
			$extra
		);
	}

	/**
	 * @param array<int,array<string,mixed>>         $fields
	 * @param array<string,string|array<int,string>> $answers
	 */
	private function render( array $fields, array $answers = array() ): string {
		return Blueworx_Clubhouse_Profile_Panel::render( $fields, $answers, 'https://club.test/save', '<input name="_wpnonce" value="n">', array() );
	}

	public function test_a_club_with_no_fields_draws_no_card_at_all(): void {
		$this->assertSame( '', $this->render( array() ) );
	}

	public function test_a_club_with_only_private_fields_draws_no_card(): void {
		$this->assertSame( '', $this->render( array( $this->field( 'notes', 'textarea', 'private' ) ) ) );
	}

	public function test_a_private_field_never_reaches_the_html(): void {
		$html = $this->render(
			array( $this->field( 'shirt', 'text', 'member' ), $this->field( 'notes', 'textarea', 'private' ) ),
			array( 'shirt' => 'Medium', 'notes' => 'Paid in cash' )
		);
		$this->assertStringNotContainsString( 'notes', $html );
		$this->assertStringNotContainsString( 'Paid in cash', $html );
	}

	public function test_a_member_field_is_an_editable_control(): void {
		$html = $this->render( array( $this->field( 'shirt', 'text', 'member' ) ), array( 'shirt' => 'Medium' ) );
		$this->assertStringContainsString( 'name="clubhouse_profile[shirt]"', $html );
		$this->assertStringContainsString( 'value="Medium"', $html );
	}

	public function test_a_club_field_is_shown_but_has_no_control_to_change_it(): void {
		$html = $this->render( array( $this->field( 'squad', 'number', 'club' ) ), array( 'squad' => '9' ) );
		$this->assertStringContainsString( '9', $html );
		$this->assertStringNotContainsString( 'name="clubhouse_profile[squad]"', $html );
	}

	public function test_an_unanswered_club_field_says_so_rather_than_showing_a_gap(): void {
		$html = $this->render( array( $this->field( 'squad', 'number', 'club' ) ), array( 'squad' => '' ) );
		$this->assertStringContainsString( 'Not set', $html );
	}

	public function test_a_club_tick_reads_as_yes_or_no_not_as_a_one(): void {
		$yes = $this->render( array( $this->field( 'paid', 'checkbox', 'club' ) ), array( 'paid' => '1' ) );
		$no  = $this->render( array( $this->field( 'paid', 'checkbox', 'club' ) ), array( 'paid' => '' ) );
		$this->assertStringContainsString( 'Yes', $yes );
		$this->assertStringContainsString( 'No', $no );
	}

	public function test_a_required_field_is_marked_required_in_the_markup(): void {
		$html = $this->render( array( $this->field( 'contact', 'text', 'member', array( 'required' => true ) ) ) );
		$this->assertStringContainsString( 'required', $html );
	}

	public function test_a_dropdown_offers_the_clubs_choices_and_a_way_to_answer_nothing(): void {
		$html = $this->render( array( $this->field( 'shirt', 'select', 'member', array( 'choices' => array( 'Small', 'Medium' ) ) ) ) );
		$this->assertStringContainsString( '<option value="Small">Small</option>', $html );
		$this->assertStringContainsString( '<option value="Medium">Medium</option>', $html );
		$this->assertStringContainsString( '<option value="">', $html );
	}

	public function test_a_multi_select_posts_a_list_and_marks_what_was_chosen(): void {
		$html = $this->render(
			array( $this->field( 'allergies', 'multiselect', 'member', array( 'choices' => array( 'Nuts', 'Dairy' ) ) ) ),
			array( 'allergies' => array( 'Nuts' ) )
		);
		$this->assertStringContainsString( 'name="clubhouse_profile[allergies][]"', $html );
		$this->assertStringContainsString( 'multiple', $html );
		$this->assertStringContainsString( '<option value="Nuts" selected>Nuts</option>', $html );
	}

	public function test_a_club_multi_select_reads_as_a_list_of_words(): void {
		$html = $this->render(
			array( $this->field( 'allergies', 'multiselect', 'club', array( 'choices' => array( 'Nuts', 'Dairy' ) ) ) ),
			array( 'allergies' => array( 'Nuts', 'Dairy' ) )
		);
		$this->assertStringContainsString( 'Nuts, Dairy', $html );
	}

	public function test_a_members_answer_is_escaped(): void {
		$html = $this->render( array( $this->field( 'shirt', 'text', 'member' ) ), array( 'shirt' => '"><script>alert(1)</script>' ) );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_a_help_note_is_shown_under_the_box(): void {
		$html = $this->render( array( $this->field( 'shirt', 'text', 'member', array( 'help' => 'We order kit in March.' ) ) ) );
		$this->assertStringContainsString( 'We order kit in March.', $html );
	}

	public function test_the_card_carries_its_nonce_and_posts_to_the_action(): void {
		$html = $this->render( array( $this->field( 'shirt', 'text', 'member' ) ) );
		$this->assertStringContainsString( 'https://club.test/save', $html );
		$this->assertStringContainsString( '_wpnonce', $html );
		$this->assertStringContainsString( 'method="post"', $html );
	}

	public function test_a_club_with_only_club_fields_still_draws_the_card_but_no_save_button(): void {
		$html = $this->render( array( $this->field( 'squad', 'number', 'club' ) ), array( 'squad' => '9' ) );
		$this->assertNotSame( '', $html );
		$this->assertStringNotContainsString( '<button type="submit"', $html );
	}

	public function test_a_notice_is_shown_to_the_member(): void {
		$html = Blueworx_Clubhouse_Profile_Panel::render(
			array( $this->field( 'shirt', 'text', 'member' ) ),
			array(),
			'https://club.test/save',
			'',
			array( array( 'type' => 'success', 'text' => 'Saved.' ) )
		);
		$this->assertStringContainsString( 'Saved.', $html );
	}
}
