<?php
// tests/php/ContentSanitiserTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ContentSanitiserTest extends TestCase {

	public function test_text_is_stripped_and_trimmed(): void {
		$def = array( 'key' => 'a', 'label' => 'A', 'type' => 'text' );
		$this->assertSame( 'One club', Blueworx_Clubhouse_Content_Sanitiser::field( $def, '  One club <script>  ', true ) );
	}

	public function test_absent_field_becomes_empty_string(): void {
		$def = array( 'key' => 'a', 'label' => 'A', 'type' => 'text' );
		$this->assertSame( '', Blueworx_Clubhouse_Content_Sanitiser::field( $def, null, false ) );
	}

	public function test_toggle_reflects_presence(): void {
		$def = array( 'key' => 'a', 'label' => 'A', 'type' => 'toggle' );
		$this->assertTrue( Blueworx_Clubhouse_Content_Sanitiser::field( $def, '1', true ) );
		$this->assertFalse( Blueworx_Clubhouse_Content_Sanitiser::field( $def, null, false ) );
	}

	public function test_image_absent_is_empty_string_not_zero(): void {
		$def = array( 'key' => 'image', 'label' => 'Image', 'type' => 'image' );
		$this->assertSame( '', Blueworx_Clubhouse_Content_Sanitiser::field( $def, '', true ) );
		$this->assertSame( 12, Blueworx_Clubhouse_Content_Sanitiser::field( $def, '12', true ) );
	}

	public function test_select_falls_back_when_value_not_an_option(): void {
		$def = array( 'key' => 'icon', 'label' => 'Icon', 'type' => 'select', 'options' => array( '' => 'None', 'join' => 'Join' ) );
		$this->assertSame( 'join', Blueworx_Clubhouse_Content_Sanitiser::field( $def, 'join', true ) );
		$this->assertSame( '', Blueworx_Clubhouse_Content_Sanitiser::field( $def, 'evil', true ) );
	}

	public function test_non_scalar_value_is_treated_as_absent(): void {
		$def = array( 'key' => 'a', 'label' => 'A', 'type' => 'text' );
		$this->assertSame( '', Blueworx_Clubhouse_Content_Sanitiser::field( $def, array( 'x' ), true ) );
	}

	public function test_items_fills_every_declared_field(): void {
		$loop = array(
			array( 'key' => 'label', 'label' => 'Label', 'type' => 'text' ),
			array( 'key' => 'featured', 'label' => 'Featured', 'type' => 'toggle' ),
		);
		$out = Blueworx_Clubhouse_Content_Sanitiser::items( $loop, array( array( 'label' => 'Tennis' ) ) );
		$this->assertSame( array( array( 'label' => 'Tennis', 'featured' => false ) ), $out );
	}
}
