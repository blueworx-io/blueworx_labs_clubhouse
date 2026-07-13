# Admin Phase 3 — Collection Editing, Projection Robustness & Header Logo/Nav — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the six collection CPTs editable on WordPress's native post-edit screens via custom meta-boxes + admin list columns, harden `Fixture_Projection` for multi-year and malformed dates, render the owner's logo in the site header while omitting hidden pages from the nav, and fix a Phase-2 wiring bug where the Setup menu never registers.

**Architecture:** One new pure unit (`Collection_Meta`) is the single source of truth for collection field definitions, sanitisation, and list columns — consumed by thin WP glue (`Collection_Meta_Boxes`) and by `WP_Collections`. A one-method WP helper (`Media`) resolves attachment IDs → URLs in the WP layer only, so the pure core, mappers, seeder, projection, and preview stay byte-for-byte unchanged. Front-end logo + nav-omission thread resolved data (a logo URL string + the existing `Visibility`) into the pure `shell_header`/`shell_footer`.

**Tech Stack:** PHP 8.2, WordPress plugin, PHPUnit (WP-free with `function_exists`-guarded stubs in `tests/php/wp-stubs.php`), PHP_CodeSniffer (`composer lint`).

## Global Constraints

- **Requires PHP:** 8.2; **Requires WP:** 6.0. All files start with `declare(strict_types=1);` and the `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard.
- **Pure vs glue split:** logic is WP-free and unit-tested; only `Collection_Meta_Boxes`, `Media`, `WP_Collections`, `Frontend`, and the plugin bootstrap may call WordPress functions. Pure units use core PHP only.
- **Meta-key contract:** the keys the meta-boxes write are exactly those the mappers read (`class-collection-mappers.php`) and the seeder seeds (`class-collection-seeder.php`) — including the fixture `home_team`/`away_team` (meta) ↔ `home`/`away` (canonical) remap, which stays untouched.
- **Byte-identical render:** WordPress and the DB-free preview dispatch through `Page_Map::render` and must produce identical bodies for identical inputs. New render inputs (logo URL) are parameters, defaulting to `''`.
- **Output escaping:** every interpolated value is escaped at output (`htmlspecialchars( …, ENT_QUOTES, 'UTF-8' )` in pure code; `esc_attr`/`esc_html`/`esc_textarea` in glue).
- **Version:** bump `0.16.1 → 0.18.0` (minor) in `blueworx-labs-clubhouse.php` (header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json`, with a matching `CHANGELOG.md` entry — as the final task. (Phase 3 uses **0.18.0**, not 0.17.0: a parallel `admin-demo-mode` branch already claims 0.17.0, so Phase 3 cedes it. `0.18.0 > 0.16.1` keeps the CI version-bump check valid whichever branch merges first.)
- **Run from repo root:** tests `./vendor/bin/phpunit`; a single test `./vendor/bin/phpunit --filter TestName`; lint `composer lint`.
- **Isolation:** this branch is executed in a dedicated git worktree at `../blueworx_labs_clubhouse-phase3` (a second session works `admin-demo-mode` in the primary working copy). Run all commands from the worktree root.
- **Commit** after each task's tests pass. Branch is `admin-phase-3-cpt-editing` (created off `main`).

---

## File Structure

**New files**
- `includes/collections/class-collection-meta.php` — pure field defs + sanitisers + columns + media-key list (Task 1).
- `includes/collections/class-media.php` — WP helper `url(int): string` (Task 3).
- `includes/collections/class-collection-meta-boxes.php` — meta-box + save + column WP glue (Task 4).
- `assets/js/admin-collections.js` — vanilla `wp.media` picker for image fields (Task 4).
- `tests/php/CollectionMetaTest.php`, `tests/php/MediaTest.php`, `tests/php/WpCollectionsTest.php`, `tests/php/CollectionMetaBoxesTest.php` (Tasks 1/3/3/4).

**Modified files**
- `includes/render/class-fixture-projection.php` — `Y-m` grouping + `try_date` guard (Task 2).
- `includes/collections/class-wp-collections.php` — resolve media-key IDs → URLs (Task 3).
- `includes/render/class-sections.php` — `header()` logo slot (Task 6).
- `includes/render/class-page-renderer.php` — `shell_header`/`shell_footer` gain `Visibility` + logo, nav/footer filtering; 9 page methods gain optional `$logo_url` (Task 6).
- `includes/render/class-page-map.php` — `render()` gains optional `$logo_url` (Task 6).
- `includes/frontend/class-frontend.php` — `resolve_logo()` + thread into `render_body` (Task 7).
- `assets/looks/court-side.css`, `assets/looks/members-house.css`, `assets/looks/floodlight.css` — `.ch-brand__logo` (Task 7).
- `includes/bootstrap.php` — require `Collection_Meta` (Task 1).
- `blueworx-labs-clubhouse.php` + `tests/php/bootstrap.php` — require `Media` + `Collection_Meta_Boxes` (Tasks 3/4); init wiring fix (Task 5); version bump (Task 8).
- `tests/php/wp-stubs.php` — add `update_post_meta`, `add_meta_box`, `wp_verify_nonce`, `esc_attr`, `esc_html`, `esc_textarea`, `selected` (Task 4).
- `tests/php/FixtureProjectionTest.php` — label + guard assertions (Task 2).
- `CHANGELOG.md` (Task 8).

---

## Task 1: `Collection_Meta` — pure field definitions, sanitisers, columns

**Files:**
- Create: `includes/collections/class-collection-meta.php`
- Modify: `includes/bootstrap.php` (add require after `class-demo-collections.php`)
- Test: `tests/php/CollectionMetaTest.php`

**Interfaces:**
- Produces:
  - `Blueworx_Clubhouse_Collection_Meta::types(): array<int,string>` — the six CPT slugs.
  - `::fields(string $type): array<int,array{key:string,label:string,type:string,options?:array<int,string>,default?:string}>`
  - `::media_keys(string $type): array<int,string>`
  - `::columns(string $type): array<string,string>` — `[column-key => label]`.
  - `::sanitise(string $type, string $key, string $raw): string`
- Consumes: nothing (leaf unit).

- [ ] **Step 1: Write the failing test**

Create `tests/php/CollectionMetaTest.php`:

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CollectionMetaTest extends TestCase {

	public function test_types_are_the_six_cpts(): void {
		$this->assertSame(
			array( 'clubhouse_fixture', 'clubhouse_person', 'clubhouse_sponsor', 'clubhouse_sport', 'clubhouse_team', 'clubhouse_event' ),
			Blueworx_Clubhouse_Collection_Meta::types()
		);
	}

	public function test_fixture_fields_cover_the_meta_contract(): void {
		$keys = array_column( Blueworx_Clubhouse_Collection_Meta::fields( 'clubhouse_fixture' ), 'key' );
		$this->assertSame(
			array( 'sport', 'match_date', 'kickoff_time', 'venue', 'home_team', 'away_team', 'score', 'outcome', 'result_summary' ),
			$keys
		);
	}

	public function test_media_keys_are_image_typed_only(): void {
		$this->assertSame( array( 'image' ), Blueworx_Clubhouse_Collection_Meta::media_keys( 'clubhouse_sport' ) );
		$this->assertSame( array( 'image' ), Blueworx_Clubhouse_Collection_Meta::media_keys( 'clubhouse_team' ) );
		$this->assertSame( array(), Blueworx_Clubhouse_Collection_Meta::media_keys( 'clubhouse_fixture' ) );
	}

	public function test_date_sanitiser_accepts_iso_and_rejects_garbage(): void {
		$this->assertSame( '2026-07-12', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'match_date', '2026-07-12' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'match_date', '2026-13-40' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'match_date', 'not a date' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'match_date', '' ) );
	}

	public function test_time_sanitiser_is_strict_hhmm(): void {
		$this->assertSame( '14:30', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'kickoff_time', '14:30' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'kickoff_time', '25:99' ) );
	}

	public function test_outcome_select_allows_set_and_falls_back_to_default(): void {
		$this->assertSame( 'W', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'outcome', 'W' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'outcome', '' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_fixture', 'outcome', 'X' ) );
	}

	public function test_status_select_defaults_to_upcoming(): void {
		$this->assertSame( 'past', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'status', 'past' ) );
		$this->assertSame( 'upcoming', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'status', 'bogus' ) );
	}

	public function test_email_and_url_validate(): void {
		$this->assertSame( 'a@b.com', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_person', 'email', 'a@b.com' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_person', 'email', 'nope' ) );
		$this->assertSame( 'https://x.example', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sponsor', 'url', 'https://x.example' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sponsor', 'url', 'javascript:alert(1)' ) );
	}

	public function test_href_keeps_site_relative_but_blocks_javascript(): void {
		$this->assertSame( '?page=contact', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', '?page=contact' ) );
		$this->assertSame( 'https://x.example', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', 'https://x.example' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_event', 'cta_href', 'javascript:alert(1)' ) );
	}

	public function test_media_sanitises_to_positive_id_string(): void {
		$this->assertSame( '42', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sport', 'image', '42' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sport', 'image', '0' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sport', 'image', 'abc' ) );
	}

	public function test_text_strips_tags_and_collapses_whitespace(): void {
		$this->assertSame( 'Hello World', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sport', 'label', "  <b>Hello</b>\n  World  " ) );
	}

	public function test_unknown_key_returns_empty(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Collection_Meta::sanitise( 'clubhouse_sport', 'nope', 'x' ) );
	}

	public function test_columns_defined_for_each_type(): void {
		$this->assertSame( array( 'match_date', 'matchup', 'result' ), array_keys( Blueworx_Clubhouse_Collection_Meta::columns( 'clubhouse_fixture' ) ) );
		$this->assertNotEmpty( Blueworx_Clubhouse_Collection_Meta::columns( 'clubhouse_person' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter CollectionMetaTest`
Expected: FAIL with "Class 'Blueworx_Clubhouse_Collection_Meta' not found".

- [ ] **Step 3: Write minimal implementation**

Create `includes/collections/class-collection-meta.php`:

```php
<?php
// includes/collections/class-collection-meta.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for the six collections' editable fields: per-CPT field
 * definitions (key, label, input type, select options), pure sanitisers, media-key
 * lists, and admin list-column maps. Pure — no WordPress. Shared by the meta-box
 * glue (render + save), by WP_Collections (which keys are media), and asserted in
 * tests. The keys here match the mapper reads and the seeder writes exactly.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Meta {

	/** @var array<string,array<int,array{key:string,label:string,type:string,options?:array<int,string>,default?:string}>> */
	private const FIELDS = array(
		'clubhouse_fixture' => array(
			array( 'key' => 'sport',          'label' => 'Sport',        'type' => 'text' ),
			array( 'key' => 'match_date',     'label' => 'Match date',   'type' => 'date' ),
			array( 'key' => 'kickoff_time',   'label' => 'Kick-off',     'type' => 'time' ),
			array( 'key' => 'venue',          'label' => 'Venue',        'type' => 'text' ),
			array( 'key' => 'home_team',      'label' => 'Home team',    'type' => 'text' ),
			array( 'key' => 'away_team',      'label' => 'Away team',    'type' => 'text' ),
			array( 'key' => 'score',          'label' => 'Score',        'type' => 'text' ),
			array( 'key' => 'outcome',        'label' => 'Outcome',      'type' => 'select', 'options' => array( '', 'W', 'D', 'L' ), 'default' => '' ),
			array( 'key' => 'result_summary', 'label' => 'Result note',  'type' => 'text' ),
		),
		'clubhouse_person' => array(
			array( 'key' => 'committee_role', 'label' => 'Committee role', 'type' => 'text' ),
			array( 'key' => 'directory_role', 'label' => 'Directory role', 'type' => 'text' ),
			array( 'key' => 'email',          'label' => 'Email',          'type' => 'email' ),
		),
		'clubhouse_sponsor' => array(
			array( 'key' => 'url', 'label' => 'Website URL', 'type' => 'url' ),
		),
		'clubhouse_sport' => array(
			array( 'key' => 'label',       'label' => 'Short label',  'type' => 'text' ),
			array( 'key' => 'subtitle',    'label' => 'Subtitle',     'type' => 'text' ),
			array( 'key' => 'description', 'label' => 'Description',   'type' => 'textarea' ),
			array( 'key' => 'stat1_value', 'label' => 'Stat 1 value', 'type' => 'text' ),
			array( 'key' => 'stat1_label', 'label' => 'Stat 1 label', 'type' => 'text' ),
			array( 'key' => 'stat2_value', 'label' => 'Stat 2 value', 'type' => 'text' ),
			array( 'key' => 'stat2_label', 'label' => 'Stat 2 label', 'type' => 'text' ),
			array( 'key' => 'image',       'label' => 'Image',        'type' => 'media' ),
		),
		'clubhouse_team' => array(
			array( 'key' => 'sport',       'label' => 'Sport',       'type' => 'text' ),
			array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea' ),
			array( 'key' => 'match_day',   'label' => 'Match day',   'type' => 'text' ),
			array( 'key' => 'league',      'label' => 'League',      'type' => 'text' ),
			array( 'key' => 'image',       'label' => 'Image',       'type' => 'media' ),
		),
		'clubhouse_event' => array(
			array( 'key' => 'tag',       'label' => 'Tag',          'type' => 'text' ),
			array( 'key' => 'date',      'label' => 'Date label',   'type' => 'text' ),
			array( 'key' => 'detail',    'label' => 'Detail',       'type' => 'textarea' ),
			array( 'key' => 'cta_label', 'label' => 'Button label', 'type' => 'text' ),
			array( 'key' => 'cta_href',  'label' => 'Button link',  'type' => 'href' ),
			array( 'key' => 'status',    'label' => 'Status',       'type' => 'select', 'options' => array( 'upcoming', 'past' ), 'default' => 'upcoming' ),
		),
	);

	/**
	 * Admin list columns per type. Keys are column identifiers (not always a single
	 * meta key): 'matchup' and 'result' are composed by the glue from other meta.
	 *
	 * @var array<string,array<string,string>>
	 */
	private const COLUMNS = array(
		'clubhouse_fixture' => array( 'match_date' => 'Date', 'matchup' => 'Home v Away', 'result' => 'Result' ),
		'clubhouse_team'    => array( 'sport' => 'Sport', 'league' => 'League', 'match_day' => 'Match day' ),
		'clubhouse_person'  => array( 'committee_role' => 'Committee', 'directory_role' => 'Directory', 'email' => 'Email' ),
		'clubhouse_event'   => array( 'date' => 'Date', 'tag' => 'Tag', 'status' => 'Status' ),
		'clubhouse_sport'   => array( 'subtitle' => 'Subtitle', 'stat1_value' => 'Stat 1' ),
		'clubhouse_sponsor' => array( 'url' => 'URL' ),
	);

	/** @return array<int,string> */
	public static function types(): array {
		return array_keys( self::FIELDS );
	}

	/** @return array<int,array{key:string,label:string,type:string,options?:array<int,string>,default?:string}> */
	public static function fields( string $type ): array {
		return self::FIELDS[ $type ] ?? array();
	}

	/** @return array<int,string> */
	public static function media_keys( string $type ): array {
		$keys = array();
		foreach ( self::fields( $type ) as $field ) {
			if ( 'media' === $field['type'] ) {
				$keys[] = $field['key'];
			}
		}
		return $keys;
	}

	/** @return array<string,string> */
	public static function columns( string $type ): array {
		return self::COLUMNS[ $type ] ?? array();
	}

	public static function sanitise( string $type, string $key, string $raw ): string {
		$field = null;
		foreach ( self::fields( $type ) as $candidate ) {
			if ( $candidate['key'] === $key ) {
				$field = $candidate;
				break;
			}
		}
		if ( null === $field ) {
			return '';
		}
		switch ( $field['type'] ) {
			case 'textarea':
				return trim( strip_tags( $raw ) );
			case 'date':
				return self::valid_format( 'Y-m-d', $raw );
			case 'time':
				return self::valid_format( 'H:i', $raw );
			case 'email':
				$email = filter_var( trim( $raw ), FILTER_VALIDATE_EMAIL );
				return is_string( $email ) ? $email : '';
			case 'url':
				$url = filter_var( trim( $raw ), FILTER_VALIDATE_URL );
				return is_string( $url ) ? $url : '';
			case 'href':
				return self::href( $raw );
			case 'select':
				$options = $field['options'] ?? array();
				return in_array( $raw, $options, true ) ? $raw : (string) ( $field['default'] ?? '' );
			case 'media':
				$id = (int) $raw;
				return $id > 0 ? (string) $id : '';
			case 'text':
			default:
				return trim( (string) preg_replace( '/\s+/', ' ', strip_tags( $raw ) ) );
		}
	}

	/** Strict format check: accepts only input that round-trips through the format. */
	private static function valid_format( string $format, string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		$date = DateTimeImmutable::createFromFormat( '!' . $format, $raw );
		return ( false !== $date && $date->format( $format ) === $raw ) ? $raw : '';
	}

	/** Permissive link: keeps site-relative (?page=…, /path, #frag) and absolute URLs; blocks script schemes. */
	private static function href( string $raw ): string {
		$url = trim( strip_tags( $raw ) );
		if ( '' === $url || preg_match( '/^\s*(javascript|data|vbscript):/i', $url ) ) {
			return '';
		}
		return $url;
	}
}
```

- [ ] **Step 4: Wire the class into the runtime loader**

In `includes/bootstrap.php`, after the line `require_once __DIR__ . '/collections/class-demo-collections.php';`, add:

```php
require_once __DIR__ . '/collections/class-collection-meta.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter CollectionMetaTest`
Expected: PASS (13 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/collections/class-collection-meta.php includes/bootstrap.php tests/php/CollectionMetaTest.php
git commit -m "feat: Collection_Meta pure field definitions, sanitisers and columns"
```

---

## Task 2: `Fixture_Projection` robustness — `Y-m` grouping + malformed-date guard

**Files:**
- Modify: `includes/render/class-fixture-projection.php`
- Test: `tests/php/FixtureProjectionTest.php` (update one assertion, add two tests)

**Interfaces:**
- Consumes: nothing new.
- Produces: unchanged public signatures (`home_fixtures`, `home_results`, `calendar_months`); behaviour changes only — calendar labels become `F Y`, undated fixtures are omitted from the Home tabs and bucketed under "Date TBC" on the calendar.

- [ ] **Step 1: Update the existing calendar label assertion and add the new tests**

In `tests/php/FixtureProjectionTest.php`, change the label assertion in `test_calendar_groups_by_month_with_detail` from:

```php
		$this->assertSame( array( 'July', 'June' ), $labels );
```

to:

```php
		$this->assertSame( array( 'July 2026', 'June 2026' ), $labels );
```

Then append these tests before the closing brace:

```php
	public function test_calendar_groups_by_year_and_month_across_years(): void {
		$fixtures = array(
			$this->fixture( '2026-01-10' ),
			$this->fixture( '2025-01-20' ),
			$this->fixture( '2025-12-05' ),
		);
		$labels = array_column( Blueworx_Clubhouse_Fixture_Projection::calendar_months( $fixtures ), 'label' );
		// Newest-first, and 2025 January stays separate from 2026 January.
		$this->assertSame( array( 'January 2026', 'December 2025', 'January 2025' ), $labels );
	}

	public function test_malformed_dates_do_not_resolve_to_now(): void {
		$fixtures = array( $this->fixture( '' ), $this->fixture( 'garbage' ), $this->fixture( '2026-05-01' ) );

		// Undated fixtures are omitted from the date-ranked Home tabs.
		$upcoming = Blueworx_Clubhouse_Fixture_Projection::home_fixtures( $fixtures, 10 );
		$this->assertCount( 1, $upcoming );
		$this->assertSame( 'MAY', $upcoming[0]['month'] );

		// The calendar surfaces undated fixtures under a "Date TBC" bucket, ordered last.
		$labels = array_column( Blueworx_Clubhouse_Fixture_Projection::calendar_months( $fixtures ), 'label' );
		$this->assertSame( array( 'May 2026', 'Date TBC' ), $labels );
		$tbc = Blueworx_Clubhouse_Fixture_Projection::calendar_months( $fixtures )[1]['rows'];
		$this->assertSame( 'TBC', $tbc[0]['date'] );
	}

	/** @return array<string,mixed> A minimal upcoming fixture with the given match_date. */
	private function fixture( string $match_date ): array {
		return array(
			'sport' => 'Rugby', 'match_date' => $match_date, 'kickoff_time' => '14:00',
			'venue' => 'Home', 'home' => 'ClubHouse', 'away' => 'Rivals',
			'score' => '', 'outcome' => '', 'result_summary' => '',
		);
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter FixtureProjectionTest`
Expected: FAIL — the label assertion now expects `July 2026`, and the two new tests fail (undated fixtures currently resolve to "now").

- [ ] **Step 3: Implement the guard and `Y-m` grouping**

In `includes/render/class-fixture-projection.php`, replace the `date()` helper (lines 18-20) with a nullable strict parser:

```php
	/** Strict parse; null for empty/invalid input so a bad date never becomes "now". */
	private static function try_date( string $iso ): ?DateTimeImmutable {
		$iso = trim( $iso );
		if ( '' === $iso ) {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $iso );
		return ( false !== $date && $date->format( 'Y-m-d' ) === $iso ) ? $date : null;
	}
```

Replace `home_fixtures` (skip undated in the map) so its body becomes:

```php
	/** @param array<int,array<string,mixed>> $fixtures @return array<int,array<string,string>> */
	public static function home_fixtures( array $fixtures, int $limit = 3 ): array {
		$upcoming = array_values( array_filter( $fixtures, static fn( $f ) => '' === $f['outcome'] && null !== self::try_date( $f['match_date'] ) ) );
		usort( $upcoming, static fn( $a, $b ) => strcmp( $a['match_date'], $b['match_date'] ) );
		$upcoming = array_slice( $upcoming, 0, $limit );
		return array_map(
			static function ( array $f ): array {
				$d = self::try_date( $f['match_date'] );
				return array(
					'month'       => strtoupper( $d->format( 'M' ) ),
					'day'         => $d->format( 'j' ),
					'competition' => $f['sport'],
					'time'        => $f['kickoff_time'],
					'matchup'     => $f['home'] . ' vs ' . $f['away'],
				);
			},
			$upcoming
		);
	}
```

Replace `home_results` similarly (add the `null !== self::try_date(...)` filter and use `try_date` in the map):

```php
	/** @param array<int,array<string,mixed>> $fixtures @return array<int,array<string,string>> */
	public static function home_results( array $fixtures, int $limit = 3 ): array {
		$played = array_values( array_filter( $fixtures, static fn( $f ) => '' !== $f['outcome'] && null !== self::try_date( $f['match_date'] ) ) );
		usort( $played, static fn( $a, $b ) => strcmp( $b['match_date'], $a['match_date'] ) );
		$played = array_slice( $played, 0, $limit );
		return array_map(
			static function ( array $f ): array {
				$d = self::try_date( $f['match_date'] );
				return array(
					'date'    => strtoupper( $d->format( 'M' ) ) . ' ' . $d->format( 'j' ),
					'home'    => $f['home'],
					'away'    => $f['away'],
					'score'   => $f['score'],
					'outcome' => $f['outcome'],
				);
			},
			$played
		);
	}
```

Replace `calendar_months` to group by a `Y-m` sort key (undated → a `'~'` key that sorts last), emit an `F Y` (or "Date TBC") label, and render `TBC` for undated rows:

```php
	/** @param array<int,array<string,mixed>> $fixtures @return array<int,array{label:string,rows:array<int,array<string,string>>}> */
	public static function calendar_months( array $fixtures ): array {
		$sorted = $fixtures;
		usort( $sorted, static fn( $a, $b ) => strcmp( $b['match_date'], $a['match_date'] ) );
		$groups = array();
		$labels = array();
		$order  = array();
		foreach ( $sorted as $f ) {
			$date = self::try_date( $f['match_date'] );
			// '~' sorts after any 'YYYY-MM' so undated fixtures land last.
			$sort_key = null !== $date ? $date->format( 'Y-m' ) : '~';
			$label    = null !== $date ? $date->format( 'F Y' ) : 'Date TBC';
			if ( ! isset( $groups[ $sort_key ] ) ) {
				$groups[ $sort_key ] = array();
				$labels[ $sort_key ] = $label;
				$order[]             = $sort_key;
			}
			$detail = '' === $f['outcome']
				? $f['venue'] . ' · ' . $f['kickoff_time']
				: $f['result_summary'];
			$groups[ $sort_key ][] = array(
				'date'        => null !== $date ? $date->format( 'D j' ) : 'TBC',
				'competition' => $f['sport'],
				'matchup'     => $f['home'] . ' vs ' . $f['away'],
				'detail'      => $detail,
				'outcome'     => $f['outcome'],
			);
		}
		rsort( $order ); // 'YYYY-MM' descending; '~' (undated) sorts to the end.
		$out = array();
		foreach ( $order as $sort_key ) {
			$out[] = array( 'label' => $labels[ $sort_key ], 'rows' => $groups[ $sort_key ] );
		}
		return $out;
	}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter FixtureProjectionTest`
Expected: PASS (5 tests). Then run the full suite to confirm no calendar consumers regressed: `./vendor/bin/phpunit`
Expected: PASS (existing calendar page tests only assert the `ch-cal` hook, not the label text).

- [ ] **Step 5: Commit**

```bash
git add includes/render/class-fixture-projection.php tests/php/FixtureProjectionTest.php
git commit -m "fix: Fixture_Projection groups by Y-m and guards malformed match_date"
```

---

## Task 3: `Media` helper + `WP_Collections` image resolution

**Files:**
- Create: `includes/collections/class-media.php`
- Modify: `includes/collections/class-wp-collections.php`
- Modify: `blueworx-labs-clubhouse.php` (require `Media`), `tests/php/bootstrap.php` (require `Media`)
- Test: `tests/php/MediaTest.php`, `tests/php/WpCollectionsTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Collection_Meta::media_keys()` (Task 1); the `wp_get_attachment_image_url` stub (already in `wp-stubs.php`).
- Produces: `Blueworx_Clubhouse_Media::url(int $id): string` — `''` for a missing/deleted/non-image attachment.

- [ ] **Step 1: Write the failing tests**

Create `tests/php/MediaTest.php`:

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class MediaTest extends TestCase {

	public function test_positive_id_resolves_to_a_url(): void {
		// The wp-stubs stub returns https://club.test/wp-content/uploads/att-{id}.png for a truthy id.
		$this->assertSame( 'https://club.test/wp-content/uploads/att-42.png', Blueworx_Clubhouse_Media::url( 42 ) );
	}

	public function test_zero_or_negative_id_is_empty(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Media::url( 0 ) );
		$this->assertSame( '', Blueworx_Clubhouse_Media::url( -5 ) );
	}
}
```

Create `tests/php/WpCollectionsTest.php`:

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class WpCollectionsTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_sport_image_id_is_resolved_to_a_url(): void {
		$post = (object) array( 'ID' => 7, 'post_title' => 'Rugby', 'post_type' => 'clubhouse_sport' );
		$GLOBALS['wp_stub_posts']['clubhouse_sport'] = array( $post );
		$GLOBALS['wp_stub_postmeta'][7] = array( 'label' => 'RUG', 'image' => '42' );

		$sports = ( new Blueworx_Clubhouse_WP_Collections() )->sports();

		$this->assertCount( 1, $sports );
		$this->assertSame( 'Rugby', $sports[0]['title'] );
		$this->assertSame( 'https://club.test/wp-content/uploads/att-42.png', $sports[0]['image'] );
	}

	public function test_empty_image_stays_empty(): void {
		$post = (object) array( 'ID' => 8, 'post_title' => 'Cricket', 'post_type' => 'clubhouse_sport' );
		$GLOBALS['wp_stub_posts']['clubhouse_sport'] = array( $post );
		$GLOBALS['wp_stub_postmeta'][8] = array( 'image' => '' );

		$sports = ( new Blueworx_Clubhouse_WP_Collections() )->sports();
		$this->assertSame( '', $sports[0]['image'] );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter MediaTest`
Expected: FAIL with "Class 'Blueworx_Clubhouse_Media' not found".

- [ ] **Step 3: Create the `Media` helper**

Create `includes/collections/class-media.php`:

```php
<?php
// includes/collections/class-media.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a WordPress attachment ID to a full-size image URL. Thin WP glue used by
 * WP_Collections (collection image fields) and Frontend (the header logo), so the
 * pure render path only ever receives a URL string. Returns '' for a missing,
 * deleted, or non-image attachment, which degrades to the renderer's empty-media
 * placeholder rather than a broken src.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Media {

	public static function url( int $id ): string {
		if ( $id <= 0 ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, 'full' );
		return is_string( $url ) ? $url : '';
	}
}
```

- [ ] **Step 4: Resolve media keys in `WP_Collections`**

In `includes/collections/class-wp-collections.php`, replace the `fetch()` method body so it resolves media-typed meta after flattening:

```php
	/** @param callable(array):array $mapper */
	private function fetch( string $post_type, callable $mapper ): array {
		$posts = get_posts( array(
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		) );
		$media_keys = Blueworx_Clubhouse_Collection_Meta::media_keys( $post_type );
		$out        = array();
		foreach ( $posts as $post ) {
			$id   = is_object( $post ) ? $post->ID : (int) $post;
			$meta = self::flatten_meta( $id );
			foreach ( $media_keys as $key ) {
				if ( isset( $meta[ $key ] ) && ctype_digit( $meta[ $key ] ) ) {
					$meta[ $key ] = Blueworx_Clubhouse_Media::url( (int) $meta[ $key ] );
				}
			}
			$out[] = $mapper( array(
				'title' => get_the_title( $post ),
				'meta'  => $meta,
			) );
		}
		return $out;
	}
```

(The `ctype_digit` guard leaves an already-empty or legacy-URL value untouched, so nothing breaks for pre-existing data.)

- [ ] **Step 5: Require `Media` in both loaders**

In `blueworx-labs-clubhouse.php`, after `require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-collection-mappers.php';` add:

```php
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-media.php';
```

In `tests/php/bootstrap.php`, after `require_once dirname( __DIR__, 2 ) . '/includes/collections/class-collection-mappers.php';` add:

```php
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-media.php';
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter MediaTest && ./vendor/bin/phpunit --filter WpCollectionsTest`
Expected: PASS (2 + 2 tests).

- [ ] **Step 7: Commit**

```bash
git add includes/collections/class-media.php includes/collections/class-wp-collections.php blueworx-labs-clubhouse.php tests/php/bootstrap.php tests/php/MediaTest.php tests/php/WpCollectionsTest.php
git commit -m "feat: Media helper resolves attachment ids; WP_Collections resolves image meta"
```

---

## Task 4: `Collection_Meta_Boxes` — meta-box render, save, and list columns

**Files:**
- Create: `includes/collections/class-collection-meta-boxes.php`
- Create: `assets/js/admin-collections.js`
- Modify: `tests/php/wp-stubs.php` (add `update_post_meta`, `add_meta_box`, `wp_verify_nonce`, `esc_attr`, `esc_html`, `esc_textarea`, `selected`)
- Modify: `blueworx-labs-clubhouse.php` + `tests/php/bootstrap.php` (require the class)
- Test: `tests/php/CollectionMetaBoxesTest.php`

**Interfaces:**
- Consumes: `Collection_Meta::types()/fields()/columns()/sanitise()` (Task 1); the new stubs.
- Produces:
  - `Blueworx_Clubhouse_Collection_Meta_Boxes::register(): void`
  - `::box_html(string $type, int $post_id): string`
  - `::merge_columns(string $type, array $cols): array` (pure)
  - `::column_value(string $type, string $col, int $post_id): string`
  - `::save(int $post_id, object $post): void`

- [ ] **Step 1: Add the WordPress stubs the glue needs**

In `tests/php/wp-stubs.php`, before the closing `?>`-less end of file (after the `wp_unslash` block), add:

```php
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $id, string $key, $value ) {
		$GLOBALS['wp_stub_postmeta'][ $id ][ $key ] = $value;
		wp_stub_record( 'update_post_meta', array( $id, $key, $value ) );
		return true;
	}
}
if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box( ...$a ) { wp_stub_record( 'add_meta_box', $a ); }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) { wp_stub_record( 'wp_verify_nonce', array( $nonce, $action ) ); return 1; }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'selected' ) ) {
	function selected( $a, $b = true, $echo = true ) {
		$r = ( (string) $a === (string) $b ) ? ' selected="selected"' : '';
		if ( $echo ) { echo $r; }
		return $r;
	}
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/php/CollectionMetaBoxesTest.php`:

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CollectionMetaBoxesTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_box_html_renders_a_control_per_field_with_nonce(): void {
		$html = Blueworx_Clubhouse_Collection_Meta_Boxes::box_html( 'clubhouse_fixture', 0 );
		$this->assertStringContainsString( 'name="_clubhouse_meta_nonce"', $html );
		$this->assertStringContainsString( 'name="clubhouse_meta[match_date]"', $html );
		$this->assertStringContainsString( 'type="date"', $html );
		$this->assertStringContainsString( 'type="time"', $html );
		$this->assertStringContainsString( '<select', $html ); // outcome
	}

	public function test_box_html_escapes_stored_values(): void {
		$GLOBALS['wp_stub_postmeta'][5] = array( 'venue' => '"><script>x</script>' );
		$html = Blueworx_Clubhouse_Collection_Meta_Boxes::box_html( 'clubhouse_fixture', 5 );
		$this->assertStringNotContainsString( '<script>x', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_save_sanitises_and_persists_each_field(): void {
		$post = (object) array( 'ID' => 11, 'post_type' => 'clubhouse_fixture' );
		$_POST['_clubhouse_meta_nonce'] = 'stub';
		$_POST['clubhouse_meta'] = array(
			'match_date' => '2026-08-01',
			'outcome'    => 'BOGUS',
			'home_team'  => '  Alpha  ',
		);

		Blueworx_Clubhouse_Collection_Meta_Boxes::save( 11, $post );

		$this->assertSame( '2026-08-01', $GLOBALS['wp_stub_postmeta'][11]['match_date'] );
		$this->assertSame( '', $GLOBALS['wp_stub_postmeta'][11]['outcome'] );      // rejected → default
		$this->assertSame( 'Alpha', $GLOBALS['wp_stub_postmeta'][11]['home_team'] ); // trimmed

		unset( $_POST['_clubhouse_meta_nonce'], $_POST['clubhouse_meta'] );
	}

	public function test_save_ignores_posts_without_the_nonce(): void {
		$post = (object) array( 'ID' => 12, 'post_type' => 'clubhouse_fixture' );
		Blueworx_Clubhouse_Collection_Meta_Boxes::save( 12, $post );
		$this->assertArrayNotHasKey( 12, $GLOBALS['wp_stub_postmeta'] );
	}

	public function test_merge_columns_inserts_our_columns_between_title_and_date(): void {
		$cols = array( 'cb' => '<input>', 'title' => 'Title', 'date' => 'Date' );
		$merged = Blueworx_Clubhouse_Collection_Meta_Boxes::merge_columns( 'clubhouse_fixture', $cols );
		$this->assertSame(
			array( 'cb', 'title', 'clubhouse_match_date', 'clubhouse_matchup', 'clubhouse_result', 'date' ),
			array_keys( $merged )
		);
	}

	public function test_column_value_composes_fixture_matchup_and_result(): void {
		$GLOBALS['wp_stub_postmeta'][20] = array( 'home_team' => 'Alpha', 'away_team' => 'Beta', 'score' => '2-1', 'outcome' => 'W' );
		$this->assertSame( 'Alpha v Beta', Blueworx_Clubhouse_Collection_Meta_Boxes::column_value( 'clubhouse_fixture', 'clubhouse_matchup', 20 ) );
		$this->assertSame( '2-1 (W)', Blueworx_Clubhouse_Collection_Meta_Boxes::column_value( 'clubhouse_fixture', 'clubhouse_result', 20 ) );
	}

	public function test_register_adds_meta_box_and_column_hooks(): void {
		Blueworx_Clubhouse_Collection_Meta_Boxes::register();
		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$filters = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_filter' ) );
		$this->assertContains( 'add_meta_boxes', $actions );
		$this->assertContains( 'save_post', $actions );
		$this->assertContains( 'manage_clubhouse_fixture_posts_columns', $filters );
	}
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter CollectionMetaBoxesTest`
Expected: FAIL with "Class 'Blueworx_Clubhouse_Collection_Meta_Boxes' not found".

- [ ] **Step 4: Implement the glue**

Create `includes/collections/class-collection-meta-boxes.php`:

```php
<?php
// includes/collections/class-collection-meta-boxes.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress glue that puts the six collections' editable fields on their native
 * post-edit screens: a "Details" meta-box per CPT (rendered and saved through the
 * pure Collection_Meta), plus admin list columns. Nonce- and capability-checked on
 * save; every value sanitised through Collection_Meta and escaped on output.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Meta_Boxes {

	private const NONCE  = 'clubhouse_meta_save';
	private const PREFIX = 'clubhouse_';

	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
		add_action( 'save_post', array( self::class, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", static function ( $cols ) use ( $type ) {
				return self::merge_columns( $type, is_array( $cols ) ? $cols : array() );
			} );
			add_action( "manage_{$type}_posts_custom_column", static function ( $col, $post_id ) use ( $type ) {
				echo self::column_value( $type, (string) $col, (int) $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in column_value.
			}, 10, 2 );
		}
	}

	public static function add(): void {
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			add_meta_box( 'clubhouse_meta_' . $type, 'Details', array( self::class, 'render' ), $type, 'normal', 'high' );
		}
	}

	public static function render( $post ): void {
		$type    = is_object( $post ) ? (string) $post->post_type : '';
		$post_id = is_object( $post ) ? (int) $post->ID : 0;
		echo self::box_html( $type, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within box_html.
	}

	public static function box_html( string $type, int $post_id ): string {
		$html = wp_nonce_field( self::NONCE, '_clubhouse_meta_nonce', true, false );
		foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
			$value = (string) get_post_meta( $post_id, $field['key'], true );
			$html .= self::field_html( $field, $value );
		}
		return '<div class="clubhouse-meta">' . $html . '</div>';
	}

	/** @param array{key:string,label:string,type:string,options?:array<int,string>} $field */
	private static function field_html( array $field, string $value ): string {
		$id    = 'clubhouse_meta_' . $field['key'];
		$name  = 'clubhouse_meta[' . $field['key'] . ']';
		$label = '<label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $field['label'] ) . '</strong></label>';

		switch ( $field['type'] ) {
			case 'textarea':
				$control = '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" class="widefat">' . esc_textarea( $value ) . '</textarea>';
				break;
			case 'select':
				$options = '';
				foreach ( ( $field['options'] ?? array() ) as $opt ) {
					$options .= '<option value="' . esc_attr( $opt ) . '"' . selected( $value, $opt, false ) . '>' . esc_html( '' === $opt ? '—' : $opt ) . '</option>';
				}
				$control = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . $options . '</select>';
				break;
			case 'media':
				$preview = ( '' !== $value && ctype_digit( $value ) ) ? (string) wp_get_attachment_image_url( (int) $value, 'thumbnail' ) : '';
				$hidden  = '' === $preview ? ' style="display:none"' : '';
				$control = '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">'
					. '<img class="clubhouse-meta__preview" src="' . esc_attr( $preview ) . '" alt=""' . $hidden . '>'
					. '<button type="button" class="button clubhouse-meta__pick" data-target="' . esc_attr( $id ) . '">Choose image</button> '
					. '<button type="button" class="button clubhouse-meta__clear" data-target="' . esc_attr( $id ) . '">Remove</button>';
				break;
			case 'date':
			case 'time':
			case 'email':
				$control = '<input type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
				break;
			case 'url':
				$control = '<input type="url" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
				break;
			case 'text':
			case 'href':
			default:
				$control = '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
		}
		return '<p class="clubhouse-meta__row">' . $label . '<br>' . $control . '</p>';
	}

	public static function save( int $post_id, $post ): void {
		if ( ! isset( $_POST['_clubhouse_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_clubhouse_meta_nonce'] ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$type = is_object( $post ) ? (string) $post->post_type : '';
		if ( ! in_array( $type, Blueworx_Clubhouse_Collection_Meta::types(), true ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = ( isset( $_POST['clubhouse_meta'] ) && is_array( $_POST['clubhouse_meta'] ) ) ? wp_unslash( $_POST['clubhouse_meta'] ) : array();
		foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
			$value = isset( $raw[ $field['key'] ] ) ? (string) $raw[ $field['key'] ] : '';
			update_post_meta( $post_id, $field['key'], Blueworx_Clubhouse_Collection_Meta::sanitise( $type, $field['key'], $value ) );
		}
	}

	/** @param array<string,string> $cols @return array<string,string> */
	public static function merge_columns( string $type, array $cols ): array {
		$out = array();
		if ( isset( $cols['cb'] ) ) {
			$out['cb'] = $cols['cb'];
		}
		if ( isset( $cols['title'] ) ) {
			$out['title'] = $cols['title'];
		}
		foreach ( Blueworx_Clubhouse_Collection_Meta::columns( $type ) as $key => $col_label ) {
			$out[ self::PREFIX . $key ] = $col_label;
		}
		if ( isset( $cols['date'] ) ) {
			$out['date'] = $cols['date'];
		}
		return $out;
	}

	public static function column_value( string $type, string $col, int $post_id ): string {
		if ( 0 !== strpos( $col, self::PREFIX ) ) {
			return '';
		}
		$key = substr( $col, strlen( self::PREFIX ) );
		if ( 'clubhouse_fixture' === $type && 'matchup' === $key ) {
			return self::e( (string) get_post_meta( $post_id, 'home_team', true ) . ' v ' . (string) get_post_meta( $post_id, 'away_team', true ) );
		}
		if ( 'clubhouse_fixture' === $type && 'result' === $key ) {
			$score   = (string) get_post_meta( $post_id, 'score', true );
			$outcome = (string) get_post_meta( $post_id, 'outcome', true );
			return self::e( trim( $score . ( '' !== $outcome ? ' (' . $outcome . ')' : '' ) ) );
		}
		return self::e( (string) get_post_meta( $post_id, $key, true ) );
	}

	public static function enqueue( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'clubhouse-admin-collections', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/admin-collections.js', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	private static function e( string $s ): string {
		return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
	}
}
```

- [ ] **Step 5: Create the media-picker script**

Create `assets/js/admin-collections.js`:

```js
/* Clubhouse collection meta-boxes: wp.media image picker for hidden-id fields. */
( function () {
	document.addEventListener( 'click', function ( e ) {
		var t = e.target;
		if ( ! t || ! t.classList ) {
			return;
		}
		if ( t.classList.contains( 'clubhouse-meta__pick' ) ) {
			e.preventDefault();
			var target = document.getElementById( t.getAttribute( 'data-target' ) );
			if ( ! target || ! window.wp || ! window.wp.media ) {
				return;
			}
			var frame = window.wp.media( { title: 'Choose image', multiple: false } );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				target.value = att.id;
				var img = target.parentNode.querySelector( '.clubhouse-meta__preview' );
				if ( img ) {
					img.src = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
					img.style.display = '';
				}
			} );
			frame.open();
		}
		if ( t.classList.contains( 'clubhouse-meta__clear' ) ) {
			e.preventDefault();
			var tgt = document.getElementById( t.getAttribute( 'data-target' ) );
			if ( ! tgt ) {
				return;
			}
			tgt.value = '';
			var im = tgt.parentNode.querySelector( '.clubhouse-meta__preview' );
			if ( im ) {
				im.style.display = 'none';
			}
		}
	} );
} )();
```

- [ ] **Step 6: Require the class in both loaders**

In `blueworx-labs-clubhouse.php`, after the `class-collection-seeder.php` require add:

```php
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/collections/class-collection-meta-boxes.php';
```

In `tests/php/bootstrap.php`, after the `class-collection-seeder.php` require add:

```php
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-collection-meta-boxes.php';
```

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter CollectionMetaBoxesTest`
Expected: PASS (7 tests).

- [ ] **Step 8: Commit**

```bash
git add includes/collections/class-collection-meta-boxes.php assets/js/admin-collections.js tests/php/wp-stubs.php blueworx-labs-clubhouse.php tests/php/bootstrap.php tests/php/CollectionMetaBoxesTest.php
git commit -m "feat: Collection_Meta_Boxes native CPT editing and admin list columns"
```

---

## Task 5: Fix admin registration wiring (Phase-2 Setup bug + new meta-boxes)

**Context:** `Blueworx_Clubhouse_Setup_Controller::register()` is defined but never called in production — `blueworx_labs_clubhouse_init()` only calls `Frontend::register()`. So the Phase-2 Clubhouse Setup menu never appears on a real install. This task wires it and the new meta-boxes.

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (the `blueworx_labs_clubhouse_init` function)

**Interfaces:**
- Consumes: `Setup_Controller::register()`, `Collection_Meta_Boxes::register()`.
- Produces: nothing new (wiring only).

- [ ] **Step 1: Confirm the gap**

Run: `grep -n "Setup_Controller::register\|Collection_Meta_Boxes::register" blueworx-labs-clubhouse.php`
Expected: no matches (proves the wiring is missing before the fix).

- [ ] **Step 2: Add the registration calls**

In `blueworx-labs-clubhouse.php`, replace the `blueworx_labs_clubhouse_init` function body:

```php
function blueworx_labs_clubhouse_init() {
	Blueworx_Clubhouse_Frontend::register();
	Blueworx_Clubhouse_Setup_Controller::register();
	Blueworx_Clubhouse_Collection_Meta_Boxes::register();
}
```

- [ ] **Step 3: Verify the wiring is present and the suite is still green**

Run: `grep -n "Setup_Controller::register\|Collection_Meta_Boxes::register" blueworx-labs-clubhouse.php`
Expected: both lines now present.

Run: `./vendor/bin/phpunit`
Expected: PASS (whole suite — this change is plugin-file wiring, not covered by unit tests; the guarantee that each `register()` adds the right hooks is already covered by `SetupControllerTest` and `CollectionMetaBoxesTest::test_register_adds_meta_box_and_column_hooks`).

> **Manual-smoke note:** this fix is only observable at runtime. The Phase 3 manual WP smoke MUST confirm **Clubhouse → Setup** appears in the admin menu after activation — that is the regression check for this bug.

- [ ] **Step 4: Commit**

```bash
git add blueworx-labs-clubhouse.php
git commit -m "fix: register Setup_Controller (Phase-2 menu never wired) and meta-boxes on init"
```

---

## Task 6: Header logo slot + hidden-page nav/footer omission (pure render + threading)

**Files:**
- Modify: `includes/render/class-sections.php` (`header()` logo slot)
- Modify: `includes/render/class-page-renderer.php` (`shell_header`/`shell_footer` + 9 page methods)
- Modify: `includes/render/class-page-map.php` (`render()` optional `$logo_url`)
- Test: `tests/php/SectionsTest.php`, `tests/php/PageRendererTest.php`

**Interfaces:**
- Consumes: `Visibility::is_page_visible()` (existing).
- Produces:
  - `Sections::header()` accepts an optional `logo` key (URL string).
  - `Page_Renderer::home()/about()/membership()/contact()/login()/sports()/teams()/events()/calendar()` gain an optional trailing `string $logo_url = ''`.
  - `Page_Map::render(..., string $logo_url = '')`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/SectionsTest.php` (inside the class):

```php
	public function test_header_renders_logo_image_when_a_url_is_given(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name' => 'ClubHouse', 'banner' => '', 'banner_href' => '',
			'nav' => array( array( 'label' => 'Home', 'href' => '?page=home' ) ),
			'active' => '?page=home', 'login' => 'Log in', 'login_href' => '?page=login',
			'join' => 'Join', 'join_href' => '?page=membership',
			'logo' => 'https://club.test/logo.png',
		) );
		$this->assertStringContainsString( '<img class="ch-brand__logo" src="https://club.test/logo.png" alt="ClubHouse">', $html );
		$this->assertStringContainsString( 'ClubHouse', $html ); // name text kept beside the logo
		$this->assertStringNotContainsString( 'ch-brand__mark', $html );
	}

	public function test_header_falls_back_to_the_mark_glyph_without_a_logo(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name' => 'ClubHouse', 'banner' => '', 'banner_href' => '',
			'nav' => array( array( 'label' => 'Home', 'href' => '?page=home' ) ),
			'active' => '?page=home', 'login' => 'Log in', 'login_href' => '?page=login',
			'join' => 'Join', 'join_href' => '?page=membership',
		) );
		$this->assertStringContainsString( 'ch-brand__mark', $html );
		$this->assertStringNotContainsString( 'ch-brand__logo', $html );
	}
```

Append to `tests/php/PageRendererTest.php` (inside the class — reuse its existing `branding()`/`collections()` helpers):

```php
	public function test_hidden_page_is_omitted_from_nav_and_footer(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_page_visible( 'about', false );

		$body = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $vis, $this->collections() );

		$this->assertStringNotContainsString( 'href="?page=about"', $body );
		$this->assertStringContainsString( 'href="?page=sports"', $body ); // still-visible page remains
	}

	public function test_home_renders_the_logo_when_threaded(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $vis, $this->collections(), 'https://club.test/logo.png' );
		$this->assertStringContainsString( 'ch-brand__logo', $body );
		$this->assertStringContainsString( 'src="https://club.test/logo.png"', $body );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter SectionsTest && ./vendor/bin/phpunit --filter PageRendererTest`
Expected: FAIL — header has no logo slot; `home()` rejects a 4th argument / does not filter nav.

- [ ] **Step 3: Add the logo slot to `Sections::header`**

In `includes/render/class-sections.php`, in `header()`, replace the brand-lockup line:

```php
			. '<a class="ch-brand" href="?page=home"><span class="ch-brand__mark">C</span>' . self::e( $data['club_name'] ) . '</a>'
```

with:

```php
			. '<a class="ch-brand" href="?page=home">' . self::brand_mark( $data ) . self::e( $data['club_name'] ) . '</a>'
```

And add this private helper to the class (e.g. after the `media()` helper):

```php
	/** @param array{club_name:string,logo?:string} $data */
	private static function brand_mark( array $data ): string {
		$logo = $data['logo'] ?? '';
		return '' !== $logo
			? '<img class="ch-brand__logo" src="' . self::e( $logo ) . '" alt="' . self::e( $data['club_name'] ) . '">'
			: '<span class="ch-brand__mark">C</span>';
	}
```

Also update the `header()` docblock `@param` to include `logo?:string`.

- [ ] **Step 4: Thread logo + visibility through `shell_header`/`shell_footer`**

In `includes/render/class-page-renderer.php`, change the `shell_header` signature and build a visibility-filtered nav + logo:

```php
	private static function shell_header( string $club, string $active, Blueworx_Clubhouse_Visibility $visibility, string $logo_url = '' ): string {
		$nav = array(
			array( 'label' => 'Home', 'href' => '?page=home' ),
			array( 'label' => 'About', 'href' => '?page=about' ),
			array( 'label' => 'Sports', 'href' => '?page=sports' ),
			array( 'label' => 'Teams', 'href' => '?page=teams' ),
			array( 'label' => 'Membership', 'href' => '?page=membership' ),
			array( 'label' => 'Events', 'href' => '?page=events' ),
			array( 'label' => 'Calendar', 'href' => '?page=calendar' ),
			array( 'label' => 'Contact', 'href' => '?page=contact' ),
		);
		return Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => $club,
			'banner'      => 'Summer sign-ups are open — register your interest for 2026/27 →',
			'banner_href' => '?page=membership',
			'nav'         => self::visible_nav( $nav, $visibility ),
			'active'      => $active,
			'login'       => 'Log in',
			'login_href'  => '?page=login',
			'join'        => 'Join the Club',
			'join_href'   => '?page=membership',
			'logo'        => $logo_url,
		) );
	}
```

Change `shell_footer` to accept `Visibility` and filter each column's links:

```php
	private static function shell_footer( string $club, Blueworx_Clubhouse_Visibility $visibility ): string {
		return Blueworx_Clubhouse_Sections::footer( array(
			'club_name'  => $club,
			'tagline'    => 'Nine sports, one club. A home ground for every team, and everyone who follows them.',
			'socials'    => array( 'Facebook', 'Instagram', 'Community', 'Share' ),
			'columns'    => array(
				array( 'title' => 'Club', 'links' => self::visible_nav( array(
					array( 'label' => 'About', 'href' => '?page=about' ),
					array( 'label' => 'Sports', 'href' => '?page=sports' ),
					array( 'label' => 'Teams', 'href' => '?page=teams' ),
					array( 'label' => 'Events', 'href' => '?page=events' ),
				), $visibility ) ),
				array( 'title' => 'Get involved', 'links' => self::visible_nav( array(
					array( 'label' => 'Membership', 'href' => '?page=membership' ),
					array( 'label' => 'Calendar', 'href' => '?page=calendar' ),
					array( 'label' => 'Volunteer', 'href' => '?page=contact' ),
					array( 'label' => 'Contact', 'href' => '?page=contact' ),
				), $visibility ) ),
			),
			'newsletter' => array(
				'heading'     => 'Stay in the loop',
				'lede'        => 'Fixtures, results and club news — one email a month.',
				'placeholder' => 'Your email',
				'cta'         => 'Subscribe',
			),
			'legal'      => array(
				array( 'label' => 'Privacy Policy', 'href' => '#' ),
				array( 'label' => 'Terms', 'href' => '#' ),
				array( 'label' => 'Club Rules', 'href' => '#' ),
				array( 'label' => 'Safeguarding', 'href' => '#' ),
			),
		) );
	}

	/**
	 * Drop nav/footer links whose target page is hidden. Non-page hrefs
	 * (e.g. '#', 'mailto:') are never filtered.
	 *
	 * @param array<int,array{label:string,href:string}> $items
	 * @return array<int,array{label:string,href:string}>
	 */
	private static function visible_nav( array $items, Blueworx_Clubhouse_Visibility $visibility ): array {
		return array_values( array_filter(
			$items,
			static function ( array $item ) use ( $visibility ): bool {
				$slug = self::href_page( $item['href'] );
				return null === $slug || $visibility->is_page_visible( $slug );
			}
		) );
	}

	/** Map a '?page=x' href to its page key; null for any other href. */
	private static function href_page( string $href ): ?string {
		return 0 === strpos( $href, '?page=' ) ? substr( $href, strlen( '?page=' ) ) : null;
	}
```

- [ ] **Step 5: Thread `$logo_url` through the nine page methods**

In `includes/render/class-page-renderer.php`, for **each** of the nine page methods (`home`, `about`, `membership`, `contact`, `login`, `sports`, `teams`, `events`, `calendar`):

1. Add a trailing parameter `string $logo_url = ''` to the signature.
2. Update its `shell_header(...)` call to pass `$visibility` and `$logo_url`, and its `shell_footer(...)` call to pass `$visibility`.

For example, `home()`:

```php
	public static function home(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = ''
	): string {
		$club = $branding->get_club_name();
		$out  = '';

		if ( $visibility->is_section_visible( 'home', 'header' ) ) {
			$out .= self::shell_header( $club, '?page=home', $visibility, $logo_url );
		}
```

…and its footer line (currently `$out .= self::shell_footer( $club );` near the end of `home()`) becomes `$out .= self::shell_footer( $club, $visibility );`.

Apply the same two edits to the other eight methods. Their existing header calls look like `self::shell_header( $club, '?page=about' )` — change each to `self::shell_header( $club, '?page=about', $visibility, $logo_url )` (matching each method's own `?page=` slug), and each `self::shell_footer( $club )` to `self::shell_footer( $club, $visibility )`.

- [ ] **Step 6: Thread `$logo_url` through `Page_Map::render`**

In `includes/render/class-page-map.php`, change `render()`:

```php
	public static function render(
		string $slug,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = ''
	): string {
		$method = 'home';
		foreach ( self::pages() as $page ) {
			if ( $page['slug'] === $slug ) {
				$method = $page['method'];
				break;
			}
		}
		return call_user_func(
			array( Blueworx_Clubhouse_Page_Renderer::class, $method ),
			$branding,
			$visibility,
			$collections,
			$logo_url
		);
	}
```

- [ ] **Step 7: Run the tests**

Run: `./vendor/bin/phpunit --filter SectionsTest && ./vendor/bin/phpunit --filter PageRendererTest && ./vendor/bin/phpunit --filter PageMapTest`
Expected: PASS — including the pre-existing 3-argument calls (the new parameter defaults to `''`).

Then the full suite to confirm the preview render test (4-arg `Page_Map::render`) still passes:

Run: `./vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php includes/render/class-page-map.php tests/php/SectionsTest.php tests/php/PageRendererTest.php
git commit -m "feat: header logo slot and hidden-page nav/footer omission threaded through the shell"
```

---

## Task 7: Wire resolved logo in `Frontend` + `.ch-brand__logo` look CSS

**Files:**
- Modify: `includes/frontend/class-frontend.php` (`resolve_logo` + `render_body`)
- Modify: `assets/looks/court-side.css`, `assets/looks/members-house.css`, `assets/looks/floodlight.css`
- Test: `tests/php/FrontendTest.php`, `tests/php/CourtSideStylesheetTest.php`, `tests/php/MembersHouseStylesheetTest.php`, `tests/php/FloodlightStylesheetTest.php`

**Interfaces:**
- Consumes: `Media::url()` (Task 3), `Branding::get_logo()` (existing).
- Produces: `Frontend::resolve_logo(string $stored): string`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/FrontendTest.php` (inside the class):

```php
	public function test_resolve_logo_turns_an_attachment_id_into_a_url(): void {
		$this->assertSame( 'https://club.test/wp-content/uploads/att-9.png', Blueworx_Clubhouse_Frontend::resolve_logo( '9' ) );
	}

	public function test_resolve_logo_passes_through_a_stored_url_and_empty(): void {
		$this->assertSame( 'https://cdn.example/logo.svg', Blueworx_Clubhouse_Frontend::resolve_logo( 'https://cdn.example/logo.svg' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::resolve_logo( '' ) );
	}
```

Append to `tests/php/CourtSideStylesheetTest.php` (inside the class — match its existing assertion style for a `.ch-*` selector):

```php
	public function test_stylesheet_styles_the_brand_logo(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/looks/court-side.css' );
		$this->assertStringContainsString( '.ch-brand__logo', $css );
	}
```

Add the same test method to `tests/php/MembersHouseStylesheetTest.php` and `tests/php/FloodlightStylesheetTest.php`, changing the filename to `members-house.css` / `floodlight.css` respectively.

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter FrontendTest && ./vendor/bin/phpunit --filter Stylesheet`
Expected: FAIL — `resolve_logo` undefined; `.ch-brand__logo` absent from each stylesheet.

- [ ] **Step 3: Add `resolve_logo` and thread it into `render_body`**

In `includes/frontend/class-frontend.php`, add the resolver (near `club_name()`):

```php
	/** Turn a stored logo (attachment ID or legacy URL) into a URL string for the header. */
	public static function resolve_logo( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}
		return ctype_digit( $stored ) ? Blueworx_Clubhouse_Media::url( (int) $stored ) : $stored;
	}
```

Change `render_body()` to resolve and pass the logo:

```php
	public static function render_body(): string {
		$slug = self::current_slug();
		if ( null === $slug ) {
			return '';
		}
		$ctx      = self::context();
		$logo_url = self::resolve_logo( $ctx->branding->get_logo() );
		return Blueworx_Clubhouse_Page_Map::render( $slug, $ctx->branding, $ctx->visibility, $ctx->collections, $logo_url );
	}
```

- [ ] **Step 4: Add the `.ch-brand__logo` CSS hook to all three looks**

In `assets/looks/court-side.css`, add (place it next to the existing `.ch-brand` / `.ch-brand__mark` rules):

```css
.ch-brand__logo { display: block; max-height: 40px; width: auto; }
```

Add the equivalent rule to `assets/looks/members-house.css` and `assets/looks/floodlight.css`. Match each look's brand-mark sizing if it differs — the constraint that matters for parity and layout is a bounded `max-height` and `width: auto`; keep the selector identical across all three so the cross-look selector-parity tests stay green.

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/phpunit --filter FrontendTest && ./vendor/bin/phpunit --filter Stylesheet`
Expected: PASS. Then the full suite: `./vendor/bin/phpunit`
Expected: PASS (the Members' House / Floodlight selector-parity tests now see `.ch-brand__logo` in all three sheets).

- [ ] **Step 6: Commit**

```bash
git add includes/frontend/class-frontend.php assets/looks/court-side.css assets/looks/members-house.css assets/looks/floodlight.css tests/php/FrontendTest.php tests/php/CourtSideStylesheetTest.php tests/php/MembersHouseStylesheetTest.php tests/php/FloodlightStylesheetTest.php
git commit -m "feat: resolve logo id to url in Frontend and style .ch-brand__logo per look"
```

---

## Task 8: Version bump to 0.18.0, changelog, final verification

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (header `Version:` + `BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `package.json` (`version`)
- Modify: `CHANGELOG.md`

**Interfaces:** none.

- [ ] **Step 1: Bump the plugin version**

In `blueworx-labs-clubhouse.php`, change the header line ` * Version:           0.16.1` to ` * Version:           0.18.0`, and `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.16.1' );` to `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.18.0' );`.

In `package.json`, change `"version": "0.16.1"` to `"version": "0.18.0"`.

- [ ] **Step 2: Add the changelog entry**

In `CHANGELOG.md`, add a new entry immediately below the top heading, above the most recent existing entry, matching the file's existing format:

```markdown
## [0.18.0] — Admin Phase 3: collection editing, projection robustness, header logo/nav

### Added
- Native custom meta-boxes for all six collection CPTs (fixtures, teams, people, sponsors, sports, events) with typed inputs (date/time/select/email/url) and a `wp.media` image picker, driven by a single pure `Collection_Meta` field definition; values sanitised server-side and escaped on output.
- Admin list columns for the high-signal fields of each collection (e.g. a fixture's date, teams, and result).
- Front-end logo rendering in the site header (attachment resolved to a URL in the WordPress layer; club-name text kept beside it) and omission of hidden pages from the header nav and footer link lists.

### Fixed
- `Fixture_Projection` now groups the calendar by year-and-month (`January 2026`) so fixtures in different years no longer merge, and guards empty/malformed match dates (which previously resolved to "now") — undated fixtures show as "Date TBC" on the calendar.
- The Clubhouse Setup admin menu is now registered on init (it was defined in v0.16.0 but never wired, so it never appeared on a real install).
```

- [ ] **Step 3: Final verification**

Run: `./vendor/bin/phpunit`
Expected: PASS (whole suite — the pre-existing ~238 tests plus the ~30 added here).

Run: `composer lint`
Expected: no errors. (Present any warnings to the user at session end for a decision — do not auto-fix in a loop, per house rules.)

- [ ] **Step 4: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.18.0 (Admin Phase 3 — collection editing)"
```

---

## Post-plan: PR, CI, merge, deploy (performed after all tasks pass)

1. Push `admin-phase-3-cpt-editing`; open a PR **to `main`** (base is `main`, not a stacked branch). If the base is wrong, retarget via `gh api -X PATCH repos/:owner/:repo/pulls/N -f base=main` (`gh pr edit --base` silently no-ops here).
2. Wait for the required **`guardrails / guardrails`** check to go GREEN. Auto-merge is disabled — merge manually once green.
3. After merge, refresh the deployment zip at `..\blueworx-labs-clubhouse.zip` per the house WordPress-plugin rule: **runtime files only** (`blueworx-labs-clubhouse.php` + `includes/` + `assets/` + `templates/`), excluding `tests/`, `docs/`, `preview/`, `vendor/`, `.superpowers/`. Build via .NET `ZipArchive` with forward-slash entry names under a top-level `blueworx-labs-clubhouse/` folder (PowerShell 5.1 `Compress-Archive` writes backslashes that break WP unzip). Delete the old zip in a separate PowerShell call first (the sandbox blocks a single script containing both `Remove-Item` and a `'\'`/`'/'` literal), then build, then verify entries with `unzip -l`.

## Owed manual WordPress smoke (runtime-only; not unit-testable)

Carried on the owed list (Phase 1 and Phase 2 smokes are still outstanding). Phase 3 adds:
- Activate on a fresh install → **Clubhouse → Setup** appears in the admin menu (regression check for the Task 5 wiring fix), and the six collection menus each show a **Details** meta-box on their edit screens.
- Edit a fixture (change date/teams/result) → the front-end `/calendar` reflects it and groups under the correct **month and year**; give a fixture a blank date → it shows as **Date TBC** on the calendar and no fatal.
- Pick an image for a sport/team via the media modal → it renders on the front end; set a **logo** in Setup → it appears in the header with the club name beside it.
- Hide a page in Setup → it drops from the header nav **and** the footer link lists.

## Self-Review

- **Spec coverage:** meta-boxes (Tasks 1, 4) ✓; typed inputs + pure sanitisation (Task 1) ✓; media = attachment ID resolved in WP layer (Tasks 3, 7) ✓; admin list columns (Tasks 1, 4) ✓; `Fixture_Projection` Y-m + malformed-date guard (Task 2) ✓; logo header render + hidden-page nav omission threaded into pure shell (Tasks 6, 7) ✓; seed→register→read contract preserved (unchanged mappers/seeder; Task 1 keys match) ✓; version bump + changelog (Task 8) ✓; manual smoke carried ✓. Bonus: Phase-2 Setup-menu wiring bug (Task 5).
- **Placeholder scan:** none — every step has concrete code and exact commands.
- **Type consistency:** `Collection_Meta::media_keys` produced in Task 1, consumed in Task 3; `Media::url(int):string` produced in Task 3, consumed in Tasks 3 and 7; `Page_Map::render(..., string $logo_url='')` and the nine page methods' trailing `string $logo_url=''` are consistent across Tasks 6 and 7; `merge_columns`/`column_value`/`box_html`/`save` signatures match their tests in Task 4.
