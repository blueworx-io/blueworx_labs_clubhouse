<?php

use PHPUnit\Framework\TestCase;

/**
 * Visibility is not a setting. A switch on the Visibility tab IS its page's
 * status, so publishing that page from WordPress's own Pages list has to make
 * the switch read as on — there is one fact, not two copies of it that can
 * come to disagree with no way to tell which is right.
 */
final class SetupPageControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	private function bridge(): Blueworx_Clubhouse_Setup_Storage {
		return new Blueworx_Clubhouse_Setup_Storage( new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function given_a_club_page( string $page, int $post_id, string $status ): void {
		$slug = (string) Blueworx_Clubhouse_Club_Pages::slug_for_page_key( $page );
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( $slug ), $post_id );
		$GLOBALS['wp_stub_post_status'][ $post_id ] = $status;
	}

	public function test_a_drafted_page_reads_as_off(): void {
		$this->given_a_club_page( 'contact', 42, 'draft' );

		$this->assertFalse( $this->bridge()->read()['page_visible_contact'] );
	}

	public function test_a_published_page_reads_as_on(): void {
		$this->given_a_club_page( 'contact', 42, 'publish' );

		$this->assertTrue( $this->bridge()->read()['page_visible_contact'] );
	}

	/**
	 * The bug this guards: the stored flag says hidden, somebody publishes the
	 * page from the Pages list, and the switch goes on saying hidden.
	 */
	public function test_publishing_the_page_elsewhere_turns_the_switch_on(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->given_a_club_page( 'contact', 42, 'publish' );
		// The stored flag, left saying the opposite.
		$storage->set( 'visibility', array( 'pages' => array( 'contact' => false ) ) );

		$values = ( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->read();

		$this->assertTrue( $values['page_visible_contact'] );
	}

	/** Home's key is 'home' and its slug is '' — never a truthiness check. */
	public function test_home_reads_its_own_page(): void {
		$this->given_a_club_page( 'home', 7, 'draft' );

		$this->assertFalse( $this->bridge()->read()['page_visible_home'] );
	}

	public function test_a_site_whose_pages_are_not_created_yet_falls_back_to_the_stored_flag(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Visibility( $storage ) )->set_page_visible( 'contact', false );

		$this->assertFalse( ( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->read()['page_visible_contact'] );
	}

	public function test_a_never_saved_page_with_no_page_behind_it_reads_as_on(): void {
		$this->assertTrue( $this->bridge()->read()['page_visible_contact'] );
	}

	public function test_switching_a_page_off_drafts_the_page_behind_it(): void {
		$this->given_a_club_page( 'contact', 42, 'publish' );

		$this->bridge()->write( array( 'page_visible_contact' => false ) );

		$updates = wp_stub_calls( 'wp_update_post' );
		$this->assertCount( 1, $updates );
		$this->assertSame( 42, $updates[0]['args'][0]['ID'] );
		$this->assertSame( 'draft', $updates[0]['args'][0]['post_status'] );
	}
}
