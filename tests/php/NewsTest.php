<?php
// tests/php/NewsTest.php

use PHPUnit\Framework\TestCase;

/**
 * Club news: the archive's paging and filtering, and the two screens that draw
 * it. Everything here runs on the demo post source, so no WordPress is needed
 * to pin behaviour a club would notice.
 */
final class NewsTest extends TestCase {

	protected function setUp(): void {
		Blueworx_Clubhouse_News::set_source( new Blueworx_Clubhouse_Demo_Posts() );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_News::reset();
	}

	private function render_index(): string {
		return Blueworx_Clubhouse_Page_Map::render(
			'news',
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Null_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Null_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections()
		);
	}

	private function render_article(): string {
		return Blueworx_Clubhouse_Page_Renderer::post(
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Null_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Null_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections()
		);
	}

	public function test_news_is_a_page_the_site_serves(): void {
		$this->assertTrue( Blueworx_Clubhouse_Page_Map::is_available( 'news' ) );
		$this->assertSame( 'News', Blueworx_Clubhouse_Page_Map::label( 'news' ) );
	}

	public function test_the_index_leads_with_the_newest_story_and_does_not_repeat_it(): void {
		$html = $this->render_index();

		$this->assertStringContainsString( 'ch-featured__card', $html );
		$this->assertStringContainsString( '1st XV promoted to Division 3 South', $html );
		// Once in the featured card, and not again in the grid below it.
		$this->assertSame( 1, substr_count( $html, '1st XV promoted to Division 3 South' ) );
	}

	/** Eight demo posts, six to a page: the lead plus five, then a second page. */
	public function test_the_index_pages_rather_than_listing_everything(): void {
		$html = $this->render_index();

		$this->assertSame( 5, substr_count( $html, 'ch-postcard__title' ) );
		$this->assertStringContainsString( 'ch-pager', $html );
		$this->assertStringContainsString( '8 stories', $html );
	}

	public function test_the_second_page_carries_the_rest(): void {
		Blueworx_Clubhouse_News::set_page( 2 );
		$html = $this->render_index();

		$this->assertStringContainsString( 'Netball section adds a fourth team', $html );
		// The lead story is only lifted out on page one — on page two "featured"
		// would just mean "whichever post happens to be first".
		$this->assertStringNotContainsString( 'ch-featured__card', $html );
	}

	/**
	 * A stale bookmark to page 99 is a reader's mistake, not the club deleting
	 * everything — clamp rather than show an empty archive.
	 */
	public function test_a_page_number_past_the_end_is_clamped(): void {
		$this->assertSame(
			array( 'page' => 2, 'pages' => 2, 'offset' => 6 ),
			Blueworx_Clubhouse_News::paging( 8, 99 )
		);
		$this->assertSame(
			array( 'page' => 1, 'pages' => 1, 'offset' => 0 ),
			Blueworx_Clubhouse_News::paging( 0, 3 )
		);
	}

	public function test_categories_become_filter_pills_with_an_all_pill_first(): void {
		$html = $this->render_index();

		$this->assertStringContainsString( '>All</a>', $html );
		$this->assertStringContainsString( '>Rugby</a>', $html );
		$this->assertStringContainsString( '>Hockey</a>', $html );
	}

	public function test_a_pager_is_not_drawn_when_there_is_only_one_page(): void {
		$html = Blueworx_Clubhouse_Sections::news_grid(
			array(
				'filter_label' => 'Filter',
				'filters'      => array(),
				'count_label'  => '2 stories',
				'posts'        => array(),
				'empty_text'   => 'Nothing yet.',
				'pager'        => array( 'page' => 1, 'pages' => 1, 'prev_href' => '', 'next_href' => '', 'pages_list' => array() ),
			)
		);
		$this->assertStringNotContainsString( 'ch-pager', $html );
	}

	/**
	 * A club with no news at all gets a sentence, not a bare heading over a gap.
	 */
	public function test_a_site_with_no_posts_says_so(): void {
		Blueworx_Clubhouse_News::set_source( null );
		$html = $this->render_index();

		$this->assertStringContainsString( 'no club news yet', $html );
		$this->assertStringNotContainsString( 'ch-featured__card', $html );
	}

	public function test_the_article_renders_the_whole_design(): void {
		$html = $this->render_article();

		$this->assertStringContainsString( 'ch-posthead__back', $html );
		$this->assertStringContainsString( '<h1 class="ch-posthead__title">1st XV promoted to Division 3 South</h1>', $html );
		$this->assertStringContainsString( 'ch-posthead__standfirst', $html );
		$this->assertStringContainsString( 'ch-byline__name', $html );
		$this->assertStringContainsString( 'ch-prose', $html );
		$this->assertStringContainsString( 'ch-posttag', $html );
		$this->assertStringContainsString( 'ch-postauthor__bio', $html );
		$this->assertStringContainsString( 'Keep reading', $html );
	}

	/** One h1 per page, and it is the headline — the heading order has to hold. */
	public function test_the_article_has_exactly_one_main_heading(): void {
		$this->assertSame( 1, substr_count( $this->render_article(), '<h1' ) );
	}

	/** Header and footer are the site's own, unchanged by the news pages. */
	public function test_both_screens_wear_the_ordinary_site_chrome(): void {
		foreach ( array( $this->render_index(), $this->render_article() ) as $html ) {
			$this->assertStringContainsString( '<header class="ch-nav">', $html );
			$this->assertStringContainsString( 'ch-footer', $html );
		}
	}

	/**
	 * The body is the one place stored markup is emitted as markup — that is the
	 * point of an article — so the headings and quotes an editor writes survive.
	 */
	public function test_the_article_body_keeps_the_markup_the_editor_wrote(): void {
		$html = Blueworx_Clubhouse_Sections::post_body(
			array( 'html' => '<h2>A season</h2><blockquote><p>Quote</p></blockquote>', 'tags' => array() )
		);
		$this->assertStringContainsString( '<h2>A season</h2>', $html );
		$this->assertStringContainsString( '<blockquote><p>Quote</p></blockquote>', $html );
	}

	/**
	 * Previous and next. The demo posts run newest first, so the newest story
	 * has nothing after it and the oldest has nothing before it — the two ends
	 * are where a naive control draws a link to nowhere.
	 */
	public function test_a_story_in_the_middle_offers_both_directions(): void {
		$steps = ( new Blueworx_Clubhouse_Demo_Posts( 3 ) )->adjacent();

		$this->assertNotNull( $steps['previous'] );
		$this->assertNotNull( $steps['next'] );
		$this->assertNotSame( '', $steps['previous']['title'] );
		$this->assertNotSame( '', $steps['next']['href'] );
	}

	public function test_the_newest_story_has_no_next_and_the_oldest_no_previous(): void {
		$posts  = new Blueworx_Clubhouse_Demo_Posts();
		$newest = (int) $posts->recent( 1 )[0]['id'];
		$all    = $posts->recent( 99 );
		$oldest = (int) $all[ count( $all ) - 1 ]['id'];

		$this->assertNull( ( new Blueworx_Clubhouse_Demo_Posts( $newest ) )->adjacent()['next'] );
		$this->assertNotNull( ( new Blueworx_Clubhouse_Demo_Posts( $newest ) )->adjacent()['previous'] );

		$this->assertNull( ( new Blueworx_Clubhouse_Demo_Posts( $oldest ) )->adjacent()['previous'] );
		$this->assertNotNull( ( new Blueworx_Clubhouse_Demo_Posts( $oldest ) )->adjacent()['next'] );
	}

	/** 'previous' is the older story: working backwards through a season. */
	public function test_previous_is_the_older_story(): void {
		$posts = new Blueworx_Clubhouse_Demo_Posts();
		$rows  = $posts->recent( 3 );
		$steps = ( new Blueworx_Clubhouse_Demo_Posts( (int) $rows[1]['id'] ) )->adjacent();

		$this->assertSame( $rows[2]['title'], $steps['previous']['title'] );
		$this->assertSame( $rows[0]['title'], $steps['next']['title'] );
	}

	public function test_only_the_half_that_exists_is_drawn(): void {
		$html = Blueworx_Clubhouse_Sections::post_steps(
			array( 'previous' => array( 'title' => 'An older match', 'href' => '/older/' ), 'next' => null )
		);

		$this->assertStringContainsString( 'ch-poststep--prev', $html );
		$this->assertStringNotContainsString( 'ch-poststep--next', $html );
		$this->assertStringContainsString( 'An older match', $html );
	}

	/** A club with one story gets no control at all, not an empty band. */
	public function test_a_lone_story_draws_no_control(): void {
		$this->assertSame(
			'',
			Blueworx_Clubhouse_Sections::post_steps( array( 'previous' => null, 'next' => null ) )
		);
	}

	public function test_the_control_escapes_the_titles_it_is_given(): void {
		$html = Blueworx_Clubhouse_Sections::post_steps(
			array(
				'previous' => array( 'title' => 'Ladies 1s <script>alert(1)</script>', 'href' => '/a/' ),
				'next'     => null,
			)
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/** The article carries the control, drawn once. */
	public function test_the_article_offers_the_story_either_side(): void {
		$html = $this->render_article();

		$this->assertStringContainsString( 'ch-poststeps', $html );
		$this->assertSame( 1, substr_count( $html, 'class="ch-sec ch-poststeps"' ) );
	}

	/** Everything around the body is still escaped. */
	public function test_the_article_furniture_is_escaped(): void {
		$html = Blueworx_Clubhouse_Sections::post_head(
			array(
				'back_label' => 'All news',
				'back_href'  => '?page=news',
				'post'       => array(
					'title'      => 'Ladies 1s <script>alert(1)</script>',
					'standfirst' => '',
					'category'   => 'Hockey',
					'date'       => '6 July 2026',
					'read'       => '',
					'author'     => array( 'name' => '', 'role' => '', 'initials' => '', 'bio' => '' ),
				),
			)
		);
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_an_article_with_no_biography_skips_the_author_card(): void {
		$html = Blueworx_Clubhouse_Sections::post_author(
			array( 'label' => 'Written by', 'author' => array( 'name' => 'Tom', 'role' => '', 'initials' => 'T', 'bio' => '  ' ) )
		);
		$this->assertSame( '', $html );
	}

	public function test_an_article_with_no_image_renders_no_figure(): void {
		$this->assertSame(
			'',
			Blueworx_Clubhouse_Sections::post_media( array( 'image' => '', 'image_alt' => 'x', 'caption' => 'A caption' ) )
		);
	}

	public function test_initials_come_from_the_first_and_last_name(): void {
		$this->assertSame( 'TB', Blueworx_Clubhouse_WP_Posts::initials( 'Tom Brennan' ) );
		$this->assertSame( 'DR', Blueworx_Clubhouse_WP_Posts::initials( '  Dev  Raman ' ) );
		$this->assertSame( 'A', Blueworx_Clubhouse_WP_Posts::initials( 'Ann' ) );
		$this->assertSame( '', Blueworx_Clubhouse_WP_Posts::initials( '   ' ) );
	}

	/** The first page of everything is the bare address, not one full of defaults. */
	public function test_the_news_url_drops_its_defaults(): void {
		$this->assertSame( '?page=news', Blueworx_Clubhouse_News::url() );
		$this->assertStringContainsString( 'rugby', Blueworx_Clubhouse_News::url( 'rugby' ) );
		$this->assertStringContainsString( 'clubhouse_page_no=2', Blueworx_Clubhouse_News::url( '', 2 ) );
	}
}
