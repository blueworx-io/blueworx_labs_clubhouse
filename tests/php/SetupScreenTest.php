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
				'items'     => array( 'look' => true, 'accent' => false, 'club_name' => true, 'logo_favicon' => false, 'social' => false, 'visibility' => false ),
				'completed' => 2,
				'total'     => 6,
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
				'accent' => '#c6f24e', 'accent_default' => '#c6f24e',
				'secondary' => '', 'secondary_default' => '', 'secondary_effective' => '#4ec6f2',
				'club_name' => 'Riverside & Sons', 'logo' => '42',
				'logo_preview' => 'https://club.test/logo.png',
				'favicon' => '', 'favicon_preview' => '',
				'facebook' => 'https://facebook.com/riverside', 'instagram' => '',
				'linkedin' => 'https://linkedin.com/company/riverside',
				'x' => 'https://x.com/riverside',
			),
			'inventory'     => Blueworx_Clubhouse_Setup_Sections::inventory(),
			'visibility'    => array( 'pages' => array( 'events' => false ), 'sections' => array( 'home.ticker' => false ) ),
		);
	}

	public function test_renders_nonce_action_and_progress_out_of_six(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="_wpnonce" value="NONCE123"', $html );
		$this->assertStringContainsString( 'action="https://club.test/wp-admin/admin.php?page=clubhouse-setup"', $html );
		$this->assertStringContainsString( '2 of 6', $html );
	}

	public function test_owner_sees_two_tabs_and_no_demo(): void {
		// Default model has can_demo unset/false — the owner view.
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'data-tab="look"', $html );
		$this->assertStringContainsString( 'data-tab="visibility"', $html );
		$this->assertStringNotContainsString( 'data-tab="demo"', $html );
		$this->assertStringNotContainsString( 'clubhouse_demo_active', $html );
	}

	public function test_admin_sees_the_demo_tab_and_toggle(): void {
		$model = $this->model();
		$model['can_demo']    = true;
		$model['demo_active'] = true;
		$html = Blueworx_Clubhouse_Setup_Screen::render( $model );
		$this->assertStringContainsString( 'data-tab="demo"', $html );
		$this->assertMatchesRegularExpression( '/name="clubhouse_demo_active"[^>]*checked/', $html );
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
		// Counted from the live inventory rather than pinned: the Booking page is
		// only in it when LatePoint is installed, so a literal would encode which
		// integrations the test machine happens to have.
		$expected = array_sum( array_map(
			static fn( array $p ): int => count( $p['sections'] ),
			Blueworx_Clubhouse_Setup_Sections::inventory()
		) );
		$this->assertSame( $expected, substr_count( $html, 'name="clubhouse_section[' ) );
		$this->assertSame( 10, substr_count( $html, 'name="clubhouse_page[' ) );
		$this->assertStringContainsString( 'name="clubhouse_section[home.hero]" value="1" checked', $html );
		$this->assertStringContainsString( 'name="clubhouse_section[home.ticker]" value="1">', $html );
	}

	public function test_save_button_is_never_disabled(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'clubhouse_setup_submit', $html );
		$this->assertDoesNotMatchRegularExpression( '/<button[^>]*type="submit"[^>]*disabled/', $html );
	}

	public function test_renders_error_notice(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'notice notice-error', $html );
		$this->assertStringContainsString( 'That accent is too low-contrast.', $html );
	}

	public function test_seeded_style_block_carries_unescaped_font_quote(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( '.clubhouse-setup{', $html );
		// The <style> block is raw CSS text — the font token must NOT be HTML-entity-escaped
		// (browsers do not decode character references inside a <style> element), while the
		// look-card preview `style="…"` attributes legitimately contain the escaped form.
		$this->assertMatchesRegularExpression( '/\.clubhouse-setup\{[^}]*--font-display:\x27Syne\x27/', $html );
	}

	/**
	 * Role tags sit in the top bar, under the page title. Passed in as prebuilt
	 * markup rather than decided here: the screen is WordPress-free and has no way
	 * to ask who is looking, so the controller hands it '' for anyone but an
	 * administrator — and an owner's Setup screen must not leak the access map.
	 */
	public function test_role_tags_render_in_the_top_bar_when_supplied(): void {
		$model              = $this->model();
		$model['role_tags'] = Blueworx_Clubhouse_Access_Screen::role_tags( array( 'Administrator', 'ClubHouse - Owner' ) );
		$html               = Blueworx_Clubhouse_Setup_Screen::render( $model );

		$this->assertStringContainsString( 'class="clubhouse-roletags"', $html );
		// Inside the head's title block, not floating elsewhere on the page.
		$this->assertMatchesRegularExpression( '/clubhouse-head__titles.*clubhouse-roletags.*<\/div>/s', $html );
	}

	public function test_no_role_tags_for_anyone_but_an_administrator(): void {
		$model              = $this->model();
		$model['role_tags'] = '';
		$this->assertStringNotContainsString( 'clubhouse-roletag', Blueworx_Clubhouse_Setup_Screen::render( $model ) );

		// Absent entirely, not merely empty — an older caller's model must not fatal.
		unset( $model['role_tags'] );
		$this->assertStringNotContainsString( 'clubhouse-roletag', Blueworx_Clubhouse_Setup_Screen::render( $model ) );
	}

	/**
	 * Both colour settings are pickers, and neither is a bare text box. The markup
	 * stays a labelled text input carrying the hex — that is what posts, what
	 * somebody who knows their brand hex can type, and what still works with
	 * JavaScript off — plus the data Iris needs to upgrade it in place.
	 */
	public function test_both_colour_settings_are_pickers_with_a_reset_target(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );

		foreach ( array( 'clubhouse_accent', 'clubhouse_secondary' ) as $name ) {
			$this->assertMatchesRegularExpression(
				'/<input type="text" id="' . $name . '" name="' . $name . '"[^>]*class="clubhouse-input clubhouse-color"/',
				$html,
				$name
			);
			$this->assertMatchesRegularExpression( '/id="' . $name . '"[^>]*data-default-color="/', $html, $name );
			$this->assertStringContainsString( 'data-contrast-for="' . $name . '"', $html, $name );
		}

		// Each field drives its own custom property, or the live preview repaints
		// the wrong thing.
		$this->assertMatchesRegularExpression( '/id="clubhouse_accent"[^>]*data-token="--color-accent"/', $html );
		$this->assertMatchesRegularExpression( '/id="clubhouse_secondary"[^>]*data-token="--color-secondary"/', $html );
	}

	/** No colour setting is left as a bare text box — the issue's explicit ask. */
	public function test_no_colour_field_is_a_plain_text_box(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertSame( 2, substr_count( $html, 'clubhouse-input clubhouse-color' ) );
		$this->assertStringNotContainsString( 'id="clubhouse-accent-swatch"', $html, 'the old mirror-only swatch is gone' );
	}

	/** The preset swatches and the look's own surfaces reach the picker as JSON. */
	public function test_the_picker_island_carries_presets_and_the_shell_to_judge_against(): void {
		$model                  = $this->model();
		$model['color_palette'] = array( '#c6f24e', '#1d4ed8' );
		$html                   = Blueworx_Clubhouse_Setup_Screen::render( $model );

		$this->assertStringContainsString( 'id="clubhouse-color-picker"', $html );
		$this->assertMatchesRegularExpression( '/clubhouse-color-picker"[^>]*>\{.*#1d4ed8/s', $html );
		// The contrast check needs the surfaces the colour will actually sit on.
		$this->assertMatchesRegularExpression( '/clubhouse-color-picker"[^>]*>\{.*"shell"/s', $html );
	}

	/**
	 * An unset secondary says what it is currently resolving to. "Not set" alone
	 * leaves an owner guessing what colour their site is actually using.
	 */
	public function test_an_unset_secondary_shows_the_colour_being_derived(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( '#4ec6f2', $html );
		$this->assertStringContainsString( 'derived from your primary colour', $html );
	}

	/** A chosen secondary needs no such note — the field shows the answer. */
	public function test_a_chosen_secondary_shows_no_derived_note(): void {
		$model                          = $this->model();
		$model['branding']['secondary'] = '#1d4ed8';
		$html                           = Blueworx_Clubhouse_Setup_Screen::render( $model );
		$this->assertStringNotContainsString( 'derived from your primary colour', $html );
	}

	/**
	 * A blank "after signing in" quietly does two different things depending on
	 * whether a shop is connected, so the screen has to say which one is in
	 * force rather than leave an owner to discover it.
	 */
	public function test_a_club_with_a_shop_is_told_members_land_on_the_dashboard(): void {
		$model                            = $this->model();
		$model['members']['dashboard_url'] = 'https://club.test/customer-dashboard/';
		$html                              = Blueworx_Clubhouse_Setup_Screen::render( $model );
		$this->assertStringContainsString( 'account dashboard', $html );
	}

	public function test_a_club_with_no_shop_is_told_members_land_on_the_front_page(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringNotContainsString( 'account dashboard', $html );
		$this->assertStringContainsString( 'blank to send them to your front page', $html );
	}
}
