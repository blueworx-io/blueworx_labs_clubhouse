<?php

use PHPUnit\Framework\TestCase;

/**
 * Issue #111, the half of it that belongs to this plugin: the committee on
 * About could only ever be initials, because a person had no photo field at
 * all. A club can now set one; a club that does not still gets the initials
 * block, which is a deliberate look rather than a missing picture.
 *
 * (The other half — the coach cards on Book a court, where one card carries an
 * extra link and pushes its Book button out of line — is LatePoint's own
 * markup, from its [latepoint_resources items="agents"] shortcode.)
 */
final class PersonPhotoTest extends TestCase {

	/** @param array<int,array<string,string>> $people */
	private function grid( array $people ): string {
		return Blueworx_Clubhouse_Sections::people_grid( array(
			'eyebrow' => 'Who runs the club',
			'heading' => 'The committee',
			'people'  => $people,
		) );
	}

	public function test_a_person_with_a_photo_gets_the_photo(): void {
		$html = $this->grid( array(
			array( 'name' => 'Kathy Smith', 'role' => 'Chair', 'email' => '', 'photo' => 'https://example.test/kathy.jpg' ),
		) );
		$this->assertStringContainsString( 'src="https://example.test/kathy.jpg"', $html );
		$this->assertStringContainsString( 'class="ch-person__avatar ch-person__photo"', $html );
	}

	/** The photo is the person, so it is named, not hidden from screen readers. */
	public function test_the_photo_carries_the_persons_name(): void {
		$html = $this->grid( array(
			array( 'name' => 'Kathy Smith', 'role' => 'Chair', 'email' => '', 'photo' => 'https://example.test/kathy.jpg' ),
		) );
		$this->assertStringContainsString( 'alt="Kathy Smith"', $html );
		$this->assertStringNotContainsString( 'ch-person__photo" src="https://example.test/kathy.jpg" aria-hidden', $html );
	}

	public function test_no_photo_still_means_initials(): void {
		$html = $this->grid( array(
			array( 'name' => 'Kathy Smith', 'role' => 'Chair', 'email' => '', 'photo' => '' ),
		) );
		$this->assertStringContainsString( 'ch-avatar" aria-hidden="true">KS<', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	/** A club that has never heard of the field renders as it always did. */
	public function test_a_person_without_the_key_at_all_is_fine(): void {
		$html = $this->grid( array(
			array( 'name' => 'Kathy Smith', 'role' => 'Chair', 'email' => '' ),
		) );
		$this->assertStringContainsString( 'ch-avatar', $html );
	}

	/** Half a committee photographed must still line up with the other half. */
	public function test_photos_and_initials_share_the_same_square(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/looks/base.css' );
		$this->assertMatchesRegularExpression( '/\.ch-person__photo\{[^}]*object-fit:cover/', $css );
		$this->assertMatchesRegularExpression( '/\.ch-person__photo\{[^}]*width:100%/', $css );
	}

	public function test_the_photo_is_an_editable_field_on_a_person(): void {
		$fields = Blueworx_Clubhouse_Collection_Meta::fields( 'clubhouse_person' );
		$keys   = array_column( $fields, 'key' );
		$this->assertContains( 'photo', $keys );
		$photo = $fields[ array_search( 'photo', $keys, true ) ];
		$this->assertSame( 'media', $photo['type'], 'chosen from the media library, not typed as a URL' );
	}

	/** The field has to be registered as meta too, or nothing it holds is saved. */
	public function test_the_photo_is_stored_against_the_person(): void {
		$php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/collections/class-collection-types.php' );
		$this->assertMatchesRegularExpression(
			"/'clubhouse_person'\s*=> array\([^)]*'photo'/",
			$php,
			'photo is registered as meta on the person type'
		);
	}

	/**
	 * Both places a club's people appear have to show the photo, not just the
	 * grid in isolation — the committee on About and the directory on Contact.
	 */
	public function test_both_pages_that_list_people_pass_photos_through(): void {
		$collections = new Blueworx_Clubhouse_Photographed_Collections();
		foreach ( array( 'about', 'contact' ) as $page ) {
			$html = Blueworx_Clubhouse_Test_Site::page( $page, new Blueworx_Clubhouse_Fake_Storage(), '', $collections );
			$this->assertStringContainsString( 'src="https://example.test/kathy.jpg"', $html, $page );
		}
	}
}

/** A club whose one committee member has sat for a photograph. */
final class Blueworx_Clubhouse_Photographed_Collections implements Blueworx_Clubhouse_Collections {

	public function sports(): array {
		return Blueworx_Clubhouse_Demo_Content::sports();
	}
	public function teams(): array {
		return Blueworx_Clubhouse_Demo_Content::teams();
	}
	public function fixtures(): array {
		return Blueworx_Clubhouse_Demo_Content::fixtures();
	}
	public function events(): array {
		return Blueworx_Clubhouse_Demo_Content::events();
	}
	public function sponsors(): array {
		return Blueworx_Clubhouse_Demo_Content::sponsors();
	}
	public function people(): array {
		return array(
			array(
				'name'           => 'Kathy Smith',
				'committee_role' => 'Chair',
				'directory_role' => 'Press',
				'email'          => 'chair@clubhouse.example',
				'photo'          => 'https://example.test/kathy.jpg',
			),
		);
	}
}
