<?php
// tests/php/ImportScreenTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportScreenTest extends TestCase {

	/** @param array<string,mixed> $overrides */
	private function model( array $overrides = array() ): array {
		return array_merge( array(
			'state'         => 'start',
			'download_url'  => 'https://club.test/wp-admin/admin-post.php?action=clubhouse_import_prompt',
			'action_url'    => 'https://club.test/wp-admin/admin.php?page=clubhouse-import',
			'nonce_field'   => '<input type="hidden" name="_wpnonce" value="abc">',
			'error'         => '',
			'rows'          => array(),
			'warnings'      => array(),
			'images_needed' => array(),
			'max_upload'    => '1 MB',
		), $overrides );
	}

	/** The title must share Content/Setup's class, or admin-content.css's rule for it never matches. */
	public function test_the_page_title_uses_the_shared_heading_class(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model() );
		$this->assertStringContainsString( '<h1 class="clubhouse-head__h1">Import your content</h1>', $html );
	}

	public function test_start_state_offers_the_prompt_and_an_upload(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model() );
		$this->assertStringContainsString( 'admin-post.php?action=clubhouse_import_prompt', $html );
		$this->assertStringContainsString( 'type="file"', $html );
		$this->assertStringContainsString( 'name="clubhouse_import_file"', $html );
		$this->assertStringContainsString( 'enctype="multipart/form-data"', $html );
		$this->assertStringContainsString( '1 MB', $html );
	}

	public function test_start_state_has_no_apply_button(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model() );
		$this->assertStringNotContainsString( 'clubhouse_import_apply', $html );
	}

	public function test_the_nonce_field_is_emitted_as_markup(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model() );
		$this->assertStringContainsString( '<input type="hidden" name="_wpnonce" value="abc">', $html );
	}

	public function test_an_error_is_shown_and_escaped(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model( array(
			'error' => 'This file is not a ClubHouse import file. <script>',
		) ) );
		$this->assertStringContainsString( 'This file is not a ClubHouse import file.', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_preview_state_lists_rows_and_offers_apply(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model( array(
			'state' => 'preview',
			'rows'  => array( array( 'label' => 'Global · Hero', 'detail' => '5 fields' ) ),
		) ) );
		$this->assertStringContainsString( 'Global · Hero', $html );
		$this->assertStringContainsString( '5 fields', $html );
		$this->assertStringContainsString( 'clubhouse_import_apply', $html );
		$this->assertStringContainsString( 'clubhouse_import_cancel', $html );
	}

	public function test_preview_rows_are_escaped(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model( array(
			'state' => 'preview',
			'rows'  => array( array( 'label' => '<img src=x onerror=1>', 'detail' => '1 field' ) ),
		) ) );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringContainsString( '&lt;img src=x', $html );
	}

	public function test_warnings_are_listed_and_escaped(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model( array(
			'state'    => 'preview',
			'rows'     => array( array( 'label' => 'Global · Hero', 'detail' => '1 field' ) ),
			'warnings' => array( 'Ignored unknown section "home/<b>".' ),
		) ) );
		$this->assertStringContainsString( 'Ignored unknown section', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function test_a_preview_with_no_rows_says_so_and_hides_apply(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model( array( 'state' => 'preview' ) ) );
		$this->assertStringContainsString( 'nothing to import', $html );
		$this->assertStringNotContainsString( 'clubhouse_import_apply', $html );
	}

	public function test_result_state_lists_what_changed_and_the_images_still_needed(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model( array(
			'state'         => 'result',
			'rows'          => array( array( 'label' => 'Sports', 'detail' => '4 entries created' ) ),
			'images_needed' => array( array( 'label' => 'Global · Hero — Background image' ) ),
		) ) );
		$this->assertStringContainsString( '4 entries created', $html );
		$this->assertStringContainsString( 'Global · Hero — Background image', $html );
		$this->assertStringNotContainsString( 'clubhouse_import_apply', $html );
	}

	public function test_a_javascript_download_url_is_refused(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model( array(
			'download_url' => 'javascript:alert(1)',
		) ) );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_a_tab_obfuscated_javascript_download_url_is_refused(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model( array(
			'download_url' => "java\tscript:alert(1)",
		) ) );
		$this->assertStringNotContainsString( "java\tscript:alert(1)", $html );
		$this->assertStringNotContainsString( 'script:', $html );
	}

	public function test_a_newline_obfuscated_javascript_download_url_is_refused(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model( array(
			'download_url' => "java\nscript:alert(1)",
		) ) );
		$this->assertStringNotContainsString( "java\nscript:alert(1)", $html );
		$this->assertStringNotContainsString( 'script:', $html );
	}

	public function test_an_ordinary_https_download_url_still_renders(): void {
		$html = Blueworx_Clubhouse_Import_Screen::render( $this->model( array(
			'download_url' => 'https://club.test/wp-admin/admin-post.php?action=clubhouse_import_prompt',
		) ) );
		$this->assertStringContainsString( 'https://club.test/wp-admin/admin-post.php?action=clubhouse_import_prompt', $html );
	}
}
