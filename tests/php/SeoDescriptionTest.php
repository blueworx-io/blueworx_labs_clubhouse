<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Where a page's meta description comes from.
 *
 * It used to be read off the old content store, through a member of the request
 * context that no longer exists — so every front-end page fataled on a real
 * WordPress install while the whole PHP suite stayed green. It reads the page's
 * hero block now, which is the same words in their new home.
 *
 * The regression this file exists to stop is the plainest one: a real context,
 * asked a real question, must answer rather than throw.
 */
final class SeoDescriptionTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	private function context( Blueworx_Clubhouse_Storage $storage ): Blueworx_Clubhouse_Clubhouse_Context {
		return new Blueworx_Clubhouse_Clubhouse_Context(
			new Blueworx_Clubhouse_Court_Side(),
			new Blueworx_Clubhouse_Branding( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage ),
			new Blueworx_Clubhouse_Theme_Cache( $storage ),
			new Blueworx_Clubhouse_Demo_Collections(),
			Blueworx_Clubhouse_Frontend::registry( $storage ),
			Blueworx_Clubhouse_Test_Site::composer( $storage )
		);
	}

	public function test_the_clubs_own_lede_is_the_description(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::write( $storage, 'about/hero', array( 'lede' => 'Rowing on the Thames since 1974.' ) );

		$this->assertSame(
			'Rowing on the Thames since 1974.',
			Blueworx_Clubhouse_Seo_Head::description( $this->context( $storage ), 'about', 'Marlow RC' )
		);
	}

	/** No lede, so the heading it sits under answers instead. */
	public function test_the_heading_stands_in_when_there_is_no_lede(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Test_Site::write( $storage, 'about/hero', array(
			'title_lead'      => 'A club for',
			'title_highlight' => 'everyone',
		) );

		$this->assertSame(
			'A club for everyone',
			Blueworx_Clubhouse_Seo_Head::description( $this->context( $storage ), 'about', 'Marlow RC' )
		);
	}

	/**
	 * A club that has written nothing gets its own name rather than the shipped
	 * placeholder copy — that sentence is identical on every clubhouse site,
	 * which is worse for a search engine than a bare name.
	 */
	public function test_a_club_that_has_written_nothing_gets_its_name(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->assertSame(
			'Marlow RC',
			Blueworx_Clubhouse_Seo_Head::description( $this->context( $storage ), 'about', 'Marlow RC' )
		);
	}

	/** A page with no hero of its own answers, rather than throwing looking for one. */
	public function test_a_page_with_no_hero_still_answers(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->assertSame(
			'Marlow RC',
			Blueworx_Clubhouse_Seo_Head::description( $this->context( $storage ), 'news', 'Marlow RC' )
		);
	}
}
