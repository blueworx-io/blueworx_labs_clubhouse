<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Issue #144: the menu builder moved from Club Pages to the Clubhouse screen,
 * where the rest of the site-wide settings live.
 *
 * The move must not change who can edit the menu. A Content Editor holds no
 * manage_clubhouse, so opening the Clubhouse screen to that role has to give
 * it the Menu tab and nothing else.
 */
final class MenuTabLocationTest extends TestCase {

	/** @return array<string,mixed> */
	private function menu_model(): array {
		return array(
			'tree'        => array(
				array( 'label' => 'Home', 'target' => 'page:home', 'children' => array() ),
			),
			'targets'     => array(
				array( 'target' => 'page:home', 'label' => 'Home', 'group' => 'Pages', 'url' => '/' ),
			),
			'action_url'  => 'admin.php?page=clubhouse-setup',
			'nonce_field' => '<input type="hidden" name="_wpnonce" value="x">',
		);
	}

	/** @return array<string,mixed> */
	private function setup_model( bool $can_setup, bool $with_menu ): array {
		return array(
			'nonce_field'   => '<input type="hidden" name="_wpnonce" value="x">',
			'action_url'    => 'admin.php?page=clubhouse-setup',
			'notices'       => array(),
			'progress'      => array( 'completed' => 1, 'total' => 3, 'items' => array() ),
			'looks'         => array(),
			'color_palette' => array(),
			'branding'      => array(
				'accent'              => '#123456',
				'accent_default'      => '#123456',
				'secondary'           => '',
				'secondary_default'   => '',
				'secondary_effective' => '#654321',
				'club_name'           => 'ClubHouse',
				'logo'                => '',
				'logo_preview'        => '',
				'favicon'             => '',
				'favicon_preview'     => '',
				'facebook'            => '',
				'instagram'           => '',
				'linkedin'            => '',
				'x'                   => '',
			),
			'pages'         => array(),
			'visibility'    => array( 'pages' => array() ),
			'members'       => array( 'post_login' => '', 'post_logout' => '', 'dashboard_url' => '' ),
			'active_slug'   => '',
			'look_tokens'   => array(),
			'font_face_css' => '',
			'can_demo'      => false,
			'can_setup'     => $can_setup,
			'menu'          => $with_menu ? $this->menu_model() : null,
		);
	}

	public function test_the_clubhouse_screen_has_a_menu_tab(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->setup_model( true, true ) );
		$this->assertStringContainsString( 'data-tab="menu"', $html );
		$this->assertStringContainsString( 'data-panel="menu"', $html );
		$this->assertStringContainsString( 'Header menu', $html );
	}

	/**
	 * The menu saves through the content plumbing, which is its own form — so it
	 * must not be nested inside the setup form, where a browser would drop it.
	 */
	public function test_the_menu_form_is_not_nested_inside_the_setup_form(): void {
		$html  = Blueworx_Clubhouse_Setup_Screen::render( $this->setup_model( true, true ) );
		$panel = strpos( $html, 'data-panel="menu"' );
		$close = strpos( $html, '</form>' );
		$this->assertIsInt( $panel );
		$this->assertIsInt( $close );
		$this->assertGreaterThan( $close, $panel, 'the menu panel comes after the setup form closes' );
	}

	public function test_the_menu_keeps_its_own_save_plumbing(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->setup_model( true, true ) );
		$this->assertStringContainsString( '<input type="hidden" name="clubhouse_content_tab" value="menu">', $html );
		$this->assertStringContainsString( '<input type="hidden" name="clubhouse_content_submit" value="1">', $html );
	}

	/** A Content Editor gets the Menu tab and none of the setup tabs. */
	public function test_a_content_editor_sees_only_the_menu_tab(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->setup_model( false, true ) );
		$this->assertStringContainsString( 'data-tab="menu"', $html );
		$this->assertStringContainsString( 'Header menu', $html );
		foreach ( array( 'look', 'visibility', 'members', 'demo' ) as $tab ) {
			$this->assertStringNotContainsString( 'data-tab="' . $tab . '"', $html, "no {$tab} tab" );
		}
		// And no setup form to post to.
		$this->assertStringNotContainsString( 'clubhouse_setup_submit', $html );
	}

	/** Nothing at all for someone with neither capability. */
	public function test_neither_capability_renders_nothing(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Setup_Screen::render( $this->setup_model( false, false ) ) );
	}

	/** The owner dashboard's copy of the screen is unaffected: no menu passed, no menu tab. */
	public function test_the_screen_without_a_menu_model_has_no_menu_tab(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->setup_model( true, false ) );
		$this->assertStringNotContainsString( 'data-tab="menu"', $html );
		$this->assertStringContainsString( 'data-tab="look"', $html );
	}

	/** The guide sends people to the new place. */
	public function test_the_guide_points_at_the_clubhouse_screen(): void {
		$php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/class-guide.php' );
		$this->assertStringContainsString( 'Go to the Menu tab.', $php );
		$this->assertStringNotContainsString( 'Go to the Menu panel.', $php );
	}
}
