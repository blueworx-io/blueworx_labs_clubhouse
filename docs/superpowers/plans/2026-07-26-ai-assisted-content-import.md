# AI-Assisted Content Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a club owner download a generated AI prompt from ClubHouse, be interviewed by any AI chat, and upload the resulting JSON file back to populate every page section and all six collections.

**Architecture:** Pure core plus thin WordPress glue, matching the existing Setup and Content screens. The downloadable prompt is *generated* from `Content_Catalogue` and `Collection_Meta`, so it never drifts. Uploaded JSON is validated against those same catalogues, turned into an `Import_Plan`, previewed, then applied.

**Tech Stack:** PHP 8.1+, WordPress, PHPUnit 10 with the project's WP-free stub harness (`tests/php/wp-stubs.php`), PHPCS.

## Global Constraints

- **No new runtime dependencies.** `approved-deps.json` governs npm only; add nothing to `package.json`.
- **Pure classes make no WordPress calls** beyond the sanitising helpers already stubbed (`sanitize_text_field`, `sanitize_textarea_field`, `esc_url_raw`, `absint`). No `$_GET`/`$_POST`/`$_FILES` reads, no persistence, in any pure class.
- **All runtime classes** start with `<?php`, `declare(strict_types=1);`, and `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- **All new classes are `final`** and prefixed `Blueworx_Clubhouse_`.
- **WordPress coding style** — tabs for indent, `array()` not `[]`, spaces inside parens: `foo( $bar )`. `composer lint` must stay clean.
- **Every new pure class is required from `includes/bootstrap.php`**; every new glue class is required from the main plugin file and from `tests/php/bootstrap.php`.
- Tests run with `composer test`; lint with `composer lint`. Both must pass before every commit.
- **Capability for every import surface:** `Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP` (`manage_clubhouse`).
- **Import file format version:** `1`, in the JSON key `clubhouse_import`.
- **Content is keyed by `store_page`**, never by tab slug.

## File Structure

| File | Responsibility |
|---|---|
| `includes/content/class-content-sanitiser.php` | Pure field/loop sanitising by catalogue type — extracted from `Content_Controller` (new) |
| `includes/admin/class-content-catalogue.php` | Gains `index()`: `"store_page/section" => {tab, tab_label, section_label}` (modify) |
| `includes/collections/class-demo-content.php` | Gains `titles( string $type )` (modify) |
| `includes/collections/class-collection-seeder.php` | Stamps `_clubhouse_demo` on seeded posts (modify) |
| `includes/import/class-import-plan.php` | Pure DTO: writes, collections, images, warnings; `to_array`/`from_array` (new) |
| `includes/import/class-import-parser.php` | Pure: decoded JSON → plan, validated against the catalogues (new) |
| `includes/import/class-import-prompt.php` | Pure: catalogue → downloadable Markdown prompt (new) |
| `includes/import/class-import-preview.php` | Pure: plan → human summary rows (new) |
| `includes/import/class-import-screen.php` | Pure: escaped HTML for the Import page (new) |
| `includes/import/class-import-applier.php` | Glue: execute a plan against WordPress (new) |
| `includes/import/class-import-controller.php` | Glue: menu, caps, nonces, upload, download, transient hand-off (new) |
| `includes/admin/class-content-controller.php` | Delegates to `Content_Sanitiser`; surfaces the images-needed notice (modify) |
| `includes/admin/class-content-screen.php` | `notices()` gains optional per-notice links (modify) |
| `tests/php/wp-stubs.php` | Adds transient, submenu, post delete/update, sideload, redirect stubs (modify) |

---

### Task 1: Extract `Content_Sanitiser`

Move the field-type sanitising out of `Content_Controller` so the importer and the
editor share exactly one implementation. Pure refactor — no behaviour change.

**Files:**
- Create: `includes/content/class-content-sanitiser.php`
- Modify: `includes/content/../bootstrap.php` → `includes/bootstrap.php` (add require)
- Modify: `includes/admin/class-content-controller.php` (delete the two private methods, delegate)
- Test: `tests/php/ContentSanitiserTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Content_Catalogue` field definitions (`{key,label,type,options?}`).
- Produces:
  - `Blueworx_Clubhouse_Content_Sanitiser::field( array $field_def, mixed $raw, bool $present ): mixed`
  - `Blueworx_Clubhouse_Content_Sanitiser::items( array $loop_fields, array $raw_items ): array`

- [ ] **Step 1: Write the failing test**

Create `tests/php/ContentSanitiserTest.php`:

```php
<?php
// tests/php/ContentSanitiserTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ContentSanitiserTest extends TestCase {

	public function test_text_is_stripped_and_trimmed(): void {
		$def = array( 'key' => 'a', 'label' => 'A', 'type' => 'text' );
		$this->assertSame( 'One club', Blueworx_Clubhouse_Content_Sanitiser::field( $def, '  One club <script>  ', true ) );
	}

	public function test_absent_field_becomes_empty_string(): void {
		$def = array( 'key' => 'a', 'label' => 'A', 'type' => 'text' );
		$this->assertSame( '', Blueworx_Clubhouse_Content_Sanitiser::field( $def, null, false ) );
	}

	public function test_toggle_reflects_presence(): void {
		$def = array( 'key' => 'a', 'label' => 'A', 'type' => 'toggle' );
		$this->assertTrue( Blueworx_Clubhouse_Content_Sanitiser::field( $def, '1', true ) );
		$this->assertFalse( Blueworx_Clubhouse_Content_Sanitiser::field( $def, null, false ) );
	}

	public function test_image_absent_is_empty_string_not_zero(): void {
		$def = array( 'key' => 'image', 'label' => 'Image', 'type' => 'image' );
		$this->assertSame( '', Blueworx_Clubhouse_Content_Sanitiser::field( $def, '', true ) );
		$this->assertSame( 12, Blueworx_Clubhouse_Content_Sanitiser::field( $def, '12', true ) );
	}

	public function test_select_falls_back_when_value_not_an_option(): void {
		$def = array( 'key' => 'icon', 'label' => 'Icon', 'type' => 'select', 'options' => array( '' => 'None', 'join' => 'Join' ) );
		$this->assertSame( 'join', Blueworx_Clubhouse_Content_Sanitiser::field( $def, 'join', true ) );
		$this->assertSame( '', Blueworx_Clubhouse_Content_Sanitiser::field( $def, 'evil', true ) );
	}

	public function test_non_scalar_value_is_treated_as_absent(): void {
		$def = array( 'key' => 'a', 'label' => 'A', 'type' => 'text' );
		$this->assertSame( '', Blueworx_Clubhouse_Content_Sanitiser::field( $def, array( 'x' ), true ) );
	}

	public function test_items_fills_every_declared_field(): void {
		$loop = array(
			array( 'key' => 'label', 'label' => 'Label', 'type' => 'text' ),
			array( 'key' => 'featured', 'label' => 'Featured', 'type' => 'toggle' ),
		);
		$out = Blueworx_Clubhouse_Content_Sanitiser::items( $loop, array( array( 'label' => 'Tennis' ) ) );
		$this->assertSame( array( array( 'label' => 'Tennis', 'featured' => false ) ), $out );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter ContentSanitiserTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Content_Sanitiser" not found`.

- [ ] **Step 3: Create the class**

Create `includes/content/class-content-sanitiser.php`. The bodies of `field()` and
`items()` are the current `Content_Controller::sanitise_field()` and
`::sanitise_items()` verbatim — including their comments, which record hard-won
decisions:

```php
<?php
// includes/content/class-content-sanitiser.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure sanitising for Content_Catalogue field values, by field type. Extracted
 * from Content_Controller so that the admin editor and the AI import path
 * share one implementation — an imported file must be treated exactly like
 * form input, and a field type must decide its own sanitising in exactly one
 * place.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Content_Sanitiser {

	/**
	 * Sanitise a single field's value by its catalogue type.
	 *
	 * @param array<string,mixed> $field_def
	 */
	public static function field( array $field_def, mixed $raw, bool $present ): mixed {
		// A value that isn't scalar (e.g. field[key][]=x submitted as an array, or
		// a nested array under an image/select field) must never reach string
		// coercion below — PHP would emit "Array to string conversion" and store
		// the literal "Array". Treat it as though the field were absent.
		if ( $present && ! is_scalar( $raw ) ) {
			$present = false;
		}
		switch ( $field_def['type'] ) {
			case 'text':
				return $present ? sanitize_text_field( (string) $raw ) : '';
			case 'textarea':
				return $present ? sanitize_textarea_field( (string) $raw ) : '';
			case 'url':
				return $present ? esc_url_raw( (string) $raw ) : '';
			case 'image':
				// '' — not 0 — is the "unset" sentinel every other type uses, and the
				// one Page_Renderer::cget() falls back on. An image field's hidden
				// input always posts, so absint('') === 0 would otherwise land on every
				// untouched image on the first Save and read back as a real override
				// (rendering src="0" and dropping the empty-state fallback).
				// Attachment IDs start at 1, so nothing legitimate is lost.
				$id = $present ? absint( $raw ) : 0;
				return $id > 0 ? $id : '';
			case 'toggle':
				return $present;
			case 'select':
				$value   = $present ? (string) $raw : '';
				$options = $field_def['options'] ?? array();
				return array_key_exists( $value, $options ) ? $value : '';
			default:
				return '';
		}
	}

	/**
	 * Sanitise every posted item of a loop section by its field definitions.
	 *
	 * @param array<int,array<string,mixed>> $loop_fields
	 * @param array<int,mixed>               $raw_items
	 * @return array<int,array<string,mixed>>
	 */
	public static function items( array $loop_fields, array $raw_items ): array {
		$items = array();
		foreach ( $raw_items as $raw_item ) {
			$raw_item = is_array( $raw_item ) ? $raw_item : array();
			$item     = array();
			foreach ( $loop_fields as $field_def ) {
				$fkey          = (string) $field_def['key'];
				$present       = array_key_exists( $fkey, $raw_item );
				$item[ $fkey ] = self::field( $field_def, $present ? $raw_item[ $fkey ] : null, $present );
			}
			$items[] = $item;
		}
		return $items;
	}
}
```

- [ ] **Step 4: Require it from the runtime loader**

In `includes/bootstrap.php`, under the `// Content` block, add the require **after**
`class-content-store.php` and before `class-visibility.php`:

```php
require_once __DIR__ . '/content/class-content-sanitiser.php';
```

- [ ] **Step 5: Delegate from `Content_Controller`**

In `includes/admin/class-content-controller.php`, delete the whole
`private static function sanitise_field(...)` and
`private static function sanitise_items(...)` method bodies and replace both with
delegating one-liners so existing call sites are untouched:

```php
	/**
	 * Sanitise a single field's posted value by its catalogue type.
	 * Delegates to the shared pure sanitiser (also used by the AI import path).
	 *
	 * @param array<string,mixed> $field_def
	 */
	private static function sanitise_field( array $field_def, mixed $raw, bool $present ): mixed {
		return Blueworx_Clubhouse_Content_Sanitiser::field( $field_def, $raw, $present );
	}

	/**
	 * Sanitise every posted item of a loop section by its field definitions.
	 *
	 * @param array<int,array<string,mixed>> $loop_fields
	 * @param array<int,mixed>               $raw_items
	 * @return array<int,array<string,mixed>>
	 */
	private static function sanitise_items( array $loop_fields, array $raw_items ): array {
		return Blueworx_Clubhouse_Content_Sanitiser::items( $loop_fields, $raw_items );
	}
```

- [ ] **Step 6: Run the full suite**

Run: `composer test`
Expected: PASS — the new `ContentSanitiserTest` plus every existing
`ContentControllerTest` case, unchanged. If any `ContentControllerTest` case fails,
the extraction was not verbatim; diff against `git show HEAD:includes/admin/class-content-controller.php`.

- [ ] **Step 7: Lint**

Run: `composer lint`
Expected: no errors.

- [ ] **Step 8: Commit**

```bash
git add includes/content/class-content-sanitiser.php includes/bootstrap.php includes/admin/class-content-controller.php tests/php/ContentSanitiserTest.php
git commit -m "refactor: extract Content_Sanitiser from Content_Controller"
```

---

### Task 2: `Content_Catalogue::index()`

A flat lookup from a stored content address to its human labels and owning tab.
Both the import preview (row labels) and the images-needed notice (deep links into
the Content screen) need it, and deriving it keeps them correct as the catalogue grows.

**Files:**
- Modify: `includes/admin/class-content-catalogue.php`
- Test: `tests/php/ContentCatalogueTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Content_Catalogue::index(): array<string,array{tab:string,tab_label:string,section_key:string,section_label:string}>`, keyed `"{store_page}/{section_key}"`.

- [ ] **Step 1: Write the failing test**

Append to `tests/php/ContentCatalogueTest.php`:

```php
	public function test_index_keys_by_store_page_and_section(): void {
		$index = Blueworx_Clubhouse_Content_Catalogue::index();
		$this->assertArrayHasKey( 'home/hero', $index );
		$this->assertSame( 'global', $index['home/hero']['tab'] );
		$this->assertSame( 'Global', $index['home/hero']['tab_label'] );
		$this->assertSame( 'Hero', $index['home/hero']['section_label'] );
	}

	public function test_index_uses_store_page_not_tab_for_global_sections(): void {
		$index = Blueworx_Clubhouse_Content_Catalogue::index();
		// The Global tab's Header stores under the 'global' store_page.
		$this->assertArrayHasKey( 'global/header', $index );
		$this->assertSame( 'global', $index['global/header']['tab'] );
		$this->assertSame( 'Header', $index['global/header']['section_label'] );
	}

	public function test_index_covers_every_catalogue_section(): void {
		$expected = 0;
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$expected += count( $page['sections'] );
		}
		$this->assertCount( $expected, Blueworx_Clubhouse_Content_Catalogue::index() );
	}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter ContentCatalogueTest`
Expected: FAIL — `Call to undefined method ...::index()`.

- [ ] **Step 3: Implement**

Add to `Blueworx_Clubhouse_Content_Catalogue`, after `pages()`:

```php
	/**
	 * Flat lookup from a stored content address to its labels and owning tab.
	 * Keyed "{store_page}/{section_key}" — the same address Content_Store uses —
	 * so callers holding only stored data (the import preview, the images-needed
	 * notice) can name a section for a human and link to its panel.
	 *
	 * @return array<string,array{tab:string,tab_label:string,section_key:string,section_label:string}>
	 */
	public static function index(): array {
		$index = array();
		foreach ( self::pages() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$key           = (string) $section['store_page'] . '/' . (string) $section['key'];
				$index[ $key ] = array(
					'tab'           => (string) $page['tab'],
					'tab_label'     => (string) $page['label'],
					'section_key'   => (string) $section['key'],
					'section_label' => (string) $section['label'],
				);
			}
		}
		return $index;
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter ContentCatalogueTest`
Expected: PASS.

Note: two tabs both contain a `hero` section storing under different `store_page`
values, so keys never collide. If `test_index_covers_every_catalogue_section` fails
with a count shortfall, two sections share a `store_page`+`key` pair — that is a
catalogue bug, not a test bug; report it rather than loosening the assertion.

- [ ] **Step 5: Lint and commit**

```bash
composer lint
git add includes/admin/class-content-catalogue.php tests/php/ContentCatalogueTest.php
git commit -m "feat: add Content_Catalogue::index() address lookup"
```

---

### Task 3: Mark seeded demo posts

The importer replaces demo collection posts but keeps real ones. New installs get an
explicit marker; installs seeded before this change fall back to title-matching
against `Demo_Content`.

**Files:**
- Modify: `includes/collections/class-demo-content.php`
- Modify: `includes/collections/class-collection-seeder.php`
- Test: `tests/php/DemoContentTest.php`, `tests/php/CollectionSeederTest.php`

**Interfaces:**
- Produces:
  - `Blueworx_Clubhouse_Demo_Content::titles( string $type ): array<int,string>`
  - `Blueworx_Clubhouse_Collection_Seeder::DEMO_META` — the string `'_clubhouse_demo'`

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/DemoContentTest.php`:

```php
	public function test_titles_for_each_type_match_the_seeded_titles(): void {
		$this->assertSame( 6, count( Blueworx_Clubhouse_Demo_Content::titles( 'clubhouse_sport' ) ) );
		$this->assertContains( 'Rugby', Blueworx_Clubhouse_Demo_Content::titles( 'clubhouse_sport' ) );
		$this->assertContains( 'Sponsor 01', Blueworx_Clubhouse_Demo_Content::titles( 'clubhouse_sponsor' ) );
		$this->assertContains( 'Priya Nair', Blueworx_Clubhouse_Demo_Content::titles( 'clubhouse_person' ) );
	}

	public function test_fixture_titles_are_composed_home_vs_away(): void {
		$titles = Blueworx_Clubhouse_Demo_Content::titles( 'clubhouse_fixture' );
		$this->assertContains( 'ClubHouse vs Riverside RFC', $titles );
	}

	public function test_titles_for_unknown_type_is_empty(): void {
		$this->assertSame( array(), Blueworx_Clubhouse_Demo_Content::titles( 'nope' ) );
	}
```

Append to `tests/php/CollectionSeederTest.php`:

```php
	public function test_seeded_posts_carry_the_demo_marker(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Collection_Seeder::seed();
		$metas = wp_stub_calls( 'add_post_meta' );
		$marked = array_filter( $metas, static fn( $c ) => Blueworx_Clubhouse_Collection_Seeder::DEMO_META === $c['args'][1] );
		$this->assertNotEmpty( $marked, 'every seeded post should be stamped as demo content' );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test -- --filter "DemoContentTest|CollectionSeederTest"`
Expected: FAIL — undefined method `titles()`, undefined constant `DEMO_META`.

- [ ] **Step 3: Implement `Demo_Content::titles()`**

Add to `Blueworx_Clubhouse_Demo_Content`:

```php
	/**
	 * The post titles this class seeds for a collection type, composed exactly as
	 * Collection_Seeder composes them. The import path uses these to recognise
	 * unmarked demo posts on installs seeded before the _clubhouse_demo marker
	 * existed.
	 *
	 * @return array<int,string>
	 */
	public static function titles( string $type ): array {
		switch ( $type ) {
			case 'clubhouse_sport':
				return array_map( static fn( $i ) => (string) $i['title'], self::sports() );
			case 'clubhouse_team':
				return array_map( static fn( $i ) => (string) $i['title'], self::teams() );
			case 'clubhouse_event':
				return array_map( static fn( $i ) => (string) $i['title'], self::events() );
			case 'clubhouse_sponsor':
				return array_map( static fn( $i ) => (string) $i['name'], self::sponsors() );
			case 'clubhouse_person':
				return array_map( static fn( $i ) => (string) $i['name'], self::people() );
			case 'clubhouse_fixture':
				return array_map( static fn( $i ) => $i['home'] . ' vs ' . $i['away'], self::fixtures() );
			default:
				return array();
		}
	}
```

- [ ] **Step 4: Stamp the marker in the seeder**

In `includes/collections/class-collection-seeder.php`, add the constant at the top of
the class:

```php
	/** Meta key stamped on every seeded post so the importer can tell demo from real. */
	public const DEMO_META = '_clubhouse_demo';
```

In `seed_type()`, immediately after the existing `foreach ( $meta_keys as $key )` loop:

```php
			add_post_meta( (int) $id, self::DEMO_META, '1' );
```

In `seed_fixtures()`, immediately after its `foreach ( $meta as $key => $value )` loop:

```php
			add_post_meta( (int) $id, self::DEMO_META, '1' );
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer test -- --filter "DemoContentTest|CollectionSeederTest"`
Expected: PASS.

- [ ] **Step 6: Run the full suite and lint**

Run: `composer test && composer lint`
Expected: PASS, no lint errors. The marker is an extra meta row, so any existing
seeder test asserting an exact `add_post_meta` call *count* will now fail — update it
to the new count rather than removing the marker.

- [ ] **Step 7: Commit**

```bash
git add includes/collections/class-demo-content.php includes/collections/class-collection-seeder.php tests/php/DemoContentTest.php tests/php/CollectionSeederTest.php
git commit -m "feat: mark seeded posts as demo content and expose demo titles"
```

---

### Task 4: `Import_Plan` DTO

The single value passed from parser → preview → applier, and the thing stored in the
transient between preview and apply. Pure, with lossless array round-tripping.

**Files:**
- Create: `includes/import/class-import-plan.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/ImportPlanTest.php`

**Interfaces:**
- Produces `Blueworx_Clubhouse_Import_Plan` with:
  - `add_field( string $page, string $section, string $field, mixed $value ): void`
  - `add_items( string $page, string $section, array $items ): void`
  - `add_image( string $page, string $section, string $field, string $url, string $alt, string $label, int $index = -1 ): void` — `$index` is the loop-item position for an image inside a repeatable section, or `-1` for a plain section field
  - `add_collection( string $type, array $items ): void` — each item `array{title:string,meta:array<string,string>,images:array<string,array{url:string,alt:string}>}`
  - `warn( string $message ): void`
  - `fields(): array` — page → section → field → value
  - `items(): array` — page → section → list of items
  - `images(): array` — flat list of `{page,section,field,url,alt,label}`
  - `collections(): array` — type → list of items
  - `warnings(): array<int,string>`
  - `is_empty(): bool`
  - `to_array(): array` / `static from_array( array $a ): self`

- [ ] **Step 1: Write the failing test**

Create `tests/php/ImportPlanTest.php`:

```php
<?php
// tests/php/ImportPlanTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportPlanTest extends TestCase {

	public function test_a_fresh_plan_is_empty(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$this->assertTrue( $plan->is_empty() );
	}

	public function test_a_warning_alone_does_not_make_a_plan_non_empty(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->warn( 'unknown section "nope"' );
		$this->assertTrue( $plan->is_empty() );
		$this->assertSame( array( 'unknown section "nope"' ), $plan->warnings() );
	}

	public function test_fields_nest_by_page_and_section(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$plan->add_field( 'home', 'hero', 'lede', 'A club for all' );
		$this->assertFalse( $plan->is_empty() );
		$this->assertSame(
			array( 'eyebrow' => 'Est. 1974', 'lede' => 'A club for all' ),
			$plan->fields()['home']['hero']
		);
	}

	public function test_items_are_stored_as_a_list(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_items( 'membership', 'faq', array( array( 'question' => 'Q', 'answer' => 'A' ) ) );
		$this->assertCount( 1, $plan->items()['membership']['faq'] );
	}

	public function test_images_are_a_flat_queue(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/a.jpg', 'Pavilion', 'Global · Hero — Background image' );
		$this->assertSame( 'https://e.test/a.jpg', $plan->images()[0]['url'] );
		$this->assertSame( 'Global · Hero — Background image', $plan->images()[0]['label'] );
		$this->assertSame( -1, $plan->images()[0]['index'] );
		$this->assertFalse( $plan->is_empty() );
	}

	public function test_a_loop_item_image_records_its_item_index(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_image( 'home', 'news', 'image', 'https://e.test/n.jpg', '', 'Global · News — Image', 2 );
		$this->assertSame( 2, $plan->images()[0]['index'] );
	}

	public function test_collections_are_keyed_by_type(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_collection( 'clubhouse_sport', array(
			array( 'title' => 'Tennis', 'meta' => array( 'subtitle' => 'Six courts' ), 'images' => array() ),
		) );
		$this->assertSame( 'Tennis', $plan->collections()['clubhouse_sport'][0]['title'] );
		$this->assertFalse( $plan->is_empty() );
	}

	public function test_round_trips_through_an_array(): void {
		$plan = new Blueworx_Clubhouse_Import_Plan();
		$plan->add_field( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$plan->add_items( 'home', 'stats', array( array( 'value' => '450', 'label' => 'Members', 'featured' => true ) ) );
		$plan->add_image( 'home', 'hero', 'image', 'https://e.test/a.jpg', '', 'Global · Hero — Background image' );
		$plan->add_collection( 'clubhouse_sport', array( array( 'title' => 'Tennis', 'meta' => array(), 'images' => array() ) ) );
		$plan->warn( 'unknown field "x"' );

		$copy = Blueworx_Clubhouse_Import_Plan::from_array( $plan->to_array() );

		$this->assertSame( $plan->fields(), $copy->fields() );
		$this->assertSame( $plan->items(), $copy->items() );
		$this->assertSame( $plan->images(), $copy->images() );
		$this->assertSame( $plan->collections(), $copy->collections() );
		$this->assertSame( $plan->warnings(), $copy->warnings() );
	}

	public function test_from_array_tolerates_junk(): void {
		$copy = Blueworx_Clubhouse_Import_Plan::from_array( array( 'fields' => 'not-an-array', 'nope' => 1 ) );
		$this->assertTrue( $copy->is_empty() );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter ImportPlanTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Import_Plan" not found`.

- [ ] **Step 3: Implement**

Create `includes/import/class-import-plan.php`:

```php
<?php
// includes/import/class-import-plan.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The validated, sanitised result of parsing an import file: what to write to
 * Content_Store, which collection items to reconcile, which images to fetch,
 * and what was dropped along the way. Pure and serialisable — the controller
 * stores to_array() in a transient between the preview and the apply step, so
 * the plan the owner approved is the exact plan that runs.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Plan {

	/** @var array<string,array<string,array<string,mixed>>> page => section => field => value */
	private array $fields = array();

	/** @var array<string,array<string,array<int,array<string,mixed>>>> page => section => items */
	private array $items = array();

	/** @var array<int,array{page:string,section:string,field:string,url:string,alt:string,label:string,index:int}> */
	private array $images = array();

	/** @var array<string,array<int,array{title:string,meta:array<string,string>,images:array<string,array{url:string,alt:string}>}>> */
	private array $collections = array();

	/** @var array<int,string> */
	private array $warnings = array();

	public function add_field( string $page, string $section, string $field, mixed $value ): void {
		$this->fields[ $page ][ $section ][ $field ] = $value;
	}

	/** @param array<int,array<string,mixed>> $items */
	public function add_items( string $page, string $section, array $items ): void {
		$this->items[ $page ][ $section ] = array_values( $items );
	}

	/**
	 * Queue an image to fetch. $index is the loop-item position when the image
	 * belongs to a repeatable section's item (News articles carry one each), or
	 * -1 for a plain section field. The applier needs it to know whether to
	 * write the resulting attachment ID to a section field or into an item.
	 */
	public function add_image( string $page, string $section, string $field, string $url, string $alt, string $label, int $index = -1 ): void {
		$this->images[] = array(
			'page'    => $page,
			'section' => $section,
			'field'   => $field,
			'url'     => $url,
			'alt'     => $alt,
			'label'   => $label,
			'index'   => $index,
		);
	}

	/** @param array<int,array{title:string,meta:array<string,string>,images:array<string,array{url:string,alt:string}>}> $items */
	public function add_collection( string $type, array $items ): void {
		$this->collections[ $type ] = array_values( $items );
	}

	public function warn( string $message ): void {
		$this->warnings[] = $message;
	}

	/** @return array<string,array<string,array<string,mixed>>> */
	public function fields(): array {
		return $this->fields;
	}

	/** @return array<string,array<string,array<int,array<string,mixed>>>> */
	public function items(): array {
		return $this->items;
	}

	/** @return array<int,array{page:string,section:string,field:string,url:string,alt:string,label:string,index:int}> */
	public function images(): array {
		return $this->images;
	}

	/** @return array<string,array<int,array{title:string,meta:array<string,string>,images:array<string,array{url:string,alt:string}>}>> */
	public function collections(): array {
		return $this->collections;
	}

	/** @return array<int,string> */
	public function warnings(): array {
		return $this->warnings;
	}

	/**
	 * True when there is nothing to apply. Warnings alone do not count — a file
	 * that produced only warnings must be reported as "nothing to import"
	 * rather than offering an Apply button that would write nothing.
	 */
	public function is_empty(): bool {
		return array() === $this->fields
			&& array() === $this->items
			&& array() === $this->images
			&& array() === $this->collections;
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'fields'      => $this->fields,
			'items'       => $this->items,
			'images'      => $this->images,
			'collections' => $this->collections,
			'warnings'    => $this->warnings,
		);
	}

	/**
	 * Rehydrate a plan from to_array(). Defensive: a transient can be corrupted
	 * or hand-edited, and a malformed slot must degrade to empty rather than
	 * fatal on a later foreach.
	 *
	 * @param array<string,mixed> $a
	 */
	public static function from_array( array $a ): self {
		$plan              = new self();
		$plan->fields      = is_array( $a['fields'] ?? null ) ? $a['fields'] : array();
		$plan->items       = is_array( $a['items'] ?? null ) ? $a['items'] : array();
		$plan->images      = is_array( $a['images'] ?? null ) ? array_values( $a['images'] ) : array();
		$plan->collections = is_array( $a['collections'] ?? null ) ? $a['collections'] : array();
		$plan->warnings    = is_array( $a['warnings'] ?? null ) ? array_values( $a['warnings'] ) : array();
		return $plan;
	}
}
```

- [ ] **Step 4: Require it from the runtime loader**

In `includes/bootstrap.php`, add a new block at the end, after the `// Frontend (pure)` block:

```php
// Import (pure)
require_once __DIR__ . '/import/class-import-plan.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter ImportPlanTest`
Expected: PASS.

- [ ] **Step 6: Lint and commit**

```bash
composer lint
git add includes/import/class-import-plan.php includes/bootstrap.php tests/php/ImportPlanTest.php
git commit -m "feat: add Import_Plan DTO"
```

---

### Task 5: `Import_Parser` — file envelope and page content

Validate the file envelope, then turn its `content` object into plan writes. The
catalogue is the allow-list: anything it does not declare becomes a warning and is
dropped. Collections come in Task 6.

**Files:**
- Create: `includes/import/class-import-parser.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/ImportParserContentTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Content_Catalogue::pages()`, `::index()`,
  `Blueworx_Clubhouse_Content_Sanitiser::field()` / `::items()`,
  `Blueworx_Clubhouse_Import_Plan`.
- Produces:
  - `Blueworx_Clubhouse_Import_Parser::FORMAT_VERSION` — int `1`
  - `Blueworx_Clubhouse_Import_Parser::parse( mixed $decoded ): array{plan:?Blueworx_Clubhouse_Import_Plan,error:string}`
  - `Blueworx_Clubhouse_Import_Parser::image_ref( mixed $raw ): ?array{url:string,alt:string}` (public — Task 6 reuses it for collection images)

- [ ] **Step 1: Write the failing test**

Create `tests/php/ImportParserContentTest.php`:

```php
<?php
// tests/php/ImportParserContentTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportParserContentTest extends TestCase {

	/** @param array<string,mixed> $content */
	private function parse( array $content ): array {
		return Blueworx_Clubhouse_Import_Parser::parse( array(
			'clubhouse_import' => 1,
			'content'          => $content,
		) );
	}

	public function test_a_non_array_file_is_a_hard_error(): void {
		$out = Blueworx_Clubhouse_Import_Parser::parse( 'nope' );
		$this->assertNull( $out['plan'] );
		$this->assertSame( 'This file is not a ClubHouse import file.', $out['error'] );
	}

	public function test_a_missing_format_marker_is_a_hard_error(): void {
		$out = Blueworx_Clubhouse_Import_Parser::parse( array( 'content' => array() ) );
		$this->assertNull( $out['plan'] );
		$this->assertSame( 'This file is missing its "clubhouse_import" format marker.', $out['error'] );
	}

	public function test_an_unsupported_format_version_is_a_hard_error(): void {
		$out = Blueworx_Clubhouse_Import_Parser::parse( array( 'clubhouse_import' => 99, 'content' => array() ) );
		$this->assertNull( $out['plan'] );
		$this->assertStringContainsString( 'version 99', $out['error'] );
	}

	public function test_a_file_with_neither_content_nor_collections_is_a_hard_error(): void {
		$out = Blueworx_Clubhouse_Import_Parser::parse( array( 'clubhouse_import' => 1 ) );
		$this->assertNull( $out['plan'] );
		$this->assertSame( 'This file contains no content or collections to import.', $out['error'] );
	}

	public function test_a_known_field_is_sanitised_onto_the_plan(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array( 'eyebrow' => '  Est. 1974 <script> ' ) ) ) );
		$this->assertSame( 'Est. 1974', $out['plan']->fields()['home']['hero']['eyebrow'] );
		$this->assertSame( '', $out['error'] );
	}

	public function test_content_is_keyed_by_store_page_not_tab(): void {
		// The Global tab's Header section stores under the 'global' store_page.
		$out = $this->parse( array( 'global' => array( 'header' => array( 'join' => 'Join us' ) ) ) );
		$this->assertSame( 'Join us', $out['plan']->fields()['global']['header']['join'] );
	}

	public function test_an_unknown_section_is_warned_and_dropped(): void {
		$out = $this->parse( array( 'home' => array( 'nope' => array( 'x' => 'y' ) ) ) );
		$this->assertTrue( $out['plan']->is_empty() );
		$this->assertSame( array( 'Ignored unknown section "home/nope".' ), $out['plan']->warnings() );
	}

	public function test_an_unknown_field_is_warned_and_dropped_without_losing_its_siblings(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array( 'evil' => 'x', 'eyebrow' => 'ok' ) ) ) );
		$this->assertSame( 'ok', $out['plan']->fields()['home']['hero']['eyebrow'] );
		$this->assertArrayNotHasKey( 'evil', $out['plan']->fields()['home']['hero'] );
		$this->assertSame( array( 'Ignored unknown field "home/hero/evil".' ), $out['plan']->warnings() );
	}

	public function test_a_page_that_is_not_an_object_is_warned(): void {
		$out = $this->parse( array( 'home' => 'nope' ) );
		$this->assertSame( array( 'Ignored "home": expected a group of sections.' ), $out['plan']->warnings() );
	}

	public function test_loop_items_are_sanitised_onto_the_plan(): void {
		$out = $this->parse( array( 'membership' => array( 'faq' => array( 'items' => array(
			array( 'question' => 'When?', 'answer' => 'Now' ),
		) ) ) ) );
		$this->assertSame( 'When?', $out['plan']->items()['membership']['faq'][0]['question'] );
	}

	public function test_loop_items_gain_every_declared_field(): void {
		$out = $this->parse( array( 'home' => array( 'stats' => array( 'items' => array(
			array( 'value' => '450', 'label' => 'Members' ),
		) ) ) ) );
		$this->assertFalse( $out['plan']->items()['home']['stats'][0]['featured'] );
	}

	public function test_items_on_a_non_loop_section_is_warned(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array( 'items' => array( array( 'a' => 'b' ) ) ) ) ) );
		$this->assertSame( array( 'Ignored "home/hero/items": this section is not a repeatable list.' ), $out['plan']->warnings() );
	}

	public function test_non_list_items_are_warned(): void {
		$out = $this->parse( array( 'membership' => array( 'faq' => array( 'items' => 'nope' ) ) ) );
		$this->assertSame( array( 'Ignored "membership/faq/items": expected a list of items.' ), $out['plan']->warnings() );
	}

	public function test_an_image_object_is_queued_not_stored(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array(
			'image' => array( 'url' => 'https://e.test/a.jpg', 'alt' => 'Pavilion' ),
		) ) ) );
		$this->assertSame( array(), $out['plan']->fields() ); // nothing written directly
		$img = $out['plan']->images()[0];
		$this->assertSame( 'https://e.test/a.jpg', $img['url'] );
		$this->assertSame( 'Pavilion', $img['alt'] );
		$this->assertSame( 'Global · Hero — Background image', $img['label'] );
		$this->assertSame( -1, $img['index'] );
	}

	public function test_a_bare_image_string_is_accepted(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array( 'image' => 'https://e.test/a.jpg' ) ) ) );
		$this->assertSame( 'https://e.test/a.jpg', $out['plan']->images()[0]['url'] );
	}

	public function test_a_non_http_image_url_is_warned(): void {
		$out = $this->parse( array( 'home' => array( 'hero' => array( 'image' => 'javascript:alert(1)' ) ) ) );
		$this->assertSame( array(), $out['plan']->images() );
		$this->assertSame( array( 'Ignored "home/hero/image": expected an image URL.' ), $out['plan']->warnings() );
	}

	public function test_a_loop_item_image_is_queued_with_its_index(): void {
		$out = $this->parse( array( 'home' => array( 'news' => array( 'items' => array(
			array( 'title' => 'First' ),
			array( 'title' => 'Second', 'image' => 'https://e.test/n.jpg' ),
		) ) ) ) );
		$this->assertSame( 1, $out['plan']->images()[0]['index'] );
		$this->assertSame( 'news', $out['plan']->images()[0]['section'] );
		// The item itself keeps the empty sentinel until the applier fills it in.
		$this->assertSame( '', $out['plan']->items()['home']['news'][1]['image'] );
	}

	public function test_a_select_value_outside_its_options_falls_back(): void {
		$out = $this->parse( array( 'home' => array( 'quick_tiles' => array( 'items' => array(
			array( 'label' => 'Join', 'icon' => 'evil' ),
		) ) ) ) );
		$this->assertSame( '', $out['plan']->items()['home']['quick_tiles'][0]['icon'] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter ImportParserContentTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Import_Parser" not found`.

- [ ] **Step 3: Implement**

Create `includes/import/class-import-parser.php`:

```php
<?php
// includes/import/class-import-parser.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a decoded import file into an Import_Plan. Pure and total: it never
 * throws and never partially writes — a malformed value becomes a warning and
 * is dropped, so an owner always gets a reviewable plan plus an honest list of
 * what was ignored.
 *
 * The Content_Catalogue and Collection_Meta are the allow-list. Nothing reaches
 * the plan that they do not declare, and every value is sanitised by the same
 * code the admin editor uses — an AI-authored file is treated exactly like
 * form input.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Parser {

	/** The `clubhouse_import` value this parser understands. */
	public const FORMAT_VERSION = 1;

	/** Loop sections carry their repeated rows under this reserved key. */
	private const ITEMS_KEY = 'items';

	/**
	 * @return array{plan:?Blueworx_Clubhouse_Import_Plan,error:string}
	 */
	public static function parse( mixed $decoded ): array {
		if ( ! is_array( $decoded ) ) {
			return self::fail( 'This file is not a ClubHouse import file.' );
		}
		if ( ! array_key_exists( 'clubhouse_import', $decoded ) ) {
			return self::fail( 'This file is missing its "clubhouse_import" format marker.' );
		}
		$version = $decoded['clubhouse_import'];
		if ( ! is_int( $version ) || self::FORMAT_VERSION !== $version ) {
			$shown = is_scalar( $version ) ? (string) $version : 'unknown';
			return self::fail( sprintf(
				'This file uses import format version %s, which this version of ClubHouse cannot read.',
				$shown
			) );
		}

		$content     = is_array( $decoded['content'] ?? null ) ? $decoded['content'] : null;
		$collections = is_array( $decoded['collections'] ?? null ) ? $decoded['collections'] : null;
		if ( null === $content && null === $collections ) {
			return self::fail( 'This file contains no content or collections to import.' );
		}

		$plan = new Blueworx_Clubhouse_Import_Plan();
		if ( null !== $content ) {
			self::parse_content( $content, $plan );
		}

		return array( 'plan' => $plan, 'error' => '' );
	}

	/** @return array{plan:null,error:string} */
	private static function fail( string $message ): array {
		return array( 'plan' => null, 'error' => $message );
	}

	/**
	 * Read an image reference. The chat cannot know attachment IDs, so an image
	 * arrives as a URL — either a bare string or an object with an optional alt.
	 * Only http(s) is accepted; anything else (a data: payload, a local path, a
	 * script scheme) is refused here rather than at fetch time.
	 *
	 * @return array{url:string,alt:string}|null
	 */
	public static function image_ref( mixed $raw ): ?array {
		$url = '';
		$alt = '';
		if ( is_string( $raw ) ) {
			$url = trim( $raw );
		} elseif ( is_array( $raw ) ) {
			$url = is_string( $raw['url'] ?? null ) ? trim( $raw['url'] ) : '';
			$alt = is_string( $raw['alt'] ?? null ) ? trim( $raw['alt'] ) : '';
		}
		if ( '' === $url || 1 !== preg_match( '#^https?://#i', $url ) ) {
			return null;
		}
		return array( 'url' => $url, 'alt' => $alt );
	}

	/**
	 * Catalogue sections keyed by their stored address, carrying the definition
	 * and the labels needed to name an image slot for a human.
	 *
	 * @return array<string,array{def:array<string,mixed>,tab_label:string,section_label:string}>
	 */
	private static function sections(): array {
		$labels = Blueworx_Clubhouse_Content_Catalogue::index();
		$out    = array();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$address         = (string) $section['store_page'] . '/' . (string) $section['key'];
				$out[ $address ] = array(
					'def'           => $section,
					'tab_label'     => $labels[ $address ]['tab_label'] ?? '',
					'section_label' => $labels[ $address ]['section_label'] ?? '',
				);
			}
		}
		return $out;
	}

	/** @param array<string,mixed> $content */
	private static function parse_content( array $content, Blueworx_Clubhouse_Import_Plan $plan ): void {
		$sections = self::sections();

		foreach ( $content as $page => $page_sections ) {
			$page = (string) $page;
			if ( ! is_array( $page_sections ) ) {
				$plan->warn( sprintf( 'Ignored "%s": expected a group of sections.', $page ) );
				continue;
			}
			foreach ( $page_sections as $section_key => $supplied ) {
				$section_key = (string) $section_key;
				$address     = $page . '/' . $section_key;
				if ( ! isset( $sections[ $address ] ) ) {
					$plan->warn( sprintf( 'Ignored unknown section "%s".', $address ) );
					continue;
				}
				if ( ! is_array( $supplied ) ) {
					$plan->warn( sprintf( 'Ignored "%s": expected a group of fields.', $address ) );
					continue;
				}
				self::parse_section( $page, $section_key, $sections[ $address ], $supplied, $plan );
			}
		}
	}

	/**
	 * @param array{def:array<string,mixed>,tab_label:string,section_label:string} $entry
	 * @param array<string,mixed>                                                  $supplied
	 */
	private static function parse_section( string $page, string $section_key, array $entry, array $supplied, Blueworx_Clubhouse_Import_Plan $plan ): void {
		$def          = $entry['def'];
		$field_defs   = is_array( $def['fields'] ?? null ) ? $def['fields'] : array();
		$loop_fields  = is_array( $def['loop']['fields'] ?? null ) ? $def['loop']['fields'] : array();
		$by_key       = array();
		foreach ( $field_defs as $field_def ) {
			$by_key[ (string) $field_def['key'] ] = $field_def;
		}

		foreach ( $supplied as $field_key => $raw ) {
			$field_key = (string) $field_key;
			if ( self::ITEMS_KEY === $field_key ) {
				continue; // handled below.
			}
			if ( ! isset( $by_key[ $field_key ] ) ) {
				$plan->warn( sprintf( 'Ignored unknown field "%s/%s/%s".', $page, $section_key, $field_key ) );
				continue;
			}
			$field_def = $by_key[ $field_key ];
			if ( 'image' === $field_def['type'] ) {
				$ref = self::image_ref( $raw );
				if ( null === $ref ) {
					$plan->warn( sprintf( 'Ignored "%s/%s/%s": expected an image URL.', $page, $section_key, $field_key ) );
					continue;
				}
				$plan->add_image(
					$page,
					$section_key,
					$field_key,
					$ref['url'],
					$ref['alt'],
					self::image_label( $entry, (string) $field_def['label'] )
				);
				continue;
			}
			$plan->add_field(
				$page,
				$section_key,
				$field_key,
				Blueworx_Clubhouse_Content_Sanitiser::field( $field_def, $raw, true )
			);
		}

		if ( ! array_key_exists( self::ITEMS_KEY, $supplied ) ) {
			return;
		}
		if ( array() === $loop_fields ) {
			$plan->warn( sprintf( 'Ignored "%s/%s/items": this section is not a repeatable list.', $page, $section_key ) );
			return;
		}
		$raw_items = $supplied[ self::ITEMS_KEY ];
		if ( ! is_array( $raw_items ) || ! array_is_list( $raw_items ) ) {
			$plan->warn( sprintf( 'Ignored "%s/%s/items": expected a list of items.', $page, $section_key ) );
			return;
		}

		// Sanitise first: an image reference is a non-scalar, so the shared
		// sanitiser already reduces it to the '' sentinel. The images are then
		// queued separately and the applier writes the attachment IDs back in.
		$plan->add_items( $page, $section_key, Blueworx_Clubhouse_Content_Sanitiser::items( $loop_fields, $raw_items ) );

		foreach ( $loop_fields as $field_def ) {
			if ( 'image' !== $field_def['type'] ) {
				continue;
			}
			$field_key = (string) $field_def['key'];
			foreach ( $raw_items as $index => $raw_item ) {
				if ( ! is_array( $raw_item ) || ! array_key_exists( $field_key, $raw_item ) ) {
					continue;
				}
				$ref = self::image_ref( $raw_item[ $field_key ] );
				if ( null === $ref ) {
					$plan->warn( sprintf( 'Ignored "%s/%s/items[%d]/%s": expected an image URL.', $page, $section_key, (int) $index, $field_key ) );
					continue;
				}
				$plan->add_image(
					$page,
					$section_key,
					$field_key,
					$ref['url'],
					$ref['alt'],
					self::image_label( $entry, (string) $field_def['label'] ),
					(int) $index
				);
			}
		}
	}

	/** @param array{def:array<string,mixed>,tab_label:string,section_label:string} $entry */
	private static function image_label( array $entry, string $field_label ): string {
		return $entry['tab_label'] . ' · ' . $entry['section_label'] . ' — ' . $field_label;
	}
}
```

- [ ] **Step 4: Require it from the runtime loader**

In `includes/bootstrap.php`, under the `// Import (pure)` block, after
`class-import-plan.php`:

```php
require_once __DIR__ . '/import/class-import-parser.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter ImportParserContentTest`
Expected: PASS, all 18 cases.

If `test_an_image_object_is_queued_not_stored` reports a label mismatch, read the
real labels out of the catalogue rather than guessing: the Home hero lives on the
`global` tab (label `Global`), its section label is `Hero`, and its image field's
label is `Background image`.

- [ ] **Step 6: Lint and commit**

```bash
composer lint
git add includes/import/class-import-parser.php includes/bootstrap.php tests/php/ImportParserContentTest.php
git commit -m "feat: parse import file envelope and page content into a plan"
```

---

### Task 6: `Import_Parser` — collections

Extend the parser to read the `collections` object. `Collection_Meta` is the
allow-list; `Collection_Meta::sanitise()` does every value. Media-typed meta arrives
as an image reference, exactly as page-content images do.

**Files:**
- Modify: `includes/import/class-import-parser.php`
- Test: `tests/php/ImportParserCollectionsTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Collection_Meta::types()`, `::fields()`, `::sanitise()`,
  and `Blueworx_Clubhouse_Import_Parser::image_ref()` from Task 5.
- Produces: plan entries via `Import_Plan::add_collection( string $type, array $items )`
  where each item is `array{title:string,meta:array<string,string>,images:array<string,array{url:string,alt:string}>}`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/ImportParserCollectionsTest.php`:

```php
<?php
// tests/php/ImportParserCollectionsTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ImportParserCollectionsTest extends TestCase {

	/** @param array<string,mixed> $collections */
	private function parse( array $collections ): array {
		return Blueworx_Clubhouse_Import_Parser::parse( array(
			'clubhouse_import' => 1,
			'collections'      => $collections,
		) );
	}

	public function test_a_collection_item_becomes_a_title_and_meta_pair(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array(
			array( 'title' => 'Tennis', 'subtitle' => 'Six courts' ),
		) ) );
		$item = $out['plan']->collections()['clubhouse_sport'][0];
		$this->assertSame( 'Tennis', $item['title'] );
		$this->assertSame( 'Six courts', $item['meta']['subtitle'] );
	}

	public function test_an_unknown_collection_type_is_warned_and_dropped(): void {
		$out = $this->parse( array( 'clubhouse_nope' => array( array( 'title' => 'X' ) ) ) );
		$this->assertTrue( $out['plan']->is_empty() );
		$this->assertSame( array( 'Ignored unknown collection "clubhouse_nope".' ), $out['plan']->warnings() );
	}

	public function test_a_collection_that_is_not_a_list_is_warned(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array( 'title' => 'Tennis' ) ) );
		$this->assertSame( array( 'Ignored "clubhouse_sport": expected a list of items.' ), $out['plan']->warnings() );
	}

	public function test_an_item_without_a_title_is_warned_and_dropped(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array(
			array( 'subtitle' => 'No name' ),
			array( 'title' => 'Tennis' ),
		) ) );
		$this->assertCount( 1, $out['plan']->collections()['clubhouse_sport'] );
		$this->assertSame( array( 'Ignored clubhouse_sport item 1: every item needs a title.' ), $out['plan']->warnings() );
	}

	public function test_an_empty_collection_list_is_not_planned(): void {
		// An empty list must not reach the plan: applying it would delete the demo
		// posts of that type and leave the section blank, which no owner asked for.
		$out = $this->parse( array( 'clubhouse_sport' => array() ) );
		$this->assertTrue( $out['plan']->is_empty() );
		$this->assertSame( array( 'Ignored "clubhouse_sport": the list is empty.' ), $out['plan']->warnings() );
	}

	public function test_unknown_meta_keys_are_warned_and_dropped(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array(
			array( 'title' => 'Tennis', 'evil' => 'x' ),
		) ) );
		$this->assertArrayNotHasKey( 'evil', $out['plan']->collections()['clubhouse_sport'][0]['meta'] );
		$this->assertSame( array( 'Ignored unknown field "clubhouse_sport/evil".' ), $out['plan']->warnings() );
	}

	public function test_values_are_sanitised_by_collection_meta(): void {
		$out = $this->parse( array( 'clubhouse_fixture' => array(
			array( 'title' => 'A vs B', 'match_date' => 'not-a-date', 'kickoff_time' => '14:00' ),
		) ) );
		$meta = $out['plan']->collections()['clubhouse_fixture'][0]['meta'];
		$this->assertSame( '', $meta['match_date'] ); // strict format check rejects it
		$this->assertSame( '14:00', $meta['kickoff_time'] );
	}

	public function test_a_select_value_outside_its_options_falls_back_to_the_default(): void {
		$out = $this->parse( array( 'clubhouse_event' => array(
			array( 'title' => 'Gala', 'status' => 'sideways' ),
		) ) );
		$this->assertSame( 'upcoming', $out['plan']->collections()['clubhouse_event'][0]['meta']['status'] );
	}

	public function test_a_media_field_is_kept_as_an_image_reference(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array(
			array( 'title' => 'Tennis', 'image' => array( 'url' => 'https://e.test/t.jpg', 'alt' => 'Court' ) ),
		) ) );
		$item = $out['plan']->collections()['clubhouse_sport'][0];
		$this->assertSame( 'https://e.test/t.jpg', $item['images']['image']['url'] );
		$this->assertSame( 'Court', $item['images']['image']['alt'] );
		$this->assertArrayNotHasKey( 'image', $item['meta'] );
	}

	public function test_a_bad_media_url_is_warned_and_the_item_still_imports(): void {
		$out = $this->parse( array( 'clubhouse_sport' => array(
			array( 'title' => 'Tennis', 'image' => 'ftp://e.test/t.jpg' ),
		) ) );
		$item = $out['plan']->collections()['clubhouse_sport'][0];
		$this->assertSame( 'Tennis', $item['title'] );
		$this->assertSame( array(), $item['images'] );
		$this->assertSame( array( 'Ignored the image for clubhouse_sport item 0: expected an image URL.' ), $out['plan']->warnings() );
	}

	public function test_content_and_collections_can_arrive_in_one_file(): void {
		$out = Blueworx_Clubhouse_Import_Parser::parse( array(
			'clubhouse_import' => 1,
			'content'          => array( 'home' => array( 'hero' => array( 'eyebrow' => 'Est. 1974' ) ) ),
			'collections'      => array( 'clubhouse_sport' => array( array( 'title' => 'Tennis' ) ) ),
		) );
		$this->assertSame( 'Est. 1974', $out['plan']->fields()['home']['hero']['eyebrow'] );
		$this->assertCount( 1, $out['plan']->collections()['clubhouse_sport'] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter ImportParserCollectionsTest`
Expected: FAIL — every collection assertion fails because `parse()` currently ignores
the `collections` key (`Undefined array key "clubhouse_sport"`).

- [ ] **Step 3: Call the new branch from `parse()`**

In `Blueworx_Clubhouse_Import_Parser::parse()`, after the existing content branch:

```php
		if ( null !== $collections ) {
			self::parse_collections( $collections, $plan );
		}
```

- [ ] **Step 4: Implement `parse_collections()`**

Add to `Blueworx_Clubhouse_Import_Parser`, after `parse_section()`:

```php
	/**
	 * Read the file's `collections` object. Collection_Meta is the allow-list:
	 * a type it does not declare, or a meta key that type does not declare, is
	 * dropped with a warning. Media-typed meta is kept as an image reference for
	 * the applier to sideload, never as a raw value — an attachment ID is the
	 * only thing that may reach post meta.
	 *
	 * @param array<string,mixed> $collections
	 */
	private static function parse_collections( array $collections, Blueworx_Clubhouse_Import_Plan $plan ): void {
		$known = Blueworx_Clubhouse_Collection_Meta::types();

		foreach ( $collections as $type => $raw_items ) {
			$type = (string) $type;
			if ( ! in_array( $type, $known, true ) ) {
				$plan->warn( sprintf( 'Ignored unknown collection "%s".', $type ) );
				continue;
			}
			if ( ! is_array( $raw_items ) || ! array_is_list( $raw_items ) ) {
				$plan->warn( sprintf( 'Ignored "%s": expected a list of items.', $type ) );
				continue;
			}
			if ( array() === $raw_items ) {
				// An empty list is almost certainly an oversight, and applying it
				// would delete this type's demo posts and leave the section blank.
				$plan->warn( sprintf( 'Ignored "%s": the list is empty.', $type ) );
				continue;
			}

			$field_defs = array();
			foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field_def ) {
				$field_defs[ (string) $field_def['key'] ] = $field_def;
			}

			$items = array();
			foreach ( $raw_items as $index => $raw_item ) {
				$index = (int) $index;
				if ( ! is_array( $raw_item ) ) {
					$plan->warn( sprintf( 'Ignored %s item %d: expected a group of fields.', $type, $index ) );
					continue;
				}
				$title = is_scalar( $raw_item['title'] ?? null ) ? trim( (string) $raw_item['title'] ) : '';
				if ( '' === $title ) {
					$plan->warn( sprintf( 'Ignored %s item %d: every item needs a title.', $type, $index ) );
					continue;
				}

				$meta   = array();
				$images = array();
				foreach ( $raw_item as $key => $raw_value ) {
					$key = (string) $key;
					if ( 'title' === $key ) {
						continue;
					}
					if ( ! isset( $field_defs[ $key ] ) ) {
						$plan->warn( sprintf( 'Ignored unknown field "%s/%s".', $type, $key ) );
						continue;
					}
					if ( 'media' === $field_defs[ $key ]['type'] ) {
						$ref = self::image_ref( $raw_value );
						if ( null === $ref ) {
							$plan->warn( sprintf( 'Ignored the image for %s item %d: expected an image URL.', $type, $index ) );
							continue;
						}
						$images[ $key ] = $ref;
						continue;
					}
					$value        = is_scalar( $raw_value ) ? (string) $raw_value : '';
					$meta[ $key ] = Blueworx_Clubhouse_Collection_Meta::sanitise( $type, $key, $value );
				}

				$items[] = array( 'title' => $title, 'meta' => $meta, 'images' => $images );
			}

			if ( array() !== $items ) {
				$plan->add_collection( $type, $items );
			}
		}
	}
```

Note on `sanitise()` and defaults: `Collection_Meta::sanitise()` returns a select
field's declared default when the supplied value is not an option, which is why
`test_a_select_value_outside_its_options_falls_back_to_the_default` expects
`upcoming` rather than `''`. Do not "fix" that by pre-checking the option list here —
`Collection_Meta` is the single source of truth for what a field accepts.

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter ImportParserCollectionsTest`
Expected: PASS, all 11 cases.

- [ ] **Step 6: Run the full suite and lint**

Run: `composer test && composer lint`
Expected: PASS — in particular `ImportParserContentTest` must still be green; the
collections branch must not have disturbed the content branch.

- [ ] **Step 7: Commit**

```bash
git add includes/import/class-import-parser.php tests/php/ImportParserCollectionsTest.php
git commit -m "feat: parse collections from the import file"
```

---

### Task 7: `Import_Prompt` — the generated Markdown

The downloadable prompt, rendered from `Content_Catalogue` and `Collection_Meta` so it
can never fall behind the plugin. A lockstep test proves coverage in both directions.

**Files:**
- Create: `includes/import/class-import-prompt.php`
- Modify: `includes/collections/class-collection-meta.php` (add `label()`)
- Modify: `includes/bootstrap.php`
- Test: `tests/php/ImportPromptTest.php`, `tests/php/CollectionMetaTest.php`

**Interfaces:**
- Consumes: `Content_Catalogue::pages()`, `Collection_Meta::types()`/`::fields()`.
- Produces:
  - `Blueworx_Clubhouse_Collection_Meta::label( string $type ): string` — human plural, e.g. `Sports`
  - `Blueworx_Clubhouse_Import_Prompt::markdown( string $version ): string`
  - `Blueworx_Clubhouse_Import_Prompt::FILENAME` — `'clubhouse-import-prompt.md'`

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/CollectionMetaTest.php`:

```php
	public function test_every_type_has_a_human_label(): void {
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			$this->assertNotSame( '', Blueworx_Clubhouse_Collection_Meta::label( $type ), $type . ' needs a label' );
		}
		$this->assertSame( 'Sports', Blueworx_Clubhouse_Collection_Meta::label( 'clubhouse_sport' ) );
		$this->assertSame( 'People', Blueworx_Clubhouse_Collection_Meta::label( 'clubhouse_person' ) );
	}

	public function test_label_for_an_unknown_type_is_the_type(): void {
		$this->assertSame( 'nope', Blueworx_Clubhouse_Collection_Meta::label( 'nope' ) );
	}
```

Create `tests/php/ImportPromptTest.php`:

```php
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
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test -- --filter "ImportPromptTest|CollectionMetaTest"`
Expected: FAIL — undefined method `label()`, class `Blueworx_Clubhouse_Import_Prompt` not found.

- [ ] **Step 3: Add `Collection_Meta::label()`**

In `includes/collections/class-collection-meta.php`, add the map beside `COLUMNS`:

```php
	/**
	 * Human plural names for the six collections, used wherever a collection is
	 * named for an owner: the generated import prompt and the import preview.
	 *
	 * @var array<string,string>
	 */
	private const LABELS = array(
		'clubhouse_fixture' => 'Fixtures',
		'clubhouse_person'  => 'People',
		'clubhouse_sponsor' => 'Sponsors',
		'clubhouse_sport'   => 'Sports',
		'clubhouse_team'    => 'Teams',
		'clubhouse_event'   => 'Events',
	);
```

and the accessor, after `columns()`:

```php
	/** Human plural name for a collection type; the raw type if it is unknown. */
	public static function label( string $type ): string {
		return self::LABELS[ $type ] ?? $type;
	}
```

- [ ] **Step 4: Implement `Import_Prompt`**

Create `includes/import/class-import-prompt.php`:

```php
<?php
// includes/import/class-import-prompt.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Markdown prompt a club owner downloads and pastes into an AI
 * chat. Every field description is generated from Content_Catalogue and
 * Collection_Meta, so adding a section to ClubHouse updates the prompt on the
 * next download — there is no hand-maintained copy to drift. A lockstep test
 * asserts the coverage.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Prompt {

	/** Filename offered to the browser on download. */
	public const FILENAME = 'clubhouse-import-prompt.md';

	public static function markdown( string $version ): string {
		$out  = self::preamble();
		$out .= self::content_inventory();
		$out .= self::collection_inventory();
		$out .= self::output_contract( $version );
		return $out;
	}

	private static function preamble(): string {
		return <<<'MD'
# ClubHouse website content interview

You are helping a sports club write the content for their ClubHouse website.
The club owner is not a copywriter and does not know the website's structure —
you do, because it is described below. Interview them, draft the copy, and at
the end produce a single JSON file they can upload back into their site.

## How to run the interview

1. Start by asking what the club is: its name, sports, where it plays, and
   roughly how old it is. Use that to inform every later draft.
2. Then work through the sections below **in the order they appear**, a few at
   a time. Do not dump the whole list at them.
3. For each section, explain in one sentence what it is for, then ask what they
   want it to say. Offer a draft they can accept or correct — do not make them
   write from scratch.
4. Keep each field to the length its description implies. Short text is a few
   words, not a paragraph. A paragraph is two or three sentences.
5. **Never invent facts.** Prices, dates, member counts, league names, results,
   people's names and email addresses must all come from the club. If they do
   not know one, leave the field out rather than guessing.
6. After each page, tell them what is left and ask whether to carry on now or
   generate the file so far. Both are fine — the file can be uploaded as many
   times as they like, and each upload only changes what it contains.
7. When they ask for the file, produce it exactly as described at the end.

## Images

Some fields are images. ClubHouse cannot receive an image through this chat, so
for each one ask for a **public image URL** — a link to the picture on their old
website, or a public link from Drive, Dropbox, or similar. If they do not have
one, leave the field out; the site will show a placeholder and they can upload
the picture in the admin later. Never invent an image URL.


MD;
	}

	private static function content_inventory(): string {
		$out = "## The pages\n\n";
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$out .= '### ' . $page['label'] . "\n\n";
			foreach ( $page['sections'] as $section ) {
				$address = (string) $section['store_page'] . '.' . (string) $section['key'];
				$out    .= '#### ' . $section['label'] . ' — `content.' . $address . "`\n\n";

				$note = self::section_note( $section );
				if ( '' !== $note ) {
					$out .= $note . "\n\n";
				}

				$fields = is_array( $section['fields'] ?? null ) ? $section['fields'] : array();
				foreach ( $fields as $field ) {
					$out .= self::field_line( $field );
				}
				if ( array() !== $fields ) {
					$out .= "\n";
				}

				$loop = is_array( $section['loop'] ?? null ) ? $section['loop'] : array();
				if ( array() !== $loop ) {
					$out .= 'Then a repeatable list of **' . $loop['name'] . '** entries, under `items`. Each entry has:' . "\n";
					foreach ( $loop['fields'] as $field ) {
						$out .= self::field_line( $field );
					}
					$out .= "\n";
				}
			}
		}
		return $out;
	}

	/** The catalogue's own explanatory note for a section, if it has one. */
	private static function section_note( array $section ): string {
		$parts = array();
		if ( ! empty( $section['note'] ) ) {
			$parts[] = (string) $section['note'];
		}
		if ( ! empty( $section['link']['text'] ) ) {
			$parts[] = (string) $section['link']['text'];
		}
		if ( ! empty( $section['auto']['text'] ) ) {
			$parts[] = (string) $section['auto']['text'];
		}
		return '' === implode( '', $parts ) ? '' : '_' . implode( ' ', $parts ) . '_';
	}

	private static function field_line( array $field ): string {
		$line = '- `' . $field['key'] . '` — ' . $field['label'] . ' (' . self::type_hint( $field ) . ')';
		if ( ! empty( $field['placeholder'] ) ) {
			$line .= ' e.g. ' . $field['placeholder'];
		}
		return $line . "\n";
	}

	/** A plain-English description of what a field accepts. */
	private static function type_hint( array $field ): string {
		switch ( $field['type'] ) {
			case 'textarea':
				return 'a short paragraph';
			case 'url':
			case 'href':
				return 'a link';
			case 'image':
			case 'media':
				return 'a public image URL';
			case 'toggle':
				return 'true or false';
			case 'date':
				return 'a date, YYYY-MM-DD';
			case 'time':
				return 'a 24-hour time, HH:MM';
			case 'email':
				return 'an email address';
			case 'select':
				return 'one of: ' . self::option_list( $field );
			case 'text':
			default:
				return 'short text';
		}
	}

	/**
	 * Select options come in two shapes: the content catalogue keys them
	 * value => label, while Collection_Meta lists bare values. Handle both, and
	 * name the empty value rather than printing nothing.
	 */
	private static function option_list( array $field ): string {
		$options = is_array( $field['options'] ?? null ) ? $field['options'] : array();
		$values  = array_is_list( $options ) ? $options : array_keys( $options );
		$shown   = array();
		foreach ( $values as $value ) {
			$shown[] = '' === (string) $value ? '(leave out)' : (string) $value;
		}
		return implode( ', ', $shown );
	}

	private static function collection_inventory(): string {
		$out  = "## The collections\n\n";
		$out .= "These are lists the site builds pages from. Ask for as many entries as the club\n";
		$out .= "actually has — do not pad them out. Every entry needs a `title`.\n\n";

		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			$out .= '### ' . Blueworx_Clubhouse_Collection_Meta::label( $type ) . ' — `collections.' . $type . "`\n\n";
			$out .= '- `title` — the name shown on the site (short text)' . "\n";
			foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
				$out .= self::field_line( $field );
			}
			$out .= "\n";
		}
		return $out;
	}

	private static function output_contract( string $version ): string {
		$example = <<<'JSON'
```json
{
  "clubhouse_import": 1,
  "generated_for": "VERSION",
  "content": {
    "home": {
      "hero": {
        "eyebrow": "Est. 1974 · Marlow",
        "title_lead": "One club, ",
        "title_highlight": "every sport",
        "image": { "url": "https://example.org/pavilion.jpg", "alt": "The pavilion" }
      },
      "stats": {
        "items": [
          { "value": "450", "label": "Members", "featured": true },
          { "value": "6", "label": "Sports", "featured": false }
        ]
      }
    },
    "global": { "header": { "join": "Join the club" } }
  },
  "collections": {
    "clubhouse_sport": [
      { "title": "Tennis", "subtitle": "Four courts, all year",
        "image": { "url": "https://example.org/tennis.jpg" } }
    ]
  }
}
```
JSON;
		$example = str_replace( 'VERSION', $version, $example );

		return <<<MD
## The file you produce

When the club asks for the file, output **one JSON code block and nothing else
inside it**, and tell them to save it as `clubhouse-import.json` and upload it
at Club Content → Import in their ClubHouse admin.

Rules for the file:

- `"clubhouse_import": 1` and `"generated_for": "{$version}"` must both be present.
- Use the exact `content.<page>.<section>` addresses and the exact field keys
  listed above. A key that is not listed will be ignored on upload.
- **Leave out anything you did not discuss.** Uploading only changes what the
  file contains; absent sections keep whatever is already on the site. A blank
  string is not the same as leaving a field out — a blank string clears it.
- Repeatable sections take their entries as a list under `items`.
- Images are `{{ "url": "https://…", "alt": "…" }}`. The `alt` is optional.
- Collections are lists of entries, each with a `title`.

{$example}

MD;
	}
}
```

- [ ] **Step 5: Require it from the runtime loader**

In `includes/bootstrap.php`, under the `// Import (pure)` block:

```php
require_once __DIR__ . '/import/class-import-prompt.php';
```

- [ ] **Step 6: Run the tests**

Run: `composer test -- --filter "ImportPromptTest|CollectionMetaTest"`
Expected: PASS.

Two things to watch:
- The `output_contract()` heredoc is **interpolating** (`<<<MD`, unquoted), so its
  literal braces are doubled (`{{ "url": … }}`) to survive. The `preamble()` and the
  JSON example use **nowdoc** (`<<<'MD'`, `<<<'JSON'`) and must not be.
- If `test_every_catalogue_field_key_appears` fails on a key like `image`, check the
  loop-field branch is emitting `field_line()` for loop fields too, not just the
  section's own fields.

- [ ] **Step 7: Read the output once, as a human**

Run:
```bash
php -r "define('ABSPATH',__DIR__.'/'); require 'includes/bootstrap.php'; echo Blueworx_Clubhouse_Import_Prompt::markdown('0.35.0');" > /tmp/prompt-check.md
```
Read it. It is the product here, not just a string — if a section reads as
nonsense to you it will read as nonsense to the AI running the interview. Fix the
generator, not the catalogue.

- [ ] **Step 8: Lint and commit**

```bash
composer lint
git add includes/import/class-import-prompt.php includes/collections/class-collection-meta.php includes/bootstrap.php tests/php/ImportPromptTest.php tests/php/CollectionMetaTest.php
git commit -m "feat: generate the AI import prompt from the catalogues"
```

---
