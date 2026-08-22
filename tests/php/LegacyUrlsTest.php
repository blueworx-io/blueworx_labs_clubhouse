<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Issue #243. The plugin's own rewrite rules are gone, so the addresses they
 * answered — '/sports/rugby/' and the '?clubhouse_page=' query form — have to
 * be forwarded to the real page instead of quietly 404ing. These are the two
 * pure decisions behind that.
 */
final class LegacyUrlsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_the_old_query_form_names_its_page(): void {
		$this->assertSame(
			array(
				'slug' => 'news',
				'item' => '',
			),
			Blueworx_Clubhouse_Legacy_Urls::target( 'news', '', false )
		);
	}

	/** 'home' was always the query form's literal for Home, whose slug is ''. */
	public function test_the_old_query_form_knows_home_by_name(): void {
		$this->assertSame(
			array(
				'slug' => '',
				'item' => '',
			),
			Blueworx_Clubhouse_Legacy_Urls::target( 'home', '', false )
		);
	}

	public function test_the_query_form_is_answered_wherever_it_appears(): void {
		// It never depended on the path, so a bookmark that carries it on some
		// other address is still asking for the page it names.
		$this->assertSame(
			'news',
			Blueworx_Clubhouse_Legacy_Urls::target( 'news', 'somewhere/else', false )['slug']
		);
	}

	public function test_a_query_value_naming_no_club_page_is_left_alone(): void {
		$this->assertNull( Blueworx_Clubhouse_Legacy_Urls::target( 'quidditch', '', false ) );
		$this->assertNull( Blueworx_Clubhouse_Legacy_Urls::target( '', '', false ) );
		$this->assertNull( Blueworx_Clubhouse_Legacy_Urls::target( null, '', false ) );
	}

	/**
	 * The one address that never had a page of its own: a sport or team hung
	 * off its listing by a rewrite rule. It is a query argument now.
	 */
	public function test_a_sports_item_path_is_forwarded_with_its_item(): void {
		$this->assertSame(
			array(
				'slug' => 'sports',
				'item' => 'rugby',
			),
			Blueworx_Clubhouse_Legacy_Urls::target( null, 'sports/rugby', true )
		);
	}

	/**
	 * A club page whose real page was given a different slug — because the site
	 * already had a page by that name — still answers at the address the plugin
	 * used to serve.
	 */
	public function test_a_bare_club_page_path_is_forwarded(): void {
		$this->assertSame(
			array(
				'slug' => 'about',
				'item' => '',
			),
			Blueworx_Clubhouse_Legacy_Urls::target( null, 'about/', true )
		);
	}

	/**
	 * Only a request nothing else claimed. A real page sitting at that address
	 * has already answered, and stepping in front of it would redirect a page
	 * to itself on every single request.
	 */
	public function test_a_path_that_wordpress_already_answered_is_left_alone(): void {
		$this->assertNull( Blueworx_Clubhouse_Legacy_Urls::target( null, 'sports/rugby', false ) );
	}

	public function test_paths_that_were_never_ours_are_left_alone(): void {
		$this->assertNull( Blueworx_Clubhouse_Legacy_Urls::target( null, 'shop/basket', true ) );
		$this->assertNull( Blueworx_Clubhouse_Legacy_Urls::target( null, '', true ) );
		$this->assertNull( Blueworx_Clubhouse_Legacy_Urls::target( null, '/', true ) );
		$this->assertNull(
			Blueworx_Clubhouse_Legacy_Urls::target( null, 'sports/rugby/under-13s', true ),
			'deeper than anything this plugin ever produced'
		);
		$this->assertNull(
			Blueworx_Clubhouse_Legacy_Urls::target( null, 'news/anything', true ),
			'only Sports and Teams ever hung an item off their address'
		);
	}

	public function test_the_path_is_read_relative_to_the_wordpress_install(): void {
		$this->assertSame(
			'sports/rugby',
			Blueworx_Clubhouse_Legacy_Urls::relative_path( '/club/sports/rugby/?x=1', '/club/' )
		);
		$this->assertSame(
			'sports/rugby',
			Blueworx_Clubhouse_Legacy_Urls::relative_path( '/sports/rugby/', '/' )
		);
		$this->assertSame(
			'',
			Blueworx_Clubhouse_Legacy_Urls::relative_path( '/club/', '/club/' )
		);
	}

	/**
	 * A subdirectory install must not have its own name eaten out of a path
	 * that merely starts with the same letters.
	 */
	public function test_a_lookalike_directory_is_not_stripped(): void {
		$this->assertSame(
			'clubhouse/sports',
			Blueworx_Clubhouse_Legacy_Urls::relative_path( '/clubhouse/sports/', '/club/' )
		);
	}

	/**
	 * A redirect asks the browser to repeat a POST as a GET, which throws the
	 * form away. Signing in with the wrong password came back as a blank login
	 * form rather than an error, because WordPress reports 404 for a page
	 * number that does not exist and this stepped in front of the handler.
	 */
	public function test_a_submitted_form_is_never_redirected(): void {
		$this->assertTrue( Blueworx_Clubhouse_Legacy_Urls::forwards( 'GET' ) );
		$this->assertTrue( Blueworx_Clubhouse_Legacy_Urls::forwards( 'head' ) );
		$this->assertFalse( Blueworx_Clubhouse_Legacy_Urls::forwards( 'POST' ) );
		$this->assertFalse( Blueworx_Clubhouse_Legacy_Urls::forwards( 'PUT' ) );
	}

	public function test_it_forwards_on_template_redirect_before_anything_else_routes(): void {
		Blueworx_Clubhouse_Legacy_Urls::register();

		$calls = array_values( array_filter(
			wp_stub_calls( 'add_action' ),
			static fn( array $c ): bool => 'template_redirect' === $c['args'][0]
		) );

		$this->assertCount( 1, $calls );
		$this->assertLessThan(
			5,
			$calls[0]['args'][2] ?? 10,
			'ahead of the member area at 5 and the 404 pass at 10'
		);
	}
}
