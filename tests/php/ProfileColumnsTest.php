<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Custom member fields as columns on the WordPress Users screen (issue #278).
 *
 * A club ordering kit wants every under-14's shirt size on one screen, not one
 * member at a time. Which fields get a column is the owner's choice, field by
 * field — thirty fields would make the screen unreadable.
 */
final class ProfileColumnsTest extends TestCase {

	/** @return array<int,array<string,mixed>> */
	private function fields(): array {
		return Blueworx_Clubhouse_Profile_Fields::sanitise_all( array(
			array( 'label' => 'Shirt size', 'type' => 'select', 'choices' => "S\nM\nL", 'who' => 'member', 'column' => '1' ),
			array( 'label' => 'Squad number', 'type' => 'number', 'who' => 'club', 'column' => '1' ),
			array( 'label' => 'Notes', 'type' => 'textarea', 'who' => 'private', 'column' => '1' ),
			array( 'label' => 'Dietary needs', 'type' => 'multiselect', 'choices' => "Veggie\nNut allergy", 'who' => 'club', 'column' => '1' ),
			array( 'label' => 'Emergency contact', 'type' => 'text', 'who' => 'club' ),
		) );
	}

	/** @param array<int,array<string,mixed>> $fields */
	private function labels( array $fields ): array {
		return array_column( $fields, 'label' );
	}

	public function test_a_field_is_not_a_column_until_the_owner_says_so(): void {
		$shown = Blueworx_Clubhouse_Profile_Columns::shown( $this->fields(), true );
		$this->assertNotContains( 'Emergency contact', $this->labels( $shown ) );
	}

	public function test_the_chosen_fields_are_columns_in_the_order_the_club_set_them(): void {
		$shown = Blueworx_Clubhouse_Profile_Columns::shown( $this->fields(), true );
		$this->assertSame( array( 'Shirt size', 'Squad number', 'Notes', 'Dietary needs' ), $this->labels( $shown ) );
	}

	/**
	 * A private field is the club's own note about a member — the member never
	 * sees it, and neither does anybody who cannot already open that member's
	 * screen and read it there.
	 */
	public function test_a_private_field_has_no_column_for_somebody_who_cannot_see_it(): void {
		$shown = Blueworx_Clubhouse_Profile_Columns::shown( $this->fields(), false );
		$this->assertSame( array( 'Shirt size', 'Squad number', 'Dietary needs' ), $this->labels( $shown ) );
	}

	public function test_a_column_id_is_namespaced_so_it_cannot_collide_with_wordpresss_own(): void {
		$id = Blueworx_Clubhouse_Profile_Columns::column_id( 'shirt_size' );
		$this->assertStringStartsWith( 'clubhouse_', $id );
		$this->assertSame( 'shirt_size', Blueworx_Clubhouse_Profile_Columns::field_key( $id ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Columns::field_key( 'email' ) );
	}

	public function test_the_columns_are_added_after_wordpresss_own(): void {
		$columns = Blueworx_Clubhouse_Profile_Columns::add( array( 'username' => 'Username', 'email' => 'Email' ), $this->fields(), true );
		$this->assertSame(
			array( 'username', 'email', 'clubhouse_shirt_size', 'clubhouse_squad_number', 'clubhouse_notes', 'clubhouse_dietary_needs' ),
			array_keys( $columns )
		);
		$this->assertSame( 'Shirt size', $columns['clubhouse_shirt_size'] );
	}

	// -----------------------------------------------------------------
	// What one cell says
	// -----------------------------------------------------------------

	/** @param array<int,array<string,mixed>> $fields */
	private function field( string $label ): array {
		foreach ( $this->fields() as $field ) {
			if ( $label === $field['label'] ) {
				return $field;
			}
		}
		$this->fail( "no field called $label" );
	}

	public function test_a_plain_answer_is_the_answer(): void {
		$this->assertSame( '7', Blueworx_Clubhouse_Profile_Columns::cell( $this->field( 'Squad number' ), '7' ) );
	}

	/** A member who has not answered reads as not answered, not as a blank cell somebody has to interpret. */
	public function test_an_unanswered_field_says_so(): void {
		$this->assertSame( '—', Blueworx_Clubhouse_Profile_Columns::cell( $this->field( 'Squad number' ), '' ) );
		$this->assertSame( '—', Blueworx_Clubhouse_Profile_Columns::cell( $this->field( 'Dietary needs' ), array() ) );
	}

	public function test_a_pick_several_answer_reads_as_a_list(): void {
		$this->assertSame(
			'Veggie, Nut allergy',
			Blueworx_Clubhouse_Profile_Columns::cell( $this->field( 'Dietary needs' ), array( 'Veggie', 'Nut allergy' ) )
		);
	}

	/** A yes/no field stores '1' and nothing; neither reads as an answer on its own. */
	public function test_a_yes_no_answer_reads_as_yes_or_no(): void {
		$tick = Blueworx_Clubhouse_Profile_Fields::sanitise_all( array(
			array( 'label' => 'Photo consent', 'type' => 'checkbox', 'who' => 'club', 'column' => '1' ),
		) )[0];
		$this->assertSame( 'Yes', Blueworx_Clubhouse_Profile_Columns::cell( $tick, '1' ) );
		$this->assertSame( 'No', Blueworx_Clubhouse_Profile_Columns::cell( $tick, '' ) );
	}

	/** An answer is a member's own typing, and it is printed into a table. */
	public function test_an_answer_is_escaped(): void {
		$out = Blueworx_Clubhouse_Profile_Columns::cell( $this->field( 'Squad number' ), '<script>alert(1)</script>' );
		$this->assertStringNotContainsString( '<script>', $out );
	}

	/** A long answer would push every other column off the screen. */
	public function test_a_long_answer_is_cut_short(): void {
		$out = Blueworx_Clubhouse_Profile_Columns::cell( $this->field( 'Notes' ), str_repeat( 'a', 200 ) );
		$this->assertLessThan( 100, strlen( $out ) );
		$this->assertStringEndsWith( '…', $out );
	}

	// -----------------------------------------------------------------
	// Sorting
	// -----------------------------------------------------------------

	/**
	 * A column sorts where sorting means something. "Pick several" does not:
	 * the answers are stored as a list, and ordering members by a serialised
	 * array puts them in an order nobody asked for and nobody can explain.
	 */
	public function test_only_the_types_that_sort_meaningfully_are_sortable(): void {
		$sortable = Blueworx_Clubhouse_Profile_Columns::sortable( array(), $this->fields(), true );
		$this->assertSame(
			array( 'clubhouse_shirt_size', 'clubhouse_squad_number', 'clubhouse_notes' ),
			array_keys( $sortable )
		);
	}

	public function test_a_private_column_is_not_sortable_for_somebody_who_cannot_see_it(): void {
		$sortable = Blueworx_Clubhouse_Profile_Columns::sortable( array(), $this->fields(), false );
		$this->assertArrayNotHasKey( 'clubhouse_notes', $sortable );
	}

	public function test_sorting_by_a_column_orders_by_that_fields_answers(): void {
		$order = Blueworx_Clubhouse_Profile_Columns::order_by( 'clubhouse_squad_number', $this->fields(), true );
		$this->assertSame( 'clubhouse_profile_squad_number', $order['meta_query']['answer']['key'] );
		$this->assertSame( 'answer', $order['orderby'] );
		$this->assertSame( 'NUMERIC', $order['meta_query']['answer']['type'], 'a number sorts as a number, so 10 comes after 9' );
	}

	public function test_text_sorts_as_text(): void {
		$order = Blueworx_Clubhouse_Profile_Columns::order_by( 'clubhouse_shirt_size', $this->fields(), true );
		$this->assertSame( 'CHAR', $order['meta_query']['answer']['type'] );
	}

	/**
	 * A member who has not answered still has to appear in the list. The short
	 * form of this — `meta_key` plus `orderby => meta_value` — makes WordPress
	 * INNER JOIN on the key and drops every unanswered member from the results,
	 * which reads as members having been deleted. The NOT EXISTS half of the OR
	 * is what makes it a LEFT JOIN instead.
	 *
	 * This pins the shape. What proves the behaviour is the browser spec, which
	 * counts the rows against real WordPress — that is where the short form was
	 * caught losing a member.
	 */
	public function test_members_with_no_answer_are_not_dropped_from_the_list(): void {
		$order = Blueworx_Clubhouse_Profile_Columns::order_by( 'clubhouse_squad_number', $this->fields(), true );
		$this->assertSame( 'OR', $order['meta_query']['relation'] );
		$this->assertSame( 'NOT EXISTS', $order['meta_query']['unanswered']['compare'] );
		$this->assertArrayNotHasKey( 'meta_key', $order, 'meta_key here would re-introduce the inner join' );
	}

	public function test_wordpresss_own_sorting_is_left_alone(): void {
		$this->assertSame( array(), Blueworx_Clubhouse_Profile_Columns::order_by( 'email', $this->fields(), true ) );
		$this->assertSame( array(), Blueworx_Clubhouse_Profile_Columns::order_by( '', $this->fields(), true ) );
	}

	/** Somebody who cannot see a private field cannot sort by it either, however they got the address. */
	public function test_a_private_column_cannot_be_sorted_by_from_the_url(): void {
		$this->assertSame( array(), Blueworx_Clubhouse_Profile_Columns::order_by( 'clubhouse_notes', $this->fields(), false ) );
	}

	// -----------------------------------------------------------------
	// The definition itself
	// -----------------------------------------------------------------

	public function test_the_column_choice_is_stored_with_the_field(): void {
		$fields = $this->fields();
		$this->assertTrue( $fields[0]['column'] );
		$this->assertFalse( $fields[4]['column'], 'a field the owner did not tick must not become a column' );
	}
}
