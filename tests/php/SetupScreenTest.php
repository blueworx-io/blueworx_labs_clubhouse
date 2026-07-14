<?php
// tests/php/SetupScreenTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupScreenTest extends TestCase {

	private function model(): array {
		$look = new Blueworx_Clubhouse_Court_Side();
		$tokens = array(
			'court-side'    => $look->tokens() + array( '--color-accent-deep' => '#3a6a00' ),
			'members-house' => ( new Blueworx_Clubhouse_Members_House() )->tokens() + array( '--color-accent-deep' => '#3a6a00' ),
			'floodlight'    => ( new Blueworx_Clubhouse_Floodlight() )->tokens() + array( '--color-accent-deep' => '#cfe86a' ),
		);
		return array(
			'nonce_field'   => '<input type="hidden" name="_wpnonce" value="NONCE123">',
			'action_url'    => 'https://club.test/wp-admin/admin.php?page=clubhouse-setup',
			'notices'       => array( array( 'type' => 'error', 'text' => 'That accent is too low-contrast.' ) ),
			'progress'      => array(
				'items'     => array( 'look' => true, 'accent' => false, 'club_name' => true, 'logo_favicon' => false, 'social' => false ),
				'completed' => 2,
				'total'     => 5,
			),
			'looks'         => array(
				array( 'slug' => 'court-side', 'name' => 'Court Side', 'description' => 'Bright & playful.', 'active' => true ),
				array( 'slug' => 'members-house', 'name' => "Members' House", 'description' => 'Editorial.', 'active' => false ),
				array( 'slug' => 'floodlight', 'name' => 'Floodlight', 'description' => 'Dark night-match.', 'active' => false ),
			),
			'active_slug'   => 'court-side',
			'look_tokens'   => $tokens,
			'font_face_css' => "@font-face{font-family:'Syne';src:url(x)}",
			'branding'      => array(
				'accent' => '#c6f24e', 'club_name' => 'Riverside & Sons', 'logo' => '42',
				'logo_preview' => 'https://club.test/logo.png',
				'favicon' => '', 'favicon_preview' => '',
				'facebook' => 'https://facebook.com/riverside', 'instagram' => '',
				'linkedin' => 'https://linkedin.com/company/riverside',
			),
			'inventory'     => Blueworx_Clubhouse_Setup_Sections::inventory(),
			'visibility'    => array( 'pages' => array( 'events' => false ), 'sections' => array( 'home.ticker' => false ) ),
			'demo_active'   => false,
		);
	}

	public function test_renders_nonce_action_and_progress_out_of_five(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="_wpnonce" value="NONCE123"', $html );
		$this->assertStringContainsString( 'action="https://club.test/wp-admin/admin.php?page=clubhouse-setup"', $html );
		$this->assertStringContainsString( '2 of 5', $html );
	}

	public function test_renders_three_tabs(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'data-tab="look"', $html );
		$this->assertStringContainsString( 'data-tab="visibility"', $html );
		$this->assertStringContainsString( 'data-tab="demo"', $html );
	}

	public function test_renders_look_cards_with_active_marked_and_token_preview(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertSame( 3, substr_count( $html, 'name="clubhouse_look"' ) );
		$this->assertStringContainsString( 'value="court-side" checked', $html );
		$this->assertStringContainsString( 'clubhouse-look-card__preview', $html );
		$this->assertStringContainsString( '--color-bg:', $html ); // preview carries look tokens inline
	}

	public function test_embeds_look_tokens_json_and_font_faces(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'id="clubhouse-look-tokens"', $html );
		$this->assertStringContainsString( '@font-face', $html );
		$this->assertStringContainsString( 'members-house', $html );
	}

	public function test_renders_branding_incl_favicon_and_linkedin_escaped(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="clubhouse_accent"', $html );
		$this->assertStringContainsString( 'value="Riverside &amp; Sons"', $html );
		$this->assertStringContainsString( 'name="clubhouse_favicon"', $html );
		$this->assertStringContainsString( 'name="clubhouse_linkedin"', $html );
		$this->assertStringContainsString( 'value="https://linkedin.com/company/riverside"', $html );
	}

	public function test_renders_a_toggle_per_section_plus_per_page(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertSame( 45, substr_count( $html, 'name="clubhouse_section[' ) );
		$this->assertSame( 9, substr_count( $html, 'name="clubhouse_page[' ) );
		$this->assertStringContainsString( 'name="clubhouse_section[home.hero]" value="1" checked', $html );
		$this->assertStringContainsString( 'name="clubhouse_section[home.ticker]" value="1">', $html );
	}

	public function test_save_button_is_never_disabled(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'clubhouse_setup_submit', $html );
		$this->assertDoesNotMatchRegularExpression( '/<button[^>]*type="submit"[^>]*disabled/', $html );
	}

	public function test_renders_error_notice_and_demo_toggle(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'notice notice-error', $html );
		$this->assertStringContainsString( 'That accent is too low-contrast.', $html );
		$this->assertStringContainsString( 'name="clubhouse_demo_active"', $html );
	}

	public function test_checks_demo_toggle_when_active(): void {
		$model = $this->model();
		$model['demo_active'] = true;
		$html = Blueworx_Clubhouse_Setup_Screen::render( $model );
		$this->assertMatchesRegularExpression( '/name="clubhouse_demo_active"[^>]*checked/', $html );
	}
}
