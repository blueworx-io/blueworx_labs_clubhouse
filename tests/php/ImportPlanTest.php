<?php
// tests/php/ImportPlanTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportPlanTest extends TestCase {

	public function test_a_fresh_plan_is_empty(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$this->assertTrue( $plan->is_empty() );
	}

	public function test_a_warning_alone_does_not_make_a_plan_non_empty(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->warn( 'unknown section "nope"' );
		$this->assertTrue( $plan->is_empty() );
		$this->assertSame( array( 'unknown section "nope"' ), $plan->warnings() );
	}

	public function test_fields_nest_by_page_and_section(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$plan->add_field( 'home', 'hero', 'lede', 'A club for all' );
		$this->assertFalse( $plan->is_empty() );
		$this->assertSame(
			array( 'eyebrow' => 'Est. 1974', 'lede' => 'A club for all' ),
			$plan->fields()['home']['hero']
		);
	}

	public function test_items_are_stored_as_a_list(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'membership', 'faq', array( array( 'question' => 'Q', 'answer' => 'A' ) ) );
		$this->assertCount( 1, $plan->items()['membership']['faq'] );
	}

	public function test_images_are_a_flat_queue(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/a.jpg', 'Pavilion', 'Global · Hero — Background image' );
		$this->assertSame( 'https://e.test/a.jpg', $plan->images()[0]['url'] );
		$this->assertSame( 'Global · Hero — Background image', $plan->images()[0]['label'] );
		$this->assertSame( -1, $plan->images()[0]['index'] );
		$this->assertFalse( $plan->is_empty() );
	}

	public function test_a_loop_item_image_records_its_item_index(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'news', 'image', 'https://e.test/n.jpg', '', 'Global · News — Image', 2 );
		$this->assertSame( 2, $plan->images()[0]['index'] );
	}

	public function test_collections_are_keyed_by_type(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_collection( 'clubhouse_sport', array(
			array( 'title' => 'Tennis', 'meta' => array( 'subtitle' => 'Six courts' ), 'images' => array() ),
		) );
		$this->assertSame( 'Tennis', $plan->collections()['clubhouse_sport'][0]['title'] );
		$this->assertFalse( $plan->is_empty() );
	}

	public function test_round_trips_through_an_array(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$plan->add_items( 'home', 'stats', array( array( 'value' => '450', 'label' => 'Members', 'featured' => true ) ) );
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/a.jpg', '', 'Global · Hero — Background image', 3 );
		$plan->add_collection( 'clubhouse_sport', array( array( 'title' => 'Tennis', 'meta' => array(), 'images' => array() ) ) );
		$plan->warn( 'unknown field "x"' );

		$copy = Blueworx_Clubhouse_Import_Plan::from_array( $plan->to_array() );

		$this->assertSame( $plan->fields(), $copy->fields() );
		$this->assertSame( $plan->items(), $copy->items() );
		$this->assertSame( $plan->images(), $copy->images() );
		$this->assertSame( $plan->collections(), $copy->collections() );
		$this->assertSame( $plan->warnings(), $copy->warnings() );
	}

	public function test_from_array_tolerates_junk(): void {
		$copy = Blueworx_Clubhouse_Import_Plan::from_array( array( 'fields' => 'not-an-array', 'nope' => 1 ) );
		$this->assertTrue( $copy->is_empty() );
	}
}
