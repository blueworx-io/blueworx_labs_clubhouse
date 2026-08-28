# Profile Builder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A club defines its own member fields in Clubhouse Setup; members fill in their own on a new Profile page; club staff see all of them on the WordPress user screen.

**Architecture:** Four pure classes hold every rule (the type catalogue, definition sanitising, answer validation, viewer filtering, and the member card's HTML). Three thin WordPress-coupled classes wire them to storage, the member area and wp-admin. Field definitions live in the Clubhouse options row via the existing `Storage` interface; a member's answers live in WordPress user meta, one meta key per field.

**Tech Stack:** PHP 8.1+, WordPress, PHPUnit (unit, WP-free, `tests/php`), Playwright (journeys, `tests/`), PHPCS (`composer lint`).

**Spec:** `docs/superpowers/specs/2026-08-27-profile-builder-design.md`

**Issue:** [#276](../../../../issues/276)

## Global Constraints

- Every new PHP file starts with `<?php`, a path comment, `declare(strict_types=1);`, and the `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard, matching every existing file in `includes/`.
- Classes are `final` and prefixed `Blueworx_Clubhouse_`. Files are `class-<name>.php`, tabs for indent, WordPress array syntax (`array( … )`, not `[]`).
- Pure classes must not call a single WordPress function except the sanitisers already stubbed in `tests/php/wp-stubs.php` (`sanitize_text_field`, `sanitize_textarea_field`, `sanitize_key`, `esc_html`).
- Every new class is added to the require list in `includes/bootstrap.php`, in its own `// Profile.` block placed after the `dashboard/` block.
- Meta keys are `clubhouse_profile_<key>`. The option key is `profile_fields` (the `Options_Storage` prefix makes it `clubhouse_profile_fields`).
- At most **30** fields per club.
- Copy is written for a club owner, not a developer: short sentences, no jargon, no field names shown to members.
- Version bump is **minor** (0.93.1 → 0.94.0), with a matching `CHANGELOG.md` entry.
- Never work on `main`. This plan is executed on the existing `profile-builder` branch.

---

### Task 1: The field type catalogue and definition sanitising

**Files:**
- Create: `includes/profile/class-profile-fields.php`
- Test: `tests/php/ProfileFieldsTest.php`
- Modify: `includes/bootstrap.php` (add the require)

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Blueworx_Clubhouse_Profile_Fields::TYPES` — `array<string,string>` of type key => owner-facing label.
  - `Blueworx_Clubhouse_Profile_Fields::WHO` — `array<string,string>` of who key => owner-facing label. Keys: `member`, `club`, `private`.
  - `Blueworx_Clubhouse_Profile_Fields::MAX_FIELDS` — `int`, 30.
  - `Blueworx_Clubhouse_Profile_Fields::has_choices( string $type ): bool`
  - `Blueworx_Clubhouse_Profile_Fields::key_from_label( string $label, array $taken ): string`
  - `Blueworx_Clubhouse_Profile_Fields::sanitise_one( array $raw, array $taken ): ?array` — returns a complete definition or null if it has no usable label.
  - `Blueworx_Clubhouse_Profile_Fields::sanitise_all( array $rows ): array<int,array<string,mixed>>`
  - A definition is `array{key:string,label:string,type:string,choices:array<int,string>,help:string,required:bool,who:string}`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/ProfileFieldsTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class ProfileFieldsTest extends TestCase {

	public function test_the_seven_types_the_spec_names_are_the_types_offered(): void {
		$this->assertSame(
			array( 'text', 'textarea', 'number', 'date', 'select', 'multiselect', 'checkbox' ),
			array_keys( Blueworx_Clubhouse_Profile_Fields::TYPES )
		);
	}

	public function test_who_fills_it_in_has_exactly_three_settings(): void {
		$this->assertSame( array( 'member', 'club', 'private' ), array_keys( Blueworx_Clubhouse_Profile_Fields::WHO ) );
	}

	public function test_only_the_two_choice_types_take_choices(): void {
		$this->assertTrue( Blueworx_Clubhouse_Profile_Fields::has_choices( 'select' ) );
		$this->assertTrue( Blueworx_Clubhouse_Profile_Fields::has_choices( 'multiselect' ) );
		$this->assertFalse( Blueworx_Clubhouse_Profile_Fields::has_choices( 'text' ) );
	}

	public function test_a_key_is_made_from_the_label(): void {
		$this->assertSame( 'shirt_size', Blueworx_Clubhouse_Profile_Fields::key_from_label( 'Shirt size', array() ) );
	}

	public function test_a_second_field_with_the_same_label_gets_its_own_key(): void {
		$this->assertSame( 'shirt_size_2', Blueworx_Clubhouse_Profile_Fields::key_from_label( 'Shirt size', array( 'shirt_size' ) ) );
	}

	public function test_a_label_of_pure_punctuation_still_yields_a_usable_key(): void {
		$this->assertSame( 'field', Blueworx_Clubhouse_Profile_Fields::key_from_label( '£$%', array() ) );
	}

	public function test_a_field_with_no_label_is_dropped(): void {
		$this->assertNull( Blueworx_Clubhouse_Profile_Fields::sanitise_one( array( 'label' => '   ' ), array() ) );
	}

	public function test_a_bare_row_becomes_a_complete_definition(): void {
		$field = Blueworx_Clubhouse_Profile_Fields::sanitise_one( array( 'label' => 'Shirt size' ), array() );
		$this->assertSame(
			array(
				'key'      => 'shirt_size',
				'label'    => 'Shirt size',
				'type'     => 'text',
				'choices'  => array(),
				'help'     => '',
				'required' => false,
				'who'      => 'member',
			),
			$field
		);
	}

	public function test_an_unknown_type_or_who_falls_back_to_the_safe_default(): void {
		$field = Blueworx_Clubhouse_Profile_Fields::sanitise_one(
			array( 'label' => 'Squad number', 'type' => 'nonsense', 'who' => 'nonsense' ),
			array()
		);
		$this->assertSame( 'text', $field['type'] );
		// 'member' is the safe default: a field nobody declared private must not
		// become private by accident, and a member-editable field leaks nothing.
		$this->assertSame( 'member', $field['who'] );
	}

	public function test_choices_are_one_per_line_and_blank_lines_are_dropped(): void {
		$field = Blueworx_Clubhouse_Profile_Fields::sanitise_one(
			array( 'label' => 'Shirt size', 'type' => 'select', 'choices' => "Small\n\n Medium \nLarge\n" ),
			array()
		);
		$this->assertSame( array( 'Small', 'Medium', 'Large' ), $field['choices'] );
	}

	public function test_a_non_choice_type_keeps_no_choices_even_if_some_were_typed(): void {
		$field = Blueworx_Clubhouse_Profile_Fields::sanitise_one(
			array( 'label' => 'Notes', 'type' => 'textarea', 'choices' => "One\nTwo" ),
			array()
		);
		$this->assertSame( array(), $field['choices'] );
	}

	public function test_an_existing_key_is_kept_so_a_rename_does_not_lose_the_answers(): void {
		$field = Blueworx_Clubhouse_Profile_Fields::sanitise_one(
			array( 'key' => 'shirt_size', 'label' => 'Kit size' ),
			array()
		);
		$this->assertSame( 'shirt_size', $field['key'] );
		$this->assertSame( 'Kit size', $field['label'] );
	}

	public function test_sanitise_all_drops_empty_rows_and_keeps_keys_unique(): void {
		$fields = Blueworx_Clubhouse_Profile_Fields::sanitise_all(
			array(
				array( 'label' => 'Shirt size' ),
				array( 'label' => '' ),
				array( 'label' => 'Shirt size' ),
			)
		);
		$this->assertSame( array( 'shirt_size', 'shirt_size_2' ), array_column( $fields, 'key' ) );
	}

	public function test_no_more_than_thirty_fields_survive(): void {
		$rows = array();
		for ( $i = 1; $i <= 35; $i++ ) {
			$rows[] = array( 'label' => 'Field ' . $i );
		}
		$this->assertCount( 30, Blueworx_Clubhouse_Profile_Fields::sanitise_all( $rows ) );
	}
}
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `vendor/bin/phpunit --filter ProfileFieldsTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Profile_Fields" not found`.

- [ ] **Step 3: Write the implementation**

Create `includes/profile/class-profile-fields.php`:

```php
<?php
// includes/profile/class-profile-fields.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a custom member field can be, and how a submitted definition becomes a
 * valid one.
 *
 * Pure — no WordPress beyond the sanitisers. Every rule about what a field IS
 * lives here; what an ANSWER may be lives in Profile_Values.
 *
 * The key is generated once, from the label, and then never changes. That is
 * the whole reason a definition carries a key at all: an owner rewriting
 * "Shirt size" to "Kit size" must not detach every member's answer from it.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Fields {

	/** The seven types, in the order the builder offers them. */
	public const TYPES = array(
		'text'        => 'Short text',
		'textarea'    => 'Long text',
		'number'      => 'Number',
		'date'        => 'Date',
		'select'      => 'Dropdown (pick one)',
		'multiselect' => 'Dropdown (pick several)',
		'checkbox'    => 'Yes / no',
	);

	/**
	 * Who fills a field in.
	 *
	 * 'member'  — the member's own to write, on their Profile page and in wp-admin.
	 * 'club'    — the member sees the value and cannot change it; staff can.
	 * 'private' — the member never sees it at all; staff can.
	 */
	public const WHO = array(
		'member'  => 'The member fills this in',
		'club'    => 'The club fills this in, and the member can see it',
		'private' => 'The club fills this in, and the member never sees it',
	);

	public const DEFAULT_TYPE = 'text';
	public const DEFAULT_WHO  = 'member';

	/**
	 * Past this, the Profile page stops being a page anyone reads. A cap is a
	 * cheaper conversation than the page it prevents.
	 */
	public const MAX_FIELDS = 30;

	/** Only the two choice types have choices to offer. */
	public static function has_choices( string $type ): bool {
		return in_array( $type, array( 'select', 'multiselect' ), true );
	}

	/**
	 * A permanent key from a label, unique against the keys already taken.
	 *
	 * A label of pure punctuation still has to yield something storable, so the
	 * fallback is 'field' rather than an empty key that would collide with the
	 * next one.
	 *
	 * @param array<int,string> $taken
	 */
	public static function key_from_label( string $label, array $taken ): string {
		$base = strtolower( trim( $label ) );
		$base = (string) preg_replace( '/[^a-z0-9]+/', '_', $base );
		$base = trim( $base, '_' );
		if ( '' === $base ) {
			$base = 'field';
		}
		$base = substr( $base, 0, 40 );

		$key = $base;
		$n   = 1;
		while ( in_array( $key, $taken, true ) ) {
			++$n;
			$key = $base . '_' . $n;
		}
		return $key;
	}

	/**
	 * One submitted row into a complete definition, or null if it has no label.
	 *
	 * A row with no label is an empty row the owner never filled in — the add
	 * button leaves one behind every time — so it is dropped rather than saved
	 * as a nameless field.
	 *
	 * @param array<string,mixed> $raw
	 * @param array<int,string>   $taken
	 * @return array{key:string,label:string,type:string,choices:array<int,string>,help:string,required:bool,who:string}|null
	 */
	public static function sanitise_one( array $raw, array $taken ): ?array {
		$label = sanitize_text_field( (string) ( $raw['label'] ?? '' ) );
		if ( '' === $label ) {
			return null;
		}

		$type = (string) ( $raw['type'] ?? '' );
		if ( ! array_key_exists( $type, self::TYPES ) ) {
			$type = self::DEFAULT_TYPE;
		}

		$who = (string) ( $raw['who'] ?? '' );
		if ( ! array_key_exists( $who, self::WHO ) ) {
			$who = self::DEFAULT_WHO;
		}

		// An existing key is authoritative: this row is a field the club already
		// has, and its answers are stored under that key.
		$key = sanitize_key( (string) ( $raw['key'] ?? '' ) );
		if ( '' === $key || in_array( $key, $taken, true ) ) {
			$key = self::key_from_label( $label, $taken );
		}

		return array(
			'key'      => $key,
			'label'    => $label,
			'type'     => $type,
			'choices'  => self::has_choices( $type ) ? self::choices_from_text( (string) ( $raw['choices'] ?? '' ) ) : array(),
			'help'     => sanitize_text_field( (string) ( $raw['help'] ?? '' ) ),
			'required' => ! empty( $raw['required'] ),
			'who'      => $who,
		);
	}

	/**
	 * Every submitted row into a definition list, capped and with unique keys.
	 *
	 * @param array<int,mixed> $rows
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitise_all( array $rows ): array {
		$out   = array();
		$taken = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$field = self::sanitise_one( $row, $taken );
			if ( null === $field ) {
				continue;
			}
			$taken[] = $field['key'];
			$out[]   = $field;
			if ( count( $out ) >= self::MAX_FIELDS ) {
				break;
			}
		}
		return $out;
	}

	/** One choice per line, blanks dropped. @return array<int,string> */
	private static function choices_from_text( string $text ): array {
		$out = array();
		foreach ( preg_split( '/\R/', $text ) ?: array() as $line ) {
			$line = sanitize_text_field( (string) $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
```

- [ ] **Step 4: Add it to the bootstrap**

In `includes/bootstrap.php`, immediately after the `dashboard/` require block (the line requiring `dashboard/class-commerce-pages.php`), add:

```php

// Profile.
require_once __DIR__ . '/profile/class-profile-fields.php';
```

- [ ] **Step 5: Run the test and watch it pass**

Run: `vendor/bin/phpunit --filter ProfileFieldsTest`
Expected: PASS, all tests green.

- [ ] **Step 6: Commit**

```bash
git add includes/profile/class-profile-fields.php tests/php/ProfileFieldsTest.php includes/bootstrap.php
git commit -m "The field types a club can choose from, and what a field definition is"
```

---

### Task 2: Validating answers, and who may see which field

**Files:**
- Create: `includes/profile/class-profile-values.php`
- Test: `tests/php/ProfileValuesTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Profile_Fields` (definition shape, `has_choices`).
- Produces:
  - `Blueworx_Clubhouse_Profile_Values::clean( array $field, mixed $raw ): string|array` — one answer, normalised to its type. Multi-select returns `array<int,string>`; everything else a string.
  - `Blueworx_Clubhouse_Profile_Values::is_blank( array $field, string|array $value ): bool`
  - `Blueworx_Clubhouse_Profile_Values::visible_to_member( array $fields ): array<int,array<string,mixed>>` — drops `private`.
  - `Blueworx_Clubhouse_Profile_Values::writable_by_member( array $fields ): array<int,array<string,mixed>>` — keeps only `member`.
  - `Blueworx_Clubhouse_Profile_Values::from_member_post( array $fields, array $post ): array{values:array<string,string|array>,missing:array<int,string>}` — the answers a member may write, plus the labels of required fields left blank.
  - `Blueworx_Clubhouse_Profile_Values::from_admin_post( array $fields, array $post ): array<string,string|array>` — every field, required not enforced.

- [ ] **Step 1: Write the failing test**

Create `tests/php/ProfileValuesTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class ProfileValuesTest extends TestCase {

	/** @return array<string,mixed> */
	private function field( string $type, string $who = 'member', bool $required = false, array $choices = array() ): array {
		return array(
			'key'      => 'f',
			'label'    => 'A field',
			'type'     => $type,
			'choices'  => $choices,
			'help'     => '',
			'required' => $required,
			'who'      => $who,
		);
	}

	public function test_short_text_is_trimmed_and_stripped_of_markup(): void {
		$this->assertSame( 'Medium', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'text' ), '  <b>Medium</b> ' ) );
	}

	public function test_long_text_keeps_its_line_breaks(): void {
		$this->assertSame( "One\nTwo", Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'textarea' ), "One\nTwo" ) );
	}

	public function test_a_number_that_is_not_a_number_is_discarded(): void {
		$this->assertSame( '12', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'number' ), ' 12 ' ) );
		$this->assertSame( '-3.5', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'number' ), '-3.5' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'number' ), 'twelve' ) );
	}

	public function test_a_date_must_be_a_real_calendar_date(): void {
		$this->assertSame( '2026-02-28', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'date' ), '2026-02-28' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'date' ), '2026-02-30' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'date' ), '28/02/2026' ) );
	}

	public function test_a_dropdown_answer_the_club_never_offered_is_discarded(): void {
		$field = $this->field( 'select', 'member', false, array( 'Small', 'Medium' ) );
		$this->assertSame( 'Medium', Blueworx_Clubhouse_Profile_Values::clean( $field, 'Medium' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Values::clean( $field, 'Enormous' ) );
	}

	public function test_multi_select_keeps_only_offered_choices_and_returns_a_list(): void {
		$field = $this->field( 'multiselect', 'member', false, array( 'Nuts', 'Dairy', 'Gluten' ) );
		$this->assertSame(
			array( 'Nuts', 'Gluten' ),
			Blueworx_Clubhouse_Profile_Values::clean( $field, array( 'Nuts', 'Enormous', 'Gluten' ) )
		);
		$this->assertSame( array(), Blueworx_Clubhouse_Profile_Values::clean( $field, 'not-a-list' ) );
	}

	public function test_a_tick_is_one_or_empty(): void {
		$this->assertSame( '1', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'checkbox' ), '1' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Values::clean( $this->field( 'checkbox' ), '' ) );
	}

	public function test_blankness_understands_a_list(): void {
		$multi = $this->field( 'multiselect', 'member', true, array( 'Nuts' ) );
		$this->assertTrue( Blueworx_Clubhouse_Profile_Values::is_blank( $multi, array() ) );
		$this->assertFalse( Blueworx_Clubhouse_Profile_Values::is_blank( $multi, array( 'Nuts' ) ) );
		$this->assertTrue( Blueworx_Clubhouse_Profile_Values::is_blank( $this->field( 'text' ), '' ) );
	}

	public function test_a_private_field_is_not_visible_to_the_member(): void {
		$fields = array(
			$this->field( 'text', 'member' ) + array( 'key' => 'a' ),
			$this->field( 'text', 'club' ) + array( 'key' => 'b' ),
			$this->field( 'text', 'private' ) + array( 'key' => 'c' ),
		);
		$fields[0]['key'] = 'a';
		$fields[1]['key'] = 'b';
		$fields[2]['key'] = 'c';

		$this->assertSame( array( 'a', 'b' ), array_column( Blueworx_Clubhouse_Profile_Values::visible_to_member( $fields ), 'key' ) );
		$this->assertSame( array( 'a' ), array_column( Blueworx_Clubhouse_Profile_Values::writable_by_member( $fields ), 'key' ) );
	}

	public function test_a_member_cannot_write_a_club_field_by_posting_it(): void {
		$fields = array(
			array( 'key' => 'shirt', 'label' => 'Shirt size', 'type' => 'text', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'member' ),
			array( 'key' => 'squad', 'label' => 'Squad number', 'type' => 'number', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'club' ),
			array( 'key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'private' ),
		);
		$result = Blueworx_Clubhouse_Profile_Values::from_member_post(
			$fields,
			array( 'clubhouse_profile' => array( 'shirt' => 'Medium', 'squad' => '9', 'notes' => 'Anything' ) )
		);
		$this->assertSame( array( 'shirt' => 'Medium' ), $result['values'] );
		$this->assertSame( array(), $result['missing'] );
	}

	public function test_a_required_field_left_blank_is_reported_by_its_label(): void {
		$fields = array(
			array( 'key' => 'contact', 'label' => 'Emergency contact', 'type' => 'text', 'choices' => array(), 'help' => '', 'required' => true, 'who' => 'member' ),
		);
		$result = Blueworx_Clubhouse_Profile_Values::from_member_post( $fields, array( 'clubhouse_profile' => array( 'contact' => '   ' ) ) );
		$this->assertSame( array( 'Emergency contact' ), $result['missing'] );
	}

	public function test_staff_may_write_every_field_and_are_never_blocked_by_required(): void {
		$fields = array(
			array( 'key' => 'shirt', 'label' => 'Shirt size', 'type' => 'text', 'choices' => array(), 'help' => '', 'required' => true, 'who' => 'member' ),
			array( 'key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'private' ),
		);
		$values = Blueworx_Clubhouse_Profile_Values::from_admin_post(
			$fields,
			array( 'clubhouse_profile' => array( 'shirt' => '', 'notes' => 'Paid in cash' ) )
		);
		$this->assertSame( array( 'shirt' => '', 'notes' => 'Paid in cash' ), $values );
	}
}
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `vendor/bin/phpunit --filter ProfileValuesTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Profile_Values" not found`.

- [ ] **Step 3: Write the implementation**

Create `includes/profile/class-profile-values.php`:

```php
<?php
// includes/profile/class-profile-values.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a member's answer may be, and who is allowed to see or write it.
 *
 * Pure. Two jobs that belong together because both are answered per field and
 * both must agree: an answer is only cleaned against the definition that
 * allowed it, and a submission is only read for the fields its sender may
 * write.
 *
 * The filtering is deliberately positive — writable_by_member() KEEPS what is
 * allowed rather than removing what is not. A new 'who' setting added later
 * therefore defaults to unwritable and invisible, which is the failure worth
 * having.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Values {

	/** Where a submission carries its answers. */
	public const POST_KEY = 'clubhouse_profile';

	/**
	 * One answer, normalised to its field's type.
	 *
	 * Anything that does not fit the type becomes empty rather than an error.
	 * A member is stopped at the browser by the control's own type, and a
	 * hand-crafted post is not worth a screen of its own.
	 *
	 * @param array<string,mixed> $field
	 * @return string|array<int,string>
	 */
	public static function clean( array $field, mixed $raw ): string|array {
		$type    = (string) ( $field['type'] ?? 'text' );
		$choices = array_map( 'strval', (array) ( $field['choices'] ?? array() ) );

		if ( 'multiselect' === $type ) {
			if ( ! is_array( $raw ) ) {
				return array();
			}
			$out = array();
			foreach ( $raw as $one ) {
				$one = sanitize_text_field( (string) $one );
				if ( in_array( $one, $choices, true ) ) {
					$out[] = $one;
				}
			}
			return array_values( array_unique( $out ) );
		}

		if ( is_array( $raw ) ) {
			return '';
		}
		$value = (string) $raw;

		switch ( $type ) {
			case 'textarea':
				return trim( sanitize_textarea_field( $value ) );
			case 'number':
				$value = trim( $value );
				return is_numeric( $value ) ? $value : '';
			case 'date':
				$value = trim( $value );
				if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
					return '';
				}
				return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
			case 'select':
				$value = sanitize_text_field( $value );
				return in_array( $value, $choices, true ) ? $value : '';
			case 'checkbox':
				return '' !== trim( $value ) ? '1' : '';
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * @param array<string,mixed>      $field
	 * @param string|array<int,string> $value
	 */
	public static function is_blank( array $field, string|array $value ): bool {
		return is_array( $value ) ? array() === $value : '' === trim( $value );
	}

	/**
	 * The fields a member is shown. Private fields are not merely hidden by
	 * CSS — they never enter the HTML at all.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>
	 */
	public static function visible_to_member( array $fields ): array {
		return array_values(
			array_filter(
				$fields,
				static function ( array $field ): bool {
					return in_array( (string) ( $field['who'] ?? '' ), array( 'member', 'club' ), true );
				}
			)
		);
	}

	/**
	 * The fields a member may write. Everything else in their submission is
	 * discarded, so a tampered form cannot set a squad number.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>
	 */
	public static function writable_by_member( array $fields ): array {
		return array_values(
			array_filter(
				$fields,
				static function ( array $field ): bool {
					return 'member' === (string) ( $field['who'] ?? '' );
				}
			)
		);
	}

	/**
	 * A member's submission: the answers they are allowed to write, and the
	 * labels of any required field they left blank.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @param array<string,mixed>            $post
	 * @return array{values:array<string,string|array<int,string>>,missing:array<int,string>}
	 */
	public static function from_member_post( array $fields, array $post ): array {
		$sent    = is_array( $post[ self::POST_KEY ] ?? null ) ? (array) $post[ self::POST_KEY ] : array();
		$values  = array();
		$missing = array();

		foreach ( self::writable_by_member( $fields ) as $field ) {
			$key   = (string) $field['key'];
			$value = self::clean( $field, $sent[ $key ] ?? '' );
			if ( ! empty( $field['required'] ) && self::is_blank( $field, $value ) ) {
				$missing[] = (string) $field['label'];
			}
			$values[ $key ] = $value;
		}

		return array( 'values' => $values, 'missing' => $missing );
	}

	/**
	 * A staff submission from wp-admin: every field, and required is not
	 * enforced. Staff routinely change one thing about a member, and blocking
	 * that on an unrelated required field would make the screen unusable.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @param array<string,mixed>            $post
	 * @return array<string,string|array<int,string>>
	 */
	public static function from_admin_post( array $fields, array $post ): array {
		$sent   = is_array( $post[ self::POST_KEY ] ?? null ) ? (array) $post[ self::POST_KEY ] : array();
		$values = array();
		foreach ( $fields as $field ) {
			$key            = (string) $field['key'];
			$values[ $key ] = self::clean( $field, $sent[ $key ] ?? '' );
		}
		return $values;
	}
}
```

- [ ] **Step 4: Add it to the bootstrap**

In `includes/bootstrap.php`, under the `// Profile.` block, after the fields require:

```php
require_once __DIR__ . '/profile/class-profile-values.php';
```

- [ ] **Step 5: Run the test and watch it pass**

Run: `vendor/bin/phpunit --filter ProfileValuesTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/profile/class-profile-values.php tests/php/ProfileValuesTest.php includes/bootstrap.php
git commit -m "What a member's answer may be, and who is allowed to write it"
```

---

### Task 3: Storing the definitions and the answers

**Files:**
- Create: `includes/profile/class-profile-store.php`
- Test: `tests/php/ProfileStoreTest.php`
- Modify: `includes/bootstrap.php`, `tests/php/wp-stubs.php` (add user-meta stubs)

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Storage`, `Blueworx_Clubhouse_Profile_Fields`, `Blueworx_Clubhouse_Profile_Values`.
- Produces:
  - `Blueworx_Clubhouse_Profile_Store::OPTION` — `'profile_fields'`.
  - `Blueworx_Clubhouse_Profile_Store::META_PREFIX` — `'clubhouse_profile_'`.
  - `new Blueworx_Clubhouse_Profile_Store( Blueworx_Clubhouse_Storage $storage )`
  - `->fields(): array<int,array<string,mixed>>`
  - `->save_fields( array $rows ): void` — sanitises before storing.
  - `->meta_key( string $field_key ): string`
  - `->answers( int $user_id, array $fields ): array<string,string|array<int,string>>`
  - `->save_answers( int $user_id, array $values ): void`
  - `->forget( string $field_key ): void` — deletes that field's meta for every user.

- [ ] **Step 1: Add the user-meta stubs**

In `tests/php/wp-stubs.php`, beside the other `function_exists` guards, add:

```php
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) {
		global $wp_stub_user_meta;
		$value = $wp_stub_user_meta[ (int) $user_id ][ (string) $key ] ?? '';
		return $single ? $value : array( $value );
	}
}
if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value ) {
		global $wp_stub_user_meta;
		$wp_stub_user_meta[ (int) $user_id ][ (string) $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_user_meta' ) ) {
	function delete_user_meta( $user_id, $key ) {
		global $wp_stub_user_meta;
		unset( $wp_stub_user_meta[ (int) $user_id ][ (string) $key ] );
		return true;
	}
}
if ( ! function_exists( 'delete_metadata' ) ) {
	function delete_metadata( $type, $object_id, $key, $value = '', $all = false ) {
		global $wp_stub_user_meta;
		wp_stub_record( 'delete_metadata', array( $type, $object_id, $key, $value, $all ) );
		if ( $all ) {
			foreach ( array_keys( (array) $wp_stub_user_meta ) as $uid ) {
				unset( $wp_stub_user_meta[ $uid ][ (string) $key ] );
			}
		}
		return true;
	}
}
```

Add `$wp_stub_user_meta = array();` to the globals reset inside `wp_stub_reset()`, declaring it with `global $wp_stub_user_meta;` first, matching how the other stub globals are reset there.

- [ ] **Step 2: Write the failing test**

Create `tests/php/ProfileStoreTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class ProfileStoreTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Storage $storage;
	private Blueworx_Clubhouse_Profile_Store $store;

	protected function setUp(): void {
		wp_stub_reset();
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->store   = new Blueworx_Clubhouse_Profile_Store( $this->storage );
	}

	public function test_a_club_that_has_defined_nothing_has_no_fields(): void {
		$this->assertSame( array(), $this->store->fields() );
	}

	public function test_what_is_saved_comes_back_sanitised(): void {
		$this->store->save_fields( array( array( 'label' => 'Shirt size', 'type' => 'select', 'choices' => "Small\nMedium" ) ) );
		$fields = $this->store->fields();
		$this->assertCount( 1, $fields );
		$this->assertSame( 'shirt_size', $fields[0]['key'] );
		$this->assertSame( array( 'Small', 'Medium' ), $fields[0]['choices'] );
	}

	public function test_rubbish_in_the_option_reads_as_no_fields(): void {
		$this->storage->set( 'profile_fields', 'not-an-array' );
		$this->assertSame( array(), $this->store->fields() );
	}

	public function test_a_field_key_becomes_a_prefixed_meta_key(): void {
		$this->assertSame( 'clubhouse_profile_shirt_size', $this->store->meta_key( 'shirt_size' ) );
	}

	public function test_answers_round_trip_for_one_member(): void {
		$this->store->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		$fields = $this->store->fields();
		$this->store->save_answers( 7, array( 'shirt_size' => 'Medium' ) );
		$this->assertSame( array( 'shirt_size' => 'Medium' ), $this->store->answers( 7, $fields ) );
	}

	public function test_a_member_who_has_answered_nothing_reads_as_empty_not_missing(): void {
		$this->store->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		$this->assertSame( array( 'shirt_size' => '' ), $this->store->answers( 7, $this->store->fields() ) );
	}

	public function test_a_multi_select_answer_comes_back_as_a_list(): void {
		$this->store->save_fields( array( array( 'label' => 'Allergies', 'type' => 'multiselect', 'choices' => "Nuts\nDairy" ) ) );
		$fields = $this->store->fields();
		$this->store->save_answers( 7, array( 'allergies' => array( 'Nuts' ) ) );
		$this->assertSame( array( 'allergies' => array( 'Nuts' ) ), $this->store->answers( 7, $fields ) );
	}

	public function test_deleting_a_field_leaves_the_answers_alone(): void {
		$this->store->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		$this->store->save_answers( 7, array( 'shirt_size' => 'Medium' ) );

		$this->store->save_fields( array() );
		$this->assertSame( array(), $this->store->fields() );

		// Re-adding the field finds the answer still attached.
		$this->store->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		$this->assertSame( array( 'shirt_size' => 'Medium' ), $this->store->answers( 7, $this->store->fields() ) );
	}

	public function test_forgetting_a_field_clears_it_for_every_member(): void {
		$this->store->forget( 'shirt_size' );
		$calls = wp_stub_calls( 'delete_metadata' );
		$this->assertSame( array( 'user', 0, 'clubhouse_profile_shirt_size', '', true ), $calls[0] );
	}
}
```

- [ ] **Step 3: Run the test and watch it fail**

Run: `vendor/bin/phpunit --filter ProfileStoreTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Profile_Store" not found`.

- [ ] **Step 4: Write the implementation**

Create `includes/profile/class-profile-store.php`:

```php
<?php
// includes/profile/class-profile-store.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the club's field definitions and each member's answers are kept.
 *
 * Definitions go in the Clubhouse options row, like every other Clubhouse
 * setting. Answers go in WordPress user meta, one key per field — so a club's
 * data sits beside a member's name and email, survives this plugin being
 * removed, and is readable by any export tool the club already has.
 *
 * Deleting a field does NOT delete its answers. An owner who removes a field by
 * accident gets everything back by adding it again; clearing answers for good
 * is forget(), which is only ever reached through an explicit confirmation.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Store {

	public const OPTION      = 'profile_fields';
	public const META_PREFIX = 'clubhouse_profile_';

	public function __construct( private Blueworx_Clubhouse_Storage $storage ) {}

	/** @return array<int,array<string,mixed>> */
	public function fields(): array {
		$raw = $this->storage->get( self::OPTION, array() );
		return is_array( $raw ) ? Blueworx_Clubhouse_Profile_Fields::sanitise_all( $raw ) : array();
	}

	/**
	 * Sanitising on the way IN as well as out: the option is the club's record,
	 * and a half-valid definition sitting in it would be read by anything that
	 * ever queries the option directly.
	 *
	 * @param array<int,mixed> $rows
	 */
	public function save_fields( array $rows ): void {
		$this->storage->set( self::OPTION, Blueworx_Clubhouse_Profile_Fields::sanitise_all( $rows ) );
	}

	public function meta_key( string $field_key ): string {
		return self::META_PREFIX . $field_key;
	}

	/**
	 * One member's answers, keyed by field, with every field present.
	 *
	 * Present-but-empty rather than absent, so callers never have to tell "not
	 * answered" from "field is new" — both draw the same empty control.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,string|array<int,string>>
	 */
	public function answers( int $user_id, array $fields ): array {
		$out = array();
		foreach ( $fields as $field ) {
			$key   = (string) $field['key'];
			$value = get_user_meta( $user_id, $this->meta_key( $key ), true );
			if ( 'multiselect' === (string) $field['type'] ) {
				$out[ $key ] = is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : array();
				continue;
			}
			$out[ $key ] = is_scalar( $value ) ? (string) $value : '';
		}
		return $out;
	}

	/** @param array<string,string|array<int,string>> $values */
	public function save_answers( int $user_id, array $values ): void {
		foreach ( $values as $key => $value ) {
			update_user_meta( $user_id, $this->meta_key( (string) $key ), $value );
		}
	}

	/** Clear one field's answers for every member. Irreversible, by design. */
	public function forget( string $field_key ): void {
		delete_metadata( 'user', 0, $this->meta_key( $field_key ), '', true );
	}
}
```

- [ ] **Step 5: Add it to the bootstrap**

```php
require_once __DIR__ . '/profile/class-profile-store.php';
```

- [ ] **Step 6: Run the test and watch it pass**

Run: `vendor/bin/phpunit --filter ProfileStoreTest`
Expected: PASS.

- [ ] **Step 7: Run the whole unit suite**

Run: `vendor/bin/phpunit`
Expected: PASS — the new stubs must not disturb any existing test.

- [ ] **Step 8: Commit**

```bash
git add includes/profile/class-profile-store.php tests/php/ProfileStoreTest.php tests/php/wp-stubs.php includes/bootstrap.php
git commit -m "Keep field definitions in Clubhouse settings and answers in the member's WordPress record"
```

---

### Task 4: The builder on Setup → Members

**Files:**
- Modify: `includes/admin/class-setup-screen.php` (add `profile_fields_area()`, call it from the members panel)
- Modify: `includes/admin/class-setup-controller.php` (build the model, handle save/add/remove/forget)
- Test: `tests/php/SetupProfileFieldsTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Profile_Fields`, `Blueworx_Clubhouse_Profile_Store`.
- Produces:
  - `Blueworx_Clubhouse_Setup_Screen::profile_fields_area( array $fields ): string` — public so the test can call it directly.
  - Setup POST keys: `clubhouse_profile_field[<idx>][label|type|choices|help|required|who]`, `clubhouse_profile_field_add`, `clubhouse_profile_field_remove` (value = index), `clubhouse_profile_field_forget` (value = field key).
  - `build_model()` gains `'profile_fields' => array<int,array<string,mixed>>`.

The add and remove buttons are submit buttons handled server-side, exactly like `Content_Screen::loop_area` — no JavaScript, so the builder works on first load and without JS.

- [ ] **Step 1: Write the failing test**

Create `tests/php/SetupProfileFieldsTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class SetupProfileFieldsTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Storage $storage;

	protected function setUp(): void {
		wp_stub_reset();
		$this->storage = new Blueworx_Clubhouse_Fake_Storage();
	}

	private function fields(): array {
		return ( new Blueworx_Clubhouse_Profile_Store( $this->storage ) )->fields();
	}

	public function test_a_club_with_no_fields_is_told_what_the_builder_is_for(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::profile_fields_area( array() );
		$this->assertStringContainsString( 'Add a field', $html );
		$this->assertStringContainsString( 'clubhouse_profile_field_add', $html );
	}

	public function test_every_type_and_every_who_setting_is_offered(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::profile_fields_area(
			array( array( 'key' => 'shirt_size', 'label' => 'Shirt size', 'type' => 'text', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'member' ) )
		);
		foreach ( array_keys( Blueworx_Clubhouse_Profile_Fields::TYPES ) as $type ) {
			$this->assertStringContainsString( 'value="' . $type . '"', $html );
		}
		foreach ( array_keys( Blueworx_Clubhouse_Profile_Fields::WHO ) as $who ) {
			$this->assertStringContainsString( 'value="' . $who . '"', $html );
		}
	}

	public function test_the_key_travels_with_the_row_so_a_rename_keeps_the_answers(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::profile_fields_area(
			array( array( 'key' => 'shirt_size', 'label' => 'Shirt size', 'type' => 'text', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'member' ) )
		);
		$this->assertStringContainsString( 'name="clubhouse_profile_field[0][key]"', $html );
		$this->assertStringContainsString( 'value="shirt_size"', $html );
	}

	public function test_saving_the_setup_form_stores_the_fields(): void {
		Blueworx_Clubhouse_Setup_Controller::handle_save(
			array( 'clubhouse_profile_field' => array( array( 'label' => 'Shirt size', 'type' => 'select', 'choices' => "Small\nMedium", 'who' => 'member' ) ) ),
			$this->storage
		);
		$fields = $this->fields();
		$this->assertSame( 'shirt_size', $fields[0]['key'] );
		$this->assertSame( 'select', $fields[0]['type'] );
	}

	public function test_add_leaves_one_empty_row_for_the_owner_to_fill_in(): void {
		Blueworx_Clubhouse_Setup_Controller::handle_save(
			array(
				'clubhouse_profile_field'     => array( array( 'label' => 'Shirt size' ) ),
				'clubhouse_profile_field_add' => '1',
			),
			$this->storage
		);
		// The empty row is not stored — it is drawn by the screen, which always
		// offers one more row than the club has fields.
		$this->assertCount( 1, $this->fields() );
	}

	public function test_remove_takes_the_named_row_out(): void {
		Blueworx_Clubhouse_Setup_Controller::handle_save(
			array(
				'clubhouse_profile_field'        => array(
					array( 'key' => 'shirt_size', 'label' => 'Shirt size' ),
					array( 'key' => 'squad', 'label' => 'Squad number' ),
				),
				'clubhouse_profile_field_remove' => '0',
			),
			$this->storage
		);
		$this->assertSame( array( 'squad' ), array_column( $this->fields(), 'key' ) );
	}

	public function test_removing_a_field_does_not_clear_the_answers(): void {
		Blueworx_Clubhouse_Setup_Controller::handle_save(
			array(
				'clubhouse_profile_field'        => array( array( 'key' => 'shirt_size', 'label' => 'Shirt size' ) ),
				'clubhouse_profile_field_remove' => '0',
			),
			$this->storage
		);
		$this->assertSame( array(), wp_stub_calls( 'delete_metadata' ) );
	}

	public function test_forget_clears_the_answers_and_says_so(): void {
		$notices = Blueworx_Clubhouse_Setup_Controller::handle_save(
			array( 'clubhouse_profile_field_forget' => 'shirt_size' ),
			$this->storage
		);
		$calls = wp_stub_calls( 'delete_metadata' );
		$this->assertSame( 'clubhouse_profile_shirt_size', $calls[0][2] );
		$texts = array_column( $notices, 'text' );
		$this->assertNotEmpty( array_filter( $texts, static fn( $t ) => str_contains( $t, 'cleared' ) ) );
	}

	public function test_a_setup_save_that_never_mentions_profile_fields_leaves_them_alone(): void {
		( new Blueworx_Clubhouse_Profile_Store( $this->storage ) )->save_fields( array( array( 'label' => 'Shirt size' ) ) );
		Blueworx_Clubhouse_Setup_Controller::handle_save( array( 'clubhouse_post_login' => '/members/' ), $this->storage );
		$this->assertCount( 1, $this->fields() );
	}
}
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `vendor/bin/phpunit --filter SetupProfileFieldsTest`
Expected: FAIL — `Call to undefined method …::profile_fields_area()`.

- [ ] **Step 3: Add the screen section**

In `includes/admin/class-setup-screen.php`, add this method after `members_area()`:

```php
	/**
	 * The custom member fields a club has invented, and the row for the next one.
	 *
	 * Server-rendered add and remove, like the Club Pages content loop: submit
	 * buttons rather than JavaScript, so the builder works on first load, with
	 * JS off, and never loses a half-typed row to a script that failed to load.
	 *
	 * The key rides along in a hidden input on every existing row. It is what
	 * lets an owner rewrite a label without detaching every member's answer —
	 * so it must survive the round trip, and it is never editable.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 */
	public static function profile_fields_area( array $fields ): string {
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Members</p>'
			. '<h2 class="clubhouse-step__h">What you keep about a member</h2>';
		$out .= '<p class="clubhouse-step__lede">Add anything your club needs to know — shirt size, emergency contact, squad number. '
			. 'Members see and fill in their own on their Profile page. You can also keep things only the club sees.</p>';

		$out .= '<div class="clubhouse-loop">';
		foreach ( $fields as $idx => $field ) {
			$out .= self::profile_field_row( (array) $field, (int) $idx );
		}
		// Always one blank row past the end, so adding a field is typing rather
		// than clicking then typing.
		$out .= self::profile_field_row( array(), count( $fields ) );

		if ( count( $fields ) < Blueworx_Clubhouse_Profile_Fields::MAX_FIELDS ) {
			$out .= '<button type="submit" name="clubhouse_profile_field_add" value="1" class="clubhouse-btn clubhouse-btn--sm">Add another field</button>';
		} else {
			$out .= '<p class="clubhouse-help">That is ' . (int) Blueworx_Clubhouse_Profile_Fields::MAX_FIELDS
				. ' fields — as many as one page can sensibly ask anybody for.</p>';
		}
		$out .= '</div>';
		return $out . '</div>';
	}

	/**
	 * One field's row. An empty $field is the blank row at the end.
	 *
	 * @param array<string,mixed> $field
	 */
	private static function profile_field_row( array $field, int $idx ): string {
		$name  = 'clubhouse_profile_field[' . $idx . ']';
		$key   = (string) ( $field['key'] ?? '' );
		$type  = (string) ( $field['type'] ?? Blueworx_Clubhouse_Profile_Fields::DEFAULT_TYPE );
		$who   = (string) ( $field['who'] ?? Blueworx_Clubhouse_Profile_Fields::DEFAULT_WHO );
		$blank = '' === $key;

		$out = '<div class="clubhouse-loop__item">';
		if ( ! $blank ) {
			$out .= '<input type="hidden" name="' . self::esc( $name ) . '[key]" value="' . self::esc( $key ) . '">';
		}
		$out .= '<div class="clubhouse-fields">';
		$out .= self::text_field( $name . '[label]', $blank ? 'Add a field' : 'What it is called', (string) ( $field['label'] ?? '' ) );
		$out .= self::select_field( $name . '[type]', 'Kind of answer', Blueworx_Clubhouse_Profile_Fields::TYPES, $type );
		$out .= self::select_field( $name . '[who]', 'Who fills it in', Blueworx_Clubhouse_Profile_Fields::WHO, $who );
		$out .= '</div>';

		$out .= '<div class="clubhouse-field"><label class="clubhouse-label" for="' . self::esc( self::slug_id( 'field', $name . '[choices]' ) ) . '">Choices, one per line</label>'
			. '<textarea id="' . self::esc( self::slug_id( 'field', $name . '[choices]' ) ) . '" name="' . self::esc( $name ) . '[choices]" rows="3" class="clubhouse-input">'
			. self::esc( implode( "\n", array_map( 'strval', (array) ( $field['choices'] ?? array() ) ) ) )
			. '</textarea>'
			. '<p class="clubhouse-help">Only used by the two dropdown kinds. Ignored otherwise.</p></div>';

		$out .= '<div class="clubhouse-fields">';
		$out .= self::text_field( $name . '[help]', 'A note under the box (optional)', (string) ( $field['help'] ?? '' ) );
		$out .= '</div>';
		$out .= self::toggle( $name . '[required]', 'A member must fill this in before they can save', ! empty( $field['required'] ) );

		if ( ! $blank ) {
			$out .= '<div class="clubhouse-loop__actions">'
				. '<button type="submit" name="clubhouse_profile_field_remove" value="' . (int) $idx . '" class="clubhouse-btn-link">Remove this field</button>'
				. '<button type="submit" name="clubhouse_profile_field_forget" value="' . self::esc( $key ) . '" class="clubhouse-btn-link clubhouse-btn-link--danger" '
				. 'onclick="return confirm(\'This clears every member\\\'s answer to this field, for good. Are you sure?\')">Remove and clear every answer</button>'
				. '</div>';
		}
		return $out . '</div>';
	}

	/**
	 * A labelled dropdown.
	 *
	 * @param array<string,string> $options value => label
	 */
	private static function select_field( string $name, string $label, array $options, string $value ): string {
		$id  = self::slug_id( 'field', $name );
		$out = '<div class="clubhouse-field"><label class="clubhouse-label" for="' . self::esc( $id ) . '">' . self::esc( $label ) . '</label>'
			. '<select id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" class="clubhouse-input">';
		foreach ( $options as $opt_value => $opt_label ) {
			$selected = ( (string) $opt_value === $value ) ? ' selected' : '';
			$out     .= '<option value="' . self::esc( (string) $opt_value ) . '"' . $selected . '>' . self::esc( $opt_label ) . '</option>';
		}
		return $out . '</select></div>';
	}
```

If `Setup_Screen` has no `slug_id()` helper, copy the one from `Content_Screen` verbatim, keeping its name and behaviour. If its `text_field()` or `toggle()` signatures differ from `( string $name, string $label, string $value )` and `( string $name, string $label, bool $on )`, adapt the calls above rather than the helpers.

- [ ] **Step 4: Call it from the Members panel**

In `includes/admin/class-setup-screen.php`, in the members panel section, insert the new area between `members_area()` and `emails_area()`:

```php
		$out .= '<section class="clubhouse-panel" data-panel="members" role="tabpanel">'
			. self::members_area( $model['members'] ?? array() )
			. self::profile_fields_area( (array) ( $model['profile_fields'] ?? array() ) )
			// Beside it rather than in a tab of its own: the email a club sends is
			// almost entirely password resets, which is a member journey.
			. self::emails_area( $model['mail'] ?? array() ) . '</section>';
```

- [ ] **Step 5: Build the model**

In `includes/admin/class-setup-controller.php`, in `build_model()`, add to the returned array beside `'members'`:

```php
			'profile_fields' => ( new Blueworx_Clubhouse_Profile_Store( $storage ) )->fields(),
```

- [ ] **Step 6: Handle the save**

In `includes/admin/class-setup-controller.php`, in `handle_save()`, immediately before the `return $notices;` at the end, add:

```php
		// Custom member fields.
		//
		// Order matters: forget acts on a field key and must run whether or not
		// the form carried any rows, and remove strikes a row out before the
		// list is sanitised, so an index is never applied to a shifted list.
		$profile = new Blueworx_Clubhouse_Profile_Store( $storage );

		if ( isset( $post['clubhouse_profile_field_forget'] ) ) {
			$forget = sanitize_key( (string) $post['clubhouse_profile_field_forget'] );
			if ( '' !== $forget ) {
				$profile->forget( $forget );
				$notices[] = array(
					'type' => 'success',
					'text' => 'That field is gone and every member\'s answer to it has been cleared.',
				);
			}
		}

		if ( isset( $post['clubhouse_profile_field'] ) && is_array( $post['clubhouse_profile_field'] ) ) {
			$rows = array_values( (array) $post['clubhouse_profile_field'] );

			foreach ( array( 'clubhouse_profile_field_remove', 'clubhouse_profile_field_forget' ) as $drop_key ) {
				if ( ! isset( $post[ $drop_key ] ) ) {
					continue;
				}
				$raw = (string) $post[ $drop_key ];
				$rows = array_values(
					array_filter(
						$rows,
						static function ( $row, $idx ) use ( $drop_key, $raw ): bool {
							if ( 'clubhouse_profile_field_remove' === $drop_key ) {
								return (string) $idx !== $raw;
							}
							return sanitize_key( (string) ( ( (array) $row )['key'] ?? '' ) ) !== sanitize_key( $raw );
						},
						ARRAY_FILTER_USE_BOTH
					)
				);
			}

			$profile->save_fields( $rows );
		}
```

- [ ] **Step 7: Run the test and watch it pass**

Run: `vendor/bin/phpunit --filter SetupProfileFieldsTest`
Expected: PASS.

- [ ] **Step 8: Run the whole unit suite**

Run: `vendor/bin/phpunit`
Expected: PASS. `SetupScreenTest` and `SetupControllerTest` must both still pass — if either asserts on the exact shape of the members panel or the model keys, update those assertions to include the new section.

- [ ] **Step 9: Commit**

```bash
git add includes/admin/class-setup-screen.php includes/admin/class-setup-controller.php tests/php/SetupProfileFieldsTest.php
git commit -m "A club builds its own member fields on Setup's Members tab"
```

---

### Task 5: The member's custom-fields card

**Files:**
- Create: `includes/profile/class-profile-panel.php`
- Test: `tests/php/ProfilePanelTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Profile_Fields`, `Blueworx_Clubhouse_Profile_Values`.
- Produces:
  - `Blueworx_Clubhouse_Profile_Panel::ACTION` — `'clubhouse_profile_save'` (the admin-post action).
  - `Blueworx_Clubhouse_Profile_Panel::NONCE` — `'clubhouse_profile_save'`.
  - `Blueworx_Clubhouse_Profile_Panel::render( array $fields, array $answers, string $action_url, string $nonce_field, array $notices ): string` — the whole card, or `''` when the club has no member-visible fields.
  - `Blueworx_Clubhouse_Profile_Panel::control( array $field, string|array $value, string $name_prefix, bool $editable ): string` — one field's control, or its read-only text.

- [ ] **Step 1: Write the failing test**

Create `tests/php/ProfilePanelTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class ProfilePanelTest extends TestCase {

	/** @return array<string,mixed> */
	private function field( string $key, string $type, string $who, array $extra = array() ): array {
		return array_merge(
			array( 'key' => $key, 'label' => ucfirst( $key ), 'type' => $type, 'choices' => array(), 'help' => '', 'required' => false, 'who' => $who ),
			$extra
		);
	}

	private function render( array $fields, array $answers = array() ): string {
		return Blueworx_Clubhouse_Profile_Panel::render( $fields, $answers, 'https://club.test/save', '<input name="_wpnonce" value="n">', array() );
	}

	public function test_a_club_with_no_fields_draws_no_card_at_all(): void {
		$this->assertSame( '', $this->render( array() ) );
	}

	public function test_a_club_with_only_private_fields_draws_no_card(): void {
		$this->assertSame( '', $this->render( array( $this->field( 'notes', 'textarea', 'private' ) ) ) );
	}

	public function test_a_private_field_never_reaches_the_html(): void {
		$html = $this->render(
			array( $this->field( 'shirt', 'text', 'member' ), $this->field( 'notes', 'textarea', 'private' ) ),
			array( 'shirt' => 'Medium', 'notes' => 'Paid in cash' )
		);
		$this->assertStringNotContainsString( 'notes', $html );
		$this->assertStringNotContainsString( 'Paid in cash', $html );
	}

	public function test_a_member_field_is_an_editable_control(): void {
		$html = $this->render( array( $this->field( 'shirt', 'text', 'member' ) ), array( 'shirt' => 'Medium' ) );
		$this->assertStringContainsString( 'name="clubhouse_profile[shirt]"', $html );
		$this->assertStringContainsString( 'value="Medium"', $html );
	}

	public function test_a_club_field_is_shown_but_has_no_control_to_change_it(): void {
		$html = $this->render( array( $this->field( 'squad', 'number', 'club' ) ), array( 'squad' => '9' ) );
		$this->assertStringContainsString( '9', $html );
		$this->assertStringNotContainsString( 'name="clubhouse_profile[squad]"', $html );
	}

	public function test_an_unanswered_club_field_says_so_rather_than_showing_a_gap(): void {
		$html = $this->render( array( $this->field( 'squad', 'number', 'club' ) ), array( 'squad' => '' ) );
		$this->assertStringContainsString( 'Not set', $html );
	}

	public function test_a_required_field_is_marked_required_in_the_markup(): void {
		$html = $this->render( array( $this->field( 'contact', 'text', 'member', array( 'required' => true ) ) ) );
		$this->assertStringContainsString( 'required', $html );
	}

	public function test_a_dropdown_offers_the_clubs_choices_and_a_way_to_answer_nothing(): void {
		$html = $this->render( array( $this->field( 'shirt', 'select', 'member', array( 'choices' => array( 'Small', 'Medium' ) ) ) ) );
		$this->assertStringContainsString( '<option value="Small">Small</option>', $html );
		$this->assertStringContainsString( '<option value="Medium">Medium</option>', $html );
		$this->assertStringContainsString( '<option value="">', $html );
	}

	public function test_a_multi_select_posts_a_list(): void {
		$html = $this->render(
			array( $this->field( 'allergies', 'multiselect', 'member', array( 'choices' => array( 'Nuts', 'Dairy' ) ) ) ),
			array( 'allergies' => array( 'Nuts' ) )
		);
		$this->assertStringContainsString( 'name="clubhouse_profile[allergies][]"', $html );
		$this->assertStringContainsString( 'multiple', $html );
	}

	public function test_a_members_answer_is_escaped(): void {
		$html = $this->render( array( $this->field( 'shirt', 'text', 'member' ) ), array( 'shirt' => '"><script>alert(1)</script>' ) );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_the_card_carries_its_nonce_and_posts_to_the_action(): void {
		$html = $this->render( array( $this->field( 'shirt', 'text', 'member' ) ) );
		$this->assertStringContainsString( 'https://club.test/save', $html );
		$this->assertStringContainsString( '_wpnonce', $html );
		$this->assertStringContainsString( 'method="post"', $html );
	}

	public function test_a_club_with_only_club_fields_still_draws_the_card_but_no_save_button(): void {
		$html = $this->render( array( $this->field( 'squad', 'number', 'club' ) ), array( 'squad' => '9' ) );
		$this->assertNotSame( '', $html );
		$this->assertStringNotContainsString( '<button type="submit"', $html );
	}

	public function test_a_notice_is_shown_to_the_member(): void {
		$html = Blueworx_Clubhouse_Profile_Panel::render(
			array( $this->field( 'shirt', 'text', 'member' ) ),
			array(),
			'https://club.test/save',
			'',
			array( array( 'type' => 'success', 'text' => 'Saved.' ) )
		);
		$this->assertStringContainsString( 'Saved.', $html );
	}
}
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `vendor/bin/phpunit --filter ProfilePanelTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Profile_Panel" not found`.

- [ ] **Step 3: Write the implementation**

Create `includes/profile/class-profile-panel.php`:

```php
<?php
// includes/profile/class-profile-panel.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member's own view of what the club keeps about them.
 *
 * Pure: it is handed the fields, the answers and a nonce field, and returns
 * HTML. Nothing here decides who may see what — Profile_Values does that, and
 * this asks it rather than re-deriving it, so there is one place to be wrong.
 *
 * A private field never enters this HTML: not as a hidden input, not as
 * read-only text. Hiding one with CSS would put it in the page source, which is
 * not hiding anything from anybody.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Panel {

	public const ACTION = 'clubhouse_profile_save';
	public const NONCE  = 'clubhouse_profile_save';

	/**
	 * @param array<int,array<string,mixed>>                $fields
	 * @param array<string,string|array<int,string>>        $answers
	 * @param array<int,array{type:string,text:string}>     $notices
	 */
	public static function render( array $fields, array $answers, string $action_url, string $nonce_field, array $notices = array() ): string {
		$visible = Blueworx_Clubhouse_Profile_Values::visible_to_member( $fields );
		if ( array() === $visible ) {
			return '';
		}
		$editable_count = count( Blueworx_Clubhouse_Profile_Values::writable_by_member( $visible ) );

		$out = '<form class="clubhouse-profile" method="post" action="' . self::e( $action_url ) . '">';
		$out .= $nonce_field;
		$out .= '<input type="hidden" name="action" value="' . self::e( self::ACTION ) . '">';

		foreach ( $notices as $notice ) {
			$type = (string) ( $notice['type'] ?? 'success' );
			$out .= '<p class="clubhouse-profile__notice clubhouse-profile__notice--' . self::e( $type ) . '" role="status">'
				. self::e( (string) ( $notice['text'] ?? '' ) ) . '</p>';
		}

		$out .= '<div class="clubhouse-profile__fields">';
		foreach ( $visible as $field ) {
			$key      = (string) $field['key'];
			$editable = 'member' === (string) $field['who'];
			$out     .= self::row( $field, $answers[ $key ] ?? ( 'multiselect' === $field['type'] ? array() : '' ), $editable );
		}
		$out .= '</div>';

		// Nothing to save is not a reason to draw a button that saves nothing.
		if ( $editable_count > 0 ) {
			$out .= '<button type="submit" class="clubhouse-profile__save">Save my details</button>';
		}
		return $out . '</form>';
	}

	/**
	 * @param array<string,mixed>      $field
	 * @param string|array<int,string> $value
	 */
	private static function row( array $field, string|array $value, bool $editable ): string {
		$key  = (string) $field['key'];
		$id   = 'clubhouse-profile-' . $key;
		$help = (string) ( $field['help'] ?? '' );

		$out = '<div class="clubhouse-profile__row">';
		$out .= $editable
			? '<label class="clubhouse-profile__label" for="' . self::e( $id ) . '">' . self::e( (string) $field['label'] ) . '</label>'
			: '<p class="clubhouse-profile__label">' . self::e( (string) $field['label'] ) . '</p>';
		$out .= self::control( $field, $value, self::name( $key, (string) $field['type'] ), $editable );
		if ( '' !== $help ) {
			$out .= '<p class="clubhouse-profile__help">' . self::e( $help ) . '</p>';
		}
		return $out . '</div>';
	}

	private static function name( string $key, string $type ): string {
		return Blueworx_Clubhouse_Profile_Values::POST_KEY . '[' . $key . ']' . ( 'multiselect' === $type ? '[]' : '' );
	}

	/**
	 * One field's control, or the plain text a club field shows.
	 *
	 * @param array<string,mixed>      $field
	 * @param string|array<int,string> $value
	 */
	public static function control( array $field, string|array $value, string $name_prefix, bool $editable ): string {
		$type    = (string) $field['type'];
		$key     = (string) $field['key'];
		$id      = 'clubhouse-profile-' . $key;
		$choices = array_map( 'strval', (array) ( $field['choices'] ?? array() ) );
		$req     = ! empty( $field['required'] ) ? ' required' : '';

		if ( ! $editable ) {
			return '<p class="clubhouse-profile__value">' . self::e( self::readable( $field, $value ) ) . '</p>';
		}

		switch ( $type ) {
			case 'textarea':
				return '<textarea id="' . self::e( $id ) . '" name="' . self::e( $name_prefix ) . '" rows="4" class="clubhouse-profile__input"' . $req . '>'
					. self::e( is_array( $value ) ? '' : $value ) . '</textarea>';
			case 'checkbox':
				$checked = ( '1' === ( is_array( $value ) ? '' : $value ) ) ? ' checked' : '';
				return '<input type="checkbox" id="' . self::e( $id ) . '" name="' . self::e( $name_prefix ) . '" value="1" class="clubhouse-profile__tick"' . $checked . '>';
			case 'select':
				$out = '<select id="' . self::e( $id ) . '" name="' . self::e( $name_prefix ) . '" class="clubhouse-profile__input"' . $req . '>';
				// An empty first option is the only way to answer "none of these"
				// on a field the club did not make required.
				$out .= '<option value="">Please choose</option>';
				foreach ( $choices as $choice ) {
					$sel  = ( ! is_array( $value ) && $choice === $value ) ? ' selected' : '';
					$out .= '<option value="' . self::e( $choice ) . '"' . $sel . '>' . self::e( $choice ) . '</option>';
				}
				return $out . '</select>';
			case 'multiselect':
				$chosen = is_array( $value ) ? $value : array();
				$out    = '<select id="' . self::e( $id ) . '" name="' . self::e( $name_prefix ) . '" class="clubhouse-profile__input" multiple size="' . min( 6, max( 2, count( $choices ) ) ) . '">';
				foreach ( $choices as $choice ) {
					$sel  = in_array( $choice, $chosen, true ) ? ' selected' : '';
					$out .= '<option value="' . self::e( $choice ) . '"' . $sel . '>' . self::e( $choice ) . '</option>';
				}
				return $out . '</select>';
			case 'number':
			case 'date':
				return '<input type="' . self::e( $type ) . '" id="' . self::e( $id ) . '" name="' . self::e( $name_prefix ) . '" value="'
					. self::e( is_array( $value ) ? '' : $value ) . '" class="clubhouse-profile__input"' . $req . '>';
			default:
				return '<input type="text" id="' . self::e( $id ) . '" name="' . self::e( $name_prefix ) . '" value="'
					. self::e( is_array( $value ) ? '' : $value ) . '" class="clubhouse-profile__input"' . $req . '>';
		}
	}

	/**
	 * What a club field's stored value reads as.
	 *
	 * "Not set" rather than a blank line: an empty row under a label reads as a
	 * broken page, where a member who sees "Not set" knows to ask the club.
	 *
	 * @param array<string,mixed>      $field
	 * @param string|array<int,string> $value
	 */
	private static function readable( array $field, string|array $value ): string {
		if ( is_array( $value ) ) {
			return array() === $value ? 'Not set' : implode( ', ', $value );
		}
		if ( 'checkbox' === (string) $field['type'] ) {
			return '1' === $value ? 'Yes' : 'No';
		}
		return '' === trim( $value ) ? 'Not set' : $value;
	}

	private static function e( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
```

- [ ] **Step 4: Add it to the bootstrap**

```php
require_once __DIR__ . '/profile/class-profile-panel.php';
```

- [ ] **Step 5: Run the test and watch it pass**

Run: `vendor/bin/phpunit --filter ProfilePanelTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/profile/class-profile-panel.php tests/php/ProfilePanelTest.php includes/bootstrap.php
git commit -m "The card a member sees for the club's own questions"
```

---

### Task 6: The Profile view, and splitting Account

**Files:**
- Modify: `includes/dashboard/class-dashboard-views.php`
- Modify: `includes/dashboard/class-member-dashboard.php`
- Test: `tests/php/DashboardViewsTest.php` (existing — update), `tests/php/MemberDashboardTest.php` (existing — add)

**Interfaces:**
- Consumes: nothing new.
- Produces:
  - A `profile` view, placed immediately before `account`, `where` = `both`, `requires` = `NEEDS_SURECART`, `blocks` = `array( 'surecart/wordpress-account' )`, and a new key `'panel' => 'profile'`.
  - `account` keeps its key, drops `surecart/wordpress-account`, keeps billing details and payment methods, and changes `where` from `both` to `side` (the phone bar already carries Billing for money).
  - Every view gains `'panel' => ''`.
  - `Blueworx_Clubhouse_Member_Dashboard::view_body()` renders a declared panel into its own card via a new injected callable.

- [ ] **Step 1: Update the failing tests**

In `tests/php/DashboardViewsTest.php`, change the two order assertions to include `profile` before `account`:

```php
			array( 'dashboard', 'bookings', 'orders', 'invoices', 'billing', 'plans', 'profile', 'account' ),
```

and add:

```php
	public function test_the_profile_view_carries_the_wordpress_account_block_and_a_panel_of_our_own(): void {
		$profile = Blueworx_Clubhouse_Dashboard_Views::find( 'profile', Blueworx_Clubhouse_Dashboard_Views::all() );
		$this->assertNotNull( $profile );
		$this->assertSame( array( 'surecart/wordpress-account' ), $profile['blocks'] );
		$this->assertSame( 'profile', $profile['panel'] );
	}

	public function test_account_keeps_its_key_so_old_bookmarks_still_land(): void {
		$account = Blueworx_Clubhouse_Dashboard_Views::find( 'account', Blueworx_Clubhouse_Dashboard_Views::all() );
		$this->assertNotNull( $account );
		$this->assertSame(
			array( 'surecart/customer-billing-details', 'surecart/customer-payment-methods' ),
			$account['blocks']
		);
	}

	public function test_the_name_and_password_block_lives_in_exactly_one_view(): void {
		$holders = array();
		foreach ( Blueworx_Clubhouse_Dashboard_Views::all() as $view ) {
			if ( in_array( 'surecart/wordpress-account', (array) $view['blocks'], true ) ) {
				$holders[] = $view['key'];
			}
		}
		$this->assertSame( array( 'profile' ), $holders );
	}

	public function test_every_view_declares_whether_it_has_a_panel_of_our_own(): void {
		foreach ( Blueworx_Clubhouse_Dashboard_Views::all() as $view ) {
			$this->assertIsString( $view['panel'] );
		}
	}
```

In `tests/php/MemberDashboardTest.php`, add:

```php
	public function test_a_views_own_panel_is_drawn_in_its_own_card(): void {
		$view = array(
			'key' => 'profile', 'label' => 'Profile', 'title' => 'Profile', 'lede' => '',
			'icon' => 'user', 'requires' => '', 'where' => 'both',
			'blocks' => array(), 'shortcode' => '', 'panel' => 'profile',
		);
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body(
			$view,
			'',
			'https://club.test/',
			static fn( string $panel ): string => 'profile' === $panel ? '<p id="ours">Our panel</p>' : ''
		);
		$this->assertStringContainsString( 'Our panel', $html );
	}

	public function test_a_view_whose_panel_draws_nothing_and_has_no_blocks_shows_the_honest_empty_state(): void {
		$view = array(
			'key' => 'profile', 'label' => 'Profile', 'title' => 'Profile', 'lede' => '',
			'icon' => 'user', 'requires' => '', 'where' => 'both',
			'blocks' => array(), 'shortcode' => '', 'panel' => 'profile',
		);
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body( $view, '', 'https://club.test/', static fn( string $p ): string => '' );
		$this->assertStringNotContainsString( 'Our panel', $html );
	}
```

- [ ] **Step 2: Run the tests and watch them fail**

Run: `vendor/bin/phpunit --filter "DashboardViewsTest|MemberDashboardTest"`
Expected: FAIL — the order assertion mismatches and `view_body()` takes three arguments.

- [ ] **Step 3: Add the Profile view and split Account**

In `includes/dashboard/class-dashboard-views.php`, add `'panel' => '',` to every existing entry, update the `@return` docblock shape to include `panel:string`, then replace the `account` entry with these two, in this order:

```php
			array(
				'key'       => 'profile',
				'label'     => 'Profile',
				'title'     => 'Your profile',
				'lede'      => 'Who you are, and what the club keeps about you.',
				'icon'      => 'user',
				// The name, email and password block is SureCart's, so the view
				// needs the shop for its first card. The club's own fields could
				// stand alone on a shop-less site; that is not built yet, and a
				// view offered only to show custom fields nobody has defined
				// would be an empty screen on most clubs.
				'requires'  => self::NEEDS_SURECART,
				'where'     => 'both',
				'blocks'    => array( 'surecart/wordpress-account' ),
				// Our own card, drawn under the block above: the club's custom
				// member fields. Empty on a club that has defined none.
				'panel'     => 'profile',
				'shortcode' => '',
			),
			array(
				'key'       => 'account',
				'label'     => 'Account',
				'title'     => 'Account details',
				'lede'      => 'How you pay the club.',
				'icon'      => 'credit-card',
				'requires'  => self::NEEDS_SURECART,
				// Off the phone's bottom bar now that Profile is on it: Billing
				// is already the phone's one money screen and carries both of
				// these panels, so a second bar item would show the same thing.
				'where'     => 'side',
				'blocks'    => array( 'surecart/customer-billing-details', 'surecart/customer-payment-methods' ),
				'panel'     => '',
				'shortcode' => '',
			),
```

- [ ] **Step 4: Let a view draw a panel of our own**

In `includes/dashboard/class-member-dashboard.php`, change `view_body()` to take an optional panel renderer and call it:

```php
	/**
	 * One view's contents.
	 *
	 * A shortcode view is handed the whole panel — LatePoint brings its own
	 * tabs and does not belong inside a card of ours. Blocks each get a card,
	 * then a view's own panel, if it declares one, gets another. A panel whose
	 * plugin says nothing shows the honest empty state rather than an empty card.
	 *
	 * $panel_renderer is injected rather than called for by name so this stays
	 * testable without WordPress, like everything else on this class.
	 *
	 * @param array<string,mixed>   $view
	 * @param callable(string):string|null $panel_renderer
	 */
	public static function view_body( array $view, string $welcome, string $home_url, ?callable $panel_renderer = null ): string {
		$shortcode = (string) $view['shortcode'];
		if ( '' !== $shortcode ) {
			$out = Blueworx_Clubhouse_Plugin_Slot::shortcode( $shortcode );
			return '' !== $out ? $out : self::not_set_up( $home_url );
		}

		$out = '';
		foreach ( (array) $view['blocks'] as $block ) {
			$panel = Blueworx_Clubhouse_Plugin_Slot::block( (string) $block );
			if ( '' !== $panel ) {
				$out .= Blueworx_Clubhouse_Dashboard_Shell::card( '', $panel );
			}
		}

		$own = (string) ( $view['panel'] ?? '' );
		if ( '' !== $own && null !== $panel_renderer ) {
			$drawn = (string) $panel_renderer( $own );
			if ( '' !== $drawn ) {
				$out .= Blueworx_Clubhouse_Dashboard_Shell::card( '', $drawn );
			}
		}

		if ( '' === $out ) {
			return self::not_set_up( $home_url );
		}
		// $welcome is always '' here in production — screen() only ever passes
		// the pack into overview(). Kept as a parameter so the brief's direct
		// calls to view_body() can still exercise it; not a live path.
		return ( '' !== $welcome ? $welcome : '' ) . $out;
	}
```

Then find the call site inside `screen()` and pass the renderer through, so the live path draws the card. Task 7 supplies `Blueworx_Clubhouse_Profile_Form::panel()`; until it exists, pass `null` and this task's tests still pass.

- [ ] **Step 5: Run the tests and watch them pass**

Run: `vendor/bin/phpunit --filter "DashboardViewsTest|MemberDashboardTest"`
Expected: PASS.

- [ ] **Step 6: Run the whole unit suite**

Run: `vendor/bin/phpunit`
Expected: PASS. `DashboardShellTest` and `MemberDashboardActionsTest` may assert on nav contents or on the account view — update those assertions to expect Profile.

- [ ] **Step 7: Commit**

```bash
git add includes/dashboard/class-dashboard-views.php includes/dashboard/class-member-dashboard.php tests/php/DashboardViewsTest.php tests/php/MemberDashboardTest.php
git commit -m "A Profile page in the member area, with Account left to look after paying"
```

---

### Task 7: Saving what the member typed

**Files:**
- Create: `includes/profile/class-profile-form.php`
- Modify: `includes/bootstrap.php`, `includes/dashboard/class-member-dashboard.php` (pass the renderer at the live call site)
- Test: `tests/php/ProfileFormTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Profile_Store`, `Blueworx_Clubhouse_Profile_Values`, `Blueworx_Clubhouse_Profile_Panel`.
- Produces:
  - `Blueworx_Clubhouse_Profile_Form::boot(): void` — hooks `admin_post_clubhouse_profile_save`.
  - `Blueworx_Clubhouse_Profile_Form::panel( string $which ): string` — the renderer passed to `view_body()`; returns `''` for anything but `'profile'`.
  - `Blueworx_Clubhouse_Profile_Form::apply( Blueworx_Clubhouse_Profile_Store $store, int $user_id, array $post ): array{saved:bool,missing:array<int,string>}` — pure enough to unit test; does the filtering, required check and save.

- [ ] **Step 1: Write the failing test**

Create `tests/php/ProfileFormTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class ProfileFormTest extends TestCase {

	private Blueworx_Clubhouse_Profile_Store $store;

	protected function setUp(): void {
		wp_stub_reset();
		$this->store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$this->store->save_fields(
			array(
				array( 'label' => 'Shirt size', 'type' => 'text', 'who' => 'member' ),
				array( 'label' => 'Emergency contact', 'type' => 'text', 'who' => 'member', 'required' => '1' ),
				array( 'label' => 'Squad number', 'type' => 'number', 'who' => 'club' ),
				array( 'label' => 'Notes', 'type' => 'textarea', 'who' => 'private' ),
			)
		);
	}

	public function test_a_complete_submission_is_saved(): void {
		$result = Blueworx_Clubhouse_Profile_Form::apply(
			$this->store,
			7,
			array( 'clubhouse_profile' => array( 'shirt_size' => 'Medium', 'emergency_contact' => 'Jo 07000 000000' ) )
		);
		$this->assertTrue( $result['saved'] );
		$this->assertSame( array(), $result['missing'] );
		$answers = $this->store->answers( 7, $this->store->fields() );
		$this->assertSame( 'Medium', $answers['shirt_size'] );
	}

	public function test_a_required_field_left_blank_saves_nothing_at_all(): void {
		$result = Blueworx_Clubhouse_Profile_Form::apply(
			$this->store,
			7,
			array( 'clubhouse_profile' => array( 'shirt_size' => 'Medium', 'emergency_contact' => '' ) )
		);
		$this->assertFalse( $result['saved'] );
		$this->assertSame( array( 'Emergency contact' ), $result['missing'] );
		// Nothing partial: a member who has to come back should find the page
		// as they left it, not half-written.
		$this->assertSame( '', $this->store->answers( 7, $this->store->fields() )['shirt_size'] );
	}

	public function test_a_tampered_submission_cannot_set_a_club_field(): void {
		Blueworx_Clubhouse_Profile_Form::apply(
			$this->store,
			7,
			array( 'clubhouse_profile' => array( 'emergency_contact' => 'Jo', 'squad_number' => '9', 'notes' => 'Anything' ) )
		);
		$answers = $this->store->answers( 7, $this->store->fields() );
		$this->assertSame( '', $answers['squad_number'] );
		$this->assertSame( '', $answers['notes'] );
	}

	public function test_a_submission_for_nobody_saves_nothing(): void {
		$result = Blueworx_Clubhouse_Profile_Form::apply( $this->store, 0, array( 'clubhouse_profile' => array( 'shirt_size' => 'Medium' ) ) );
		$this->assertFalse( $result['saved'] );
	}

	public function test_the_renderer_answers_only_to_its_own_panel_name(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Profile_Form::panel( 'something-else' ) );
	}
}
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `vendor/bin/phpunit --filter ProfileFormTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Profile_Form" not found`.

- [ ] **Step 3: Write the implementation**

Create `includes/profile/class-profile-form.php`:

```php
<?php
// includes/profile/class-profile-form.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member's Profile card: drawing it, and saving what they typed.
 *
 * All or nothing on save. A submission that fails its required check writes
 * nothing at all, so a member who has to come back finds the page as they left
 * it rather than half-written.
 *
 * The result of a save travels back in a query argument rather than a session,
 * because the member area is a plain server-rendered page and a redirect after
 * post is the only thing that stops a refresh saving twice.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Form {

	/** What came back from a save, for the member to read. */
	public const RESULT_ARG = 'clubhouse_profile_result';

	public static function boot(): void {
		add_action( 'admin_post_' . Blueworx_Clubhouse_Profile_Panel::ACTION, array( __CLASS__, 'handle' ) );
	}

	/** The card, for Member_Dashboard's panel renderer. */
	public static function panel( string $which ): string {
		if ( 'profile' !== $which ) {
			return '';
		}
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return '';
		}
		$store  = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Options_Storage() );
		$fields = $store->fields();
		if ( array() === $fields ) {
			return '';
		}

		return Blueworx_Clubhouse_Profile_Panel::render(
			$fields,
			$store->answers( $user_id, $fields ),
			admin_url( 'admin-post.php' ),
			wp_nonce_field( Blueworx_Clubhouse_Profile_Panel::NONCE, '_wpnonce', true, false ),
			self::notices()
		);
	}

	/**
	 * What the last save had to say, read off the address.
	 *
	 * @return array<int,array{type:string,text:string}>
	 */
	private static function notices(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the outcome of a redirect, not acting on it.
		$raw = $_GET[ self::RESULT_ARG ] ?? '';
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		if ( 'saved' === $raw ) {
			return array( array( 'type' => 'success', 'text' => 'Saved. Thank you.' ) );
		}
		$missing = array_filter( array_map( 'sanitize_text_field', explode( '|', $raw ) ) );
		if ( array() === $missing ) {
			return array();
		}
		return array(
			array(
				'type' => 'error',
				'text' => 'Nothing was saved — your club needs ' . implode( ', ', $missing ) . '.',
			),
		);
	}

	/** The admin-post handler. Verifies, applies, redirects back. */
	public static function handle(): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		check_admin_referer( Blueworx_Clubhouse_Profile_Panel::NONCE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately above.
		$post   = wp_unslash( $_POST );
		$store  = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Options_Storage() );
		$result = self::apply( $store, $user_id, is_array( $post ) ? $post : array() );

		$back = wp_get_referer();
		if ( false === $back || '' === $back ) {
			$back = home_url( '/' );
		}
		$back = remove_query_arg( self::RESULT_ARG, $back );
		$back = add_query_arg(
			self::RESULT_ARG,
			$result['saved'] ? 'saved' : rawurlencode( implode( '|', $result['missing'] ) ),
			$back
		);
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Apply one member's submission. WordPress-free, so the rules are testable.
	 *
	 * @param array<string,mixed> $post
	 * @return array{saved:bool,missing:array<int,string>}
	 */
	public static function apply( Blueworx_Clubhouse_Profile_Store $store, int $user_id, array $post ): array {
		if ( $user_id <= 0 ) {
			return array( 'saved' => false, 'missing' => array() );
		}
		$fields = $store->fields();
		$result = Blueworx_Clubhouse_Profile_Values::from_member_post( $fields, $post );
		if ( array() !== $result['missing'] ) {
			return array( 'saved' => false, 'missing' => $result['missing'] );
		}
		$store->save_answers( $user_id, $result['values'] );
		return array( 'saved' => true, 'missing' => array() );
	}
}
```

- [ ] **Step 4: Add the stubs the test needs**

`ProfileFormTest` only exercises `apply()` and `panel()`. `panel()` calls `get_current_user_id()`; if `tests/php/wp-stubs.php` does not already stub it, add:

```php
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() { global $wp_stub_current_user_id; return (int) ( $wp_stub_current_user_id ?? 0 ); }
}
```

and reset `$wp_stub_current_user_id = 0;` in `wp_stub_reset()`.

- [ ] **Step 5: Wire it up**

In `includes/bootstrap.php`, under `// Profile.`:

```php
require_once __DIR__ . '/profile/class-profile-form.php';
```

and call `Blueworx_Clubhouse_Profile_Form::boot();` alongside the other `::boot()` calls in the same file (match the existing style — if boots are wrapped in an `is_admin()` check, this one must run in both admin and front end, because `admin-post.php` is admin but the card renders on the front end).

In `includes/dashboard/class-member-dashboard.php`, at the live `view_body()` call site inside `screen()`, pass the renderer:

```php
		$body = self::view_body( $view, '', $home_url, array( 'Blueworx_Clubhouse_Profile_Form', 'panel' ) );
```

- [ ] **Step 6: Run the test and watch it pass**

Run: `vendor/bin/phpunit --filter ProfileFormTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/profile/class-profile-form.php includes/bootstrap.php includes/dashboard/class-member-dashboard.php tests/php/ProfileFormTest.php tests/php/wp-stubs.php
git commit -m "A member fills in the club's questions and saves them"
```

---

### Task 8: The WordPress user profile screen

**Files:**
- Create: `includes/profile/class-profile-user-screen.php`
- Test: `tests/php/ProfileUserScreenTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Profile_Store`, `Blueworx_Clubhouse_Profile_Panel`, `Blueworx_Clubhouse_Profile_Values`.
- Produces:
  - `Blueworx_Clubhouse_Profile_User_Screen::boot(): void`
  - `Blueworx_Clubhouse_Profile_User_Screen::table( array $fields, array $answers ): string` — the wp-admin markup, pure, every field editable.
  - `Blueworx_Clubhouse_Profile_User_Screen::save( Blueworx_Clubhouse_Profile_Store $store, int $user_id, array $post ): void`

- [ ] **Step 1: Write the failing test**

Create `tests/php/ProfileUserScreenTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class ProfileUserScreenTest extends TestCase {

	private Blueworx_Clubhouse_Profile_Store $store;

	protected function setUp(): void {
		wp_stub_reset();
		$this->store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$this->store->save_fields(
			array(
				array( 'label' => 'Shirt size', 'type' => 'text', 'who' => 'member' ),
				array( 'label' => 'Squad number', 'type' => 'number', 'who' => 'club' ),
				array( 'label' => 'Notes', 'type' => 'textarea', 'who' => 'private' ),
			)
		);
	}

	public function test_staff_see_every_field_including_the_private_one(): void {
		$html = Blueworx_Clubhouse_Profile_User_Screen::table( $this->store->fields(), array() );
		$this->assertStringContainsString( 'Shirt size', $html );
		$this->assertStringContainsString( 'Squad number', $html );
		$this->assertStringContainsString( 'Notes', $html );
	}

	public function test_every_field_is_editable_here_even_the_members_own(): void {
		$html = Blueworx_Clubhouse_Profile_User_Screen::table( $this->store->fields(), array() );
		foreach ( array( 'shirt_size', 'squad_number', 'notes' ) as $key ) {
			$this->assertStringContainsString( 'clubhouse_profile[' . $key . ']', $html );
		}
	}

	public function test_a_private_field_is_marked_as_one_so_staff_know(): void {
		$html = Blueworx_Clubhouse_Profile_User_Screen::table( $this->store->fields(), array() );
		$this->assertStringContainsString( 'never sees', $html );
	}

	public function test_a_club_with_no_fields_draws_nothing(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Profile_User_Screen::table( array(), array() ) );
	}

	public function test_staff_can_write_every_field(): void {
		Blueworx_Clubhouse_Profile_User_Screen::save(
			$this->store,
			7,
			array( 'clubhouse_profile' => array( 'shirt_size' => 'Large', 'squad_number' => '9', 'notes' => 'Paid in cash' ) )
		);
		$answers = $this->store->answers( 7, $this->store->fields() );
		$this->assertSame( 'Large', $answers['shirt_size'] );
		$this->assertSame( '9', $answers['squad_number'] );
		$this->assertSame( 'Paid in cash', $answers['notes'] );
	}

	public function test_a_required_field_left_blank_never_blocks_staff(): void {
		$this->store->save_fields( array( array( 'label' => 'Shirt size', 'type' => 'text', 'who' => 'member', 'required' => '1' ) ) );
		Blueworx_Clubhouse_Profile_User_Screen::save( $this->store, 7, array( 'clubhouse_profile' => array( 'shirt_size' => '' ) ) );
		$this->assertSame( '', $this->store->answers( 7, $this->store->fields() )['shirt_size'] );
	}

	public function test_a_save_for_nobody_writes_nothing(): void {
		Blueworx_Clubhouse_Profile_User_Screen::save( $this->store, 0, array( 'clubhouse_profile' => array( 'shirt_size' => 'Large' ) ) );
		$this->assertSame( array(), wp_stub_calls( 'update_user_meta' ) );
	}
}
```

If `update_user_meta` is not recorded by the stub, add `wp_stub_record( 'update_user_meta', func_get_args() );` as its first line in `tests/php/wp-stubs.php`.

- [ ] **Step 2: Run the test and watch it fail**

Run: `vendor/bin/phpunit --filter ProfileUserScreenTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

Create `includes/profile/class-profile-user-screen.php`:

```php
<?php
// includes/profile/class-profile-user-screen.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The club's custom fields on WordPress's own user profile screens.
 *
 * Everything is editable here, including the fields a member never sees — this
 * IS where a squad number or a DBS date gets set. Required is not enforced:
 * staff routinely change one thing about a member, and blocking that on an
 * unrelated required field would make the screen unusable.
 *
 * Nothing is drawn or saved unless the current user can edit the user in
 * question. WordPress asks that question for us; we ask it again rather than
 * assume the hook only fires when it should.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_User_Screen {

	public const NONCE = 'clubhouse_profile_user';

	public static function boot(): void {
		add_action( 'show_user_profile', array( __CLASS__, 'render' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'handle' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'handle' ) );
	}

	/** @param \WP_User|object $user */
	public static function render( $user ): void {
		$user_id = (int) ( $user->ID ?? 0 );
		if ( $user_id <= 0 || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		$store  = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Options_Storage() );
		$fields = $store->fields();
		if ( array() === $fields ) {
			return;
		}
		wp_nonce_field( self::NONCE, '_clubhouse_profile_nonce' );
		// Built by a pure method and echoed whole; every value inside is escaped there.
		echo self::table( $fields, $store->answers( $user_id, $fields ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function handle( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST['_clubhouse_profile_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_clubhouse_profile_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately above.
		$post = wp_unslash( $_POST );
		self::save(
			new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Options_Storage() ),
			$user_id,
			is_array( $post ) ? $post : array()
		);
	}

	/**
	 * The club's fields as a standard WordPress settings table.
	 *
	 * @param array<int,array<string,mixed>>         $fields
	 * @param array<string,string|array<int,string>> $answers
	 */
	public static function table( array $fields, array $answers ): string {
		if ( array() === $fields ) {
			return '';
		}
		$out = '<h2>Club details</h2>'
			. '<p>What your club keeps about this member. Set here by club staff; members fill in their own on their Profile page.</p>'
			. '<table class="form-table" role="presentation"><tbody>';

		foreach ( $fields as $field ) {
			$key   = (string) $field['key'];
			$type  = (string) $field['type'];
			$id    = 'clubhouse-profile-' . $key;
			$name  = Blueworx_Clubhouse_Profile_Values::POST_KEY . '[' . $key . ']' . ( 'multiselect' === $type ? '[]' : '' );
			$value = $answers[ $key ] ?? ( 'multiselect' === $type ? array() : '' );

			$out .= '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( (string) $field['label'] ) . '</label></th><td>';
			// Every field is editable on this screen, so 'editable' is always true —
			// the who setting governs the MEMBER's page, not this one.
			$out .= Blueworx_Clubhouse_Profile_Panel::control( $field, $value, $name, true );

			$notes = array();
			if ( '' !== (string) ( $field['help'] ?? '' ) ) {
				$notes[] = (string) $field['help'];
			}
			if ( 'private' === (string) $field['who'] ) {
				$notes[] = 'The member never sees this.';
			} elseif ( 'club' === (string) $field['who'] ) {
				$notes[] = 'The member can see this but cannot change it.';
			}
			if ( array() !== $notes ) {
				$out .= '<p class="description">' . esc_html( implode( ' ', $notes ) ) . '</p>';
			}
			$out .= '</td></tr>';
		}

		return $out . '</tbody></table>';
	}

	/**
	 * @param array<string,mixed> $post
	 */
	public static function save( Blueworx_Clubhouse_Profile_Store $store, int $user_id, array $post ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		$store->save_answers( $user_id, Blueworx_Clubhouse_Profile_Values::from_admin_post( $store->fields(), $post ) );
	}
}
```

Add `esc_attr` to `tests/php/wp-stubs.php` if it is not already stubbed:

```php
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
```

- [ ] **Step 4: Wire it up**

In `includes/bootstrap.php`, under `// Profile.`:

```php
require_once __DIR__ . '/profile/class-profile-user-screen.php';
```

and `Blueworx_Clubhouse_Profile_User_Screen::boot();` beside the other boots.

- [ ] **Step 5: Run the test and watch it pass**

Run: `vendor/bin/phpunit --filter ProfileUserScreenTest`
Expected: PASS.

- [ ] **Step 6: Run the whole unit suite**

Run: `vendor/bin/phpunit`
Expected: PASS, every test.

- [ ] **Step 7: Commit**

```bash
git add includes/profile/class-profile-user-screen.php tests/php/ProfileUserScreenTest.php tests/php/wp-stubs.php includes/bootstrap.php
git commit -m "Club staff set a member's details on the WordPress user screen"
```

---

### Task 9: Styling the member's card

**Files:**
- Modify: `assets/css/` — the member-area stylesheet that serves the dashboard shell (find it by grepping for `clubhouse-member__quick` in `assets/` and `includes/render/`; if the shell's CSS is generated in `class-theme-css.php`, add there instead)
- Test: `tests/tap-targets.spec.js` and `tests/no-sideways-scroll.spec.js` already cover the rules this must not break; no new test file.

**Interfaces:**
- Consumes: the class names emitted by `Profile_Panel` — `clubhouse-profile`, `__notice`, `__notice--error`, `__notice--success`, `__fields`, `__row`, `__label`, `__value`, `__help`, `__input`, `__tick`, `__save`.
- Produces: no PHP interface.

- [ ] **Step 1: Find where the member area's CSS lives**

Run: `grep -rn "clubhouse-member__quick" assets/ includes/ --include=*.css --include=*.php | head`
Whichever file defines that class is the file this task edits.

- [ ] **Step 2: Add the rules**

Use the existing custom properties that file already uses for surface, text and accent colours — do not introduce new hex values. Requirements:

- `.clubhouse-profile__row` stacks label, control and help, with the same vertical rhythm as the cards around it.
- `.clubhouse-profile__input` fills the card's width, has a visible focus ring, and a minimum height of 44px so the tap-target test keeps passing.
- `.clubhouse-profile__save` matches the member area's existing primary button.
- `.clubhouse-profile__notice--error` uses the error colour already defined for the shell; `--success` the positive one.
- `.clubhouse-profile__value` reads as text, not as a disabled input.
- Nothing may exceed the card's width — `max-width: 100%` on the select and textarea, which is what the sideways-scroll test checks.

- [ ] **Step 3: Check nothing regressed**

Run: `npm run wp:up` then `npx playwright test tap-targets no-sideways-scroll contrast --config playwright.config.js`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add assets/
git commit -m "Style the member's profile card to match the rest of the member area"
```

---

### Task 10: The journeys, end to end

**Files:**
- Create: `tests/profile-builder.spec.js`
- Modify: `tests/global-setup.js` if a member user with a known password does not already exist (check first — `member-sign-in.spec.js` and `member-dashboard.spec.js` will show what is already seeded)

**Interfaces:**
- Consumes: the running WordPress harness with SureCart installed (`npm run wp:up` then `npm run wp:shop`).
- Produces: no code interface.

- [ ] **Step 1: Read what the harness already gives you**

Run: `sed -n '1,120p' tests/global-setup.js` and `sed -n '1,60p' tests/member-dashboard.spec.js`
Note the existing sign-in helper, the member's credentials, and how a spec reaches the member area. Reuse them; do not invent a second way in.

- [ ] **Step 2: Write the spec**

Create `tests/profile-builder.spec.js`. Follow the existing specs' style — `const { test, expect } = require('@playwright/test');`, a comment naming the issue, one `test()` per journey:

```js
const { test, expect } = require('@playwright/test');

// Issue #276: a club can invent its own member fields, members fill in their
// own, and the club sees every one of them on the WordPress user screen.

// Uses the same admin and member sign-in helpers as member-dashboard.spec.js —
// see that file for where they come from.

test.describe.serial('profile builder', () => {

  test('an owner adds three fields on Setup', async ({ page }) => {
    // Sign in as the administrator, open wp-admin ?page=clubhouse-setup,
    // click the Members tab, then fill the blank field row:
    //   1. label "Shirt size", type "select", choices "Small\nMedium\nLarge", who "member"
    //   2. label "Squad number", type "number", who "club"
    //   3. label "Notes", type "textarea", who "private"
    // Use the "Add another field" button between each, then Save changes.
    // Assert the three labels are on the page after the save.
  });

  test('a member fills in their own field and it saves', async ({ page }) => {
    // Sign in as the member, go to the member area with ?view=profile.
    // Assert "Shirt size" is present with a <select>.
    // Choose "Medium", click "Save my details".
    // Assert the success notice, and that reloading still shows "Medium" chosen.
  });

  test('a club field is shown but cannot be changed', async ({ page }) => {
    // As the member on ?view=profile:
    // Assert "Squad number" is present.
    // Assert there is no form control named clubhouse_profile[squad_number].
  });

  test('a private field never reaches the member', async ({ page }) => {
    // As the member on ?view=profile:
    // const html = await page.content();
    // expect(html).not.toContain('Notes');
    // expect(html).not.toContain('clubhouse_profile[notes]');
  });

  test('the club sees every field, and the answer, on the WordPress user screen', async ({ page }) => {
    // Sign in as the administrator, open the member's user-edit.php.
    // Assert "Club details" is present.
    // Assert the Shirt size control holds "Medium" — the value the member saved.
    // Assert Notes is present and editable here.
  });

  test('Account still shows how the member pays', async ({ page }) => {
    // As the member, go to ?view=account.
    // Assert the page resolves (no redirect to the dashboard) and the billing
    // panel is present — the old bookmark still works.
  });
});
```

Replace every comment with real Playwright code, using the selectors the implementation actually emits. Run each test as you write it; do not write all six then run once.

- [ ] **Step 3: Run the specs**

Run: `npm run wp:up`, then `npm run wp:shop`, then `npx playwright test profile-builder`
Expected: PASS, all six.

- [ ] **Step 4: Run the whole Playwright suite**

Run: `npx playwright test`
Expected: PASS. `member-area-tabs.spec.js` and `member-dashboard.spec.js` may assert on the nav — Profile is a new item and Account has moved off the phone bar, so update those assertions to match rather than reverting Task 6.

- [ ] **Step 5: Commit**

```bash
git add tests/
git commit -m "Cover the profile builder end to end"
```

---

### Task 11: Version, changelog, and the final check

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (the `Version:` header and any version constant)
- Modify: `package.json` if it carries a version
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Bump to 0.94.0**

Run: `grep -rn "0\.93\.1" blueworx-labs-clubhouse.php package.json includes/ | head`
Change every hit to `0.94.0`. A minor bump: this is new functionality, not a fix.

- [ ] **Step 2: Write the changelog entry**

Add a new section at the top of `CHANGELOG.md`, matching the existing format exactly (check the top of the file first). Written for a club owner:

```markdown
## 0.94.0

- Your club can now keep whatever it needs about a member — shirt size,
  emergency contact, squad number, anything. Add the questions under
  Clubhouse Setup, on the Members tab.
- Members answer their own on a new Profile page in the member area, which
  also holds their name, email and password.
- Some things only the club fills in, and some the member never sees. You
  choose, question by question.
- Account is now just how a member pays you.
```

- [ ] **Step 3: Run everything**

Run, in order:
```bash
vendor/bin/phpunit
npx playwright test
composer lint
```
Expected: the two test suites PASS. Record any PHPCS findings — **do not fix them yet**, and do not loop lint → fix → lint. They go to Luke at the end of the session for a decision.

- [ ] **Step 4: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "Release 0.94.0 — the profile builder"
```

- [ ] **Step 5: Push and open the pull request**

```bash
git push -u origin profile-builder
gh pr create --base main --head profile-builder --title "Clubs can keep their own details about a member" --body "..."
```

The PR body says what it does and anything Luke has to decide. Not a walkthrough. Close with `Closes #276.`

---

## Self-Review

**Spec coverage.** Every section of the design maps to a task: field types and definitions → Task 1; answer validation and the three who-settings → Task 2; storage in options and user meta, and delete-keeps-answers → Task 3; the builder on Setup → Members → Task 4; the member's card and private-fields-never-render → Task 5; the Profile view and the Account split → Task 6; the member's save, required enforcement and the security rules → Task 7; the WordPress user screen → Task 8; the look → Task 9; the journeys the spec lists → Task 10; version, changelog and delivery → Task 11. The 30-field cap is enforced in Task 1 and surfaced in Task 4. The `forget` path is built in Task 3 and reached in Task 4.

**Placeholders.** Task 9 and Task 10 describe rather than show — deliberately, and they are the two tasks that must be written against what the code actually emits and against CSS custom properties this plan cannot see. Both name the exact command that reveals what is needed first. Every other step carries the real content.

**Type consistency.** `who` values are `member` / `club` / `private` in every task. Type keys are `text` / `textarea` / `number` / `date` / `select` / `multiselect` / `checkbox` throughout. The POST key is `clubhouse_profile` everywhere, via `Profile_Values::POST_KEY`. `Profile_Panel::control()` has the same four-parameter signature in Task 5 where it is defined and Task 8 where it is reused. `view_body()` gains one optional fourth parameter in Task 6 and is called with it in Task 7.
