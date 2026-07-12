# Engine Core & Content Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the tested, dependency-injected primitives the Sports Club Template engine is built on: a base registry, a storage abstraction, singular-content storage, and page/section visibility.

**Architecture:** Pure-logic PHP classes that take a `Storage` interface by constructor injection, so all logic is unit-testable with a plain in-memory fake and no WordPress runtime. The one WordPress-touching class (`Options_Storage`) is a thin adapter over `get_option`/`update_option`, exercised later via Playwright rather than unit tests. Runtime loading uses explicit `require_once` (no autoloader, no runtime Composer dependency); Composer is a dev-only tool that provides PHPUnit.

**Tech Stack:** PHP 8.2+, PHPUnit 11 (dev only, via Composer), WordPress options API.

## Global Constraints

- Plugin main file: `blueworx-labs-clubhouse.php`; plugin slug: `blueworx-labs-clubhouse`.
- Class prefix `Blueworx_Clubhouse_`; files named `class-*.php` under `includes/` (house style).
- Every shipped PHP file starts with `<?php` then `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- PHP 8.2+ typed code; `declare(strict_types=1);` at the top of every shipped class file (after the `<?php` tag, before the ABSPATH guard).
- Runtime must not depend on Composer/`vendor/`. `vendor/` is dev-only and git-ignored.
- This plan's PR bumps the plugin version **0.1.1 → 0.2.0** (minor: new functionality) in the plugin header, the `BLUEWORX_LABS_CLUBHOUSE_VERSION` constant, and `package.json` (all three must match — CI `check-plugin-version-sync` enforces header vs `package.json`), and adds a matching `CHANGELOG.md` entry (CI `check-changelog` requires it).
- Work on the branch `engine-core-content-foundation` (created off `main` in Task 1, Step 0), separate from `template-engine-spec`. Never commit engine code to `main`.
- Default visibility for pages and sections is **visible** (owners hide by opting out).

---

### Task 1: PHP test harness + loader scaffold

Establishes Composer (dev-only PHPUnit), the runtime class loader (`includes/bootstrap.php` — explicit requires), the PHPUnit bootstrap (defines a dummy `ABSPATH` so guarded files load), and a trivial passing test that proves the harness runs.

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `includes/bootstrap.php`
- Create: `tests/php/bootstrap.php`
- Create: `tests/php/HarnessTest.php`
- Modify: `blueworx-labs-clubhouse.php` (require the includes bootstrap)
- Modify: `.gitignore` (ignore `/vendor/`)

**Interfaces:**
- Consumes: nothing.
- Produces: `includes/bootstrap.php` (runtime loader — later tasks append `require_once` lines to it, in dependency order); `tests/php/bootstrap.php` defines `ABSPATH` and loads runtime classes + test fakes for PHPUnit; `composer test` runs the suite.

- [ ] **Step 0: Create the branch off `main`**

Run: `git checkout main && git pull && git checkout -b engine-core-content-foundation`
Expected: switched to a new branch `engine-core-content-foundation`.

- [ ] **Step 1: Create `composer.json`**

```json
{
  "name": "blueworx/labs-clubhouse",
  "description": "Blueworx Labs Clubhouse — Sports Club Template engine.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=8.2"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.5"
  },
  "scripts": {
    "test": "phpunit"
  }
}
```

- [ ] **Step 2: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/php/bootstrap.php"
    colors="true"
    failOnWarning="true"
    failOnRisky="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/php</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create `includes/bootstrap.php` (runtime loader — starts empty of classes)**

```php
<?php
/**
 * Runtime class loader. Requires engine classes in dependency order.
 * No hooks or instantiation here — loading only, so it is safe to include
 * from both the plugin runtime and the PHPUnit bootstrap.
 *
 * @package BlueworxLabsClubhouse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Core primitives (added by later tasks), e.g.:
// require_once __DIR__ . '/core/class-registry.php';
```

- [ ] **Step 4: Create `tests/php/bootstrap.php`**

```php
<?php
/**
 * PHPUnit bootstrap. Loads Composer's dev autoloader (PHPUnit), defines a
 * dummy ABSPATH so guarded plugin files load, then pulls in the runtime
 * classes and the test fakes.
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

require dirname( __DIR__, 2 ) . '/includes/bootstrap.php';

// Test doubles.
foreach ( glob( __DIR__ . '/fakes/*.php' ) as $fake ) {
	require_once $fake;
}
```

- [ ] **Step 5: Create the fakes directory placeholder + a trivial harness test `tests/php/HarnessTest.php`**

```php
<?php

use PHPUnit\Framework\TestCase;

final class HarnessTest extends TestCase {
	public function test_harness_runs(): void {
		$this->assertTrue( defined( 'ABSPATH' ) );
	}
}
```

(Also create an empty `tests/php/fakes/` directory — add a `.gitkeep` file so `glob` and git both see it: create `tests/php/fakes/.gitkeep` with no content.)

- [ ] **Step 6: Modify `blueworx-labs-clubhouse.php` — require the loader**

Add this line immediately after the `BLUEWORX_LABS_CLUBHOUSE_URL` define and before the `blueworx_labs_clubhouse_init` function:

```php
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/bootstrap.php';
```

- [ ] **Step 7: Modify `.gitignore` — ignore Composer artifacts**

Add these lines to the existing `.gitignore`:

```
/vendor/
composer.lock
```

- [ ] **Step 8: Install dev dependencies and run the suite**

Run: `composer install && composer test`
Expected: Composer installs PHPUnit into `vendor/`; PHPUnit runs `HarnessTest` — `OK (1 test, 1 assertion)`.

- [ ] **Step 9: Commit**

```bash
git add composer.json phpunit.xml.dist includes/bootstrap.php tests/php blueworx-labs-clubhouse.php .gitignore
git commit -m "test: add PHP unit test harness and runtime loader scaffold"
```

---

### Task 2: Base Registry

An ordered, keyed registry used by pages, sections, collections, and features. Pure logic, no WordPress.

**Files:**
- Create: `includes/core/class-registry.php`
- Test: `tests/php/RegistryTest.php`
- Modify: `includes/bootstrap.php` (require the new class)

**Interfaces:**
- Consumes: nothing.
- Produces: `class Blueworx_Clubhouse_Registry` with `register(string $key, mixed $item): void`, `has(string $key): bool`, `get(string $key): mixed` (null if absent), `all(): array` (key→item, in registration order), `keys(): array` (registration order).

- [ ] **Step 1: Write the failing test `tests/php/RegistryTest.php`**

```php
<?php

use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase {
	public function test_register_and_get(): void {
		$r = new Blueworx_Clubhouse_Registry();
		$r->register( 'home', 'HOME_ITEM' );
		$this->assertSame( 'HOME_ITEM', $r->get( 'home' ) );
	}

	public function test_get_missing_returns_null(): void {
		$r = new Blueworx_Clubhouse_Registry();
		$this->assertNull( $r->get( 'nope' ) );
	}

	public function test_has(): void {
		$r = new Blueworx_Clubhouse_Registry();
		$r->register( 'a', 1 );
		$this->assertTrue( $r->has( 'a' ) );
		$this->assertFalse( $r->has( 'b' ) );
	}

	public function test_keys_preserve_registration_order(): void {
		$r = new Blueworx_Clubhouse_Registry();
		$r->register( 'first', 1 );
		$r->register( 'second', 2 );
		$r->register( 'third', 3 );
		$this->assertSame( array( 'first', 'second', 'third' ), $r->keys() );
	}

	public function test_register_overwrites_same_key_without_reordering(): void {
		$r = new Blueworx_Clubhouse_Registry();
		$r->register( 'a', 1 );
		$r->register( 'b', 2 );
		$r->register( 'a', 99 );
		$this->assertSame( 99, $r->get( 'a' ) );
		$this->assertSame( array( 'a', 'b' ), $r->keys() );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter RegistryTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Registry" not found`.

- [ ] **Step 3: Write the implementation `includes/core/class-registry.php`**

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ordered, keyed registry of items (pages, sections, collections, features).
 *
 * @package BlueworxLabsClubhouse
 */
class Blueworx_Clubhouse_Registry {

	/** @var array<string, mixed> Items keyed by slug, in registration order. */
	private array $items = array();

	public function register( string $key, mixed $item ): void {
		$this->items[ $key ] = $item;
	}

	public function has( string $key ): bool {
		return array_key_exists( $key, $this->items );
	}

	public function get( string $key ): mixed {
		return $this->items[ $key ] ?? null;
	}

	/** @return array<string, mixed> */
	public function all(): array {
		return $this->items;
	}

	/** @return list<string> */
	public function keys(): array {
		return array_keys( $this->items );
	}
}
```

- [ ] **Step 4: Register the class in the runtime loader**

In `includes/bootstrap.php`, add under the "Core primitives" comment:

```php
require_once __DIR__ . '/core/class-registry.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter RegistryTest`
Expected: PASS — `OK (5 tests, ...)`.

- [ ] **Step 6: Commit**

```bash
git add includes/core/class-registry.php includes/bootstrap.php tests/php/RegistryTest.php
git commit -m "feat: add base registry"
```

---

### Task 3: Storage interface, options adapter, and in-memory fake

Defines the `Storage` contract, its WordPress options adapter (thin glue), and the in-memory fake used by every later unit test.

**Files:**
- Create: `includes/core/interface-storage.php`
- Create: `includes/core/class-options-storage.php`
- Create: `tests/php/fakes/class-fake-storage.php`
- Test: `tests/php/FakeStorageTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `interface Blueworx_Clubhouse_Storage` with `get(string $key, mixed $default = null): mixed`, `set(string $key, mixed $value): void`, `delete(string $key): void`.
  - `class Blueworx_Clubhouse_Options_Storage implements Blueworx_Clubhouse_Storage` — constructor `__construct(string $prefix = 'clubhouse_')`; stores each key as an autoloaded WordPress option named `{$prefix}{$key}`.
  - `class Blueworx_Clubhouse_Fake_Storage implements Blueworx_Clubhouse_Storage` — array-backed test double (used by Tasks 4 and 5).

- [ ] **Step 1: Write the failing test `tests/php/FakeStorageTest.php`**

```php
<?php

use PHPUnit\Framework\TestCase;

final class FakeStorageTest extends TestCase {
	public function test_get_returns_default_when_missing(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		$this->assertSame( 'fallback', $s->get( 'x', 'fallback' ) );
	}

	public function test_set_then_get_roundtrips(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		$s->set( 'x', array( 'a' => 1 ) );
		$this->assertSame( array( 'a' => 1 ), $s->get( 'x' ) );
	}

	public function test_delete_removes_value(): void {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		$s->set( 'x', 1 );
		$s->delete( 'x' );
		$this->assertNull( $s->get( 'x' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter FakeStorageTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Fake_Storage" not found`.

- [ ] **Step 3: Create the interface `includes/core/interface-storage.php`**

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Key/value storage contract. Production uses WordPress options; tests use an
 * in-memory fake. Keeps engine logic free of the WordPress runtime.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Storage {

	public function get( string $key, mixed $default = null ): mixed;

	public function set( string $key, mixed $value ): void;

	public function delete( string $key ): void;
}
```

- [ ] **Step 4: Create the options adapter `includes/core/class-options-storage.php`**

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress options-backed storage. Every value is stored as an autoloaded
 * option so reads add no extra queries on a normal page load.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Options_Storage implements Blueworx_Clubhouse_Storage {

	private string $prefix;

	public function __construct( string $prefix = 'clubhouse_' ) {
		$this->prefix = $prefix;
	}

	public function get( string $key, mixed $default = null ): mixed {
		return get_option( $this->prefix . $key, $default );
	}

	public function set( string $key, mixed $value ): void {
		update_option( $this->prefix . $key, $value, true );
	}

	public function delete( string $key ): void {
		delete_option( $this->prefix . $key );
	}
}
```

- [ ] **Step 5: Create the fake `tests/php/fakes/class-fake-storage.php`**

```php
<?php
declare(strict_types=1);

/**
 * In-memory Storage double for unit tests.
 */
final class Blueworx_Clubhouse_Fake_Storage implements Blueworx_Clubhouse_Storage {

	/** @var array<string, mixed> */
	private array $data = array();

	public function get( string $key, mixed $default = null ): mixed {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	public function set( string $key, mixed $value ): void {
		$this->data[ $key ] = $value;
	}

	public function delete( string $key ): void {
		unset( $this->data[ $key ] );
	}
}
```

- [ ] **Step 6: Register the shipped classes in the runtime loader**

In `includes/bootstrap.php`, add (interface first, then adapter):

```php
require_once __DIR__ . '/core/interface-storage.php';
require_once __DIR__ . '/core/class-options-storage.php';
```

- [ ] **Step 7: Run test to verify it passes**

Run: `composer test -- --filter FakeStorageTest`
Expected: PASS — `OK (3 tests, 3 assertions)`.

- [ ] **Step 8: Commit**

```bash
git add includes/core/interface-storage.php includes/core/class-options-storage.php tests/php/fakes/class-fake-storage.php tests/php/FakeStorageTest.php includes/bootstrap.php
git commit -m "feat: add storage interface, options adapter, and test fake"
```

---

### Task 4: Content Store (singular section content)

Reads/writes singular content for the one-of-a-kind template pages, keyed by page → section → field, persisted as one storage entry per page.

**Files:**
- Create: `includes/content/class-content-store.php`
- Test: `tests/php/ContentStoreTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Storage`, `Blueworx_Clubhouse_Fake_Storage`.
- Produces: `class Blueworx_Clubhouse_Content_Store` — `__construct(Blueworx_Clubhouse_Storage $storage)`; `get_section(string $page, string $section): array`; `get(string $page, string $section, string $field, mixed $default = null): mixed`; `set(string $page, string $section, string $field, mixed $value): void`. Storage key per page is `content_{page}`.

- [ ] **Step 1: Write the failing test `tests/php/ContentStoreTest.php`**

```php
<?php

use PHPUnit\Framework\TestCase;

final class ContentStoreTest extends TestCase {
	private function store(): Blueworx_Clubhouse_Content_Store {
		return new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_get_missing_field_returns_default(): void {
		$store = $this->store();
		$this->assertSame( 'D', $store->get( 'home', 'hero', 'heading', 'D' ) );
	}

	public function test_set_then_get_roundtrips(): void {
		$store = $this->store();
		$store->set( 'home', 'hero', 'heading', 'Welcome' );
		$this->assertSame( 'Welcome', $store->get( 'home', 'hero', 'heading' ) );
	}

	public function test_get_section_returns_all_fields(): void {
		$store = $this->store();
		$store->set( 'home', 'hero', 'heading', 'Welcome' );
		$store->set( 'home', 'hero', 'body', 'Hi' );
		$this->assertSame(
			array(
				'heading' => 'Welcome',
				'body'    => 'Hi',
			),
			$store->get_section( 'home', 'hero' )
		);
	}

	public function test_get_section_missing_returns_empty_array(): void {
		$this->assertSame( array(), $this->store()->get_section( 'home', 'nope' ) );
	}

	public function test_sections_and_pages_are_isolated(): void {
		$store = $this->store();
		$store->set( 'home', 'hero', 'heading', 'H' );
		$store->set( 'about', 'hero', 'heading', 'A' );
		$this->assertSame( 'H', $store->get( 'home', 'hero', 'heading' ) );
		$this->assertSame( 'A', $store->get( 'about', 'hero', 'heading' ) );
		$this->assertSame( array(), $store->get_section( 'home', 'other' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter ContentStoreTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Content_Store" not found`.

- [ ] **Step 3: Write the implementation `includes/content/class-content-store.php`**

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores singular content for template pages, keyed page -> section -> field.
 * One storage entry per page keeps reads to a single autoloaded option.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Content_Store {

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	private function page_key( string $page ): string {
		return 'content_' . $page;
	}

	/** @return array<string, mixed> */
	public function get_section( string $page, string $section ): array {
		$all = $this->storage->get( $this->page_key( $page ), array() );
		if ( is_array( $all ) && isset( $all[ $section ] ) && is_array( $all[ $section ] ) ) {
			return $all[ $section ];
		}
		return array();
	}

	public function get( string $page, string $section, string $field, mixed $default = null ): mixed {
		$fields = $this->get_section( $page, $section );
		return array_key_exists( $field, $fields ) ? $fields[ $field ] : $default;
	}

	public function set( string $page, string $section, string $field, mixed $value ): void {
		$all = $this->storage->get( $this->page_key( $page ), array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		if ( ! isset( $all[ $section ] ) || ! is_array( $all[ $section ] ) ) {
			$all[ $section ] = array();
		}
		$all[ $section ][ $field ] = $value;
		$this->storage->set( $this->page_key( $page ), $all );
	}
}
```

- [ ] **Step 4: Register the class in the runtime loader**

In `includes/bootstrap.php`, add a "Content" section:

```php
require_once __DIR__ . '/content/class-content-store.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter ContentStoreTest`
Expected: PASS — `OK (5 tests, ...)`.

- [ ] **Step 6: Commit**

```bash
git add includes/content/class-content-store.php includes/bootstrap.php tests/php/ContentStoreTest.php
git commit -m "feat: add content store for singular section content"
```

---

### Task 5: Visibility (page + section show/hide)

Resolves and persists show/hide state for pages and sections, defaulting to visible.

**Files:**
- Create: `includes/content/class-visibility.php`
- Test: `tests/php/VisibilityTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Storage`, `Blueworx_Clubhouse_Fake_Storage`.
- Produces: `class Blueworx_Clubhouse_Visibility` — `__construct(Blueworx_Clubhouse_Storage $storage)`; `is_page_visible(string $page): bool` (default true); `is_section_visible(string $page, string $section): bool` (default true); `set_page_visible(string $page, bool $visible): void`; `set_section_visible(string $page, string $section, bool $visible): void`. Persists to storage key `visibility` as `array{ pages: array<string,bool>, sections: array<string,bool> }`, section keys formatted `"{page}.{section}"`.

- [ ] **Step 1: Write the failing test `tests/php/VisibilityTest.php`**

```php
<?php

use PHPUnit\Framework\TestCase;

final class VisibilityTest extends TestCase {
	private function vis(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_pages_visible_by_default(): void {
		$this->assertTrue( $this->vis()->is_page_visible( 'home' ) );
	}

	public function test_sections_visible_by_default(): void {
		$this->assertTrue( $this->vis()->is_section_visible( 'home', 'hero' ) );
	}

	public function test_hiding_a_page_persists(): void {
		$v = $this->vis();
		$v->set_page_visible( 'blog', false );
		$this->assertFalse( $v->is_page_visible( 'blog' ) );
		$this->assertTrue( $v->is_page_visible( 'home' ) );
	}

	public function test_hiding_a_section_persists(): void {
		$v = $this->vis();
		$v->set_section_visible( 'home', 'hero', false );
		$this->assertFalse( $v->is_section_visible( 'home', 'hero' ) );
		$this->assertTrue( $v->is_section_visible( 'home', 'other' ) );
	}

	public function test_section_keys_do_not_collide_across_pages(): void {
		$v = $this->vis();
		$v->set_section_visible( 'home', 'hero', false );
		$this->assertTrue( $v->is_section_visible( 'about', 'hero' ) );
	}

	public function test_re_showing_a_page(): void {
		$v = $this->vis();
		$v->set_page_visible( 'blog', false );
		$v->set_page_visible( 'blog', true );
		$this->assertTrue( $v->is_page_visible( 'blog' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter VisibilityTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Visibility" not found`.

- [ ] **Step 3: Write the implementation `includes/content/class-visibility.php`**

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show/hide state for pages and sections. Defaults to visible; owners hide by
 * opting out. Persisted as one storage entry mirroring the feature-toggle pattern.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Visibility {

	private const KEY = 'visibility';

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/** @return array<string, array<string, bool>> */
	private function state(): array {
		$state = $this->storage->get( self::KEY, array() );
		return is_array( $state ) ? $state : array();
	}

	private function section_key( string $page, string $section ): string {
		return $page . '.' . $section;
	}

	public function is_page_visible( string $page ): bool {
		$state = $this->state();
		return (bool) ( $state['pages'][ $page ] ?? true );
	}

	public function is_section_visible( string $page, string $section ): bool {
		$state = $this->state();
		return (bool) ( $state['sections'][ $this->section_key( $page, $section ) ] ?? true );
	}

	public function set_page_visible( string $page, bool $visible ): void {
		$state                        = $this->state();
		$state['pages'][ $page ]      = $visible;
		$this->storage->set( self::KEY, $state );
	}

	public function set_section_visible( string $page, string $section, bool $visible ): void {
		$state = $this->state();
		$state['sections'][ $this->section_key( $page, $section ) ] = $visible;
		$this->storage->set( self::KEY, $state );
	}
}
```

- [ ] **Step 4: Register the class in the runtime loader**

In `includes/bootstrap.php`, under the "Content" section:

```php
require_once __DIR__ . '/content/class-visibility.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter VisibilityTest`
Expected: PASS — `OK (6 tests, ...)`.

- [ ] **Step 6: Run the whole suite**

Run: `composer test`
Expected: PASS — all tests from Tasks 1–5 green.

- [ ] **Step 7: Commit**

```bash
git add includes/content/class-visibility.php includes/bootstrap.php tests/php/VisibilityTest.php
git commit -m "feat: add page and section visibility"
```

---

### Task 6: Version bump, changelog, and full verification

Makes the branch CI-green and PR-ready. No new behavior — version metadata, changelog, and a final full-suite + PHP-lint check.

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (header `Version:` and `BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `package.json` (`version`)
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Bump the plugin header version**

In `blueworx-labs-clubhouse.php`, change `* Version:           0.1.1` to `* Version:           0.2.0`.

- [ ] **Step 2: Bump the version constant**

In `blueworx-labs-clubhouse.php`, change `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.1.1' );` to `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.2.0' );`.

- [ ] **Step 3: Bump `package.json`**

In `package.json`, change `"version": "0.1.1",` to `"version": "0.2.0",`.

- [ ] **Step 4: Add a `CHANGELOG.md` entry**

Insert directly above the `## [0.1.1] - 2026-07-09` line:

```markdown
## [0.2.0] - 2026-07-09

### Added

- Engine core & content foundation: PHP unit test harness (PHPUnit, dev-only),
  runtime class loader, base `Registry`, `Storage` interface with an autoloaded
  WordPress options adapter, `Content_Store` for singular section content, and
  page/section `Visibility` — all dependency-injected and unit-tested.

```

- [ ] **Step 5: Verify version sync, PHP lint, and the full suite**

Run each and confirm all pass:
- `node "$GITHUB_WORKSPACE/../bluegroup_core_foundation/scripts/check-plugin-version-sync.mjs"` → `Plugin header and package.json agree (0.2.0).` (Adjust the path to wherever the foundation repo is checked out locally; on CI this runs from `.foundation/scripts/`.)
- `find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l` → `No syntax errors detected` for every file.
- `composer test` → all tests pass.

- [ ] **Step 6: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump version to 0.2.0 and update changelog"
```

- [ ] **Step 7: Push and open the PR (only when the branch is ready for review)**

```bash
git push -u origin engine-core-content-foundation
gh pr create --fill --base main
```

Expected: CI (`guardrails / guardrails`) runs on the PR. Note: the Playwright job needs a real staging/preview URL to pass; until one exists, that step will fail against the placeholder. Coordinate with the repo owner on whether to wire a staging URL before merge or treat this planning/engine branch accordingly.

---

## Notes for the next plan (Module system — Plan 2)

- Section modules will declare a field **schema** consumed by the admin field renderer (Plan 4) and read from `Content_Store` at render (Plan 5).
- Page modules will hold an ordered list of section slugs and be filtered through `Visibility` at render.
- Collection modules will register CPTs; their registration is WordPress glue (integration-tested via Playwright), while any query-shaping helpers should stay pure and unit-tested.
- Reuse `Blueworx_Clubhouse_Registry` for the page, section, and collection registries.
