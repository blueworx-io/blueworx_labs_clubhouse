<?php

use PHPUnit\Framework\TestCase;

/**
 * The Setup screen's own read and write.
 *
 * Setup's values were never one option — they live in the look registry,
 * Branding, Visibility, Menu, Profile_Store, Auth_Settings, Mail_Settings and
 * Demo_State. This is the bridge between those and the one flat map of field
 * ids the page editor library hands about, so nothing had to move in the
 * database for phase 4.
 */
final class SetupStorageTest extends TestCase {

	private function storage(): Blueworx_Clubhouse_Fake_Storage {
		return new Blueworx_Clubhouse_Fake_Storage();
	}

	private function bridge( Blueworx_Clubhouse_Storage $storage ): Blueworx_Clubhouse_Setup_Storage {
		return new Blueworx_Clubhouse_Setup_Storage( $storage );
	}

	// ---- read ----

	public function test_it_reads_branding_from_the_branding_store(): void {
		$storage = $this->storage();
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_club_name( 'Ashwood RFC' );

		$this->assertSame( 'Ashwood RFC', $this->bridge( $storage )->read()['club_name'] );
	}

	public function test_it_reads_the_active_look(): void {
		$storage = $this->storage();
		Blueworx_Clubhouse_Frontend::registry( $storage )->set_active( 'floodlight' );

		$this->assertSame( 'floodlight', $this->bridge( $storage )->read()['look'] );
	}

	public function test_it_reads_where_members_land_and_who_email_comes_from(): void {
		$storage = $this->storage();
		( new Blueworx_Clubhouse_Auth_Settings( $storage ) )->set_post_login( 'page:home' );
		( new Blueworx_Clubhouse_Mail_Settings( $storage ) )->set_from_name( 'Ashwood RFC' );

		$values = $this->bridge( $storage )->read();

		$this->assertSame( 'page:home', $values['post_login'] );
		$this->assertSame( 'Ashwood RFC', $values['mail_from_name'] );
	}

	public function test_a_never_saved_page_switch_reads_as_on(): void {
		$this->assertTrue( $this->bridge( $this->storage() )->read()['page_visible_home'] );
	}

	public function test_a_page_switched_off_reads_as_off(): void {
		$storage = $this->storage();
		( new Blueworx_Clubhouse_Visibility( $storage ) )->set_page_visible( 'about', false );

		$this->assertFalse( $this->bridge( $storage )->read()['page_visible_about'] );
	}

	public function test_the_menu_reads_as_flat_rows_with_a_nested_flag(): void {
		$storage = $this->storage();
		( new Blueworx_Clubhouse_Menu( $storage ) )->save(
			array(
				array(
					'label'    => 'About',
					'target'   => 'page:about',
					'children' => array( array( 'label' => 'History', 'target' => 'page:about' ) ),
				),
			)
		);

		$this->assertSame(
			array(
				array( 'label' => 'About',   'target' => 'page:about', 'nested' => false ),
				array( 'label' => 'History', 'target' => 'page:about', 'nested' => true ),
			),
			$this->bridge( $storage )->read()['menu']
		);
	}

	public function test_it_reads_the_clubs_own_member_questions(): void {
		$storage = $this->storage();
		( new Blueworx_Clubhouse_Profile_Store( $storage ) )->save_fields(
			array( array( 'key' => 'shirt', 'label' => 'Shirt size', 'type' => 'text' ) )
		);

		$rows = $this->bridge( $storage )->read()['profile_fields'];

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Shirt size', $rows[0]['label'] );
	}

	// ---- write ----

	public function test_writing_a_look_and_an_accent_lands_where_they_belong(): void {
		$storage = $this->storage();

		$this->bridge( $storage )->write( array( 'look' => 'floodlight', 'accent' => '#c6f24e' ) );

		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Frontend::registry( $storage )->active()->slug() );
		$this->assertSame( '#c6f24e', ( new Blueworx_Clubhouse_Branding( $storage ) )->get_accent() );
	}

	public function test_a_look_this_site_does_not_have_is_ignored(): void {
		$storage = $this->storage();
		Blueworx_Clubhouse_Frontend::registry( $storage )->set_active( 'court-side' );

		$this->bridge( $storage )->write( array( 'look' => 'not-a-look' ) );

		$this->assertSame( 'court-side', Blueworx_Clubhouse_Frontend::registry( $storage )->active()->slug() );
	}

	public function test_a_page_switch_writes_through_visibility(): void {
		$storage = $this->storage();

		$this->bridge( $storage )->write( array( 'page_visible_about' => false ) );

		$this->assertFalse( ( new Blueworx_Clubhouse_Visibility( $storage ) )->is_page_visible( 'about' ) );
	}

	public function test_a_nested_row_becomes_a_child_of_the_row_above_it(): void {
		$storage = $this->storage();

		$this->bridge( $storage )->write(
			array(
				'menu' => array(
					array( 'label' => 'About',   'target' => 'page:about', 'nested' => false ),
					array( 'label' => 'History', 'target' => 'page:about', 'nested' => true ),
				),
			)
		);

		$tree = ( new Blueworx_Clubhouse_Menu( $storage ) )->tree();
		$this->assertCount( 1, $tree );
		$this->assertSame( 'History', $tree[0]['children'][0]['label'] );
	}

	public function test_a_leading_nested_row_becomes_a_top_level_item(): void {
		$storage = $this->storage();

		$this->bridge( $storage )->write(
			array( 'menu' => array( array( 'label' => 'Orphan', 'target' => 'page:home', 'nested' => true ) ) )
		);

		$tree = ( new Blueworx_Clubhouse_Menu( $storage ) )->tree();
		$this->assertSame( 'Orphan', $tree[0]['label'] );
		$this->assertSame( array(), $tree[0]['children'] );
	}

	public function test_the_menu_survives_a_round_trip(): void {
		$storage = $this->storage();
		$rows    = $this->bridge( $storage )->read()['menu'];

		$this->bridge( $storage )->write( array( 'menu' => $rows ) );

		$this->assertSame( $rows, $this->bridge( $storage )->read()['menu'] );
	}

	public function test_removing_a_member_question_leaves_answers_already_given_alone(): void {
		wp_stub_reset();
		$storage = $this->storage();
		$profile = new Blueworx_Clubhouse_Profile_Store( $storage );
		$profile->save_fields( array( array( 'key' => 'shirt', 'label' => 'Shirt size', 'type' => 'text' ) ) );
		$profile->save_answers( 7, array( 'shirt' => 'Large' ) );

		$this->bridge( $storage )->write( array( 'profile_fields' => array() ) );

		$this->assertSame( array(), $profile->fields() );
		$this->assertSame( 'Large', get_user_meta( 7, $profile->meta_key( 'shirt' ), true ) );
		$this->assertSame( array(), wp_stub_calls( 'delete_metadata' ) );
	}

	public function test_a_value_the_screen_did_not_send_is_left_alone(): void {
		$storage = $this->storage();
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_club_name( 'Ashwood RFC' );

		$this->bridge( $storage )->write( array( 'accent' => '#c6f24e' ) );

		$this->assertSame( 'Ashwood RFC', ( new Blueworx_Clubhouse_Branding( $storage ) )->get_club_name() );
	}

	/**
	 * A Content Editor's save carries the menu and nothing else — the library
	 * has already dropped every field they may not write. Demo mode must not
	 * be switched off by that save.
	 */
	public function test_a_menu_only_save_does_not_touch_demo_mode(): void {
		$storage = $this->storage();
		( new Blueworx_Clubhouse_Demo_State( $storage ) )->set( true );

		$this->bridge( $storage )->write( array( 'menu' => array() ) );

		$this->assertTrue( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}
}
