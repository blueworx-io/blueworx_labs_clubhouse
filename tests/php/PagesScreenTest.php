<?php
// tests/php/PagesScreenTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * The Content → Pages screen: what an owner can see and do with a page's blocks.
 *
 * Controller and screen are tested together because the split is the point —
 * the controller decides, the screen only draws — so a test that never renders
 * would not prove the decision reached the page.
 */
final class PagesScreenTest extends TestCase {

	private function seeded(): Blueworx_Clubhouse_Fake_Storage {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Block_Seeder(
			new Blueworx_Clubhouse_Block_Library( $storage ),
			new Blueworx_Clubhouse_Page_Composition( $storage )
		) )->seed();
		return $storage;
	}

	private function model( Blueworx_Clubhouse_Fake_Storage $storage, string $page = 'about' ): array {
		return Blueworx_Clubhouse_Pages_Controller::build_model( $storage, array(), '', 'http://x.test/admin.php?page=clubhouse-pages', $page );
	}

	// -- The page list --------------------------------------------------------

	public function test_lists_every_page_the_site_serves(): void {
		$html = Blueworx_Clubhouse_Pages_Screen::render( $this->model( $this->seeded() ) );
		foreach ( array( 'Home', 'About', 'Membership', 'Contact', 'News', 'Sports', 'Teams', 'Events', 'Calendar', 'Privacy', 'Terms' ) as $label ) {
			$this->assertStringContainsString( $label, $html );
		}
	}

	public function test_an_unknown_page_falls_back_to_home(): void {
		$model = $this->model( $this->seeded(), 'not-a-page' );
		$this->assertSame( 'home', $model['current']['slug'] );
	}

	// -- The blocks on a page -------------------------------------------------

	public function test_shows_the_pages_blocks_in_render_order(): void {
		$storage = $this->seeded();
		$model   = $this->model( $storage, 'about' );

		$composer = new Blueworx_Clubhouse_Page_Composer(
			new Blueworx_Clubhouse_Block_Library( $storage ),
			new Blueworx_Clubhouse_Page_Composition( $storage )
		);
		$rendered = array_map(
			static fn( array $block ): string => (string) $block['id'],
			$composer->blocks_for( 'about' )
		);

		$this->assertSame( $rendered, array_column( $model['rows'], 'id' ) );
		$this->assertNotSame( array(), $rendered );
	}

	public function test_each_block_row_offers_edit_and_remove(): void {
		$html = Blueworx_Clubhouse_Pages_Screen::render( $this->model( $this->seeded() ) );
		$this->assertStringContainsString( 'clubhouse_pages_remove', $html );
		$this->assertStringContainsString( 'page=clubhouse-blocks', $html );
	}

	public function test_the_header_and_footer_are_pinned_and_not_removable(): void {
		$model = $this->model( $this->seeded() );
		$this->assertSame( 'Header', $model['pinned_top']['type_label'] );
		$this->assertSame( 'Footer', $model['pinned_bottom']['type_label'] );

		// They are the shell, not entries on any page's list — so nothing can take
		// them off one.
		$this->assertNotContains( 'header', array_column( $model['rows'], 'type' ) );
		$this->assertNotContains( 'footer', array_column( $model['rows'], 'type' ) );
	}

	public function test_a_block_used_on_another_page_says_where(): void {
		$storage = $this->seeded();
		$library = new Blueworx_Clubhouse_Block_Library( $storage );
		$comp    = new Blueworx_Clubhouse_Page_Composition( $storage );

		$id = $library->add( 'band', 'Shared band', '', 400 );
		$comp->add( 'about', $id );
		$comp->add( 'membership', $id );

		$row = null;
		foreach ( $this->model( $storage, 'about' )['rows'] as $candidate ) {
			if ( $candidate['id'] === $id ) {
				$row = $candidate;
			}
		}
		$this->assertNotNull( $row );
		$this->assertSame( array( 'Membership' ), $row['shared_with'] );
	}

	// -- The picker -----------------------------------------------------------

	public function test_the_picker_groups_existing_blocks_by_type_and_offers_a_new_one(): void {
		$model = $this->model( $this->seeded() );

		$groups = array();
		foreach ( $model['picker'] as $group ) {
			$groups[ $group['type'] ] = $group;
		}
		$this->assertArrayHasKey( 'band', $groups );
		$this->assertSame( 'New call to action band', $groups['band']['new_label'] );
		$this->assertNotSame( array(), $groups['band']['existing'] );
	}

	public function test_the_picker_never_offers_the_header_or_footer(): void {
		$types = array_column( $this->model( $this->seeded() )['picker'], 'type' );
		$this->assertNotContains( 'header', $types );
		$this->assertNotContains( 'footer', $types );
	}

	// -- The page switch ------------------------------------------------------

	public function test_the_page_switch_reports_what_the_front_end_does(): void {
		$storage = $this->seeded();
		( new Blueworx_Clubhouse_Visibility( $storage ) )->set_page_visible( 'about', false );
		$this->assertFalse( $this->model( $storage, 'about' )['current']['enabled'] );
	}

	// -- Saving ---------------------------------------------------------------

	public function test_switching_a_page_off_hides_it_from_the_front_end(): void {
		$storage = $this->seeded();
		Blueworx_Clubhouse_Pages_Controller::handle_post(
			array( 'clubhouse_pages_page' => 'about', 'clubhouse_pages_switch' => '1' ),
			$storage
		);
		$this->assertFalse( ( new Blueworx_Clubhouse_Visibility( $storage ) )->is_page_visible( 'about' ) );
		$this->assertFalse( ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->is_enabled( 'about' ) );
	}

	public function test_switching_a_page_back_on_shows_it_again(): void {
		$storage = $this->seeded();
		( new Blueworx_Clubhouse_Visibility( $storage ) )->set_page_visible( 'about', false );
		Blueworx_Clubhouse_Pages_Controller::handle_post(
			array( 'clubhouse_pages_page' => 'about', 'clubhouse_pages_switch' => '1', 'clubhouse_page_enabled' => '1' ),
			$storage
		);
		$this->assertTrue( ( new Blueworx_Clubhouse_Visibility( $storage ) )->is_page_visible( 'about' ) );
	}

	public function test_removing_a_block_takes_it_off_the_page_but_keeps_it(): void {
		$storage = $this->seeded();
		$comp    = new Blueworx_Clubhouse_Page_Composition( $storage );
		$id      = $comp->blocks( 'about' )[1];

		Blueworx_Clubhouse_Pages_Controller::handle_post(
			array( 'clubhouse_pages_page' => 'about', 'clubhouse_pages_remove' => $id ),
			$storage
		);

		$this->assertNotContains( $id, ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->blocks( 'about' ) );
		$this->assertTrue( ( new Blueworx_Clubhouse_Block_Library( $storage ) )->has( $id ) );
	}

	public function test_adding_an_existing_block_puts_it_on_the_page_without_copying_it(): void {
		$storage = $this->seeded();
		$comp    = new Blueworx_Clubhouse_Page_Composition( $storage );
		$before  = count( ( new Blueworx_Clubhouse_Block_Library( $storage ) )->all() );
		$id      = $comp->blocks( 'membership' )[1];

		Blueworx_Clubhouse_Pages_Controller::handle_post(
			array( 'clubhouse_pages_page' => 'about', 'clubhouse_pages_add' => 'have:' . $id ),
			$storage
		);

		$this->assertContains( $id, ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->blocks( 'about' ) );
		$this->assertCount( $before, ( new Blueworx_Clubhouse_Block_Library( $storage ) )->all() );
	}

	public function test_adding_a_new_block_creates_one_and_drops_it_in(): void {
		$storage = $this->seeded();

		Blueworx_Clubhouse_Pages_Controller::handle_post(
			array( 'clubhouse_pages_page' => 'about', 'clubhouse_pages_add' => 'new:faq' ),
			$storage
		);

		$library = new Blueworx_Clubhouse_Block_Library( $storage );
		$added   = null;
		foreach ( ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->blocks( 'about' ) as $id ) {
			$block = $library->get( $id );
			if ( 'faq' === $block['type'] ) {
				$added = $block;
			}
		}
		$this->assertNotNull( $added );
		$this->assertSame( 'About FAQ', $added['name'] );
		$this->assertSame( Blueworx_Clubhouse_Block_Types::rank( 'faq' ), $added['position'] );
	}

	public function test_a_block_id_that_is_not_in_the_library_is_ignored(): void {
		$storage = $this->seeded();
		Blueworx_Clubhouse_Pages_Controller::handle_post(
			array( 'clubhouse_pages_page' => 'about', 'clubhouse_pages_add' => 'have:no-such-block' ),
			$storage
		);
		$this->assertNotContains( 'no-such-block', ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->blocks( 'about' ) );
	}

	public function test_an_unknown_block_type_is_ignored(): void {
		$storage = $this->seeded();
		$before  = count( ( new Blueworx_Clubhouse_Block_Library( $storage ) )->all() );
		Blueworx_Clubhouse_Pages_Controller::handle_post(
			array( 'clubhouse_pages_page' => 'about', 'clubhouse_pages_add' => 'new:not_a_type' ),
			$storage
		);
		$this->assertCount( $before, ( new Blueworx_Clubhouse_Block_Library( $storage ) )->all() );
	}

	public function test_a_post_naming_a_page_the_site_does_not_serve_changes_nothing(): void {
		$storage = $this->seeded();
		Blueworx_Clubhouse_Pages_Controller::handle_post(
			array( 'clubhouse_pages_page' => 'wp-config', 'clubhouse_pages_add' => 'new:faq' ),
			$storage
		);
		$this->assertSame( array(), ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->blocks( 'wp-config' ) );
	}

	// -- Output hygiene, as every other screen keeps ---------------------------

	public function test_escapes_a_block_name(): void {
		$storage = $this->seeded();
		$library = new Blueworx_Clubhouse_Block_Library( $storage );
		$id      = $library->add( 'band', '<script>alert(1)</script>', '', 400 );
		( new Blueworx_Clubhouse_Page_Composition( $storage ) )->add( 'about', $id );

		$html = Blueworx_Clubhouse_Pages_Screen::render( $this->model( $storage, 'about' ) );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_no_inline_styles(): void {
		$html = Blueworx_Clubhouse_Pages_Screen::render( $this->model( $this->seeded() ) );
		$this->assertStringNotContainsString( ' style="', $html );
	}
}
