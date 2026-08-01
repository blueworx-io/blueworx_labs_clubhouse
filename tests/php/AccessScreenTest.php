<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** The read-only access page and the top-bar role tags. */
final class AccessScreenTest extends TestCase {

	/** @return array<string,mixed> */
	private function model(): array {
		return array(
			'users' => array(
				array(
					'login' => 'jo',
					'name'  => 'Jo Bailey',
					'email' => 'jo@club.test',
					'roles' => array( 'ClubHouse - Owner' ),
					'pages' => array( 'Clubhouse Setup', 'Club Content', 'Import', 'Collections' ),
				),
				array(
					'login' => 'sam',
					'name'  => 'Sam Reid',
					'email' => 'sam@club.test',
					'roles' => array( 'ClubHouse - Content Editor' ),
					'pages' => array( 'Collections' ),
				),
			),
			'roles' => array(
				array( 'label' => 'Administrator', 'pages' => array( 'Clubhouse Setup', 'Collections' ) ),
				array( 'label' => 'ClubHouse - Content Editor', 'pages' => array( 'Collections' ) ),
			),
			'pages' => array(
				array( 'label' => 'Clubhouse Setup', 'description' => 'Base look and branding.', 'access' => array( 'Administrator' ) ),
				array( 'label' => 'Nothing', 'description' => 'Unreachable.', 'access' => array() ),
			),
		);
	}

	public function test_it_lists_every_user_their_role_and_their_sections(): void {
		$html = Blueworx_Clubhouse_Access_Screen::render( $this->model() );
		$this->assertStringContainsString( 'Jo Bailey', $html );
		$this->assertStringContainsString( 'jo', $html );
		$this->assertStringContainsString( 'ClubHouse - Owner', $html );
		$this->assertStringContainsString( 'Sam Reid', $html );
		$this->assertStringContainsString( 'ClubHouse - Content Editor', $html );
		$this->assertStringContainsString( 'Clubhouse Setup', $html );
		$this->assertStringContainsString( 'Collections', $html );
	}

	/**
	 * Read-only, and provably so: no form, no submit control, no nonce. Roles are
	 * managed on the Users screen — a page that both reports access and edits it
	 * invites somebody to grant it by accident.
	 */
	public function test_the_page_cannot_change_anything(): void {
		$html = Blueworx_Clubhouse_Access_Screen::render( $this->model() );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( '<input', $html );
		$this->assertStringNotContainsString( '<button', $html );
		$this->assertStringNotContainsString( '<select', $html );
		$this->assertStringNotContainsString( 'nonce', $html );
	}

	/** An empty access list says "Nobody" rather than leaving a blank cell that reads as missing data. */
	public function test_an_empty_list_says_so(): void {
		$html = Blueworx_Clubhouse_Access_Screen::render( $this->model() );
		$this->assertStringContainsString( 'Nobody', $html );
	}

	/** No ClubHouse users is a legitimate state, not an error — say which roles would qualify. */
	public function test_no_clubhouse_users_explains_itself(): void {
		$model          = $this->model();
		$model['users'] = array();
		$html           = Blueworx_Clubhouse_Access_Screen::render( $model );
		$this->assertStringContainsString( 'Nobody holds a ClubHouse role yet', $html );
		$this->assertStringContainsString( Blueworx_Clubhouse_Owner_Capabilities::DISPLAY, $html );
		$this->assertStringContainsString( Blueworx_Clubhouse_Owner_Capabilities::EDITOR_DISPLAY, $html );
	}

	/** User-supplied names reach the page, so they are escaped. */
	public function test_user_names_are_escaped(): void {
		$model                     = $this->model();
		$model['users'][0]['name'] = '<script>alert(1)</script>';
		$html                      = Blueworx_Clubhouse_Access_Screen::render( $model );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_role_tags_render_one_chip_per_role(): void {
		$html = Blueworx_Clubhouse_Access_Screen::role_tags( array( 'Administrator', 'ClubHouse - Owner' ) );
		$this->assertStringContainsString( 'class="clubhouse-roletags"', $html );
		$this->assertSame( 2, substr_count( $html, 'class="clubhouse-roletag"' ) );
		$this->assertStringContainsString( 'Administrator', $html );
		$this->assertStringContainsString( 'ClubHouse - Owner', $html );
	}

	/** Nothing to say, nothing rendered — no empty chrome in the top bar. */
	public function test_no_roles_renders_no_tag_strip(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Access_Screen::role_tags( array() ) );
	}
}
