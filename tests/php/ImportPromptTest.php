<?php
// tests/php/ImportPromptTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportPromptTest extends TestCase {

	private function md(): string {
		return Blueworx_Clubhouse_Import_Prompt::markdown( '9.9.9' );
	}

	public function test_it_states_the_format_version_and_plugin_version(): void {
		$md = $this->md();
		$this->assertStringContainsString( '"clubhouse_import": 1', $md );
		$this->assertStringContainsString( '"generated_for": "9.9.9"', $md );
	}

	public function test_it_names_the_output_file(): void {
		$this->assertStringContainsString( 'clubhouse-import.json', $this->md() );
	}

	public function test_it_tells_the_assistant_not_to_invent_facts(): void {
		$this->assertStringContainsString( 'Never invent', $this->md() );
	}

	public function test_it_tells_the_assistant_to_omit_what_was_not_discussed(): void {
		$this->assertStringContainsString( 'Leave out anything you did not discuss', $this->md() );
	}

	/**
	 * The lockstep guarantee: every field the plugin can store is described in
	 * the prompt. If this fails after a catalogue change, the prompt generator
	 * has stopped covering the catalogue — fix the generator, never the test.
	 */
	public function test_every_catalogue_field_key_appears(): void {
		$md = $this->md();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$address = (string) $section['store_page'] . '.' . (string) $section['key'];
				$this->assertStringContainsString( $address, $md, 'missing section ' . $address );
				foreach ( ( $section['fields'] ?? array() ) as $field ) {
					$this->assertStringContainsString( '`' . $field['key'] . '`', $md, 'missing field ' . $field['key'] );
				}
				foreach ( ( $section['loop']['fields'] ?? array() ) as $field ) {
					$this->assertStringContainsString( '`' . $field['key'] . '`', $md, 'missing loop field ' . $field['key'] );
				}
			}
		}
	}

	public function test_every_collection_type_and_meta_key_appears(): void {
		$md = $this->md();
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			$this->assertStringContainsString( $type, $md, 'missing collection ' . $type );
			foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
				$this->assertStringContainsString( '`' . $field['key'] . '`', $md, 'missing meta ' . $field['key'] );
			}
		}
	}

	public function test_loop_sections_are_described_as_repeatable(): void {
		$md = $this->md();
		// Membership tiers is a loop whose item is called "Tier".
		$this->assertMatchesRegularExpression( '/repeatable list of .{0,20}Tier/i', $md );
	}

	public function test_image_fields_ask_for_a_public_url(): void {
		$this->assertStringContainsString( 'public image URL', $this->md() );
	}

	public function test_select_options_are_listed(): void {
		// The event status field offers upcoming or past.
		$this->assertMatchesRegularExpression( '/one of:.{0,40}upcoming/', $this->md() );
	}

	public function test_sections_backed_by_a_collection_say_so(): void {
		// Home's sponsors section is a linkout to the sponsors collection.
		$this->assertStringContainsString( 'managed as a collection', $this->md() );
	}

	/**
	 * Pins the image-object shape in the "Rules for the file" prose. An
	 * interpolating heredoc's braces are already literal, so doubling them
	 * (`{{ … }}`) does not collapse to single braces — it renders literally
	 * doubled, contradicting the correctly single-braced JSON example below it.
	 */
	public function test_the_rules_prose_describes_a_single_braced_image_object(): void {
		$md = $this->md();
		$this->assertStringContainsString( '{ "url":', $md );
		$this->assertStringNotContainsString( '{{', $md );
	}
}
