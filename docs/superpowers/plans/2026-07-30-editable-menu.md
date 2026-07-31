# Editable Header Menu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a club owner reorder, rename and nest the header nav, and point any nav item — or any link field in Club Content — at a page, a section anchor or a filtered collection view, chosen from a shared catalogue instead of typed.

**Architecture:** A new `Link_Catalogue` enumerates and resolves every linkable target as a string tag (`page:about`, `anchor:about.history`, `filter:sports:netball`, `url:https://…`). A new `Menu` class stores the nav tree against the existing `Storage` seam and resolves it to render-ready items, falling back to today's hardcoded nine when nothing is stored. `Sections::header()` grows one level of children. A "Menu" tab in Club Content edits the tree with plain form posts.

**Tech Stack:** PHP 7.4+ procedural-OO WordPress plugin, no framework. PHPUnit for unit tests (`composer test`), Playwright for browser tests (`npm run test:wp`). No new dependencies — `approved-deps.json` is unchanged by this work.

## Global Constraints

- **No new dependencies.** Adding one requires prior approval in `approved-deps.json`. This plan adds none.
- **Every class is `final`, `declare(strict_types=1)`, guarded by `if ( ! defined( 'ABSPATH' ) ) { exit; }`,** and tagged `@package BlueworxLabsClubhouse` — match the surrounding files exactly.
- **Tabs, not spaces,** for indentation in PHP. WordPress brace and spacing style (`function foo( $a )`), enforced by `composer lint` (PHPCS).
- **New `includes/` files must be added to `includes/bootstrap.php`'s require list** in the right group, or nothing loads them.
- **The DB-free preview (`preview/index.php`) and the WordPress frontend must render byte-identical bodies.** Anything reading storage must degrade to a default when storage is absent.
- **No JavaScript is required for any feature here.** The submenu opens on CSS `:hover`/`:focus-within`; the menu editor reorders via form posts.
- **Version:** minor bump `0.45.0` → `0.46.0` in `blueworx-labs-clubhouse.php` (both the header comment and `BLUEWORX_LABS_CLUBHOUSE_VERSION`), with a matching `CHANGELOG.md` entry. Task 9.
- **Lint once at the end.** Do not loop lint→fix→lint. Run `composer lint` in Task 9 and report findings; do not action them without approval.

## File Structure

**Create:**
- `includes/content/class-link-catalogue.php` — enumerates and resolves link targets. Pure; takes a `Collections` instance as an argument rather than holding one.
- `includes/content/class-menu.php` — stores, defaults and resolves the nav tree.
- `includes/admin/class-menu-panel.php` — renders the Menu tab's HTML. Pure string building, like `Content_Screen`.
- `tests/php/LinkCatalogueTest.php`, `tests/php/MenuTest.php`, `tests/php/MenuPanelTest.php`, `tests/php/SectionAnchorTest.php`
- `tests/menu-editor.spec.js` — Playwright.

**Modify:**
- `includes/bootstrap.php` — require the three new classes.
- `includes/render/class-page-renderer.php` — `slugify()` becomes public; new `anchored()`; every `Sections::` call in a page method wrapped by it; `shell_header()` takes `$collections` and asks `Menu` for its nav.
- `includes/render/class-sections.php` — `header()` renders `children`.
- `assets/css/*` — submenu styles (see Task 5 for which file).
- `includes/admin/class-content-screen.php` — Menu tab in the tab row; datalist fed from `Link_Catalogue`.
- `includes/admin/class-content-controller.php` — save branch for the Menu tab.
- `tests/php/bootstrap.php` — require `class-menu-panel.php` if bootstrap grouping puts it outside the plugin bootstrap (it does not — admin classes are in `includes/bootstrap.php`; no change expected, verify).

---

### Task 1: `Link_Catalogue` — pages and section anchors

**Files:**
- Create: `includes/content/class-link-catalogue.php`
- Modify: `includes/bootstrap.php` (require, in the content group after `class-visibility.php`)
- Modify: `includes/render/class-page-renderer.php` (`slugify` private → public)
- Test: `tests/php/LinkCatalogueTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Page_Map::available()`, `Blueworx_Clubhouse_Content_Catalogue::pages()`, `Blueworx_Clubhouse_Links::url()`.
- Produces:
  - `Blueworx_Clubhouse_Link_Catalogue::targets( Blueworx_Clubhouse_Collections $collections ): array` — list of `array{target:string,label:string,group:string,url:string}`.
  - `Blueworx_Clubhouse_Link_Catalogue::resolve( string $target, Blueworx_Clubhouse_Collections $collections ): string` — href, or `''` when unresolvable.
  - `Blueworx_Clubhouse_Link_Catalogue::anchor_id( string $page, string $section ): string` — `ch-<page>-<section>`.
  - `Blueworx_Clubhouse_Page_Renderer::slugify( string $s ): string` — now public.

- [ ] **Step 1: Write the failing test**

Create `tests/php/LinkCatalogueTest.php`:

```php
<?php
// tests/php/LinkCatalogueTest.php

use PHPUnit\Framework\TestCase;

final class LinkCatalogueTest extends TestCase {

	private function collections(): Blueworx_Clubhouse_Demo_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	/** @return array<int,array{target:string,label:string,group:string,url:string}> */
	private function targets(): array {
		return Blueworx_Clubhouse_Link_Catalogue::targets( $this->collections() );
	}

	private function find( string $target ): ?array {
		foreach ( $this->targets() as $entry ) {
			if ( $entry['target'] === $target ) {
				return $entry;
			}
		}
		return null;
	}

	public function test_every_available_page_is_offered_as_a_page_target(): void {
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $page ) {
			$key   = '' === $page['slug'] ? 'home' : $page['slug'];
			$entry = $this->find( 'page:' . $key );
			$this->assertNotNull( $entry, "missing page target for {$key}" );
			$this->assertSame( 'Pages', $entry['group'] );
			$this->assertSame( $page['label'], $entry['label'] );
		}
	}

	public function test_anchor_targets_exist_for_catalogue_sections(): void {
		$entry = $this->find( 'anchor:about.history' );
		$this->assertNotNull( $entry );
		$this->assertSame( 'Sections', $entry['group'] );
		$this->assertSame( 'About → History', $entry['label'] );
		$this->assertStringContainsString( '#ch-about-history', $entry['url'] );
	}

	public function test_anchor_id_is_derived_from_page_and_section_keys(): void {
		$this->assertSame( 'ch-about-history', Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'about', 'history' ) );
		$this->assertSame( 'ch-home-quick-tiles', Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'home', 'quick_tiles' ) );
	}

	public function test_resolve_returns_the_url_for_a_known_page(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Links::url( 'about' ),
			Blueworx_Clubhouse_Link_Catalogue::resolve( 'page:about', $this->collections() )
		);
	}

	public function test_resolve_returns_empty_for_an_unknown_page(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( 'page:nope', $this->collections() ) );
	}

	public function test_resolve_returns_empty_for_an_unknown_anchor(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( 'anchor:about.nope', $this->collections() ) );
	}

	public function test_resolve_passes_a_custom_url_through(): void {
		$this->assertSame(
			'https://example.test/x',
			Blueworx_Clubhouse_Link_Catalogue::resolve( 'url:https://example.test/x', $this->collections() )
		);
	}

	public function test_resolve_rejects_a_javascript_url(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( 'url:javascript:alert(1)', $this->collections() ) );
	}

	public function test_resolve_returns_empty_for_a_malformed_target(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( 'nonsense', $this->collections() ) );
		$this->assertSame( '', Blueworx_Clubhouse_Link_Catalogue::resolve( '', $this->collections() ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter LinkCatalogueTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Link_Catalogue" not found`.

- [ ] **Step 3: Make `slugify` public**

In `includes/render/class-page-renderer.php`, change the declaration only — do not move it:

```php
	/** Lowercase, hyphenated slug — the one place a label becomes a filter slug.
	 * Public because Link_Catalogue builds filter targets from the same labels
	 * the pill rows do; two implementations would drift. */
	public static function slugify( string $s ): string {
		$s = strtolower( trim( $s ) );
		return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $s ), '-' );
	}
```

- [ ] **Step 4: Write the catalogue (pages and anchors only)**

Create `includes/content/class-link-catalogue.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every place inside this site an owner can point a link, as a flat list of
 * target tags. One catalogue serves both the menu editor and the URL fields in
 * Club Content, so the two can never offer different destinations.
 *
 * A target is a tagged string rather than a URL, because a URL cannot say what
 * it meant: a stored '/about' does not know it was "the About page" and so
 * cannot follow a rename or disappear with the page. The tags are:
 *
 *   page:<key>              a plugin page          → /about
 *   anchor:<page>.<section> a section of a page    → /about#ch-about-history
 *   filter:<page>:<slug>    a filtered list view   → /sports?clubhouse_filter=netball
 *   url:<href>              anything else          → itself
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Link_Catalogue {

	/**
	 * The id a section's markup carries and an anchor target points at. Section
	 * keys are snake_case in the catalogue ('quick_tiles') and hyphenated in
	 * markup, so this is the one place the two spellings meet.
	 */
	public static function anchor_id( string $page, string $section ): string {
		return 'ch-' . str_replace( '_', '-', $page ) . '-' . str_replace( '_', '-', $section );
	}

	/**
	 * Everything linkable, in group order. Collections are passed in rather than
	 * constructed so the preview and the tests can offer fixture content.
	 *
	 * @return array<int,array{target:string,label:string,group:string,url:string}>
	 */
	public static function targets( Blueworx_Clubhouse_Collections $collections ): array {
		return array_merge( self::pages(), self::anchors() );
	}

	/** @return array<int,array{target:string,label:string,group:string,url:string}> */
	private static function pages(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $page ) {
			$key   = '' === $page['slug'] ? 'home' : $page['slug'];
			$out[] = array(
				'target' => 'page:' . $key,
				'label'  => (string) $page['label'],
				'group'  => 'Pages',
				'url'    => Blueworx_Clubhouse_Links::url( $key ),
			);
		}
		return $out;
	}

	/**
	 * One target per editable section, labelled "Page → Section" so a long list
	 * stays scannable. Sections of a page the site cannot serve are skipped —
	 * the catalogue's tabs and Page_Map's slugs share their spelling except for
	 * Home, whose slug is ''.
	 *
	 * @return array<int,array{target:string,label:string,group:string,url:string}>
	 */
	private static function anchors(): array {
		$out = array();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$tab  = (string) $page['tab'];
			$slug = 'home' === $tab ? '' : $tab;
			if ( 'global' === $tab || ! Blueworx_Clubhouse_Page_Map::is_available( $slug ) ) {
				continue;
			}
			foreach ( $page['sections'] as $section ) {
				$key   = (string) $section['key'];
				$out[] = array(
					'target' => 'anchor:' . $tab . '.' . $key,
					'label'  => (string) $page['label'] . ' → ' . (string) $section['label'],
					'group'  => 'Sections',
					'url'    => Blueworx_Clubhouse_Links::url( $tab ) . '#' . self::anchor_id( $tab, $key ),
				);
			}
		}
		return $out;
	}

	/**
	 * A target tag's href, or '' when it no longer names anything this site can
	 * serve. Callers treat '' as "drop this link" — a link that goes nowhere is
	 * worse than one that is not shown.
	 */
	public static function resolve( string $target, Blueworx_Clubhouse_Collections $collections ): string {
		if ( 0 === strpos( $target, 'url:' ) ) {
			return self::safe_url( substr( $target, 4 ) );
		}
		foreach ( self::targets( $collections ) as $entry ) {
			if ( $entry['target'] === $target ) {
				return $entry['url'];
			}
		}
		return '';
	}

	/**
	 * Reject every scheme but http, https, mailto, tel and site-relative — a
	 * stored 'javascript:' must never reach an href, however it got in.
	 */
	private static function safe_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( '/' === $url[0] || '#' === $url[0] || '?' === $url[0] ) {
			return $url;
		}
		if ( (bool) preg_match( '#^(https?://|mailto:|tel:)#i', $url ) ) {
			return $url;
		}
		return '';
	}
}
```

- [ ] **Step 5: Require it from the bootstrap**

In `includes/bootstrap.php`, in the content group, immediately after the `class-visibility.php` line:

```php
require_once __DIR__ . '/content/class-link-catalogue.php';
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `composer test -- --filter LinkCatalogueTest`
Expected: PASS (10 tests).

Then run the whole suite to confirm making `slugify` public broke nothing:

Run: `composer test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/content/class-link-catalogue.php includes/bootstrap.php includes/render/class-page-renderer.php tests/php/LinkCatalogueTest.php
git commit -m "feat: link catalogue of pages and section anchors"
```

---

### Task 2: `Link_Catalogue` — filtered collection views

**Files:**
- Modify: `includes/content/class-link-catalogue.php`
- Test: `tests/php/LinkCatalogueTest.php`

**Interfaces:**
- Consumes: Task 1's `targets()`/`resolve()`; `Blueworx_Clubhouse_Links::filtered_url()`; `Blueworx_Clubhouse_Page_Renderer::slugify()`.
- Produces: `filter:<page>:<slug>` targets in groups `Sports`, `Teams`, `Events`.

**Note on labels — read before writing code.** The three filtered pages do *not* all filter by their own item titles:
- `/sports` filters by each **sport's `title`** (`Rugby`, `Tennis`, …).
- `/teams` filters by each team's **`sport`** (`Rugby`, `Cricket`, …) — *not* the team name, because the page's pill row is "Filter teams by sport".
- `/events` filters by each event's **`tag`**.

Offering a target the pill row cannot produce would create a link to an empty list, so the catalogue reads the same fields.

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/LinkCatalogueTest.php`:

```php
	public function test_sports_targets_come_from_sport_titles(): void {
		$entry = $this->find( 'filter:sports:rugby' );
		$this->assertNotNull( $entry );
		$this->assertSame( 'Sports', $entry['group'] );
		$this->assertSame( 'Sports → Rugby', $entry['label'] );
		$this->assertStringContainsString( 'clubhouse_filter=rugby', $entry['url'] );
	}

	public function test_teams_targets_come_from_the_team_sport_not_the_team_name(): void {
		$this->assertNotNull( $this->find( 'filter:teams:rugby' ) );
		$this->assertNull( $this->find( 'filter:teams:1st-xv' ) );
	}

	public function test_collection_targets_are_deduplicated(): void {
		$seen = array();
		foreach ( $this->targets() as $entry ) {
			$this->assertNotContains( $entry['target'], $seen, 'duplicate target ' . $entry['target'] );
			$seen[] = $entry['target'];
		}
	}

	public function test_resolve_returns_empty_for_a_collection_item_that_is_gone(): void {
		$this->assertSame(
			'',
			Blueworx_Clubhouse_Link_Catalogue::resolve( 'filter:sports:quidditch', $this->collections() )
		);
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter LinkCatalogueTest`
Expected: FAIL — `find()` returns null for `filter:sports:rugby`.

- [ ] **Step 3: Implement**

In `class-link-catalogue.php`, extend `targets()` and add `filters()`:

```php
	public static function targets( Blueworx_Clubhouse_Collections $collections ): array {
		return array_merge( self::pages(), self::anchors(), self::filters( $collections ) );
	}

	/**
	 * Filtered list views, one per distinct pill the page would render. Read the
	 * same field each page's pill row reads — /teams filters by the team's sport,
	 * not its name — so every target here corresponds to a pill that exists and
	 * a list that has something in it.
	 *
	 * @return array<int,array{target:string,label:string,group:string,url:string}>
	 */
	private static function filters( Blueworx_Clubhouse_Collections $collections ): array {
		$groups = array(
			array( 'page' => 'sports', 'group' => 'Sports', 'rows' => $collections->sports(), 'field' => 'title' ),
			array( 'page' => 'teams',  'group' => 'Teams',  'rows' => $collections->teams(),  'field' => 'sport' ),
			array( 'page' => 'events', 'group' => 'Events', 'rows' => $collections->events(), 'field' => 'tag' ),
		);
		$out = array();
		foreach ( $groups as $g ) {
			if ( ! Blueworx_Clubhouse_Page_Map::is_available( $g['page'] ) ) {
				continue;
			}
			$seen = array();
			foreach ( $g['rows'] as $row ) {
				$label = trim( (string) ( $row[ $g['field'] ] ?? '' ) );
				$slug  = Blueworx_Clubhouse_Page_Renderer::slugify( $label );
				if ( '' === $slug || in_array( $slug, $seen, true ) ) {
					continue;
				}
				$seen[] = $slug;
				$out[]  = array(
					'target' => 'filter:' . $g['page'] . ':' . $slug,
					'label'  => $g['group'] . ' → ' . $label,
					'group'  => $g['group'],
					'url'    => Blueworx_Clubhouse_Links::filtered_url( $g['page'], $slug ),
				);
			}
		}
		return $out;
	}
```

`resolve()` needs no change — it already scans `targets()`, so a filter slug that no longer exists falls through to `''`.

- [ ] **Step 4: Run to verify it passes**

Run: `composer test -- --filter LinkCatalogueTest`
Expected: PASS (14 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/content/class-link-catalogue.php tests/php/LinkCatalogueTest.php
git commit -m "feat: offer filtered sport, team and event views as link targets"
```

---

### Task 3: Section anchors in the rendered markup

**Files:**
- Modify: `includes/render/class-page-renderer.php`
- Test: `tests/php/SectionAnchorTest.php`

**Interfaces:**
- Consumes: `Link_Catalogue::anchor_id()`.
- Produces: `Blueworx_Clubhouse_Page_Renderer::anchored( string $page, string $section, string $html ): string`.

**Why string surgery, not a new wrapper.** Wrapping a section in an extra `<div>` would change `.ch-main`'s child list, and the looks give `.ch-main`'s children flow margins while reveal.js hides them until they scroll in — an extra element would take those styles and break the page. Adding an `anchor` key to every section's data array instead would touch ~40 renderers with heterogeneous signatures (`ticker()` takes a list of strings, not an options array). So `anchored()` injects the id into the section's own root tag, in one tested function. Sections emit no id on their roots today, so there is nothing to collide with; the helper leaves an already-id'd root alone.

- [ ] **Step 1: Write the failing test**

Create `tests/php/SectionAnchorTest.php`:

```php
<?php
// tests/php/SectionAnchorTest.php

use PHPUnit\Framework\TestCase;

final class SectionAnchorTest extends TestCase {

	public function test_anchored_injects_the_id_into_the_root_tag(): void {
		$out = Blueworx_Clubhouse_Page_Renderer::anchored( 'about', 'history', '<section class="ch-x">hi</section>' );
		$this->assertSame( '<section id="ch-about-history" class="ch-x">hi</section>', $out );
	}

	public function test_anchored_handles_a_root_tag_with_no_attributes(): void {
		$out = Blueworx_Clubhouse_Page_Renderer::anchored( 'home', 'ticker', '<div>hi</div>' );
		$this->assertSame( '<div id="ch-home-ticker">hi</div>', $out );
	}

	public function test_anchored_leaves_an_already_identified_root_alone(): void {
		$html = '<section id="mine" class="ch-x">hi</section>';
		$this->assertSame( $html, Blueworx_Clubhouse_Page_Renderer::anchored( 'about', 'history', $html ) );
	}

	public function test_anchored_leaves_empty_output_alone(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::anchored( 'about', 'history', '' ) );
	}

	public function test_anchored_snake_case_section_keys_become_hyphenated_ids(): void {
		$out = Blueworx_Clubhouse_Page_Renderer::anchored( 'home', 'quick_tiles', '<div>x</div>' );
		$this->assertStringContainsString( 'id="ch-home-quick-tiles"', $out );
	}

	/**
	 * The catalogue must not offer an anchor the markup does not emit. Renders
	 * every page and asserts each catalogued section's id is present, skipping
	 * sections an owner-neutral default render legitimately omits (a loop with
	 * no items renders nothing at all).
	 */
	public function test_rendered_pages_carry_the_ids_the_catalogue_advertises(): void {
		$branding    = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$visibility  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$collections = new Blueworx_Clubhouse_Demo_Collections();

		$missing = array();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$tab = (string) $page['tab'];
			if ( 'global' === $tab ) {
				continue;
			}
			$slug = 'home' === $tab ? '' : $tab;
			if ( ! Blueworx_Clubhouse_Page_Map::is_available( $slug ) ) {
				continue;
			}
			$html = Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility, $collections );
			foreach ( $page['sections'] as $section ) {
				$id = Blueworx_Clubhouse_Link_Catalogue::anchor_id( $tab, (string) $section['key'] );
				if ( false === strpos( $html, 'id="' . $id . '"' ) ) {
					$missing[] = $id;
				}
			}
		}
		$this->assertSame( array(), $missing, 'catalogued sections with no anchor in the markup' );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter SectionAnchorTest`
Expected: FAIL — `Call to undefined method …::anchored()`.

- [ ] **Step 3: Implement `anchored()`**

Add to `includes/render/class-page-renderer.php`, next to `nav_links()`:

```php
	/**
	 * Stamp a section's root element with the id its anchor target points at.
	 *
	 * The id goes on the section's own root rather than a wrapper: the looks
	 * give .ch-main's children flow margins and reveal.js hides them until they
	 * scroll in, so an extra element in that child list would take styling meant
	 * for a section and shift the page. An already-identified root is left alone.
	 */
	public static function anchored( string $page, string $section, string $html ): string {
		if ( '' === $html || '<' !== $html[0] ) {
			return $html;
		}
		$id = Blueworx_Clubhouse_Link_Catalogue::anchor_id( $page, $section );
		// Match the opening tag's name only; a root that already carries an id
		// keeps it, because something else is relying on that one.
		if ( ! (bool) preg_match( '/^<([a-z][a-z0-9]*)(\s[^>]*)?>/i', $html, $m ) ) {
			return $html;
		}
		if ( isset( $m[2] ) && (bool) preg_match( '/\sid\s*=/i', $m[2] ) ) {
			return $html;
		}
		$rest = $m[2] ?? '';
		return '<' . $m[1] . ' id="' . $id . '"' . $rest . '>' . substr( $html, strlen( $m[0] ) );
	}
```

- [ ] **Step 4: Run the unit-level anchor tests**

Run: `composer test -- --filter "test_anchored"`
Expected: PASS (5 tests). The sixth test still fails — no page uses `anchored()` yet.

- [ ] **Step 5: Wrap every section call in every page method**

In `includes/render/class-page-renderer.php`, for each page method (`home`, `about`, `membership`, `contact`, `login`, `sports`, `teams`, `events`, `calendar`, `booking`), change each guarded section append from:

```php
		if ( $visibility->is_section_visible( 'home', 'ticker' ) ) {
			…
			$out  .= Blueworx_Clubhouse_Sections::ticker( … );
		}
```

to:

```php
		if ( $visibility->is_section_visible( 'home', 'ticker' ) ) {
			…
			$out  .= self::anchored( 'home', 'ticker', Blueworx_Clubhouse_Sections::ticker( … ) );
		}
```

The page and section strings are always the same pair already passed to `is_section_visible()` immediately above — copy them, do not invent new ones. Leave `shell_header()` and `shell_footer()` calls unwrapped; header and footer are not anchor targets.

- [ ] **Step 6: Run the full anchor test**

Run: `composer test -- --filter SectionAnchorTest`
Expected: PASS (6 tests).

If `test_rendered_pages_carry_the_ids_the_catalogue_advertises` reports missing ids, each one is either a section call you missed (fix the call) or a section the catalogue lists that has no markup of its own — e.g. a `linkout` section that only tells the owner where to edit something else. For the latter, exclude that section from `Link_Catalogue::anchors()` by skipping sections whose `type` is `linkout` or `auto`, and re-run both this test and `LinkCatalogueTest`.

- [ ] **Step 7: Run the whole suite**

Run: `composer test`
Expected: PASS. If a golden-markup assertion in `PageRendererTest` or `PreviewRenderTest` fails, it is asserting on a section root that now carries an id — update the expected string; the id is the intended change.

- [ ] **Step 8: Commit**

```bash
git add includes/render/class-page-renderer.php tests/php/SectionAnchorTest.php
git commit -m "feat: give every editable section a stable anchor id"
```

---

### Task 4: The `Menu` class

**Files:**
- Create: `includes/content/class-menu.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/MenuTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Storage`, `Blueworx_Clubhouse_Visibility`, `Blueworx_Clubhouse_Collections`, `Link_Catalogue::resolve()`.
- Produces:
  - `new Blueworx_Clubhouse_Menu( Blueworx_Clubhouse_Storage $storage )`
  - `Blueworx_Clubhouse_Menu::DEFAULTS` — the stored-shape default tree.
  - `->tree(): array` — stored tree, or `DEFAULTS` when never written. Shape `array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>`.
  - `->save( array $tree ): void` — sanitises, caps nesting at one level, and writes.
  - `->items( Collections $c, Visibility $v ): array` — render-ready `array<int,array{label:string,href:string,children:array<int,array{label:string,href:string}>}>`.
  - `Blueworx_Clubhouse_Menu::set_provider( ?callable $p ): void` / the seam Task 6 uses.

- [ ] **Step 1: Write the failing test**

Create `tests/php/MenuTest.php`:

```php
<?php
// tests/php/MenuTest.php

use PHPUnit\Framework\TestCase;

final class MenuTest extends TestCase {

	private function menu( ?Blueworx_Clubhouse_Fake_Storage $storage = null ): Blueworx_Clubhouse_Menu {
		return new Blueworx_Clubhouse_Menu( $storage ?? new Blueworx_Clubhouse_Fake_Storage() );
	}

	private function collections(): Blueworx_Clubhouse_Demo_Collections {
		return new Blueworx_Clubhouse_Demo_Collections();
	}

	private function visibility( ?Blueworx_Clubhouse_Fake_Storage $storage = null ): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( $storage ?? new Blueworx_Clubhouse_Fake_Storage() );
	}

	/** @return array<int,string> */
	private function labels( array $items ): array {
		return array_map( static fn( array $i ): string => $i['label'], $items );
	}

	public function test_an_unwritten_menu_is_todays_nine_items(): void {
		$items = $this->menu()->items( $this->collections(), $this->visibility() );
		$this->assertSame(
			array( 'Home', 'About', 'Sports', 'Teams', 'Membership', 'Events', 'Calendar', 'Contact' ),
			array_values( array_diff( $this->labels( $items ), array( 'Book a court' ) ) )
		);
	}

	public function test_a_saved_order_is_preserved(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$menu    = $this->menu( $storage );
		$menu->save( array(
			array( 'label' => 'Contact', 'target' => 'page:contact', 'children' => array() ),
			array( 'label' => 'Home',    'target' => 'page:home',    'children' => array() ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) );
		$this->assertSame( array( 'Contact', 'Home' ), $this->labels( $items ) );
	}

	public function test_a_renamed_label_is_used(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'Say hello', 'target' => 'page:contact', 'children' => array() ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) );
		$this->assertSame( array( 'Say hello' ), $this->labels( $items ) );
		$this->assertSame( Blueworx_Clubhouse_Links::url( 'contact' ), $items[0]['href'] );
	}

	public function test_children_are_resolved(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'About', 'target' => 'page:about', 'children' => array(
				array( 'label' => 'Our history', 'target' => 'anchor:about.history' ),
			) ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) );
		$this->assertCount( 1, $items );
		$this->assertSame( 'Our history', $items[0]['children'][0]['label'] );
		$this->assertStringContainsString( '#ch-about-history', $items[0]['children'][0]['href'] );
	}

	public function test_a_third_level_is_truncated_on_save(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'About', 'target' => 'page:about', 'children' => array(
				array( 'label' => 'History', 'target' => 'anchor:about.history', 'children' => array(
					array( 'label' => 'Too deep', 'target' => 'page:contact' ),
				) ),
			) ),
		) );
		$tree = $this->menu( $storage )->tree();
		$this->assertArrayNotHasKey( 'children', $tree[0]['children'][0] );
	}

	public function test_a_hidden_page_is_dropped(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_page_visible( 'contact', false );
		$this->menu( $storage )->save( array(
			array( 'label' => 'Home',    'target' => 'page:home',    'children' => array() ),
			array( 'label' => 'Contact', 'target' => 'page:contact', 'children' => array() ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $vis );
		$this->assertSame( array( 'Home' ), $this->labels( $items ) );
	}

	public function test_a_dead_target_is_dropped(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'Home',  'target' => 'page:home',           'children' => array() ),
			array( 'label' => 'Ghost', 'target' => 'filter:sports:ghost', 'children' => array() ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) );
		$this->assertSame( array( 'Home' ), $this->labels( $items ) );
	}

	public function test_a_dead_parent_with_live_children_becomes_a_heading(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'Club', 'target' => 'filter:sports:ghost', 'children' => array(
				array( 'label' => 'About', 'target' => 'page:about' ),
			) ),
		) );
		$items = $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) );
		$this->assertCount( 1, $items );
		$this->assertSame( '', $items[0]['href'] );
		$this->assertSame( array( 'About' ), $this->labels( $items[0]['children'] ) );
	}

	public function test_a_dead_parent_with_no_live_children_is_dropped(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => 'Club', 'target' => 'filter:sports:ghost', 'children' => array(
				array( 'label' => 'Ghost too', 'target' => 'filter:teams:ghost' ),
			) ),
		) );
		$this->assertSame( array(), $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) ) );
	}

	public function test_an_explicitly_emptied_menu_stays_empty(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array() );
		$this->assertSame( array(), $this->menu( $storage )->items( $this->collections(), $this->visibility( $storage ) ) );
	}

	public function test_an_item_with_a_blank_label_is_dropped_on_save(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->menu( $storage )->save( array(
			array( 'label' => '  ', 'target' => 'page:about', 'children' => array() ),
			array( 'label' => 'Home', 'target' => 'page:home', 'children' => array() ),
		) );
		$this->assertCount( 1, $this->menu( $storage )->tree() );
	}
}
```

If `Blueworx_Clubhouse_Visibility` has no `set_page_visible()`, read the class and use whatever setter it exposes; do not add one.

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter MenuTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Menu" not found`.

- [ ] **Step 3: Implement**

Create `includes/content/class-menu.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The header navigation an owner has arranged: order, labels and one level of
 * children, each item pointing at a Link_Catalogue target.
 *
 * Absence and emptiness mean different things here. A site that has never
 * opened the menu editor has no stored value, and gets DEFAULTS — the nav this
 * plugin has always rendered, so upgrading changes nothing. A site that deleted
 * every row has a stored empty array, and gets an empty nav, because that is
 * what it asked for.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Menu {

	private const KEY = 'menu';

	/**
	 * The nav before anyone edits it — the list shell_header() used to hardcode.
	 * Availability and visibility still filter it at render time, so a site
	 * without LatePoint never sees "Book a court" here either.
	 *
	 * @var array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	public const DEFAULTS = array(
		array( 'label' => 'Home',         'target' => 'page:home',       'children' => array() ),
		array( 'label' => 'About',        'target' => 'page:about',      'children' => array() ),
		array( 'label' => 'Sports',       'target' => 'page:sports',     'children' => array() ),
		array( 'label' => 'Teams',        'target' => 'page:teams',      'children' => array() ),
		array( 'label' => 'Membership',   'target' => 'page:membership', 'children' => array() ),
		array( 'label' => 'Events',       'target' => 'page:events',     'children' => array() ),
		array( 'label' => 'Calendar',     'target' => 'page:calendar',   'children' => array() ),
		array( 'label' => 'Book a court', 'target' => 'page:booking',    'children' => array() ),
		array( 'label' => 'Contact',      'target' => 'page:contact',    'children' => array() ),
	);

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * The stored tree, or the defaults when nothing was ever written. A stored
	 * empty array is returned as-is — see the class comment.
	 *
	 * @return array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	public function tree(): array {
		$stored = $this->storage->get( self::KEY, null );
		if ( ! is_array( $stored ) ) {
			return self::DEFAULTS;
		}
		return self::sanitise( $stored );
	}

	/** @param array<int,mixed> $tree */
	public function save( array $tree ): void {
		$this->storage->set( self::KEY, self::sanitise( $tree ) );
	}

	/**
	 * Coerce arbitrary input — a form post, or an option written by an older
	 * version — into the stored shape. Rows without a label or target are
	 * dropped, and a third level is truncated rather than rejected: an owner
	 * who over-indented should lose the nesting, not the item.
	 *
	 * @param array<int,mixed> $tree
	 * @return array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	private static function sanitise( array $tree ): array {
		$out = array();
		foreach ( $tree as $row ) {
			$item = self::sanitise_row( $row );
			if ( null === $item ) {
				continue;
			}
			$children = array();
			$raw      = is_array( $row ) && isset( $row['children'] ) && is_array( $row['children'] ) ? $row['children'] : array();
			foreach ( $raw as $child_row ) {
				$child = self::sanitise_row( $child_row );
				if ( null !== $child ) {
					$children[] = $child; // Note: no 'children' key — one level only.
				}
			}
			$item['children'] = $children;
			$out[]            = $item;
		}
		return $out;
	}

	/**
	 * One row, label and target only. Returns null when it is not usable.
	 *
	 * @param mixed $row
	 * @return array{label:string,target:string}|null
	 */
	private static function sanitise_row( $row ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$label  = trim( (string) ( $row['label'] ?? '' ) );
		$target = trim( (string) ( $row['target'] ?? '' ) );
		if ( '' === $label || '' === $target ) {
			return null;
		}
		return array( 'label' => $label, 'target' => $target );
	}

	/**
	 * The tree as the header renders it: resolved hrefs, gone items dropped.
	 *
	 * A parent whose own target has died but which still has living children
	 * survives as a heading with an empty href, because dropping it would take
	 * its children with it and silently shrink the nav.
	 *
	 * @return array<int,array{label:string,href:string,children:array<int,array{label:string,href:string}>}>
	 */
	public function items( Blueworx_Clubhouse_Collections $collections, Blueworx_Clubhouse_Visibility $visibility ): array {
		$out = array();
		foreach ( $this->tree() as $row ) {
			$children = array();
			foreach ( $row['children'] as $child ) {
				$href = self::href( $child['target'], $collections, $visibility );
				if ( '' !== $href ) {
					$children[] = array( 'label' => $child['label'], 'href' => $href );
				}
			}
			$href = self::href( $row['target'], $collections, $visibility );
			if ( '' === $href && array() === $children ) {
				continue;
			}
			$out[] = array( 'label' => $row['label'], 'href' => $href, 'children' => $children );
		}
		return $out;
	}

	/**
	 * A target's href once both gates have run: can this site serve it at all
	 * (Link_Catalogue, which already excludes pages whose integration is
	 * missing), and has the owner hidden the page it lands on.
	 */
	private static function href(
		string $target,
		Blueworx_Clubhouse_Collections $collections,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$page = self::page_key( $target );
		if ( '' !== $page && ! $visibility->is_page_visible( $page ) ) {
			return '';
		}
		return Blueworx_Clubhouse_Link_Catalogue::resolve( $target, $collections );
	}

	/** The visibility key a target lands on — '' for a custom URL, which has none. */
	private static function page_key( string $target ): string {
		if ( 0 === strpos( $target, 'page:' ) ) {
			return substr( $target, 5 );
		}
		if ( 0 === strpos( $target, 'anchor:' ) ) {
			$rest = substr( $target, 7 );
			$dot  = strpos( $rest, '.' );
			return false === $dot ? $rest : substr( $rest, 0, $dot );
		}
		if ( 0 === strpos( $target, 'filter:' ) ) {
			$rest  = substr( $target, 7 );
			$colon = strpos( $rest, ':' );
			return false === $colon ? $rest : substr( $rest, 0, $colon );
		}
		return '';
	}
}
```

- [ ] **Step 4: Require it from the bootstrap**

In `includes/bootstrap.php`, after the `class-link-catalogue.php` line:

```php
require_once __DIR__ . '/content/class-menu.php';
```

- [ ] **Step 5: Run to verify it passes**

Run: `composer test -- --filter MenuTest`
Expected: PASS (11 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/content/class-menu.php includes/bootstrap.php tests/php/MenuTest.php
git commit -m "feat: store and resolve an owner-arranged header menu"
```

---

### Task 5: Render children in the header

**Files:**
- Modify: `includes/render/class-sections.php` (`header()`)
- Modify: the three look stylesheets' shared nav rules — find where `.ch-nav__link` is styled with `grep -rn "ch-nav__link" assets/` and add the submenu rules to the same file(s)
- Test: `tests/php/PageRendererTest.php` (add cases)

**Interfaces:**
- Consumes: nothing new.
- Produces: `Sections::header()` accepts `nav` entries of shape `array{label:string,href:string,children?:array<int,array{label:string,href:string}>}`. An entry with an empty `href` renders as a `<span class="ch-nav__link ch-nav__link--static">`, not an `<a>`.

- [ ] **Step 1: Write the failing test**

Append to `tests/php/PageRendererTest.php`:

```php
	public function test_header_renders_a_child_list_under_a_parent(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => 'ClubHouse',
			'banner'      => '',
			'banner_href' => '#',
			'nav'         => array(
				array( 'label' => 'About', 'href' => '/about', 'children' => array(
					array( 'label' => 'History', 'href' => '/about#ch-about-history' ),
				) ),
			),
			'active'      => '/about',
			'login'       => 'Log in',
			'login_href'  => '/login',
			'join'        => 'Join',
			'join_href'   => '/membership',
		) );
		$this->assertStringContainsString( 'ch-nav__item--has-children', $html );
		$this->assertStringContainsString( 'aria-haspopup="true"', $html );
		$this->assertStringContainsString( 'ch-nav__sub', $html );
		$this->assertStringContainsString( '/about#ch-about-history', $html );
	}

	public function test_header_renders_a_hrefless_parent_as_a_non_link(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => 'ClubHouse',
			'banner'      => '',
			'banner_href' => '#',
			'nav'         => array(
				array( 'label' => 'Club', 'href' => '', 'children' => array(
					array( 'label' => 'About', 'href' => '/about' ),
				) ),
			),
			'active'      => '',
			'login'       => 'Log in',
			'login_href'  => '/login',
			'join'        => 'Join',
			'join_href'   => '/membership',
		) );
		$this->assertStringContainsString( 'ch-nav__link--static', $html );
		$this->assertStringNotContainsString( '<a class="ch-nav__link" href="">', $html );
	}

	public function test_header_still_renders_a_flat_nav_unchanged(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => 'ClubHouse',
			'banner'      => '',
			'banner_href' => '#',
			'nav'         => array( array( 'label' => 'About', 'href' => '/about' ) ),
			'active'      => '/about',
			'login'       => 'Log in',
			'login_href'  => '/login',
			'join'        => 'Join',
			'join_href'   => '/membership',
		) );
		$this->assertStringContainsString( 'ch-nav__link--active', $html );
		$this->assertStringNotContainsString( 'ch-nav__sub', $html );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter "test_header_renders"`
Expected: FAIL — no `ch-nav__item--has-children` in the output.

- [ ] **Step 3: Implement**

In `includes/render/class-sections.php`, replace the `$links` loop inside `header()` and add a helper. Update the docblock's `nav` type to include `children`.

```php
		$links = '';
		foreach ( $data['nav'] as $item ) {
			$links .= self::nav_item( $item, $data['active'] );
		}
```

```php
	/**
	 * One header nav entry — a link, or a parent with a submenu.
	 *
	 * The submenu opens on :hover and :focus-within (see the look stylesheets),
	 * so it needs no JavaScript and stays reachable by keyboard: tabbing into a
	 * child keeps the list open because focus is still inside the wrapper.
	 *
	 * @param array{label:string,href:string,children?:array<int,array{label:string,href:string}>} $item
	 */
	private static function nav_item( array $item, string $active ): string {
		$children = isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : array();
		$is_here  = '' !== $item['href'] && $item['href'] === $active;
		$cls      = 'ch-nav__link' . ( $is_here ? ' ch-nav__link--active' : '' );

		if ( array() === $children ) {
			return '<a class="' . $cls . '" href="' . self::e( $item['href'] ) . '">' . self::e( $item['label'] ) . '</a>';
		}

		// A parent whose own target has gone still heads its children, but must
		// not be a link to nowhere — Menu::items() hands it an empty href.
		$head = '' === $item['href']
			? '<span class="' . $cls . ' ch-nav__link--static" aria-haspopup="true">' . self::e( $item['label'] ) . '</span>'
			: '<a class="' . $cls . '" href="' . self::e( $item['href'] ) . '" aria-haspopup="true">' . self::e( $item['label'] ) . '</a>';

		$sub = '';
		foreach ( $children as $child ) {
			$sub .= '<a class="ch-nav__sublink" href="' . self::e( $child['href'] ) . '">' . self::e( $child['label'] ) . '</a>';
		}

		return '<span class="ch-nav__item ch-nav__item--has-children">' . $head
			. '<span class="ch-nav__sub">' . $sub . '</span></span>';
	}
```

- [ ] **Step 4: Add the styles**

Find the file that styles `.ch-nav__link`:

```bash
grep -rn "ch-nav__link" assets/
```

Add to the same stylesheet (each look's file, if the rule is per-look — match whatever the grep shows):

```css
.ch-nav__item--has-children{position:relative;display:inline-flex}
.ch-nav__sub{position:absolute;top:100%;left:0;z-index:20;display:flex;flex-direction:column;min-width:12rem;
  padding:.4rem 0;opacity:0;visibility:hidden;transition:opacity .12s ease;
  background:var(--ch-surface,#fff);border:1px solid var(--ch-line,rgba(0,0,0,.12));border-radius:.5rem}
.ch-nav__item--has-children:hover .ch-nav__sub,
.ch-nav__item--has-children:focus-within .ch-nav__sub{opacity:1;visibility:visible}
.ch-nav__sublink{display:block;padding:.45rem .9rem;white-space:nowrap;text-decoration:none;color:inherit}
.ch-nav__sublink:hover,.ch-nav__sublink:focus{text-decoration:underline}
.ch-nav__link--static{cursor:default}

/* In the burger drawer there is no hover target, so children are simply an
   indented, always-open sub-list. */
.ch-nav__drawer .ch-nav__item--has-children{display:block;position:static}
.ch-nav__drawer .ch-nav__sub{position:static;opacity:1;visibility:visible;border:0;background:none;
  padding:0 0 0 1rem;min-width:0}
```

Use the same custom-property names the surrounding rules use — read the neighbouring declarations and match them rather than copying `--ch-surface`/`--ch-line` blindly.

- [ ] **Step 5: Run to verify it passes**

Run: `composer test`
Expected: PASS. `test_header_still_renders_a_flat_nav_unchanged` guards the no-children path.

- [ ] **Step 6: Commit**

```bash
git add includes/render/class-sections.php assets/ tests/php/PageRendererTest.php
git commit -m "feat: render one level of children in the header nav"
```

---

### Task 6: Feed the header from `Menu`

**Files:**
- Modify: `includes/render/class-page-renderer.php` (`shell_header()` and its ~10 call sites)
- Modify: `includes/content/class-menu.php` (the provider seam)
- Modify: `includes/frontend/class-frontend.php` (install the provider)
- Test: `tests/php/PageRendererTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Menu::set_provider( ?callable $provider ): void` where `$provider` is `callable(): Blueworx_Clubhouse_Menu`, and `Blueworx_Clubhouse_Menu::current(): Blueworx_Clubhouse_Menu` returning the provider's menu or a defaults-only instance.

**Why a static seam.** `shell_header()` has no `Storage`, and neither do the ten page methods or `Page_Map::render()`'s fixed-arity `call_user_func`. Threading a seventh parameter through all of them to reach one call is more churn than the codebase already accepts for exactly this problem — `Blueworx_Clubhouse_Links::set_resolver()` is the same seam for the same reason. Follow that pattern. `shell_header()` *does* gain a `$collections` parameter, because every page method already holds one and `Menu::items()` genuinely needs it.

- [ ] **Step 1: Write the failing test**

Append to `tests/php/PageRendererTest.php`:

```php
	public function test_home_nav_comes_from_the_stored_menu(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$menu    = new Blueworx_Clubhouse_Menu( $storage );
		$menu->save( array(
			array( 'label' => 'Say hello', 'target' => 'page:contact', 'children' => array() ),
		) );
		Blueworx_Clubhouse_Menu::set_provider( static fn(): Blueworx_Clubhouse_Menu => $menu );
		try {
			$html = Blueworx_Clubhouse_Page_Renderer::home(
				$this->branding(),
				new Blueworx_Clubhouse_Visibility( $storage ),
				$this->collections()
			);
		} finally {
			Blueworx_Clubhouse_Menu::set_provider( null );
		}
		$this->assertStringContainsString( '>Say hello<', $html );
		$this->assertStringNotContainsString( '>Membership</a>', $html );
	}

	public function test_home_nav_falls_back_to_the_defaults_with_no_provider(): void {
		Blueworx_Clubhouse_Menu::set_provider( null );
		$html = Blueworx_Clubhouse_Page_Renderer::home(
			$this->branding(),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			$this->collections()
		);
		$this->assertStringContainsString( '>Membership<', $html );
		$this->assertStringContainsString( '>Contact<', $html );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter "test_home_nav"`
Expected: FAIL — `Call to undefined method …Menu::set_provider()`.

- [ ] **Step 3: Add the seam to `Menu`**

In `includes/content/class-menu.php`:

```php
	/** @var (callable():Blueworx_Clubhouse_Menu)|null */
	private static $provider = null;

	/**
	 * Where the renderer gets its menu. WordPress installs one backed by real
	 * options (see Frontend); the preview and the tests leave it unset and get
	 * the defaults, which is what keeps the two renders identical for a site
	 * that has not edited its nav.
	 *
	 * @param (callable():Blueworx_Clubhouse_Menu)|null $provider
	 */
	public static function set_provider( ?callable $provider ): void {
		self::$provider = $provider;
	}

	public static function current(): Blueworx_Clubhouse_Menu {
		if ( null !== self::$provider ) {
			return ( self::$provider )();
		}
		return new self( new Blueworx_Clubhouse_Null_Storage() );
	}
```

If no `Blueworx_Clubhouse_Null_Storage` exists (check `includes/core/`), add one beside `class-options-storage.php` — a three-method class whose `get()` always returns `$default`, `set()`/`delete()` do nothing — and require it from the bootstrap before `class-menu.php`. Do not reuse the test fake; it lives in `tests/`.

- [ ] **Step 4: Use it in `shell_header()`**

Change the signature and the nav source:

```php
	private static function shell_header(
		string $club,
		string $active,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
```

and replace the hardcoded `'nav' => self::nav_links( array( … ), $visibility ),` with:

```php
			'nav'         => Blueworx_Clubhouse_Menu::current()->items( $collections, $visibility ),
```

Then update every `self::shell_header( $club, …, $visibility, $logo_url, $content )` call in the ten page methods to pass `$collections` in its new position: `self::shell_header( $club, …, $visibility, $collections, $logo_url, $content )`.

Leave `nav_links()` in place — `shell_footer()` still uses it, and the footer is out of scope.

- [ ] **Step 5: Install the provider in WordPress**

In `includes/frontend/class-frontend.php`, wherever `Blueworx_Clubhouse_Links::set_resolver()` is installed, add alongside it:

```php
		Blueworx_Clubhouse_Menu::set_provider(
			static fn(): Blueworx_Clubhouse_Menu => new Blueworx_Clubhouse_Menu( $storage )
		);
```

using whatever variable holds the storage in that scope. If the resolver is installed in more than one place, install the provider in each.

- [ ] **Step 6: Run to verify it passes**

Run: `composer test`
Expected: PASS. If another test leaked a provider, that is the bug the `finally` block above exists to prevent — check any new test you add resets it.

- [ ] **Step 7: Verify the preview still renders**

Run: `npm run ports` then open the preview per `docs/testing.md`, or simply run the DB-free preview test:

Run: `composer test -- --filter PreviewRenderTest`
Expected: PASS — the preview has no provider, so it renders the defaults.

- [ ] **Step 8: Commit**

```bash
git add includes/render/class-page-renderer.php includes/content/class-menu.php includes/core/ includes/bootstrap.php includes/frontend/class-frontend.php tests/php/PageRendererTest.php
git commit -m "feat: render the header nav from the stored menu"
```

---

### Task 7: The Menu tab — rendering

**Files:**
- Create: `includes/admin/class-menu-panel.php`
- Modify: `includes/bootstrap.php`
- Modify: `includes/admin/class-content-screen.php` (tab row + panel)
- Modify: `includes/admin/class-content-controller.php` (`build_model` supplies the menu model)
- Test: `tests/php/MenuPanelTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Menu_Panel::render( array $model ): string` where `$model` is
  `array{tree:array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>, targets:array<int,array{target:string,label:string,group:string,url:string}>, action_url:string, nonce_field:string}`.
- The panel's form posts `menu[<i>][label]`, `menu[<i>][target]`, `menu[<i>][custom]`, `menu[<i>][children][<j>][label]`, `menu[<i>][children][<j>][target]`, `menu[<i>][children][<j>][custom]`, plus one of `clubhouse_menu_up[<path>]`, `clubhouse_menu_down[<path>]`, `clubhouse_menu_indent[<path>]`, `clubhouse_menu_outdent[<path>]`, `clubhouse_menu_remove[<path>]`, `clubhouse_menu_add`. `<path>` is `<i>` for a top-level row and `<i>-<j>` for a child.

- [ ] **Step 1: Write the failing test**

Create `tests/php/MenuPanelTest.php`:

```php
<?php
// tests/php/MenuPanelTest.php

use PHPUnit\Framework\TestCase;

final class MenuPanelTest extends TestCase {

	private function model( array $tree ): array {
		return array(
			'tree'        => $tree,
			'targets'     => Blueworx_Clubhouse_Link_Catalogue::targets( new Blueworx_Clubhouse_Demo_Collections() ),
			'action_url'  => 'http://x.test/wp-admin/admin.php?page=clubhouse-site-content',
			'nonce_field' => '<input type="hidden" name="_wpnonce" value="abc">',
		);
	}

	public function test_a_row_renders_its_label_and_selected_target(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'Say hello', 'target' => 'page:contact', 'children' => array() ),
		) ) );
		$this->assertStringContainsString( 'name="menu[0][label]"', $html );
		$this->assertStringContainsString( 'value="Say hello"', $html );
		$this->assertStringContainsString( '<option value="page:contact" selected>', $html );
	}

	public function test_targets_are_grouped_in_the_picker(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( Blueworx_Clubhouse_Menu::DEFAULTS ) );
		$this->assertStringContainsString( '<optgroup label="Pages">', $html );
		$this->assertStringContainsString( '<optgroup label="Sections">', $html );
		$this->assertStringContainsString( '<optgroup label="Sports">', $html );
	}

	public function test_a_child_row_uses_the_nested_field_names(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'About', 'target' => 'page:about', 'children' => array(
				array( 'label' => 'History', 'target' => 'anchor:about.history' ),
			) ),
		) ) );
		$this->assertStringContainsString( 'name="menu[0][children][0][label]"', $html );
		$this->assertStringContainsString( 'clubhouse_menu_outdent[0-0]', $html );
	}

	public function test_the_first_row_cannot_move_up_or_indent(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'Home', 'target' => 'page:home', 'children' => array() ),
			array( 'label' => 'About', 'target' => 'page:about', 'children' => array() ),
		) ) );
		$this->assertMatchesRegularExpression(
			'/name="clubhouse_menu_up\[0\]"[^>]*\bdisabled\b/',
			$html
		);
		$this->assertMatchesRegularExpression(
			'/name="clubhouse_menu_indent\[0\]"[^>]*\bdisabled\b/',
			$html
		);
	}

	public function test_a_custom_url_target_shows_its_url(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'Shop', 'target' => 'url:https://shop.test', 'children' => array() ),
		) ) );
		$this->assertStringContainsString( '<option value="url:" selected>', $html );
		$this->assertStringContainsString( 'name="menu[0][custom]"', $html );
		$this->assertStringContainsString( 'value="https://shop.test"', $html );
	}

	public function test_a_stored_target_that_no_longer_exists_is_flagged(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => 'Ghost', 'target' => 'filter:sports:ghost', 'children' => array() ),
		) ) );
		$this->assertStringContainsString( 'target unavailable', $html );
	}

	public function test_labels_and_urls_are_escaped(): void {
		$html = Blueworx_Clubhouse_Menu_Panel::render( $this->model( array(
			array( 'label' => '"><script>x</script>', 'target' => 'page:home', 'children' => array() ),
		) ) );
		$this->assertStringNotContainsString( '<script>', $html );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter MenuPanelTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Menu_Panel" not found`.

- [ ] **Step 3: Implement the panel**

Create `includes/admin/class-menu-panel.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Menu tab of the Club Content screen: the nav tree as a list of rows, each
 * a label, a target picker and the buttons that move it.
 *
 * Order and nesting live in the field names (menu[0][children][1][label]), so
 * the submitted form *is* the tree — no client-side state, and reordering works
 * with JavaScript off. Pure string building, like Content_Screen; the
 * controller supplies the model and handles the post.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Menu_Panel {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * @param array{tree:array<int,array<string,mixed>>,targets:array<int,array{target:string,label:string,group:string,url:string}>,action_url:string,nonce_field:string} $model
	 */
	public static function render( array $model ): string {
		$tree = $model['tree'];

		$out  = '<section class="clubhouse-pagepanel is-active" id="clubhouse-tab-menu" data-pagepanel="menu" role="tabpanel">';
		$out .= '<form method="post" action="' . self::esc( (string) $model['action_url'] ) . '" class="clubhouse-form">';
		$out .= (string) $model['nonce_field'];
		$out .= '<input type="hidden" name="clubhouse_content_tab" value="menu">';
		// Same reason as Content_Screen's: only the activated submit button
		// contributes its name, so a Move/Remove click carries no submit key.
		$out .= '<input type="hidden" name="clubhouse_content_submit" value="1">';
		$out .= '<div class="clubhouse-body"><div class="clubhouse-panels">';
		$out .= '<div class="clubhouse-section"><h2 class="clubhouse-section__h2">Header menu</h2>';
		$out .= '<p class="clubhouse-help">The order here is the order in the site header. Indent an item to hang it under the one above.</p>';
		$out .= '<ol class="clubhouse-menu">';

		foreach ( $tree as $i => $row ) {
			$out .= self::row( $model, (string) $row['label'], (string) $row['target'], (string) $i, false, 0 === $i, count( $tree ) - 1 === $i );
			$children = is_array( $row['children'] ?? null ) ? $row['children'] : array();
			foreach ( $children as $j => $child ) {
				$out .= self::row(
					$model,
					(string) $child['label'],
					(string) $child['target'],
					$i . '-' . $j,
					true,
					0 === $j,
					count( $children ) - 1 === $j
				);
			}
		}

		$out .= '</ol>';
		$out .= '<p><button type="submit" class="button" name="clubhouse_menu_add" value="1">Add item</button></p>';
		$out .= '</div></div></div>';
		$out .= '<div class="clubhouse-savebar"><button type="submit" class="button button-primary" name="clubhouse_content_submit" value="1">Save menu</button></div>';
		$out .= '</form></section>';
		return $out;
	}

	/**
	 * One row. $path is '<i>' or '<i>-<j>'; the field-name prefix is derived
	 * from it so the names and the button paths can never disagree.
	 */
	private static function row(
		array $model,
		string $label,
		string $target,
		string $path,
		bool $is_child,
		bool $is_first,
		bool $is_last
	): string {
		$parts  = explode( '-', $path );
		$prefix = $is_child
			? 'menu[' . $parts[0] . '][children][' . $parts[1] . ']'
			: 'menu[' . $parts[0] . ']';

		$known  = self::is_known( $model['targets'], $target );
		$custom = 0 === strpos( $target, 'url:' ) ? substr( $target, 4 ) : '';

		$out  = '<li class="clubhouse-menu__row' . ( $is_child ? ' clubhouse-menu__row--child' : '' ) . '">';
		$out .= '<input type="text" class="clubhouse-input" name="' . self::esc( $prefix . '[label]' ) . '" value="' . self::esc( $label ) . '" aria-label="Menu item label">';
		$out .= self::picker( $model['targets'], $prefix . '[target]', $target );
		$out .= '<input type="url" class="clubhouse-input clubhouse-menu__custom" name="' . self::esc( $prefix . '[custom]' ) . '" value="' . self::esc( $custom ) . '" placeholder="https://…" aria-label="Custom URL">';

		if ( ! $known && '' === $custom ) {
			$out .= '<span class="clubhouse-menu__warn">target unavailable</span>';
		}

		$out .= self::button( 'clubhouse_menu_up', $path, 'Move up', '↑', $is_first );
		$out .= self::button( 'clubhouse_menu_down', $path, 'Move down', '↓', $is_last );
		$out .= self::button( 'clubhouse_menu_indent', $path, 'Indent', '→', $is_child || ( ! $is_child && '0' === $path ) );
		$out .= self::button( 'clubhouse_menu_outdent', $path, 'Outdent', '←', ! $is_child );
		$out .= self::button( 'clubhouse_menu_remove', $path, 'Remove', '✕', false );
		$out .= '</li>';
		return $out;
	}

	private static function button( string $name, string $path, string $title, string $glyph, bool $disabled ): string {
		return '<button type="submit" class="button clubhouse-menu__btn" name="' . self::esc( $name . '[' . $path . ']' ) . '" value="1"'
			. ' title="' . self::esc( $title ) . '" aria-label="' . self::esc( $title ) . '"'
			. ( $disabled ? ' disabled' : '' ) . '>' . self::esc( $glyph ) . '</button>';
	}

	/**
	 * The target picker: every catalogue entry, grouped, plus a "Custom URL"
	 * option whose value is the bare 'url:' tag — the adjacent text input
	 * carries the address.
	 *
	 * @param array<int,array{target:string,label:string,group:string,url:string}> $targets
	 */
	private static function picker( array $targets, string $name, string $selected ): string {
		$is_custom = 0 === strpos( $selected, 'url:' );

		$out   = '<select class="clubhouse-input" name="' . self::esc( $name ) . '" aria-label="Links to">';
		$group = '';
		foreach ( $targets as $entry ) {
			if ( $entry['group'] !== $group ) {
				$out  .= '' === $group ? '' : '</optgroup>';
				$group = $entry['group'];
				$out  .= '<optgroup label="' . self::esc( $group ) . '">';
			}
			$sel  = ( ! $is_custom && $entry['target'] === $selected ) ? ' selected' : '';
			$out .= '<option value="' . self::esc( $entry['target'] ) . '"' . $sel . '>' . self::esc( $entry['label'] ) . '</option>';
		}
		$out .= '' === $group ? '' : '</optgroup>';
		$out .= '<option value="url:"' . ( $is_custom ? ' selected' : '' ) . '>Custom URL…</option>';
		return $out . '</select>';
	}

	/** @param array<int,array{target:string,label:string,group:string,url:string}> $targets */
	private static function is_known( array $targets, string $target ): bool {
		foreach ( $targets as $entry ) {
			if ( $entry['target'] === $target ) {
				return true;
			}
		}
		return false;
	}
}
```

- [ ] **Step 4: Require it and hang it off the screen**

`includes/bootstrap.php`, in the admin group after `class-content-screen.php`:

```php
require_once __DIR__ . '/admin/class-menu-panel.php';
```

In `includes/admin/class-content-screen.php::render()`, add the Menu tab ahead of the catalogue tabs. Immediately after `$out .= self::links_datalist();`:

```php
		// The Menu tab is not a catalogue page — it edits the nav tree, not
		// section content — so it renders through its own panel rather than
		// page_block(). It still posts through the same form plumbing.
		$out .= Blueworx_Clubhouse_Menu_Panel::render( array(
			'tree'        => $model['menu_tree'],
			'targets'     => $model['menu_targets'],
			'action_url'  => $action_url,
			'nonce_field' => (string) $model['nonce_field'],
		) );
```

and in `page_tabs()`, emit the Menu tab first, before the catalogue loop, marking it active and the catalogue's first tab not-active:

```php
	private static function page_tabs( array $catalogue, string $action_url ): string {
		$out  = '<nav class="clubhouse-pagetabs" role="tablist">';
		$out .= '<a class="clubhouse-pagetab is-active" href="' . self::esc_url( self::tab_href( $action_url, 'menu' ) ) . '" data-tab="menu" role="tab" aria-selected="true">Menu</a>';
		foreach ( $catalogue as $page ) {
			$tab  = (string) $page['tab'];
			$href = self::tab_href( $action_url, $tab );
			$out .= '<a class="clubhouse-pagetab" href="' . self::esc_url( $href ) . '" data-tab="' . self::esc( $tab ) . '" role="tab" aria-selected="false">'
				. self::esc( (string) $page['label'] ) . '</a>';
		}
		return $out . '</nav>';
	}
```

and change the `page_block()` loop's active argument so no catalogue panel claims `is-active`:

```php
		foreach ( $catalogue as $page ) {
			$out .= self::page_block( $page, false, $action_url, (string) $model['nonce_field'] );
		}
```

- [ ] **Step 5: Supply the model**

In `includes/admin/class-content-controller.php::build_model()`, add to the returned array (find the `return array(` at the end of the method and add two keys):

```php
			'menu_tree'    => ( new Blueworx_Clubhouse_Menu( $storage ) )->tree(),
			'menu_targets' => Blueworx_Clubhouse_Link_Catalogue::targets( new Blueworx_Clubhouse_WP_Collections() ),
```

- [ ] **Step 6: Run to verify it passes**

Run: `composer test -- --filter MenuPanelTest`
Expected: PASS (7 tests).

Run: `composer test`
Expected: PASS. `ContentControllerTest` may assert on the model's keys or the tab row — update those assertions to expect the Menu tab; its presence is the intended change.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/class-menu-panel.php includes/admin/class-content-screen.php includes/admin/class-content-controller.php includes/bootstrap.php tests/php/MenuPanelTest.php
git commit -m "feat: a Menu tab in Club Content that renders the nav tree"
```

---

### Task 8: The Menu tab — saving, reordering and nesting

**Files:**
- Modify: `includes/admin/class-content-controller.php`
- Test: `tests/php/ContentControllerTest.php` (add cases)

**Interfaces:**
- Consumes: Task 7's field names; `Menu::save()`.
- Produces: `Blueworx_Clubhouse_Content_Controller::handle_save()` returns early for `clubhouse_content_tab === 'menu'`, having applied the post to the menu. Internally: `private static function save_menu( array $post, Storage $storage ): array` returning notices.

- [ ] **Step 1: Write the failing test**

Append to `tests/php/ContentControllerTest.php`:

```php
	/** @return array<int,array<string,mixed>> */
	private function menu_tree( Blueworx_Clubhouse_Fake_Storage $storage ): array {
		return ( new Blueworx_Clubhouse_Menu( $storage ) )->tree();
	}

	private function menu_post( array $rows, array $extra = array() ): array {
		return array_merge( array( 'clubhouse_content_tab' => 'menu', 'menu' => $rows ), $extra );
	}

	public function test_saving_the_menu_tab_stores_labels_and_targets(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( $this->menu_post( array(
			array( 'label' => 'Say hello', 'target' => 'page:contact', 'custom' => '' ),
		) ), $storage );
		$tree = $this->menu_tree( $storage );
		$this->assertSame( 'Say hello', $tree[0]['label'] );
		$this->assertSame( 'page:contact', $tree[0]['target'] );
	}

	public function test_a_custom_url_row_stores_the_url_target(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( $this->menu_post( array(
			array( 'label' => 'Shop', 'target' => 'url:', 'custom' => 'https://shop.test' ),
		) ), $storage );
		$this->assertSame( 'url:https://shop.test', $this->menu_tree( $storage )[0]['target'] );
	}

	public function test_move_down_swaps_two_rows(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( $this->menu_post(
			array(
				array( 'label' => 'A', 'target' => 'page:home',  'custom' => '' ),
				array( 'label' => 'B', 'target' => 'page:about', 'custom' => '' ),
			),
			array( 'clubhouse_menu_down' => array( '0' => '1' ) )
		), $storage );
		$tree = $this->menu_tree( $storage );
		$this->assertSame( array( 'B', 'A' ), array( $tree[0]['label'], $tree[1]['label'] ) );
	}

	public function test_indent_hangs_a_row_under_the_one_above(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( $this->menu_post(
			array(
				array( 'label' => 'About',   'target' => 'page:about',           'custom' => '' ),
				array( 'label' => 'History', 'target' => 'anchor:about.history', 'custom' => '' ),
			),
			array( 'clubhouse_menu_indent' => array( '1' => '1' ) )
		), $storage );
		$tree = $this->menu_tree( $storage );
		$this->assertCount( 1, $tree );
		$this->assertSame( 'History', $tree[0]['children'][0]['label'] );
	}

	public function test_outdent_promotes_a_child_to_just_after_its_parent(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( $this->menu_post(
			array(
				array( 'label' => 'About', 'target' => 'page:about', 'custom' => '', 'children' => array(
					array( 'label' => 'History', 'target' => 'anchor:about.history', 'custom' => '' ),
				) ),
				array( 'label' => 'Contact', 'target' => 'page:contact', 'custom' => '' ),
			),
			array( 'clubhouse_menu_outdent' => array( '0-0' => '1' ) )
		), $storage );
		$tree = $this->menu_tree( $storage );
		$this->assertSame( array( 'About', 'History', 'Contact' ), array_column( $tree, 'label' ) );
		$this->assertSame( array(), $tree[0]['children'] );
	}

	public function test_remove_deletes_a_child(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( $this->menu_post(
			array(
				array( 'label' => 'About', 'target' => 'page:about', 'custom' => '', 'children' => array(
					array( 'label' => 'History', 'target' => 'anchor:about.history', 'custom' => '' ),
				) ),
			),
			array( 'clubhouse_menu_remove' => array( '0-0' => '1' ) )
		), $storage );
		$this->assertSame( array(), $this->menu_tree( $storage )[0]['children'] );
	}

	public function test_add_appends_a_blank_row(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( $this->menu_post(
			array( array( 'label' => 'A', 'target' => 'page:home', 'custom' => '' ) ),
			array( 'clubhouse_menu_add' => '1' )
		), $storage );
		$tree = $this->menu_tree( $storage );
		$this->assertCount( 2, $tree );
		$this->assertSame( 'New item', $tree[1]['label'] );
	}

	public function test_a_menu_post_does_not_touch_section_content(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$store   = new Blueworx_Clubhouse_Content_Store( $storage );
		$store->set( 'global', 'header', 'banner', 'Keep me' );
		Blueworx_Clubhouse_Content_Controller::handle_save( $this->menu_post( array(
			array( 'label' => 'A', 'target' => 'page:home', 'custom' => '' ),
		) ), $storage );
		$this->assertSame( 'Keep me', $store->get( 'global', 'header', 'banner', '' ) );
	}
```

Check `Content_Store`'s actual getter/setter names before writing the last test — read the class and match them.

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter ContentControllerTest`
Expected: FAIL — `handle_save` finds no catalogue page named `menu` and returns `array()` without saving.

- [ ] **Step 3: Implement**

In `includes/admin/class-content-controller.php`, add the branch as the first thing `handle_save()` does, before `$page = self::find_page( $tab_slug );`:

```php
		if ( 'menu' === $tab_slug ) {
			return self::save_menu( $post, $storage );
		}
```

and add the methods:

```php
	/**
	 * Apply a Menu-tab post. The submitted form is already the tree — order and
	 * nesting live in the field names — so this reads it, applies whichever
	 * single move button was activated, and stores the result. One move per
	 * request: a form submits exactly one activated button.
	 *
	 * @param array<string,mixed> $post
	 * @return array<int,array{type:string,text:string}>
	 */
	private static function save_menu( array $post, Blueworx_Clubhouse_Storage $storage ): array {
		$tree = self::menu_from_post( self::as_array( $post['menu'] ?? null ) );

		$tree = self::menu_move( $tree, 'up', self::first_key( $post['clubhouse_menu_up'] ?? null ) );
		$tree = self::menu_move( $tree, 'down', self::first_key( $post['clubhouse_menu_down'] ?? null ) );
		$tree = self::menu_move( $tree, 'indent', self::first_key( $post['clubhouse_menu_indent'] ?? null ) );
		$tree = self::menu_move( $tree, 'outdent', self::first_key( $post['clubhouse_menu_outdent'] ?? null ) );
		$tree = self::menu_move( $tree, 'remove', self::first_key( $post['clubhouse_menu_remove'] ?? null ) );

		if ( isset( $post['clubhouse_menu_add'] ) ) {
			$tree[] = array( 'label' => 'New item', 'target' => 'page:home', 'children' => array() );
		}

		( new Blueworx_Clubhouse_Menu( $storage ) )->save( $tree );
		return array( array( 'type' => 'success', 'text' => 'Your menu has been saved.' ) );
	}

	/** The single path a move button submitted, or '' when none did. */
	private static function first_key( $raw ): string {
		if ( ! is_array( $raw ) ) {
			return '';
		}
		foreach ( $raw as $key => $ignored ) {
			return (string) $key;
		}
		return '';
	}

	/**
	 * Turn the posted rows into the stored tree, folding each row's target
	 * select and its custom-URL box into one target tag.
	 *
	 * @param array<int|string,mixed> $rows
	 * @return array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	private static function menu_from_post( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$children = array();
			foreach ( self::as_array( $row['children'] ?? null ) as $child ) {
				if ( is_array( $child ) ) {
					$children[] = array(
						'label'  => (string) ( $child['label'] ?? '' ),
						'target' => self::menu_target( $child ),
					);
				}
			}
			$out[] = array(
				'label'    => (string) ( $row['label'] ?? '' ),
				'target'   => self::menu_target( $row ),
				'children' => $children,
			);
		}
		return $out;
	}

	/**
	 * A row's target. The picker's "Custom URL…" option posts the bare tag
	 * 'url:', which only means anything once the adjacent box is filled in — so
	 * an empty box yields an empty target and Menu::save() drops the row.
	 *
	 * @param array<string,mixed> $row
	 */
	private static function menu_target( array $row ): string {
		$target = trim( (string) ( $row['target'] ?? '' ) );
		if ( 'url:' !== $target ) {
			return $target;
		}
		$custom = trim( (string) ( $row['custom'] ?? '' ) );
		return '' === $custom ? '' : 'url:' . $custom;
	}

	/**
	 * Apply one move to the tree. $path is '<i>' for a top-level row or
	 * '<i>-<j>' for a child; '' is a no-op, which is the normal case for four
	 * of the five buttons on any given request.
	 *
	 * @param array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}> $tree
	 * @return array<int,array{label:string,target:string,children:array<int,array{label:string,target:string}>}>
	 */
	private static function menu_move( array $tree, string $op, string $path ): array {
		if ( '' === $path ) {
			return $tree;
		}
		$parts  = explode( '-', $path );
		$i      = (int) $parts[0];
		$j      = isset( $parts[1] ) ? (int) $parts[1] : null;
		$child  = null !== $j;
		if ( ! isset( $tree[ $i ] ) || ( $child && ! isset( $tree[ $i ]['children'][ $j ] ) ) ) {
			return $tree;
		}

		if ( 'remove' === $op ) {
			if ( $child ) {
				unset( $tree[ $i ]['children'][ $j ] );
				$tree[ $i ]['children'] = array_values( $tree[ $i ]['children'] );
			} else {
				unset( $tree[ $i ] );
				$tree = array_values( $tree );
			}
			return $tree;
		}

		if ( 'indent' === $op && ! $child && $i > 0 ) {
			// The row and everything under it hang off the row above; a third
			// level cannot exist, so its own children are promoted alongside it.
			$row   = $tree[ $i ];
			$moved = array_merge(
				array( array( 'label' => $row['label'], 'target' => $row['target'] ) ),
				array_map(
					static fn( array $c ): array => array( 'label' => $c['label'], 'target' => $c['target'] ),
					$row['children']
				)
			);
			$tree[ $i - 1 ]['children'] = array_merge( $tree[ $i - 1 ]['children'], $moved );
			unset( $tree[ $i ] );
			return array_values( $tree );
		}

		if ( 'outdent' === $op && $child ) {
			$row = $tree[ $i ]['children'][ $j ];
			unset( $tree[ $i ]['children'][ $j ] );
			$tree[ $i ]['children'] = array_values( $tree[ $i ]['children'] );
			array_splice( $tree, $i + 1, 0, array( array( 'label' => $row['label'], 'target' => $row['target'], 'children' => array() ) ) );
			return array_values( $tree );
		}

		$delta = 'up' === $op ? -1 : ( 'down' === $op ? 1 : 0 );
		if ( 0 === $delta ) {
			return $tree;
		}

		if ( $child ) {
			$to = $j + $delta;
			if ( isset( $tree[ $i ]['children'][ $to ] ) ) {
				$swap                          = $tree[ $i ]['children'][ $to ];
				$tree[ $i ]['children'][ $to ] = $tree[ $i ]['children'][ $j ];
				$tree[ $i ]['children'][ $j ]  = $swap;
			}
			return $tree;
		}

		$to = $i + $delta;
		if ( isset( $tree[ $to ] ) ) {
			$swap        = $tree[ $to ];
			$tree[ $to ] = $tree[ $i ];
			$tree[ $i ]  = $swap;
		}
		return $tree;
	}
```

- [ ] **Step 4: Run to verify it passes**

Run: `composer test -- --filter ContentControllerTest`
Expected: PASS.

Run: `composer test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-content-controller.php tests/php/ContentControllerTest.php
git commit -m "feat: save, reorder and nest menu items from the Menu tab"
```

---

### Task 9: The shared link picker, browser test, version and changelog

**Files:**
- Modify: `includes/admin/class-content-screen.php` (`links_datalist()`)
- Modify: `includes/admin/class-content-controller.php` (pass targets into the model — already done in Task 7; reuse `menu_targets`)
- Create: `tests/menu-editor.spec.js`
- Modify: `blueworx-labs-clubhouse.php`, `CHANGELOG.md`
- Test: `tests/php/ContentScreenTest.php` if one exists, else add the assertion to `MenuPanelTest.php`

- [ ] **Step 1: Write the failing datalist test**

Add to `tests/php/MenuPanelTest.php` (or an existing Content_Screen test file if there is one):

```php
	public function test_the_shared_datalist_offers_anchors_and_filters(): void {
		$html = Blueworx_Clubhouse_Content_Controller::screen_html( new Blueworx_Clubhouse_Fake_Storage(), array() );
		$this->assertStringContainsString( 'About → History', $html );
		$this->assertStringContainsString( 'Sports → Rugby', $html );
	}
```

If `screen_html()` cannot run under the test stubs (it calls `wp_nonce_field()` and `admin_url()`), check `tests/php/wp-stubs.php` — both are likely stubbed already, since `ContentControllerTest` exists. If not, assert against `Content_Screen::render()` with a hand-built model instead.

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter test_the_shared_datalist`
Expected: FAIL — the datalist still holds only the nine page URLs.

- [ ] **Step 3: Widen the datalist**

In `includes/admin/class-content-screen.php`, replace `links_datalist()`'s body and update its docblock:

```php
	/**
	 * The suggestion list every URL field points at, drawn from the same
	 * Link_Catalogue the menu editor uses — so a link an owner can pick in the
	 * menu is a link they can pick anywhere.
	 *
	 * Suggestions only: the input stays a free-text URL field, because plenty of
	 * links point at pages this plugin does not own.
	 *
	 * @param array<int,array{target:string,label:string,group:string,url:string}> $targets
	 */
	private static function links_datalist( array $targets ): string {
		$out  = '<datalist id="' . self::LINKS_DATALIST_ID . '">';
		$seen = array();
		foreach ( $targets as $entry ) {
			if ( '' === $entry['url'] || in_array( $entry['url'], $seen, true ) ) {
				continue;
			}
			$seen[] = $entry['url'];
			$out   .= '<option value="' . self::esc( $entry['url'] ) . '" label="' . self::esc( $entry['label'] ) . '">'
				. self::esc( $entry['label'] ) . '</option>';
		}
		return $out . '</datalist>';
	}
```

and in `render()` change the call to `$out .= self::links_datalist( $model['menu_targets'] );`.

- [ ] **Step 4: Run to verify it passes**

Run: `composer test`
Expected: PASS.

- [ ] **Step 5: Write the browser test**

This spec edits wp-admin, so it is `@wordpress`-tagged — `playwright.config.js` keeps those out of the preview project, where there is no admin at all. Specs in this repo are CommonJS (`require`, not `import`); match that. The harness's admin credentials are `admin` / `wptest-admin-pw`, set in `bin/wp-test.mjs` — no spec logs in today, so this one does it inline rather than adding a helper module for a single caller.

Create `tests/menu-editor.spec.js`:

```js
const { test, expect } = require('@playwright/test');

// @wordpress only: the Menu tab lives in wp-admin, which the DB-free preview
// does not have. These specs mutate a stored option, so they run in series —
// the wordpress project is already non-parallel.

async function loginAsAdmin(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('an owner can rename, reorder and nest a menu item @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-site-content&tab=menu');

  // Row 1 of the defaults is About. Rename it, then hang it under Home.
  await page.fill('input[name="menu[1][label]"]', 'Our club');
  await page.click('button[name="clubhouse_menu_indent[1]"]');
  await expect(page.locator('input[name="menu[0][children][0][label]"]')).toHaveValue('Our club');

  await page.click('button[name="clubhouse_content_submit"]');
  await expect(page.locator('.notice-success')).toContainText('menu has been saved');

  // The front end shows it nested under its parent.
  await page.goto('/');
  const parent = page.locator('.ch-nav__item--has-children').first();
  await expect(parent).toBeVisible();
  await expect(parent.locator('.ch-nav__sub')).toContainText('Our club');

  // A submenu opens on keyboard focus alone — no pointer, no JavaScript.
  await parent.locator('.ch-nav__link').first().focus();
  await expect(parent.locator('.ch-nav__sub')).toBeVisible();
});

test('a removed item leaves the nav @wordpress', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=clubhouse-site-content&tab=menu');

  const doomed = await page.inputValue('input[name="menu[1][label]"]');
  await page.click('button[name="clubhouse_menu_remove[1]"]');
  await page.click('button[name="clubhouse_content_submit"]');

  await page.goto('/');
  await expect(page.locator('.ch-nav__links')).not.toContainText(doomed);
});
```

Both specs leave the site's menu edited. That is the same shape as the demo-mode flag `global-setup.js` seeds — state the WordPress project already carries — but if a later spec turns out to depend on the default nav, reset it at the end of the second test by clicking Remove down to nothing and re-saving, or by extending `global-setup.js` to `delete_option( 'clubhouse_menu' )` before the run. Prefer the latter; it makes every run start from the defaults.

- [ ] **Step 6: Run the browser test**

Per `docs/testing.md`, bring the WordPress harness up first and point Playwright at it — `npm run test:wp` can race the container's boot:

```bash
npm run wp:up
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8705 npx playwright test tests/menu-editor.spec.js --project=wordpress
```

`npm run test:wp` in one shot can fail with connection-refused — it races the harness's boot — so bring the harness up first and point Playwright at it. Confirm the port `npm run wp:up` prints and use that if it differs from 8705.

Expected: PASS. Fix the spec's selectors against the real markup if they miss; do not change the panel's field names to suit the test.

Then bring it down: `npm run wp:down`.

- [ ] **Step 7: Bump the version and the changelog**

In `blueworx-labs-clubhouse.php`, both places:

```php
 * Version:           0.46.0
```
```php
define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.46.0' );
```

In `CHANGELOG.md`, a new entry at the top matching the file's existing format:

```markdown
## 0.46.0

- Added a Menu tab to Club Content: reorder, rename and nest header nav items, and point each one at a page, a section of a page, a filtered sport/team/event view, or a custom URL.
- Every editable section now carries a stable anchor id, so a menu item can link straight to it.
- The suggestion list behind every URL field in Club Content now offers section anchors and filtered collection views, not just the top-level pages.
```

- [ ] **Step 8: Lint once, and report**

Run: `composer lint`

Do **not** fix what it reports. Collect the findings and present them to the user at the end of the session for a decision.

- [ ] **Step 9: Commit and open the pull request**

```bash
git add -A
git commit -m "feat: editable header menu (v0.46.0)"
git push -u origin editable-menu
```

Open a PR against `main` titled `feat: an editable header menu (v0.46.0)`, body summarising the three user-visible changes from the changelog entry.

---

## Notes for the implementer

- **The spec said `filter:teams:1st-xv`; it is wrong.** `/teams` filters by the team's *sport*, so the real target is `filter:teams:rugby`. Task 2 explains why. The spec's other examples are correct.
- **`Fake_Storage` and `Demo_Collections`** are the test doubles this codebase already uses — `tests/php/bootstrap.php` loads them. Do not write new ones.
- **The preview must keep working.** It has no `Storage`, so `Menu::current()` returns the defaults there. If you find yourself needing to give the preview a real menu, stop — that is a scope change, not an implementation detail.
