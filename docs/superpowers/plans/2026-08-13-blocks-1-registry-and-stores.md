# Blocks, plan 1 of 5: type registry and stores

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the data spine for the block library — the block-type registry, the render context, the block store and the page-composition store — with nothing wired to the front end yet.

**Architecture:** Four new pure classes under `includes/blocks/`, following the plugin's existing pure/glue split: pure data and logic with no WordPress calls, persisted through the existing `Blueworx_Clubhouse_Storage` interface exactly as `Content_Store` and `Visibility` do. Nothing in this plan changes a single byte of rendered output — the front end still runs the eleven hardcoded page methods. That is deliberate: this plan is complete and mergeable on its own, and every later plan builds on interfaces fixed here.

**Tech Stack:** PHP 8.1+, PHPUnit 10 (`composer test`), PHP_CodeSniffer (`composer lint`). No new dependencies.

Spec: `docs/superpowers/specs/2026-08-13-page-composition-and-block-library-design.md`.

## Global Constraints

- **No new dependencies.** `approved-deps.json` governs npm; adding anything to it needs prior approval. This plan needs nothing new.
- **Pure classes stay WordPress-free.** No `esc_*`, `get_option`, `wp_*` or `add_action` calls in anything under `includes/blocks/` in this plan. Persistence goes through `Blueworx_Clubhouse_Storage`. Tests use `Blueworx_Clubhouse_Fake_Storage`.
- **Every new runtime class is explicitly required** in `includes/bootstrap.php`. There is no autoloader. Order matters: a class must be required after anything it references at load time.
- **Test bootstrap** is `tests/php/bootstrap.php`; it requires `includes/bootstrap.php`. Classes required there need no second entry.
- **Coding standard:** tabs for indentation, `declare(strict_types=1);`, an `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard at the top of every runtime file, WordPress spacing inside parentheses, `final class`, `@package BlueworxLabsClubhouse` docblock. Run `composer lint` before each commit; fix what it reports.
- **Version bump and changelog** happen once, at the end of the plan, in Task 7 — minor bump, since this is new capability.
- **Commit after every task**, with a plain one-line message. No hook skipping.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `includes/blocks/class-block-context.php` | Readonly DTO handed to a block's renderer: page slug, branding, collections, anchor id, filter, logo URL. Created here so later plans have a stable signature to render against. |
| `includes/blocks/class-block-types.php` | The block-type registry. One entry per renderer on `Sections`: key, label, default rank, fields, source, singleton flag, integration requirement. Pure, declarative, no rendering. |
| `includes/blocks/class-block-library.php` | Stores blocks — id, type, name, defaults key, position, content, settings. Add, get, update, rename, duplicate, delete, list. |
| `includes/blocks/class-page-composition.php` | Stores per page: whether it is enabled, and which block ids it shows. Add, remove, list, reorder-free. |
| `tests/php/BlockContextTest.php` | The DTO carries what it is given. |
| `tests/php/BlockTypesTest.php` | Registry contract: unique keys, every type complete, every current content address covered exactly once. |
| `tests/php/BlockLibraryTest.php` | Store round-trips and the rules — unique ids, duplicate, delete. |
| `tests/php/PageCompositionTest.php` | Composition round-trips, defaults, add/remove. |
| `includes/bootstrap.php` | Modify: require the four new classes. |

`Content_Catalogue`, `Setup_Sections`, `Content_Store`, `Visibility`, `Page_Renderer` and `Sections` are **not touched in this plan.**

---

### Task 1: Block context DTO

The object every block renderer will receive in plan 2. Fixing it now means later tasks have one stable signature.

**Files:**
- Create: `includes/blocks/class-block-context.php`
- Create: `tests/php/BlockContextTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Branding`, `Blueworx_Clubhouse_Collections` (both exist).
- Produces: `Blueworx_Clubhouse_Block_Context` with readonly promoted properties `page`, `branding`, `collections`, `anchor_id`, `filter`, `logo_url`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/BlockContextTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class BlockContextTest extends TestCase {

	private function ctx( string $page = 'home', string $filter = '' ): Blueworx_Clubhouse_Block_Context {
		return new Blueworx_Clubhouse_Block_Context(
			$page,
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections(),
			'ch-home-hero',
			$filter,
			'https://club.test/logo.png'
		);
	}

	public function test_it_carries_the_page_and_anchor(): void {
		$ctx = $this->ctx();
		$this->assertSame( 'home', $ctx->page );
		$this->assertSame( 'ch-home-hero', $ctx->anchor_id );
	}

	public function test_filter_and_logo_are_carried_through(): void {
		$ctx = $this->ctx( 'sports', 'netball' );
		$this->assertSame( 'netball', $ctx->filter );
		$this->assertSame( 'https://club.test/logo.png', $ctx->logo_url );
	}

	public function test_branding_and_collections_are_the_objects_given(): void {
		$ctx = $this->ctx();
		$this->assertInstanceOf( Blueworx_Clubhouse_Branding::class, $ctx->branding );
		$this->assertNotSame( array(), $ctx->collections->sports() );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `composer test -- --filter BlockContextTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Block_Context" not found`.

- [ ] **Step 3: Write the class**

Create `includes/blocks/class-block-context.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything a block needs to render itself, and nothing else. Handed to a
 * block type's renderer so the renderer never reaches for global state — the
 * same reason Clubhouse_Context exists on the frontend side.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Context {

	public function __construct(
		public readonly string $page,
		public readonly Blueworx_Clubhouse_Branding $branding,
		public readonly Blueworx_Clubhouse_Collections $collections,
		public readonly string $anchor_id = '',
		public readonly string $filter = '',
		public readonly string $logo_url = ''
	) {}
}
```

- [ ] **Step 4: Require it and run the test**

In `includes/bootstrap.php`, add after the existing `includes/content/` requires:

```php
require_once __DIR__ . '/blocks/class-block-context.php';
```

Run: `composer test -- --filter BlockContextTest`
Expected: PASS, 3 tests.

- [ ] **Step 5: Lint and commit**

```bash
composer lint
git add includes/blocks/class-block-context.php includes/bootstrap.php tests/php/BlockContextTest.php
git commit -m "feat: add the block render context"
```

---

### Task 2: Block-type registry — the table

The registry is the spine of the whole feature. Every entry corresponds to one renderer on `Sections` and to one or more of the content addresses that exist today.

**Files:**
- Create: `includes/blocks/class-block-types.php`
- Create: `tests/php/BlockTypesTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Integrations::LATEPOINT_TAG` (exists).
- Produces:
  - `Blueworx_Clubhouse_Block_Types::all(): array<string,array>` — key => entry.
  - `Blueworx_Clubhouse_Block_Types::get( string $key ): ?array`
  - `Blueworx_Clubhouse_Block_Types::has( string $key ): bool`
  - `Blueworx_Clubhouse_Block_Types::rank( string $key ): int` — the type's default position, `500` for an unknown key.
  - Each entry: `array{key:string,label:string,rank:int,source:string,singleton:bool,requires:string}`.

Fields are **not** in the registry yet — they move across from `Content_Catalogue` in plan 3, when the admin screens need them. Adding them now would duplicate a table nothing reads.

- [ ] **Step 1: Write the failing test**

Create `tests/php/BlockTypesTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class BlockTypesTest extends TestCase {

	public function test_every_entry_is_complete_and_keyed_by_its_own_key(): void {
		foreach ( Blueworx_Clubhouse_Block_Types::all() as $key => $type ) {
			$this->assertSame( $key, $type['key'], $key );
			$this->assertNotSame( '', $type['label'], $key );
			$this->assertIsInt( $type['rank'], $key );
			$this->assertContains( $type['source'], array( 'content', 'collection', 'mixed' ), $key );
			$this->assertIsBool( $type['singleton'], $key );
			$this->assertIsString( $type['requires'], $key );
		}
	}

	public function test_header_and_footer_are_the_only_singletons(): void {
		$singletons = array();
		foreach ( Blueworx_Clubhouse_Block_Types::all() as $key => $type ) {
			if ( $type['singleton'] ) {
				$singletons[] = $key;
			}
		}
		sort( $singletons );
		$this->assertSame( array( 'footer', 'header' ), $singletons );
	}

	public function test_header_ranks_first_and_footer_last(): void {
		$ranks = array_column( Blueworx_Clubhouse_Block_Types::all(), 'rank' );
		$this->assertSame( min( $ranks ), Blueworx_Clubhouse_Block_Types::rank( 'header' ) );
		$this->assertSame( max( $ranks ), Blueworx_Clubhouse_Block_Types::rank( 'footer' ) );
	}

	public function test_unknown_keys_are_reported_honestly(): void {
		$this->assertFalse( Blueworx_Clubhouse_Block_Types::has( 'no_such_type' ) );
		$this->assertNull( Blueworx_Clubhouse_Block_Types::get( 'no_such_type' ) );
		$this->assertSame( 500, Blueworx_Clubhouse_Block_Types::rank( 'no_such_type' ) );
	}

	public function test_the_booking_slot_type_names_its_integration(): void {
		$type = Blueworx_Clubhouse_Block_Types::get( 'shortcode_block' );
		$this->assertSame( Blueworx_Clubhouse_Integrations::LATEPOINT_TAG, $type['requires'] );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `composer test -- --filter BlockTypesTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Block_Types" not found`.

- [ ] **Step 3: Write the registry**

Create `includes/blocks/class-block-types.php`. The table below is derived from the renderers the eleven page methods actually call today — do not invent entries, and do not omit any.

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every kind of block a page can be built from — one entry per renderer on
 * Sections. This is the single source of truth the page editor, the library,
 * the migration and the render loop all read, so none of them can disagree
 * about what a block is.
 *
 * 'rank' is the default position for a block of this type created fresh. It is
 * not the whole ordering story: a block carries its own position, because one
 * rank per type cannot reproduce a page like About, which runs the same type
 * either side of two others. See the design spec.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Types {

	/**
	 * @param string $key       Stable identifier, matching the Sections renderer.
	 * @param string $label     Owner-facing name.
	 * @param int    $rank      Default position for a fresh block of this type.
	 * @param string $source    'content', 'collection' or 'mixed'.
	 * @param bool   $singleton True for the header and footer only.
	 * @param string $requires  Shortcode tag of a required integration, or ''.
	 */
	private static function type( string $key, string $label, int $rank, string $source, bool $singleton = false, string $requires = '' ): array {
		return array(
			'key'       => $key,
			'label'     => $label,
			'rank'      => $rank,
			'source'    => $source,
			'singleton' => $singleton,
			'requires'  => $requires,
		);
	}

	/** @return array<string,array{key:string,label:string,rank:int,source:string,singleton:bool,requires:string}> */
	public static function all(): array {
		$types = array(
			self::type( 'header', 'Header', 0, 'content', true ),

			self::type( 'home_hero', 'Home hero', 100, 'mixed' ),
			self::type( 'hero', 'Hero', 100, 'content' ),
			self::type( 'hero_filter', 'Filtered hero', 100, 'mixed' ),
			self::type( 'news_head', 'News page head', 105, 'content' ),
			self::type( 'ticker', 'Ticker', 110, 'content' ),

			self::type( 'card_grid_switch', 'Sports and teams grid', 200, 'collection' ),
			self::type( 'news_featured', 'Featured story', 205, 'collection' ),
			self::type( 'timeline', 'Timeline', 210, 'content' ),
			self::type( 'benefit_grid', 'Benefit grid', 220, 'content' ),
			self::type( 'image_band', 'Image band', 230, 'content' ),
			self::type( 'tier_grid', 'Membership tiers', 240, 'content' ),
			self::type( 'list_split', 'Included and excluded', 250, 'content' ),
			self::type( 'step_grid', 'Steps', 260, 'content' ),
			self::type( 'faq', 'FAQ', 270, 'content' ),
			self::type( 'activity_tabs', 'Fixtures, results and events', 280, 'collection' ),
			self::type( 'stat_card_grid', 'Directory cards', 290, 'collection' ),
			self::type( 'event_grid', 'Upcoming events', 300, 'collection' ),
			self::type( 'event_archive', 'Past events', 310, 'collection' ),
			self::type( 'calendar_months', 'Calendar', 320, 'collection' ),
			self::type( 'news_cards', 'News cards', 330, 'collection' ),
			self::type( 'news_grid', 'All stories', 335, 'collection' ),
			self::type( 'people_grid', 'People', 340, 'collection' ),
			self::type( 'contact_form', 'Contact form', 350, 'content' ),
			self::type( 'info_panel', 'Find us details', 360, 'content' ),
			self::type( 'sponsors', 'Sponsors', 370, 'collection' ),
			self::type( 'auth', 'Log in form', 390, 'content' ),
			self::type( 'band', 'Call to action band', 400, 'content' ),
			self::type( 'closing_band', 'Social band', 410, 'mixed' ),

			// A booking slot is a heading plus a third-party shortcode; without
			// LatePoint installed there is nothing to put in it, so the type is
			// not offered at all. Same rule Integrations::section_available applies.
			self::type( 'shortcode_block', 'Booking slot', 380, 'content', false, Blueworx_Clubhouse_Integrations::LATEPOINT_TAG ),

			self::type( 'footer', 'Footer', 500, 'content', true ),
		);

		$keyed = array();
		foreach ( $types as $type ) {
			$keyed[ $type['key'] ] = $type;
		}
		return $keyed;
	}

	/** @return array{key:string,label:string,rank:int,source:string,singleton:bool,requires:string}|null */
	public static function get( string $key ): ?array {
		return self::all()[ $key ] ?? null;
	}

	public static function has( string $key ): bool {
		return isset( self::all()[ $key ] );
	}

	/** The default position for a fresh block of this type; last for an unknown key. */
	public static function rank( string $key ): int {
		return (int) ( self::all()[ $key ]['rank'] ?? 500 );
	}
}
```

- [ ] **Step 4: Require it and run the test**

In `includes/bootstrap.php`, after the block-context require:

```php
require_once __DIR__ . '/blocks/class-block-types.php';
```

Run: `composer test -- --filter BlockTypesTest`
Expected: PASS, 5 tests.

- [ ] **Step 5: Lint and commit**

```bash
composer lint
git add includes/blocks/class-block-types.php includes/bootstrap.php tests/php/BlockTypesTest.php
git commit -m "feat: add the block type registry"
```

---

### Task 3: Prove the registry covers today's site

The registry is only trustworthy if every content address the plugin has today maps onto exactly one type. This test is the contract that later plans lean on, and it is what will catch a missed renderer.

**Files:**
- Create: `includes/blocks/class-block-addresses.php`
- Modify: `tests/php/BlockTypesTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Block_Types::has()`.
- Produces: `Blueworx_Clubhouse_Block_Addresses::map(): array<string,array{type:string,position:int}>` — keyed `"{page}/{section}"`, giving the block type that address renders as and the position it occupies on its page today. Plans 2 and 3 read this for defaults, migration and seeding.

Positions are the page's own running order in steps of ten, so a later plan can slot something between two without renumbering.

- [ ] **Step 1: Write the failing test**

Add to `tests/php/BlockTypesTest.php`:

```php
	public function test_every_address_maps_to_a_real_type(): void {
		foreach ( Blueworx_Clubhouse_Block_Addresses::map() as $address => $entry ) {
			$this->assertTrue(
				Blueworx_Clubhouse_Block_Types::has( $entry['type'] ),
				$address . ' maps to unknown type ' . $entry['type']
			);
			$this->assertIsInt( $entry['position'], $address );
		}
	}

	/**
	 * The addresses are the ones the content editor offers today. If a section is
	 * added to the catalogue without a block type behind it, the page editor
	 * would silently drop it — so the two lists are pinned together here.
	 */
	public function test_every_catalogue_address_has_a_block(): void {
		$map = Blueworx_Clubhouse_Block_Addresses::map();
		foreach ( Blueworx_Clubhouse_Content_Catalogue::index() as $address => $entry ) {
			$this->assertArrayHasKey( $address, $map, $address . ' has no block type' );
		}
	}

	/** Positions are unique within a page, or two blocks would fight for a slot. */
	public function test_positions_are_unique_within_each_page(): void {
		$seen = array();
		foreach ( Blueworx_Clubhouse_Block_Addresses::map() as $address => $entry ) {
			$page = explode( '/', $address )[0];
			$slot = $page . ':' . $entry['position'];
			$this->assertArrayNotHasKey( $slot, $seen, $address . ' collides with ' . ( $seen[ $slot ] ?? '' ) );
			$seen[ $slot ] = $address;
		}
	}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `composer test -- --filter BlockTypesTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Block_Addresses" not found`.

- [ ] **Step 3: Write the address map**

Create `includes/blocks/class-block-addresses.php`. Every entry below is taken from the order the page methods in `Page_Renderer` render today — confirm each against that file as you go; a wrong position here becomes a visibly wrong page in plan 2.

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where every piece of content the plugin ships lives today: which block type
 * renders it, and where on its page it sits. Read by the seeder and the
 * migration, so a club upgrading gets exactly the site it had.
 *
 * Positions step by ten within a page — the running order the page methods in
 * Page_Renderer produce — leaving room to slot something between two later.
 *
 * The header and footer are absent on purpose: they are singleton blocks shown
 * on every page, not entries on any one page.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Addresses {

	/** @return array<string,array{type:string,position:int}> */
	public static function map(): array {
		$pages = array(
			'global' => array(
				'header' => 'header',
				'footer' => 'footer',
			),
			'home' => array(
				'hero'        => 'home_hero',
				'quick_tiles' => 'home_hero',
				'ticker'      => 'ticker',
				'sports'      => 'card_grid_switch',
				'clubhouse'   => 'image_band',
				'membership'  => 'band',
				'activity'    => 'activity_tabs',
				'news'        => 'news_cards',
				'sponsors'    => 'sponsors',
				'social'      => 'closing_band',
				'info'        => 'closing_band',
			),
			'about' => array(
				'hero'         => 'hero',
				'history'      => 'timeline',
				'values'       => 'benefit_grid',
				'facilities'   => 'image_band',
				'committee'    => 'people_grid',
				'get_involved' => 'benefit_grid',
				'cta'          => 'band',
			),
			'membership' => array(
				'hero'   => 'hero',
				'tiers'  => 'tier_grid',
				'why'    => 'benefit_grid',
				'detail' => 'list_split',
				'steps'  => 'step_grid',
				'faq'    => 'faq',
				'cta'    => 'band',
			),
			'contact' => array(
				'hero'      => 'hero',
				'form'      => 'contact_form',
				'directory' => 'people_grid',
				'social'    => 'closing_band',
			),
			'login' => array(
				'form' => 'auth',
			),
			'news' => array(
				'head'     => 'news_head',
				'featured' => 'news_featured',
				'posts'    => 'news_grid',
			),
			'sports' => array(
				'hero'      => 'hero_filter',
				'directory' => 'stat_card_grid',
				'cta'       => 'band',
			),
			'teams' => array(
				'hero'      => 'hero_filter',
				'directory' => 'stat_card_grid',
				'cta'       => 'band',
			),
			'events' => array(
				'hero'     => 'hero_filter',
				'upcoming' => 'event_grid',
				'past'     => 'event_archive',
				'cta'      => 'band',
			),
			'calendar' => array(
				'hero'     => 'hero_filter',
				'booking'  => 'shortcode_block',
				'schedule' => 'calendar_months',
				'cta'      => 'band',
			),
			'booking' => array(
				'hero'      => 'hero',
				'services'  => 'shortcode_block',
				'locations' => 'shortcode_block',
				'agents'    => 'shortcode_block',
			),
		);

		$out = array();
		foreach ( $pages as $page => $sections ) {
			$position = 0;
			foreach ( $sections as $section => $type ) {
				$position         += 10;
				$out[ $page . '/' . $section ] = array(
					'type'     => $type,
					'position' => $position,
				);
			}
		}
		return $out;
	}

	/** The block type an address renders as, or '' when the address is unknown. */
	public static function type( string $address ): string {
		return (string) ( self::map()[ $address ]['type'] ?? '' );
	}
}
```

Two things to notice while checking this against `Page_Renderer`:

- Home's `quick_tiles` is not a section of its own — its tiles render inside `home_hero`. It keeps an address because the content editor and the link catalogue both offer it, and it maps to the same type. Plan 3 folds the two into one block; here it just has to map somewhere real.
- Home's `social` and `info` are also one rendered block, not two: `Page_Renderer::home()` closes the page with a single `closing_band` carrying the social links and the find-us columns, each half controlled by its own toggle. Both addresses map to `closing_band`, and they come **after** `sponsors` — which is not the order `Setup_Sections` happens to list them in. Read the render order from the page method, never from the visibility table.
- `info_panel` therefore has no address of its own. It is used today only by the per-sport and per-team detail pages, which are generated from a collection item and stay outside the block model (see the spec's "Out of scope"). It stays in the registry as a type an owner can add to a page from plan 4 onward; the test in Task 3 checks every address has a type, not that every type has an address.

- [ ] **Step 4: Require it and run the tests**

In `includes/bootstrap.php`, after the block-types require:

```php
require_once __DIR__ . '/blocks/class-block-addresses.php';
```

Run: `composer test -- --filter BlockTypesTest`
Expected: PASS, 8 tests. If `test_every_catalogue_address_has_a_block` fails, the catalogue has a section this map missed — add it rather than weakening the test.

- [ ] **Step 5: Lint and commit**

```bash
composer lint
git add includes/blocks/class-block-addresses.php includes/bootstrap.php tests/php/BlockTypesTest.php
git commit -m "feat: map today's content addresses onto block types"
```

---

### Task 4: The block library store

**Files:**
- Create: `includes/blocks/class-block-library.php`
- Create: `tests/php/BlockLibraryTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Storage`, `Blueworx_Clubhouse_Block_Types::rank()`.
- Produces `Blueworx_Clubhouse_Block_Library`:
  - `__construct( Blueworx_Clubhouse_Storage $storage )`
  - `add( string $type, string $name, string $defaults_key = '', ?int $position = null ): string` — returns the new id.
  - `get( string $id ): ?array` — `array{id,type,name,defaults_key,position,content,settings}`.
  - `all(): array<string,array>` — id => block, insertion order.
  - `of_type( string $type ): array<string,array>`
  - `set_content( string $id, array $content ): void`
  - `rename( string $id, string $name ): void`
  - `duplicate( string $id, string $name ): string` — returns the new id.
  - `delete( string $id ): void`
  - `has( string $id ): bool`

Ids are slugs from the name, made unique with a numeric suffix. Content is stored raw — sanitising belongs to the admin controller in plan 4, exactly as it does for `Content_Store` today.

- [ ] **Step 1: Write the failing test**

Create `tests/php/BlockLibraryTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class BlockLibraryTest extends TestCase {

	private function lib(): Blueworx_Clubhouse_Block_Library {
		return new Blueworx_Clubhouse_Block_Library( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_a_new_block_gets_a_slug_id_and_its_types_rank(): void {
		$lib = $this->lib();
		$id  = $lib->add( 'hero', 'Home hero' );
		$this->assertSame( 'home-hero', $id );

		$block = $lib->get( $id );
		$this->assertSame( 'hero', $block['type'] );
		$this->assertSame( 'Home hero', $block['name'] );
		$this->assertSame( Blueworx_Clubhouse_Block_Types::rank( 'hero' ), $block['position'] );
		$this->assertSame( array(), $block['content'] );
		$this->assertSame( '', $block['defaults_key'] );
	}

	public function test_a_given_position_and_defaults_key_are_kept(): void {
		$lib   = $this->lib();
		$id    = $lib->add( 'hero', 'About hero', 'about/hero', 10 );
		$block = $lib->get( $id );
		$this->assertSame( 'about/hero', $block['defaults_key'] );
		$this->assertSame( 10, $block['position'] );
	}

	public function test_two_blocks_of_the_same_name_get_distinct_ids(): void {
		$lib = $this->lib();
		$this->assertSame( 'hero', $lib->add( 'hero', 'Hero' ) );
		$this->assertSame( 'hero-2', $lib->add( 'hero', 'Hero' ) );
		$this->assertSame( 'hero-3', $lib->add( 'hero', 'Hero' ) );
	}

	public function test_content_round_trips(): void {
		$lib = $this->lib();
		$id  = $lib->add( 'hero', 'Home hero' );
		$lib->set_content( $id, array( 'title_lead' => 'Welcome', 'items' => array( array( 'text' => 'One' ) ) ) );
		$block = $lib->get( $id );
		$this->assertSame( 'Welcome', $block['content']['title_lead'] );
		$this->assertSame( 'One', $block['content']['items'][0]['text'] );
	}

	public function test_renaming_keeps_the_id_so_nothing_breaks(): void {
		$lib = $this->lib();
		$id  = $lib->add( 'band', 'Join CTA' );
		$lib->rename( $id, 'Come and play' );
		$this->assertSame( 'join-cta', $id );
		$this->assertSame( 'Come and play', $lib->get( $id )['name'] );
	}

	public function test_duplicating_copies_the_content_but_not_the_id(): void {
		$lib = $this->lib();
		$id  = $lib->add( 'band', 'Join CTA', 'about/cta', 70 );
		$lib->set_content( $id, array( 'heading' => 'Come down' ) );

		$copy = $lib->duplicate( $id, 'Join CTA for Contact' );
		$this->assertNotSame( $id, $copy );

		$new = $lib->get( $copy );
		$this->assertSame( 'Come down', $new['content']['heading'] );
		$this->assertSame( 'band', $new['type'] );
		$this->assertSame( 'about/cta', $new['defaults_key'] );
		$this->assertSame( 70, $new['position'] );
		$this->assertSame( 'Join CTA for Contact', $new['name'] );
	}

	public function test_deleting_removes_it_and_leaves_the_rest(): void {
		$lib = $this->lib();
		$a   = $lib->add( 'hero', 'A' );
		$b   = $lib->add( 'hero', 'B' );
		$lib->delete( $a );
		$this->assertFalse( $lib->has( $a ) );
		$this->assertTrue( $lib->has( $b ) );
		$this->assertNull( $lib->get( $a ) );
	}

	public function test_of_type_selects_only_that_type(): void {
		$lib = $this->lib();
		$lib->add( 'hero', 'A' );
		$lib->add( 'band', 'B' );
		$lib->add( 'hero', 'C' );
		$this->assertSame( array( 'a', 'c' ), array_keys( $lib->of_type( 'hero' ) ) );
	}

	public function test_unknown_ids_are_reported_honestly(): void {
		$lib = $this->lib();
		$this->assertFalse( $lib->has( 'nope' ) );
		$this->assertNull( $lib->get( 'nope' ) );
		$lib->delete( 'nope' );
		$lib->rename( 'nope', 'x' );
		$lib->set_content( 'nope', array( 'a' => 'b' ) );
		$this->assertSame( array(), $lib->all() );
	}

	public function test_a_block_survives_a_new_store_over_the_same_storage(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$first   = new Blueworx_Clubhouse_Block_Library( $storage );
		$id      = $first->add( 'hero', 'Home hero' );

		$second = new Blueworx_Clubhouse_Block_Library( $storage );
		$this->assertSame( 'Home hero', $second->get( $id )['name'] );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `composer test -- --filter BlockLibraryTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Block_Library" not found`.

- [ ] **Step 3: Write the store**

Create `includes/blocks/class-block-library.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The club's blocks: named instances of a block type, each holding its own
 * content. One block used on three pages is one entry here, which is what makes
 * "edit it once" true.
 *
 * Stored as a single entry, like Content_Store — one autoloaded option rather
 * than one per block, because every page render reads the whole library.
 *
 * Content is stored as given. Sanitising is the admin controller's job, the
 * same division Content_Store keeps.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Library {

	private const KEY = 'blocks';

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/** @return array<string,array{id:string,type:string,name:string,defaults_key:string,position:int,content:array,settings:array}> */
	public function all(): array {
		$blocks = $this->storage->get( self::KEY, array() );
		return is_array( $blocks ) ? $blocks : array();
	}

	/** @param array<string,array> $blocks */
	private function save( array $blocks ): void {
		$this->storage->set( self::KEY, $blocks );
	}

	public function has( string $id ): bool {
		return isset( $this->all()[ $id ] );
	}

	/** @return array{id:string,type:string,name:string,defaults_key:string,position:int,content:array,settings:array}|null */
	public function get( string $id ): ?array {
		return $this->all()[ $id ] ?? null;
	}

	/** @return array<string,array> */
	public function of_type( string $type ): array {
		return array_filter(
			$this->all(),
			static fn( array $block ): bool => $block['type'] === $type
		);
	}

	/**
	 * A url-safe id from the block's name, suffixed until it is unique. The id is
	 * fixed at creation and never follows a rename — pages refer to blocks by id,
	 * and a renamed block must not fall off the pages using it.
	 */
	private function unique_id( string $name, array $blocks ): string {
		$base = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ) ?? '', '-' );
		if ( '' === $base ) {
			$base = 'block';
		}
		$id = $base;
		$n  = 1;
		while ( isset( $blocks[ $id ] ) ) {
			++$n;
			$id = $base . '-' . $n;
		}
		return $id;
	}

	/**
	 * @param string   $type         A Block_Types key.
	 * @param string   $name         Owner-facing name.
	 * @param string   $defaults_key The "page/section" address whose default copy
	 *                               this block inherits; '' for the type's own.
	 * @param int|null $position     Where it sits on a page; the type's rank when null.
	 * @return string The new block's id.
	 */
	public function add( string $type, string $name, string $defaults_key = '', ?int $position = null ): string {
		$blocks = $this->all();
		$id     = $this->unique_id( $name, $blocks );

		$blocks[ $id ] = array(
			'id'           => $id,
			'type'         => $type,
			'name'         => $name,
			'defaults_key' => $defaults_key,
			'position'     => $position ?? Blueworx_Clubhouse_Block_Types::rank( $type ),
			'content'      => array(),
			'settings'     => array(),
		);
		$this->save( $blocks );
		return $id;
	}

	public function set_content( string $id, array $content ): void {
		$blocks = $this->all();
		if ( ! isset( $blocks[ $id ] ) ) {
			return;
		}
		$blocks[ $id ]['content'] = $content;
		$this->save( $blocks );
	}

	public function set_settings( string $id, array $settings ): void {
		$blocks = $this->all();
		if ( ! isset( $blocks[ $id ] ) ) {
			return;
		}
		$blocks[ $id ]['settings'] = $settings;
		$this->save( $blocks );
	}

	public function rename( string $id, string $name ): void {
		$blocks = $this->all();
		if ( ! isset( $blocks[ $id ] ) ) {
			return;
		}
		$blocks[ $id ]['name'] = $name;
		$this->save( $blocks );
	}

	/** @return string The copy's id, or '' when the original is gone. */
	public function duplicate( string $id, string $name ): string {
		$blocks = $this->all();
		if ( ! isset( $blocks[ $id ] ) ) {
			return '';
		}
		$copy_id = $this->unique_id( $name, $blocks );

		$copy               = $blocks[ $id ];
		$copy['id']         = $copy_id;
		$copy['name']       = $name;
		$blocks[ $copy_id ] = $copy;

		$this->save( $blocks );
		return $copy_id;
	}

	public function delete( string $id ): void {
		$blocks = $this->all();
		if ( ! isset( $blocks[ $id ] ) ) {
			return;
		}
		unset( $blocks[ $id ] );
		$this->save( $blocks );
	}
}
```

- [ ] **Step 4: Require it and run the tests**

In `includes/bootstrap.php`, after the block-addresses require:

```php
require_once __DIR__ . '/blocks/class-block-library.php';
```

Run: `composer test -- --filter BlockLibraryTest`
Expected: PASS, 10 tests.

- [ ] **Step 5: Lint and commit**

```bash
composer lint
git add includes/blocks/class-block-library.php includes/bootstrap.php tests/php/BlockLibraryTest.php
git commit -m "feat: add the block library store"
```

---

### Task 5: The page composition store

**Files:**
- Create: `includes/blocks/class-page-composition.php`
- Create: `tests/php/PageCompositionTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Storage`.
- Produces `Blueworx_Clubhouse_Page_Composition`:
  - `__construct( Blueworx_Clubhouse_Storage $storage )`
  - `blocks( string $page ): array<int,string>` — block ids, in stored order.
  - `set_blocks( string $page, array $ids ): void`
  - `add( string $page, string $id ): void` — appends; a repeat is a no-op.
  - `remove( string $page, string $id ): void`
  - `uses( string $id ): array<int,string>` — pages using this block, for the library's "used on" line and the delete warning.
  - `is_enabled( string $page ): bool` — true unless explicitly disabled.
  - `set_enabled( string $page, bool $enabled ): void`
  - `is_configured(): bool` — false until something has been stored, so the seeder and migration know whether this site has been set up.

- [ ] **Step 1: Write the failing test**

Create `tests/php/PageCompositionTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class PageCompositionTest extends TestCase {

	private function comp(): Blueworx_Clubhouse_Page_Composition {
		return new Blueworx_Clubhouse_Page_Composition( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_a_fresh_site_has_no_composition_and_no_blocks(): void {
		$comp = $this->comp();
		$this->assertFalse( $comp->is_configured() );
		$this->assertSame( array(), $comp->blocks( 'home' ) );
	}

	public function test_pages_are_enabled_until_switched_off(): void {
		$comp = $this->comp();
		$this->assertTrue( $comp->is_enabled( 'home' ) );
		$comp->set_enabled( 'home', false );
		$this->assertFalse( $comp->is_enabled( 'home' ) );
		$comp->set_enabled( 'home', true );
		$this->assertTrue( $comp->is_enabled( 'home' ) );
	}

	public function test_blocks_round_trip_in_the_order_given(): void {
		$comp = $this->comp();
		$comp->set_blocks( 'home', array( 'home-hero', 'home-ticker' ) );
		$this->assertSame( array( 'home-hero', 'home-ticker' ), $comp->blocks( 'home' ) );
		$this->assertTrue( $comp->is_configured() );
	}

	public function test_adding_appends_and_never_duplicates(): void {
		$comp = $this->comp();
		$comp->add( 'home', 'home-hero' );
		$comp->add( 'home', 'home-ticker' );
		$comp->add( 'home', 'home-hero' );
		$this->assertSame( array( 'home-hero', 'home-ticker' ), $comp->blocks( 'home' ) );
	}

	public function test_removing_leaves_a_gapless_list(): void {
		$comp = $this->comp();
		$comp->set_blocks( 'home', array( 'a', 'b', 'c' ) );
		$comp->remove( 'home', 'b' );
		$this->assertSame( array( 'a', 'c' ), $comp->blocks( 'home' ) );
	}

	public function test_removing_from_one_page_leaves_the_other(): void {
		$comp = $this->comp();
		$comp->set_blocks( 'home', array( 'shared-cta' ) );
		$comp->set_blocks( 'about', array( 'shared-cta' ) );
		$comp->remove( 'home', 'shared-cta' );
		$this->assertSame( array(), $comp->blocks( 'home' ) );
		$this->assertSame( array( 'shared-cta' ), $comp->blocks( 'about' ) );
	}

	public function test_uses_names_every_page_a_block_is_on(): void {
		$comp = $this->comp();
		$comp->set_blocks( 'home', array( 'shared-cta', 'home-hero' ) );
		$comp->set_blocks( 'about', array( 'shared-cta' ) );
		$comp->set_blocks( 'contact', array( 'contact-form' ) );
		$this->assertSame( array( 'home', 'about' ), $comp->uses( 'shared-cta' ) );
		$this->assertSame( array(), $comp->uses( 'nobody-uses-me' ) );
	}

	public function test_it_survives_a_new_store_over_the_same_storage(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$first   = new Blueworx_Clubhouse_Page_Composition( $storage );
		$first->set_blocks( 'home', array( 'home-hero' ) );
		$first->set_enabled( 'about', false );

		$second = new Blueworx_Clubhouse_Page_Composition( $storage );
		$this->assertSame( array( 'home-hero' ), $second->blocks( 'home' ) );
		$this->assertFalse( $second->is_enabled( 'about' ) );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `composer test -- --filter PageCompositionTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Page_Composition" not found`.

- [ ] **Step 3: Write the store**

Create `includes/blocks/class-page-composition.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What each page is made of: whether it is on the site, and which blocks it
 * shows. The order stored here is only a tie-break — a page renders its blocks
 * by each block's own position (see Block_Library), because the editor does not
 * offer moving one.
 *
 * Persisted as one entry, mirroring Visibility, which it replaces for sections.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Composition {

	private const KEY = 'page_composition';

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/** @return array<string,array{enabled?:bool,blocks?:array<int,string>}> */
	private function state(): array {
		$state = $this->storage->get( self::KEY, array() );
		return is_array( $state ) ? $state : array();
	}

	private function save( array $state ): void {
		$this->storage->set( self::KEY, $state );
	}

	/** False until this site has stored a composition — the seeder's cue. */
	public function is_configured(): bool {
		return array() !== $this->state();
	}

	/** @return array<int,string> */
	public function blocks( string $page ): array {
		$blocks = $this->state()[ $page ]['blocks'] ?? array();
		return is_array( $blocks ) ? array_values( $blocks ) : array();
	}

	/** @param array<int,string> $ids */
	public function set_blocks( string $page, array $ids ): void {
		$state                     = $this->state();
		$state[ $page ]['blocks']  = array_values( array_unique( $ids ) );
		$this->save( $state );
	}

	public function add( string $page, string $id ): void {
		$blocks = $this->blocks( $page );
		if ( in_array( $id, $blocks, true ) ) {
			return;
		}
		$blocks[] = $id;
		$this->set_blocks( $page, $blocks );
	}

	public function remove( string $page, string $id ): void {
		$this->set_blocks(
			$page,
			array_values( array_filter( $this->blocks( $page ), static fn( string $b ): bool => $b !== $id ) )
		);
	}

	/**
	 * Every page showing this block. The library's "used on" line and the delete
	 * warning both read this, so an owner is never surprised by a shared edit.
	 *
	 * @return array<int,string>
	 */
	public function uses( string $id ): array {
		$pages = array();
		foreach ( array_keys( $this->state() ) as $page ) {
			if ( in_array( $id, $this->blocks( (string) $page ), true ) ) {
				$pages[] = (string) $page;
			}
		}
		return $pages;
	}

	public function is_enabled( string $page ): bool {
		return (bool) ( $this->state()[ $page ]['enabled'] ?? true );
	}

	public function set_enabled( string $page, bool $enabled ): void {
		$state                     = $this->state();
		$state[ $page ]['enabled'] = $enabled;
		$this->save( $state );
	}
}
```

- [ ] **Step 4: Require it and run the tests**

In `includes/bootstrap.php`, after the block-library require:

```php
require_once __DIR__ . '/blocks/class-page-composition.php';
```

Run: `composer test -- --filter PageCompositionTest`
Expected: PASS, 8 tests.

- [ ] **Step 5: Lint and commit**

```bash
composer lint
git add includes/blocks/class-page-composition.php includes/bootstrap.php tests/php/PageCompositionTest.php
git commit -m "feat: add the page composition store"
```

---

### Task 6: Prove the spine can describe today's site

A store that round-trips is not yet proof the model fits. This task builds today's site inside the two stores in a test — no production code — and asserts the result is the site the plugin renders now. It is the design's first real load test, and it will be reused as the seeder's fixture in plan 3.

**Files:**
- Create: `tests/php/BlockSpineTest.php`

**Interfaces:**
- Consumes: everything built in tasks 1–5, plus `Blueworx_Clubhouse_Content_Catalogue::index()`.
- Produces: nothing. Test-only.

- [ ] **Step 1: Write the failing test**

Create `tests/php/BlockSpineTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

/**
 * The spine has to be able to describe the site the plugin ships today. This
 * builds it from the address map and checks what comes back out, so a model
 * that cannot hold the real site fails here rather than in plan 2's renderer.
 */
final class BlockSpineTest extends TestCase {

	/** @return array{0:Blueworx_Clubhouse_Block_Library,1:Blueworx_Clubhouse_Page_Composition} */
	private function build(): array {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$lib     = new Blueworx_Clubhouse_Block_Library( $storage );
		$comp    = new Blueworx_Clubhouse_Page_Composition( $storage );

		foreach ( Blueworx_Clubhouse_Block_Addresses::map() as $address => $entry ) {
			[ $page, $section ] = explode( '/', $address );
			$id                 = $lib->add(
				$entry['type'],
				ucfirst( $page ) . ' · ' . ucfirst( str_replace( '_', ' ', $section ) ),
				$address,
				$entry['position']
			);
			if ( 'global' !== $page ) {
				$comp->add( $page, $id );
			}
		}
		return array( $lib, $comp );
	}

	private function ordered( Blueworx_Clubhouse_Block_Library $lib, Blueworx_Clubhouse_Page_Composition $comp, string $page ): array {
		$blocks = array();
		foreach ( $comp->blocks( $page ) as $index => $id ) {
			$block             = $lib->get( $id );
			$blocks[] = array( $block['position'], $index, $block['defaults_key'] );
		}
		usort(
			$blocks,
			static fn( array $a, array $b ): int => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] )
		);
		return array_column( $blocks, 2 );
	}

	public function test_every_address_becomes_exactly_one_block(): void {
		[ $lib ] = $this->build();
		$this->assertCount( count( Blueworx_Clubhouse_Block_Addresses::map() ), $lib->all() );
	}

	/**
	 * About is the page that killed one-rank-per-type: values and get involved are
	 * both benefit grids, either side of facilities and committee. If the model
	 * ever loses per-block positions, this is the test that says so.
	 */
	public function test_about_keeps_its_running_order(): void {
		[ $lib, $comp ] = $this->build();
		$this->assertSame(
			array(
				'about/hero',
				'about/history',
				'about/values',
				'about/facilities',
				'about/committee',
				'about/get_involved',
				'about/cta',
			),
			$this->ordered( $lib, $comp, 'about' )
		);
	}

	public function test_home_keeps_its_running_order(): void {
		[ $lib, $comp ] = $this->build();
		$this->assertSame(
			array(
				'home/hero',
				'home/quick_tiles',
				'home/ticker',
				'home/sports',
				'home/clubhouse',
				'home/membership',
				'home/activity',
				'home/news',
				'home/sponsors',
				'home/social',
				'home/info',
			),
			$this->ordered( $lib, $comp, 'home' )
		);
	}

	public function test_a_block_can_be_shared_by_two_pages(): void {
		[ $lib, $comp ] = $this->build();
		$shared         = $lib->add( 'band', 'Join today', 'about/cta', 400 );
		$comp->add( 'home', $shared );
		$comp->add( 'contact', $shared );

		$this->assertSame( array( 'home', 'contact' ), $comp->uses( $shared ) );

		$lib->set_content( $shared, array( 'heading' => 'Come and play' ) );
		$this->assertSame( 'Come and play', $lib->get( $shared )['content']['heading'] );
	}

	public function test_taking_a_block_off_one_page_leaves_it_in_the_library(): void {
		[ $lib, $comp ] = $this->build();
		$id             = $comp->blocks( 'about' )[0];
		$comp->remove( 'about', $id );

		$this->assertNotContains( $id, $comp->blocks( 'about' ) );
		$this->assertTrue( $lib->has( $id ) );
	}

	public function test_the_header_and_footer_are_on_no_page(): void {
		[ $lib, $comp ] = $this->build();
		foreach ( $lib->all() as $id => $block ) {
			if ( in_array( $block['type'], array( 'header', 'footer' ), true ) ) {
				$this->assertSame( array(), $comp->uses( $id ), $id );
			}
		}
	}
}
```

- [ ] **Step 2: Run it and see what the model gets wrong**

Run: `composer test -- --filter BlockSpineTest`
Expected: the ordering tests fail if `Block_Addresses` has a position wrong. This is the point of the task — a failure here means Task 3's table disagrees with `Page_Renderer`, not that the store is broken.

- [ ] **Step 3: Fix the address map, not the test**

If `test_home_keeps_its_running_order` or `test_about_keeps_its_running_order` fails, open `includes/render/class-page-renderer.php`, read the render order out of the page method itself, and correct the order of entries in `Blueworx_Clubhouse_Block_Addresses::map()`. Do not reorder the expectations in the test to match the map — the test states what the site does today, which is the thing being preserved.

- [ ] **Step 4: Run the whole suite**

Run: `composer test`
Expected: PASS, all tests — the existing suite plus roughly 32 new ones. Nothing else should have changed, because no existing file has been modified beyond `includes/bootstrap.php`.

- [ ] **Step 5: Lint and commit**

```bash
composer lint
git add tests/php/BlockSpineTest.php includes/blocks/class-block-addresses.php
git commit -m "test: prove the block model can describe today's site"
```

---

### Task 7: Version, changelog and a clean finish

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (header `Version:` on line 6, and the version constant below it)
- Modify: `package.json` (`version`)
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing.

The CI guardrail requires the version to be bumped above the base branch and the changelog updated in the same pull request. Baseline is 0.63.1; this is new capability, so it is a minor bump to **0.64.0**.

- [ ] **Step 1: Bump the plugin version**

In `blueworx-labs-clubhouse.php`, change the `Version:` header from `0.63.1` to `0.64.0`, and the version constant defined below it to match. Both must agree — a mismatch busts the theme cache signature for no reason.

- [ ] **Step 2: Bump package.json**

Set `"version": "0.64.0"`.

- [ ] **Step 3: Add the changelog entry**

At the top of `CHANGELOG.md`, above the existing entries (the file runs newest first), following the format already in use:

```markdown
## 0.64.0

- Groundwork for the new page editor: blocks can now be stored as a reusable library, and each page can record which of them it shows. Nothing changes on the site yet.
```

- [ ] **Step 4: Run everything**

Run: `composer test`
Expected: PASS, whole suite.

Run: `composer lint`
Expected: no errors.

Run: `npx playwright test`
Expected: PASS — the front end is untouched, so any failure here is a genuine regression and must be fixed before committing.

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: release 0.64.0"
```

---

## What this plan deliberately leaves undone

Named here so a reviewer does not read them as omissions:

- **Nothing renders from blocks yet.** The eleven page methods are untouched and still drive the site. Plan 2 extracts the defaults and the renderers and switches the front end over behind a parity check.
- **Fields are not in the registry.** They stay in `Content_Catalogue` until plan 3 needs them for the admin screens.
- **No seeding, no migration.** An upgraded site has empty block stores, which nothing reads. Plan 3 fills them.
- **No admin screens, and the Visibility tab is still there.** Plan 4.
- **Import, guide and link catalogue still address page-and-section.** Plan 5.

## The plan sequence

1. **This plan** — type registry, address map, block library, page composition. No behaviour change.
2. **Defaults and the render loop** — extract the ~55 default sets and the per-type renderers out of `Page_Renderer`, add the composer, and switch the front end over behind a byte-for-byte parity check against the current output. Delete the page methods.
3. **Seeding and migration** — `Block_Seeder` for fresh installs, the one-off migration for existing ones, and the existing Content screen repointed at blocks so the site stays editable throughout.
4. **The two admin screens** — Content → Pages and Content → Blocks; retire the old Content screen and the Setup Visibility tab.
5. **Downstream and cleanup** — import, guide, link catalogue; remove `Content_Store`'s page content, `Setup_Sections`, and `Visibility`'s section methods.
