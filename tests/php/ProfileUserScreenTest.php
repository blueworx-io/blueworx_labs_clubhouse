<?php

use PHPUnit\Framework\TestCase;

final class ProfileUserScreenTest extends TestCase {

	private Blueworx_Clubhouse_Profile_Store $store;

	protected function setUp(): void {
		wp_stub_reset();
		$this->store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$this->store->save_fields(
			array(
				array( 'label' => 'Shirt size', 'type' => 'text', 'who' => 'member' ),
				array( 'label' => 'Squad number', 'type' => 'number', 'who' => 'club' ),
				array( 'label' => 'Notes', 'type' => 'textarea', 'who' => 'private' ),
			)
		);
	}

	private function table(): string {
		return Blueworx_Clubhouse_Profile_User_Screen::table( $this->store->fields(), $this->store->answers( 7, $this->store->fields() ) );
	}

	public function test_staff_see_every_field_including_the_private_one(): void {
		$html = $this->table();
		$this->assertStringContainsString( 'Shirt size', $html );
		$this->assertStringContainsString( 'Squad number', $html );
		$this->assertStringContainsString( 'Notes', $html );
	}

	public function test_every_field_is_editable_here_even_the_members_own(): void {
		$html = $this->table();
		foreach ( array( 'shirt_size', 'squad_number', 'notes' ) as $key ) {
			$this->assertStringContainsString( 'clubhouse_profile[' . $key . ']', $html );
		}
	}

	public function test_staff_are_told_which_fields_the_member_cannot_see_or_change(): void {
		$html = $this->table();
		$this->assertStringContainsString( 'never sees', $html );
		$this->assertStringContainsString( 'cannot change', $html );
	}

	public function test_a_club_with_no_fields_draws_nothing(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Profile_User_Screen::table( array(), array() ) );
	}

	public function test_a_stored_answer_is_shown_back(): void {
		$this->store->save_answers( 7, array( 'shirt_size' => 'Large' ) );
		$this->assertStringContainsString( 'value="Large"', $this->table() );
	}

	public function test_staff_can_write_every_field(): void {
		Blueworx_Clubhouse_Profile_User_Screen::save(
			$this->store,
			7,
			array( 'clubhouse_profile' => array( 'shirt_size' => 'Large', 'squad_number' => '9', 'notes' => 'Paid in cash' ) )
		);
		$answers = $this->store->answers( 7, $this->store->fields() );
		$this->assertSame( 'Large', $answers['shirt_size'] );
		$this->assertSame( '9', $answers['squad_number'] );
		$this->assertSame( 'Paid in cash', $answers['notes'] );
	}

	public function test_a_required_field_left_blank_never_blocks_staff(): void {
		$this->store->save_fields( array( array( 'label' => 'Shirt size', 'type' => 'text', 'who' => 'member', 'required' => '1' ) ) );
		Blueworx_Clubhouse_Profile_User_Screen::save( $this->store, 7, array( 'clubhouse_profile' => array( 'shirt_size' => '' ) ) );
		$this->assertSame( '', $this->store->answers( 7, $this->store->fields() )['shirt_size'] );
	}

	public function test_a_save_for_nobody_writes_nothing(): void {
		Blueworx_Clubhouse_Profile_User_Screen::save( $this->store, 0, array( 'clubhouse_profile' => array( 'shirt_size' => 'Large' ) ) );
		$this->assertSame( array(), wp_stub_calls( 'update_user_meta' ) );
	}

	public function test_a_label_that_carries_markup_is_escaped(): void {
		$this->store->save_fields( array( array( 'label' => '<script>alert(1)</script>', 'type' => 'text', 'who' => 'member' ) ) );
		$this->assertStringNotContainsString( '<script>', $this->table() );
	}

	public function test_it_listens_on_both_the_members_own_screen_and_the_staff_one(): void {
		Blueworx_Clubhouse_Profile_User_Screen::register();
		$hooks = array_map( static fn( array $c ): string => (string) $c['args'][0], wp_stub_calls( 'add_action' ) );
		foreach ( array( 'show_user_profile', 'edit_user_profile', 'personal_options_update', 'edit_user_profile_update' ) as $hook ) {
			$this->assertContains( $hook, $hooks );
		}
	}
}
