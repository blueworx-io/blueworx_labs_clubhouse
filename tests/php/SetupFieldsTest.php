<?php

use PHPUnit\Framework\TestCase;

/**
 * The Setup screen as data. Pure — no storage, no WordPress — so the whole
 * screen can be held against the library's own shape check here rather than
 * discovered by an owner opening a screen that says it is not ready.
 */
final class SetupFieldsTest extends TestCase {

	/** @return array{setup:bool,menu:bool,demo:bool} */
	private function can(): array {
		return array( 'setup' => true, 'menu' => true, 'demo' => true );
	}

	/** @return array<string,array{name:string,description:string}> */
	private function looks(): array {
		return array(
			'court-side' => array( 'name' => 'Court Side', 'description' => 'Crisp and sporty.' ),
			'floodlight' => array( 'name' => 'Floodlight', 'description' => 'Dark and dramatic.' ),
		);
	}

	/** @return array<int,array{page:string,label:string}> */
	private function pages(): array {
		return array(
			array( 'page' => 'home',  'label' => 'Home' ),
			array( 'page' => 'about', 'label' => 'About' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private function tabs( ?array $can = null ): array {
		return Blueworx_Clubhouse_Setup_Fields::tabs( $can ?? $this->can(), $this->looks(), $this->pages() );
	}

	public function test_the_six_tabs_are_in_the_order_issue_284_asks_for(): void {
		$this->assertSame(
			array( 'look', 'visibility', 'menu', 'members', 'settings', 'demo' ),
			array_column( $this->tabs(), 'id' )
		);
	}

	public function test_members_and_settings_are_separate_tabs(): void {
		$tabs = array_column( $this->tabs(), null, 'id' );
		$this->assertSame( array( 'profile_fields' ), array_column( $tabs['members']['panels'], 'id' ) );
		$this->assertSame( array( 'after_sign_in', 'emails' ), array_column( $tabs['settings']['panels'], 'id' ) );
	}

	public function test_visibility_has_one_switch_per_available_page(): void {
		$tabs = array_column( $this->tabs(), null, 'id' );
		$this->assertSame(
			array( 'page_visible_home', 'page_visible_about' ),
			array_column( $tabs['visibility']['panels'][0]['fields'], 'id' )
		);
	}

	public function test_a_page_switch_defaults_to_on(): void {
		$tabs   = array_column( $this->tabs(), null, 'id' );
		$fields = array_column( $tabs['visibility']['panels'][0]['fields'], null, 'id' );
		$this->assertTrue( $fields['page_visible_home']['default'] );
	}

	public function test_every_look_the_registry_offers_is_a_choice(): void {
		$tabs   = array_column( $this->tabs(), null, 'id' );
		$fields = array_column( $tabs['look']['panels'][0]['fields'], null, 'id' );
		$this->assertSame( array( 'court-side', 'floodlight' ), array_column( $fields['look']['options'], 'value' ) );
	}

	public function test_a_content_editor_gets_the_menu_tab_and_nothing_else(): void {
		$this->assertSame(
			array( 'menu' ),
			array_column( $this->tabs( array( 'setup' => false, 'menu' => true, 'demo' => false ) ), 'id' )
		);
	}

	public function test_demo_is_absent_for_a_non_administrator(): void {
		$this->assertNotContains(
			'demo',
			array_column( $this->tabs( array( 'setup' => true, 'menu' => true, 'demo' => false ) ), 'id' )
		);
	}

	/**
	 * The menu is a Content Editor's job, so its fields carry no capability of
	 * their own — everything else on the screen is behind SETUP_CAP. Without
	 * this the library would show a Content Editor the Menu tab with every
	 * control in it locked.
	 */
	public function test_the_menu_rows_are_writable_by_a_content_editor(): void {
		$tabs = array_column( $this->tabs(), null, 'id' );
		$menu = $tabs['menu']['panels'][0]['fields'][0];
		$this->assertSame( '', $menu['capability'] ?? '' );
		foreach ( $menu['fields'] as $cell ) {
			$this->assertSame( '', $cell['capability'] ?? '' );
		}
	}

	public function test_demo_mode_is_administrator_only(): void {
		$tabs = array_column( $this->tabs(), null, 'id' );
		$this->assertSame( 'manage_options', $tabs['demo']['panels'][0]['fields'][0]['capability'] );
	}

	public function test_everything_outside_the_menu_is_behind_the_setup_capability(): void {
		foreach ( $this->tabs() as $tab ) {
			if ( in_array( $tab['id'], array( 'menu', 'demo' ), true ) ) {
				continue;
			}
			foreach ( $tab['panels'] as $panel ) {
				foreach ( $panel['fields'] as $field ) {
					$this->assertSame(
						Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP,
						$field['capability'] ?? '',
						$field['id'] . ' should be behind the setup capability'
					);
				}
			}
		}
	}

	public function test_the_screen_passes_the_librarys_own_shape_check(): void {
		$screen = array(
			'slug'       => 'clubhouse-setup',
			'title'      => 'Clubhouse Setup',
			'store'      => 'option',
			'read'       => static fn( int $id ): array => array(),
			'write'      => static fn( array $values, int $id ): bool => true,
			'capability' => Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			'tabs'       => $this->tabs(),
		);
		$this->assertIsArray( \Blueworx\PageEditor\v1\Schema::validate( $screen ) );
	}

	public function test_a_content_editors_screen_passes_it_too(): void {
		$screen = array(
			'slug'       => 'clubhouse-setup',
			'title'      => 'Clubhouse Setup',
			'store'      => 'option',
			'read'       => static fn( int $id ): array => array(),
			'write'      => static fn( array $values, int $id ): bool => true,
			'capability' => Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			'tabs'       => $this->tabs( array( 'setup' => false, 'menu' => true, 'demo' => false ) ),
		);
		$this->assertIsArray( \Blueworx\PageEditor\v1\Schema::validate( $screen ) );
	}
}
