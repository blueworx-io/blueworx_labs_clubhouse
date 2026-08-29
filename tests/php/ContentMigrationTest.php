<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ContentMigrationTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Storage $storage;

	protected function setUp(): void {
		$GLOBALS['wp_stub_postmeta'] = array();
		$GLOBALS['wp_stub_options']  = array();
		$this->storage               = new Blueworx_Clubhouse_Fake_Storage();
		update_option( 'clubhouse_page_id_home', 42 );
		update_option( 'clubhouse_page_id_about', 43 );
	}

	public function test_a_field_arrives_at_its_new_address(): void {
		$this->storage->set( 'content_home', array( 'hero' => array( 'title_lead' => 'Crewe Vagrants' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( 'Crewe Vagrants', $GLOBALS['wp_stub_postmeta'][42]['page_hero_title_lead'] );
	}

	public function test_rows_arrive_as_one_value(): void {
		$rows = array( array( 'text' => 'Match on Saturday' ) );
		$this->storage->set( 'content_home', array( 'ticker' => array( 'items' => $rows ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( $rows, $GLOBALS['wp_stub_postmeta'][42]['page_ticker_items'] );
	}

	public function test_a_switch_keeps_its_state_and_its_type(): void {
		$this->storage->set( 'visibility', array( 'sections' => array( 'home.social_feed' => false ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$content = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertFalse( $content->is_section_shown( 'home', 'social_feed' ) );
	}

	public function test_a_section_nobody_touched_arrives_shown(): void {
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$content = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertTrue( $content->is_section_shown( 'home', 'hero' ) );
	}

	public function test_global_content_goes_to_its_own_option(): void {
		$this->storage->set( 'content_global', array( 'header' => array( 'join' => 'Join us' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( 'Join us', $this->storage->get( 'global_content', array() )['header_join'] );
	}

	public function test_a_field_never_saved_is_not_written_at_all(): void {
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertArrayNotHasKey( 'page_hero_title_lead', $GLOBALS['wp_stub_postmeta'][42] ?? array() );
	}

	/**
	 * Image fields have held two shapes: an attachment id, and a raw URL from
	 * a demo or a preview. The media kind is an integer, so a raw URL would
	 * cast to 0 and the picture would vanish. Anything that cannot be resolved
	 * to an attachment is left where it is and named in the report.
	 */
	public function test_an_image_that_is_not_an_attachment_is_reported_and_not_written(): void {
		$this->storage->set( 'content_home', array( 'clubhouse' => array( 'image' => 'https://example.test/x.jpg' ) ) );
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertContains( 'home/clubhouse/image', $result['skipped'] );
		$this->assertArrayNotHasKey( 'page_clubhouse_image', $GLOBALS['wp_stub_postmeta'][42] ?? array() );
	}

	public function test_a_page_with_no_post_behind_it_is_reported(): void {
		delete_option( 'clubhouse_page_id_about' );
		$this->storage->set( 'content_about', array( 'hero' => array( 'title_lead' => 'About us' ) ) );
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertContains( 'about/hero/title_lead', $result['skipped'] );
	}

	public function test_running_twice_changes_nothing_the_second_time(): void {
		$this->storage->set( 'content_home', array( 'hero' => array( 'title_lead' => 'Crewe Vagrants' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$first  = $GLOBALS['wp_stub_postmeta'];
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( $first, $GLOBALS['wp_stub_postmeta'] );
	}

	public function test_the_old_option_is_left_alone(): void {
		$this->storage->set( 'content_home', array( 'hero' => array( 'title_lead' => 'Crewe Vagrants' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertNotSame( array(), $this->storage->get( 'content_home', array() ) );
	}
}
