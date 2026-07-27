<?php
// tests/php/ImportPreviewTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportPreviewTest extends TestCase {

	private function plan(): Blueworx_Clubhouse_Import_Plan {
		return new Blueworx_Clubhouse_Import_Plan();
	}

	public function test_an_empty_plan_reports_no_rows(): void {
		// is_empty() is not part of this contract: Import_Screen already treats an
		// empty rows array as "nothing to import", so that is the fact this test
		// pins — see Blueworx_Clubhouse_Import_Plan::is_empty() for the plan-level check.
		$out = Blueworx_Clubhouse_Import_Preview::summary( $this->plan(), array() );
		$this->assertSame( array(), $out['rows'] );
	}

	public function test_a_section_row_is_named_for_a_human_and_counts_its_fields(): void {
		$plan = $this->plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$plan->add_field( 'home', 'hero', 'lede', 'A club for all' );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array() );
		$this->assertSame( 'Global · Hero', $out['rows'][0]['label'] );
		$this->assertSame( '2 fields', $out['rows'][0]['detail'] );
	}

	public function test_a_single_field_is_not_pluralised(): void {
		$plan = $this->plan();
		$plan->add_field( 'global', 'header', 'join', 'Join us' );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array() );
		$this->assertSame( '1 field', $out['rows'][0]['detail'] );
	}

	public function test_fields_and_entries_for_one_section_share_a_row(): void {
		$plan = $this->plan();
		$plan->add_field( 'home', 'news', 'heading', 'Latest' );
		$plan->add_items( 'home', 'news', array( array( 'title' => 'A' ), array( 'title' => 'B' ) ) );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array() );
		$this->assertCount( 1, $out['rows'] );
		$this->assertSame( '1 field, 2 entries', $out['rows'][0]['detail'] );
	}

	public function test_an_entries_only_section_still_gets_a_row(): void {
		$plan = $this->plan();
		$plan->add_items( 'membership', 'faq', array( array( 'question' => 'Q' ) ) );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array() );
		$this->assertSame( 'Membership · FAQ', $out['rows'][0]['label'] );
		$this->assertSame( '1 entry', $out['rows'][0]['detail'] );
	}

	public function test_rows_follow_catalogue_order_not_file_order(): void {
		$plan = $this->plan();
		$plan->add_field( 'contact', 'form', 'heading', 'Say hello' );
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array() );
		// Home's hero is declared on the Global tab, before Contact.
		$this->assertSame( 'Global · Hero', $out['rows'][0]['label'] );
		$this->assertSame( 'Contact · Contact form', $out['rows'][1]['label'] );
	}

	public function test_a_collection_row_reports_the_demo_posts_it_replaces(): void {
		$plan = $this->plan();
		$plan->add_collection( 'clubhouse_sport', array(
			array( 'title' => 'Tennis', 'meta' => array(), 'images' => array() ),
			array( 'title' => 'Rugby', 'meta' => array(), 'images' => array() ),
		) );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array( 'clubhouse_sport' => 6 ) );
		$this->assertSame( 'Sports', $out['rows'][0]['label'] );
		$this->assertSame( '2 entries, replacing 6 demo entries', $out['rows'][0]['detail'] );
	}

	public function test_a_collection_row_omits_the_demo_clause_when_there_are_none(): void {
		$plan = $this->plan();
		$plan->add_collection( 'clubhouse_sport', array( array( 'title' => 'Tennis', 'meta' => array(), 'images' => array() ) ) );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array( 'clubhouse_sport' => 0 ) );
		$this->assertSame( '1 entry', $out['rows'][0]['detail'] );
	}

	public function test_images_get_their_own_final_row(): void {
		$plan = $this->plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/a.jpg', '', 'Global · Hero — Background image' );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array() );
		$last = end( $out['rows'] );
		$this->assertSame( 'Images', $last['label'] );
		$this->assertSame( '1 image to fetch', $last['detail'] );
	}

	public function test_collection_images_are_counted_too(): void {
		$plan = $this->plan();
		$plan->add_collection( 'clubhouse_sport', array(
			array( 'title' => 'Tennis', 'meta' => array(), 'images' => array( 'image' => array( 'url' => 'https://e.test/t.jpg', 'alt' => '' ) ) ),
		) );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array() );
		$last = end( $out['rows'] );
		$this->assertSame( '1 image to fetch', $last['detail'] );
	}

	public function test_warnings_are_passed_through(): void {
		$plan = $this->plan();
		$plan->warn( 'Ignored unknown section "home/nope".' );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array() );
		$this->assertSame( array( 'Ignored unknown section "home/nope".' ), $out['warnings'] );
	}

	public function test_an_unknown_address_falls_back_to_its_raw_key(): void {
		// Defensive: a plan rehydrated from an older transient could name a
		// section the catalogue no longer has. It must still be nameable.
		$plan = Blueworx_Clubhouse_Import_Plan::from_array( array(
			'fields' => array( 'ghost' => array( 'gone' => array( 'x' => 'y' ) ) ),
		) );
		$out = Blueworx_Clubhouse_Import_Preview::summary( $plan, array() );
		$this->assertSame( 'ghost/gone', $out['rows'][0]['label'] );
	}
}
