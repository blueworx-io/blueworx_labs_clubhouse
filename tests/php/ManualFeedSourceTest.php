<?php

use PHPUnit\Framework\TestCase;

final class ManualFeedSourceTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		update_option( 'clubhouse_page_id_home', 42 );
	}

	/** @param array<int,mixed> $items */
	private function store( array $items ): Blueworx_Clubhouse_Page_Content {
		$content = new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'home', 'social_feed', $items );
		return $content;
	}

	public function test_a_pasted_link_becomes_a_normalised_post(): void {
		$source = new Blueworx_Clubhouse_Manual_Feed_Source(
			$this->store( array( array( 'href' => 'https://facebook.com/club/posts/1', 'caption' => 'Saturday win' ) ) )
		);
		$posts = $source->posts();
		$this->assertIsArray( $posts );
		$this->assertCount( 1, $posts );
		$this->assertSame( 'https://facebook.com/club/posts/1', $posts[0]['permalink'] );
		$this->assertSame( 'Saturday win', $posts[0]['caption'] );
		$this->assertSame( '', $posts[0]['image'] );
		$this->assertSame( '', $posts[0]['date'] );
		$this->assertNotSame( '', $posts[0]['id'] );
	}

	public function test_the_same_link_always_gets_the_same_id(): void {
		$a = Blueworx_Clubhouse_Manual_Feed_Source::normalise( array( 'href' => 'https://x.test/p/1' ) );
		$b = Blueworx_Clubhouse_Manual_Feed_Source::normalise( array( 'href' => 'https://x.test/p/1' ) );
		$this->assertSame( $a['id'], $b['id'] );
	}

	public function test_a_row_without_a_usable_link_is_dropped_not_rendered(): void {
		$source = new Blueworx_Clubhouse_Manual_Feed_Source(
			$this->store( array(
				array( 'href' => '', 'caption' => 'no link' ),
				array( 'caption' => 'no href key at all' ),
				array( 'href' => 'javascript:alert(1)', 'caption' => 'not a web address' ),
				'not even an array',
				array( 'href' => 'https://good.test/p/1', 'caption' => 'kept' ),
			) )
		);
		$posts = $source->posts();
		$this->assertCount( 1, $posts );
		$this->assertSame( 'kept', $posts[0]['caption'] );
	}

	public function test_nothing_pasted_is_an_empty_list_never_a_failure(): void {
		$source = new Blueworx_Clubhouse_Manual_Feed_Source( $this->store( array() ) );
		$this->assertSame( array(), $source->posts() );
	}
}
