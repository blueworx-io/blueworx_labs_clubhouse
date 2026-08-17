<?php

use PHPUnit\Framework\TestCase;

/**
 * The store a club's content sat in before its site was built from blocks.
 *
 * Read-only now, and read by one thing only: the migration. So these are all
 * reads, over content laid down the way an old site held it — nothing in the
 * plugin can write here any more.
 */
final class ContentStoreTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Storage $storage;

	protected function setUp(): void {
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
	}

	private function store(): Blueworx_Clubhouse_Content_Store {
		return new Blueworx_Clubhouse_Content_Store( $this->storage );
	}

	/** @param array<string,mixed> $fields */
	private function had( string $page, string $section, array $fields ): void {
		Blueworx_Clubhouse_Test_Site::legacy_content( $this->storage, $page, $section, $fields );
	}

	public function test_get_missing_field_returns_default(): void {
		$this->assertSame( 'D', $this->store()->get( 'home', 'hero', 'heading', 'D' ) );
	}

	public function test_a_stored_field_reads_back(): void {
		$this->had( 'home', 'hero', array( 'heading' => 'Welcome' ) );
		$this->assertSame( 'Welcome', $this->store()->get( 'home', 'hero', 'heading' ) );
	}

	public function test_get_section_returns_all_fields(): void {
		$this->had( 'home', 'hero', array( 'heading' => 'Welcome', 'body' => 'Hi' ) );
		$this->assertSame(
			array(
				'heading' => 'Welcome',
				'body'    => 'Hi',
			),
			$this->store()->get_section( 'home', 'hero' )
		);
	}

	public function test_get_section_missing_returns_empty_array(): void {
		$this->assertSame( array(), $this->store()->get_section( 'home', 'nope' ) );
	}

	public function test_sections_and_pages_are_isolated(): void {
		$this->had( 'home', 'hero', array( 'heading' => 'H' ) );
		$this->had( 'about', 'hero', array( 'heading' => 'A' ) );

		$store = $this->store();
		$this->assertSame( 'H', $store->get( 'home', 'hero', 'heading' ) );
		$this->assertSame( 'A', $store->get( 'about', 'hero', 'heading' ) );
		$this->assertSame( array(), $store->get_section( 'home', 'other' ) );
	}

	public function test_get_items_defaults_to_empty_array(): void {
		$this->assertSame( array(), $this->store()->get_items( 'membership', 'faq' ) );
	}

	public function test_stored_items_read_back(): void {
		$items = array( array( 'Question' => 'Q1', 'Answer' => 'A1' ) );
		$this->had( 'membership', 'faq', array( 'items' => $items ) );
		$this->assertSame( $items, $this->store()->get_items( 'membership', 'faq' ) );
	}

	public function test_items_are_isolated_from_field_reads(): void {
		$this->had( 'home', 'hero', array( 'eyebrow' => 'Est. 1974' ) );
		$this->had( 'home', 'quick_tiles', array( 'items' => array( array( 'Label' => 'Join' ) ) ) );

		$store = $this->store();
		$this->assertSame( 'Est. 1974', $store->get( 'home', 'hero', 'eyebrow' ) );
		$this->assertCount( 1, $store->get_items( 'home', 'quick_tiles' ) );
	}
}
