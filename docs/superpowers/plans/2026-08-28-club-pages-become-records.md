# Club pages become records — implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every club page's content moves out of one big option and onto the WordPress page it belongs to, edited through the BlueWorx page editor library instead of the hand-built Club Pages screen.

**Architecture:** One declarative source (`Page_Fields`) describes all fifteen content areas in the library's own vocabulary. It feeds three things: the fourteen record-editor screens the library registers, the one global settings screen, and `Page_Content` — the reader the front end uses, which reads post meta the library wrote and casts it back by field kind. A one-off migration copies the old option into the new addresses, the front end is repointed at `Page_Content`, and the release after deletes Club Pages.

**Tech Stack:** PHP 8.3, WordPress, PHPUnit 11, Playwright, no build step. The vendored `Blueworx\PageEditor\v1\…` library from `bluegroup_core_foundation` at `v1` (v1.10.0).

**Spec:** [docs/superpowers/specs/2026-08-28-page-editor-adoption-design.md](../specs/2026-08-28-page-editor-adoption-design.md) — §2, §7, §9. Phase 3 of six.

## Global Constraints

- **Baseline:** plugin v0.97.1, `main` at `f58e9fb`. Foundation vendored at `v1` (v1.10.0) and in sync.
- **Branch and PR.** Always a branch, never `main`. Every change through a pull request. CI guardrails never bypassed.
- **Version and changelog.** Minor bump (new feature) with the changelog updated alongside, in the same PR.
- **No new dependency** without `approved-deps.json` first.
- **Meta key convention is the library's, not ours.** `PostStore::key()` is `<post_type>_<field_id>`. Club pages are post type `page`, so a field id of `hero_eyebrow` is stored at meta key `page_hero_eyebrow`. Nothing in this plugin may compose that key by hand except `Page_Content`, which is the one place that mirrors it.
- **Field ids are `<section_key>_<field_key>`.** Panel id is the bare `<section_key>`, so the library's auto-declared hideable switch is `<section_key>__shown` → meta key `page_<section_key>__shown`.
- **Do not edit `.claude/skills/blueworx-admin-design/` or `blueworx-page-editor/`.** They are hash-compared against the foundation. A difference is fixed by re-pulling, never by editing.
- **Local green is not green for admin screens.** `@wordpress`-tagged specs are silently skipped by a preview-only run. Run `npm run wp:up` then `npm run test:wp` before claiming a screen works.
- **Front-end parity is the acceptance test.** The rendered HTML of all fourteen pages must be byte-identical before and after the migration.

---

## File Structure

**Created:**

- `blueworx-page-editor/` — the vendored library (copied, never authored). `blueworx-page-editor.php`, `Registry.php`, `v1/*.php`.
- `assets/blueworx-page-editor.js` — the vendored browser half.
- `includes/pages/class-page-fields.php` — the declarative source. Fifteen content areas (fourteen pages plus Global), each a list of panels, each panel a list of fields in library vocabulary. Replaces `Content_Catalogue`.
- `includes/pages/class-page-content.php` — read and write club page content. Same four methods `Content_Store` has, plus `is_section_shown()`. Backed by post meta for a page and by one option for Global. The only place that knows the meta key convention.
- `includes/pages/class-page-editors.php` — registers the fifteen screens from `Page_Fields`, hides the fourteen record editors' menu items, points the Pages list at them.
- `includes/pages/class-content-migration.php` — the one-off. Deleted in phase 4.
- `tests/php/PageFieldsTest.php`, `tests/php/PageContentTest.php`, `tests/php/PageEditorsTest.php`, `tests/php/ContentMigrationTest.php`
- `tests/club-page-editor.spec.js` — Playwright, `@wordpress`.
- `docs/upgrades/2026-08-28-club-pages-become-records.md` — the written record, as the last migration got.

**Modified:**

- `blueworx-labs-clubhouse.php` — require the library loader; bump version.
- `includes/bootstrap.php` — load the three new classes.
- `includes/render/class-page-renderer.php` — type rename `Content_Store` → `Page_Content` throughout; `cget`/`citems` bodies unchanged; section visibility reads the panel switch.
- `includes/render/class-page-map.php`, `includes/frontend/class-frontend.php`, `includes/frontend/class-clubhouse-context.php`, `includes/dashboard/class-member-dashboard.php`, `includes/membership/class-welcome-pack.php`, `includes/social/class-manual-feed-source.php` — same type rename.
- `includes/import/class-import-applier.php` — construct `Page_Content` instead of `Content_Store`.
- `includes/admin/class-club-page-editing.php` — `editor_url()` points at the page's own editor screen.
- `includes/admin/class-setup-controller.php`, `includes/admin/class-setup-screen.php` — Visibility tab loses its per-section switches.
- `tests/php/wp-stubs.php` — add `metadata_exists`, `delete_post_meta`.

**Deleted (task 10):**

- `includes/admin/class-content-controller.php`, `class-content-screen.php`, `class-content-catalogue.php`
- `includes/content/class-content-store.php`, `class-content-sanitiser.php`
- `includes/admin/class-setup-sections.php`
- `assets/css/admin-content.css`, `assets/js/admin-content.js`
- Their tests.

---

### Task 1: Vendor the page editor library and boot it

Nothing in this plugin uses the library yet — the skill folder carries a copy, but `blueworx-page-editor/` and `assets/blueworx-page-editor.js` do not exist. The sync check treats the library as "not adopted" while both are absent and starts enforcing the moment either appears, so they land together.

**Files:**
- Create: `blueworx-page-editor/` (copied), `assets/blueworx-page-editor.js` (copied)
- Modify: `blueworx-labs-clubhouse.php`
- Test: `tests/php/PageEditorLibraryTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `\Blueworx\PageEditor\v1\Editor::register( array $screen ): void`, `Editor::load( string $slug, int $id = 0 ): array`, `Editor::save( string $slug, array $values, int $id = 0 ): array`, all available from `plugins_loaded` priority 0 onward.

- [ ] **Step 1: Copy the library in**

```bash
cd c:/Users/LukeMcfarland/Documents/GitHub/blueworx_labs_clubhouse
rm -rf blueworx-page-editor
cp -r .claude/skills/blueworx-admin-design/editor/php blueworx-page-editor
cp .claude/skills/blueworx-admin-design/editor/blueworx-page-editor.js assets/blueworx-page-editor.js
```

`Screen::url()` resolves assets as `plugin_dir_url( dirname( $loader ) )` where `$loader` is `blueworx-page-editor/blueworx-page-editor.php`. That makes the plugin root the base and `assets/blueworx-page-editor.js` the conventional path, so no `blueworx_page_editor_asset_url` filter is needed.

- [ ] **Step 2: Require the loader from the main plugin file**

In `blueworx-labs-clubhouse.php`, before the existing `includes/bootstrap.php` require:

```php
// The BlueWorx page editor library. Vendored from bluegroup_core_foundation and
// hash-compared against it on every pull request — never edited here. Loaded
// before the plugin's own classes because it registers itself on plugins_loaded
// at priority 0, ahead of anything that declares a screen to it.
require_once __DIR__ . '/blueworx-page-editor/blueworx-page-editor.php';
```

- [ ] **Step 3: Write the failing test**

`tests/php/PageEditorLibraryTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageEditorLibraryTest extends TestCase {

	public function test_the_vendored_library_is_the_one_the_skill_folder_carries(): void {
		$root   = dirname( __DIR__, 2 );
		$mine   = $this->hash_tree( $root . '/blueworx-page-editor' );
		$theirs = $this->hash_tree( $root . '/.claude/skills/blueworx-admin-design/editor/php' );
		$this->assertSame( $theirs, $mine, 'The vendored library has drifted from the design system copy. Re-pull it; never edit it.' );
	}

	public function test_the_browser_half_is_the_one_the_skill_folder_carries(): void {
		$root = dirname( __DIR__, 2 );
		$this->assertSame(
			md5_file( $root . '/.claude/skills/blueworx-admin-design/editor/blueworx-page-editor.js' ),
			md5_file( $root . '/assets/blueworx-page-editor.js' )
		);
	}

	/** @return array<string,string> relative path => hash, sorted */
	private function hash_tree( string $dir ): array {
		$out   = array();
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			$out[ str_replace( '\\', '/', substr( (string) $file, strlen( $dir ) + 1 ) ) ] = md5_file( (string) $file );
		}
		ksort( $out );
		return $out;
	}
}
```

- [ ] **Step 4: Run it**

Run: `vendor/bin/phpunit --filter PageEditorLibraryTest`
Expected: PASS. (It fails only if the copy in step 1 was partial — which is the whole point of having it.)

- [ ] **Step 5: Run the foundation's sync check**

Run: `node ../bluegroup_core_foundation/scripts/check-design-system-sync.mjs`
Expected: reports the skill folder, the CSS, **and now the editor library and its JS**, all matching the foundation at `v1`.

- [ ] **Step 6: Commit**

```bash
git checkout -b club-pages-become-records
git add blueworx-page-editor assets/blueworx-page-editor.js blueworx-labs-clubhouse.php tests/php/PageEditorLibraryTest.php
git commit -m "Vendor the page editor library"
```

---

### Task 2: The declarative source

`Content_Catalogue::pages()` describes fifteen content areas in Clubhouse's own vocabulary. `Page_Fields` says the same thing in the library's. It is a straight translation — no field gains or loses meaning — and it is the only place the two vocabularies meet.

**Files:**
- Create: `includes/pages/class-page-fields.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/PageFieldsTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Content_Catalogue::pages( ?Products )` (read to write this, and by the lockstep test below; both go in task 10), `Blueworx_Clubhouse_Page_Map`, `Blueworx_Clubhouse_Integrations`, `Blueworx_Clubhouse_Products`.
- Produces:
  - `Blueworx_Clubhouse_Page_Fields::areas( ?Blueworx_Clubhouse_Products $products = null ): array` — `array<string,array{label:string,tabs:array<int,array{id:string,label:string,panels:array}>}>` keyed by area key (`home`, `about`, …, `global`).
  - `Blueworx_Clubhouse_Page_Fields::kind_of( string $area, string $section, string $field ): string` — the field's library kind, or `''` when unknown.
  - `Blueworx_Clubhouse_Page_Fields::field_id( string $section, string $field ): string` — `$section . '_' . $field`.
  - `Blueworx_Clubhouse_Page_Fields::address_label( string $address ): string` — "Home · Hero" for a `page/section` address, replacing `Content_Catalogue::address_label()`. Task 10 repoints its two callers; declare it here so it exists before they need it.
  - `Blueworx_Clubhouse_Page_Fields::REPEATER_FIELD = 'items'` — the field key a repeater panel's rows live under, matching `Content_Store::ITEMS_KEY`.

- [ ] **Step 1: Write the failing test**

`tests/php/PageFieldsTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageFieldsTest extends TestCase {

	public function test_every_club_page_and_global_has_an_area(): void {
		$areas = array_keys( Blueworx_Clubhouse_Page_Fields::areas() );
		sort( $areas );
		$expected = array( 'about', 'booking', 'calendar', 'contact', 'events', 'global', 'home', 'login', 'membership', 'news', 'privacy', 'rules', 'sports', 'teams', 'terms' );
		sort( $expected );
		$this->assertSame( $expected, $areas );
	}

	public function test_field_ids_are_unique_within_an_area(): void {
		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $key => $area ) {
			$ids = array();
			foreach ( $area['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					foreach ( $panel['fields'] as $field ) {
						$ids[] = $field['id'];
					}
					if ( ! empty( $panel['hideable'] ) ) {
						$ids[] = $panel['id'] . '__shown';
					}
				}
			}
			$this->assertSame( array_unique( $ids ), $ids, sprintf( 'The "%s" area has two fields with the same id. One would silently overwrite the other.', $key ) );
		}
	}

	public function test_every_field_id_is_its_section_and_key_joined(): void {
		$this->assertSame( 'hero_eyebrow', Blueworx_Clubhouse_Page_Fields::field_id( 'hero', 'eyebrow' ) );
	}

	public function test_every_kind_is_one_the_library_accepts(): void {
		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $area ) {
			foreach ( $area['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					foreach ( $panel['fields'] as $field ) {
						$this->assertContains( $field['kind'], \Blueworx\PageEditor\v1\Schema::KINDS, $field['id'] );
						foreach ( $field['fields'] ?? array() as $cell ) {
							$this->assertContains( $cell['kind'], \Blueworx\PageEditor\v1\Schema::REPEATER_KINDS, $field['id'] . '.' . $cell['id'] );
						}
					}
				}
			}
		}
	}

	/**
	 * The lockstep that proves this is a translation and not a rewrite. Deleted
	 * with the catalogue itself in phase 4 — until then it is the only thing
	 * standing between a mistyped key and a club's words landing nowhere.
	 */
	public function test_every_catalogue_field_has_a_counterpart(): void {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$area = (string) $section['store_page'];
				$sec  = (string) $section['key'];
				foreach ( $section['fields'] ?? array() as $field ) {
					$this->assertNotSame(
						'',
						Blueworx_Clubhouse_Page_Fields::kind_of( $area, $sec, (string) $field['key'] ),
						sprintf( '%s/%s/%s is in the catalogue and not in Page_Fields.', $area, $sec, $field['key'] )
					);
				}
				if ( ! empty( $section['loop'] ) ) {
					$this->assertSame(
						'repeater',
						Blueworx_Clubhouse_Page_Fields::kind_of( $area, $sec, Blueworx_Clubhouse_Page_Fields::REPEATER_FIELD )
					);
				}
			}
		}
	}
}
```

- [ ] **Step 2: Run it to see it fail**

Run: `vendor/bin/phpunit --filter PageFieldsTest`
Expected: FAIL with `Class "Blueworx_Clubhouse_Page_Fields" not found`.

- [ ] **Step 3: Write `Page_Fields`**

The translation rules, applied field by field from `Content_Catalogue::pages()`:

| Catalogue | `Page_Fields` |
| --- | --- |
| `type => 'text'` | `kind => 'text'`, `placeholder` → `placeholder` |
| `type => 'textarea'` | `kind => 'textarea'`, `rows` → `rows` |
| `type => 'url'` | `kind => 'text'`, `format => 'url'`, `suggestions` added in task 5 |
| `type => 'image'` | `kind => 'media'` |
| `type => 'toggle'` | `kind => 'toggle'`, `default` carried across unchanged |
| `type => 'select'` | `kind => 'select'`, `options` reshaped from `value => label` to `[ [ 'value' => …, 'label' => … ], … ]` |
| `type => 'shortcode'` | `kind => 'text'` |
| `loop` | one `kind => 'repeater'` field with `id => <section>_items`, its `fields` being the loop's own fields translated by the same rules |
| `type => 'linkout'` / `'auto'` | a `kind => 'copytext'` field carrying the `link.text` / `auto.text` sentence, plus any real fields the section also declares |

Skeleton — the four global panels shown in full, the rest following the same shape:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every editable content area, said in the page editor library's vocabulary.
 *
 * One source, three readers: Page_Editors builds the fifteen screens from it,
 * Page_Content casts stored values back by the kinds it declares, and the
 * migration reads it to know where each old address now lives. A field that is
 * not here is a field that cannot be edited, cannot be read and will not be
 * migrated — which is why a lockstep test holds it against the catalogue it
 * replaces until that catalogue is deleted.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Fields {

	/** The field key a repeating panel's rows live under — Content_Store's own. */
	public const REPEATER_FIELD = 'items';

	/** A field's id: its section and its key, joined. Unique within an area. */
	public static function field_id( string $section, string $field ): string {
		return $section . '_' . $field;
	}

	private static function text( string $section, string $key, string $label, string $ph = '' ): array {
		$out = array( 'id' => self::field_id( $section, $key ), 'kind' => 'text', 'label' => $label );
		if ( '' !== $ph ) {
			$out['placeholder'] = $ph;
		}
		return $out;
	}

	private static function area_field( string $section, string $key, string $label, int $rows = 3 ): array {
		return array( 'id' => self::field_id( $section, $key ), 'kind' => 'textarea', 'label' => $label, 'rows' => $rows );
	}

	private static function url( string $section, string $key, string $label ): array {
		// Suggestions are attached in Page_Editors, not here: they depend on
		// the site's own pages and shop, and this class stays pure.
		return array( 'id' => self::field_id( $section, $key ), 'kind' => 'text', 'format' => 'url', 'label' => $label );
	}

	private static function media( string $section, string $key, string $label ): array {
		return array( 'id' => self::field_id( $section, $key ), 'kind' => 'media', 'label' => $label );
	}

	private static function toggle( string $section, string $key, string $label, bool $default ): array {
		return array( 'id' => self::field_id( $section, $key ), 'kind' => 'toggle', 'label' => $label, 'default' => $default );
	}

	/** @param array<string,string> $options value => label */
	private static function select( string $section, string $key, string $label, array $options ): array {
		$out = array();
		foreach ( $options as $value => $text ) {
			$out[] = array( 'value' => (string) $value, 'label' => (string) $text );
		}
		return array( 'id' => self::field_id( $section, $key ), 'kind' => 'select', 'label' => $label, 'options' => $out );
	}

	/** @param array<int,array<string,mixed>> $cells */
	private static function repeater( string $section, string $label, array $cells ): array {
		return array(
			'id'     => self::field_id( $section, self::REPEATER_FIELD ),
			'kind'   => 'repeater',
			'label'  => $label,
			'fields' => $cells,
		);
	}

	/** A row cell. Its id is bare — repeater scopes are separate, so no prefix. */
	private static function cell( string $id, string $kind, string $label, array $extra = array() ): array {
		return array_merge( array( 'id' => $id, 'kind' => $kind, 'label' => $label ), $extra );
	}

	/** Display-only prose, where a section points at a collection instead of editing. */
	private static function copytext( string $section, string $text ): array {
		return array( 'id' => self::field_id( $section, 'about' ), 'kind' => 'copytext', 'text' => $text );
	}

	/**
	 * @return array<string,array{label:string,tabs:array<int,array<string,mixed>>}>
	 */
	public static function areas( ?Blueworx_Clubhouse_Products $products = null ): array {
		$areas = array(
			'global' => array(
				'label' => 'Global content',
				'tabs'  => array(
					array( 'id' => 'content', 'label' => 'Content', 'panels' => array(
						array( 'id' => 'header', 'title' => 'Header', 'eyebrow' => 'Every page · Top',
							'note'   => 'Shown on every page. Logo and club name come from Site setup → Branding.',
							'fields' => array(
								self::text( 'header', 'join', 'Menu CTA label', 'e.g. Join the Club' ),
								self::url( 'header', 'join_href', 'Menu CTA link' ),
								self::toggle( 'header', 'banner_show', 'Show announcement bar', true ),
								self::text( 'header', 'banner', 'Announcement text' ),
								self::url( 'header', 'banner_href', 'Announcement link' ),
							) ),
						array( 'id' => 'footer', 'title' => 'Footer', 'eyebrow' => 'Every page · Foot',
							'note'   => 'Shown on every page. Contact details and social links come from Site setup → Branding. Paste a SureForms shortcode to collect newsletter signups — without one the signup box is hidden, because a box that takes an address and does nothing with it is worse than none.',
							'fields' => array(
								self::area_field( 'footer', 'tagline', 'About blurb', 4 ),
								self::text( 'footer', 'newsletter_heading', 'Newsletter heading' ),
								self::area_field( 'footer', 'newsletter_lede', 'Newsletter blurb', 2 ),
								self::text( 'footer', 'newsletter_shortcode', 'Newsletter signup shortcode (SureForms)' ),
							) ),
						array( 'id' => 'welcome', 'title' => 'Welcome pack', 'eyebrow' => 'Member dashboard',
							'note'   => 'Shown to a member on their account dashboard once they have joined — the practical things a new member needs: how to get in, where to park, who to ask. Leave the body empty and nothing is shown at all.',
							'fields' => array(
								self::text( 'welcome', 'heading', 'Heading', 'e.g. Welcome to the club' ),
								self::area_field( 'welcome', 'body', 'Welcome pack', 8 ),
								self::text( 'welcome', 'link_label', 'Link label', 'e.g. Read the full handbook' ),
								self::url( 'welcome', 'link_href', 'Link' ),
							) ),
						array( 'id' => 'cookies', 'title' => 'Cookie notice', 'eyebrow' => 'Every page · Foot',
							'note'   => 'Shown once per visitor, at the foot of every page, until they dismiss it. If you run a dedicated consent plugin, switch this off and let that one do the job.',
							'fields' => array(
								self::toggle( 'cookies', 'show', 'Show the cookie notice', true ),
								self::area_field( 'cookies', 'text', 'Notice text', 3 ),
								self::text( 'cookies', 'link_label', 'Link label' ),
								self::url( 'cookies', 'link_href', 'Link' ),
								self::text( 'cookies', 'dismiss', 'Dismiss button label' ),
							) ),
					) ),
				),
			),
			'home' => array( 'label' => 'Home', 'tabs' => self::home_tabs() ),
			// … about, membership, contact, login, news, sports, teams, events,
			// calendar, booking, privacy, terms, rules — each built the same way.
		);

		return self::drop_unavailable( $areas );
	}
}
```

Tabs, per the spec's "one per group of sections, or none where a page has three panels or fewer": **every area gets one tab, `content`, labelled "Content", except Home.** Home has eleven panels, so it gets three:

| Tab | Panels |
| --- | --- |
| `hero` — "Top of the page" | hero, quick_tiles, ticker |
| `club` — "The club" | sports, clubhouse, membership, activity |
| `community` — "News and community" | news, social_feed, info, sponsors, social |

`hideable` is `true` on exactly the panels a club can switch off today — every section that appears in `Setup_Sections::inventory()`. `home.social_feed` keeps its shipped-off default by declaring `'hideable' => true` **and** relying on the migration (task 8) to write its current state; the library's own default is on, matching `Visibility`'s.

`drop_unavailable()` applies the same two filters `Content_Catalogue::pages()` ends with: drop a whole area when `Page_Map::is_available()` says its integration is absent, then drop individual panels through `Integrations::section_available()`.

- [ ] **Step 4: Register the file**

Add to `includes/bootstrap.php`, alongside the other content classes:

```php
require_once __DIR__ . '/pages/class-page-fields.php';
```

- [ ] **Step 5: Run the tests**

Run: `vendor/bin/phpunit --filter PageFieldsTest`
Expected: PASS, all five.

- [ ] **Step 6: Commit**

```bash
git add includes/pages/class-page-fields.php includes/bootstrap.php tests/php/PageFieldsTest.php
git commit -m "Describe every content area in the library's vocabulary"
```

---

### Task 3: `Page_Content`

The reader the front end uses. It reads what the library writes, and writes what the library would — so the import can keep using it and the migration has somewhere to write to.

**Files:**
- Create: `includes/pages/class-page-content.php`
- Modify: `includes/bootstrap.php`, `tests/php/wp-stubs.php`
- Test: `tests/php/PageContentTest.php`

**Interfaces:**
- Consumes: `Page_Fields::kind_of()`, `Page_Fields::field_id()`, `Blueworx_Clubhouse_Club_Pages::post_id()`.
- Produces:
  - `Blueworx_Clubhouse_Page_Content::__construct( ?Blueworx_Clubhouse_Storage $globals = null )`
  - `get( string $page, string $section, string $field, mixed $default = null ): mixed`
  - `set( string $page, string $section, string $field, mixed $value ): void`
  - `get_items( string $page, string $section ): array`
  - `set_items( string $page, string $section, array $items ): void`
  - `is_section_shown( string $page, string $section ): bool`
  - `const GLOBAL_AREA = 'global'`, `const GLOBAL_OPTION = 'global_content'`

- [ ] **Step 1: Add the two missing WordPress stubs**

In `tests/php/wp-stubs.php`, beside `get_post_meta`:

```php
if ( ! function_exists( 'metadata_exists' ) ) {
	function metadata_exists( string $type, int $id, string $key ): bool {
		return array_key_exists( $key, $GLOBALS['wp_stub_postmeta'][ $id ] ?? array() );
	}
}
if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $id, string $key ): bool {
		unset( $GLOBALS['wp_stub_postmeta'][ $id ][ $key ] );
		return true;
	}
}
```

- [ ] **Step 2: Write the failing test**

`tests/php/PageContentTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageContentTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_stub_postmeta'] = array();
		$GLOBALS['wp_stub_options']  = array();
		update_option( 'clubhouse_page_id_home', 42 );
	}

	private function content(): Blueworx_Clubhouse_Page_Content {
		return new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_a_value_is_stored_at_the_key_the_library_would_use(): void {
		$this->content()->set( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$this->assertSame( 'Est. 1974', $GLOBALS['wp_stub_postmeta'][42]['page_hero_eyebrow'] );
	}

	public function test_a_value_round_trips(): void {
		$c = $this->content();
		$c->set( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$this->assertSame( 'Est. 1974', $c->get( 'home', 'hero', 'eyebrow' ) );
	}

	/**
	 * The reason this class casts at all. WordPress stores boolean false as an
	 * empty string, so a switch an owner turned off would read back as '' —
	 * which the renderer treats as "never set" and replaces with the declared
	 * default, switching it straight back on.
	 */
	public function test_a_toggle_switched_off_reads_back_as_false_and_not_as_unset(): void {
		$c = $this->content();
		$c->set( 'global', 'cookies', 'show', false );
		$this->assertFalse( $c->get( 'global', 'cookies', 'show' ) );
	}

	public function test_a_field_never_written_reads_back_as_the_given_default(): void {
		$this->assertSame( 'fallback', $this->content()->get( 'home', 'hero', 'eyebrow', 'fallback' ) );
	}

	public function test_a_media_field_reads_back_as_an_integer(): void {
		$c = $this->content();
		$c->set( 'home', 'clubhouse', 'image', '77' );
		$this->assertSame( 77, $c->get( 'home', 'clubhouse', 'image' ) );
	}

	public function test_rows_round_trip(): void {
		$c    = $this->content();
		$rows = array( array( 'label' => 'Join', 'href' => '/join/', 'icon' => 'join' ) );
		$c->set_items( 'home', 'quick_tiles', $rows );
		$this->assertSame( $rows, $c->get_items( 'home', 'quick_tiles' ) );
	}

	public function test_rows_never_written_read_back_as_an_empty_list(): void {
		$this->assertSame( array(), $this->content()->get_items( 'home', 'quick_tiles' ) );
	}

	public function test_global_content_lives_in_an_option_and_not_on_a_page(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$c       = new Blueworx_Clubhouse_Page_Content( $storage );
		$c->set( 'global', 'header', 'join', 'Join us' );
		$this->assertSame( array( 'header_join' => 'Join us' ), $storage->get( 'global_content', array() ) );
		$this->assertSame( array(), $GLOBALS['wp_stub_postmeta'] );
	}

	public function test_a_section_nobody_has_hidden_is_shown(): void {
		$this->assertTrue( $this->content()->is_section_shown( 'home', 'hero' ) );
	}

	public function test_a_section_switched_off_is_hidden(): void {
		$GLOBALS['wp_stub_postmeta'][42]['page_hero__shown'] = '';
		$this->assertFalse( $this->content()->is_section_shown( 'home', 'hero' ) );
	}

	public function test_a_page_with_no_post_behind_it_reads_defaults_and_writes_nothing(): void {
		delete_option( 'clubhouse_page_id_home' );
		$c = $this->content();
		$c->set( 'home', 'hero', 'eyebrow', 'Est. 1974' );
		$this->assertSame( 'fallback', $c->get( 'home', 'hero', 'eyebrow', 'fallback' ) );
	}
}
```

- [ ] **Step 3: Run it to see it fail**

Run: `vendor/bin/phpunit --filter PageContentTest`
Expected: FAIL with `Class "Blueworx_Clubhouse_Page_Content" not found`.

- [ ] **Step 4: Write `Page_Content`**

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A club page's words, read from the page itself.
 *
 * The page editor library owns the writing: it saves each field as post meta
 * on the club page, keyed <post_type>_<field_id>. This class is the other
 * half — the front end's way in, and the only place in this plugin that
 * mirrors that key. It keeps the four methods Content_Store had, so the
 * renderer's own read helpers change type and nothing else.
 *
 * It casts, because raw meta cannot answer for itself. WordPress stores a
 * boolean false as an empty string, and the renderer reads an empty string as
 * "never set" and substitutes its own default — so a switch an owner turned
 * off would come back on. Kinds come from Page_Fields, which is also what the
 * library validated the value against on the way in.
 *
 * Global content — header, footer, welcome pack, cookie notice — has no page
 * behind it, so it lives in one option and is reached under the page key
 * 'global'. Same addresses, same methods, different shelf.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Content {

	public const GLOBAL_AREA   = 'global';
	public const GLOBAL_OPTION = 'global_content';

	/** The post type club pages are, and so the prefix the library's meta keys carry. */
	private const POST_TYPE = 'page';

	private Blueworx_Clubhouse_Storage $globals;

	public function __construct( ?Blueworx_Clubhouse_Storage $globals = null ) {
		$this->globals = $globals ?? new Blueworx_Clubhouse_Options_Storage();
	}

	/** The meta key the library writes a field to. Mirrors PostStore::key(). */
	private function meta_key( string $section, string $field ): string {
		return self::POST_TYPE . '_' . Blueworx_Clubhouse_Page_Fields::field_id( $section, $field );
	}

	/** The post behind a club page, or 0 when there is none yet. */
	private function post_id( string $page ): int {
		$slug = Blueworx_Clubhouse_Club_Pages::slug_for_page_key( $page );
		return null === $slug ? 0 : Blueworx_Clubhouse_Club_Pages::post_id( $slug );
	}

	/** @return array<string,mixed> */
	private function global_values(): array {
		$saved = $this->globals->get( self::GLOBAL_OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	public function get( string $page, string $section, string $field, mixed $default = null ): mixed {
		$kind = Blueworx_Clubhouse_Page_Fields::kind_of( $page, $section, $field );

		if ( self::GLOBAL_AREA === $page ) {
			$values = $this->global_values();
			$key    = Blueworx_Clubhouse_Page_Fields::field_id( $section, $field );
			return array_key_exists( $key, $values ) ? $this->cast( $kind, $values[ $key ] ) : $default;
		}

		$id = $this->post_id( $page );
		if ( 0 === $id || ! function_exists( 'metadata_exists' ) ) {
			return $default;
		}
		$key = $this->meta_key( $section, $field );
		// metadata_exists() is the only way to tell "never written" from
		// "written as empty" — get_post_meta() answers '' to both, and the
		// renderer's own fallback turns '' into its hardcoded default. Without
		// this a deliberately cleared field would spring back to its shipped
		// wording, which is exactly the bug the old store did not have.
		return metadata_exists( 'post', $id, $key )
			? $this->cast( $kind, get_post_meta( $id, $key, true ) )
			: $default;
	}

	public function set( string $page, string $section, string $field, mixed $value ): void {
		if ( self::GLOBAL_AREA === $page ) {
			$values = $this->global_values();
			$values[ Blueworx_Clubhouse_Page_Fields::field_id( $section, $field ) ] = $value;
			$this->globals->set( self::GLOBAL_OPTION, $values );
			return;
		}
		$id = $this->post_id( $page );
		if ( 0 === $id || ! function_exists( 'update_post_meta' ) ) {
			return;
		}
		update_post_meta( $id, $this->meta_key( $section, $field ), $value );
	}

	/** @return array<int,array<string,mixed>> */
	public function get_items( string $page, string $section ): array {
		$value = $this->get( $page, $section, Blueworx_Clubhouse_Page_Fields::REPEATER_FIELD, array() );
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/** @param array<int,array<string,mixed>> $items */
	public function set_items( string $page, string $section, array $items ): void {
		$this->set( $page, $section, Blueworx_Clubhouse_Page_Fields::REPEATER_FIELD, array_values( $items ) );
	}

	/**
	 * Whether a panel's Shown switch is on. Defaults to on, the way the
	 * library's own auto-declared switch does — a panel nobody has touched has
	 * not been hidden.
	 *
	 * The library names that switch <panel_id>__shown, and this class joins a
	 * section and a field with one underscore, so the field key here is
	 * "_shown" — the second underscore is the join.
	 */
	public function is_section_shown( string $page, string $section ): bool {
		return (bool) $this->get( $page, $section, '_shown', true );
	}

	/** Turn what storage handed back into what the field's kind means. */
	private function cast( string $kind, mixed $value ): mixed {
		switch ( $kind ) {
			case 'toggle':
				return (bool) $value;
			case 'media':
			case 'number':
				return is_numeric( $value ) ? (int) $value : 0;
			case 'repeater':
				return is_array( $value ) ? array_values( $value ) : array();
			default:
				return $value;
		}
	}
}
```

`Page_Fields::kind_of()` must answer `'toggle'` for `( '<area>', '<section>', '_shown' )` on a hideable panel. `is_section_shown()` casts to bool itself, so the switch would survive without it — but a caller reaching the same address through `get()` would get a raw `'1'` or `''` back, and one of the two readers would then be answering a different question from the other. Add the case to `kind_of()` in the same commit, and cover it with `test_a_section_switched_off_is_hidden` above.

- [ ] **Step 5: Register the file**

```php
require_once __DIR__ . '/pages/class-page-content.php';
```

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit --filter PageContentTest`
Expected: PASS, all eleven.

- [ ] **Step 7: Commit**

```bash
git add includes/pages/class-page-content.php includes/bootstrap.php tests/php/wp-stubs.php tests/php/PageContentTest.php
git commit -m "Read a club page's words from the page itself"
```

---

### Task 4: Register the fifteen screens

**Files:**
- Create: `includes/pages/class-page-editors.php`
- Modify: `includes/bootstrap.php`, `includes/admin/class-club-page-editing.php`
- Test: `tests/php/PageEditorsTest.php`

**Interfaces:**
- Consumes: `Page_Fields::areas()`, `\Blueworx\PageEditor\v1\Editor::register()`, `Club_Pages::post_id()`, `Setup_Controller::PAGE_SLUG`.
- Produces:
  - `Blueworx_Clubhouse_Page_Editors::register(): void` — hooks everything.
  - `Blueworx_Clubhouse_Page_Editors::slug_for( string $area ): string` — `'clubhouse-page-' . $area`, and `'clubhouse-global-content'` for Global.
  - `Blueworx_Clubhouse_Page_Editors::screens( ?Products $products = null ): array<int,array>` — the definitions, pure, for testing.
  - `Blueworx_Clubhouse_Page_Editors::editor_url( string $area ): string`

- [ ] **Step 1: Write the failing test**

`tests/php/PageEditorsTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageEditorsTest extends TestCase {

	/** @return array<string,array<string,mixed>> slug => screen */
	private function screens(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Editors::screens() as $screen ) {
			$out[ $screen['slug'] ] = $screen;
		}
		return $out;
	}

	public function test_every_screen_the_library_would_refuse_is_named(): void {
		foreach ( Blueworx_Clubhouse_Page_Editors::screens() as $screen ) {
			// validate() throws on anything wrong, naming the field. Letting it
			// through here would mean a live screen that says "not ready".
			\Blueworx\PageEditor\v1\Schema::validate( $screen );
			$this->addToAssertionCount( 1 );
		}
	}

	public function test_a_club_page_screen_stores_a_record_on_the_page_post_type(): void {
		$home = $this->screens()['clubhouse-page-home'];
		$this->assertSame( 'post', $home['store'] );
		$this->assertSame( 'page', $home['post_type'] );
	}

	public function test_global_content_stores_to_an_option(): void {
		$global = $this->screens()['clubhouse-global-content'];
		$this->assertSame( 'option', $global['store'] );
		$this->assertSame( 'clubhouse_global_content', $global['option_name'] );
	}

	public function test_every_club_page_screen_hangs_off_the_clubhouse_menu(): void {
		foreach ( $this->screens() as $slug => $screen ) {
			$this->assertSame( Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG, $screen['parent'], $slug );
		}
	}

	public function test_a_screen_declares_the_content_capability(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			$this->screens()['clubhouse-page-home']['capability']
		);
	}

	public function test_home_gets_three_tabs_and_a_short_page_gets_one(): void {
		$this->assertCount( 3, $this->screens()['clubhouse-page-home']['tabs'] );
		$this->assertCount( 1, $this->screens()['clubhouse-page-login']['tabs'] );
	}

	public function test_the_editor_url_carries_the_page_it_edits(): void {
		update_option( 'clubhouse_page_id_about', 91 );
		$this->assertStringContainsString( 'page=clubhouse-page-about', Blueworx_Clubhouse_Page_Editors::editor_url( 'about' ) );
		$this->assertStringContainsString( 'id=91', Blueworx_Clubhouse_Page_Editors::editor_url( 'about' ) );
	}
}
```

- [ ] **Step 2: Run it to see it fail**

Run: `vendor/bin/phpunit --filter PageEditorsTest`
Expected: FAIL with `Class "Blueworx_Clubhouse_Page_Editors" not found`.

- [ ] **Step 3: Write `Page_Editors`**

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The fifteen editor screens, declared to the page editor library.
 *
 * Fourteen edit a club page as a record, on the page's own post — which is
 * what gives them revisions, a slug, and the library's Publish and settings
 * tab. The fifteenth is global content, which has no page behind it and so
 * stores to an option.
 *
 * The fourteen have no menu item. A club page is reached from WordPress's own
 * Pages list, which is where somebody looking for a page looks; a second list
 * beside it is how an owner ends up on the wrong one. They are still
 * registered under the Clubhouse menu so that menu stays highlighted while one
 * is open — the item itself is removed straight afterwards.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Editors {

	public const GLOBAL_SLUG = 'clubhouse-global-content';

	public static function slug_for( string $area ): string {
		return Blueworx_Clubhouse_Page_Content::GLOBAL_AREA === $area
			? self::GLOBAL_SLUG
			: 'clubhouse-page-' . $area;
	}

	/** The address of an area's editor, carrying the record it edits. */
	public static function editor_url( string $area ): string {
		$url = 'admin.php?page=' . self::slug_for( $area );
		if ( Blueworx_Clubhouse_Page_Content::GLOBAL_AREA !== $area ) {
			$slug = Blueworx_Clubhouse_Club_Pages::slug_for_page_key( $area );
			$id   = null === $slug ? 0 : Blueworx_Clubhouse_Club_Pages::post_id( $slug );
			$url .= '&id=' . $id;
		}
		return function_exists( 'admin_url' ) ? admin_url( $url ) : $url;
	}

	/**
	 * The screen definitions. Pure — no hooks, no WordPress — so the test
	 * above can hold every one of them against Schema::validate() and a
	 * mistake is a red test rather than a live screen saying it is not ready.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function screens( ?Blueworx_Clubhouse_Products $products = null ): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Fields::areas( $products ) as $area => $spec ) {
			$global   = Blueworx_Clubhouse_Page_Content::GLOBAL_AREA === $area;
			$screen   = array(
				'slug'       => self::slug_for( $area ),
				'title'      => $global ? 'Global content' : $spec['label'],
				'menu_title' => $global ? 'Global content' : $spec['label'],
				'eyebrow'    => $global ? 'Clubhouse' : 'Club page',
				'lede'       => $global
					? 'The header, footer, welcome pack and cookie notice — the parts that appear on every page.'
					: sprintf( 'The words on your %s page. Nothing changes on the site until you save.', strtolower( $spec['label'] ) ),
				'capability' => Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
				'parent'     => Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG,
				'tabs'       => self::with_suggestions( $spec['tabs'] ),
			);
			if ( $global ) {
				$screen['store']       = 'option';
				$screen['option_name'] = 'clubhouse_global_content';
			} else {
				$screen['store']     = 'post';
				$screen['post_type'] = 'page';
			}
			$out[] = $screen;
		}
		return $out;
	}

	/** Task 5 fills this in. Until then it is the identity. */
	private static function with_suggestions( array $tabs ): array {
		return $tabs;
	}

	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'plugins_loaded', array( self::class, 'declare_screens' ), 5 );
		// After Screen::menu(), which runs at the default priority.
		add_action( 'admin_menu', array( self::class, 'hide_record_editors' ), 11 );
	}

	public static function declare_screens(): void {
		$products = class_exists( 'Blueworx_Clubhouse_Products' ) ? Blueworx_Clubhouse_Registry::products() : null;
		foreach ( self::screens( $products ) as $screen ) {
			\Blueworx\PageEditor\v1\Editor::register( $screen );
		}
	}

	/**
	 * Take the fourteen record editors back out of the menu. Global content
	 * keeps its item: it is the one area with no page in the Pages list
	 * standing for it, so without an item there would be no way to reach it.
	 */
	public static function hide_record_editors(): void {
		foreach ( self::screens() as $screen ) {
			if ( self::GLOBAL_SLUG === $screen['slug'] ) {
				continue;
			}
			remove_submenu_page( Blueworx_Clubhouse_Setup_Controller::PAGE_SLUG, $screen['slug'] );
		}
	}
}
```

Before writing this, **confirm `Schema::validate()` preserves unknown top-level keys** (`parent`, `menu_title`, `option_name`): read `Schema::validate()`'s return and check it returns the mutated `$screen` rather than a rebuilt array. It does today — `Screen::menu()` reads `$screen['parent']` — but confirm rather than assume.

- [ ] **Step 4: Repoint `Club_Page_Editing`**

In `includes/admin/class-club-page-editing.php`, `editor_url()` becomes:

```php
	/** The page's own editor screen, on the record behind it. */
	public static function editor_url( string $slug ): string {
		return Blueworx_Clubhouse_Page_Editors::editor_url( self::tab_for( $slug ) );
	}
```

`tab_for()` keeps its name and job — it is the ''→'home' rename, which is still exactly what is needed. `wants_block_editor()`, `filter_edit_link()` and `redirect_to_club_pages()` are unchanged: the body is still deliberately empty, so typing `post.php` must still land on the real editor. Update the three doc comments that say "Club Pages" to say "the page's own editor".

- [ ] **Step 5: Register and hook**

`includes/bootstrap.php`:

```php
require_once __DIR__ . '/pages/class-page-editors.php';
```

and call `Blueworx_Clubhouse_Page_Editors::register();` wherever the other admin registrations are made.

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit --filter PageEditorsTest`
Expected: PASS, all seven.

- [ ] **Step 7: Check it by hand in the harness**

```bash
npm run wp:up
```

Open `admin.php?page=clubhouse-page-home&id=<the home page id>`. Expected: the editor draws, three tabs plus **Publish & settings**, panels stacked, save bar reading "Everything is saved". The Clubhouse menu shows **Global content** and no fourteen page items. Pages → Home → Edit lands here.

- [ ] **Step 8: Commit**

```bash
git add includes/pages/class-page-editors.php includes/admin/class-club-page-editing.php includes/bootstrap.php tests/php/PageEditorsTest.php
git commit -m "Edit a club page on the page itself"
```

---

### Task 5: Link suggestions

The one part of the Club Pages screen worth carrying across. `Link_Catalogue::targets()` is what the menu editor already offers, so a link an owner can pick in the menu is a link they can pick anywhere.

**Files:**
- Modify: `includes/pages/class-page-editors.php`
- Test: `tests/php/PageEditorsTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Link_Catalogue::targets( Collections )` → `array<int,array{target:string,label:string,group:string,url:string}>`.
- Produces: nothing new; `with_suggestions()` stops being the identity.

- [ ] **Step 1: Write the failing test**

Append to `tests/php/PageEditorsTest.php`:

```php
	public function test_a_url_field_offers_the_sites_own_pages(): void {
		$fields = array();
		foreach ( Blueworx_Clubhouse_Page_Editors::screens() as $screen ) {
			foreach ( $screen['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					foreach ( $panel['fields'] as $field ) {
						if ( 'url' === ( $field['format'] ?? '' ) ) {
							$fields[] = $field;
						}
					}
				}
			}
		}
		$this->assertNotSame( array(), $fields, 'No url fields at all — the translation lost them.' );
		foreach ( $fields as $field ) {
			$this->assertNotEmpty( $field['suggestions'] ?? array(), $field['id'] );
			foreach ( $field['suggestions'] as $suggestion ) {
				$this->assertArrayHasKey( 'value', $suggestion );
				$this->assertArrayHasKey( 'label', $suggestion );
			}
		}
	}

	/**
	 * A menu target like "shop:dashboard" is a token this plugin resolves and a
	 * browser does not. These go into a free-text box that has to hold a link,
	 * so every suggestion is either a path or an absolute address.
	 */
	public function test_a_suggestion_is_an_address_and_not_a_target_token(): void {
		foreach ( Blueworx_Clubhouse_Page_Editors::screens() as $screen ) {
			foreach ( $screen['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					foreach ( $panel['fields'] as $field ) {
						foreach ( $field['suggestions'] ?? array() as $suggestion ) {
							$this->assertMatchesRegularExpression( '#^(/|https?://)#', $suggestion['value'], $field['id'] );
						}
					}
				}
			}
		}
	}
```

- [ ] **Step 2: Run to see it fail**

Run: `vendor/bin/phpunit --filter PageEditorsTest`
Expected: FAIL — `suggestions` is empty on every url field.

- [ ] **Step 3: Fill in `with_suggestions()`**

```php
	/**
	 * Offer every link this site can already make, on every url field.
	 *
	 * The same list the menu editor offers, resolved to real addresses — a
	 * menu target like "shop:dashboard" is a token this plugin understands and
	 * a browser does not, and these go into a free-text box that has to hold a
	 * link. The field stays free text: plenty of links point somewhere the
	 * plugin does not own.
	 */
	private static function with_suggestions( array $tabs ): array {
		$suggestions = self::link_suggestions();
		if ( array() === $suggestions ) {
			return $tabs;
		}
		foreach ( $tabs as &$tab ) {
			foreach ( $tab['panels'] as &$panel ) {
				foreach ( $panel['fields'] as &$field ) {
					if ( 'url' === ( $field['format'] ?? '' ) ) {
						$field['suggestions'] = $suggestions;
					}
				}
				unset( $field );
			}
			unset( $panel );
		}
		unset( $tab );
		return $tabs;
	}

	/** @return array<int,array{value:string,label:string}> */
	private static function link_suggestions(): array {
		if ( ! class_exists( 'Blueworx_Clubhouse_Link_Catalogue' ) ) {
			return array();
		}
		$out = array();
		foreach ( Blueworx_Clubhouse_Link_Catalogue::targets( Blueworx_Clubhouse_Registry::collections() ) as $target ) {
			$url = (string) ( $target['url'] ?? '' );
			if ( '' === $url ) {
				continue;
			}
			$out[] = array(
				'value' => $url,
				'label' => ( '' !== (string) ( $target['group'] ?? '' ) )
					? $target['group'] . ' · ' . $target['label']
					: (string) $target['label'],
			);
		}
		return $out;
	}
```

Repeater **cells** with `format => 'url'` get them too — phase 1 of the foundation confirmed `format` and `suggestions` are honoured on a cell. Extend the loop to walk `$field['fields']` when the field is a repeater, and add a test asserting a quick-tile's `href` cell carries them.

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit --filter PageEditorsTest`
Expected: PASS.

- [ ] **Step 5: Check it in the harness**

Open the Home editor, focus a link field. Expected: the browser's own suggestion list drops down with the site's pages, the shop, and the section anchors, and typing anything else is still accepted.

- [ ] **Step 6: Commit**

```bash
git add includes/pages/class-page-editors.php tests/php/PageEditorsTest.php
git commit -m "Offer the site's own links on every link field"
```

---

### Task 6: Point the front end at the page

The renderer's two read helpers keep their bodies. Everything else in this task is a type rename, and the render tests — which are extensive — are what proves it.

**Files:**
- Modify: `includes/render/class-page-renderer.php`, `includes/render/class-page-map.php`, `includes/frontend/class-frontend.php`, `includes/frontend/class-clubhouse-context.php`, `includes/dashboard/class-member-dashboard.php`, `includes/membership/class-welcome-pack.php`, `includes/social/class-manual-feed-source.php`
- Test: the existing render suite, plus one new test

**Interfaces:**
- Consumes: `Page_Content` (task 3).
- Produces: `Page_Renderer`'s public methods keep their names and their argument order; the `?Blueworx_Clubhouse_Content_Store` parameter becomes `?Blueworx_Clubhouse_Page_Content` in every signature.

- [ ] **Step 1: Write the failing test**

`tests/php/PageContentRenderTest.php`:

```php
	public function test_the_home_hero_renders_what_the_page_stores(): void {
		update_option( 'clubhouse_page_id_home', 42 );
		$content = new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set( 'home', 'hero', 'title_lead', 'Crewe Vagrants' );
		$html = Blueworx_Clubhouse_Page_Renderer::render( '', /* … the usual collaborators … */ $content );
		$this->assertStringContainsString( 'Crewe Vagrants', $html );
	}

	public function test_a_section_switched_off_on_its_own_panel_does_not_render(): void {
		update_option( 'clubhouse_page_id_home', 42 );
		$GLOBALS['wp_stub_postmeta'][42]['page_ticker__shown'] = '';
		$content = new Blueworx_Clubhouse_Page_Content( new Blueworx_Clubhouse_Fake_Storage() );
		$html    = Blueworx_Clubhouse_Page_Renderer::render( '', /* … */ $content );
		$this->assertStringNotContainsString( 'ch-ticker', $html );
	}
```

Copy the exact collaborator list from an existing test in `tests/php/` that already calls `Page_Renderer::render()` — do not invent the argument list.

- [ ] **Step 2: Run to see it fail**

Run: `vendor/bin/phpunit --filter PageContentRenderTest`
Expected: FAIL — `render()` will not accept a `Page_Content`.

- [ ] **Step 3: Rename the type**

```bash
grep -rl "Blueworx_Clubhouse_Content_Store" includes/render includes/frontend includes/dashboard includes/membership includes/social \
  | xargs sed -i 's/Blueworx_Clubhouse_Content_Store/Blueworx_Clubhouse_Page_Content/g'
```

Then read the diff. `cget()` and `citems()` bodies must be unchanged — the fallback rule (`null` or `''` means "use the hardcoded default") is what makes the front end identical before and after, and `Page_Content::get()` returns `null` for a field never written, which is the same signal `Content_Store` gave.

- [ ] **Step 4: Move section visibility onto the panel switch**

`Page_Renderer` asks `$visibility->is_section_visible( $page, $section )` in many places. Change each to `$content->is_section_shown( $page, $section )`, falling back to `true` when `$content` is null — matching what the old call did with no store.

Add a small helper beside `cget`/`citems` rather than repeating the null check:

```php
	/** Whether a section's own Shown switch is on. No store means nothing hidden. */
	private static function cshown( ?Blueworx_Clubhouse_Page_Content $c, string $page, string $sec ): bool {
		return null === $c ? true : $c->is_section_shown( $page, $sec );
	}
```

`Visibility::is_section_visible()` and `set_section_visible()` stay for now — task 9 deletes them, after the Setup screen stops calling them.

- [ ] **Step 5: Run the whole suite**

Run: `vendor/bin/phpunit`
Expected: PASS. Failures here are the rename catching a call site the sed missed, or a test constructing a `Content_Store` — fix the test to construct a `Page_Content` and set `clubhouse_page_id_<page>` first.

- [ ] **Step 6: Commit**

```bash
git add -A includes tests/php
git commit -m "Render a club page from the page it belongs to"
```

---

### Task 7: Repoint the import

Four write sites, one construction. The import's plan and preview address content as `page/section/field`, which has not changed — only where that address resolves to.

**Files:**
- Modify: `includes/import/class-import-applier.php`
- Test: the existing import suite

- [ ] **Step 1: Run the import tests to see them pass first**

Run: `vendor/bin/phpunit --filter Import`
Expected: PASS — this is the baseline the change must not move.

- [ ] **Step 2: Swap the store**

In `class-import-applier.php` line 29, `new Blueworx_Clubhouse_Content_Store( $storage )` becomes `new Blueworx_Clubhouse_Page_Content( $storage )`. The four `set`/`set_items` calls are unchanged — the methods have the same names and the same arguments.

`place_image()` writes an attachment id, which is what the `media` kind wants, so that path needs nothing.

- [ ] **Step 3: Point `Import_Applier`'s tests at pages**

Each import test that asserts a stored value must first `update_option( 'clubhouse_page_id_<page>', <id> )`, or the write goes nowhere. Add it to the suite's `setUp()`.

- [ ] **Step 4: Run the import tests**

Run: `vendor/bin/phpunit --filter Import`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/import tests/php
git commit -m "Import writes to the page, not the option"
```

---

### Task 8: The migration

One club, one run. It copies what is in the option onto the pages, reports anything it could not place, and is deleted in phase 4.

**Files:**
- Create: `includes/pages/class-content-migration.php`, `docs/upgrades/2026-08-28-club-pages-become-records.md`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/ContentMigrationTest.php`

**Interfaces:**
- Consumes: `Content_Store` (the last thing that reads it), `Page_Fields`, `Page_Content`, `Visibility`.
- Produces:
  - `Blueworx_Clubhouse_Content_Migration::run( Blueworx_Clubhouse_Storage $storage ): array` — `array{moved:int,skipped:array<int,string>,pages:array<string,int>}`
  - `Blueworx_Clubhouse_Content_Migration::has_run( Blueworx_Clubhouse_Storage $storage ): bool`

- [ ] **Step 1: Write the failing test**

`tests/php/ContentMigrationTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ContentMigrationTest extends TestCase {

	private Blueworx_Clubhouse_Fake_Storage $storage;

	protected function setUp(): void {
		$GLOBALS['wp_stub_postmeta'] = array();
		$GLOBALS['wp_stub_options']  = array();
		$this->storage               = new Blueworx_Clubhouse_Fake_Storage();
		update_option( 'clubhouse_page_id_home', 42 );
		update_option( 'clubhouse_page_id_about', 43 );
	}

	public function test_a_field_arrives_at_its_new_address(): void {
		$this->storage->set( 'content_home', array( 'hero' => array( 'title_lead' => 'Crewe Vagrants' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( 'Crewe Vagrants', $GLOBALS['wp_stub_postmeta'][42]['page_hero_title_lead'] );
	}

	public function test_rows_arrive_as_one_value(): void {
		$rows = array( array( 'text' => 'Match on Saturday' ) );
		$this->storage->set( 'content_home', array( 'ticker' => array( 'items' => $rows ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( $rows, $GLOBALS['wp_stub_postmeta'][42]['page_ticker_items'] );
	}

	public function test_a_switch_keeps_its_state_and_its_type(): void {
		$this->storage->set( 'visibility', array( 'sections' => array( 'home.social_feed' => false ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$content = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertFalse( $content->is_section_shown( 'home', 'social_feed' ) );
	}

	public function test_a_section_nobody_touched_arrives_shown(): void {
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$content = new Blueworx_Clubhouse_Page_Content( $this->storage );
		$this->assertTrue( $content->is_section_shown( 'home', 'hero' ) );
	}

	public function test_global_content_goes_to_its_own_option(): void {
		$this->storage->set( 'content_global', array( 'header' => array( 'join' => 'Join us' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( 'Join us', $this->storage->get( 'global_content', array() )['header_join'] );
	}

	public function test_a_field_never_saved_is_not_written_at_all(): void {
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertArrayNotHasKey( 'page_hero_title_lead', $GLOBALS['wp_stub_postmeta'][42] ?? array() );
	}

	/**
	 * Image fields have held two shapes: an attachment id, and a raw URL from
	 * a demo or a preview. The media kind is an integer, so a raw URL would
	 * cast to 0 and the picture would vanish. Anything that cannot be resolved
	 * to an attachment is left where it is and named in the report.
	 */
	public function test_an_image_that_is_not_an_attachment_is_reported_and_not_written(): void {
		$this->storage->set( 'content_home', array( 'clubhouse' => array( 'image' => 'https://example.test/x.jpg' ) ) );
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertContains( 'home/clubhouse/image', $result['skipped'] );
		$this->assertArrayNotHasKey( 'page_clubhouse_image', $GLOBALS['wp_stub_postmeta'][42] ?? array() );
	}

	public function test_a_page_with_no_post_behind_it_is_reported(): void {
		delete_option( 'clubhouse_page_id_about' );
		$this->storage->set( 'content_about', array( 'hero' => array( 'title_lead' => 'About us' ) ) );
		$result = Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertContains( 'about/hero/title_lead', $result['skipped'] );
	}

	public function test_running_twice_changes_nothing_the_second_time(): void {
		$this->storage->set( 'content_home', array( 'hero' => array( 'title_lead' => 'Crewe Vagrants' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$first  = $GLOBALS['wp_stub_postmeta'];
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertSame( $first, $GLOBALS['wp_stub_postmeta'] );
	}

	public function test_the_old_option_is_left_alone(): void {
		$this->storage->set( 'content_home', array( 'hero' => array( 'title_lead' => 'Crewe Vagrants' ) ) );
		Blueworx_Clubhouse_Content_Migration::run( $this->storage );
		$this->assertNotSame( array(), $this->storage->get( 'content_home', array() ) );
	}
}
```

- [ ] **Step 2: Run to see it fail**

Run: `vendor/bin/phpunit --filter ContentMigrationTest`
Expected: FAIL with `Class "Blueworx_Clubhouse_Content_Migration" not found`.

- [ ] **Step 3: Write the migration**

Walk `Page_Fields::areas()`, not the old catalogue — the new shape is the target, and anything in the option that no longer has an address is content nothing would ever have rendered.

```php
	/**
	 * @return array{moved:int,skipped:array<int,string>,pages:array<string,int>}
	 */
	public static function run( Blueworx_Clubhouse_Storage $storage ): array {
		$old        = new Blueworx_Clubhouse_Content_Store( $storage );
		$new        = new Blueworx_Clubhouse_Page_Content( $storage );
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$moved      = 0;
		$skipped    = array();
		$pages      = array();

		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $area => $spec ) {
			foreach ( $spec['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					$section = (string) $panel['id'];

					foreach ( $panel['fields'] as $field ) {
						$key = self::field_key( $section, (string) $field['id'] );
						if ( '' === $key ) {
							continue; // copytext and the auto-declared switch: nothing was ever stored.
						}
						// … read, place, count, or record in $skipped …
					}

					if ( ! empty( $panel['hideable'] ) ) {
						$new->set( $area, $section, '_shown', $visibility->is_section_visible( $area, $section ) );
					}
				}
			}
			$pages[ $area ] = $moved;
		}

		return array( 'moved' => $moved, 'skipped' => $skipped, 'pages' => $pages );
	}
```

Rules the body must follow:

- **Only write what was written.** `Content_Store::get()` with a `null` default; `null` means never saved and nothing is written. This is what keeps `test_a_field_never_saved_is_not_written_at_all` green, and it is what makes the front end identical: an unwritten field falls through to the renderer's own default either way.
- **Repeaters** read `get_items()` and write `set_items()`; an empty list is not written.
- **Toggles** are written as the boolean they are. `Page_Content` casts on the way back out.
- **Media** — a numeric value is written as an int. A non-numeric one is put through `attachment_url_to_postid()`; a hit is written, a miss is added to `$skipped` and not written. Never write a URL into a media field.
- **A page with no post behind it** — every field for that area goes into `$skipped`.
- **Idempotent.** Running twice writes the same values, so a half-finished run can simply be re-run.
- **The old option is never deleted.** It is the only copy of the previous state and it costs nothing to leave.

- [ ] **Step 4: Give it a way to be run**

A WP-CLI command is the honest shape for a one-off — but this plugin has no CLI surface, so add it as a button on the Import screen instead, which is already the "act on the site" screen. One button, "Move club page content onto the pages", showing the report: how many values moved, and every address skipped, with why.

If the button is more than a task's worth of work, run the migration through `wp eval-file` in the harness and on the club's site, and record the command in the upgrade doc. Decide this when you get here; the report matters more than the button.

- [ ] **Step 5: Run the tests**

Run: `vendor/bin/phpunit --filter ContentMigrationTest`
Expected: PASS, all ten.

- [ ] **Step 6: The parity check**

This is the acceptance test for the whole phase.

```bash
npm run wp:up
```

1. Before migrating, save the rendered HTML of all fourteen pages: `for p in "" about membership contact sports teams news events calendar booking login privacy terms rules; do curl -s "http://localhost:8705/$p" > /tmp/before-${p:-home}.html; done`
2. Run the migration.
3. Save the same fourteen again as `after-*.html`.
4. `diff` each pair.

Expected: **no differences.** A difference is a bug in `Page_Fields` (a field translated to the wrong kind), in the migration (a value not placed), or in `Page_Content` (a cast that changed a value). Fix the cause, not the diff.

- [ ] **Step 7: Write the upgrade record**

`docs/upgrades/2026-08-28-club-pages-become-records.md`, following `2026-08-21-club-pages-become-real-pages.md`: what moved, what to run, what the report means, and what to check afterwards.

- [ ] **Step 8: Commit**

```bash
git add includes/pages/class-content-migration.php docs/upgrades tests/php/ContentMigrationTest.php includes/bootstrap.php
git commit -m "Move club page content onto the pages"
```

---

### Task 9: Setup's Visibility tab loses its sections

Per-section on/off now lives on each panel's own Shown switch, on the page that section belongs to. Two lists of the same switches is how they end up disagreeing.

**Files:**
- Modify: `includes/admin/class-setup-controller.php`, `includes/admin/class-setup-screen.php`, `includes/content/class-visibility.php`
- Delete: `includes/admin/class-setup-sections.php`, `tests/php/SetupSectionsTest.php` (and the lockstep test against the catalogue, wherever it lives)
- Test: existing Setup tests

Setup itself is rebuilt on the library in phase 4. This task only removes the half that has moved.

- [ ] **Step 1: Find the lockstep test**

Run: `grep -rln "inventory\|Setup_Sections" tests/php includes`
Every hit is either a caller to remove or a test to delete.

- [ ] **Step 2: Write the failing test**

In the Setup screen's existing test file:

```php
	public function test_the_visibility_tab_no_longer_lists_sections(): void {
		$html = /* … render the Visibility tab, as the existing tests do … */;
		$this->assertStringNotContainsString( 'Sections', $html );
		$this->assertStringContainsString( 'Pages', $html );
	}
```

- [ ] **Step 3: Run to see it fail**

Run: `vendor/bin/phpunit --filter Setup`
Expected: FAIL — the tab still lists sections.

- [ ] **Step 4: Remove the section half**

Take the section list out of `Setup_Screen`'s Visibility tab and out of `Setup_Controller`'s save handler. The page switches stay exactly as they are — phase 4 turns them into the library's page controller; this task does not touch them.

Delete `Setup_Sections`, `Visibility::is_section_visible()` and `Visibility::set_section_visible()`, and `Visibility::SECTION_DEFAULTS`. `is_page_visible()` and `set_page_visible()` stay.

- [ ] **Step 5: Run the whole suite**

Run: `vendor/bin/phpunit`
Expected: PASS. Anything still calling `is_section_visible()` is a caller task 6 missed.

- [ ] **Step 6: Commit**

```bash
git add -A includes tests/php
git commit -m "A section is switched off where it lives"
```

---

### Task 10: Delete Club Pages

Nothing reads the old system now. It goes in the same release, because leaving it would mean two editors writing to two places with no way to tell which a club had used.

**Files:**
- Delete: `includes/admin/class-content-controller.php`, `class-content-screen.php`, `class-content-catalogue.php`, `includes/content/class-content-store.php`, `class-content-sanitiser.php`, `assets/css/admin-content.css`, `assets/js/admin-content.js`, and every test naming them.
- Modify: `includes/bootstrap.php`, `tests/php/bootstrap.php`

- [ ] **Step 1: Find every reference**

Run: `grep -rn "Content_Controller\|Content_Screen\|Content_Catalogue\|Content_Store\|Content_Sanitiser\|admin-content" includes tests assets .github docs`

`Content_Catalogue::address_label()` is used by the import preview and the images-needed notice — those need an equivalent on `Page_Fields` before the catalogue can go. Add `Page_Fields::address_label( string $address ): string` with the same "Tab · Section" output, and repoint both callers, before deleting anything.

- [ ] **Step 2: Delete, and drop the requires**

```bash
git rm includes/admin/class-content-controller.php includes/admin/class-content-screen.php \
       includes/admin/class-content-catalogue.php includes/content/class-content-store.php \
       includes/content/class-content-sanitiser.php \
       assets/css/admin-content.css assets/js/admin-content.js
```

Remove the matching `require_once` lines from `includes/bootstrap.php` and `tests/php/bootstrap.php`, and the `Content_Controller::register()` call.

The migration's own `Content_Store` read (task 8) goes with it — by this point the club's site is across, which is the whole premise of a one-off. If the migration has not yet been run on the live site, **stop and run it before this task.**

- [ ] **Step 3: Delete the lockstep test**

`PageFieldsTest::test_every_catalogue_field_has_a_counterpart` cannot survive the catalogue. Delete that one method; the other four stay.

- [ ] **Step 4: Run everything**

Run: `vendor/bin/phpunit`
Expected: PASS.

Run: `node ../bluegroup_core_foundation/scripts/check-admin-ui-adherence.mjs`
Expected: clean. The check judges only changed files, and every screen this PR touches is now either the library's or the design system's.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "Delete the Club Pages screen"
```

---

### Task 11: Browser coverage, version, changelog

**Files:**
- Create: `tests/club-page-editor.spec.js`
- Modify: `blueworx-labs-clubhouse.php`, `CHANGELOG.md`

- [ ] **Step 1: Write the spec**

`tests/club-page-editor.spec.js`, tagged `@wordpress` — it needs real WordPress, a real page and a real record.

```js
const { test, expect } = require('@playwright/test');

test.describe('@wordpress club page editor', () => {
  test('a change wakes the save bar, survives a tab switch, and saves clean', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=clubhouse-page-home&id=' + process.env.CLUBHOUSE_HOME_ID);
    await expect(page.locator('.bw-savebar')).toContainText('Everything is saved');

    await page.getByLabel('Heading').first().fill('Crewe Vagrants');
    await expect(page.locator('.bw-savebar')).not.toContainText('Everything is saved');

    await page.getByRole('tab', { name: 'The club' }).click();
    await page.getByRole('tab', { name: 'Top of the page' }).click();
    await expect(page.getByLabel('Heading').first()).toHaveValue('Crewe Vagrants');

    await page.getByRole('button', { name: /save/i }).click();
    await expect(page.locator('.bw-savebar')).toContainText('Everything is saved');
  });

  test('the Pages list opens the page in its own editor', async ({ page }) => {
    await page.goto('/wp-admin/edit.php?post_type=page');
    await page.getByRole('link', { name: 'Home' }).first().click();
    await expect(page).toHaveURL(/page=clubhouse-page-home/);
  });

  test('a section switched off on its panel leaves the page', async ({ page }) => {
    // … toggle the Ticker panel's Shown switch, save, then check the front end …
  });
});
```

Read `tests/harness.js` and an existing `@wordpress` spec first — reuse how they sign in and how they discover ids, rather than inventing an env var if one already exists.

- [ ] **Step 2: Run them properly**

```bash
npm run wp:up
npm run test:wp
```

Expected: PASS. A preview-only run silently skips every `@wordpress` spec, so a green local run without `wp:up` proves nothing — this is what broke PR #292.

- [ ] **Step 3: Bump and record**

Minor bump — this is a feature. `0.97.1` → `0.98.0` in `blueworx-labs-clubhouse.php`, matched by a `CHANGELOG.md` entry in the club's words:

```markdown
## 0.98.0

- Your club pages are now edited on the pages themselves. Find a page under Pages, press Edit, and its words are there — with every change kept, so you can go back to what you had before.
- You can change a page's address, and set who can see it, from the page itself.
- Switching a section off now happens on that section, on the page it belongs to, instead of on a separate list.
- Header, footer, welcome pack and cookie notice have moved to Clubhouse → Global content.
```

- [ ] **Step 4: Lint once**

Run the linter once as a final check. **Do not fix anything.** Present the findings at the end and let Luke decide.

- [ ] **Step 5: Open the pull request**

```bash
git push -u origin club-pages-become-records
gh pr create --title "Club pages are edited on the pages themselves" --body "…"
```

The body says what it does and what needs deciding — not a walkthrough.

- [ ] **Step 6: Wait for CI, then merge**

All four checks green before merging. `gh pr merge --squash --delete-branch`.

---

## Notes for whoever executes this

**The one thing that will bite.** Post meta and options do not store the same things. An option holds a PHP array with its types intact; post meta round-trips a boolean `false` as `''` and an integer as a string. That is why `Page_Content` casts, why the migration writes booleans and ints rather than whatever was in the option, and why the parity check in task 8 is the real test rather than the unit tests.

**Tasks 1–5 build, tasks 6–8 switch over, tasks 9–11 clean up.** Tasks 6, 7 and 8 must land together or the site renders from an empty store. If the work has to stop part-way, stop after task 5 — the new editors exist, nothing reads them yet, and the site is untouched.

**Do not touch phase 4's work.** Setup is rebuilt on the library next, absorbing #283, #284 and #285. This phase only removes Setup's per-section switches; the page switches, the Members/Settings split and the menu editor's second save bar are all phase 4.
