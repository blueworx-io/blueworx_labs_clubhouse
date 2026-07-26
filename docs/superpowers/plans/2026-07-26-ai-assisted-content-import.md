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
