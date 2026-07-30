<?php
// tests/php/MenuTest.php

use PHPUnit\Framework\TestCase;

final class MenuTest extends TestCase {

	private function menu( ?Blueworx_Clubhouse_Fake_Storage $storage = null ): Blueworx_Clubhouse_Menu {
		return new Blueworx_Clubhouse_Menu( $storage ?? new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function collections(): Blueworx_Clubhouse_Demo_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	private function visibility( ?Blueworx_Clubhouse_Fake_Storage $storage = null ): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( $storage ?? new Blueworx_Clubhouse_Fake_Storage() );
	}

	/** @return array<int,string> */
	private function labels( array $items ): array {
		return array_map( static fn( array $i ): string => $i['label'], $items );
	}

	public function test_an_unwritten_menu_is_todays_nine_items(): void {
		$items = $this->menu()->items( $this->collections(), $this->visibility() );
		$this->assertSame(
			array( 'Home', 'About', 'Sports', 'Teams', 'Membership', 'Events', 'Calendar', 'Contact' ),
			array_values( array_diff( $this->labels( $items ), array( 'Book a court' ) ) )
		);
	}

	public function test_a_saved_order_is_preserved(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$menu    = $this->menu( $storage );
		$menu->save( array(
			array( 'label' => 'Contact', 'target' => 'page:contact', 'children' => array() ),
			array( 'label' => 'Home',    'target' => 'page:home',    'children' => array() ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) );
		$this->assertSame( array( 'Contact', 'Home' ), $this->labels( $items ) );
	}

	public function test_a_renamed_label_is_used(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'Say hello', 'target' => 'page:contact', 'children' => array() ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) );
		$this->assertSame( array( 'Say hello' ), $this->labels( $items ) );
		$this->assertSame( Blueworx_Clubhouse_Links::url( 'contact' ), $items[0]['href'] );
	}

	public function test_children_are_resolved(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'About', 'target' => 'page:about', 'children' => array(
				array( 'label' => 'Our history', 'target' => 'anchor:about.history' ),
			) ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) );
		$this->assertCount( 1, $items );
		$this->assertSame( 'Our history', $items[0]['children'][0]['label'] );
		$this->assertStringContainsString( '#ch-about-history', $items[0]['children'][0]['href'] );
	}

	public function test_a_third_level_is_truncated_on_save(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'About', 'target' => 'page:about', 'children' => array(
				array( 'label' => 'History', 'target' => 'anchor:about.history', 'children' => array(
					array( 'label' => 'Too deep', 'target' => 'page:contact' ),
				) ),
			) ),
		) );
		$tree = $this->menu( $storage )->tree();
		$this->assertArrayNotHasKey( 'children', $tree[0]['children'][0] );
	}

	public function test_a_hidden_page_is_dropped(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_page_visible( 'contact', false );
		$this->menu( $storage )->save( array(
			array( 'label' => 'Home',    'target' => 'page:home',    'children' => array() ),
			array( 'label' => 'Contact', 'target' => 'page:contact', 'children' => array() ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $vis );
		$this->assertSame( array( 'Home' ), $this->labels( $items ) );
	}

	public function test_a_dead_target_is_dropped(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'Home',  'target' => 'page:home',           'children' => array() ),
			array( 'label' => 'Ghost', 'target' => 'filter:sports:ghost', 'children' => array() ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) );
		$this->assertSame( array( 'Home' ), $this->labels( $items ) );
	}

	public function test_a_dead_parent_with_live_children_becomes_a_heading(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'Club', 'target' => 'filter:sports:ghost', 'children' => array(
				array( 'label' => 'About', 'target' => 'page:about' ),
			) ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) );
		$this->assertCount( 1, $items );
		$this->assertSame( '', $items[0]['href'] );
		$this->assertSame( array( 'About' ), $this->labels( $items[0]['children'] ) );
	}

	public function test_a_dead_parent_with_no_live_children_is_dropped(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'Club', 'target' => 'filter:sports:ghost', 'children' => array(
				array( 'label' => 'Ghost too', 'target' => 'filter:teams:ghost' ),
			) ),
		) );
		$this->assertSame( array(), $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) ) );
	}

	public function test_an_explicitly_emptied_menu_stays_empty(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array() );
		$this->assertSame( array(), $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) ) );
	}

	public function test_an_item_with_a_blank_label_is_dropped_on_save(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => '  ', 'target' => 'page:about', 'children' => array() ),
			array( 'label' => 'Home', 'target' => 'page:home', 'children' => array() ),
		) );
		$this->assertCount( 1, $this->menu( $storage )->tree() );
	}
}
