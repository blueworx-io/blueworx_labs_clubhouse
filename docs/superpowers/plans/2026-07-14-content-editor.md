# Content Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give a Clubhouse owner a bespoke, tabbed "Site Content" admin screen that edits the singular page copy currently hardcoded in `Page_Renderer`, backed by the existing-but-unwired `Content_Store` as an override layer over today's defaults.

**Architecture:** Pure/glue split mirroring the Setup screen. A pure declarative `Content_Catalogue` (page→section→type+fields) is the single source of truth, shaped 1:1 to the visibility inventory. A pure `Content_Screen` renders the HTML; a WP-glue `Content_Controller` handles menu/enqueue/save. `Page_Renderer` gains a nullable `?Content_Store` param and reads each value through `cget()`/`citems()` helpers that fall back to today's literals — so an unedited site renders byte-for-byte identically. CPT-backed sections link out to native screens; genuine non-CPT lists are inline "Add new" loops.

**Tech Stack:** PHP 8.2+, WordPress, PHPUnit 11 (DB-free via `tests/php/wp-stubs.php` + `Fake_Storage`), PHP_CodeSniffer, `wp.media` for image picking. No new runtime dependencies.

## Global Constraints

- **Version floor:** plugin currently 0.22.1. This is a feature → **minor bump to 0.23.0** in `blueworx-labs-clubhouse.php` (header comment + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json`; add a matching `CHANGELOG.md` entry. (House rule: bump alongside the change.)
- **No new dependency** without approval — none are added here.
- **Every new class file** starts with `<?php declare(strict_types=1);` then `if ( ! defined( 'ABSPATH' ) ) { exit; }` and `@package BlueworxLabsClubhouse`.
- **All output escaped.** Pure render classes escape at emit (`htmlspecialchars` via the class's `esc()` helper, or `esc_html`/`esc_attr`/`esc_url` in WP-glue). Storage holds sanitised-but-raw text; escaping is a render concern.
- **Skin-agnostic CSS/JS:** `assets/css/admin-content.css` and any emitted token block use only `var(--color-*)`, `var(--font-*)`, `var(--radius-*)` — **no literal hex, no font-family names** (hygiene-tested, same guard as `admin-setup.css`).
- **Progressive enhancement:** the screen is fully usable JS-off (tab/section switching via `<a>` links carrying query args; Save via form submit; loop add/remove via submit buttons). JS upgrades to no-reload + drag-scroll.
- **Wire controllers from `blueworx_labs_clubhouse_init()`** only — never a bare `is_admin()` block (a past merge silently dropped such wiring).
- **Menu slug `clubhouse-site-content`** (the slug `clubhouse-content` is already taken by the CPT collections group).
- **Byte-identical guarantee:** with `$content === null` or an empty store, every `Page_Renderer` method must emit output identical to before this plan. The DB-free preview passes no content store, so preview + all existing render/preview tests stay green unchanged.
- **Lockstep:** `Content_Catalogue` page slugs + section keys must equal `Setup_Sections::inventory()` page/section keys exactly (enforced by a test).

## Reference artifact

The approved visual design is `C:\Users\LukeMcfarland\Downloads\Sleek onboarding flow\Clubhouse Content.dc.html`. Its `<script>` carries the full data model (page/section list, field helpers `T/A/I/U/B`, section types `fields`/`coll`/`auto`/`link`/`note`, theme token maps for court/members/floodlight). Use it as the source of truth for **layout and interaction**; use `Content_Catalogue` (Task 1) as the source of truth for **which fields exist**, reconciled per the spec's three divergences (Ticker/Stats/News/Info strip are editable loops, not `auto`; CPT sections are link-outs, not inline loops).

Design spec: `docs/superpowers/specs/2026-07-14-content-editor-design.md`.

---

## File Structure

**Create:**
- `includes/admin/class-content-catalogue.php` — pure declarative catalogue.
- `includes/admin/class-content-screen.php` — pure HTML renderer.
- `includes/admin/class-content-controller.php` — WP glue (menu, enqueue, model, save).
- `assets/css/admin-content.css` — full-bleed skin, token-driven.
- `assets/js/admin-content.js` — tab/section switching, drag-scroll, wp.media, unsaved-state.
- `tests/php/ContentCatalogueTest.php`, `tests/php/ContentScreenTest.php`, `tests/php/ContentControllerTest.php`, `tests/php/PageRendererContentOverrideTest.php`.

**Modify:**
- `includes/render/class-page-renderer.php` — add `cget()`/`citems()`; thread `?Content_Store $content = null` into all 9 page methods; replace literals with helper calls.
- `includes/render/class-page-map.php` — thread `?Content_Store $content = null` through `render()`.
- `includes/frontend/class-clubhouse-context.php` — add `?Content_Store $content` slot.
- `includes/frontend/class-frontend.php` — build `Content_Store` in `context()`; pass `$ctx->content` in `render_body()`.
- `includes/content/class-content-store.php` — add `get_items()`/`set_items()` loop helpers.
- `includes/admin/class-owner-capabilities.php` — add `clubhouse-site-content` to `menu_allowlist()`.
- `blueworx-labs-clubhouse.php` — `require_once` the three new admin classes; `Content_Controller::register()` in init; version → 0.23.0.
- `tests/php/bootstrap.php` — `require_once` the three new admin classes (+ `Content_Store` if not already loaded).
- `package.json`, `CHANGELOG.md` — version + changelog.

---

## Task 1: Content_Store loop helpers

**Files:**
- Modify: `includes/content/class-content-store.php`
- Test: `tests/php/ContentStoreTest.php` (existing — append)

**Interfaces:**
- Produces: `Content_Store::get_items(string $page, string $section): array` (returns the `items` array or `[]`); `Content_Store::set_items(string $page, string $section, array $items): void`.

- [ ] **Step 1: Write failing tests** — append to `tests/php/ContentStoreTest.php`:

```php
public function test_get_items_defaults_to_empty_array(): void {
	$store = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
	$this->assertSame( array(), $store->get_items( 'membership', 'faq' ) );
}

public function test_set_items_then_get_items_roundtrips(): void {
	$store = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
	$items = array( array( 'Question' => 'Q1', 'Answer' => 'A1' ) );
	$store->set_items( 'membership', 'faq', $items );
	$this->assertSame( $items, $store->get_items( 'membership', 'faq' ) );
}

public function test_items_are_isolated_from_field_reads(): void {
	$store = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
	$store->set( 'home', 'hero', 'eyebrow', 'Est. 1974' );
	$store->set_items( 'home', 'quick_tiles', array( array( 'Label' => 'Join' ) ) );
	$this->assertSame( 'Est. 1974', $store->get( 'home', 'hero', 'eyebrow' ) );
	$this->assertCount( 1, $store->get_items( 'home', 'quick_tiles' ) );
}
```

- [ ] **Step 2: Run — expect FAIL** (`Call to undefined method ... get_items`):

Run: `composer test -- --filter ContentStoreTest`
Expected: FAIL.

- [ ] **Step 3: Implement** — add to `class-content-store.php` (items live under the reserved `items` field key of a section):

```php
	private const ITEMS_KEY = 'items';

	/** @return array<int,array<string,mixed>> */
	public function get_items( string $page, string $section ): array {
		$val = $this->get( $page, $section, self::ITEMS_KEY, array() );
		return is_array( $val ) ? array_values( $val ) : array();
	}

	/** @param array<int,array<string,mixed>> $items */
	public function set_items( string $page, string $section, array $items ): void {
		$this->set( $page, $section, self::ITEMS_KEY, array_values( $items ) );
	}
```

- [ ] **Step 4: Run — expect PASS.**

Run: `composer test -- --filter ContentStoreTest`
Expected: PASS.

- [ ] **Step 5: Commit** — `git add includes/content/class-content-store.php tests/php/ContentStoreTest.php && git commit -m "feat: Content_Store loop-item helpers"`

---

## Task 2: Content_Catalogue (pure declarative model)

**Files:**
- Create: `includes/admin/class-content-catalogue.php`
- Test: `tests/php/ContentCatalogueTest.php`

**Interfaces:**
- Produces: `Content_Catalogue::pages(): array` returning an ordered list of pages. Each page: `array{ tab:string, label:string, sections: array<int, Section> }` where `Section` = `array{ key:string, label:string, type:'fields'|'loop'|'linkout'|'auto', vis_page:string, store_page:string, note?:string, fields?:Field[], loop?:array{name:string,plural:string,fields:Field[]}, link?:array{kind:'cpt'|'section',label:string,text:string,cpt?:string,tab?:string,sec?:string}, auto?:array{text:string,cpt?:string} }` and `Field` = `array{ key:string, label:string, type:'text'|'textarea'|'url'|'image'|'toggle', placeholder?:string, rows?:int }`.
- `tab` is the UI tab key (`global`,`about`,…); `vis_page`/`vis key` map to `Visibility` (matches `Setup_Sections` inventory); `store_page` is the `Content_Store` page (shell items → `global`, home-body items → `home`, all others → same as tab).
- Helper constructors used internally: `f_text`, `f_area`, `f_url`, `f_image`, `f_toggle`.

- [ ] **Step 1: Write failing tests** — `tests/php/ContentCatalogueTest.php`. These lock the contract and the lockstep with visibility:

```php
<?php
declare(strict_types=1);

final class ContentCatalogueTest extends WP_UnitTestCase_Stub {

	public function test_returns_nine_pages_in_page_map_order(): void {
		$tabs = array_column( Blueworx_Clubhouse_Content_Catalogue::pages(), 'tab' );
		$this->assertSame(
			array( 'global', 'about', 'membership', 'contact', 'login', 'sports', 'teams', 'events', 'calendar' ),
			$tabs
		);
	}

	/** Lockstep: every catalogue section key must exist in the visibility inventory for the same page, and vice-versa. */
	public function test_section_keys_match_visibility_inventory_exactly(): void {
		$inv = array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $p ) {
			$inv[ $p['page'] ] = array_column( $p['sections'], 'key' );
			sort( $inv[ $p['page'] ] );
		}
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$vis_page = 'global' === $page['tab'] ? 'home' : $page['tab'];
			$keys     = array_map( static fn( $s ) => $s['key'], $page['sections'] );
			sort( $keys );
			$this->assertSame( $inv[ $vis_page ], $keys, "Section keys diverge for {$vis_page}" );
		}
	}

	public function test_every_section_has_a_valid_type(): void {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $s ) {
				$this->assertContains( $s['type'], array( 'fields', 'loop', 'linkout', 'auto' ), $s['key'] );
				if ( 'loop' === $s['type'] ) {
					$this->assertNotEmpty( $s['loop']['fields'] );
				}
			}
		}
	}

	public function test_cpt_linkouts_reference_real_post_types(): void {
		$known = Blueworx_Clubhouse_Collection_Types::POST_TYPES; // clubhouse_sport/team/fixture/event/sponsor/person
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			foreach ( $page['sections'] as $s ) {
				if ( 'linkout' === $s['type'] && 'cpt' === $s['link']['kind'] ) {
					$this->assertContains( $s['link']['cpt'], array_keys( $known ), $s['key'] );
				}
			}
		}
	}

	public function test_editable_divergences_are_loops_not_auto(): void {
		$global = Blueworx_Clubhouse_Content_Catalogue::pages()[0]['sections'];
		$byKey  = array();
		foreach ( $global as $s ) { $byKey[ $s['key'] ] = $s['type']; }
		$this->assertSame( 'loop', $byKey['ticker'] );
		$this->assertSame( 'loop', $byKey['stats'] );
		$this->assertSame( 'loop', $byKey['info'] );
		$this->assertSame( 'auto', $byKey['activity'] ); // genuinely derived stays auto
	}
}
```

> Note: `Collection_Types::POST_TYPES` is an assoc array keyed by post-type slug; confirm with `array_keys`. If `WP_UnitTestCase_Stub` isn't the base class other pure tests use, match the existing convention (see `SetupSectionsTest.php`).

- [ ] **Step 2: Run — expect FAIL** (class not found).

Run: `composer test -- --filter ContentCatalogueTest`

- [ ] **Step 3: Implement `class-content-catalogue.php`.** Build the full catalogue from the spec's "content catalogue" tables. Use small private static field-builders and shared section templates (hero, cta). Skeleton (fill **every** page/section per the spec — this shows the shape and the two tricky pages; replicate for the rest):

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pure declarative catalogue of editable page content, shaped 1:1 to the
 * visibility inventory. Single source of truth for the Content editor.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Content_Catalogue {

	private static function f_text( string $key, string $label, string $ph = '' ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'text', 'placeholder' => $ph );
	}
	private static function f_area( string $key, string $label, int $rows = 3, string $ph = '' ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'textarea', 'rows' => $rows, 'placeholder' => $ph );
	}
	private static function f_url( string $key, string $label, string $ph = 'https://…' ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'url', 'placeholder' => $ph );
	}
	private static function f_image( string $key, string $label ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'image' );
	}
	private static function f_toggle( string $key, string $label ): array {
		return array( 'key' => $key, 'label' => $label, 'type' => 'toggle' );
	}

	/** Shared hero field set — keys map to Page_Renderer::hero() inputs. */
	private static function hero_fields(): array {
		return array(
			self::f_text( 'eyebrow', 'Eyebrow', 'e.g. Est. 1974 · Marlow, UK' ),
			self::f_text( 'title_lead', 'Heading' ),
			self::f_text( 'title_highlight', 'Highlighted phrase' ),
			self::f_area( 'lede', 'Subheading' ),
			self::f_text( 'cta_primary', 'Primary button label' ),
			self::f_url( 'cta_primary_href', 'Primary button link' ),
			self::f_text( 'cta_secondary', 'Secondary button label' ),
			self::f_url( 'cta_secondary_href', 'Secondary button link' ),
			self::f_image( 'image', 'Background image' ),
		);
	}
	private static function cta_fields(): array {
		return array(
			self::f_text( 'heading', 'Heading' ),
			self::f_area( 'body', 'Body' ),
			self::f_text( 'cta_label', 'Button label' ),
			self::f_url( 'cta_href', 'Button link' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function pages(): array {
		return array(
			array( 'tab' => 'global', 'label' => 'Global', 'sections' => array(
				array( 'key' => 'header', 'label' => 'Header', 'type' => 'fields', 'store_page' => 'global',
					'note' => 'Logo and club name come from Site setup → Branding.',
					'fields' => array( self::f_text( 'cta_label', 'Menu CTA label', 'e.g. Join the Club' ), self::f_url( 'cta_href', 'Menu CTA link' ) ) ),
				array( 'key' => 'hero', 'label' => 'Hero', 'type' => 'fields', 'store_page' => 'home', 'fields' => self::hero_fields() ),
				array( 'key' => 'quick_tiles', 'label' => 'Quick tiles', 'type' => 'loop', 'store_page' => 'home',
					'loop' => array( 'name' => 'Tile', 'plural' => 'Tiles', 'fields' => array( self::f_text( 'label', 'Label' ), self::f_url( 'href', 'Link' ) ) ) ),
				array( 'key' => 'ticker', 'label' => 'Ticker', 'type' => 'loop', 'store_page' => 'home',
					'loop' => array( 'name' => 'Message', 'plural' => 'Messages', 'fields' => array( self::f_text( 'text', 'Message' ) ) ) ),
				array( 'key' => 'stats', 'label' => 'Stats', 'type' => 'loop', 'store_page' => 'home',
					'loop' => array( 'name' => 'Stat', 'plural' => 'Stats', 'fields' => array( self::f_text( 'value', 'Value' ), self::f_text( 'label', 'Label' ), self::f_toggle( 'featured', 'Featured' ) ) ) ),
				array( 'key' => 'sports', 'label' => 'Sports grid', 'type' => 'linkout', 'store_page' => 'home',
					'fields' => array( self::f_text( 'heading', 'Heading' ), self::f_area( 'eyebrow', 'Intro' ) ),
					'link' => array( 'kind' => 'cpt', 'cpt' => 'clubhouse_sport', 'label' => 'Manage sports', 'text' => 'The sports shown here are managed in one place — the Sports collection.' ) ),
				array( 'key' => 'clubhouse', 'label' => 'Clubhouse band', 'type' => 'fields', 'store_page' => 'home',
					'fields' => array( self::f_text( 'eyebrow', 'Eyebrow' ), self::f_text( 'heading', 'Heading' ), self::f_image( 'image', 'Image' ), self::f_text( 'cta_label', 'Button label' ), self::f_url( 'cta_href', 'Button link' ) ) ),
				array( 'key' => 'membership', 'label' => 'Membership tiers', 'type' => 'linkout', 'store_page' => 'home',
					'fields' => array( self::f_text( 'eyebrow', 'Eyebrow' ), self::f_text( 'heading', 'Heading' ), self::f_area( 'lede', 'Intro' ), self::f_text( 'cta_label', 'Button label' ), self::f_url( 'cta_href', 'Button link' ) ),
					'link' => array( 'kind' => 'section', 'tab' => 'membership', 'sec' => 'tiers', 'label' => 'Edit tiers', 'text' => 'Tiers are managed in one place — the Membership page.' ) ),
				array( 'key' => 'activity', 'label' => 'Activity tabs', 'type' => 'auto', 'store_page' => 'home',
					'auto' => array( 'text' => 'Built from each sport’s latest fixtures, results and standings.', 'cpt' => 'clubhouse_event' ) ),
				array( 'key' => 'news', 'label' => 'News', 'type' => 'loop', 'store_page' => 'home',
					'fields' => array( self::f_text( 'eyebrow', 'Eyebrow' ), self::f_text( 'heading', 'Heading' ) ),
					'loop' => array( 'name' => 'Article', 'plural' => 'Articles', 'fields' => array( self::f_text( 'tag', 'Tag' ), self::f_text( 'date', 'Date' ), self::f_text( 'title', 'Title' ), self::f_image( 'image', 'Image' ) ) ) ),
				array( 'key' => 'info', 'label' => 'Info strip', 'type' => 'loop', 'store_page' => 'home',
					'loop' => array( 'name' => 'Column', 'plural' => 'Columns', 'fields' => array( self::f_text( 'label', 'Label' ), self::f_area( 'lines', 'Lines (one per line)' ), self::f_text( 'link_label', 'Link label' ), self::f_url( 'link_href', 'Link href' ) ) ) ),
				array( 'key' => 'sponsors', 'label' => 'Sponsors', 'type' => 'linkout', 'store_page' => 'home',
					'link' => array( 'kind' => 'cpt', 'cpt' => 'clubhouse_sponsor', 'label' => 'Manage sponsors', 'text' => 'Sponsors are managed as a collection.' ) ),
				array( 'key' => 'social', 'label' => 'Social', 'type' => 'fields', 'store_page' => 'home',
					'note' => 'Profile links come from Site setup → Branding.',
					'fields' => array( self::f_text( 'heading', 'Heading' ), self::f_area( 'lede', 'Lede' ) ) ),
				array( 'key' => 'footer', 'label' => 'Footer', 'type' => 'fields', 'store_page' => 'global',
					'note' => 'Contact details and social links come from Site setup → Branding.',
					'fields' => array( self::f_area( 'blurb', 'About blurb' ) ) ),
			) ),
			// ... about, membership, contact, login, sports, teams, events, calendar — build every section
			// per the spec tables. hero sections use self::hero_fields(); cta sections use self::cta_fields().
			// linkout CPT targets: committee/directory → clubhouse_person, sports directory → clubhouse_sport,
			// teams directory → clubhouse_team, events upcoming → clubhouse_event. events 'past' + calendar
			// 'schedule' auto note; calendar schedule also carries fields heading/intro (type 'fields' with an auto note is fine —
			// model it as type 'fields' + an 'auto' note block).
		);
	}
}
```

> Field `key`s for `fields`/hero/cta sections are the **exact `Content_Store` field names** the renderer reads (Task 4). For loops, item field `key`s are the exact keys stored per item and read by the renderer. Keep them stable — they are the storage contract.

- [ ] **Step 4: Run — expect PASS** (all `ContentCatalogueTest`).

- [ ] **Step 5: Commit** — `git add includes/admin/class-content-catalogue.php tests/php/ContentCatalogueTest.php && git commit -m "feat: pure Content_Catalogue (1:1 with visibility inventory)"`

> Before committing, add `require_once .../class-content-catalogue.php` to `tests/php/bootstrap.php` so the test can load it.

---

## Task 3: Page_Renderer override helpers + null-safe threading

**Files:**
- Modify: `includes/render/class-page-renderer.php` (add helpers + params)
- Modify: `includes/render/class-page-map.php`
- Test: `tests/php/PageRendererContentOverrideTest.php`

**Interfaces:**
- Produces: private `Page_Renderer::cget(?Content_Store $c, string $page, string $sec, string $field, mixed $default): mixed` and `citems(?Content_Store $c, string $page, string $sec, array $default): array`. All 9 page methods gain a trailing `?Blueworx_Clubhouse_Content_Store $content = null`. `Page_Map::render(...)` gains a trailing `?Blueworx_Clubhouse_Content_Store $content = null` and forwards it.

- [ ] **Step 1: Write failing test** — `tests/php/PageRendererContentOverrideTest.php`. Golden (null store) parity is implicitly covered by the *existing* render tests staying green; this new test asserts the helpers and a threaded override work:

```php
<?php
declare(strict_types=1);

final class PageRendererContentOverrideTest extends WP_UnitTestCase_Stub {

	private function ctx(): array {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		return array(
			new Blueworx_Clubhouse_Branding( $s ),
			new Blueworx_Clubhouse_Visibility( $s ),
			new Blueworx_Clubhouse_Demo_Collections(),
			new Blueworx_Clubhouse_Content_Store( $s ),
		);
	}

	public function test_null_content_renders_default_hero(): void {
		[ $b, $v, $c ] = $this->ctx();
		$html = Blueworx_Clubhouse_Page_Renderer::home( $b, $v, $c, '', null );
		$this->assertStringContainsString( 'One community.', $html ); // today's default highlight
	}

	public function test_content_override_replaces_hero_heading(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'home', 'hero', 'title_highlight', 'One club.' );
		$html = Blueworx_Clubhouse_Page_Renderer::home( $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'One club.', $html );
		$this->assertStringNotContainsString( 'One community.', $html );
	}

	public function test_page_map_render_threads_content(): void {
		[ $b, $v, $c, $content ] = $this->ctx();
		$content->set( 'home', 'hero', 'title_highlight', 'Threaded!' );
		$html = Blueworx_Clubhouse_Page_Map::render( '', $b, $v, $c, '', $content );
		$this->assertStringContainsString( 'Threaded!', $html );
	}
}
```

- [ ] **Step 2: Run — expect FAIL** (too few arguments / unknown 5th param).

Run: `composer test -- --filter PageRendererContentOverrideTest`

- [ ] **Step 3: Implement helpers + threading.** In `class-page-renderer.php` add near the top of the class:

```php
	/** Read a single content field, falling back to the hardcoded default when unset or no store. */
	private static function cget( ?Blueworx_Clubhouse_Content_Store $c, string $page, string $sec, string $field, mixed $default ): mixed {
		if ( null === $c ) { return $default; }
		$v = $c->get( $page, $sec, $field, null );
		return ( null === $v || '' === $v ) ? $default : $v;
	}

	/** Read a loop's stored items, falling back to the hardcoded default array when none saved. */
	private static function citems( ?Blueworx_Clubhouse_Content_Store $c, string $page, string $sec, array $default ): array {
		if ( null === $c ) { return $default; }
		$items = $c->get_items( $page, $sec );
		return array() === $items ? $default : $items;
	}
```

Add `?Blueworx_Clubhouse_Content_Store $content = null` as the trailing param on **all nine** methods (`home/about/membership/contact/login/sports/teams/events/calendar`) — signatures only in this task; the literals are replaced in Task 4. Update `Page_Map::render()` to accept and forward it:

```php
	public static function render(
		string $slug,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null
	): string {
		// ... unchanged method lookup ...
		return call_user_func(
			array( Blueworx_Clubhouse_Page_Renderer::class, $method ),
			$branding, $visibility, $collections, $logo_url, $content
		);
	}
```

> `shell_header`/`shell_footer` also need `?Content_Store` to read `global.header` / `global.footer` — add the param (default null) and thread from each page method. Applied in Task 4.

- [ ] **Step 4: Run — expect PASS** (new test) **and** the whole suite green (null default = byte-identical):

Run: `composer test`
Expected: OK (352+ tests) — existing render/preview tests unaffected because the new param defaults null.

- [ ] **Step 5: Commit** — `git add includes/render/class-page-renderer.php includes/render/class-page-map.php tests/php/PageRendererContentOverrideTest.php && git commit -m "feat: content override helpers + null-safe threading into renderers"`

> Add `require_once .../class-content-store.php` to `tests/php/bootstrap.php` if not already present (needed by the new test).

---

## Task 4: Apply overrides to all page renderers

**Files:**
- Modify: `includes/render/class-page-renderer.php` (all 9 methods + shell_header/footer)
- Test: `tests/php/PageRendererContentOverrideTest.php` (extend)

**Interface:** consumes the field/loop `key`s from `Content_Catalogue` — every catalogue `fields[].key` and loop item `key` must be read here with the current literal as its default.

**Mechanical transform** (apply to every literal in every method): a scalar literal `'X'` passed to a `Sections::*` array becomes `self::cget( $content, <store_page>, <section_key>, <field_key>, 'X' )`; a hardcoded array (tiers, faq, news cards, stats, ticker, quick_tiles, info, values, facilities, benefits, steps, detail) becomes `self::citems( $content, <store_page>, <section_key>, <that array> )` and, where the loop items use catalogue keys that differ from the renderer's array keys, map them (see below). `store_page` per the catalogue: shell header/footer → `global`; all Home sections → `home`; other pages → the tab slug.

- [ ] **Step 1: Extend the test** — one override assertion per page proves the wiring reaches each method. Add tests like:

```php
public function test_membership_faq_loop_override(): void {
	[ $b, $v, $c, $content ] = $this->ctx();
	$content->set_items( 'membership', 'faq', array( array( 'question' => 'Custom Q?', 'answer' => 'Custom A.' ) ) );
	$html = Blueworx_Clubhouse_Page_Renderer::membership( $b, $v, $c, '', $content );
	$this->assertStringContainsString( 'Custom Q?', $html );
}

public function test_about_history_heading_override(): void {
	[ $b, $v, $c, $content ] = $this->ctx();
	$content->set( 'about', 'history', 'heading', 'Our custom story' );
	$html = Blueworx_Clubhouse_Page_Renderer::about( $b, $v, $c, '', $content );
	$this->assertStringContainsString( 'Our custom story', $html );
}

public function test_header_menu_cta_override_applies_across_pages(): void {
	[ $b, $v, $c, $content ] = $this->ctx();
	$content->set( 'global', 'header', 'cta_label', 'Sign up' );
	$html = Blueworx_Clubhouse_Page_Renderer::contact( $b, $v, $c, '', $content );
	$this->assertStringContainsString( 'Sign up', $html );
}
// + one representative override test for each remaining page: sports/teams/events/calendar/login hero,
//   and a Home loop (stats or ticker).
```

- [ ] **Step 2: Run — expect FAIL** (defaults still hardcoded).

- [ ] **Step 3: Implement.** Rewrite each method replacing literals with `cget`/`citems`. Worked example — `home()` hero + one loop (apply the same pattern throughout, using catalogue keys):

```php
if ( $visibility->is_section_visible( 'home', 'hero' ) ) {
	$out .= Blueworx_Clubhouse_Sections::hero( array(
		'eyebrow'            => self::cget( $content, 'home', 'hero', 'eyebrow', 'Est. 1974 · Marlow, UK' ),
		'title_lead'         => self::cget( $content, 'home', 'hero', 'title_lead', 'Every sport. Every age. ' ),
		'title_highlight'    => self::cget( $content, 'home', 'hero', 'title_highlight', 'One community.' ),
		'lede'               => self::cget( $content, 'home', 'hero', 'lede', "Nine sports, twenty-four teams, and a clubhouse that's always open. Come for the game — stay for the people." ),
		'cta_primary'        => self::cget( $content, 'home', 'hero', 'cta_primary', 'Explore membership' ),
		'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
		'cta_secondary'      => self::cget( $content, 'home', 'hero', 'cta_secondary', 'Take a tour →' ),
		'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'about' ),
		'image'              => self::media_src( self::cget( $content, 'home', 'hero', 'image', '' ) ),
		'image_alt'          => 'ClubHouse floodlit pitch on a Saturday',
		'image_caption'      => 'Saturday, floodlights on',
	) );
}
```

For loops with per-item catalogue keys, map stored items to the shape `Sections::*` expects. Example — stats (catalogue keys `value`/`label`/`featured`) already matches; ticker stores `{text}` but `Sections::ticker` wants a flat string array, so map:

```php
if ( $visibility->is_section_visible( 'home', 'ticker' ) ) {
	$default = array(
		array( 'text' => '1st XV promoted to Div 3 South' ),
		array( 'text' => 'Open Day — Sat 26 Jul, 10:00–14:00' ),
		array( 'text' => 'Clubhouse refurbishment complete' ),
		array( 'text' => 'Summer Football Camp · 4–8 Aug' ),
	);
	$items = self::citems( $content, 'home', 'ticker', $default );
	$out  .= Blueworx_Clubhouse_Sections::ticker( array_values( array_map(
		static fn( array $i ): string => (string) ( $i['text'] ?? '' ),
		$items
	) ) );
}
```

> **Image fields:** stored values are attachment IDs (Task 6 saves `absint`). Add a private `media_src( string $val ): string` that returns `wp_get_attachment_image_url( (int) $val, 'large' )` when `ctype_digit`, else the value as-is (keeps preview/tests, which pass '' , byte-identical). Guard `wp_get_attachment_image_url` with `function_exists` for the DB-free test path.
> **shell_header/shell_footer:** read `global.header.cta_label`/`cta_href` and `global.footer.blurb` via `cget`, threading `$content` from each page method's `shell_header(...)`/`shell_footer(...)` call.
> Keep `image_alt`/`image_caption` and any `Links::url(...)` calls as-is (not owner-editable in v1). Only the catalogue's declared fields become editable.

Apply to **every** method. The complete field list per section is the catalogue (Task 2) cross-referenced with the current literals in each method (already in the file).

- [ ] **Step 4: Run — expect PASS** and full suite green.

Run: `composer test`

- [ ] **Step 5: Commit** — `git add includes/render/class-page-renderer.php tests/php/PageRendererContentOverrideTest.php && git commit -m "feat: wire Content_Store overrides through every page renderer"`

---

## Task 5: Thread Content_Store through Frontend + context DTO

**Files:**
- Modify: `includes/frontend/class-clubhouse-context.php`
- Modify: `includes/frontend/class-frontend.php`
- Test: `tests/php/FrontendTest.php` (existing — adjust DTO construction if asserted)

**Interfaces:**
- `Clubhouse_Context` gains `public readonly ?Blueworx_Clubhouse_Content_Store $content` as the final constructor param.
- `Frontend::context()` builds `new Blueworx_Clubhouse_Content_Store( $storage )` and passes it; `render_body()` passes `$ctx->content` as the 6th arg to `Page_Map::render`.

- [ ] **Step 1:** If `FrontendTest` (or any test) constructs `Clubhouse_Context` directly, add a test asserting the new slot round-trips; otherwise add:

```php
public function test_context_dto_carries_content_store(): void {
	$s   = new Blueworx_Clubhouse_Fake_Storage();
	$ctx = new Blueworx_Clubhouse_Clubhouse_Context(
		null,
		new Blueworx_Clubhouse_Branding( $s ),
		new Blueworx_Clubhouse_Visibility( $s ),
		new Blueworx_Clubhouse_Theme_Cache( $s ),
		new Blueworx_Clubhouse_Demo_Collections(),
		Blueworx_Clubhouse_Frontend::registry( $s ),
		new Blueworx_Clubhouse_Content_Store( $s )
	);
	$this->assertInstanceOf( Blueworx_Clubhouse_Content_Store::class, $ctx->content );
}
```

- [ ] **Step 2: Run — expect FAIL** (too few args to the DTO constructor).

- [ ] **Step 3: Implement.** Add the slot to `Clubhouse_Context::__construct` (final param). In `Frontend::context()` add `$content = new Blueworx_Clubhouse_Content_Store( $storage );` and pass it as the last DTO arg. In `render_body()`:

```php
return Blueworx_Clubhouse_Page_Map::render( $slug, $ctx->branding, $ctx->visibility, $ctx->collections, $logo_url, $ctx->content );
```

Update any other `new Blueworx_Clubhouse_Clubhouse_Context(...)` construction (grep first) to pass the 7th arg.

- [ ] **Step 4: Run — expect PASS**, full suite green.

Run: `composer test`

- [ ] **Step 5: Commit** — `git add includes/frontend/ tests/php/FrontendTest.php && git commit -m "feat: thread Content_Store into the frontend render path"`

> `preview/index.php` is intentionally NOT changed — it calls `Page_Map::render(...)` without a content store (defaults null → demo copy). Confirm preview still renders via `composer test` (PreviewRenderTest) and, if convenient, `php -S localhost:8124` spot check.

---

## Task 6: Content_Controller (menu, enqueue, model, save)

**Files:**
- Create: `includes/admin/class-content-controller.php`
- Modify: `includes/admin/class-owner-capabilities.php` (allowlist)
- Test: `tests/php/ContentControllerTest.php`

**Interfaces:**
- Produces: `Content_Controller::PAGE_SLUG = 'clubhouse-site-content'`, `NONCE`, `CAPABILITY = Owner_Capabilities::SETUP_CAP`; `register()`, `add_menu()`, `enqueue(string $hook)`, `render_page()`, `screen_html(Storage, notices): string`, `build_model(Storage, notices, nonce_field, action_url): array`, and pure-testable `handle_save(array $post, Storage $storage): array` returning `array<int,array{type:string,text:string}>`.
- `handle_save` sanitises each field by its catalogue `type` and persists via `Content_Store` (`set`/`set_items`) + `Visibility` (per-section Shown/Hidden), then returns a success notice. Handles `clubhouse_content_add`/`_remove` (JS-off loop mutation) by growing/shrinking the item array before persisting.

- [ ] **Step 1: Write failing tests** — `tests/php/ContentControllerTest.php`, over `Fake_Storage`:

```php
<?php
declare(strict_types=1);

final class ContentControllerTest extends WP_UnitTestCase_Stub {

	public function test_saves_and_sanitises_a_text_field(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'hero' => array( 'title_highlight' => '  One club <script>  ' ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( 'One club', $store->get( 'home', 'hero', 'title_highlight' ) ); // tags stripped, trimmed
	}

	public function test_saves_loop_items_for_a_section(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'membership',
			'item' => array( 'membership' => array( 'faq' => array(
				array( 'question' => 'Q1', 'answer' => 'A1' ),
				array( 'question' => 'Q2', 'answer' => 'A2' ),
			) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertCount( 2, $store->get_items( 'membership', 'faq' ) );
		$this->assertSame( 'Q2', $store->get_items( 'membership', 'faq' )[1]['question'] );
	}

	public function test_unknown_field_keys_are_ignored(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'hero' => array( 'evil' => 'x', 'eyebrow' => 'ok' ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( 'ok', $store->get( 'home', 'hero', 'eyebrow' ) );
		$this->assertNull( $store->get( 'home', 'hero', 'evil' ) );
	}

	public function test_section_visibility_toggle_persists(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'hidden' => array( 'home' => array( 'ticker' => '1' ) ), // present = hide
		), $s );
		$vis = new Blueworx_Clubhouse_Visibility( $s );
		$this->assertFalse( $vis->is_section_visible( 'home', 'ticker' ) );
	}

	public function test_image_field_stored_as_attachment_id(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Content_Controller::handle_save( array(
			'clubhouse_content_tab' => 'global',
			'field' => array( 'home' => array( 'clubhouse' => array( 'image' => '42abc' ) ) ),
		), $s );
		$store = new Blueworx_Clubhouse_Content_Store( $s );
		$this->assertSame( 42, $store->get( 'home', 'clubhouse', 'image' ) );
	}
}
```

> POST shape: fields keyed `field[<store_page>][<section_key>][<field_key>]`; loop items `item[<store_page>][<section_key>][<idx>][<item_field_key>]`; per-section hide flags `hidden[<vis_page>][<section_key>]`. Only the **currently-submitted tab's** sections are persisted (so the owner editing Global doesn't blank About) — `clubhouse_content_tab` scopes the save. `handle_save` iterates the catalogue for that tab and pulls only declared keys from `$post`.

- [ ] **Step 2: Run — expect FAIL** (class not found).

- [ ] **Step 3: Implement `class-content-controller.php`.** Mirror `Setup_Controller` structure. Key methods:

```php
public const PAGE_SLUG = 'clubhouse-site-content';
public const NONCE     = 'clubhouse_content_save';
public const CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;

public static function register(): void {
	add_action( 'admin_menu', array( self::class, 'add_menu' ) );
	add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
}

public static function add_menu(): void {
	add_menu_page( 'Site Content', 'Site Content', self::CAPABILITY, self::PAGE_SLUG, array( self::class, 'render_page' ), 'dashicons-edit', 5 );
}

public static function enqueue( string $hook ): void {
	if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) { return; }
	wp_enqueue_media();
	wp_enqueue_style( 'clubhouse-admin-content', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/admin-content.css', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
	wp_enqueue_script( 'clubhouse-admin-content', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/admin-content.js', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
}

public static function render_page(): void {
	if ( ! current_user_can( self::CAPABILITY ) ) { return; }
	$storage = new Blueworx_Clubhouse_Options_Storage();
	$notices = array();
	if ( isset( $_POST['clubhouse_content_submit'] ) ) {
		check_admin_referer( self::NONCE );
		$notices = self::handle_save( wp_unslash( $_POST ), $storage );
	}
	echo self::screen_html( $storage, $notices ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Content_Screen.
}
```

`handle_save` — sanitise by catalogue type (`text`→`sanitize_text_field`, `textarea`→`sanitize_textarea_field`, `url`→`esc_url_raw`, `image`→`absint`, `toggle`→bool-from-presence), scoped to the submitted tab; apply `hidden[...]` to `Visibility::set_section_visible` (present ⇒ hidden ⇒ `false`); for add/remove, mutate the item list then `set_items`. Return `[ ['type'=>'success','text'=>'Your changes have been saved.'] ]` on success. `build_model` assembles: `catalogue` (from `Content_Catalogue::pages()` with current stored values merged in), `theming` (look tokens + `@font-face` like `Setup_Controller::look_theming`), `active_slug`, `nonce_field`, `action_url`, `notices`, per-section `hidden` map from `Visibility`.

> Guard every WP function used in `handle_save`/`build_model` that the DB-free tests reach. `sanitize_text_field`/`sanitize_textarea_field`/`esc_url_raw`/`absint` already have stubs in `tests/php/wp-stubs.php` — verify; add any missing stub there (function_exists-guarded).

- [ ] **Step 4: Add `clubhouse-site-content` to `Owner_Capabilities::menu_allowlist()`** and a test:

```php
// in an existing OwnerCapabilitiesTest or ContentControllerTest:
public function test_owner_menu_allowlist_includes_site_content(): void {
	$this->assertContains( 'clubhouse-site-content', Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist() );
}
```

Edit `menu_allowlist()`: add `'clubhouse-site-content'` to the array.

- [ ] **Step 5: Run — expect PASS.**

Run: `composer test -- --filter 'ContentControllerTest|OwnerCapabilities'`

- [ ] **Step 6: Commit** — `git add includes/admin/class-content-controller.php includes/admin/class-owner-capabilities.php tests/php/ContentControllerTest.php && git commit -m "feat: Content_Controller save/sanitise + owner menu allowlist"`

> Add `require_once` for the controller to `tests/php/bootstrap.php`.

---

## Task 7: Content_Screen (pure HTML)

**Files:**
- Create: `includes/admin/class-content-screen.php`
- Test: `tests/php/ContentScreenTest.php`

**Interfaces:**
- Produces: `Content_Screen::render( array $model ): string`. Model shape = what `Content_Controller::build_model` returns (Task 6): `nonce_field`, `action_url`, `notices`, `catalogue` (pages→sections with merged current values + `hidden` state + loop items), `look_tokens`, `font_face_css`, `active_slug`.

- [ ] **Step 1: Write failing tests** — assert structure, escaping, hygiene, JS-off:

```php
<?php
declare(strict_types=1);

final class ContentScreenTest extends WP_UnitTestCase_Stub {

	private function model(): array {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		return Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '<input name="_wpnonce">', 'http://x/admin.php?page=clubhouse-site-content' );
	}

	public function test_renders_a_tab_per_page(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		foreach ( array( 'Global', 'About', 'Membership', 'Contact', 'Log in', 'Sports', 'Teams', 'Events', 'Calendar' ) as $name ) {
			$this->assertStringContainsString( $name, $html );
		}
	}

	public function test_escapes_stored_values(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Content_Store( $s ) )->set( 'home', 'hero', 'title_lead', '<script>alert(1)</script>' );
		$model = Blueworx_Clubhouse_Content_Controller::build_model( $s, array(), '', '' );
		$html  = Blueworx_Clubhouse_Content_Screen::render( $model );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_stylesheet_tokens_have_no_literal_hex_or_font_names(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		// Emitted :root token block is server-controlled; assert no bare font-family names leak into markup styles.
		$this->assertDoesNotMatchRegularExpression( '/font-family:\s*[\'\"]?(Syne|Inter|Fraunces|Bricolage)/i', $html );
	}

	public function test_linkout_section_renders_manage_button(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		$this->assertStringContainsString( 'Manage sports', $html );
		$this->assertStringContainsString( 'post_type=clubhouse_sport', $html );
	}

	public function test_js_off_tab_links_and_save_present(): void {
		$html = Blueworx_Clubhouse_Content_Screen::render( $this->model() );
		$this->assertStringContainsString( 'clubhouse_content_submit', $html ); // save submit
		$this->assertStringContainsString( 'tab=about', $html );                 // tab link carries state
	}
}
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement `class-content-screen.php`.** Pure, WP-free (use `admin_url`-independent strings — the model supplies `action_url`; CPT links built from a passed base or `admin.php`-relative `edit.php?post_type=…`). Structure mirrors the mockup `Clubhouse Content.dc.html`:
  - `.clubhouse-wrap` root + `.clubhouse-content` flex column (full-bleed); header (eyebrow "Clubhouse · Site content", H1 "Clubhouse Content", "Site setup →" link to `admin.php?page=clubhouse-setup`).
  - Emit the active look's `:root` tokens scoped to `.clubhouse-content` + `@font-face` in a `<style>` block **raw** (use the same `css_tokens()` raw-emit approach as `Setup_Screen` — HTML-entity escaping breaks `<style>`; see the spec's note and the v0.22.0 font-escaping fix).
  - Page tabs as `<a href="?page=clubhouse-site-content&tab=<tab>">`; the active tab from `$model` (default first). Left section-nav as `<a ...&sec=<key>>` with meta badge (`off`/count/`auto`). Right panel for the active section by `type` (fields grid / loop list with Add+Remove submit buttons / link-out card / auto note); Shown/Hidden toggle as a checkbox named `hidden[<vis_page>][<key>]`.
  - Every input `name` follows the POST shape from Task 6. Every dynamic value escaped via a private `esc()` (htmlspecialchars, ENT_QUOTES) for text/attr and `esc_url`-equivalent for hrefs.
  - Sticky footer: unsaved hint + `<button name="clubhouse_content_submit">Save changes</button>`; include `$model['nonce_field']`.
  - Wrap all in `<form method="post" action="<action_url>">`.

  Use `Content_Catalogue::pages()` + merged model values to drive the loops. Keep it skin-agnostic (only `var(--…)` in the emitted CSS/inline styles; no literal hex/font names).

- [ ] **Step 4: Run — expect PASS.**

Run: `composer test -- --filter ContentScreenTest`

- [ ] **Step 5: Commit** — `git add includes/admin/class-content-screen.php tests/php/ContentScreenTest.php && git commit -m "feat: pure Content_Screen HTML renderer"`

---

## Task 8: Assets — admin-content.css + admin-content.js

**Files:**
- Create: `assets/css/admin-content.css`
- Create: `assets/js/admin-content.js`
- Test: none new (CSS/JS not unit-tested; hygiene asserted via Content_Screen; behaviour via manual smoke). A stylesheet-hygiene test is optional but recommended:

**Interfaces:** consumed by `Content_Controller::enqueue`.

- [ ] **Step 1 (optional test): stylesheet hygiene** — `tests/php/ContentScreenTest.php`:

```php
public function test_stylesheet_file_has_no_literal_colours_or_font_names(): void {
	$css = file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/admin-content.css' );
	$this->assertDoesNotMatchRegularExpression( '/(?<!&)#[0-9a-fA-F]{3,6}\b/', $css );
	$this->assertDoesNotMatchRegularExpression( '/(Syne|Inter|Fraunces|Bricolage|Mulish|Hanken)/i', $css );
}
```

- [ ] **Step 2: Run — expect FAIL** (file missing).

- [ ] **Step 3: Implement `admin-content.css`.** Reuse the Setup full-bleed scoping, retargeted to this page's body class:

```css
/* Clubhouse Site Content — bespoke admin skin. Colour/type via injected look
   tokens only (var(--color-*)/var(--font-*)/var(--radius-*)); no literals. */
.wrap.clubhouse-wrap { margin: 0; }
body.toplevel_page_clubhouse-site-content #wpcontent { padding-left: 0; }
body.toplevel_page_clubhouse-site-content #wpbody-content { padding-bottom: 0; }
body.toplevel_page_clubhouse-site-content #wpfooter { display: none; }

.clubhouse-content { display: flex; flex-direction: column; min-height: calc(100vh - 32px); margin: 0; background: var(--color-bg); color: var(--color-ink); font-family: var(--font-body); }
.clubhouse-content * { box-sizing: border-box; }
/* header, tabs, two-column body (nav + panel), fields grid, loop rows, link-out
   card, auto note, sticky save footer — port the mockup's layout using tokens.
   Panels/sections visible by default; JS enhances switching. Match admin-setup.css
   idioms (.clubhouse-head, tokens, radii). Save footer pinned via margin-top:auto. */
```

Fill in the full ruleset porting `Clubhouse Content.dc.html`'s layout (two-column flex, pill section-nav, card panel, sticky footer) — **tokens only**.

- [ ] **Step 4: Implement `admin-content.js`** (vanilla, no deps): add `clubhouse-content--js` class; intercept tab/section `<a>` clicks to switch active panel without reload (fall back to real navigation JS-off); horizontal drag-scroll on the tab strip (port the mockup's pointer handlers); wire "Choose image" buttons to `wp.media` (set the hidden input to the chosen attachment ID + update preview); mark the form dirty on input to toggle the Save button label/hint. Loop Add/Remove: submit the form (server handles) — or, as enhancement, clone a row client-side; keep the submit fallback authoritative.

- [ ] **Step 5: Run — expect PASS** (hygiene test).

Run: `composer test -- --filter ContentScreenTest`

- [ ] **Step 6: Commit** — `git add assets/css/admin-content.css assets/js/admin-content.js tests/php/ContentScreenTest.php && git commit -m "feat: Content editor admin CSS + JS (full-bleed, token-driven)"`

---

## Task 9: Wire it up — bootstrap, init, version, changelog

**Files:**
- Modify: `blueworx-labs-clubhouse.php`
- Modify: `tests/php/bootstrap.php`
- Modify: `package.json`, `CHANGELOG.md`

- [ ] **Step 1:** In `blueworx-labs-clubhouse.php`, `require_once` the three new admin classes (after the existing admin requires) and add to `blueworx_labs_clubhouse_init()`:

```php
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/content/class-content-store.php'; // if not already loaded by bootstrap
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-content-catalogue.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-content-screen.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-content-controller.php';
// ...
Blueworx_Clubhouse_Content_Controller::register();
```

> Verify `Content_Store` is on the runtime require path (grep `class-content-store` in `includes/bootstrap.php`; add if missing — the frontend now depends on it).

- [ ] **Step 2:** Ensure `tests/php/bootstrap.php` requires all three new admin classes + `Content_Store` (some added in earlier tasks — consolidate).

- [ ] **Step 3:** Bump version to **0.23.0** in `blueworx-labs-clubhouse.php` (header `Version:` + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json`. Prepend a `CHANGELOG.md` entry:

```markdown
## 0.23.0 — 2026-07-14
### Added
- **Site Content editor** — a bespoke, full-bleed tabbed admin screen (Clubhouse → Site Content) letting owners edit the singular page copy previously hardcoded (heroes, ticker, stats, bands, news, info strips, FAQ, steps, tiers, values, facilities, CTAs). Backed by the Content_Store as an override layer over the built-in defaults; unedited sites render identically. CPT-backed sections (sports/teams/events/sponsors/committee/directory) link out to their native screens. Inherits the active Base Look; per-section Shown/Hidden shares the visibility store with Setup.
```

- [ ] **Step 4: Run full suite + lint.**

Run: `composer test && composer lint`
Expected: OK (all tests), lint clean (fix only obvious new-file issues; per house rule, present other lint findings to the user, don't loop).

- [ ] **Step 5: Commit** — `git add -A && git commit -m "chore: wire Content editor + bump to 0.23.0"`

---

## Task 10: Final verification + manual smoke checklist

**Files:**
- Modify: `docs/manual-smoke-test.md` (append the Content-editor smoke items from the spec)

- [ ] **Step 1:** Append the spec's 9 "Manual WP smoke owed" items to `docs/manual-smoke-test.md` under a "Content editor (v0.23.0)" heading.
- [ ] **Step 2:** Run `composer test` (all green) and `composer lint`; capture counts.
- [ ] **Step 3:** Spot-check the DB-free preview is unchanged: `php -S localhost:8124` → open `preview/` → Home/About/Membership render with demo copy (proves the null-store path). (Optional, runtime.)
- [ ] **Step 4: Commit** — `git add docs/manual-smoke-test.md && git commit -m "docs: Content editor manual smoke checklist"`
- [ ] **Step 5:** Report readiness for review + the owed manual WP smoke (needs a real WP install — the screen can't run in the DB-free preview).

---

## Self-Review

**Spec coverage:**
- Placement/chrome/full-bleed → Task 6 (menu/enqueue), Task 8 (CSS scoping to `body.toplevel_page_clubhouse-site-content`). ✓
- Four section types → Catalogue (Task 2) + Screen (Task 7). ✓
- Override layer + byte-identical → Tasks 3–4 (null-safe helpers, defaults = literals) + full suite green gate. ✓
- Visibility integration (shared store) → Task 6 `hidden[...]` → `Visibility`. ✓
- Pure/glue split → Catalogue/Screen pure (Tasks 2, 7); Controller glue (Task 6). ✓
- Sanitisation by type → Task 6. ✓
- Link-out to CPTs / three divergences → Catalogue encodes them; tests assert (Task 2). ✓
- Testing strategy → each task ships tests; lockstep, override precedence, screen hygiene, save sanitise. ✓
- Manual smoke → Task 10. ✓
- Wiring from init (not is_admin) + slug non-collision + allowlist → Tasks 6, 9. ✓
- Version bump + changelog → Task 9. ✓

**Placeholder scan:** The one deliberately non-transcribed block is the *remaining* catalogue pages (Task 2) and the full CSS/JS/Screen HTML (Tasks 7–8) — these are fully determined by (a) the spec's catalogue tables and (b) the concrete mockup file `Clubhouse Content.dc.html`, both named as authoritative. The field `key`s, POST shape, storage keys, method signatures, and every test are spelled out. No "TBD/add validation/handle edge cases".

**Type consistency:** `cget`/`citems` signatures identical across Tasks 3–4; `Content_Store::get_items`/`set_items` defined in Task 1 and consumed in Tasks 3/6; DTO 7th param `content` consistent Tasks 5; `handle_save(array,Storage):array` consistent Tasks 6/7; `PAGE_SLUG='clubhouse-site-content'` consistent Tasks 6/8/9. ✓
