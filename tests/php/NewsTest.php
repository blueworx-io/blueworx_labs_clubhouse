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

	/** @return string */
	private function share( array $over = array() ): string {
		return Blueworx_Clubhouse_Sections::post_share(
			array_merge( array( 'title' => '1st XV promoted', 'url' => 'https://club.example/news/promoted/' ), $over )
		);
	}

	public function test_a_story_can_be_shared_to_the_places_a_club_uses(): void {
		$html = $this->share();

		$this->assertStringContainsString( 'facebook.com/sharer', $html );
		$this->assertStringContainsString( 'wa.me', $html );
		$this->assertStringContainsString( 'mailto:', $html );
		$this->assertStringContainsString( 'Copy link', $html );
	}

	/** The story's own address, encoded, is what each of them carries. */
	public function test_every_share_target_carries_the_story_url(): void {
		$html    = $this->share();
		$encoded = rawurlencode( 'https://club.example/news/promoted/' );

		$this->assertSame( 3, substr_count( $html, $encoded ) );
		$this->assertStringContainsString( 'data-clubhouse-copy="https://club.example/news/promoted/"', $html );
	}

	/**
	 * The whole point of hand-rolled links: no vendor button, so no third-party
	 * script reads the page or the reader before they choose to share.
	 */
	public function test_sharing_pulls_in_no_third_party_script(): void {
		$this->assertStringNotContainsString( '<script', $this->share() );
		$this->assertStringNotContainsString( '<iframe', $this->share() );
	}

	/** Offering a button that cannot work is worse than not offering one. */
	public function test_copy_link_ships_hidden_for_script_to_reveal(): void {
		$this->assertMatchesRegularExpression( '/<button[^>]*\shidden/', $this->share() );
	}

	public function test_a_story_with_no_address_gets_no_share_row(): void {
		$this->assertSame( '', $this->share( array( 'url' => '' ) ) );
		$this->assertSame( '', $this->share( array( 'url' => '   ' ) ) );
	}

	public function test_the_share_row_escapes_the_title_it_is_given(): void {
		$html = $this->share( array( 'title' => 'Ladies 1s "<script>alert(1)</script>"' ) );

		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_the_article_offers_a_way_to_share_it(): void {
		$this->assertStringContainsString( 'ch-share__links', $this->render_article() );
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
