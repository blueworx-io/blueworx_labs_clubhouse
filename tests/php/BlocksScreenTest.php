<?php
// tests/php/BlocksScreenTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * The Content → Blocks screen: the library, and the form that edits one block.
 *
 * The promises being tested are the ones an owner would notice breaking — edit
 * once and every page follows, duplicate to break the link, and never delete a
 * block that is in use without being told which pages go with it.
 */
final class BlocksScreenTest extends TestCase {

	private function seeded(): Blueworx_Clubhouse_Fake_Storage {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Block_Seeder(
			new Blueworx_Clubhouse_Block_Library( $storage ),
			new Blueworx_Clubhouse_Page_Composition( $storage )
		) )->seed();
		return $storage;
	}

	/**
	 * A club that has shared one block across two pages — the About page's
	 * history shown on Membership as well, which is exactly what the Pages
	 * screen's picker makes easy. The shipped site has no shared block of its
	 * own yet, so the one being tested is made here rather than assumed.
	 */
	private function shared(): Blueworx_Clubhouse_Fake_Storage {
		$storage = $this->seeded();
		( new Blueworx_Clubhouse_Page_Composition( $storage ) )->add( 'membership', 'about-history' );
		return $storage;
	}

	private function model( Blueworx_Clubhouse_Storage $storage, string $block = '', ?array $confirm = null ): array {
		return Blueworx_Clubhouse_Blocks_Controller::build_model(
			$storage,
			array(),
			'',
			'http://x.test/admin.php?page=clubhouse-blocks',
			$block,
			$confirm
		);
	}

	private function post( array $post, Blueworx_Clubhouse_Storage $storage ): array {
		return Blueworx_Clubhouse_Blocks_Controller::handle_post( $post, $storage );
	}

	// -- The library ----------------------------------------------------------

	public function test_groups_the_library_by_kind(): void {
		$model  = $this->model( $this->seeded() );
		$labels = array_column( $model['groups'], 'label' );
		$this->assertContains( 'Header', $labels );
		$this->assertContains( 'Hero', $labels );
		$this->assertNotContains( '', $labels );
	}

	public function test_each_block_says_which_pages_use_it(): void {
		$storage = $this->shared();
		$rows    = array();
		foreach ( $this->model( $storage )['groups'] as $group ) {
			foreach ( $group['blocks'] as $block ) {
				$rows[ $block['id'] ] = $block['used_on'];
			}
		}
		$this->assertSame( array( 'About' ), $rows['about-hero'] );
		$this->assertSame( array( 'About', 'Membership' ), $rows['about-history'] );
	}

	public function test_a_shared_block_says_so_before_the_fields(): void {
		$html = Blueworx_Clubhouse_Blocks_Screen::render( $this->model( $this->shared(), 'about-history' ) );
		$this->assertStringContainsString( 'Shared', $html );
		$this->assertStringContainsString( 'About, Membership', $html );
	}

	public function test_an_empty_state_when_no_block_is_chosen(): void {
		$html = Blueworx_Clubhouse_Blocks_Screen::render( $this->model( $this->seeded() ) );
		$this->assertStringContainsString( 'Pick a block to edit', $html );
	}

	// -- Editing --------------------------------------------------------------

	public function test_saving_words_puts_them_on_the_block(): void {
		$storage = $this->seeded();
		$this->post(
			array(
				'clubhouse_blocks_block' => 'about-history',
				'field'                  => array( 'heading' => 'Ninety years by the weir' ),
			),
			$storage
		);
		$block = ( new Blueworx_Clubhouse_Block_Library( $storage ) )->get( 'about-history' );
		$this->assertSame( 'Ninety years by the weir', $block['content']['heading'] );
	}

	public function test_editing_a_shared_block_says_which_pages_changed(): void {
		$storage = $this->shared();
		$result  = $this->post(
			array( 'clubhouse_blocks_block' => 'about-history', 'field' => array( 'heading' => 'Join us' ) ),
			$storage
		);
		$this->assertStringContainsString( 'About and Membership', $result['notices'][0]['text'] );
	}

	public function test_a_key_the_block_does_not_have_never_reaches_storage(): void {
		$storage = $this->seeded();
		$this->post(
			array(
				'clubhouse_blocks_block' => 'about-history',
				'field'                  => array( 'heading' => 'Fine', 'not_a_field' => 'smuggled' ),
			),
			$storage
		);
		$block = ( new Blueworx_Clubhouse_Block_Library( $storage ) )->get( 'about-history' );
		$this->assertArrayNotHasKey( 'not_a_field', $block['content'] );
	}

	public function test_renaming_keeps_the_block_on_its_pages(): void {
		$storage = $this->seeded();
		$before  = ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->uses( 'about-hero' );

		$this->post(
			array( 'clubhouse_blocks_block' => 'about-hero', 'clubhouse_blocks_name' => 'Our welcome' ),
			$storage
		);

		$this->assertSame( 'Our welcome', ( new Blueworx_Clubhouse_Block_Library( $storage ) )->get( 'about-hero' )['name'] );
		$this->assertSame( $before, ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->uses( 'about-hero' ) );
	}

	/**
	 * Two editors, one site. Until the old path goes (issue #207), Club Pages and
	 * Setup still project the content store onto the blocks on every save — so a
	 * block edited here has to reach that store too, or the next save anywhere
	 * else would quietly put the old words back.
	 */
	public function test_words_saved_here_survive_a_club_pages_save(): void {
		$storage = $this->seeded();
		$this->post(
			array( 'clubhouse_blocks_block' => 'about-history', 'field' => array( 'heading' => 'Ninety years by the weir' ) ),
			$storage
		);

		// Somebody now saves the old screen, which reprojects the store.
		( new Blueworx_Clubhouse_Block_Seeder(
			new Blueworx_Clubhouse_Block_Library( $storage ),
			new Blueworx_Clubhouse_Page_Composition( $storage )
		) )->sync(
			new Blueworx_Clubhouse_Content_Store( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage )
		);

		$this->assertSame(
			'Ninety years by the weir',
			( new Blueworx_Clubhouse_Block_Library( $storage ) )->get( 'about-history' )['content']['heading']
		);
	}

	public function test_repeated_items_survive_a_club_pages_save(): void {
		$storage = $this->seeded();
		$this->post(
			array(
				'clubhouse_blocks_block' => 'membership-faq',
				'item'                   => array( array( 'question' => 'When do you train?', 'answer' => 'Tuesdays.' ) ),
			),
			$storage
		);

		( new Blueworx_Clubhouse_Block_Seeder(
			new Blueworx_Clubhouse_Block_Library( $storage ),
			new Blueworx_Clubhouse_Page_Composition( $storage )
		) )->sync(
			new Blueworx_Clubhouse_Content_Store( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage )
		);

		$items = ( new Blueworx_Clubhouse_Block_Library( $storage ) )->get( 'membership-faq' )['content']['items'];
		$this->assertCount( 1, $items );
		$this->assertSame( 'When do you train?', $items[0]['question'] );
	}

	/**
	 * The home hero's quick tiles are stored at their own address and folded onto
	 * the hero block, so writing back has to unfold them again.
	 */
	public function test_folded_items_go_back_to_the_address_they_came_from(): void {
		$storage = $this->seeded();
		$this->post(
			array(
				'clubhouse_blocks_block' => 'home-hero',
				'item'                   => array( array( 'label' => 'Join the club' ) ),
			),
			$storage
		);

		$this->assertSame(
			'Join the club',
			( new Blueworx_Clubhouse_Content_Store( $storage ) )->get_items( 'home', 'quick_tiles' )[0]['label']
		);
	}

	/** The cookie notice is stored on its own, but edited on the footer block. */
	public function test_the_cookie_notice_goes_back_to_the_cookie_notice(): void {
		$storage = $this->seeded();
		$this->post(
			array( 'clubhouse_blocks_block' => 'footer', 'field' => array( 'cookie_text' => 'We use a couple of cookies.' ) ),
			$storage
		);

		$this->assertSame(
			'We use a couple of cookies.',
			( new Blueworx_Clubhouse_Content_Store( $storage ) )->get( 'global', 'cookies', 'text' )
		);
	}

	// -- Repeated items -------------------------------------------------------

	public function test_adding_and_removing_a_repeated_item(): void {
		$storage = $this->seeded();
		$library = new Blueworx_Clubhouse_Block_Library( $storage );

		$this->post(
			array(
				'clubhouse_blocks_block'    => 'membership-faq',
				'item'                      => array( array( 'question' => 'When do you train?' ) ),
				'clubhouse_blocks_add_item' => '1',
			),
			$storage
		);
		$this->assertCount( 2, $library->get( 'membership-faq' )['content']['items'] );

		$this->post(
			array(
				'clubhouse_blocks_block'       => 'membership-faq',
				'item'                         => array( array( 'question' => 'a' ), array( 'question' => 'b' ) ),
				'clubhouse_blocks_remove_item' => '0',
			),
			$storage
		);
		$items = $library->get( 'membership-faq' )['content']['items'];
		$this->assertCount( 1, $items );
		$this->assertSame( 'b', $items[0]['question'] );
	}

	// -- Duplicate ------------------------------------------------------------

	public function test_duplicating_breaks_the_link_between_two_pages(): void {
		$storage = $this->shared();
		$library = new Blueworx_Clubhouse_Block_Library( $storage );

		$this->post(
			array( 'clubhouse_blocks_block' => 'about-history', 'field' => array( 'heading' => 'Shared words' ) ),
			$storage
		);
		$result = $this->post( array( 'clubhouse_blocks_block' => 'about-history', 'clubhouse_blocks_duplicate' => '1' ), $storage );

		$copy = $library->get( $result['block'] );
		$this->assertNotSame( 'about-history', $result['block'] );
		$this->assertSame( 'Shared words', $copy['content']['heading'] );

		// Editing the copy leaves the original where it was.
		$this->post( array( 'clubhouse_blocks_block' => $result['block'], 'field' => array( 'heading' => 'Its own words' ) ), $storage );
		$this->assertSame( 'Shared words', $library->get( 'about-history' )['content']['heading'] );
	}

	public function test_a_copy_is_on_no_page_until_it_is_put_on_one(): void {
		$storage = $this->seeded();
		$result  = $this->post( array( 'clubhouse_blocks_block' => 'about-hero', 'clubhouse_blocks_duplicate' => '1' ), $storage );
		$this->assertSame( array(), ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->uses( $result['block'] ) );
	}

	// -- Delete ---------------------------------------------------------------

	public function test_deleting_a_block_in_use_asks_first_and_names_the_pages(): void {
		$storage = $this->shared();
		$result  = $this->post( array( 'clubhouse_blocks_block' => 'about-history', 'clubhouse_blocks_delete' => '1' ), $storage );

		$this->assertNotNull( $result['confirm'] );
		$this->assertSame( array( 'About', 'Membership' ), $result['confirm']['used_on'] );
		$this->assertTrue( ( new Blueworx_Clubhouse_Block_Library( $storage ) )->has( 'about-history' ) );

		$html = Blueworx_Clubhouse_Blocks_Screen::render( $this->model( $storage, 'about-history', $result['confirm'] ) );
		$this->assertStringContainsString( 'About, Membership', $html );
	}

	public function test_a_confirmed_delete_takes_the_block_off_every_page(): void {
		$storage = $this->shared();
		$this->post(
			array( 'clubhouse_blocks_block' => 'about-history', 'clubhouse_blocks_delete' => '1', 'clubhouse_blocks_confirm' => '1' ),
			$storage
		);
		$this->assertFalse( ( new Blueworx_Clubhouse_Block_Library( $storage ) )->has( 'about-history' ) );
		$this->assertSame( array(), ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->uses( 'about-history' ) );
	}

	public function test_deleting_a_block_on_no_page_does_not_ask(): void {
		$storage = $this->seeded();
		$id      = ( new Blueworx_Clubhouse_Block_Library( $storage ) )->add( 'faq', 'Spare questions' );

		$result = $this->post( array( 'clubhouse_blocks_block' => $id, 'clubhouse_blocks_delete' => '1' ), $storage );
		$this->assertNull( $result['confirm'] );
		$this->assertFalse( ( new Blueworx_Clubhouse_Block_Library( $storage ) )->has( $id ) );
	}

	public function test_the_header_and_footer_cannot_be_deleted_or_duplicated(): void {
		$html = Blueworx_Clubhouse_Blocks_Screen::render( $this->model( $this->seeded(), 'header' ) );
		$this->assertStringNotContainsString( 'clubhouse_blocks_delete', $html );
		$this->assertStringNotContainsString( 'clubhouse_blocks_duplicate', $html );
		$this->assertStringContainsString( 'Shown on every page', $html );
	}

	// -- Making one -----------------------------------------------------------

	public function test_making_a_block_opens_it_for_editing(): void {
		$storage = $this->seeded();
		$result  = $this->post( array( 'clubhouse_blocks_new' => 'faq' ), $storage );

		$block = ( new Blueworx_Clubhouse_Block_Library( $storage ) )->get( $result['block'] );
		$this->assertSame( 'faq', $block['type'] );
		$this->assertSame( array(), ( new Blueworx_Clubhouse_Page_Composition( $storage ) )->uses( $result['block'] ) );
	}

	public function test_the_header_and_footer_cannot_be_made(): void {
		$storage = $this->seeded();
		$before  = count( ( new Blueworx_Clubhouse_Block_Library( $storage ) )->all() );
		$this->post( array( 'clubhouse_blocks_new' => 'header' ), $storage );
		$this->assertCount( $before, ( new Blueworx_Clubhouse_Block_Library( $storage ) )->all() );
		$this->assertNotContains( 'header', array_column( $this->model( $storage )['new_types'], 'type' ) );
	}

	public function test_a_post_naming_a_block_that_is_gone_changes_nothing(): void {
		$storage = $this->seeded();
		$before  = count( ( new Blueworx_Clubhouse_Block_Library( $storage ) )->all() );
		$this->post( array( 'clubhouse_blocks_block' => 'no-such-block', 'clubhouse_blocks_delete' => '1' ), $storage );
		$this->assertCount( $before, ( new Blueworx_Clubhouse_Block_Library( $storage ) )->all() );
	}

	// -- Output hygiene -------------------------------------------------------

	public function test_escapes_a_block_name(): void {
		$storage = $this->seeded();
		$id      = ( new Blueworx_Clubhouse_Block_Library( $storage ) )->add( 'faq', '<script>alert(1)</script>' );
		$html    = Blueworx_Clubhouse_Blocks_Screen::render( $this->model( $storage, $id ) );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_escapes_stored_words(): void {
		$storage = $this->seeded();
		( new Blueworx_Clubhouse_Block_Library( $storage ) )->set_content( 'about-history', array( 'heading' => '<script>alert(1)</script>' ) );
		$html = Blueworx_Clubhouse_Blocks_Screen::render( $this->model( $storage, 'about-history' ) );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
	}

	public function test_no_inline_styles_outside_the_token_block(): void {
		$html = Blueworx_Clubhouse_Blocks_Screen::render( $this->model( $this->seeded(), 'about-hero' ) );
		$this->assertStringNotContainsString( ' style="', $html );
	}
}
