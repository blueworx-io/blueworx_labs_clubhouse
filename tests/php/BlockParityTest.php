<?php

use PHPUnit\Framework\TestCase;

/**
 * The safety net for the whole move to blocks: every page, rendered from the
 * block library, must be byte-for-byte what the old page methods produced.
 *
 * A difference here is not a test to adjust — it is the change of behaviour
 * this rewrite is not allowed to make. The pages are compared as whole strings
 * so a stray attribute, a lost anchor or a section in the wrong order all fail
 * loudly rather than being noticed on a club's live site.
 */
final class BlockParityTest extends TestCase {

	/** Page key => the slug Page_Map dispatches on ('' is Home). */
	private const PAGES = array(
		'home'       => '',
		'about'      => 'about',
		'membership' => 'membership',
		'contact'    => 'contact',
		'login'      => 'login',
		'news'       => 'news',
		'sports'     => 'sports',
		'teams'      => 'teams',
		'events'     => 'events',
		'calendar'   => 'calendar',
		'booking'    => 'booking',
		'privacy'    => 'privacy',
		'terms'      => 'terms',
	);

	/**
	 * @return array{0:Blueworx_Clubhouse_Branding,1:Blueworx_Clubhouse_Visibility,2:Blueworx_Clubhouse_Collections,3:Blueworx_Clubhouse_Content_Store,4:Blueworx_Clubhouse_Page_Composer}
	 */
	private function harness( Blueworx_Clubhouse_Fake_Storage $storage ): array {
		$branding    = new Blueworx_Clubhouse_Branding( $storage );
		$visibility  = new Blueworx_Clubhouse_Visibility( $storage );
		$collections = new Blueworx_Clubhouse_Demo_Collections();
		$content     = new Blueworx_Clubhouse_Content_Store( $storage );

		$library     = new Blueworx_Clubhouse_Block_Library( $storage );
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );
		( new Blueworx_Clubhouse_Block_Seeder( $library, $composition ) )->migrate( $content, $visibility );

		return array( $branding, $visibility, $collections, $content, new Blueworx_Clubhouse_Page_Composer( $library, $composition ) );
	}

	private function assertPageMatches( Blueworx_Clubhouse_Fake_Storage $storage, string $page, string $filter = '' ): void {
		[ $branding, $visibility, $collections, $content, $composer ] = $this->harness( $storage );

		$old = Blueworx_Clubhouse_Page_Map::render(
			self::PAGES[ $page ],
			$branding,
			$visibility,
			$collections,
			'',
			$content,
			$filter
		);
		$new = $composer->page( $page, $branding, $visibility, $collections, '', $filter );

		$this->assertSame( $old, $new, $page . ( '' === $filter ? '' : ' (filter: ' . $filter . ')' ) );
	}

	/** Every page, on a site nobody has edited yet. */
	public function test_every_page_renders_identically_from_blocks(): void {
		foreach ( array_keys( self::PAGES ) as $page ) {
			$this->assertPageMatches( new Blueworx_Clubhouse_Fake_Storage(), (string) $page );
		}
	}

	/** Every page again, for a club that has written its own words. */
	public function test_a_club_with_saved_content_renders_identically(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$content = new Blueworx_Clubhouse_Content_Store( $storage );

		$content->set( 'global', 'header', 'banner', 'Trials this Saturday' );
		$content->set( 'global', 'footer', 'tagline', 'Since 1902.' );
		$content->set( 'global', 'cookies', 'text', 'We use one cookie, for signing in.' );
		$content->set( 'home', 'hero', 'title_lead', 'Marlow rowing, ' );
		$content->set( 'home', 'clubhouse', 'heading', 'The boathouse' );
		$content->set_items( 'home', 'quick_tiles', array(
			array( 'label' => 'Join us', 'href' => '/membership', 'icon' => 'join' ),
		) );
		$content->set_items( 'home', 'ticker', array( array( 'text' => 'Head of the River — Sat' ) ) );
		$content->set_items( 'home', 'info', array(
			array( 'label' => 'Where', 'lines' => "The Boathouse\nMarlow", 'link_label' => '', 'link_href' => '' ),
		) );
		$content->set( 'about', 'hero', 'lede', 'A hundred and twenty years of it.' );
		$content->set_items( 'about', 'values', array( array( 'title' => 'Row', 'description' => 'Every morning.' ) ) );
		$content->set_items( 'membership', 'tiers', array(
			array( 'name' => 'Full', 'price' => '£40', 'period' => '/mo', 'features' => "Boats\nCoaching", 'featured' => true, 'cta_label' => 'Join', 'cta_href' => '/contact' ),
		) );
		$content->set_items( 'membership', 'faq', array( array( 'question' => 'Kit?', 'answer' => 'Bring shoes.', 'open' => true ) ) );
		$content->set( 'contact', 'form', 'address', "The Boathouse\nMarlow" );
		$content->set( 'contact', 'form', 'phone', '01628 111 222' );
		$content->set_items( 'privacy', 'body', array( array( 'heading' => 'Who we are', 'body' => 'Marlow RC.' ) ) );
		$content->set( 'calendar', 'booking', 'heading', 'Book a boat' );

		foreach ( array_keys( self::PAGES ) as $page ) {
			$this->assertPageMatches( $storage, (string) $page );
		}
	}

	/** Every page again, for a club that has switched things off. */
	public function test_hidden_sections_and_pages_render_identically(): void {
		$storage    = new Blueworx_Clubhouse_Fake_Storage();
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );

		$visibility->set_section_visible( 'home', 'ticker', false );
		$visibility->set_section_visible( 'home', 'membership', false );
		$visibility->set_section_visible( 'home', 'info', false );
		$visibility->set_section_visible( 'about', 'committee', false );
		$visibility->set_section_visible( 'membership', 'faq', false );
		$visibility->set_section_visible( 'contact', 'directory', false );
		$visibility->set_section_visible( 'news', 'featured', false );
		$visibility->set_section_visible( 'calendar', 'schedule', false );
		$visibility->set_page_visible( 'news', false );

		foreach ( array_keys( self::PAGES ) as $page ) {
			$this->assertPageMatches( $storage, (string) $page );
		}
	}

	/** The social half of the Home closing band, switched off on its own. */
	public function test_home_with_only_the_find_us_columns_renders_identically(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Visibility( $storage ) )->set_section_visible( 'home', 'social', false );
		$this->assertPageMatches( $storage, 'home' );
	}

	/** The filtered list views, including a filter that matches nothing. */
	public function test_filtered_pages_render_identically(): void {
		$filters = array(
			'sports'   => array( 'netball', 'no-such-sport' ),
			'teams'    => array( 'rugby', 'no-such-sport' ),
			'events'   => array( 'social', 'no-such-tag' ),
			'calendar' => array( 'rugby', 'no-such-sport' ),
		);
		foreach ( $filters as $page => $slugs ) {
			foreach ( $slugs as $slug ) {
				$this->assertPageMatches( new Blueworx_Clubhouse_Fake_Storage(), (string) $page, (string) $slug );
			}
		}
	}
}
