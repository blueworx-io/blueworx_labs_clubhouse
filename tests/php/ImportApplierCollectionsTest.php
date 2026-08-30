<?php
// tests/php/ImportApplierCollectionsTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportApplierCollectionsTest extends TestCase {

	private Blueworx_Clubhouse_Storage $storage;

	protected function setUp(): void {
		wp_stub_reset();
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
	}

	/** @param array<int,array<string,mixed>> $items */
	private function plan_with( string $type, array $items ): Blueworx_Clubhouse_Import_Plan {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_collection( $type, $items );
		return $plan;
	}

	private function item( string $title, array $meta = array(), array $images = array() ): array {
		return array( 'title' => $title, 'meta' => $meta, 'images' => $images );
	}

	public function test_a_new_item_is_created_with_its_meta(): void {
		$plan = $this->plan_with( 'clubhouse_sport', array( $this->item( 'Squash', array( 'subtitle' => 'Two courts' ) ) ) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$insert = wp_stub_calls( 'wp_insert_post' )[0]['args'][0];
		$this->assertSame( 'clubhouse_sport', $insert['post_type'] );
		$this->assertSame( 'Squash', $insert['post_title'] );
		$this->assertSame( 'publish', $insert['post_status'] );

		// At the address the collection's own editor reads, not the bare field
		// name it used to be — an import that wrote the old one would leave a
		// club's imported season invisible on every screen.
		$meta = wp_stub_calls( 'update_post_meta' );
		$this->assertSame(
			Blueworx_Clubhouse_Collection_Meta::meta_key( 'clubhouse_sport', 'subtitle' ),
			$meta[0]['args'][1]
		);
		$this->assertSame( 'Two courts', $meta[0]['args'][2] );
	}

	public function test_a_marked_demo_post_is_deleted(): void {
		wp_stub_add_post( 'clubhouse_sport', 11, 'Rugby', array( Blueworx_Clubhouse_Collection_Seeder::DEMO_META => '1' ) );
		$plan = $this->plan_with( 'clubhouse_sport', array( $this->item( 'Squash' ) ) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$deletes = wp_stub_calls( 'wp_delete_post' );
		$this->assertSame( 11, $deletes[0]['args'][0] );
		$this->assertTrue( $deletes[0]['args'][1], 'demo posts should be force-deleted, not trashed' );
	}

	public function test_an_unmarked_post_whose_title_matches_demo_content_is_also_deleted(): void {
		// Installs seeded before the marker existed have no _clubhouse_demo meta
		// on any post of this type — the precondition the title fallback requires.
		wp_stub_add_post( 'clubhouse_sport', 12, 'Rugby' );
		$plan = $this->plan_with( 'clubhouse_sport', array( $this->item( 'Squash' ) ) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( 12, wp_stub_calls( 'wp_delete_post' )[0]['args'][0] );
	}

	public function test_a_marked_demo_post_present_stops_the_title_fallback(): void {
		// Once the type has even one marked post, it is known to be post-marker,
		// so title-matching must not run — otherwise a club's own real post that
		// happens to share a seeded demo title (e.g. their own "Tennis" section)
		// would be deleted right alongside the actual demo entry.
		wp_stub_add_post( 'clubhouse_sport', 40, 'Rugby', array( Blueworx_Clubhouse_Collection_Seeder::DEMO_META => '1' ) );
		wp_stub_add_post( 'clubhouse_sport', 41, 'Tennis' ); // unmarked, owner's own, shares a demo title
		$plan = $this->plan_with( 'clubhouse_sport', array( $this->item( 'Squash' ) ) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$deletes = wp_stub_calls( 'wp_delete_post' );
		$this->assertCount( 1, $deletes );
		$this->assertSame( 40, $deletes[0]['args'][0] );
	}

	public function test_a_real_post_is_kept(): void {
		wp_stub_add_post( 'clubhouse_sport', 13, 'Korfball' );
		$plan = $this->plan_with( 'clubhouse_sport', array( $this->item( 'Squash' ) ) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( array(), wp_stub_calls( 'wp_delete_post' ) );
	}

	public function test_two_new_items_sharing_a_title_update_the_second_instead_of_duplicating(): void {
		// $by_title must learn about a freshly-inserted post immediately, or a
		// second item with the same title creates a duplicate that can never be
		// reached again by name.
		$plan = $this->plan_with( 'clubhouse_sport', array(
			$this->item( 'Squash', array( 'subtitle' => 'First' ) ),
			$this->item( 'Squash', array( 'subtitle' => 'Second' ) ),
		) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$this->assertCount( 1, wp_stub_calls( 'wp_insert_post' ) );
		$this->assertCount( 1, wp_stub_calls( 'wp_update_post' ) );
		$meta = wp_stub_calls( 'update_post_meta' );
		$this->assertSame( 'Second', end( $meta )['args'][2] );
	}

	public function test_a_real_post_with_the_same_title_is_updated_not_duplicated(): void {
		wp_stub_add_post( 'clubhouse_sport', 14, 'Squash' );
		$plan = $this->plan_with( 'clubhouse_sport', array( $this->item( 'Squash', array( 'subtitle' => 'Now three courts' ) ) ) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$this->assertSame( array(), wp_stub_calls( 'wp_insert_post' ) );
		$meta = wp_stub_calls( 'update_post_meta' );
		$this->assertSame( 14, $meta[0]['args'][0] );
		$this->assertSame( 'Now three courts', $meta[0]['args'][2] );
	}

	public function test_a_type_the_file_does_not_mention_is_left_alone(): void {
		wp_stub_add_post( 'clubhouse_team', 20, '1st XV', array( Blueworx_Clubhouse_Collection_Seeder::DEMO_META => '1' ) );
		$plan = $this->plan_with( 'clubhouse_sport', array( $this->item( 'Squash' ) ) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );
		$this->assertSame( array(), wp_stub_calls( 'wp_delete_post' ) );
	}

	public function test_a_collection_image_is_sideloaded_and_stored_as_an_id(): void {
		$plan = $this->plan_with( 'clubhouse_sport', array(
			$this->item( 'Squash', array(), array( 'image' => array( 'url' => 'https://e.test/s.jpg', 'alt' => 'Court' ) ) ),
		) );
		Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$this->assertSame( 'https://e.test/s.jpg', wp_stub_calls( 'media_sideload_image' )[0]['args'][0] );
		$image_key  = Blueworx_Clubhouse_Collection_Meta::meta_key( 'clubhouse_sport', 'image' );
		$image_meta = array_values( array_filter( wp_stub_calls( 'update_post_meta' ), static fn( $c ) => $image_key === $c['args'][1] ) );
		$this->assertSame( 500, $image_meta[0]['args'][2] );
	}

	public function test_a_failed_collection_image_warns_but_still_creates_the_item(): void {
		wp_stub_fail_sideload( 'https://e.test/gone.jpg' );
		$plan = $this->plan_with( 'clubhouse_sport', array(
			$this->item( 'Squash', array(), array( 'image' => array( 'url' => 'https://e.test/gone.jpg', 'alt' => '' ) ) ),
		) );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$this->assertSame( 'Squash', wp_stub_calls( 'wp_insert_post' )[0]['args'][0]['post_title'] );
		$this->assertStringContainsString( 'https://e.test/gone.jpg', $out['warnings'][0] );
	}

	public function test_the_result_reports_creates_updates_and_deletes(): void {
		wp_stub_add_post( 'clubhouse_sport', 15, 'Rugby', array( Blueworx_Clubhouse_Collection_Seeder::DEMO_META => '1' ) );
		wp_stub_add_post( 'clubhouse_sport', 16, 'Korfball' );
		$plan = $this->plan_with( 'clubhouse_sport', array(
			$this->item( 'Korfball' ),
			$this->item( 'Squash' ),
		) );
		$out = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$row = $out['rows'][0];
		$this->assertSame( 'Sports', $row['label'] );
		$this->assertSame( '1 created, 1 updated, 1 demo entry removed', $row['detail'] );
	}

	public function test_a_failed_insert_warns_and_is_not_counted_as_created(): void {
		wp_stub_fail_insert( 'Squash' );
		$plan = $this->plan_with( 'clubhouse_sport', array( $this->item( 'Squash' ) ) );
		$out  = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$this->assertStringContainsString( 'Squash', $out['warnings'][0] );
		$this->assertSame( '', $out['rows'][0]['detail'] );
	}

	public function test_a_failed_update_warns_and_is_not_counted_as_updated(): void {
		wp_stub_add_post( 'clubhouse_sport', 21, 'Squash' );
		wp_stub_fail_update( 21 );
		$plan = $this->plan_with( 'clubhouse_sport', array( $this->item( 'Squash', array( 'subtitle' => 'Two courts' ) ) ) );
		$out  = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$this->assertStringContainsString( 'Squash', $out['warnings'][0] );
		$this->assertSame( '', $out['rows'][0]['detail'] );
		$this->assertSame( array(), wp_stub_calls( 'update_post_meta' ) );
	}

	public function test_a_failed_delete_is_not_counted_as_removed(): void {
		wp_stub_add_post( 'clubhouse_sport', 22, 'Rugby', array( Blueworx_Clubhouse_Collection_Seeder::DEMO_META => '1' ) );
		wp_stub_fail_delete( 22 );
		$plan = $this->plan_with( 'clubhouse_sport', array( $this->item( 'Squash' ) ) );
		$out  = Blueworx_Clubhouse_Import_Applier::apply( $plan, $this->storage );

		$this->assertSame( 22, wp_stub_calls( 'wp_delete_post' )[0]['args'][0], 'the delete must still be attempted' );
		$this->assertSame( '1 created', $out['rows'][0]['detail'] );
	}

	public function test_demo_counts_reports_per_type_totals(): void {
		// A marked post is present, so this type is known post-marker: the
		// unmarked "Tennis" post is the owner's own and must not be counted,
		// even though it happens to share a seeded demo title.
		wp_stub_add_post( 'clubhouse_sport', 17, 'Rugby', array( Blueworx_Clubhouse_Collection_Seeder::DEMO_META => '1' ) );
		wp_stub_add_post( 'clubhouse_sport', 18, 'Tennis' ); // unmarked, real, coincidentally a demo title
		wp_stub_add_post( 'clubhouse_sport', 19, 'Korfball' ); // real
		$counts = Blueworx_Clubhouse_Import_Applier::demo_counts( array( 'clubhouse_sport', 'clubhouse_team' ) );
		$this->assertSame( 1, $counts['clubhouse_sport'] );
		$this->assertSame( 0, $counts['clubhouse_team'] );
	}

	public function test_demo_counts_falls_back_to_titles_when_nothing_is_marked(): void {
		// No post of this type carries the marker, so it is a pre-marker install
		// and the title fallback is the only signal available.
		wp_stub_add_post( 'clubhouse_sport', 43, 'Rugby' );
		wp_stub_add_post( 'clubhouse_sport', 44, 'Tennis' );
		wp_stub_add_post( 'clubhouse_sport', 45, 'Korfball' ); // not a demo title
		$counts = Blueworx_Clubhouse_Import_Applier::demo_counts( array( 'clubhouse_sport' ) );
		$this->assertSame( 2, $counts['clubhouse_sport'] );
	}
}
