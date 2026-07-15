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

	public function test_home_stats_is_hidden_by_default(): void {
		$this->assertFalse( $this->vis()->is_section_visible( 'home', 'stats' ) );
	}

	public function test_a_default_hidden_section_can_be_switched_on(): void {
		$v = $this->vis();
		$v->set_section_visible( 'home', 'stats', true );
		$this->assertTrue( $v->is_section_visible( 'home', 'stats' ) );
	}

	public function test_default_hidden_is_keyed_per_page_not_per_section_name(): void {
		$this->assertTrue( $this->vis()->is_section_visible( 'about', 'stats' ) );
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
}
