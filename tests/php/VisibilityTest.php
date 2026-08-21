<?php

use PHPUnit\Framework\TestCase;

final class VisibilityTest extends TestCase {
	private function vis(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_pages_visible_by_default(): void {
		$this->assertTrue( $this->vis()->is_page_visible( 'home' ) );
	}

	public function test_sections_visible_by_default(): void {
		$this->assertTrue( $this->vis()->is_section_visible( 'home', 'hero' ) );
	}

	/**
	 * The social feed is the only section that ships hidden: it shows nothing
	 * until a club has pasted its posts in, so shipping it on would put an empty
	 * band on every existing club site. Pinned so any further opt-in section is
	 * a deliberate edit here rather than a silent default change.
	 */
	public function test_only_the_social_feed_ships_hidden(): void {
		$v = $this->vis();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$key = $page['page'] . '.' . $section['key'];
				if ( 'home.social_feed' === $key ) {
					$this->assertFalse( $v->is_section_visible( $page['page'], $section['key'] ), $key );
					continue;
				}
				$this->assertTrue( $v->is_section_visible( $page['page'], $section['key'] ), $key );
			}
		}
	}

	public function test_a_club_can_opt_the_social_feed_in(): void {
		$v = $this->vis();
		$v->set_section_visible( 'home', 'social_feed', true );
		$this->assertTrue( $v->is_section_visible( 'home', 'social_feed' ) );
	}

	public function test_a_section_can_be_switched_off_and_back_on(): void {
		$v = $this->vis();
		$v->set_section_visible( 'home', 'sponsors', false );
		$this->assertFalse( $v->is_section_visible( 'home', 'sponsors' ) );
		$v->set_section_visible( 'home', 'sponsors', true );
		$this->assertTrue( $v->is_section_visible( 'home', 'sponsors' ) );
	}

	public function test_section_state_is_keyed_per_page_not_per_section_name(): void {
		$v = $this->vis();
		$v->set_section_visible( 'home', 'hero', false );
		$this->assertTrue( $v->is_section_visible( 'about', 'hero' ) );
	}

	public function test_hiding_a_page_persists(): void {
		$v = $this->vis();
		$v->set_page_visible( 'blog', false );
		$this->assertFalse( $v->is_page_visible( 'blog' ) );
		$this->assertTrue( $v->is_page_visible( 'home' ) );
	}

	public function test_hiding_a_section_persists(): void {
		$v = $this->vis();
		$v->set_section_visible( 'home', 'hero', false );
		$this->assertFalse( $v->is_section_visible( 'home', 'hero' ) );
		$this->assertTrue( $v->is_section_visible( 'home', 'other' ) );
	}

	public function test_section_keys_do_not_collide_across_pages(): void {
		$v = $this->vis();
		$v->set_section_visible( 'home', 'hero', false );
		$this->assertTrue( $v->is_section_visible( 'about', 'hero' ) );
	}

	public function test_re_showing_a_page(): void {
		$v = $this->vis();
		$v->set_page_visible( 'blog', false );
		$v->set_page_visible( 'blog', true );
		$this->assertTrue( $v->is_page_visible( 'blog' ) );
	}

	/**
	 * Switching a page off drafts the real page behind it.
	 *
	 * The flag alone only told this plugin not to render — the page stayed
	 * published, so it stayed in the sitemap and in search. A draft is out of
	 * both and 404s to a visitor, which is what "switched off" always meant.
	 */
	public function test_switching_a_page_off_drafts_its_page(): void {
		wp_stub_reset();
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'contact' ), 42 );
		$GLOBALS['wp_stub_post_status'][42] = 'publish';

		$this->vis()->set_page_visible( 'contact', false );

		$updates = wp_stub_calls( 'wp_update_post' );
		$this->assertCount( 1, $updates );
		$this->assertSame( 42, $updates[0]['args'][0]['ID'] );
		$this->assertSame( 'draft', $updates[0]['args'][0]['post_status'] );
	}

	public function test_switching_a_page_back_on_publishes_its_page(): void {
		wp_stub_reset();
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'contact' ), 42 );
		$GLOBALS['wp_stub_post_status'][42] = 'draft';

		$this->vis()->set_page_visible( 'contact', true );

		$updates = wp_stub_calls( 'wp_update_post' );
		$this->assertCount( 1, $updates );
		$this->assertSame( 'publish', $updates[0]['args'][0]['post_status'] );
	}

	/** Home's visibility key is 'home' but its slug is '' — never a truthiness check. */
	public function test_switching_home_off_drafts_the_front_page(): void {
		wp_stub_reset();
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 7 );
		$GLOBALS['wp_stub_post_status'][7] = 'publish';

		$this->vis()->set_page_visible( 'home', false );

		$updates = wp_stub_calls( 'wp_update_post' );
		$this->assertCount( 1, $updates );
		$this->assertSame( 7, $updates[0]['args'][0]['ID'] );
		$this->assertSame( 'draft', $updates[0]['args'][0]['post_status'] );
	}

	/** The stored flag is still the record the Setup screen reads. */
	public function test_the_flag_is_kept_as_well_as_the_status(): void {
		wp_stub_reset();
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'contact' ), 42 );
		$GLOBALS['wp_stub_post_status'][42] = 'publish';

		$v = $this->vis();
		$v->set_page_visible( 'contact', false );

		$this->assertFalse( $v->is_page_visible( 'contact' ) );
	}

	/** A page whose status already matches is not written to again. */
	public function test_a_page_already_in_the_right_status_is_left_alone(): void {
		wp_stub_reset();
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'contact' ), 42 );
		$GLOBALS['wp_stub_post_status'][42] = 'publish';

		$this->vis()->set_page_visible( 'contact', true );

		$this->assertSame( array(), wp_stub_calls( 'wp_update_post' ) );
	}

	/** A section switch is not a page switch and must never move a page's status. */
	public function test_switching_a_section_off_never_touches_a_page_status(): void {
		wp_stub_reset();
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'contact' ), 42 );
		$GLOBALS['wp_stub_post_status'][42] = 'publish';

		$this->vis()->set_section_visible( 'contact', 'form', false );

		$this->assertSame( array(), wp_stub_calls( 'wp_update_post' ) );
	}
}
