# Playtomic Connector Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An administrator reviews a table of members, ticks who goes, downloads a Playtomic chain-import CSV of exactly those people, and the plugin gains a tested read-only Playtomic API client for later features.

**Architecture:** Three new standard profile fields (phone, gender, date of birth) ride the existing profile machinery. A new `includes/playtomic/` module holds pure classes (settings, secret, CSV, members, client) with WordPress touched only in a controller, a transport and a SureCart adapter. One admin screen under Clubhouse, administrators only, with every action going through `admin-post.php`.

**Tech Stack:** PHP 8 (strict types), WordPress options and user meta, `wp_remote_request`, sodium/openssl for the secret, PHPUnit (`tests/php`, WordPress stubs in `tests/php/wp-stubs.php`), Playwright (`tests/*.spec.js`, WordPress project), PHPCS.

**Spec:** `docs/superpowers/specs/2026-10-15-playtomic-connector-design.md`

## Global Constraints

- Every change is on branch `add-playtomic-connector`, never main. Work starts from a GitHub issue (Task 1).
- Version bumps to **0.107.0** in `blueworx-labs-clubhouse.php` (both the header and `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `CHANGELOG.md` gets an entry in the club's own words (Task 14).
- No new dependency. `approved-deps.json` is not touched.
- Pure classes (`includes/playtomic/class-playtomic-{secret,settings,csv,members,client,screen,exception}.php`) must load under `tests/php/bootstrap.php` with no WordPress beyond the stubs in `tests/php/wp-stubs.php`.
- Every admin screen string is plain English, no jargon; the screen is built from `Blueworx_Clubhouse_Admin_Shell` and design-system classes (`bw-card`, `bw-table`, `bw-btn`, `bw-notice`, `bw-badge`, `bw-check`, `bw-field`, `bw-input`, `bw-select`).
- Dates in the CSV are always `YYYY-MM-DD`; phone numbers are written as `+61` followed by digits; the file is UTF-8, comma-separated, CRLF, no BOM.
- Secrets and tokens never appear in code, logs, the repo, test fixtures with real values, or the screen after saving.
- Playtomic facts from the official docs (read 15 Oct 2026): token `POST https://thirdparty.playtomic.io/api/v1/oauth/token` body `{client_id, secret}` → `{token, token_type:"BEARER", expires_in:3600}`; bookings `GET /api/v1/bookings` page-based (`page` from 0, `size` max 200, bare JSON array); players `GET /api/v1/venues/{venue_id}/players` cursor-based (`limit` max 100, `cursor_id`, response `{has_more,next_cursor_id,data}`), docs' curl examples use `/venue/` singular; payments `GET /api/v1/payments` cursor-based (`limit` max 100), first call per venue may return `202` with `Retry-After`; rate limit 400 requests per 10 minutes → `429`.
- PHPCS runs once at the end (Task 14) and findings are reported to Luke, not auto-fixed.
- Commit after every task with a one-line plain message ending in the attribution line `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.

## Review Focus

1. **A phone typed with spaces, brackets and a leading `+61` or `0`** must come out as `+61` plus digits once, never `+6161…` or `+610…`. Pinned in Task 5 (`test_phone_variants`).
2. **A member with an email but no first, last or display name** must be listed with a warning and never written to the file, even if ticked. Pinned in Task 6 (`test_incomplete_member_is_never_exportable`) and Task 11 (download ignores ticked incomplete ids).
3. **A token that expires mid-batch** must be refreshed once on a 401 and the call retried, and a second 401 must surface as a plain error, not a loop. Pinned in Task 8 (`test_401_refreshes_once_then_gives_up`).
4. **A venue repeater row left blank** must be dropped on save, and a venue id with surrounding whitespace must be trimmed, so the header never reads `CAT_IMP--cat_name`. Pinned in Task 4 (`test_blank_venue_rows_are_dropped`).
5. **A download with nothing ticked, or with ids that are not members** must return to the screen with a notice and mark nobody. Pinned in Task 11 (`test_download_with_no_valid_ids_marks_nobody`).

---

### Task 1: Raise the GitHub issue

**Files:** none in the repo.

- [ ] **Step 1: Create the issue**

```bash
gh issue create --repo blueworx-io/blueworx_labs_clubhouse \
  --title "Add a Playtomic connector: members CSV export and API client" \
  --body "Goal: an administrator ticks members in a review table and downloads the CSV Playtomic Manager's chain import takes, with exported members marked so they are not sent twice. Phone, gender and date of birth become standard member fields. A read-only Playtomic API client (token, caching, paging, errors) with a Test connection button is built for later features.

In scope: the Clubhouse → Playtomic screen, settings (credentials, who qualifies, venues), the CSV, the client, a guide, tests, a README under docs/integrations.
Out of scope: showing bookings or players on the site, scheduled syncs, writing to Playtomic.
Done when: the WordPress spec downloads a CSV for a ticked member and sees them marked; PHPUnit covers the CSV, members, settings and client; Test connection reports the venues from the token.

Design: docs/superpowers/specs/2026-10-15-playtomic-connector-design.md"
```

Expected: a URL for the new issue. Note the number; use `Closes #<n>` in the pull request later.

---

### Task 2: Standard profile fields (phone, gender, date of birth)

**Files:**
- Modify: `includes/profile/class-profile-fields.php`
- Modify: `includes/profile/class-profile-store.php`
- Modify: `includes/admin/class-setup-storage.php:61` (`profile_rows`)
- Test: `tests/php/ProfileStandardFieldsTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Profile_Fields::STANDARD_KEYS` (`array('phone','gender','birthdate')`), `Blueworx_Clubhouse_Profile_Fields::standard( array $hidden = array() ): array` (definitions in the same shape as `sanitise_one()` returns), `Blueworx_Clubhouse_Profile_Fields::GENDER_CHOICES` (`array('Male','Female','Other','Prefer not to say')`).
- Produces: `Blueworx_Clubhouse_Profile_Store::custom_fields(): array` (the club's own, from the option), `fields()` now returns standard + custom, `hidden_standard(): array<int,string>`, `set_hidden_standard( array $keys ): void`, option `profile_standard_hidden`.
- Consumes: nothing new.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/php/ProfileStandardFieldsTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProfileStandardFieldsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_standard_fields_come_first_and_in_order(): void {
		$store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$store->save_fields( array( array( 'label' => 'Shirt size', 'type' => 'text', 'who' => 'member' ) ) );

		$keys = array_column( $store->fields(), 'key' );
		$this->assertSame( array( 'phone', 'gender', 'birthdate', 'shirt_size' ), $keys );
	}

	public function test_standard_definitions_are_member_filled_and_typed(): void {
		$std = Blueworx_Clubhouse_Profile_Fields::standard();
		$this->assertSame( 'text', $std[0]['type'] );
		$this->assertSame( 'select', $std[1]['type'] );
		$this->assertSame( Blueworx_Clubhouse_Profile_Fields::GENDER_CHOICES, $std[1]['choices'] );
		$this->assertSame( 'date', $std[2]['type'] );
		foreach ( $std as $field ) {
			$this->assertSame( 'member', $field['who'] );
			$this->assertFalse( $field['required'] );
		}
	}

	public function test_a_custom_field_can_never_take_a_standard_key(): void {
		$fields = Blueworx_Clubhouse_Profile_Fields::sanitise_all(
			array( array( 'label' => 'Phone', 'type' => 'text' ), array( 'key' => 'gender', 'label' => 'Anything' ) )
		);
		$this->assertSame( 'phone_2', $fields[0]['key'] );
		$this->assertNotSame( 'gender', $fields[1]['key'] );
	}

	public function test_custom_fields_excludes_the_standard_ones(): void {
		$store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$store->save_fields( array( array( 'label' => 'Notes', 'type' => 'textarea' ) ) );
		$this->assertSame( array( 'notes' ), array_column( $store->custom_fields(), 'key' ) );
	}

	public function test_hidden_standard_fields_are_left_out(): void {
		$store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$store->set_hidden_standard( array( 'gender', 'nonsense' ) );
		$this->assertSame( array( 'gender' ), $store->hidden_standard() );
		$this->assertSame( array( 'phone', 'birthdate' ), array_column( $store->fields(), 'key' ) );
	}

	public function test_answers_read_standard_meta_keys(): void {
		$store = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Fake_Storage() );
		update_user_meta( 7, 'clubhouse_profile_phone', '0412 345 678' );
		$answers = $store->answers( 7, $store->fields() );
		$this->assertSame( '0412 345 678', $answers['phone'] );
		$this->assertSame( '', $answers['gender'] );
	}
}
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `vendor/bin/phpunit tests/php/ProfileStandardFieldsTest.php`
Expected: FAIL — `standard()` undefined, keys not reserved.

- [ ] **Step 3: Add the standard definitions to Profile_Fields**

In `includes/profile/class-profile-fields.php`, after `MAX_FIELDS`:

```php
	/** The three fields every club has, in the order they are drawn. */
	public const STANDARD_KEYS = array( 'phone', 'gender', 'birthdate' );

	/** Stored as the choice text, the way every select field stores its answer. */
	public const GENDER_CHOICES = array( 'Male', 'Female', 'Other', 'Prefer not to say' );

	/**
	 * The standard fields as definitions, minus any the club has hidden.
	 *
	 * Same shape as sanitise_one() returns, so every surface that draws a
	 * custom field draws these without a branch.
	 *
	 * @param array<int,string> $hidden Standard keys the club switched off.
	 * @return array<int,array{key:string,label:string,type:string,choices:array<int,string>,help:string,required:bool,who:string,column:bool}>
	 */
	public static function standard( array $hidden = array() ): array {
		$all = array(
			array( 'key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'choices' => array(), 'help' => 'Mobile preferred.', 'required' => false, 'who' => 'member', 'column' => false ),
			array( 'key' => 'gender', 'label' => 'Gender', 'type' => 'select', 'choices' => self::GENDER_CHOICES, 'help' => '', 'required' => false, 'who' => 'member', 'column' => false ),
			array( 'key' => 'birthdate', 'label' => 'Date of birth', 'type' => 'date', 'choices' => array(), 'help' => '', 'required' => false, 'who' => 'member', 'column' => false ),
		);
		return array_values( array_filter( $all, static fn( array $f ): bool => ! in_array( $f['key'], $hidden, true ) ) );
	}
```

In `sanitise_all()`, change `$taken = array();` to `$taken = self::STANDARD_KEYS;` so a custom key never collides. `sanitise_one()` already regenerates a key that is taken.

- [ ] **Step 4: Teach Profile_Store about standard fields**

In `includes/profile/class-profile-store.php`, add after `OPTION`:

```php
	public const HIDDEN_OPTION = 'profile_standard_hidden';
```

Replace `fields()` with:

```php
	/** Standard fields first, then the club's own. @return array<int,array<string,mixed>> */
	public function fields(): array {
		return array_merge( Blueworx_Clubhouse_Profile_Fields::standard( $this->hidden_standard() ), $this->custom_fields() );
	}

	/** Only the club's own fields — what the Members tab edits. @return array<int,array<string,mixed>> */
	public function custom_fields(): array {
		$raw = $this->storage->get( self::OPTION, array() );
		return is_array( $raw ) ? Blueworx_Clubhouse_Profile_Fields::sanitise_all( $raw ) : array();
	}

	/** @return array<int,string> */
	public function hidden_standard(): array {
		$raw = $this->storage->get( self::HIDDEN_OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_intersect( Blueworx_Clubhouse_Profile_Fields::STANDARD_KEYS, array_map( 'strval', $raw ) ) );
	}

	/** @param array<int,string> $keys */
	public function set_hidden_standard( array $keys ): void {
		$this->storage->set( self::HIDDEN_OPTION, array_values( array_intersect( Blueworx_Clubhouse_Profile_Fields::STANDARD_KEYS, array_map( 'strval', $keys ) ) ) );
	}
```

- [ ] **Step 5: Keep the Members tab repeater to custom fields**

In `includes/admin/class-setup-storage.php` `profile_rows()`, change `$profile->fields()` to `$profile->custom_fields()`.

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit tests/php/ProfileStandardFieldsTest.php tests/php/ProfileFieldsTest.php tests/php/SetupStorageTest.php` (and any other `Profile*Test.php` present — `ls tests/php | grep -i profile`).
Expected: PASS. If an existing test asserted `fields()` equals the saved custom list, update it to use `custom_fields()`.

- [ ] **Step 7: Commit**

```bash
git add includes/profile tests/php/ProfileStandardFieldsTest.php includes/admin/class-setup-storage.php
git commit -m "Phone, gender and date of birth are standard member fields

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3: Switches on the Members tab to hide a standard field

**Files:**
- Modify: `includes/admin/class-setup-fields.php:297-396` (`members_tab`)
- Modify: `includes/admin/class-setup-storage.php:61` (read) and `:139` (write)
- Test: `tests/php/SetupStorageTest.php` (add two tests)

**Interfaces:**
- Produces: setup values `ask_phone`, `ask_gender`, `ask_birthdate` (bool, true = asked).
- Consumes: `Profile_Store::hidden_standard()` / `set_hidden_standard()` from Task 2.

- [ ] **Step 1: Write the failing tests** (append to `SetupStorageTest`)

```php
	public function test_standard_field_switches_read_as_asked_unless_hidden(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Profile_Store( $storage ) )->set_hidden_standard( array( 'gender' ) );
		$values = ( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->read();
		$this->assertTrue( $values['ask_phone'] );
		$this->assertFalse( $values['ask_gender'] );
		$this->assertTrue( $values['ask_birthdate'] );
	}

	public function test_switching_a_standard_field_off_hides_it(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->write( array( 'ask_phone' => true, 'ask_gender' => false, 'ask_birthdate' => false ) );
		$this->assertSame( array( 'gender', 'birthdate' ), ( new Blueworx_Clubhouse_Profile_Store( $storage ) )->hidden_standard() );
	}
```

Check the exact names of the read and write methods in `Blueworx_Clubhouse_Setup_Storage` (`grep -n "public function" includes/admin/class-setup-storage.php`) and use those.

- [ ] **Step 2: Run to see them fail**

Run: `vendor/bin/phpunit tests/php/SetupStorageTest.php` — Expected: FAIL, keys missing.

- [ ] **Step 3: Read and write the switches in Setup_Storage**

In the read array (next to `'profile_fields' =>`), add:

```php
			'ask_phone'         => ! in_array( 'phone', $hidden, true ),
			'ask_gender'        => ! in_array( 'gender', $hidden, true ),
			'ask_birthdate'     => ! in_array( 'birthdate', $hidden, true ),
```

with, just before the array is built: `$hidden = ( new Blueworx_Clubhouse_Profile_Store( $this->storage ) )->hidden_standard();`

In the write method, after the `profile_fields` block:

```php
		$switches = array( 'ask_phone' => 'phone', 'ask_gender' => 'gender', 'ask_birthdate' => 'birthdate' );
		if ( array() !== array_intersect_key( $values, $switches ) ) {
			$profile = new Blueworx_Clubhouse_Profile_Store( $this->storage );
			$hidden  = $profile->hidden_standard();
			foreach ( $switches as $id => $key ) {
				if ( ! array_key_exists( $id, $values ) ) {
					continue;
				}
				$hidden = array_values( array_diff( $hidden, array( $key ) ) );
				if ( ! (bool) $values[ $id ] ) {
					$hidden[] = $key;
				}
			}
			$profile->set_hidden_standard( $hidden );
		}
```

- [ ] **Step 4: Add the panel to the Members tab**

In `members_tab()` in `includes/admin/class-setup-fields.php`, add a panel **before** the `profile_fields` panel:

```php
				array(
					'id'     => 'standard_fields',
					'title'  => 'Standard details',
					'note'   => 'Every club asks for these. Switch one off if you would rather not.',
					'fields' => array(
						array( 'id' => 'ask_phone', 'kind' => 'toggle', 'label' => 'Ask for a phone number', 'capability' => self::cap() ),
						array( 'id' => 'ask_gender', 'kind' => 'toggle', 'label' => 'Ask for gender', 'capability' => self::cap() ),
						array( 'id' => 'ask_birthdate', 'kind' => 'toggle', 'label' => 'Ask for date of birth', 'capability' => self::cap() ),
					),
				),
```

- [ ] **Step 5: Run the tests**

Run: `vendor/bin/phpunit tests/php/SetupStorageTest.php tests/php/SetupFieldsTest.php` (if a `SetupFieldsTest` or `PageFieldsTest` counts fields or panels, update the count). Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-setup-fields.php includes/admin/class-setup-storage.php tests/php/SetupStorageTest.php
git commit -m "Members tab can switch the standard details off

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4: Settings and the encrypted secret

**Files:**
- Create: `includes/playtomic/class-playtomic-secret.php`
- Create: `includes/playtomic/class-playtomic-settings.php`
- Modify: `includes/bootstrap.php` (add requires after the profile block)
- Test: `tests/php/PlaytomicSecretTest.php`, `tests/php/PlaytomicSettingsTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Playtomic_Secret::__construct( string $key_material )`, `encrypt( string $plain ): string`, `decrypt( string $stored ): string` (returns `''` if it cannot).
- Produces: `Blueworx_Clubhouse_Playtomic_Settings::__construct( Blueworx_Clubhouse_Storage $storage, Blueworx_Clubhouse_Playtomic_Secret $secret )`, `read(): array`, `save( array $raw ): array` (sanitised, stored, returned), `client_id(): string`, `secret(): string` (plain), `has_secret(): bool`, `venues(): array`, `merge_venues( array $ids ): void`, `set_last_test( string $text ): void`, `set_last_error( string $text ): void`, constants `OPTION = 'playtomic'`, `WHO_ALL = 'all'`, `WHO_SUBSCRIPTIONS = 'subscriptions'`, `EXPIRY_DATE = 'date'`, `EXPIRY_MONTHS = 'months'`, `DUE_NONE = 'none'`, `DUE_EXPIRY = 'expiry'`, `DUE_MONTHS = 'months'`.
- Settings shape:

```php
array(
	'client_id'  => '',          // string
	'secret'     => '',          // ciphertext from Playtomic_Secret, '' when unset
	'who'        => 'all',       // 'all' | 'subscriptions'
	'price_ids'  => array(),     // SureCart price ids that qualify
	'venues'     => array(       // in column order
		array(
			'id'            => '', // tenant id
			'name'          => '', // friendly
			'club'          => '', // the [clubname] in CAT_IMP-[clubname]-…
			'category'      => '',
			'expiry_mode'   => 'months',  // 'date' | 'months'
			'expiry_date'   => '',        // YYYY-MM-DD when mode is date
			'expiry_months' => 12,        // int when mode is months
			'price_id'      => '',        // benefit_price_id, optional
			'due_mode'      => 'none',    // 'none' | 'expiry' | 'months'
			'due_months'    => 1,
		),
	),
	'last_test'  => '',          // plain sentence shown beside Test connection
	'last_error' => '',          // plain sentence, no secrets
)
```

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/php/PlaytomicSecretTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicSecretTest extends TestCase {

	public function test_round_trip(): void {
		$s = new Blueworx_Clubhouse_Playtomic_Secret( 'unit-test-key-material' );
		$stored = $s->encrypt( 'hunter2' );
		$this->assertNotSame( 'hunter2', $stored );
		$this->assertStringNotContainsString( 'hunter2', $stored );
		$this->assertSame( 'hunter2', $s->decrypt( $stored ) );
	}

	public function test_a_different_key_cannot_read_it(): void {
		$stored = ( new Blueworx_Clubhouse_Playtomic_Secret( 'one' ) )->encrypt( 'hunter2' );
		$this->assertSame( '', ( new Blueworx_Clubhouse_Playtomic_Secret( 'two' ) )->decrypt( $stored ) );
	}

	public function test_empty_in_empty_out(): void {
		$s = new Blueworx_Clubhouse_Playtomic_Secret( 'k' );
		$this->assertSame( '', $s->encrypt( '' ) );
		$this->assertSame( '', $s->decrypt( '' ) );
		$this->assertSame( '', $s->decrypt( 'not-base64-ciphertext' ) );
	}
}
```

```php
<?php
// tests/php/PlaytomicSettingsTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicSettingsTest extends TestCase {

	private function settings( ?Blueworx_Clubhouse_Fake_Storage $storage = null ): Blueworx_Clubhouse_Playtomic_Settings {
		return new Blueworx_Clubhouse_Playtomic_Settings( $storage ?? new Blueworx_Clubhouse_Fake_Storage(), new Blueworx_Clubhouse_Playtomic_Secret( 'k' ) );
	}

	public function test_defaults_when_nothing_is_stored(): void {
		$s = $this->settings()->read();
		$this->assertSame( '', $s['client_id'] );
		$this->assertSame( 'all', $s['who'] );
		$this->assertSame( array(), $s['venues'] );
	}

	public function test_secret_is_stored_encrypted_and_kept_when_left_blank(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$s       = $this->settings( $storage );
		$s->save( array( 'client_id' => ' abc ', 'secret' => 'hunter2' ) );
		$this->assertStringNotContainsString( 'hunter2', serialize( $storage->get( 'playtomic' ) ) );
		$this->assertSame( 'hunter2', $s->secret() );
		$this->assertTrue( $s->has_secret() );
		$this->assertSame( 'abc', $s->client_id() );

		$s->save( array( 'client_id' => 'abc', 'secret' => '' ) );
		$this->assertSame( 'hunter2', $s->secret(), 'a blank secret field means keep the one we have' );
	}

	public function test_blank_venue_rows_are_dropped(): void {
		$s = $this->settings();
		$s->save( array( 'venues' => array(
			array( 'id' => '', 'name' => '', 'club' => '' ),
			array( 'id' => '  ten-1  ', 'name' => 'North', 'club' => 'PadlX North', 'category' => 'Founding', 'expiry_mode' => 'date', 'expiry_date' => '2027-01-31', 'expiry_months' => '', 'due_mode' => 'expiry' ),
		) ) );
		$venues = $s->venues();
		$this->assertCount( 1, $venues );
		$this->assertSame( 'ten-1', $venues[0]['id'] );
		$this->assertSame( 'date', $venues[0]['expiry_mode'] );
		$this->assertSame( '2027-01-31', $venues[0]['expiry_date'] );
		$this->assertSame( 'expiry', $venues[0]['due_mode'] );
	}

	public function test_bad_modes_and_dates_fall_back(): void {
		$s = $this->settings();
		$s->save( array( 'who' => 'nonsense', 'venues' => array( array( 'id' => 'v', 'expiry_mode' => 'weird', 'expiry_date' => '31/01/2027', 'expiry_months' => '-4', 'due_mode' => 'weird', 'due_months' => '0' ) ) ) );
		$v = $s->venues()[0];
		$this->assertSame( 'all', $s->read()['who'] );
		$this->assertSame( 'months', $v['expiry_mode'] );
		$this->assertSame( '', $v['expiry_date'] );
		$this->assertSame( 12, $v['expiry_months'] );
		$this->assertSame( 'none', $v['due_mode'] );
		$this->assertSame( 1, $v['due_months'] );
	}

	public function test_merge_venues_adds_only_new_ids(): void {
		$s = $this->settings();
		$s->save( array( 'venues' => array( array( 'id' => 'a', 'name' => 'A' ) ) ) );
		$s->merge_venues( array( 'a', 'b' ) );
		$this->assertSame( array( 'a', 'b' ), array_column( $s->venues(), 'id' ) );
		$this->assertSame( 'A', $s->venues()[0]['name'] );
		$this->assertSame( '', $s->venues()[1]['name'] );
	}

	public function test_price_ids_are_a_clean_list(): void {
		$s = $this->settings();
		$s->save( array( 'who' => 'subscriptions', 'price_ids' => array( ' p1 ', '', 'p1', 'p2' ) ) );
		$this->assertSame( array( 'p1', 'p2' ), $s->read()['price_ids'] );
	}
}
```

- [ ] **Step 2: Run to see them fail**

Run: `vendor/bin/phpunit tests/php/PlaytomicSecretTest.php tests/php/PlaytomicSettingsTest.php` — Expected: FAIL, classes not found.

- [ ] **Step 3: Write the secret class**

```php
<?php
// includes/playtomic/class-playtomic-secret.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the Playtomic secret encrypted at rest.
 *
 * WordPress has no secret store, so the key is derived from material the
 * site already keeps private (its salts — see Playtomic_Controller). Sodium
 * when PHP has it, OpenSSL otherwise, and a plain prefix as the last resort
 * so a site with neither still works and the screen can say so.
 *
 * Pure: no WordPress at all.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Secret {

	private const SODIUM  = 's1:';
	private const OPENSSL = 'o1:';
	private const PLAIN   = 'p1:';

	private string $key;

	public function __construct( string $key_material ) {
		$this->key = hash( 'sha256', 'clubhouse-playtomic|' . $key_material, true );
	}

	/** True when the secret will actually be encrypted on this server. */
	public static function can_encrypt(): bool {
		return function_exists( 'sodium_crypto_secretbox' ) || function_exists( 'openssl_encrypt' );
	}

	public function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return self::SODIUM . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $this->key ) );
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv   = random_bytes( 16 );
			$body = openssl_encrypt( $plain, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv );
			return false === $body ? '' : self::OPENSSL . base64_encode( $iv . $body );
		}
		return self::PLAIN . base64_encode( $plain );
	}

	public function decrypt( string $stored ): string {
		if ( strlen( $stored ) < 4 ) {
			return '';
		}
		$prefix = substr( $stored, 0, 3 );
		$raw    = base64_decode( substr( $stored, 3 ), true );
		if ( false === $raw ) {
			return '';
		}
		try {
			if ( self::SODIUM === $prefix && function_exists( 'sodium_crypto_secretbox_open' ) ) {
				$n   = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
				$out = strlen( $raw ) > $n ? sodium_crypto_secretbox_open( substr( $raw, $n ), substr( $raw, 0, $n ), $this->key ) : false;
				return false === $out ? '' : $out;
			}
			if ( self::OPENSSL === $prefix && function_exists( 'openssl_decrypt' ) ) {
				$out = strlen( $raw ) > 16 ? openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) ) : false;
				return false === $out ? '' : $out;
			}
			if ( self::PLAIN === $prefix ) {
				return $raw;
			}
		} catch ( \Throwable $e ) {
			return '';
		}
		return '';
	}
}
```

- [ ] **Step 4: Write the settings class**

```php
<?php
// includes/playtomic/class-playtomic-settings.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything the Playtomic screen saves, in one option, sanitised on the way
 * in and out. The secret is never returned by read(); ask secret() for it.
 *
 * Pure beyond Storage and the WordPress sanitisers.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Settings {

	public const OPTION = 'playtomic';

	public const WHO_ALL           = 'all';
	public const WHO_SUBSCRIPTIONS = 'subscriptions';
	public const EXPIRY_DATE       = 'date';
	public const EXPIRY_MONTHS     = 'months';
	public const DUE_NONE          = 'none';
	public const DUE_EXPIRY        = 'expiry';
	public const DUE_MONTHS        = 'months';

	public function __construct( private Blueworx_Clubhouse_Storage $storage, private Blueworx_Clubhouse_Playtomic_Secret $secret ) {}

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'client_id'  => '',
			'secret'     => '',
			'who'        => self::WHO_ALL,
			'price_ids'  => array(),
			'venues'     => array(),
			'last_test'  => '',
			'last_error' => '',
		);
	}

	/** @return array<string,mixed> */
	public static function venue_defaults(): array {
		return array(
			'id'            => '',
			'name'          => '',
			'club'          => '',
			'category'      => '',
			'expiry_mode'   => self::EXPIRY_MONTHS,
			'expiry_date'   => '',
			'expiry_months' => 12,
			'price_id'      => '',
			'due_mode'      => self::DUE_NONE,
			'due_months'    => 1,
		);
	}

	/** The stored settings, sanitised. The secret comes back as ciphertext. @return array<string,mixed> */
	public function read(): array {
		$raw = $this->storage->get( self::OPTION, array() );
		return self::sanitise( is_array( $raw ) ? $raw : array(), '' );
	}

	/**
	 * Sanitise a submission and store it. A blank secret keeps the one stored;
	 * a new one replaces it.
	 *
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public function save( array $raw ): array {
		$current = $this->read();
		$plain   = isset( $raw['secret'] ) && is_scalar( $raw['secret'] ) ? trim( (string) $raw['secret'] ) : '';
		$raw['secret'] = '' === $plain ? $current['secret'] : $this->secret->encrypt( $plain );
		// Only the form's own keys change; the last test and error are kept.
		$raw['last_test']  = $current['last_test'];
		$raw['last_error'] = $current['last_error'];
		$clean = self::sanitise( $raw, (string) $current['secret'] );
		$this->storage->set( self::OPTION, $clean );
		return $clean;
	}

	public function client_id(): string {
		return (string) $this->read()['client_id'];
	}

	public function secret(): string {
		return $this->secret->decrypt( (string) $this->read()['secret'] );
	}

	public function has_secret(): bool {
		return '' !== $this->secret();
	}

	/** @return array<int,array<string,mixed>> */
	public function venues(): array {
		return $this->read()['venues'];
	}

	/** Add any venue id the token named that the club has not listed yet. @param array<int,string> $ids */
	public function merge_venues( array $ids ): void {
		$all   = $this->read();
		$known = array_column( $all['venues'], 'id' );
		foreach ( $ids as $id ) {
			$id = trim( (string) $id );
			if ( '' !== $id && ! in_array( $id, $known, true ) ) {
				$all['venues'][] = array_merge( self::venue_defaults(), array( 'id' => $id ) );
				$known[]         = $id;
			}
		}
		$this->storage->set( self::OPTION, $all );
	}

	public function set_last_test( string $text ): void {
		$all              = $this->read();
		$all['last_test'] = sanitize_text_field( $text );
		$this->storage->set( self::OPTION, $all );
	}

	public function set_last_error( string $text ): void {
		$all               = $this->read();
		$all['last_error'] = sanitize_text_field( $text );
		$this->storage->set( self::OPTION, $all );
	}

	/**
	 * @param array<string,mixed> $raw
	 * @param string              $fallback_secret Ciphertext to keep if the raw secret is not a string.
	 * @return array<string,mixed>
	 */
	public static function sanitise( array $raw, string $fallback_secret ): array {
		$out = self::defaults();

		$out['client_id'] = isset( $raw['client_id'] ) && is_scalar( $raw['client_id'] ) ? sanitize_text_field( (string) $raw['client_id'] ) : '';
		$out['secret']    = isset( $raw['secret'] ) && is_string( $raw['secret'] ) ? $raw['secret'] : $fallback_secret;

		$who        = (string) ( $raw['who'] ?? '' );
		$out['who'] = in_array( $who, array( self::WHO_ALL, self::WHO_SUBSCRIPTIONS ), true ) ? $who : self::WHO_ALL;

		$ids = array();
		foreach ( (array) ( $raw['price_ids'] ?? array() ) as $id ) {
			if ( is_scalar( $id ) ) {
				$id = sanitize_text_field( (string) $id );
				if ( '' !== $id && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
		}
		$out['price_ids'] = $ids;

		$venues = array();
		$seen   = array();
		foreach ( (array) ( $raw['venues'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$venue = self::sanitise_venue( $row );
			if ( '' === $venue['id'] || in_array( $venue['id'], $seen, true ) ) {
				continue;
			}
			$seen[]   = $venue['id'];
			$venues[] = $venue;
		}
		$out['venues'] = $venues;

		$out['last_test']  = isset( $raw['last_test'] ) && is_scalar( $raw['last_test'] ) ? sanitize_text_field( (string) $raw['last_test'] ) : '';
		$out['last_error'] = isset( $raw['last_error'] ) && is_scalar( $raw['last_error'] ) ? sanitize_text_field( (string) $raw['last_error'] ) : '';
		return $out;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private static function sanitise_venue( array $row ): array {
		$v = self::venue_defaults();
		foreach ( array( 'id', 'name', 'club', 'category', 'price_id', 'expiry_date' ) as $key ) {
			$v[ $key ] = isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? sanitize_text_field( (string) $row[ $key ] ) : '';
		}
		if ( ! self::is_date( $v['expiry_date'] ) ) {
			$v['expiry_date'] = '';
		}
		$mode             = (string) ( $row['expiry_mode'] ?? '' );
		$v['expiry_mode'] = in_array( $mode, array( self::EXPIRY_DATE, self::EXPIRY_MONTHS ), true ) ? $mode : self::EXPIRY_MONTHS;
		$due              = (string) ( $row['due_mode'] ?? '' );
		$v['due_mode']    = in_array( $due, array( self::DUE_NONE, self::DUE_EXPIRY, self::DUE_MONTHS ), true ) ? $due : self::DUE_NONE;
		$v['expiry_months'] = self::months( $row['expiry_months'] ?? null, 12 );
		$v['due_months']    = self::months( $row['due_months'] ?? null, 1 );
		return $v;
	}

	private static function months( mixed $raw, int $default ): int {
		if ( ! is_scalar( $raw ) || ! is_numeric( (string) $raw ) ) {
			return $default;
		}
		$n = (int) $raw;
		return ( $n >= 1 && $n <= 120 ) ? $n : $default;
	}

	public static function is_date( string $value ): bool {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}
}
```

- [ ] **Step 5: Load the module**

In `includes/bootstrap.php`, after the Profile block:

```php
// Playtomic. Pure classes only; the controller, transport and SureCart
// adapter are loaded by the plugin file, like every other WP-facing class.
require_once __DIR__ . '/playtomic/class-playtomic-secret.php';
require_once __DIR__ . '/playtomic/class-playtomic-settings.php';
```

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit tests/php/PlaytomicSecretTest.php tests/php/PlaytomicSettingsTest.php` — Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/playtomic includes/bootstrap.php tests/php/PlaytomicSecretTest.php tests/php/PlaytomicSettingsTest.php
git commit -m "Playtomic settings, with the secret encrypted at rest

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 5: The CSV builder

**Files:**
- Create: `includes/playtomic/class-playtomic-csv.php`
- Modify: `includes/bootstrap.php` (add require)
- Test: `tests/php/PlaytomicCsvTest.php`

**Interfaces:**
- Consumes: venue rows from `Playtomic_Settings::venues()` (Task 4).
- Produces: `Blueworx_Clubhouse_Playtomic_Csv::COUNTRY_CODE = '61'`, `FILENAME_PREFIX = 'playtomic-members-'`, `headers( array $venues ): array<int,string>`, `row( array $member, array $venues, string $today ): array<int,string>`, `build( array $members, array $venues, string $today ): string`, `phone( string $raw ): string`, `gender( string $stored ): string`, `expiry( array $venue, string $today ): string`, `due( array $venue, string $today, string $expiry ): string`, `add_months( string $date, int $months ): string`.
- A member row (from Task 6) is `array{id:int,name:string,email:string,phone:string,gender:string,birthdate:string}`.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/php/PlaytomicCsvTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicCsvTest extends TestCase {

	/** @return array<string,mixed> */
	private function venue( array $over = array() ): array {
		return array_merge( Blueworx_Clubhouse_Playtomic_Settings::venue_defaults(), array( 'id' => 't1', 'club' => 'PadlX North', 'category' => 'Founding Member' ), $over );
	}

	public function test_headers_follow_the_brief_in_order(): void {
		$h = Blueworx_Clubhouse_Playtomic_Csv::headers( array( $this->venue(), $this->venue( array( 'id' => 't2', 'club' => 'PadlX South' ) ) ) );
		$this->assertSame(
			array(
				'name', 'email', 'phone_number', 'gender', 'birthdate',
				'CAT_IMP-PadlX North-cat_name', 'CAT_IMP-PadlX North-exp_date', 'CAT_IMP-PadlX North-benefit_price_id', 'CAT_IMP-PadlX North-next_due_date',
				'CAT_IMP-PadlX South-cat_name', 'CAT_IMP-PadlX South-exp_date', 'CAT_IMP-PadlX South-benefit_price_id', 'CAT_IMP-PadlX South-next_due_date',
			),
			$h
		);
	}

	/** @dataProvider phones */
	public function test_phone_variants( string $in, string $out ): void {
		$this->assertSame( $out, Blueworx_Clubhouse_Playtomic_Csv::phone( $in ) );
	}

	/** @return array<int,array{string,string}> */
	public static function phones(): array {
		return array(
			array( '0412 345 678', '+61412345678' ),
			array( '+61 412 345 678', '+61412345678' ),
			array( '(04) 1234-5678', '+61412345678' ),
			array( '61412345678', '+61412345678' ),
			array( '0061412345678', '+61412345678' ),
			array( '02 6123 4567', '+61261234567' ),
			array( '', '' ),
			array( 'n/a', '' ),
		);
	}

	public function test_gender_maps_to_playtomic_words(): void {
		$this->assertSame( 'MALE', Blueworx_Clubhouse_Playtomic_Csv::gender( 'Male' ) );
		$this->assertSame( 'FEMALE', Blueworx_Clubhouse_Playtomic_Csv::gender( 'Female' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Playtomic_Csv::gender( 'Other' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Playtomic_Csv::gender( 'Prefer not to say' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Playtomic_Csv::gender( '' ) );
	}

	public function test_add_months_handles_month_ends(): void {
		$this->assertSame( '2027-10-15', Blueworx_Clubhouse_Playtomic_Csv::add_months( '2026-10-15', 12 ) );
		$this->assertSame( '2027-02-28', Blueworx_Clubhouse_Playtomic_Csv::add_months( '2027-01-31', 1 ) );
	}

	public function test_expiry_and_due_rules(): void {
		$today = '2026-10-15';
		$this->assertSame( '2027-10-15', Blueworx_Clubhouse_Playtomic_Csv::expiry( $this->venue(), $today ) );
		$fixed = $this->venue( array( 'expiry_mode' => 'date', 'expiry_date' => '2027-01-31' ) );
		$this->assertSame( '2027-01-31', Blueworx_Clubhouse_Playtomic_Csv::expiry( $fixed, $today ) );
		$this->assertSame( '', Blueworx_Clubhouse_Playtomic_Csv::due( $this->venue(), $today, '2027-10-15' ) );
		$this->assertSame( '2027-10-15', Blueworx_Clubhouse_Playtomic_Csv::due( $this->venue( array( 'due_mode' => 'expiry' ) ), $today, '2027-10-15' ) );
		$this->assertSame( '2026-11-15', Blueworx_Clubhouse_Playtomic_Csv::due( $this->venue( array( 'due_mode' => 'months', 'due_months' => 1 ) ), $today, '2027-10-15' ) );
	}

	public function test_a_row_fills_every_column(): void {
		$member = array( 'id' => 3, 'name' => 'Ana Reyes', 'email' => 'ana@example.com', 'phone' => '0412 345 678', 'gender' => 'Female', 'birthdate' => '1990-05-04' );
		$row    = Blueworx_Clubhouse_Playtomic_Csv::row( $member, array( $this->venue( array( 'price_id' => 'price-9', 'due_mode' => 'expiry' ) ) ), '2026-10-15' );
		$this->assertSame( array( 'Ana Reyes', 'ana@example.com', '+61412345678', 'FEMALE', '1990-05-04', 'Founding Member', '2027-10-15', 'price-9', '2027-10-15' ), $row );
	}

	public function test_build_quotes_and_uses_crlf_without_bom(): void {
		$members = array( array( 'id' => 1, 'name' => 'O\'Brien, Pat', 'email' => 'pat@example.com', 'phone' => '', 'gender' => '', 'birthdate' => '' ) );
		$csv     = Blueworx_Clubhouse_Playtomic_Csv::build( $members, array( $this->venue() ), '2026-10-15' );
		$lines   = explode( "\r\n", $csv );
		$this->assertStringStartsWith( 'name,email', $csv );
		$this->assertSame( '"O\'Brien, Pat",pat@example.com,,,,Founding Member,2027-10-15,,', $lines[1] );
		$this->assertSame( '', $lines[2], 'ends with one CRLF' );
	}
}
```

- [ ] **Step 2: Run to see them fail**

Run: `vendor/bin/phpunit tests/php/PlaytomicCsvTest.php` — Expected: FAIL, class not found.

- [ ] **Step 3: Write the class**

```php
<?php
// includes/playtomic/class-playtomic-csv.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The file Playtomic Manager's chain import takes, built from member rows and
 * the club's venue settings. Headers live here and nowhere else: when the
 * official sample differs, this is the one file to change.
 *
 * Pure. No WordPress.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Csv {

	/** The club's country, not the player's — Playtomic's rule. Australia for now. */
	public const COUNTRY_CODE = '61';

	public const FILENAME_PREFIX = 'playtomic-members-';

	private const STANDARD = array( 'name', 'email', 'phone_number', 'gender', 'birthdate' );
	private const PER_CLUB = array( 'cat_name', 'exp_date', 'benefit_price_id', 'next_due_date' );

	/** @param array<int,array<string,mixed>> $venues @return array<int,string> */
	public static function headers( array $venues ): array {
		$out = self::STANDARD;
		foreach ( $venues as $venue ) {
			foreach ( self::PER_CLUB as $column ) {
				$out[] = 'CAT_IMP-' . (string) $venue['club'] . '-' . $column;
			}
		}
		return $out;
	}

	/**
	 * @param array{id:int,name:string,email:string,phone:string,gender:string,birthdate:string} $member
	 * @param array<int,array<string,mixed>> $venues
	 * @return array<int,string>
	 */
	public static function row( array $member, array $venues, string $today ): array {
		$out = array(
			(string) $member['name'],
			(string) $member['email'],
			self::phone( (string) $member['phone'] ),
			self::gender( (string) $member['gender'] ),
			(string) $member['birthdate'],
		);
		foreach ( $venues as $venue ) {
			$expiry = self::expiry( $venue, $today );
			$out[]  = (string) $venue['category'];
			$out[]  = $expiry;
			$out[]  = (string) $venue['price_id'];
			$out[]  = self::due( $venue, $today, $expiry );
		}
		return $out;
	}

	/** @param array<int,array<string,mixed>> $members @param array<int,array<string,mixed>> $venues */
	public static function build( array $members, array $venues, string $today ): string {
		$lines = array( self::line( self::headers( $venues ) ) );
		foreach ( $members as $member ) {
			$lines[] = self::line( self::row( $member, $venues, $today ) );
		}
		return implode( "\r\n", $lines ) . "\r\n";
	}

	/** Digits only, the country code once, a plus sign in front. Empty stays empty. */
	public static function phone( string $raw ): string {
		$digits = (string) preg_replace( '/\D+/', '', $raw );
		$digits = ltrim( $digits, '0' );
		if ( '' === $digits ) {
			return '';
		}
		if ( str_starts_with( $digits, self::COUNTRY_CODE ) && strlen( $digits ) > 9 ) {
			$digits = substr( $digits, strlen( self::COUNTRY_CODE ) );
		}
		return '+' . self::COUNTRY_CODE . $digits;
	}

	/** The two words Playtomic's own player records use; anything else is left blank. */
	public static function gender( string $stored ): string {
		return match ( $stored ) {
			'Male'   => 'MALE',
			'Female' => 'FEMALE',
			default  => '',
		};
	}

	/** @param array<string,mixed> $venue */
	public static function expiry( array $venue, string $today ): string {
		if ( Blueworx_Clubhouse_Playtomic_Settings::EXPIRY_DATE === (string) $venue['expiry_mode'] ) {
			return (string) $venue['expiry_date'];
		}
		return self::add_months( $today, (int) $venue['expiry_months'] );
	}

	/** @param array<string,mixed> $venue */
	public static function due( array $venue, string $today, string $expiry ): string {
		return match ( (string) $venue['due_mode'] ) {
			Blueworx_Clubhouse_Playtomic_Settings::DUE_EXPIRY => $expiry,
			Blueworx_Clubhouse_Playtomic_Settings::DUE_MONTHS => self::add_months( $today, (int) $venue['due_months'] ),
			default => '',
		};
	}

	/** Month arithmetic that stops at the month's end rather than rolling over. */
	public static function add_months( string $date, int $months ): string {
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		if ( false === $start ) {
			return '';
		}
		$first = $start->modify( 'first day of this month' )->modify( '+' . $months . ' months' );
		$day   = min( (int) $start->format( 'j' ), (int) $first->format( 't' ) );
		return $first->setDate( (int) $first->format( 'Y' ), (int) $first->format( 'n' ), $day )->format( 'Y-m-d' );
	}

	/** @param array<int,string> $cells */
	private static function line( array $cells ): string {
		return implode( ',', array_map( array( self::class, 'cell' ), $cells ) );
	}

	private static function cell( string $value ): string {
		if ( 1 === preg_match( '/[",\r\n]/', $value ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}
}
```

Add to `includes/bootstrap.php` after the settings require: `require_once __DIR__ . '/playtomic/class-playtomic-csv.php';`

- [ ] **Step 4: Run the tests** — `vendor/bin/phpunit tests/php/PlaytomicCsvTest.php` — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/playtomic/class-playtomic-csv.php includes/bootstrap.php tests/php/PlaytomicCsvTest.php
git commit -m "Build the Playtomic chain-import CSV

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 6: Members: who is listed, who qualifies, who has gone

**Files:**
- Create: `includes/playtomic/class-playtomic-members.php`
- Modify: `includes/bootstrap.php` (add require)
- Test: `tests/php/PlaytomicMembersTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Playtomic_Members::META_KEY = 'clubhouse_playtomic_exported'`, `FILTER_NEW = 'new'`, `FILTER_ALL = 'all'`; `__construct( callable $source, ?callable $qualifies = null )`; `rows( string $filter, string $from, string $to ): array`; `exportable( array $ids, string $from = '', string $to = '' ): array` (rows for those ids that are complete and qualify); static `mark( array $ids, string $today ): void`; static `clear( int $id ): void`; static `from_users( array $users ): array` (WP_User-like objects → source rows, used by the controller).
- `$source(): array<int, array{id:int,first:string,last:string,display:string,email:string,registered:string,phone:string,gender:string,birthdate:string,exported:string}>`.
- `$qualifies( int $user_id ): bool`.
- A returned row adds `name`, `complete` (bool) and `reason` (`''` or a plain sentence) and keeps `exported` (`''` or `YYYY-MM-DD`).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/php/PlaytomicMembersTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicMembersTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	/** @return array<int,array<string,mixed>> */
	private function people(): array {
		$base = array( 'first' => '', 'last' => '', 'display' => '', 'email' => '', 'registered' => '2026-09-01', 'phone' => '', 'gender' => '', 'birthdate' => '', 'exported' => '' );
		return array(
			array_merge( $base, array( 'id' => 1, 'first' => 'Ana', 'last' => 'Reyes', 'email' => 'ana@example.com' ) ),
			array_merge( $base, array( 'id' => 2, 'display' => 'pat', 'email' => 'pat@example.com', 'registered' => '2026-10-10', 'exported' => '2026-10-12' ) ),
			array_merge( $base, array( 'id' => 3, 'email' => 'noname@example.com' ) ),
			array_merge( $base, array( 'id' => 4, 'first' => 'No', 'last' => 'Email' ) ),
		);
	}

	private function members( ?callable $qualifies = null ): Blueworx_Clubhouse_Playtomic_Members {
		return new Blueworx_Clubhouse_Playtomic_Members( fn(): array => $this->people(), $qualifies );
	}

	public function test_new_filter_hides_the_exported(): void {
		$ids = array_column( $this->members()->rows( 'new', '', '' ), 'id' );
		$this->assertSame( array( 1, 3, 4 ), $ids );
	}

	public function test_all_filter_shows_everyone_with_their_date(): void {
		$rows = $this->members()->rows( 'all', '', '' );
		$this->assertSame( array( 1, 2, 3, 4 ), array_column( $rows, 'id' ) );
		$this->assertSame( '2026-10-12', $rows[1]['exported'] );
	}

	public function test_date_range_is_on_joined(): void {
		$this->assertSame( array( 2 ), array_column( $this->members()->rows( 'all', '2026-10-01', '2026-10-31' ), 'id' ) );
		$this->assertSame( array( 1, 3, 4 ), array_column( $this->members()->rows( 'all', '', '2026-09-30' ), 'id' ) );
	}

	public function test_name_falls_back_to_display_name(): void {
		$rows = $this->members()->rows( 'all', '', '' );
		$this->assertSame( 'Ana Reyes', $rows[0]['name'] );
		$this->assertSame( 'pat', $rows[1]['name'] );
	}

	public function test_incomplete_member_is_never_exportable(): void {
		$rows = $this->members()->rows( 'all', '', '' );
		$this->assertFalse( $rows[2]['complete'] );
		$this->assertSame( 'No name on their account.', $rows[2]['reason'] );
		$this->assertFalse( $rows[3]['complete'] );
		$this->assertSame( 'No email address on their account.', $rows[3]['reason'] );
		$this->assertSame( array( 1 ), array_column( $this->members()->exportable( array( 1, 3, 4, 99 ) ), 'id' ) );
	}

	public function test_qualifies_rule_is_applied(): void {
		$m = $this->members( fn( int $id ): bool => 2 === $id );
		$this->assertSame( array( 2 ), array_column( $m->rows( 'all', '', '' ), 'id' ) );
		$this->assertSame( array(), $m->exportable( array( 1 ) ) );
	}

	public function test_mark_and_clear_write_user_meta(): void {
		Blueworx_Clubhouse_Playtomic_Members::mark( array( 1, 3 ), '2026-10-15' );
		$this->assertSame( '2026-10-15', get_user_meta( 1, 'clubhouse_playtomic_exported', true ) );
		$this->assertSame( '2026-10-15', get_user_meta( 3, 'clubhouse_playtomic_exported', true ) );
		Blueworx_Clubhouse_Playtomic_Members::clear( 1 );
		$this->assertSame( '', get_user_meta( 1, 'clubhouse_playtomic_exported', true ) );
	}

	public function test_from_users_reads_meta_and_dates(): void {
		update_user_meta( 5, 'clubhouse_profile_phone', '0412' );
		update_user_meta( 5, 'clubhouse_playtomic_exported', '2026-01-02' );
		$user = (object) array( 'ID' => 5, 'first_name' => 'A', 'last_name' => 'B', 'display_name' => 'AB', 'user_email' => 'a@b.c', 'user_registered' => '2026-03-04 10:11:12' );
		$rows = Blueworx_Clubhouse_Playtomic_Members::from_users( array( $user ) );
		$this->assertSame( array( 'id' => 5, 'first' => 'A', 'last' => 'B', 'display' => 'AB', 'email' => 'a@b.c', 'registered' => '2026-03-04', 'phone' => '0412', 'gender' => '', 'birthdate' => '', 'exported' => '2026-01-02' ), $rows[0] );
	}
}
```

- [ ] **Step 2: Run to see them fail** — `vendor/bin/phpunit tests/php/PlaytomicMembersTest.php` — Expected: FAIL.

- [ ] **Step 3: Write the class**

```php
<?php
// includes/playtomic/class-playtomic-members.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The members the Playtomic screen lists, and the mark that says one has
 * already gone across.
 *
 * Pure apart from user meta (mark, clear, from_users), which the stubs cover.
 * Who the members ARE comes in through a callable so the rules can be tested
 * without a users table; the controller passes get_users().
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Members {

	public const META_KEY   = 'clubhouse_playtomic_exported';
	public const FILTER_NEW = 'new';
	public const FILTER_ALL = 'all';

	/** @var callable():array<int,array<string,mixed>> */
	private $source;
	/** @var (callable(int):bool)|null */
	private $qualifies;

	public function __construct( callable $source, ?callable $qualifies = null ) {
		$this->source    = $source;
		$this->qualifies = $qualifies;
	}

	/**
	 * The rows to show: those who qualify, joined within the range, and — for
	 * the 'new' filter — not yet exported.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function rows( string $filter, string $from, string $to ): array {
		$out = array();
		foreach ( ( $this->source )() as $person ) {
			$row = self::shape( $person );
			if ( null !== $this->qualifies && ! ( $this->qualifies )( $row['id'] ) ) {
				continue;
			}
			if ( '' !== $from && $row['registered'] < $from ) {
				continue;
			}
			if ( '' !== $to && $row['registered'] > $to ) {
				continue;
			}
			if ( self::FILTER_ALL !== $filter && '' !== $row['exported'] ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Of the ticked ids, the rows that may actually be written: complete, and
	 * qualifying. Unknown ids are ignored rather than refused.
	 *
	 * @param array<int,int> $ids
	 * @return array<int,array<string,mixed>>
	 */
	public function exportable( array $ids, string $from = '', string $to = '' ): array {
		$wanted = array_map( 'intval', $ids );
		return array_values(
			array_filter(
				$this->rows( self::FILTER_ALL, $from, $to ),
				static fn( array $row ): bool => $row['complete'] && in_array( $row['id'], $wanted, true )
			)
		);
	}

	/** @param array<int,int> $ids */
	public static function mark( array $ids, string $today ): void {
		foreach ( $ids as $id ) {
			update_user_meta( (int) $id, self::META_KEY, $today );
		}
	}

	public static function clear( int $id ): void {
		delete_user_meta( $id, self::META_KEY );
	}

	/**
	 * WordPress users into source rows. Objects, not WP_User, so a test can
	 * hand in stdClass.
	 *
	 * @param array<int,object> $users
	 * @return array<int,array<string,mixed>>
	 */
	public static function from_users( array $users ): array {
		$prefix = Blueworx_Clubhouse_Profile_Store::META_PREFIX;
		$out    = array();
		foreach ( $users as $user ) {
			$id    = (int) ( $user->ID ?? 0 );
			$out[] = array(
				'id'         => $id,
				'first'      => (string) ( $user->first_name ?? '' ),
				'last'       => (string) ( $user->last_name ?? '' ),
				'display'    => (string) ( $user->display_name ?? '' ),
				'email'      => (string) ( $user->user_email ?? '' ),
				'registered' => substr( (string) ( $user->user_registered ?? '' ), 0, 10 ),
				'phone'      => (string) get_user_meta( $id, $prefix . 'phone', true ),
				'gender'     => (string) get_user_meta( $id, $prefix . 'gender', true ),
				'birthdate'  => (string) get_user_meta( $id, $prefix . 'birthdate', true ),
				'exported'   => (string) get_user_meta( $id, self::META_KEY, true ),
			);
		}
		return $out;
	}

	/** @param array<string,mixed> $p @return array<string,mixed> */
	private static function shape( array $p ): array {
		$name = trim( trim( (string) ( $p['first'] ?? '' ) ) . ' ' . trim( (string) ( $p['last'] ?? '' ) ) );
		if ( '' === $name ) {
			$name = trim( (string) ( $p['display'] ?? '' ) );
		}
		$email  = trim( (string) ( $p['email'] ?? '' ) );
		$reason = '';
		if ( '' === $name ) {
			$reason = 'No name on their account.';
		} elseif ( '' === $email ) {
			$reason = 'No email address on their account.';
		}
		return array(
			'id'         => (int) ( $p['id'] ?? 0 ),
			'name'       => $name,
			'email'      => $email,
			'phone'      => (string) ( $p['phone'] ?? '' ),
			'gender'     => (string) ( $p['gender'] ?? '' ),
			'birthdate'  => (string) ( $p['birthdate'] ?? '' ),
			'registered' => (string) ( $p['registered'] ?? '' ),
			'exported'   => (string) ( $p['exported'] ?? '' ),
			'complete'   => '' === $reason,
			'reason'     => $reason,
		);
	}
}
```

Add to `includes/bootstrap.php`: `require_once __DIR__ . '/playtomic/class-playtomic-members.php';`

- [ ] **Step 4: Run the tests** — `vendor/bin/phpunit tests/php/PlaytomicMembersTest.php` — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/playtomic/class-playtomic-members.php includes/bootstrap.php tests/php/PlaytomicMembersTest.php
git commit -m "Playtomic export knows who is listed, who qualifies and who has gone

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 7: The client: exception, transport, token and venues

**Files:**
- Create: `includes/playtomic/class-playtomic-exception.php`
- Create: `includes/playtomic/interface-playtomic-transport.php`
- Create: `includes/playtomic/class-playtomic-client.php`
- Create: `tests/php/fakes/class-fake-playtomic-transport.php`
- Modify: `includes/bootstrap.php` (requires), `tests/php/bootstrap.php` (require the fake, next to the other fakes — check how `class-fake-storage.php` is loaded and copy it)
- Test: `tests/php/PlaytomicClientTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Playtomic_Exception extends RuntimeException` with `__construct( string $message, int $status = 0, string $detail = '' )`, `status(): int`, `detail(): string` (scrubbed body for the log).
- Produces: `interface Blueworx_Clubhouse_Playtomic_Transport { public function request( string $method, string $url, array $headers, ?string $body ): array; }` returning `array{status:int,body:string,headers:array<string,string>}`; throws `Playtomic_Exception` on a network failure.
- Produces: `Blueworx_Clubhouse_Playtomic_Client::__construct( Blueworx_Clubhouse_Playtomic_Transport $transport, string $client_id, string $secret, ?callable $now = null )`, `BASE = 'https://thirdparty.playtomic.io'`, `TOKEN_TRANSIENT = 'clubhouse_playtomic_token'`, `token(): string`, `venues(): array<int,string>`, `forget_token(): void`, `get( string $path, array $query ): array` (decoded JSON; public so later features can reach an endpoint not wrapped yet), static `scrub( string $text, array $secrets ): string`, static `log( string $message ): void`.

- [ ] **Step 1: Write the fake transport**

```php
<?php
// tests/php/fakes/class-fake-playtomic-transport.php
declare(strict_types=1);

/** Scripted responses, in order; records every request it saw. */
final class Blueworx_Clubhouse_Fake_Playtomic_Transport implements Blueworx_Clubhouse_Playtomic_Transport {

	/** @var array<int,array{status:int,body:string,headers?:array<string,string>}|Throwable> */
	public array $queue = array();
	/** @var array<int,array{method:string,url:string,headers:array<string,string>,body:?string}> */
	public array $seen = array();

	public function request( string $method, string $url, array $headers, ?string $body ): array {
		$this->seen[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body );
		$next = array_shift( $this->queue );
		if ( null === $next ) {
			throw new RuntimeException( 'fake transport ran out of responses for ' . $url );
		}
		if ( $next instanceof Throwable ) {
			throw $next;
		}
		return array( 'status' => $next['status'], 'body' => $next['body'], 'headers' => $next['headers'] ?? array() );
	}
}
```

A helper for tests: a token whose payload names two tenants.

```php
function fake_playtomic_jwt( array $tenants ): string {
	$b64 = static fn( string $s ): string => rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
	return $b64( '{"alg":"HS256"}' ) . '.' . $b64( json_encode( array( 'tenant_ids' => $tenants, 'exp' => 9999999999 ) ) ) . '.sig';
}
```

Put the helper at the bottom of the fake transport file, guarded by `if ( ! function_exists( 'fake_playtomic_jwt' ) )`.

- [ ] **Step 2: Write the failing tests**

```php
<?php
// tests/php/PlaytomicClientTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicClientTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Playtomic_Transport $t;
	private int $now = 1_000_000;

	protected function setUp(): void {
		wp_stub_reset();
		$this->t = new Blueworx_Clubhouse_Fake_Playtomic_Transport();
	}

	private function client(): Blueworx_Clubhouse_Playtomic_Client {
		return new Blueworx_Clubhouse_Playtomic_Client( $this->t, 'cid', 'shh', fn(): int => $this->now );
	}

	private function token_ok( array $tenants = array( 'ten-a', 'ten-b' ) ): array {
		return array( 'status' => 200, 'body' => json_encode( array( 'token' => fake_playtomic_jwt( $tenants ), 'token_type' => 'BEARER', 'expires_in' => 3600 ) ) );
	}

	public function test_token_posts_client_id_and_secret_as_json(): void {
		$this->t->queue[] = $this->token_ok();
		$token = $this->client()->token();
		$this->assertStringStartsWith( 'eyJ', $token );
		$req = $this->t->seen[0];
		$this->assertSame( 'POST', $req['method'] );
		$this->assertSame( 'https://thirdparty.playtomic.io/api/v1/oauth/token', $req['url'] );
		$this->assertSame( array( 'client_id' => 'cid', 'secret' => 'shh' ), json_decode( (string) $req['body'], true ) );
		$this->assertSame( 'application/json', $req['headers']['Content-Type'] );
	}

	public function test_token_is_cached_until_five_minutes_before_expiry(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = $this->token_ok( array( 'ten-z' ) );
		$c = $this->client();
		$first = $c->token();
		$this->now += 3600 - 301;
		$this->assertSame( $first, $c->token(), 'still cached' );
		$this->now += 2;
		$this->assertNotSame( $first, $c->token(), 'refreshed early' );
		$this->assertCount( 2, $this->t->seen );
	}

	public function test_venues_come_from_the_token(): void {
		$this->t->queue[] = $this->token_ok( array( 'ten-a', 'ten-b' ) );
		$this->assertSame( array( 'ten-a', 'ten-b' ), $this->client()->venues() );
	}

	public function test_wrong_credentials_are_a_plain_sentence(): void {
		$this->t->queue[] = array( 'status' => 401, 'body' => '{"error":"UNAUTHORIZED"}' );
		$this->expectException( Blueworx_Clubhouse_Playtomic_Exception::class );
		$this->expectExceptionMessage( 'Playtomic did not accept the Client ID and Secret.' );
		$this->client()->token();
	}

	public function test_network_failure_is_a_plain_sentence(): void {
		$this->t->queue[] = new Blueworx_Clubhouse_Playtomic_Exception( 'Could not reach Playtomic: cURL error 28.' );
		$this->expectExceptionMessage( 'Could not reach Playtomic' );
		$this->client()->token();
	}

	public function test_missing_credentials_never_hit_the_network(): void {
		$c = new Blueworx_Clubhouse_Playtomic_Client( $this->t, '', '', fn(): int => $this->now );
		$this->expectExceptionMessage( 'Enter the Client ID and Secret first.' );
		$c->token();
		$this->assertSame( array(), $this->t->seen );
	}

	public function test_scrub_hides_secret_and_token(): void {
		$text = 'sent shh and got eyJabc.def.ghi back';
		$this->assertSame( 'sent [hidden] and got [hidden] back', Blueworx_Clubhouse_Playtomic_Client::scrub( $text, array( 'shh', 'eyJabc.def.ghi' ) ) );
		$this->assertSame( 'nothing', Blueworx_Clubhouse_Playtomic_Client::scrub( 'nothing', array( '' ) ) );
	}

	public function test_get_sends_bearer_and_query(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 200, 'body' => '{"ok":true}' );
		$data = $this->client()->get( '/api/v1/anything', array( 'a' => '1', 'b' => array( 'x', 'y' ) ) );
		$this->assertSame( array( 'ok' => true ), $data );
		$req = $this->t->seen[1];
		$this->assertSame( 'GET', $req['method'] );
		$this->assertSame( 'https://thirdparty.playtomic.io/api/v1/anything?a=1&b=x&b=y', $req['url'] );
		$this->assertStringStartsWith( 'Bearer eyJ', $req['headers']['Authorization'] );
	}
}
```

- [ ] **Step 3: Run to see them fail** — `vendor/bin/phpunit tests/php/PlaytomicClientTest.php` — Expected: FAIL.

- [ ] **Step 4: Write the exception and the transport interface**

```php
<?php
// includes/playtomic/class-playtomic-exception.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One failure talking to Playtomic, said in plain English. The detail is
 * the scrubbed response for the log, never for the screen.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Exception extends RuntimeException {

	public function __construct( string $message, private int $status = 0, private string $detail = '' ) {
		parent::__construct( $message );
	}

	public function status(): int {
		return $this->status;
	}

	public function detail(): string {
		return $this->detail;
	}
}
```

```php
<?php
// includes/playtomic/interface-playtomic-transport.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One HTTP call. Production wraps wp_remote_request; tests script answers.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Playtomic_Transport {

	/**
	 * @param array<string,string> $headers
	 * @return array{status:int,body:string,headers:array<string,string>}
	 * @throws Blueworx_Clubhouse_Playtomic_Exception When no response came back at all.
	 */
	public function request( string $method, string $url, array $headers, ?string $body ): array;
}
```

- [ ] **Step 5: Write the client (token, venues, get, scrub, log)**

```php
<?php
// includes/playtomic/class-playtomic-client.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one class that talks to Playtomic. Auth, the token cache, paging and
 * errors live here, so a feature built later calls bookings() and never
 * thinks about HTTP.
 *
 * Pure apart from transients (the token cache) and error_log.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Client {

	public const BASE            = 'https://thirdparty.playtomic.io';
	public const TOKEN_TRANSIENT = 'clubhouse_playtomic_token';

	/** Refresh this long before Playtomic would reject the token. */
	private const EARLY = 300;

	/** @var callable():int */
	private $now;

	public function __construct(
		private Blueworx_Clubhouse_Playtomic_Transport $transport,
		private string $client_id,
		private string $secret,
		?callable $now = null
	) {
		$this->now = $now ?? static fn(): int => time();
	}

	/** A live bearer token, fetched or from the cache. */
	public function token(): string {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['token'], $cached['expires'] ) && (int) $cached['expires'] - self::EARLY > ( $this->now )() ) {
			return (string) $cached['token'];
		}
		return $this->fetch_token();
	}

	public function forget_token(): void {
		delete_transient( self::TOKEN_TRANSIENT );
	}

	/** The venue ids the credentials cover, read from the token's own payload. @return array<int,string> */
	public function venues(): array {
		$payload = self::jwt_payload( $this->token() );
		$ids     = $payload['tenant_ids'] ?? array();
		return is_array( $ids ) ? array_values( array_map( 'strval', $ids ) ) : array();
	}

	/**
	 * One authorised GET, decoded. Retries once with a fresh token on a 401.
	 *
	 * @param array<string,string|array<int,string>> $query
	 * @return array<mixed>
	 */
	public function get( string $path, array $query = array() ): array {
		$response = $this->send( $path, $query );
		if ( 401 === $response['status'] ) {
			$this->forget_token();
			$response = $this->send( $path, $query );
		}
		return $this->decode( $response, $path );
	}

	/** Replace every secret with [hidden]. @param array<int,string> $secrets */
	public static function scrub( string $text, array $secrets ): string {
		foreach ( $secrets as $secret ) {
			if ( '' !== $secret ) {
				$text = str_replace( $secret, '[hidden]', $text );
			}
		}
		return $text;
	}

	/** One line in the PHP error log, already scrubbed by the caller. */
	public static function log( string $message ): void {
		error_log( '[Clubhouse Playtomic] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the one place API failures are recorded.
	}

	/* ---------------------------------------------------------------- */

	private function fetch_token(): string {
		if ( '' === $this->client_id || '' === $this->secret ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Enter the Client ID and Secret first.' );
		}
		$response = $this->transport->request(
			'POST',
			self::BASE . '/api/v1/oauth/token',
			array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			(string) json_encode( array( 'client_id' => $this->client_id, 'secret' => $this->secret ) )
		);
		if ( 401 === $response['status'] || 403 === $response['status'] ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic did not accept the Client ID and Secret.', $response['status'], $this->safe( $response['body'] ) );
		}
		$data = $this->decode( $response, '/api/v1/oauth/token' );
		$token = (string) ( $data['token'] ?? '' );
		if ( '' === $token ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic answered without a token.', $response['status'], $this->safe( $response['body'] ) );
		}
		$ttl = max( 60, (int) ( $data['expires_in'] ?? 3600 ) );
		set_transient( self::TOKEN_TRANSIENT, array( 'token' => $token, 'expires' => ( $this->now )() + $ttl ), $ttl );
		return $token;
	}

	/** @param array<string,string|array<int,string>> $query @return array{status:int,body:string,headers:array<string,string>} */
	private function send( string $path, array $query ): array {
		$url = self::BASE . $path;
		if ( array() !== $query ) {
			$url .= '?' . self::query( $query );
		}
		return $this->transport->request(
			'GET',
			$url,
			array( 'Authorization' => 'Bearer ' . $this->token(), 'Accept' => 'application/json' ),
			null
		);
	}

	/** Repeated keys, not PHP's key[] form — the API reads b=x&b=y. @param array<string,string|array<int,string>> $query */
	private static function query( array $query ): string {
		$parts = array();
		foreach ( $query as $key => $value ) {
			foreach ( (array) $value as $one ) {
				$parts[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $one );
			}
		}
		return implode( '&', $parts );
	}

	/** @param array{status:int,body:string,headers:array<string,string>} $response @return array<mixed> */
	private function decode( array $response, string $path ): array {
		$status = $response['status'];
		$detail = $this->safe( $response['body'] );
		if ( 401 === $status || 403 === $status ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic refused the request: the token was rejected or these credentials cannot see that venue.', $status, $detail );
		}
		if ( 429 === $status ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic is rate-limiting this site (400 requests per 10 minutes). Try again in a few minutes.', $status, $detail );
		}
		if ( 400 === $status ) {
			$data = json_decode( $response['body'], true );
			$code = is_array( $data ) ? (string) ( $data['error'] ?? $data['code'] ?? '' ) : '';
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic rejected the request' . ( '' !== $code ? ' (' . $code . ')' : '' ) . '. Check the dates and venue id.', $status, $detail );
		}
		if ( 202 === $status ) {
			$after = (int) ( $response['headers']['retry-after'] ?? $response['headers']['Retry-After'] ?? 60 );
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic is still preparing that venue\'s data. Try again in about ' . max( 1, $after ) . ' seconds.', $status, $detail );
		}
		if ( $status < 200 || $status >= 300 ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic answered with an error (HTTP ' . $status . ') for ' . $path . '.', $status, $detail );
		}
		$data = json_decode( $response['body'], true );
		if ( ! is_array( $data ) ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Playtomic answered with something that was not JSON.', $status, $detail );
		}
		return $data;
	}

	private function safe( string $body ): string {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		$token  = is_array( $cached ) ? (string) ( $cached['token'] ?? '' ) : '';
		return substr( self::scrub( $body, array( $this->secret, $token ) ), 0, 500 );
	}

	/** @return array<string,mixed> */
	private static function jwt_payload( string $token ): array {
		$parts = explode( '.', $token );
		if ( count( $parts ) < 2 ) {
			return array();
		}
		$json = base64_decode( strtr( $parts[1], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $parts[1] ) % 4 ) % 4 ), true );
		$data = false === $json ? null : json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}
}
```

Add to `includes/bootstrap.php` (interface before the client):

```php
require_once __DIR__ . '/playtomic/class-playtomic-exception.php';
require_once __DIR__ . '/playtomic/interface-playtomic-transport.php';
require_once __DIR__ . '/playtomic/class-playtomic-client.php';
```

- [ ] **Step 6: Run the tests** — `vendor/bin/phpunit tests/php/PlaytomicClientTest.php` — Expected: PASS. If `delete_transient` or `error_log` is missing from the stubs, it is not: both exist (`delete_transient` in `wp-stubs.php:655`; `error_log` is PHP's own).

- [ ] **Step 7: Commit**

```bash
git add includes/playtomic includes/bootstrap.php tests/php/bootstrap.php tests/php/fakes/class-fake-playtomic-transport.php tests/php/PlaytomicClientTest.php
git commit -m "Playtomic client: token, cache, venues and plain-English errors

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 8: The client: bookings, players, player, payments, retry and paging

**Files:**
- Modify: `includes/playtomic/class-playtomic-client.php`
- Test: `tests/php/PlaytomicClientTest.php` (add tests)

**Interfaces:**
- Produces: `bookings( string $venue, string $from, string $to, array $extra = array() ): array` (every page, `size` 200, `page` from 0, stops on a short page); `players( string $venue, array $include = array() ): array` (every cursor page, `limit` 100); `player( string $venue, string $id, array $include = array() ): array`; `payments( string $venue, array $filters = array() ): array` (every cursor page, `limit` 100; filters pass straight through: `start_service_date` etc.); `PLAYERS_PATH = '/api/v1/venues/%s/players'`, `PLAYERS_PATH_SINGULAR = '/api/v1/venue/%s/players'`.
- Dates for bookings must be `YYYY-MM-DDTHH:MM:SS` UTC; the client passes them through unchanged and throws a plain error if either does not match that pattern.

- [ ] **Step 1: Add the failing tests**

```php
	public function test_401_refreshes_once_then_gives_up(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 401, 'body' => '' );
		$this->t->queue[] = $this->token_ok( array( 'ten-a' ) );
		$this->t->queue[] = array( 'status' => 401, 'body' => '' );
		$this->expectExceptionMessage( 'Playtomic refused the request' );
		try {
			$this->client()->get( '/api/v1/x' );
		} finally {
			$this->assertCount( 4, $this->t->seen, 'token, call, new token, call — and no more' );
		}
	}

	public function test_bookings_follows_pages_until_a_short_one(): void {
		$full = json_encode( array_fill( 0, 200, array( 'booking_id' => 'b' ) ) );
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 200, 'body' => $full );
		$this->t->queue[] = array( 'status' => 200, 'body' => json_encode( array( array( 'booking_id' => 'last' ) ) ) );
		$all = $this->client()->bookings( 'ten-a', '2026-10-01T00:00:00', '2026-10-31T23:59:59' );
		$this->assertCount( 201, $all );
		$this->assertStringContainsString( 'tenant_id=ten-a', $this->t->seen[1]['url'] );
		$this->assertStringContainsString( 'start_booking_date=2026-10-01T00%3A00%3A00', $this->t->seen[1]['url'] );
		$this->assertStringContainsString( 'page=0', $this->t->seen[1]['url'] );
		$this->assertStringContainsString( 'size=200', $this->t->seen[1]['url'] );
		$this->assertStringContainsString( 'page=1', $this->t->seen[2]['url'] );
	}

	public function test_bookings_refuses_a_bad_date_before_calling(): void {
		$this->expectExceptionMessage( 'Dates must look like 2026-10-01T00:00:00' );
		$this->client()->bookings( 'ten-a', '2026-10-01', '2026-10-31' );
		$this->assertSame( array(), $this->t->seen );
	}

	public function test_players_follows_cursors_and_falls_back_to_the_singular_path(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 404, 'body' => '' );
		$this->t->queue[] = array( 'status' => 200, 'body' => json_encode( array( 'has_more' => true, 'next_cursor_id' => 'c2', 'data' => array( array( 'player_id' => '1' ) ) ) ) );
		$this->t->queue[] = array( 'status' => 200, 'body' => json_encode( array( 'has_more' => false, 'next_cursor_id' => null, 'data' => array( array( 'player_id' => '2' ) ) ) ) );
		$all = $this->client()->players( 'ten-a', array( 'BENEFITS' ) );
		$this->assertSame( array( '1', '2' ), array_column( $all, 'player_id' ) );
		$this->assertStringContainsString( '/api/v1/venues/ten-a/players?', $this->t->seen[1]['url'] );
		$this->assertStringContainsString( '/api/v1/venue/ten-a/players?', $this->t->seen[2]['url'] );
		$this->assertStringContainsString( 'include=BENEFITS', $this->t->seen[2]['url'] );
		$this->assertStringContainsString( 'cursor_id=c2', $this->t->seen[3]['url'] );
		$this->assertStringContainsString( '/api/v1/venue/', $this->t->seen[3]['url'], 'once the singular path answered, it is kept' );
	}

	public function test_player_fetches_one(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 200, 'body' => '{"player_id":"9","name":"Nine"}' );
		$this->assertSame( 'Nine', $this->client()->player( 'ten-a', '9' )['name'] );
		$this->assertStringEndsWith( '/api/v1/venues/ten-a/players/9', $this->t->seen[1]['url'] );
	}

	public function test_payments_follows_cursors_and_says_when_still_preparing(): void {
		$this->t->queue[] = $this->token_ok();
		$this->t->queue[] = array( 'status' => 202, 'body' => '{"status":"PROCESSING"}', 'headers' => array( 'retry-after' => '30' ) );
		try {
			$this->client()->payments( 'ten-a' );
			$this->fail( 'expected an exception' );
		} catch ( Blueworx_Clubhouse_Playtomic_Exception $e ) {
			$this->assertSame( 202, $e->status() );
			$this->assertStringContainsString( '30 seconds', $e->getMessage() );
		}

		$this->t->queue[] = array( 'status' => 200, 'body' => json_encode( array( 'has_more' => true, 'next_cursor_id' => 'n', 'data' => array( array( 'payment_id' => 'p1' ) ) ) ) );
		$this->t->queue[] = array( 'status' => 200, 'body' => json_encode( array( 'has_more' => false, 'next_cursor_id' => null, 'data' => array( array( 'payment_id' => 'p2' ) ) ) ) );
		$all = $this->client()->payments( 'ten-a', array( 'start_payment_date' => '2026-09-01T00:00:00Z' ) );
		$this->assertSame( array( 'p1', 'p2' ), array_column( $all, 'payment_id' ) );
		$this->assertStringContainsString( 'start_payment_date=', $this->t->seen[2]['url'] );
		$this->assertStringContainsString( 'limit=100', $this->t->seen[2]['url'] );
	}
```

- [ ] **Step 2: Run to see them fail** — Expected: FAIL, methods undefined.

- [ ] **Step 3: Add the endpoint methods to the client**

```php
	public const PLAYERS_PATH          = '/api/v1/venues/%s/players';
	public const PLAYERS_PATH_SINGULAR = '/api/v1/venue/%s/players';

	private const PAGE_SIZE   = 200;
	private const CURSOR_SIZE = 100;

	/** Which players path answered last; the docs show both. */
	private ?string $players_path = null;

	/**
	 * Every booking at a venue between two UTC moments (YYYY-MM-DDTHH:MM:SS,
	 * at most 365 days apart; Playtomic keeps three months of the past).
	 *
	 * @param array<string,string|array<int,string>> $extra Any other filter the docs list, e.g. status, sport_id.
	 * @return array<int,array<mixed>>
	 */
	public function bookings( string $venue, string $from, string $to, array $extra = array() ): array {
		foreach ( array( $from, $to ) as $date ) {
			if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $date ) ) {
				throw new Blueworx_Clubhouse_Playtomic_Exception( 'Dates must look like 2026-10-01T00:00:00 (UTC).' );
			}
		}
		$all  = array();
		$page = 0;
		do {
			$batch = $this->get(
				'/api/v1/bookings',
				array_merge( $extra, array( 'tenant_id' => $venue, 'start_booking_date' => $from, 'end_booking_date' => $to, 'page' => (string) $page, 'size' => (string) self::PAGE_SIZE ) )
			);
			$batch = array_values( array_filter( $batch, 'is_array' ) );
			$all   = array_merge( $all, $batch );
			++$page;
		} while ( count( $batch ) >= self::PAGE_SIZE );
		return $all;
	}

	/** Every player registered at a venue. @param array<int,string> $include BENEFITS, SPORTS, WALLETS. @return array<int,array<mixed>> */
	public function players( string $venue, array $include = array() ): array {
		$query = array( 'limit' => (string) self::CURSOR_SIZE );
		if ( array() !== $include ) {
			$query['include'] = $include;
		}
		return $this->cursor_pages( sprintf( $this->players_path(), rawurlencode( $venue ) ), $query, $venue );
	}

	/** @param array<int,string> $include @return array<mixed> */
	public function player( string $venue, string $id, array $include = array() ): array {
		$query = array() === $include ? array() : array( 'include' => $include );
		return $this->get( sprintf( $this->players_path(), rawurlencode( $venue ) ) . '/' . rawurlencode( $id ), $query );
	}

	/**
	 * Every payment at a venue. Filters pass straight through — see the
	 * Payments page: one date axis only, 13 months at most.
	 *
	 * @param array<string,string> $filters
	 * @return array<int,array<mixed>>
	 */
	public function payments( string $venue, array $filters = array() ): array {
		return $this->cursor_pages( '/api/v1/payments', array_merge( $filters, array( 'tenant_id' => $venue, 'limit' => (string) self::CURSOR_SIZE ) ), null );
	}

	/* ---------------------------------------------------------------- */

	private function players_path(): string {
		return $this->players_path ?? self::PLAYERS_PATH;
	}

	/**
	 * Follow has_more / next_cursor_id to the end. When $venue is given, a 404
	 * on the first page tries the docs' singular path once and remembers it.
	 *
	 * @param array<string,string|array<int,string>> $query
	 * @return array<int,array<mixed>>
	 */
	private function cursor_pages( string $path, array $query, ?string $venue ): array {
		$all    = array();
		$cursor = '';
		do {
			$q = $query;
			if ( '' !== $cursor ) {
				$q['cursor_id'] = $cursor;
			}
			try {
				$page = $this->get( $path, $q );
			} catch ( Blueworx_Clubhouse_Playtomic_Exception $e ) {
				if ( 404 === $e->status() && null !== $venue && '' === $cursor && null === $this->players_path ) {
					$this->players_path = self::PLAYERS_PATH_SINGULAR;
					$path               = sprintf( self::PLAYERS_PATH_SINGULAR, rawurlencode( $venue ) );
					$page               = $this->get( $path, $q );
				} else {
					throw $e;
				}
			}
			$data   = is_array( $page['data'] ?? null ) ? array_values( array_filter( $page['data'], 'is_array' ) ) : array();
			$all    = array_merge( $all, $data );
			$cursor = ! empty( $page['has_more'] ) ? (string) ( $page['next_cursor_id'] ?? '' ) : '';
		} while ( '' !== $cursor );
		return $all;
	}
```

- [ ] **Step 4: Run the whole client test** — `vendor/bin/phpunit tests/php/PlaytomicClientTest.php` — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/playtomic/class-playtomic-client.php tests/php/PlaytomicClientTest.php
git commit -m "Playtomic client wraps bookings, players and payments with paging

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 9: The WordPress transport and the SureCart subscription rule

**Files:**
- Create: `includes/playtomic/class-playtomic-wp-transport.php`
- Create: `includes/playtomic/class-playtomic-subscriptions.php`
- Modify: `blueworx-labs-clubhouse.php` (requires, next to the other WP-facing classes), `tests/php/bootstrap.php` (require the subscriptions class)
- Test: `tests/php/PlaytomicSubscriptionsTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Playtomic_Wp_Transport implements Blueworx_Clubhouse_Playtomic_Transport` (15-second timeout; a `WP_Error` becomes a `Playtomic_Exception` reading "Could not reach Playtomic: <error message>."; response headers lower-cased).
- Produces: `Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( int $user_id, array $price_ids ): bool`, `available(): bool` (SureCart active), `set_raw_fetcher( ?callable $f ): void` (test seam, gated on `BLUEWORX_CLUBHOUSE_RUNNING_TESTS` like `SureCart_Products::set_raw_fetcher`), `CUSTOMER_META = 'sc_customer_ids'`, `ROUTE = '/surecart/v1/subscriptions'`.

- [ ] **Step 1: Verify SureCart's customer meta and subscriptions route against its source**

Download the current SureCart plugin from wordpress.org into an empty scratch folder and grep, rather than guessing (`docs/integrations/surecart-notes.md` records what guessing cost last time):

```bash
mkdir -p "$SCRATCH/surecart" && cd "$SCRATCH/surecart"
curl -sL https://downloads.wordpress.org/plugin/surecart.latest-stable.zip -o surecart.zip && unzip -q surecart.zip
grep -rn "sc_customer_ids" surecart/app/src | head
grep -rn "customer_ids\|'status'" surecart/app/src/Rest/SubscriptionsController.php surecart/app/src/Routes/*.php 2>/dev/null | head
grep -rn "function customerId\|function customerIds" surecart/app/src/Models/User.php
```

Record in the class docblock what was found: the meta key, its shape (expected: an array keyed `live` / `test` → customer id), and the query params the subscriptions route takes (expected: `customer_ids[]`, `status[]`, `per_page`). If the names differ, use the real ones everywhere below and update `docs/integrations/surecart-notes.md` with a dated paragraph.

- [ ] **Step 2: Write the failing tests**

```php
<?php
// tests/php/PlaytomicSubscriptionsTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicSubscriptionsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( null );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( null );
	}

	public function test_no_price_ids_means_nobody_qualifies(): void {
		$this->assertFalse( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array() ) );
	}

	public function test_user_without_a_customer_id_does_not_qualify_and_never_asks(): void {
		$asked = false;
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( function () use ( &$asked ): ?array { $asked = true; return array(); } );
		$this->assertFalse( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
		$this->assertFalse( $asked );
	}

	public function test_active_subscription_on_a_chosen_price_qualifies(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1' ) );
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( fn( array $customer_ids ): ?array => array(
			array( 'id' => 's1', 'status' => 'active', 'price' => 'p1' ),
			array( 'id' => 's2', 'status' => 'canceled', 'price' => 'p2' ),
		) );
		$this->assertTrue( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
		$this->assertFalse( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p2' ) ) );
	}

	public function test_price_may_be_an_expanded_object(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1', 'test' => 'cust-t' ) );
		$seen = array();
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( function ( array $customer_ids ) use ( &$seen ): ?array {
			$seen = $customer_ids;
			return array( array( 'id' => 's1', 'status' => 'trialing', 'price' => array( 'id' => 'p1' ) ) );
		} );
		$this->assertTrue( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
		$this->assertSame( array( 'cust-1', 'cust-t' ), $seen );
	}

	public function test_a_failed_fetch_does_not_qualify(): void {
		update_user_meta( 1, 'sc_customer_ids', array( 'live' => 'cust-1' ) );
		Blueworx_Clubhouse_Playtomic_Subscriptions::set_raw_fetcher( fn( array $ids ): ?array => null );
		$this->assertFalse( Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( 1, array( 'p1' ) ) );
	}
}
```

- [ ] **Step 3: Run to see them fail** — `vendor/bin/phpunit tests/php/PlaytomicSubscriptionsTest.php` — Expected: FAIL.

- [ ] **Step 4: Write the transport**

```php
<?php
// includes/playtomic/class-playtomic-wp-transport.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * wp_remote_request, shaped for the Playtomic client. The only place the
 * plugin makes an HTTP call to Playtomic.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Wp_Transport implements Blueworx_Clubhouse_Playtomic_Transport {

	private const TIMEOUT = 15;

	public function request( string $method, string $url, array $headers, ?string $body ): array {
		$args = array( 'method' => $method, 'headers' => $headers, 'timeout' => self::TIMEOUT );
		if ( null !== $body ) {
			$args['body'] = $body;
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Could not reach Playtomic: ' . $response->get_error_message() . '.' );
		}
		$out = array();
		foreach ( (array) wp_remote_retrieve_headers( $response ) as $name => $value ) {
			$out[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}
		return array(
			'status'  => (int) wp_remote_retrieve_response_code( $response ),
			'body'    => (string) wp_remote_retrieve_body( $response ),
			'headers' => $out,
		);
	}
}
```

- [ ] **Step 5: Write the subscription rule**

```php
<?php
// includes/playtomic/class-playtomic-subscriptions.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Does this member hold a live SureCart subscription on one of the club's
 * chosen prices? Read through SureCart's own REST route dispatched
 * in-process, the way SureCart_Products reads prices.
 *
 * Verified against SureCart <version> source on <date> (Task 9, step 1):
 * the user's customer ids live in user meta `sc_customer_ids` as an array
 * keyed by mode ('live', 'test'); `/surecart/v1/subscriptions` takes
 * `customer_ids[]` and `status[]`.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Subscriptions {

	public const CUSTOMER_META = 'sc_customer_ids';
	public const ROUTE         = '/surecart/v1/subscriptions';

	/** Statuses that mean the membership is in force. */
	private const LIVE = array( 'active', 'trialing', 'past_due' );

	/** @var (callable(array<int,string>):?array<int,mixed>)|null */
	private static $raw_fetcher = null;

	/** Test-only seam. @param (callable(array<int,string>):?array<int,mixed>)|null $f */
	public static function set_raw_fetcher( ?callable $f ): void {
		if ( ! defined( 'BLUEWORX_CLUBHOUSE_RUNNING_TESTS' ) || ! BLUEWORX_CLUBHOUSE_RUNNING_TESTS ) {
			return;
		}
		self::$raw_fetcher = $f;
	}

	public static function available(): bool {
		return Blueworx_Clubhouse_SureCart_Products::is_active();
	}

	/** @param array<int,string> $price_ids */
	public static function qualifies( int $user_id, array $price_ids ): bool {
		if ( array() === $price_ids ) {
			return false;
		}
		$customers = self::customer_ids( $user_id );
		if ( array() === $customers ) {
			return false;
		}
		$subscriptions = self::fetch( $customers );
		if ( null === $subscriptions ) {
			return false;
		}
		foreach ( $subscriptions as $sub ) {
			if ( ! is_array( $sub ) || ! in_array( (string) ( $sub['status'] ?? '' ), self::LIVE, true ) ) {
				continue;
			}
			$price = $sub['price'] ?? '';
			$price = is_array( $price ) ? (string) ( $price['id'] ?? '' ) : (string) $price;
			if ( in_array( $price, $price_ids, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** @return array<int,string> */
	private static function customer_ids( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::CUSTOMER_META, true );
		if ( ! is_array( $raw ) ) {
			return '' === (string) $raw ? array() : array( (string) $raw );
		}
		return array_values( array_filter( array_map( 'strval', $raw ), static fn( string $id ): bool => '' !== $id ) );
	}

	/** @param array<int,string> $customers @return array<int,mixed>|null */
	private static function fetch( array $customers ): ?array {
		if ( null !== self::$raw_fetcher ) {
			return ( self::$raw_fetcher )( $customers );
		}
		if ( ! self::available() || ! function_exists( 'rest_do_request' ) || ! class_exists( 'WP_REST_Request' ) ) {
			return null;
		}
		try {
			if ( function_exists( 'rest_get_server' ) && function_exists( 'did_action' ) && ! did_action( 'rest_api_init' ) ) {
				do_action( 'rest_api_init', rest_get_server() );
			}
			$request = new WP_REST_Request( 'GET', self::ROUTE );
			$request->set_param( 'customer_ids', $customers );
			$request->set_param( 'status', self::LIVE );
			$request->set_param( 'per_page', 100 );
			$response = rest_do_request( $request );
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ( method_exists( $response, 'is_error' ) && $response->is_error() ) ) {
			return null;
		}
		$data = $response->get_data();
		return is_array( $data ) ? $data : null;
	}
}
```

Fill in the `<version>` and `<date>` in the docblock from step 1.

- [ ] **Step 6: Load them**

In `blueworx-labs-clubhouse.php`, after the `class-seo-controller.php` require:

```php
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/playtomic/class-playtomic-wp-transport.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/playtomic/class-playtomic-subscriptions.php';
```

In `tests/php/bootstrap.php`, after the `class-seo-controller.php` require:

```php
require_once dirname( __DIR__, 2 ) . '/includes/playtomic/class-playtomic-subscriptions.php';
```

- [ ] **Step 7: Run the tests** — `vendor/bin/phpunit tests/php/PlaytomicSubscriptionsTest.php` — Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add includes/playtomic blueworx-labs-clubhouse.php tests/php/bootstrap.php tests/php/PlaytomicSubscriptionsTest.php docs/integrations/surecart-notes.md
git commit -m "Playtomic: real HTTP transport, and the SureCart subscription rule

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 10: The screen (pure HTML)

**Files:**
- Create: `includes/playtomic/class-playtomic-screen.php`
- Modify: `includes/bootstrap.php` (require, after the members class)
- Test: `tests/php/PlaytomicScreenTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Playtomic_Screen::render( array $model ): string` and the constants the controller posts against: `FIELD = 'clubhouse_playtomic'` (settings form prefix), `IDS = 'clubhouse_playtomic_ids'`, `FILTER = 'playtomic_filter'`, `FROM = 'playtomic_from'`, `TO = 'playtomic_to'`.
- Model:

```php
array(
	'page_url'     => string,   // admin.php?page=clubhouse-playtomic
	'action_url'   => string,   // admin-post.php
	'nonce_field'  => string,   // markup
	'actions'      => array{save:string,test:string,download:string,clear:string}, // admin-post action names
	'clear_urls'   => array<int,string>,  // user id → nonced clear URL
	'role_tags'    => string,
	'notice'       => array{type:string,text:string}|null,
	'settings'     => array<string,mixed>,   // from Playtomic_Settings::read(), 'secret' replaced by bool 'has_secret'
	'can_encrypt'  => bool,
	'shop'         => bool,
	'prices'       => array<int,array{id:string,label:string}>,
	'filter'       => 'new'|'all',
	'from'         => string, 'to' => string,
	'members'      => array<int,array<string,mixed>>,   // Playtomic_Members::rows()
)
```

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/php/PlaytomicScreenTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicScreenTest extends TestCase {

	/** @return array<string,mixed> */
	private function model( array $over = array() ): array {
		$settings               = Blueworx_Clubhouse_Playtomic_Settings::defaults();
		unset( $settings['secret'] );
		$settings['has_secret'] = true;
		$settings['venues']     = array( array_merge( Blueworx_Clubhouse_Playtomic_Settings::venue_defaults(), array( 'id' => 'ten-a', 'name' => 'North', 'club' => 'PadlX North' ) ) );
		return array_merge(
			array(
				'page_url'    => 'https://club.test/wp-admin/admin.php?page=clubhouse-playtomic',
				'action_url'  => 'https://club.test/wp-admin/admin-post.php',
				'nonce_field' => '<input type="hidden" name="_wpnonce" value="n">',
				'actions'     => array( 'save' => 'clubhouse_playtomic_save', 'test' => 'clubhouse_playtomic_test', 'download' => 'clubhouse_playtomic_download', 'clear' => 'clubhouse_playtomic_clear' ),
				'clear_urls'  => array( 2 => 'https://club.test/wp-admin/admin-post.php?action=clubhouse_playtomic_clear&id=2&_wpnonce=n' ),
				'role_tags'   => '',
				'notice'      => null,
				'settings'    => $settings,
				'can_encrypt' => true,
				'shop'        => true,
				'prices'      => array( array( 'id' => 'p1', 'label' => 'Founding — $99/yr' ) ),
				'filter'      => 'new',
				'from'        => '',
				'to'          => '',
				'members'     => array(
					array( 'id' => 1, 'name' => 'Ana Reyes', 'email' => 'ana@example.com', 'phone' => '0412', 'registered' => '2026-09-01', 'exported' => '', 'complete' => true, 'reason' => '' ),
					array( 'id' => 2, 'name' => 'Pat', 'email' => 'pat@example.com', 'phone' => '', 'registered' => '2026-10-10', 'exported' => '2026-10-12', 'complete' => true, 'reason' => '' ),
					array( 'id' => 3, 'name' => '', 'email' => 'x@example.com', 'phone' => '', 'registered' => '2026-10-11', 'exported' => '', 'complete' => false, 'reason' => 'No name on their account.' ),
				),
			),
			$over
		);
	}

	public function test_secret_is_never_echoed(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model() );
		$this->assertStringContainsString( 'type="password"', $html );
		$this->assertStringContainsString( 'Saved. Enter a new one only to replace it.', $html );
		$this->assertStringNotContainsString( 'value="hunter2"', $html );
	}

	public function test_venue_rows_plus_one_blank(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="clubhouse_playtomic[venues][0][id]" value="ten-a"', $html );
		$this->assertStringContainsString( 'name="clubhouse_playtomic[venues][1][id]" value=""', $html );
	}

	public function test_members_table_ticks_only_complete_unexported_rows(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model() );
		$this->assertMatchesRegularExpression( '/name="clubhouse_playtomic_ids\[\]" value="1"[^>]*checked/', $html );
		$this->assertDoesNotMatchRegularExpression( '/name="clubhouse_playtomic_ids\[\]" value="2"[^>]*checked/', $html );
		$this->assertMatchesRegularExpression( '/name="clubhouse_playtomic_ids\[\]" value="3"[^>]*disabled/', $html );
		$this->assertStringContainsString( 'No name on their account.', $html );
		$this->assertStringContainsString( '2026-10-12', $html );
		$this->assertStringContainsString( 'Clear export mark', $html );
		$this->assertStringContainsString( 'data-playtomic-count', $html );
	}

	public function test_download_button_is_disabled_with_nothing_listed(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model( array( 'members' => array() ) ) );
		$this->assertStringContainsString( 'Nobody to export with this filter.', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*name="' . 'clubhouse_playtomic_download' . '"[^>]*disabled/', $html );
	}

	public function test_subscription_rule_is_greyed_without_a_shop(): void {
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $this->model( array( 'shop' => false, 'prices' => array() ) ) );
		$this->assertStringContainsString( 'needs a shop', $html );
		$this->assertMatchesRegularExpression( '/value="subscriptions"[^>]*disabled/', $html );
	}

	public function test_notice_and_last_test_show(): void {
		$m = $this->model( array( 'notice' => array( 'type' => 'success', 'text' => 'Connected. Playtomic lists 2 venues.' ) ) );
		$m['settings']['last_test'] = 'Connected on 2026-10-15: ten-a, ten-b.';
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $m );
		$this->assertStringContainsString( 'bw-notice--success', $html );
		$this->assertStringContainsString( 'Connected. Playtomic lists 2 venues.', $html );
		$this->assertStringContainsString( 'Connected on 2026-10-15', $html );
	}

	public function test_every_club_string_is_escaped(): void {
		$m = $this->model();
		$m['members'][0]['name'] = '<b>x</b>';
		$m['settings']['venues'][0]['club'] = '"quoted"';
		$html = Blueworx_Clubhouse_Playtomic_Screen::render( $m );
		$this->assertStringNotContainsString( '<b>x</b>', $html );
		$this->assertStringContainsString( '&quot;quoted&quot;', $html );
	}
}
```

- [ ] **Step 2: Run to see them fail** — Expected: FAIL, class not found.

- [ ] **Step 3: Write the screen**

```php
<?php
// includes/playtomic/class-playtomic-screen.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Clubhouse → Playtomic screen: connection and settings first, then the
 * review table that is the last step before anyone is exported.
 *
 * Pure HTML from a model. Works without JavaScript: the table is a form
 * posted to admin-post; select-all and the live count are progressive.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Screen {

	public const FIELD  = 'clubhouse_playtomic';
	public const IDS    = 'clubhouse_playtomic_ids';
	public const FILTER = 'playtomic_filter';
	public const FROM   = 'playtomic_from';
	public const TO     = 'playtomic_to';

	private static function e( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$out = Blueworx_Clubhouse_Admin_Shell::open(
			'Clubhouse · Playtomic',
			'Playtomic',
			'Send your members to Playtomic Manager as a file it can import, and keep track of who has already gone.',
			(string) ( $model['role_tags'] ?? '' )
		);
		if ( is_array( $model['notice'] ?? null ) ) {
			$out .= self::notice( (string) $model['notice']['type'], (string) $model['notice']['text'] );
		}
		$out .= Blueworx_Clubhouse_Admin_Shell::card( 'Step 1', 'Connection and settings', 'Your Playtomic developer credentials, who counts as a member, and one row per venue.', self::settings_form( $model ) );
		$out .= Blueworx_Clubhouse_Admin_Shell::card( 'Step 2', 'Review and export', 'Tick who goes. Nobody is exported without a tick, and everyone exported is marked with the date.', self::members_form( $model ) );
		return $out . Blueworx_Clubhouse_Admin_Shell::close();
	}

	private static function notice( string $type, string $text ): string {
		$type = in_array( $type, array( 'success', 'danger', 'warning', 'info' ), true ) ? $type : 'info';
		return '<div class="bw-notice bw-notice--' . $type . '" role="status"><div class="bw-notice__body"><p class="bw-notice__text">' . self::e( $text ) . '</p></div></div>';
	}

	/* ------------------------------------------------------------------ */

	/** @param array<string,mixed> $model */
	private static function settings_form( array $model ): string {
		$s   = $model['settings'];
		$f   = self::FIELD;
		$out = '<form method="post" action="' . self::e( (string) $model['action_url'] ) . '" class="clubhouse-playtomic__settings">'
			. (string) $model['nonce_field']
			. '<input type="hidden" name="action" value="' . self::e( (string) $model['actions']['save'] ) . '">';

		$out .= '<div class="bw-fields">';
		$out .= self::text( $f . '[client_id]', 'client_id', 'Client ID', (string) $s['client_id'], 'From Playtomic Manager → Settings → Developer tools.' );
		$secret_help = ! empty( $s['has_secret'] ) ? 'Saved. Enter a new one only to replace it.' : 'Sent to you by Playtomic with the Client ID.';
		if ( empty( $model['can_encrypt'] ) ) {
			$secret_help .= ' This server cannot encrypt it, so it is stored as plain text.';
		}
		$out .= '<div class="bw-field"><label class="bw-field__label" for="playtomic-secret">Secret</label>'
			. '<input class="bw-input" type="password" id="playtomic-secret" name="' . $f . '[secret]" value="" autocomplete="new-password">'
			. '<p class="bw-field__help">' . self::e( $secret_help ) . '</p></div>';
		$out .= '</div>';

		$last = (string) ( $s['last_test'] ?? '' );
		$out .= '<p class="bw-fieldnote">' . ( '' !== $last ? self::e( $last ) : 'Not tested yet.' ) . '</p>';
		$err = (string) ( $s['last_error'] ?? '' );
		if ( '' !== $err ) {
			$out .= '<p class="bw-fieldnote clubhouse-playtomic__error">Last problem: ' . self::e( $err ) . '</p>';
		}

		$out .= self::who( $model );
		$out .= self::venues( $s['venues'] );

		$out .= '<div class="bw-card__actions clubhouse-playtomic__buttons">'
			. '<button type="submit" class="bw-btn bw-btn--primary">Save settings</button> '
			. '<button type="submit" class="bw-btn bw-btn--secondary" name="action" value="' . self::e( (string) $model['actions']['test'] ) . '">Save and test connection</button>'
			. '</div>';
		return $out . '</form>';
	}

	private static function text( string $name, string $id, string $label, string $value, string $help = '' ): string {
		$out = '<div class="bw-field"><label class="bw-field__label" for="playtomic-' . self::e( $id ) . '">' . self::e( $label ) . '</label>'
			. '<input class="bw-input" type="text" id="playtomic-' . self::e( $id ) . '" name="' . self::e( $name ) . '" value="' . self::e( $value ) . '">';
		if ( '' !== $help ) {
			$out .= '<p class="bw-field__help">' . self::e( $help ) . '</p>';
		}
		return $out . '</div>';
	}

	/** @param array<string,mixed> $model */
	private static function who( array $model ): string {
		$s      = $model['settings'];
		$f      = self::FIELD;
		$shop   = ! empty( $model['shop'] );
		$all    = Blueworx_Clubhouse_Playtomic_Settings::WHO_ALL === (string) $s['who'] || ! $shop;
		$out    = '<fieldset class="bw-field clubhouse-playtomic__who"><legend class="bw-field__label">Who counts as a member</legend>';
		$out   .= '<label class="bw-check"><input type="radio" name="' . $f . '[who]" value="all"' . ( $all ? ' checked' : '' ) . '> <span class="bw-check__text">Everyone with a member account</span></label>';
		$out   .= '<label class="bw-check"><input type="radio" name="' . $f . '[who]" value="subscriptions"' . ( $all ? '' : ' checked' ) . ( $shop ? '' : ' disabled' ) . '> <span class="bw-check__text">Only members with one of these subscriptions</span>'
			. ( $shop ? '' : '<span class="bw-check__help">This needs a shop. Install SureCart and set up your membership prices first.</span>' ) . '</label>';
		if ( $shop ) {
			$out .= '<div class="clubhouse-playtomic__prices">';
			foreach ( (array) $model['prices'] as $price ) {
				$checked = in_array( (string) $price['id'], (array) $s['price_ids'], true ) ? ' checked' : '';
				$out    .= '<label class="bw-check"><input type="checkbox" name="' . $f . '[price_ids][]" value="' . self::e( (string) $price['id'] ) . '"' . $checked . '> <span class="bw-check__text">' . self::e( (string) $price['label'] ) . '</span></label>';
			}
			if ( array() === (array) $model['prices'] ) {
				$out .= '<p class="bw-field__help">No prices found in the shop yet.</p>';
			}
			$out .= '</div>';
		}
		return $out . '</fieldset>';
	}

	/** @param array<int,array<string,mixed>> $venues */
	private static function venues( array $venues ): string {
		$rows = array_values( $venues );
		$rows[] = Blueworx_Clubhouse_Playtomic_Settings::venue_defaults();
		$out  = '<h3 class="bw-card__title">Venues</h3><p class="bw-card__note">One row per venue. Press Save and test connection to fill the ids in from Playtomic. The club name becomes part of the file\'s column headers, so it must match what Playtomic Manager calls the venue.</p>';
		$out .= '<div class="bw-tablescroll"><table class="bw-table clubhouse-playtomic__venues"><thead><tr>'
			. '<th>Venue id</th><th>Name</th><th>Club name in headers</th><th>Category</th><th>Expires</th><th>Price id</th><th>Next payment</th></tr></thead><tbody>';
		foreach ( $rows as $i => $v ) {
			$n    = self::FIELD . '[venues][' . $i . ']';
			$out .= '<tr>'
				. '<td><input class="bw-input" type="text" name="' . $n . '[id]" value="' . self::e( (string) $v['id'] ) . '" aria-label="Venue id"></td>'
				. '<td><input class="bw-input" type="text" name="' . $n . '[name]" value="' . self::e( (string) $v['name'] ) . '" aria-label="Venue name"></td>'
				. '<td><input class="bw-input" type="text" name="' . $n . '[club]" value="' . self::e( (string) $v['club'] ) . '" aria-label="Club name in headers"></td>'
				. '<td><input class="bw-input" type="text" name="' . $n . '[category]" value="' . self::e( (string) $v['category'] ) . '" aria-label="Category name"></td>'
				. '<td>' . self::select( $n . '[expiry_mode]', 'Expiry rule', array( 'months' => 'Months from export', 'date' => 'On a date' ), (string) $v['expiry_mode'] )
					. ' <input class="bw-input" type="number" min="1" max="120" name="' . $n . '[expiry_months]" value="' . (int) $v['expiry_months'] . '" aria-label="Months until expiry">'
					. ' <input class="bw-input" type="date" name="' . $n . '[expiry_date]" value="' . self::e( (string) $v['expiry_date'] ) . '" aria-label="Expiry date"></td>'
				. '<td><input class="bw-input" type="text" name="' . $n . '[price_id]" value="' . self::e( (string) $v['price_id'] ) . '" aria-label="Benefit price id"></td>'
				. '<td>' . self::select( $n . '[due_mode]', 'Next payment rule', array( 'none' => 'Leave blank', 'expiry' => 'Same as expiry', 'months' => 'Months from export' ), (string) $v['due_mode'] )
					. ' <input class="bw-input" type="number" min="1" max="120" name="' . $n . '[due_months]" value="' . (int) $v['due_months'] . '" aria-label="Months until next payment"></td>'
				. '</tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/** @param array<string,string> $options */
	private static function select( string $name, string $label, array $options, string $current, string $id = '' ): string {
		$out = '<select class="bw-select__el" name="' . $name . '" aria-label="' . self::e( $label ) . '"' . ( '' === $id ? '' : ' id="' . self::e( $id ) . '"' ) . '>';
		foreach ( $options as $value => $text ) {
			$out .= '<option value="' . self::e( (string) $value ) . '"' . ( $current === (string) $value ? ' selected' : '' ) . '>' . self::e( $text ) . '</option>';
		}
		return $out . '</select>';
	}

	/* ------------------------------------------------------------------ */

	/** @param array<string,mixed> $model */
	private static function members_form( array $model ): string {
		$out  = '<form method="get" action="' . self::e( strtok( (string) $model['page_url'], '?' ) ) . '" class="clubhouse-playtomic__filters bw-fields">'
			. '<input type="hidden" name="page" value="clubhouse-playtomic">'
			. '<div class="bw-field"><label class="bw-field__label" for="playtomic-filter">Show</label>'
			. self::select( self::FILTER, 'Show', array( 'new' => 'Not yet exported', 'all' => 'All members' ), (string) $model['filter'], 'playtomic-filter' ) . '</div>'
			. '<div class="bw-field"><label class="bw-field__label" for="playtomic-from">Joined from</label><input class="bw-input" type="date" id="playtomic-from" name="' . self::FROM . '" value="' . self::e( (string) $model['from'] ) . '"></div>'
			. '<div class="bw-field"><label class="bw-field__label" for="playtomic-to">Joined to</label><input class="bw-input" type="date" id="playtomic-to" name="' . self::TO . '" value="' . self::e( (string) $model['to'] ) . '"></div>'
			. '<div class="bw-field"><button type="submit" class="bw-btn bw-btn--secondary">Apply</button></div>'
			. '</form>';

		$members = (array) $model['members'];
		$out    .= '<form method="post" action="' . self::e( (string) $model['action_url'] ) . '" class="clubhouse-playtomic__export">'
			. (string) $model['nonce_field']
			. '<input type="hidden" name="action" value="' . self::e( (string) $model['actions']['download'] ) . '">'
			. '<input type="hidden" name="' . self::FROM . '" value="' . self::e( (string) $model['from'] ) . '">'
			. '<input type="hidden" name="' . self::TO . '" value="' . self::e( (string) $model['to'] ) . '">';

		if ( array() === $members ) {
			$out .= '<p class="bw-fieldnote">Nobody to export with this filter.</p>';
		} else {
			$out .= '<div class="bw-tablescroll"><table class="bw-table clubhouse-playtomic__members"><thead><tr>'
				. '<th class="bw-table__check"><input type="checkbox" data-playtomic-all aria-label="Tick everyone listed"></th>'
				. '<th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Exported</th></tr></thead><tbody>';
			foreach ( $members as $m ) {
				$id       = (int) $m['id'];
				$complete = ! empty( $m['complete'] );
				$ticked   = $complete && '' === (string) $m['exported'];
				$out     .= '<tr>'
					. '<td class="bw-table__check"><input type="checkbox" name="' . self::IDS . '[]" value="' . $id . '" aria-label="Export ' . self::e( (string) $m['name'] ) . '"' . ( $ticked ? ' checked' : '' ) . ( $complete ? '' : ' disabled' ) . '></td>'
					. '<td><span class="bw-table__primary">' . self::e( (string) $m['name'] ) . '</span>'
					. ( $complete ? '' : '<span class="bw-badge bw-badge--warning">' . self::e( (string) $m['reason'] ) . '</span>' ) . '</td>'
					. '<td>' . self::e( (string) $m['email'] ) . '</td>'
					. '<td>' . self::e( (string) $m['phone'] ) . '</td>'
					. '<td>' . self::e( (string) $m['registered'] ) . '</td>'
					. '<td>' . ( '' === (string) $m['exported'] ? '—' : self::e( (string) $m['exported'] ) . ' <a class="bw-btn bw-btn--link bw-btn--sm" href="' . self::e( (string) ( $model['clear_urls'][ $id ] ?? '' ) ) . '">Clear export mark</a>' ) . '</td>'
					. '</tr>';
			}
			$out .= '</tbody></table></div>';
		}

		$out .= '<div class="bw-card__actions clubhouse-playtomic__buttons">'
			. '<span class="bw-fieldnote" data-playtomic-count>' . count( array_filter( $members, static fn( array $m ): bool => ! empty( $m['complete'] ) && '' === (string) $m['exported'] ) ) . ' of ' . count( $members ) . ' selected</span> '
			. '<button type="submit" class="bw-btn bw-btn--primary" name="' . self::e( (string) $model['actions']['download'] ) . '" value="1"' . ( array() === $members ? ' disabled' : '' ) . '>Download Playtomic CSV</button>'
			. '</div>';
		return $out . '</form>';
	}
}
```

Add to `includes/bootstrap.php` after the members require: `require_once __DIR__ . '/playtomic/class-playtomic-screen.php';`

- [ ] **Step 4: Run the tests** — `vendor/bin/phpunit tests/php/PlaytomicScreenTest.php` — Expected: PASS. Adjust a selector in the test only if the markup it asserts is wrong, never the other way round.

- [ ] **Step 5: Commit**

```bash
git add includes/playtomic/class-playtomic-screen.php includes/bootstrap.php tests/php/PlaytomicScreenTest.php
git commit -m "The Playtomic screen: settings, venues and the review table

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 11: The controller, the menu, the guide and the select-all script

**Files:**
- Create: `includes/playtomic/class-playtomic-controller.php`
- Create: `assets/js/playtomic-export.js`
- Modify: `blueworx-labs-clubhouse.php` (require + `Blueworx_Clubhouse_Playtomic_Controller::register();` after the SEO controller's register)
- Modify: `tests/php/bootstrap.php` (require the controller after the SEO controller)
- Modify: `includes/admin/class-admin-pages.php:47-90` (new registry entry)
- Modify: `includes/admin/class-guides.php` (a guide in `members()`)
- Modify: `tests/php/GuidesTest.php:72-80` (sidebar map), `tests/php/AdminPagesTest.php` (if it counts pages)
- Test: `tests/php/PlaytomicControllerTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Playtomic_Controller::PAGE_SLUG = 'clubhouse-playtomic'`, `CAPABILITY = 'manage_options'`, `NONCE = 'clubhouse_playtomic'`, `SAVE_ACTION`, `TEST_ACTION`, `DOWNLOAD_ACTION`, `CLEAR_ACTION` (the strings in Task 10's test model), `NOTICE_ARG = 'playtomic_notice'`; `register()`, `add_menu()`, `enqueue( string $hook )`, `render_page()`, `handle_save()`, `handle_test()`, `handle_download()`, `handle_clear()`; the pure pieces `settings(): Blueworx_Clubhouse_Playtomic_Settings`, `key_material(): string`, `members(): Blueworx_Clubhouse_Playtomic_Members`, `test_connection( Blueworx_Clubhouse_Playtomic_Client $client, Blueworx_Clubhouse_Playtomic_Settings $settings ): array{ok:bool,text:string}`, `download_payload( Blueworx_Clubhouse_Playtomic_Members $members, array $ids, array $venues, string $today, string $from = '', string $to = '' ): ?array{filename:string,body:string,ids:array<int,int>}`, `notice_from( string $code ): ?array{type:string,text:string}`.
- Consumes: everything from Tasks 4 to 10.

- [ ] **Step 1: Write the failing tests** (the pure pieces; the WP entry points are covered by Task 12)

```php
<?php
// tests/php/PlaytomicControllerTest.php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlaytomicControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_page_is_in_the_registry_for_administrators_only(): void {
		$page = Blueworx_Clubhouse_Admin_Pages::find( 'clubhouse-playtomic' );
		$this->assertNotNull( $page );
		$this->assertSame( 'manage_options', $page['cap'] );
		$this->assertSame( array( 'administrator' ), Blueworx_Clubhouse_Admin_Pages::roles_with_access( 'clubhouse-playtomic' ) );
	}

	public function test_test_connection_reports_venues_and_merges_them(): void {
		$t = new Blueworx_Clubhouse_Fake_Playtomic_Transport();
		$t->queue[] = array( 'status' => 200, 'body' => json_encode( array( 'token' => fake_playtomic_jwt( array( 'ten-a', 'ten-b' ) ), 'expires_in' => 3600 ) ) );
		$settings = new Blueworx_Clubhouse_Playtomic_Settings( new Blueworx_Clubhouse_Fake_Storage(), new Blueworx_Clubhouse_Playtomic_Secret( 'k' ) );
		$settings->save( array( 'client_id' => 'c', 'secret' => 's' ) );
		$client = new Blueworx_Clubhouse_Playtomic_Client( $t, 'c', 's', fn(): int => 1000 );

		$result = Blueworx_Clubhouse_Playtomic_Controller::test_connection( $client, $settings );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'Connected. Playtomic lists 2 venues: ten-a, ten-b. Give each a name below.', $result['text'] );
		$this->assertSame( array( 'ten-a', 'ten-b' ), array_column( $settings->venues(), 'id' ) );
		$this->assertStringStartsWith( 'Connected on ', $settings->read()['last_test'] );
	}

	public function test_test_connection_failure_is_plain_and_logged_without_secrets(): void {
		$t = new Blueworx_Clubhouse_Fake_Playtomic_Transport();
		$t->queue[] = array( 'status' => 401, 'body' => '{"error":"UNAUTHORIZED","echo":"s"}' );
		$settings = new Blueworx_Clubhouse_Playtomic_Settings( new Blueworx_Clubhouse_Fake_Storage(), new Blueworx_Clubhouse_Playtomic_Secret( 'k' ) );
		$client   = new Blueworx_Clubhouse_Playtomic_Client( $t, 'c', 's', fn(): int => 1000 );

		$result = Blueworx_Clubhouse_Playtomic_Controller::test_connection( $client, $settings );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'Playtomic did not accept the Client ID and Secret.', $result['text'] );
		$this->assertSame( 'Playtomic did not accept the Client ID and Secret.', $settings->read()['last_error'] );
	}

	public function test_download_payload_builds_the_file_for_ticked_complete_members(): void {
		$members = new Blueworx_Clubhouse_Playtomic_Members( fn(): array => array(
			array( 'id' => 1, 'first' => 'Ana', 'last' => 'Reyes', 'display' => '', 'email' => 'ana@example.com', 'registered' => '2026-09-01', 'phone' => '0412 345 678', 'gender' => 'Female', 'birthdate' => '', 'exported' => '' ),
			array( 'id' => 3, 'first' => '', 'last' => '', 'display' => '', 'email' => 'x@example.com', 'registered' => '2026-09-01', 'phone' => '', 'gender' => '', 'birthdate' => '', 'exported' => '' ),
		) );
		$venue   = array_merge( Blueworx_Clubhouse_Playtomic_Settings::venue_defaults(), array( 'id' => 't', 'club' => 'PadlX North', 'category' => 'Founding' ) );
		$payload = Blueworx_Clubhouse_Playtomic_Controller::download_payload( $members, array( '1', '3', '99' ), array( $venue ), '2026-10-15' );
		$this->assertNotNull( $payload );
		$this->assertSame( 'playtomic-members-2026-10-15.csv', $payload['filename'] );
		$this->assertSame( array( 1 ), $payload['ids'] );
		$this->assertStringContainsString( "\r\nAna Reyes,ana@example.com,+61412345678,FEMALE,,Founding,2027-10-15,,\r\n", $payload['body'] );
	}

	public function test_download_with_no_valid_ids_marks_nobody(): void {
		$members = new Blueworx_Clubhouse_Playtomic_Members( fn(): array => array(
			array( 'id' => 3, 'first' => '', 'last' => '', 'display' => '', 'email' => 'x@example.com', 'registered' => '2026-09-01', 'phone' => '', 'gender' => '', 'birthdate' => '', 'exported' => '' ),
		) );
		$this->assertNull( Blueworx_Clubhouse_Playtomic_Controller::download_payload( $members, array(), array(), '2026-10-15' ) );
		$this->assertNull( Blueworx_Clubhouse_Playtomic_Controller::download_payload( $members, array( '3', '99' ), array(), '2026-10-15' ) );
		$this->assertSame( array(), wp_stub_calls( 'update_user_meta' ) );
	}

	public function test_notices_are_plain_sentences(): void {
		$this->assertSame( 'success', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'saved' )['type'] );
		$this->assertSame( 'warning', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'nothing' )['type'] );
		$this->assertStringContainsString( 'Tick at least one member', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'nothing' )['text'] );
		$this->assertSame( 'success', Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'cleared' )['type'] );
		$this->assertNull( Blueworx_Clubhouse_Playtomic_Controller::notice_from( 'junk' ) );
	}
}
```

Check `wp_stub_calls()`'s signature in `tests/php/wp-stubs.php:~60` and use it the way other tests do.

- [ ] **Step 2: Run to see them fail** — `vendor/bin/phpunit tests/php/PlaytomicControllerTest.php` — Expected: FAIL.

- [ ] **Step 3: Register the page**

In `includes/admin/class-admin-pages.php` `all()`, after the Search & sharing entry:

```php
			array(
				'slug'        => Blueworx_Clubhouse_Playtomic_Controller::PAGE_SLUG,
				'label'       => 'Playtomic',
				'cap'         => Blueworx_Clubhouse_Playtomic_Controller::CAPABILITY,
				'menu'        => Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG,
				'description' => 'Export members to Playtomic Manager, and the connection to Playtomic.',
			),
```

`manage_options` is in `Owner_Capabilities::denied()`, so the Clubhouse roles never get it and `roles_with_access()` returns only the administrator. If `AdminPagesTest` asserts a count of pages or a fixed list, update it to include this one.

- [ ] **Step 4: Write the controller**

```php
<?php
// includes/playtomic/class-playtomic-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Clubhouse → Playtomic screen's WordPress entry points. Everything that
 * can be decided without WordPress is a static method taking what it needs,
 * so it is unit-tested; the handle_*() methods only read the request, call
 * one of those, and redirect.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Controller {

	public const PAGE_SLUG  = 'clubhouse-playtomic';
	public const CAPABILITY = 'manage_options';
	public const NONCE      = 'clubhouse_playtomic';
	public const NOTICE_ARG = 'playtomic_notice';

	public const SAVE_ACTION     = 'clubhouse_playtomic_save';
	public const TEST_ACTION     = 'clubhouse_playtomic_test';
	public const DOWNLOAD_ACTION = 'clubhouse_playtomic_download';
	public const CLEAR_ACTION    = 'clubhouse_playtomic_clear';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( self::class, 'handle_test' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( self::class, 'handle_download' ) );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( self::class, 'handle_clear' ) );
	}

	public static function add_menu(): void {
		add_submenu_page( Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG, 'Playtomic', 'Playtomic', self::CAPABILITY, self::PAGE_SLUG, array( self::class, 'render_page' ) );
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		Blueworx_Clubhouse_Admin_Assets::enqueue();
		wp_enqueue_script( 'clubhouse-playtomic-export', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/playtomic-export.js', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	/* ---- the pure pieces ------------------------------------------------ */

	/** Key material for the secret: the site's own salts, which are already private per site. */
	public static function key_material(): string {
		$parts = array();
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY' ) as $name ) {
			$parts[] = defined( $name ) ? (string) constant( $name ) : '';
		}
		return implode( '|', $parts );
	}

	public static function settings(): Blueworx_Clubhouse_Playtomic_Settings {
		return new Blueworx_Clubhouse_Playtomic_Settings( new Blueworx_Clubhouse_Options_Storage(), new Blueworx_Clubhouse_Playtomic_Secret( self::key_material() ) );
	}

	public static function client( Blueworx_Clubhouse_Playtomic_Settings $settings ): Blueworx_Clubhouse_Playtomic_Client {
		return new Blueworx_Clubhouse_Playtomic_Client( new Blueworx_Clubhouse_Playtomic_Wp_Transport(), $settings->client_id(), $settings->secret() );
	}

	/** The members source and the qualifying rule, from the saved settings. */
	public static function members( ?Blueworx_Clubhouse_Playtomic_Settings $settings = null ): Blueworx_Clubhouse_Playtomic_Members {
		$all       = ( $settings ?? self::settings() )->read();
		$qualifies = null;
		if ( Blueworx_Clubhouse_Playtomic_Settings::WHO_SUBSCRIPTIONS === (string) $all['who'] && Blueworx_Clubhouse_Playtomic_Subscriptions::available() ) {
			$price_ids = (array) $all['price_ids'];
			$qualifies = static fn( int $user_id ): bool => Blueworx_Clubhouse_Playtomic_Subscriptions::qualifies( $user_id, $price_ids );
		}
		$source = static function (): array {
			if ( ! function_exists( 'get_users' ) ) {
				return array();
			}
			return Blueworx_Clubhouse_Playtomic_Members::from_users(
				get_users( array( 'role' => 'subscriber', 'number' => -1, 'orderby' => 'registered', 'order' => 'DESC' ) )
			);
		};
		return new Blueworx_Clubhouse_Playtomic_Members( $source, $qualifies );
	}

	/** @return array{ok:bool,text:string} */
	public static function test_connection( Blueworx_Clubhouse_Playtomic_Client $client, Blueworx_Clubhouse_Playtomic_Settings $settings ): array {
		try {
			$client->forget_token();
			$venues = $client->venues();
		} catch ( Blueworx_Clubhouse_Playtomic_Exception $e ) {
			$settings->set_last_error( $e->getMessage() );
			Blueworx_Clubhouse_Playtomic_Client::log( 'Test connection failed: ' . $e->getMessage() . ( '' !== $e->detail() ? ' | ' . $e->detail() : '' ) );
			return array( 'ok' => false, 'text' => $e->getMessage() );
		}
		$settings->merge_venues( $venues );
		$settings->set_last_error( '' );
		$settings->set_last_test( 'Connected on ' . gmdate( 'Y-m-d' ) . ': ' . ( array() === $venues ? 'no venues named in the token' : implode( ', ', $venues ) ) . '.' );
		$n = count( $venues );
		return array(
			'ok'   => true,
			'text' => 'Connected. Playtomic lists ' . $n . ' venue' . ( 1 === $n ? '' : 's' ) . ( $n > 0 ? ': ' . implode( ', ', $venues ) . '. Give each a name below.' : '.' ),
		);
	}

	/**
	 * The file for the ticked ids, or null when nobody valid was ticked.
	 *
	 * @param array<int,mixed>              $ids
	 * @param array<int,array<string,mixed>> $venues
	 * @return array{filename:string,body:string,ids:array<int,int>}|null
	 */
	public static function download_payload( Blueworx_Clubhouse_Playtomic_Members $members, array $ids, array $venues, string $today, string $from = '', string $to = '' ): ?array {
		$ids  = array_values( array_filter( array_map( 'intval', array_filter( $ids, 'is_scalar' ) ), static fn( int $id ): bool => $id > 0 ) );
		$rows = array() === $ids ? array() : $members->exportable( $ids, $from, $to );
		if ( array() === $rows ) {
			return null;
		}
		return array(
			'filename' => Blueworx_Clubhouse_Playtomic_Csv::FILENAME_PREFIX . $today . '.csv',
			'body'     => Blueworx_Clubhouse_Playtomic_Csv::build( $rows, $venues, $today ),
			'ids'      => array_map( 'intval', array_column( $rows, 'id' ) ),
		);
	}

	/** @return array{type:string,text:string}|null */
	public static function notice_from( string $code ): ?array {
		return match ( $code ) {
			'saved'       => array( 'type' => 'success', 'text' => 'Settings saved.' ),
			'test-ok'     => array( 'type' => 'success', 'text' => 'Connected. See the venues below.' ),
			'test-failed' => array( 'type' => 'danger', 'text' => 'Could not connect. The reason is under the Secret field.' ),
			'nothing'     => array( 'type' => 'warning', 'text' => 'Tick at least one member with a name and an email address, then download.' ),
			'cleared'     => array( 'type' => 'success', 'text' => 'Export mark cleared. They will be listed under Not yet exported again.' ),
			default       => null,
		};
	}

	/* ---- WordPress entry points ----------------------------------------- */

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filters on a GET screen, read-only.
		$filter = Blueworx_Clubhouse_Playtomic_Members::FILTER_ALL === (string) ( $_GET[ Blueworx_Clubhouse_Playtomic_Screen::FILTER ] ?? '' ) ? 'all' : 'new';
		$from   = self::date_arg( (string) ( $_GET[ Blueworx_Clubhouse_Playtomic_Screen::FROM ] ?? '' ) );
		$to     = self::date_arg( (string) ( $_GET[ Blueworx_Clubhouse_Playtomic_Screen::TO ] ?? '' ) );
		$code   = sanitize_key( (string) ( $_GET[ self::NOTICE_ARG ] ?? '' ) );
		// phpcs:enable

		$settings = self::settings();
		$all      = $settings->read();
		unset( $all['secret'] );
		$all['has_secret'] = $settings->has_secret();

		$members = self::members( $settings )->rows( $filter, $from, $to );
		$clear   = array();
		foreach ( $members as $m ) {
			if ( '' !== (string) $m['exported'] ) {
				$clear[ (int) $m['id'] ] = add_query_arg( array( 'action' => self::CLEAR_ACTION, 'id' => (int) $m['id'], '_wpnonce' => wp_create_nonce( self::NONCE ) ), admin_url( 'admin-post.php' ) );
			}
		}

		$prices = array();
		if ( Blueworx_Clubhouse_SureCart_Products::is_active() ) {
			foreach ( ( new Blueworx_Clubhouse_SureCart_Products() )->prices() as $price ) {
				$prices[] = array( 'id' => (string) $price['id'], 'label' => (string) $price['label'] . ' — ' . (string) $price['amount'] . (string) $price['period'] );
			}
		}

		$model = array(
			'page_url'    => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			'action_url'  => admin_url( 'admin-post.php' ),
			'nonce_field' => wp_nonce_field( self::NONCE, '_wpnonce', true, false ),
			'actions'     => array( 'save' => self::SAVE_ACTION, 'test' => self::TEST_ACTION, 'download' => self::DOWNLOAD_ACTION, 'clear' => self::CLEAR_ACTION ),
			'clear_urls'  => $clear,
			'role_tags'   => Blueworx_Clubhouse_Access_Controller::role_chips_for( self::PAGE_SLUG ),
			'notice'      => self::notice_from( $code ),
			'settings'    => $all,
			'can_encrypt' => Blueworx_Clubhouse_Playtomic_Secret::can_encrypt(),
			'shop'        => Blueworx_Clubhouse_SureCart_Products::is_active(),
			'prices'      => $prices,
			'filter'      => $filter,
			'from'        => $from,
			'to'          => $to,
			'members'     => $members,
		);
		echo Blueworx_Clubhouse_Playtomic_Screen::render( $model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Playtomic_Screen.
	}

	public static function handle_save(): void {
		self::guard();
		$raw = wp_unslash( $_POST[ Blueworx_Clubhouse_Playtomic_Screen::FIELD ] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised by Playtomic_Settings::save().
		self::settings()->save( is_array( $raw ) ? $raw : array() );
		self::back( 'saved' );
	}

	public static function handle_test(): void {
		self::guard();
		$raw = wp_unslash( $_POST[ Blueworx_Clubhouse_Playtomic_Screen::FIELD ] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised by Playtomic_Settings::save().
		$settings = self::settings();
		$settings->save( is_array( $raw ) ? $raw : array() );
		$result = self::test_connection( self::client( $settings ), $settings );
		self::back( $result['ok'] ? 'test-ok' : 'test-failed' );
	}

	public static function handle_download(): void {
		self::guard();
		$ids     = (array) wp_unslash( $_POST[ Blueworx_Clubhouse_Playtomic_Screen::IDS ] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- reduced to positive ints in download_payload().
		$from    = self::date_arg( (string) wp_unslash( $_POST[ Blueworx_Clubhouse_Playtomic_Screen::FROM ] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- date_arg() validates.
		$to      = self::date_arg( (string) wp_unslash( $_POST[ Blueworx_Clubhouse_Playtomic_Screen::TO ] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- date_arg() validates.
		$today   = gmdate( 'Y-m-d' );
		$settings = self::settings();
		$payload  = self::download_payload( self::members( $settings ), $ids, $settings->venues(), $today, $from, $to );
		if ( null === $payload ) {
			self::back( 'nothing' );
		}
		Blueworx_Clubhouse_Playtomic_Members::mark( $payload['ids'], $today );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $payload['filename'] . '"' );
		header( 'Content-Length: ' . strlen( $payload['body'] ) );
		echo $payload['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV download, not HTML.
		exit;
	}

	public static function handle_clear(): void {
		self::guard();
		$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in guard().
		if ( $id > 0 ) {
			Blueworx_Clubhouse_Playtomic_Members::clear( $id );
		}
		self::back( 'cleared', Blueworx_Clubhouse_Playtomic_Members::FILTER_ALL );
	}

	/* ---------------------------------------------------------------- */

	private static function guard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to do that.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE );
	}

	/** Back to the screen with a notice, keeping the filter. */
	private static function back( string $code, string $filter = '' ): void {
		$args = array( 'page' => self::PAGE_SLUG, self::NOTICE_ARG => $code );
		if ( '' !== $filter ) {
			$args[ Blueworx_Clubhouse_Playtomic_Screen::FILTER ] = $filter;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function date_arg( string $raw ): string {
		return Blueworx_Clubhouse_Playtomic_Settings::is_date( $raw ) ? $raw : '';
	}
}
```

- [ ] **Step 5: The progressive script**

```js
// assets/js/playtomic-export.js
// Select-all and the live "N of M selected" count on the Playtomic review
// table. The form works without this file; it only saves clicks.
(function () {
  var form = document.querySelector('.clubhouse-playtomic__export');
  if (!form) return;
  var all = form.querySelector('[data-playtomic-all]');
  var count = form.querySelector('[data-playtomic-count]');
  var button = form.querySelector('button[type="submit"]');
  var boxes = function () {
    return Array.prototype.slice.call(form.querySelectorAll('input[name="clubhouse_playtomic_ids[]"]:not([disabled])'));
  };
  var update = function () {
    var list = boxes();
    var ticked = list.filter(function (b) { return b.checked; }).length;
    if (count) count.textContent = ticked + ' of ' + form.querySelectorAll('input[name="clubhouse_playtomic_ids[]"]').length + ' selected';
    if (button) button.disabled = ticked === 0;
    if (all) {
      all.checked = list.length > 0 && ticked === list.length;
      all.indeterminate = ticked > 0 && ticked < list.length;
    }
  };
  if (all) {
    all.addEventListener('change', function () {
      boxes().forEach(function (b) { b.checked = all.checked; });
      update();
    });
  }
  form.addEventListener('change', update);
  update();
})();
```

- [ ] **Step 6: Load and register**

`blueworx-labs-clubhouse.php`: after the subscriptions require add `require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/playtomic/class-playtomic-controller.php';` and after `Blueworx_Clubhouse_Seo_Controller::register();` add `Blueworx_Clubhouse_Playtomic_Controller::register();`.

`tests/php/bootstrap.php`: after the subscriptions require add `require_once dirname( __DIR__, 2 ) . '/includes/playtomic/class-playtomic-controller.php';`.

- [ ] **Step 7: The guide**

In `includes/admin/class-guides.php` `members()`, append to its returned array (it is `members( bool $shop )`; this guide is shown regardless):

```php
			self::guide(
				'members-playtomic',
				'ch-members',
				'manage_options',
				'Send members to Playtomic',
				'Clubhouse → Playtomic',
				'Playtomic cannot take members from the website directly, so the site makes the file Playtomic Manager imports, and remembers who has already been sent.',
				array(
					'Enter the *Client ID* and *Secret* from Playtomic Manager → Settings → Developer tools, then press *Save and test connection*. Your venues appear in the table; give each a name, the club name Playtomic uses for it, and the membership category.',
					'Choose who counts: everyone with a member account, or only members on the subscriptions you tick.',
					'Under *Review and export*, tick the members to send — tick the box at the top to tick everyone listed — and press *Download Playtomic CSV*.',
					'In Playtomic Manager go to Customers → Imports → Import new customers and upload the file.',
				),
				'Everyone in the file is marked with the date. They drop out of *Not yet exported*; switch to *All members* to see them, and use *Clear export mark* if an import failed and they need to go again.'
			),
```

In `tests/php/GuidesTest.php`, add to the `$sidebar` map:

```php
			Blueworx_Clubhouse_Playtomic_Controller::PAGE_SLUG => 'Clubhouse → Playtomic',
```

- [ ] **Step 8: Run the tests**

Run: `vendor/bin/phpunit` (the whole suite). Expected: PASS, including `GuidesTest`, `AdminPagesTest`, `AccessScreenTest`.

- [ ] **Step 9: Commit**

```bash
git add includes/playtomic/class-playtomic-controller.php assets/js/playtomic-export.js blueworx-labs-clubhouse.php tests/php/bootstrap.php includes/admin/class-admin-pages.php includes/admin/class-guides.php tests/php/GuidesTest.php tests/php/AdminPagesTest.php tests/php/PlaytomicControllerTest.php
git commit -m "Clubhouse → Playtomic screen with export, test connection and a guide

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 12: The WordPress spec

**Files:**
- Create: `tests/playtomic-export.spec.js`
- Modify: `tests/global-setup.js` (seed the member's phone and clear the export mark each run)

**Interfaces:**
- Consumes: the `member` user seeded by `tests/global-setup.js:185` (login `member`, email `member@club.test`), `ADMIN_PASS` from `tests/helpers/credentials.js`, the screen's selectors from Task 10.

- [ ] **Step 1: Seed state in global-setup**

In the PHP block of `tests/global-setup.js`, after the `$member_user` block:

```php
// The Playtomic export (Task 12). Fresh each run: a phone on the member so the
// file has something to clean, and no export mark so the member is listed
// under "Not yet exported" whatever the last run did.
if ( $member_user ) {
	update_user_meta( $member_user->ID, 'clubhouse_profile_phone', '0412 345 678' );
	delete_user_meta( $member_user->ID, 'clubhouse_playtomic_exported' );
}
delete_option( 'clubhouse_playtomic' );
```

- [ ] **Step 2: Write the spec**

```js
// tests/playtomic-export.spec.js
const { test, expect } = require('@playwright/test');
const { readFileSync } = require('node:fs');
const { ADMIN_PASS } = require('./helpers/credentials');
const { targetingWordPress } = require('./harness');

// The Playtomic export: an administrator saves a venue, reviews the members
// table, ticks the seeded member, downloads the CSV, and sees them marked.
// Needs real WordPress (users, user meta, admin-post); the preview has none.

test.beforeEach(async ({ page }) => {
  test.skip(!targetingWordPress(), 'needs WordPress');
  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', ADMIN_PASS);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeAttached({ timeout: 60_000 });
});

const SCREEN = '/wp-admin/admin.php?page=clubhouse-playtomic';

test.describe.serial('playtomic export', () => {
  test('settings save and a venue row appears in the headers', async ({ page }) => {
    test.slow();
    await page.goto(SCREEN, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h1.bw-pagehead__h1')).toHaveText('Playtomic');

    await page.fill('input[name="clubhouse_playtomic[client_id]"]', 'test-client');
    await page.fill('input[name="clubhouse_playtomic[venues][0][id]"]', 'ten-a');
    await page.fill('input[name="clubhouse_playtomic[venues][0][name]"]', 'North');
    await page.fill('input[name="clubhouse_playtomic[venues][0][club]"]', 'Club North');
    await page.fill('input[name="clubhouse_playtomic[venues][0][category]"]', 'Founding');
    await page.locator('.clubhouse-playtomic__settings button.bw-btn--primary').click({ force: true });
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('.bw-notice')).toContainText('Settings saved.');
    await expect(page.locator('input[name="clubhouse_playtomic[venues][0][id]"]')).toHaveValue('ten-a');
    await expect(page.locator('input[name="clubhouse_playtomic[venues][1][id]"]')).toHaveValue('');
  });

  test('the member is listed, ticked, downloaded and marked', async ({ page }) => {
    test.slow();
    await page.goto(SCREEN, { waitUntil: 'domcontentloaded' });
    const row = page.locator('.clubhouse-playtomic__members tr', { hasText: 'member@club.test' });
    await expect(row).toBeVisible();
    await expect(row.locator('input[type="checkbox"]')).toBeChecked();
    await expect(row).toContainText('0412 345 678');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('.clubhouse-playtomic__export button[type="submit"]').click({ force: true }),
    ]);
    expect(download.suggestedFilename()).toMatch(/^playtomic-members-\d{4}-\d{2}-\d{2}\.csv$/);
    const csv = readFileSync(await download.path(), 'utf8');
    const lines = csv.split('\r\n');
    expect(lines[0]).toBe('name,email,phone_number,gender,birthdate,CAT_IMP-Club North-cat_name,CAT_IMP-Club North-exp_date,CAT_IMP-Club North-benefit_price_id,CAT_IMP-Club North-next_due_date');
    const member = lines.find((l) => l.includes('member@club.test'));
    expect(member).toBeTruthy();
    expect(member).toContain(',+61412345678,');
    expect(member).toMatch(/,Founding,\d{4}-\d{2}-\d{2},,$/);

    await page.goto(SCREEN, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.clubhouse-playtomic__members tr', { hasText: 'member@club.test' })).toHaveCount(0);

    await page.goto(SCREEN + '&playtomic_filter=all', { waitUntil: 'domcontentloaded' });
    const again = page.locator('.clubhouse-playtomic__members tr', { hasText: 'member@club.test' });
    await expect(again).toBeVisible();
    await expect(again).toContainText(/\d{4}-\d{2}-\d{2}/);
    await expect(again.locator('input[type="checkbox"]')).not.toBeChecked();

    await again.locator('a', { hasText: 'Clear export mark' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.bw-notice')).toContainText('Export mark cleared.');
    await page.goto(SCREEN, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.clubhouse-playtomic__members tr', { hasText: 'member@club.test' })).toBeVisible();
  });

  test('test connection with made-up credentials fails in plain words', async ({ page }) => {
    test.slow();
    await page.goto(SCREEN, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="clubhouse_playtomic[client_id]"]', 'test-client');
    await page.fill('#playtomic-secret', 'not-a-real-secret');
    await page.locator('.clubhouse-playtomic__settings button.bw-btn--secondary').click({ force: true });
    await page.waitForLoadState('domcontentloaded', { timeout: 60_000 });
    await expect(page.locator('.bw-notice')).toContainText('Could not connect.');
    await expect(page.locator('.clubhouse-playtomic__error')).toContainText(/Playtomic (did not accept|could not be reached|Could not reach)/);
    const html = await page.content();
    expect(html).not.toContain('not-a-real-secret');
  });
});
```

The third test makes one real HTTPS call to Playtomic with fake credentials. If CI has no outbound network, the message is the transport's "Could not reach Playtomic", which the regex accepts.

- [ ] **Step 3: Run it against WordPress**

```bash
npm run wp:up
npx playwright test tests/playtomic-export.spec.js --project=wordpress
```

Expected: 3 passed. If the first run fails with connection refused, it is the harness boot race: wait for `wp:up` to report ready and run again.

- [ ] **Step 4: Run the whole suite once** — `npm run test:wp` — Expected: everything green. `npm run wp:down` afterwards.

- [ ] **Step 5: Commit**

```bash
git add tests/playtomic-export.spec.js tests/global-setup.js
git commit -m "WordPress spec: export a ticked member to the Playtomic CSV and see them marked

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 13: The README for the client

**Files:**
- Create: `docs/integrations/playtomic.md`

- [ ] **Step 1: Write it**

```markdown
# Playtomic — how the plugin talks to it

Written 15 October 2026 from the official docs at https://third-party.playtomic.io/
(Authentication, Conventions, Bookings, Players, Payments). Nothing here was
verified against a live account yet; the Test connection button is the first
real call.

## What exists

- **Clubhouse → Playtomic** (administrators only): credentials, who counts as
  a member, one row per venue, and the review table that builds the CSV for
  Playtomic Manager's chain import (Customers → Imports → Import new customers).
- `Blueworx_Clubhouse_Playtomic_Client` — the only class that talks to the
  API. Everything else calls it.

## Using the client from a future feature

```php
$settings = Blueworx_Clubhouse_Playtomic_Controller::settings();
$client   = Blueworx_Clubhouse_Playtomic_Controller::client( $settings );

try {
	$venues   = $client->venues();                                        // tenant ids from the token
	$bookings = $client->bookings( $venues[0], '2026-10-01T00:00:00', '2026-10-31T23:59:59' );
	$players  = $client->players( $venues[0], array( 'BENEFITS' ) );
	$one      = $client->player( $venues[0], $players[0]['player_id'] );
	$payments = $client->payments( $venues[0], array( 'start_payment_date' => '2026-09-01T00:00:00Z' ) );
	$raw      = $client->get( '/api/v1/anything-new', array( 'tenant_id' => $venues[0] ) );
} catch ( Blueworx_Clubhouse_Playtomic_Exception $e ) {
	// $e->getMessage() is a plain sentence fit for a screen.
	// $e->status() is the HTTP status (0 for a network failure).
	// $e->detail() is the scrubbed response body, for the log only.
	Blueworx_Clubhouse_Playtomic_Client::log( $e->getMessage() . ' | ' . $e->detail() );
}
```

Things the client already does, so a feature does not have to:

- Gets a token (`POST /api/v1/oauth/token`, body `client_id` + `secret`), caches it in a transient, refreshes it five minutes before the hour is up, and once more if a call comes back 401.
- Follows every page: bookings by `page`/`size` (200) until a short page; players and payments by `cursor_id` until `has_more` is false.
- Tries `/api/v1/venues/{id}/players` first and `/api/v1/venue/{id}/players` if that 404s, and remembers which answered. The docs show both.
- Turns 400, 401/403, 429 (rate limit: 400 calls per 10 minutes), 202 (payments still being prepared) and network failures into one exception with a plain message.
- Scrubs the secret and the token out of anything it logs.

Things it does not do: retry on 429 or 202 (a feature decides whether to wait), schedule anything, or write to Playtomic (the API is read-only).

## Where the credentials live

In the `clubhouse_playtomic` option, with the secret encrypted using a key
derived from the site's own salts (`AUTH_KEY`, `SECURE_AUTH_KEY`,
`LOGGED_IN_KEY`). Changing the salts means re-entering the secret. The secret is
never shown on screen again and never written to the log.

## Before the first real import

- Compare the header row of a downloaded file with the official chain sample
  from Playtomic Manager. The headers live in one place:
  `Blueworx_Clubhouse_Playtomic_Csv`.
- Confirm the gender values Playtomic's import accepts. The file writes `MALE`
  and `FEMALE` (what the Players API returns) and leaves anything else blank.
- Phone numbers are written as `+61` followed by digits (`Playtomic_Csv::COUNTRY_CODE`).
- Import one or two dummy members and check the categories attach.
```

- [ ] **Step 2: Commit**

```bash
git add docs/integrations/playtomic.md
git commit -m "Notes on using the Playtomic client

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 14: Version, changelog, lint, zip, pull request

**Files:**
- Modify: `blueworx-labs-clubhouse.php:6` (header `Version:`) and `:33` (`BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Bump to 0.107.0 in both places**

```bash
sed -i 's/^ \* Version:           0.106.2/ * Version:           0.107.0/; s/BLUEWORX_LABS_CLUBHOUSE_VERSION'"'"', '"'"'0.106.2'"'"'/BLUEWORX_LABS_CLUBHOUSE_VERSION'"'"', '"'"'0.107.0'"'"'/' blueworx-labs-clubhouse.php
grep -n "0.107.0" blueworx-labs-clubhouse.php
```

Expected: two lines. If `package.json` carries a version, bump it too.

- [ ] **Step 2: Changelog entry** (above `## 0.106.2`)

```markdown
## 0.107.0

- A new Clubhouse → Playtomic screen, for administrators. It builds the file Playtomic Manager imports: tick the members to send, download the file, and they are marked with the date so they are never sent twice by accident. A "Clear export mark" link puts someone back if an import failed. Who counts as a member is a setting: everyone, or only those on the subscriptions you choose.
- Phone, gender and date of birth are now standard member details on every club, above the club's own questions on a member's Profile page and under Users. Each can be switched off on the Members tab.
- The site can connect to Playtomic's API with the club's own credentials, with a "Save and test connection" button that lists the venues. Nothing is shown from it yet; the connection is there for what comes next.
```

- [ ] **Step 3: Lint once, report, do not loop**

```bash
composer lint
vendor/bin/phpunit
```

Expected: PHPUnit green. Note every PHPCS finding for Luke in the final message; fix none unless he says so.

- [ ] **Step 4: Build and verify the zip**

```bash
bash bin/build-zip.sh
tar -tf ../blueworx-labs-clubhouse-0.107.0.zip | grep -c '\\' ; tar -tf ../blueworx-labs-clubhouse-0.107.0.zip | head -5
```

Expected: `0` backslashes, every entry starting `blueworx-labs-clubhouse/`. Remove any older `blueworx-labs-clubhouse*.zip` in the parent folder first.

- [ ] **Step 5: Commit and open the pull request**

```bash
git add blueworx-labs-clubhouse.php CHANGELOG.md package.json
git commit -m "Version 0.107.0: Playtomic connector

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
git push -u origin add-playtomic-connector
gh pr create --title "Add a Playtomic connector: members CSV export and API client" --body "Exports ticked members as the CSV Playtomic Manager imports and marks them so they are not sent twice. Adds phone, gender and date of birth as standard member details. Adds a read-only Playtomic API client with a Test connection button, for later features.

To decide before the first real import: compare the header row with Playtomic's chain sample, and confirm the gender values it accepts.

Closes #<issue number from Task 1>

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

After merging: tag `v0.107.0` and push the tag so sites update themselves.

