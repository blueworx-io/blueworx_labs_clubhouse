<?php
// tests/php/ProfileStandardFieldsTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProfileStandardFieldsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_standard_fields_come_first_and_in_order(): void {
		$store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$store->save_fields( array( array( 'label' => 'Shirt size', 'type' => 'text', 'who' => 'member' ) ) );

		$keys = array_column( $store->fields(), 'key' );
		$this->assertSame( array( 'phone', 'gender', 'birthdate', 'shirt_size' ), $keys );
	}

	public function test_standard_definitions_are_member_filled_and_typed(): void {
		$std = Blueworx_Clubhouse_Profile_Fields::standard();
		$this->assertSame( 'text', $std[0]['type'] );
		$this->assertSame( 'select', $std[1]['type'] );
		$this->assertSame( Blueworx_Clubhouse_Profile_Fields::GENDER_CHOICES, $std[1]['choices'] );
		$this->assertSame( 'date', $std[2]['type'] );
		foreach ( $std as $field ) {
			$this->assertSame( 'member', $field['who'] );
			$this->assertFalse( $field['required'] );
		}
	}

	public function test_a_custom_field_can_never_take_a_standard_key(): void {
		$fields = Blueworx_Clubhouse_Profile_Fields::sanitise_all(
			array( array( 'label' => 'Phone', 'type' => 'text' ), array( 'key' => 'gender', 'label' => 'Anything' ) )
		);
		$this->assertSame( 'phone_2', $fields[0]['key'] );
		$this->assertNotSame( 'gender', $fields[1]['key'] );
	}

	public function test_custom_fields_excludes_the_standard_ones(): void {
		$store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$store->save_fields( array( array( 'label' => 'Notes', 'type' => 'textarea' ) ) );
		$this->assertSame( array( 'notes' ), array_column( $store->custom_fields(), 'key' ) );
	}

	public function test_hidden_standard_fields_are_left_out(): void {
		$store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$store->set_hidden_standard( array( 'gender', 'nonsense' ) );
		$this->assertSame( array( 'gender' ), $store->hidden_standard() );
		$this->assertSame( array( 'phone', 'birthdate' ), array_column( $store->fields(), 'key' ) );
	}

	public function test_answers_read_standard_meta_keys(): void {
		$store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		update_user_meta( 7, 'clubhouse_profile_phone', '0412 345 678' );
		$answers = $store->answers( 7, $store->fields() );
		$this->assertSame( '0412 345 678', $answers['phone'] );
		$this->assertSame( '', $answers['gender'] );
	}
}
