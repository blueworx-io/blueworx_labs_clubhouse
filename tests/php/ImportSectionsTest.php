<?php
// tests/php/ImportSectionsTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportSectionsTest extends TestCase {

	private Blueworx_Clubhouse_Storage $storage;

	protected function setUp(): void {
		wp_stub_reset();
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
	}

	/** @return array<string,bool> "page.section" => visible */
	private function map( Blueworx_Clubhouse_Import_Plan $plan ): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Import_Sections::changes( $plan ) as $change ) {
			$out[ $change['page'] . '.' . $change['section'] ] = $change['visible'];
		}
		return $out;
	}

	public function test_a_section_the_file_filled_in_is_switched_on(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$this->assertTrue( $this->map( $plan )['home.hero'] );
	}

	public function test_a_section_the_file_says_nothing_about_is_switched_off(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$this->assertFalse( $this->map( $plan )['home.ticker'] );
	}

	public function test_a_section_filled_in_only_by_a_list_counts_as_covered(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'home', 'ticker', array( array( 'text' => 'Bar open' ) ) );
		$this->assertTrue( $this->map( $plan )['home.ticker'] );
	}

	public function test_a_section_filled_in_only_by_an_image_counts_as_covered(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'clubhouse', 'image', 'https://e.test/a.jpg', '', 'Global · Clubhouse band — Image' );
		$this->assertTrue( $this->map( $plan )['home.clubhouse'] );
	}

	/**
	 * The prompt encourages importing a tab at a time, so a file covering only
	 * Home must leave every About toggle exactly as the owner left it.
	 */
	public function test_pages_the_file_never_mentions_are_left_alone(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$map = $this->map( $plan );
		$this->assertArrayHasKey( 'home.news', $map );
		$this->assertArrayNotHasKey( 'about.history', $map );
	}

	/**
	 * Header and Footer are site chrome, not page content. A file that happens
	 * not to mention the header must never take the header off the site.
	 */
	public function test_the_header_and_footer_are_never_touched(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$map = $this->map( $plan );
		$this->assertArrayNotHasKey( 'home.header', $map );
		$this->assertArrayNotHasKey( 'home.footer', $map );
	}

	/** …and supplying only chrome does not count as touching the Home page. */
	public function test_supplying_only_the_header_does_not_switch_the_home_page_off(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'global', 'header', 'join', 'Join the Club' );
		$this->assertSame( array(), Blueworx_Clubhouse_Import_Sections::changes( $plan ) );
	}

	/**
	 * Sponsors, the committee and the directories render a collection and have
	 * nothing of their own to fill in — the collection is the only honest signal.
	 */
	public function test_a_collection_backed_section_follows_its_collection(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_collection( 'clubhouse_sponsor', array( array( 'title' => 'Marlow Motors', 'meta' => array(), 'images' => array() ) ) );
		$map = $this->map( $plan );
		$this->assertTrue( $map['home.sponsors'] );
		$this->assertFalse( $map['home.news'] );
	}

	public function test_an_auto_section_follows_the_collection_it_is_built_from(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_collection( 'clubhouse_event', array( array( 'title' => 'Summer Ball', 'meta' => array(), 'images' => array() ) ) );
		$map = $this->map( $plan );
		$this->assertTrue( $map['home.activity'] );
		$this->assertTrue( $map['events.upcoming'] );
	}

	// -- Writing the changes onto the club's pages -----------------------------

	/** A seeded club, so there are blocks for the import to move. */
	private function club(): Blueworx_Clubhouse_Storage {
		Blueworx_Clubhouse_Test_Site::composer( $this->storage );
		return $this->storage;
	}

	/** Whether this address's block is on the page it belongs to. */
	private function on_page( string $address ): bool {
		$page = explode( '/', $address, 2 )[0];
		$id   = ( new Blueworx_Clubhouse_Block_Library( $this->storage ) )->by_address( $address );
		return '' !== $id
			&& in_array( $id, ( new Blueworx_Clubhouse_Page_Composition( $this->storage ) )->blocks( $page ), true );
	}

	public function test_apply_moves_blocks_on_and_off_their_pages(): void {
		$this->club();
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		Blueworx_Clubhouse_Import_Sections::apply( $plan, $this->storage );

		$this->assertTrue( $this->on_page( 'home/hero' ) );
		$this->assertFalse( $this->on_page( 'home/ticker' ) );
		$this->assertTrue( $this->on_page( 'about/history' ), 'a page the file never mentioned' );
	}

	/** The block comes off the page but stays in the library, words and all. */
	public function test_a_block_taken_off_a_page_keeps_its_content(): void {
		$this->club();
		Blueworx_Clubhouse_Test_Site::write( $this->storage, 'home/ticker', array( 'items' => array( array( 'text' => 'Bar open' ) ) ) );

		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		Blueworx_Clubhouse_Import_Sections::apply( $plan, $this->storage );

		$this->assertFalse( $this->on_page( 'home/ticker' ) );
		$this->assertSame( 'Bar open', Blueworx_Clubhouse_Test_Site::items( $this->storage, 'home/ticker' )[0]['text'] );
	}

	/** A section the file fills in goes back on the page, even one the owner had removed. */
	public function test_apply_puts_a_removed_block_back_when_the_file_fills_it(): void {
		$this->club();
		Blueworx_Clubhouse_Test_Site::without( $this->storage, 'home/ticker' );

		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'home', 'ticker', array( array( 'text' => 'Friday open session' ) ) );
		Blueworx_Clubhouse_Import_Sections::apply( $plan, $this->storage );

		$this->assertTrue( $this->on_page( 'home/ticker' ) );
	}

	public function test_apply_counts_only_real_changes(): void {
		$this->club();
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );

		$first = Blueworx_Clubhouse_Import_Sections::apply( $plan, $this->storage );
		$this->assertGreaterThan( 0, $first['off'] );

		$again = Blueworx_Clubhouse_Import_Sections::apply( $plan, $this->storage );
		$this->assertSame( array( 'on' => 0, 'off' => 0 ), $again );
	}

	/** The preview must not offer to take off something the owner already removed. */
	public function test_the_preview_list_skips_blocks_that_are_already_off(): void {
		$this->club();
		Blueworx_Clubhouse_Test_Site::without( $this->storage, 'home/ticker' );

		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );

		$off = Blueworx_Clubhouse_Import_Sections::switching_off( $plan, $this->storage );
		$this->assertContains( 'Home · News', $off );
		$this->assertNotContains( 'Home · Ticker', $off );
	}

	public function test_an_empty_plan_changes_nothing(): void {
		$this->club();
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$this->assertSame( array(), Blueworx_Clubhouse_Import_Sections::changes( $plan ) );
		$this->assertSame( array( 'on' => 0, 'off' => 0 ), Blueworx_Clubhouse_Import_Sections::apply( $plan, $this->storage ) );
	}
}
