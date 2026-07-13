<?php
// tests/php/SetupScreenTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupScreenTest extends TestCase {

	private function model(): array {
		return array(
			'nonce_field' => '<input type="hidden" name="_wpnonce" value="NONCE123">',
			'action_url'  => 'https://club.test/wp-admin/admin.php?page=clubhouse-setup',
			'notices'     => array( array( 'type' => 'error', 'text' => 'That accent is too low-contrast.' ) ),
			'progress'    => array(
				'items'     => array( 'look' => true, 'accent' => false, 'club_name' => true, 'logo' => false, 'facebook' => false, 'instagram' => false ),
				'completed' => 2,
				'total'     => 6,
			),
			'looks'       => array(
				array( 'slug' => 'court-side', 'name' => 'Court Side', 'description' => 'Bright & playful.', 'active' => true ),
				array( 'slug' => 'members-house', 'name' => "Members' House", 'description' => 'Editorial.', 'active' => false ),
				array( 'slug' => 'floodlight', 'name' => 'Floodlight', 'description' => 'Dark night-match.', 'active' => false ),
			),
			'branding'    => array(
				'accent' => '#c6f24e', 'club_name' => 'Riverside & Sons', 'logo' => '42',
				'logo_preview' => 'https://club.test/wp-content/uploads/logo.png',
				'facebook' => 'https://facebook.com/riverside', 'instagram' => '',
			),
			'inventory'   => Blueworx_Clubhouse_Setup_Sections::inventory(),
			'visibility'  => array(
				'pages'    => array( 'events' => false ),
				'sections' => array( 'home.ticker' => false ),
			),
		);
	}

	public function test_renders_nonce_and_action_and_progress(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="_wpnonce" value="NONCE123"', $html );
		$this->assertStringContainsString( 'action="https://club.test/wp-admin/admin.php?page=clubhouse-setup"', $html );
		$this->assertStringContainsString( '2 of 6', $html );
	}

	public function test_renders_a_card_per_look_with_active_marked(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertSame( 3, substr_count( $html, 'name="clubhouse_look"' ) );
		$this->assertStringContainsString( 'value="court-side" checked', $html );
	}

	public function test_renders_branding_fields_with_current_values_escaped(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="clubhouse_accent"', $html );
		$this->assertStringContainsString( 'value="Riverside &amp; Sons"', $html );
		$this->assertStringNotContainsString( 'Riverside & Sons"', $html );
		$this->assertStringContainsString( 'name="clubhouse_facebook"', $html );
		$this->assertStringContainsString( 'name="clubhouse_logo"', $html );
	}

	public function test_renders_a_checkbox_per_section_plus_per_page(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertSame( 45, substr_count( $html, 'name="clubhouse_section[' ) );
		$this->assertSame( 9, substr_count( $html, 'name="clubhouse_page[' ) );
		$this->assertStringContainsString( 'name="clubhouse_section[home.hero]" value="1" checked', $html );
		$this->assertStringContainsString( 'name="clubhouse_section[home.ticker]" value="1">', $html );
	}

	public function test_renders_error_notice(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'notice notice-error', $html );
		$this->assertStringContainsString( 'That accent is too low-contrast.', $html );
	}

	public function test_render_includes_demo_mode_toggle(): void {
		$model = $this->model();
		$model['demo_active'] = false;
		$html = Blueworx_Clubhouse_Setup_Screen::render( $model );
		$this->assertStringContainsString( 'name="clubhouse_demo_active"', $html );
		$this->assertStringContainsString( 'Demo mode', $html );
	}

	public function test_render_checks_demo_toggle_when_active(): void {
		$model = $this->model();
		$model['demo_active'] = true;
		$html = Blueworx_Clubhouse_Setup_Screen::render( $model );
		$this->assertMatchesRegularExpression( '/name="clubhouse_demo_active"[^>]*checked/', $html );
	}
}
