# Admin Phase 1 — Foundation & Engine Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close four deferred engine/foundation gaps — with zero UI — so the later admin phases can safely change the active look and accent: register all three Base Looks in production, add an accent-legibility validator, harden the theme-cache signature, and replace `Frontend::context()`'s positional array with a named DTO.

**Architecture:** All four items are small, low-risk changes to existing pure/near-pure classes. Three are unit-tested WordPress-free (`Color_Engine`, `Theme_Cache`, a new `Clubhouse_Context` DTO, a new registry-builder helper); the `Frontend` wiring is exercised through the existing `tests/php/wp-stubs.php` recorder/option shims. No new dependencies, no new options schema.

**Tech Stack:** PHP 8.2, PHPUnit 11 (WP-free with `function_exists`-guarded shims), PHP_CodeSniffer (`composer lint`). WordPress plugin.

## Global Constraints

- PHP requirement: `Requires PHP: 8.2` — use typed signatures, `declare(strict_types=1)` at the top of every PHP file.
- Every runtime PHP file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }` after the `declare`.
- Class naming: `Blueworx_Clubhouse_<Name>`; file naming: `class-<kebab-name>.php` under the matching `includes/<area>/` folder.
- Tests are WordPress-free: pure logic tests instantiate classes directly with `Blueworx_Clubhouse_Fake_Storage`; WP glue is asserted via the `wp_stub_*` helpers in `tests/php/wp-stubs.php`. Never require a real WordPress runtime.
- Run the full suite with `./vendor/bin/phpunit`; lint with `composer lint`. Both must be green before the phase is done.
- Accent legibility threshold is WCAG AA: contrast ratio `>= 4.5`.
- Version bump this phase: `0.14.0` → `0.15.0` (minor). Keep `blueworx-labs-clubhouse.php` (header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json` in sync, and update `CHANGELOG.md`.
- Commit after every green step. Use Conventional Commit prefixes (`feat:`, `refactor:`, `test:`, `chore:`).

---

## File Structure

- `includes/theme/class-color-engine.php` — **modify**: add pure `accent_is_legible()`.
- `includes/theme/class-theme-cache.php` — **modify**: harden `signature()`.
- `includes/frontend/class-frontend.php` — **modify**: add `registry()` builder; refactor `context()` to build via it and return the DTO; update the three consumers.
- `includes/frontend/class-clubhouse-context.php` — **create**: the named DTO.
- `blueworx-labs-clubhouse.php` — **modify**: require the DTO file before `class-frontend.php`; version bump.
- `tests/php/bootstrap.php` — **modify**: require the DTO file so tests can load it.
- `tests/php/ColorEngineLegibilityTest.php` — **create**.
- `tests/php/ThemeCacheTest.php` — **modify**: add signature-hardening tests.
- `tests/php/FrontendRegistryTest.php` — **create**.
- `tests/php/ClubhouseContextTest.php` — **create**.
- `tests/php/FrontendTest.php` — **modify**: add a `club_name()` harness test.
- `package.json`, `CHANGELOG.md` — **modify**: version + changelog (final task).

---

## Task 1: `Color_Engine::accent_is_legible()`

Pure static validator. This is the single home for the deferred "reject low-contrast accents" and "accent-on-ink guard" items. An accent is legible for a look iff **both** derived tokens clear AA: the ink on the accent fill (`accent-ink` vs `accent`) **and** the accent-as-text on the shell (`accent-deep` vs the shell background).

**Files:**
- Modify: `includes/theme/class-color-engine.php` (add a method; class ends at line 117)
- Test: `tests/php/ColorEngineLegibilityTest.php` (create)

**Interfaces:**
- Consumes: existing `Blueworx_Clubhouse_Color_Engine::derive( string $accent, string $shell_bg, string $shell_ink ): array` (returns keys `--color-accent`, `--color-accent-ink`, `--color-accent-deep`, `--color-accent-wash`), `::contrast_ratio( string $a, string $b ): float`, and the protected `::normalize_hex( string $hex ): string`.
- Produces: `Blueworx_Clubhouse_Color_Engine::accent_is_legible( string $accent, string $shell_bg, string $shell_ink ): bool` — consumed later by the Phase 2 setup screen.

- [ ] **Step 1: Write the failing test**

Create `tests/php/ColorEngineLegibilityTest.php`:

```php
<?php
// tests/php/ColorEngineLegibilityTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ColorEngineLegibilityTest extends TestCase {

	// Court Side shell (light): warm near-white bg, near-black ink.
	private const CS_BG  = '#faf8f3';
	private const CS_INK = '#1c1b18';

	// Floodlight shell (dark): warm-ink canvas, bone ink (both light).
	private const FL_BG  = '#14110b';
	private const FL_INK = '#f3ede0';

	public function test_saturated_accent_is_legible_on_light_shell(): void {
		// Volt Lime: dark ink wins on the light lime fill; deep is AA-guaranteed.
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( '#c6f24e', self::CS_BG, self::CS_INK )
		);
	}

	public function test_dark_accent_is_legible_on_light_shell(): void {
		// Claret: white ink wins on the dark fill.
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( '#7a2f3a', self::CS_BG, self::CS_INK )
		);
	}

	public function test_light_accent_is_illegible_on_dark_shell(): void {
		// On Floodlight both candidate inks (bone + white) are light, so a light
		// accent fill cannot carry legible text: accent-ink fails AA -> not legible.
		$this->assertFalse(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( '#c6f24e', self::FL_BG, self::FL_INK )
		);
	}

	public function test_normalizes_input_hex_forms(): void {
		// Accepts shorthand / missing-hash input the same way derive() does.
		$this->assertSame(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( '#c6f24e', self::CS_BG, self::CS_INK ),
			Blueworx_Clubhouse_Color_Engine::accent_is_legible( 'c6f24e', self::CS_BG, self::CS_INK )
		);
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter ColorEngineLegibilityTest`
Expected: FAIL — `Call to undefined method Blueworx_Clubhouse_Color_Engine::accent_is_legible()`.

- [ ] **Step 3: Write minimal implementation**

In `includes/theme/class-color-engine.php`, add this method inside the class (e.g. immediately after `derive()`, before the closing `}` on line 117):

```php
	/**
	 * Is this accent legible against the given shell? True iff BOTH derived
	 * tokens clear WCAG AA (>= 4.5): the ink on the accent fill (accent-ink vs
	 * accent) and the accent-as-text on the shell (accent-deep vs shell bg).
	 *
	 * accent-deep is AA-guaranteed by derive() on any shell, so in practice the
	 * binding constraint is accent-ink — a light accent on a light-ink (dark)
	 * shell has no legible text colour and is rejected. Used by the admin setup
	 * screen to refuse low-contrast accents at selection time.
	 */
	public static function accent_is_legible( string $accent, string $shell_bg, string $shell_ink ): bool {
		$d       = self::derive( $accent, $shell_bg, $shell_ink );
		$ink_ok  = self::contrast_ratio( $d['--color-accent-ink'], $d['--color-accent'] ) >= 4.5;
		$deep_ok = self::contrast_ratio( $d['--color-accent-deep'], self::normalize_hex( $shell_bg ) ) >= 4.5;
		return $ink_ok && $deep_ok;
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter ColorEngineLegibilityTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/theme/class-color-engine.php tests/php/ColorEngineLegibilityTest.php
git commit -m "feat: add Color_Engine::accent_is_legible accent-contrast validator"
```

---

## Task 2: `Theme_Cache` signature hardening

The cache signature is currently `md5( look->slug() . '|' . accent )`. A plugin upgrade that changes a look's shell tokens (without changing its slug or the accent) would keep serving the stale composed `:root` CSS. Fold a hash of the look's token contents **and** the plugin version into the signature.

**Files:**
- Modify: `includes/theme/class-theme-cache.php:51-57` (the `signature()` method)
- Test: `tests/php/ThemeCacheTest.php` (add tests; existing tests must stay green)

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look::slug()` and `::tokens(): array<string,string>`; `Blueworx_Clubhouse_Branding::get_accent(): string`; the optional constant `BLUEWORX_LABS_CLUBHOUSE_VERSION` (defined in the plugin runtime, **not** in the PHPUnit bootstrap — must be guarded with `defined()`).
- Produces: no signature/API change to `root_css()` / `invalidate()`; only the internal `signature()` value changes (cache busts once after upgrade — acceptable and intended).

- [ ] **Step 1: Write the failing tests**

Add these methods to `tests/php/ThemeCacheTest.php` (inside the class, before the final `}`). They use a tiny in-file fake look so token contents can be varied without touching a real look:

```php
	public function test_signature_changes_when_look_tokens_change(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$css_a = $cache->root_css( new Clubhouse_Test_Look( array( '--color-bg' => '#ffffff', '--color-ink' => '#000000' ) ), $branding );
		// Same slug + accent, but the look now emits a different bg token.
		$css_b = $cache->root_css( new Clubhouse_Test_Look( array( '--color-bg' => '#111111', '--color-ink' => '#eeeeee' ) ), $branding );

		$this->assertNotSame( $css_a, $css_b, 'A token change must bust the cache even with the same slug + accent.' );
	}

	public function test_signature_stable_for_identical_look_and_accent(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$first = $cache->root_css( new Blueworx_Clubhouse_Court_Side(), $branding );
		// Corrupt the stored CSS; an unchanged signature must return the cached (corrupt) value.
		$storage->set( 'root_css', ':root{--sentinel:1}' );
		$this->assertSame( ':root{--sentinel:1}', $cache->root_css( new Blueworx_Clubhouse_Court_Side(), $branding ) );
	}
```

Add this minimal fake look at the very bottom of the file, **after** the test class's closing `}`:

```php
/** Minimal Base Look whose token set can be varied per test. */
final class Clubhouse_Test_Look implements Blueworx_Clubhouse_Base_Look {
	/** @param array<string,string> $tokens */
	public function __construct( private array $tokens ) {}
	public function slug(): string { return 'test-look'; }
	public function name(): string { return 'Test Look'; }
	public function description(): string { return 'Fixture look for cache-signature tests.'; }
	public function tokens(): array { return $this->tokens; }
	public function fonts(): array { return array(); }
	public function stylesheet(): string { return 'assets/looks/test-look.css'; }
}
```

- [ ] **Step 2: Run tests to verify the new one fails**

Run: `./vendor/bin/phpunit --filter ThemeCacheTest`
Expected: `test_signature_changes_when_look_tokens_change` FAILS (`Failed asserting that two strings are not identical` — with the old signature, same slug + accent → same signature → the second call returns the first cached CSS). Other `ThemeCacheTest` tests still PASS.

- [ ] **Step 3: Write minimal implementation**

Replace the `signature()` method in `includes/theme/class-theme-cache.php` (lines 51-57) with:

```php
	private static function signature(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding
	): string {
		// Tokens depend on the look's slug, its shell token *contents*, the derived
		// accent, and the plugin version. Hashing the token contents + version means
		// an upgrade that changes a look's tokens (same slug/accent) still busts the
		// cache. The version constant is absent under PHPUnit, so guard it.
		$version = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : 'dev';
		$tokens  = self::serialize_tokens( $look->tokens() );
		return md5( $look->slug() . '|' . $branding->get_accent() . '|' . $tokens . '|' . $version );
	}
```

Add a tiny private helper to the same class (place it directly above `signature()`) so token hashing is deterministic and dependency-free (no reliance on `json_encode` key ordering):

```php
	/** @param array<string,string> $tokens */
	private static function serialize_tokens( array $tokens ): string {
		ksort( $tokens );
		$parts = array();
		foreach ( $tokens as $key => $value ) {
			$parts[] = $key . ':' . $value;
		}
		return implode( ';', $parts );
	}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter ThemeCacheTest`
Expected: PASS (all `ThemeCacheTest` tests, including the two new ones).

- [ ] **Step 5: Commit**

```bash
git add includes/theme/class-theme-cache.php tests/php/ThemeCacheTest.php
git commit -m "fix: include look tokens + plugin version in Theme_Cache signature"
```

---

## Task 3: `Frontend::registry()` — register all three looks

`Frontend::context()` currently registers **only** Court Side, so on a live install the active look can never resolve to Members' House or Floodlight — the future Base Look picker would be a silent no-op. Extract a small, testable builder that registers all three looks against a given storage, and unit-test that the stored active slug resolves correctly. (Task 4 makes `context()` use it.)

**Files:**
- Modify: `includes/frontend/class-frontend.php` (add a public static `registry()`)
- Test: `tests/php/FrontendRegistryTest.php` (create)

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look_Registry` (`register()`, `set_active()`, `active()`), `Blueworx_Clubhouse_Court_Side`, `Blueworx_Clubhouse_Members_House`, `Blueworx_Clubhouse_Floodlight`, and the `Blueworx_Clubhouse_Storage` interface.
- Produces: `Blueworx_Clubhouse_Frontend::registry( Blueworx_Clubhouse_Storage $storage ): Blueworx_Clubhouse_Base_Look_Registry` — a registry with all three looks registered (Court Side first, so it stays the first-registered fallback). Consumed by `context()` in Task 4.

- [ ] **Step 1: Write the failing test**

Create `tests/php/FrontendRegistryTest.php`:

```php
<?php
// tests/php/FrontendRegistryTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class FrontendRegistryTest extends TestCase {

	public function test_registry_registers_all_three_looks(): void {
		$reg = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Fake_Storage() );

		$this->assertTrue( $reg->has( 'court-side' ) );
		$this->assertTrue( $reg->has( 'members-house' ) );
		$this->assertTrue( $reg->has( 'floodlight' ) );
	}

	public function test_active_resolves_to_stored_non_default_look(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$reg     = Blueworx_Clubhouse_Frontend::registry( $storage );
		$reg->set_active( 'floodlight' );

		// Rebuild from the same storage to prove persistence, not in-memory state.
		$rebuilt = Blueworx_Clubhouse_Frontend::registry( $storage );
		$this->assertInstanceOf( Blueworx_Clubhouse_Floodlight::class, $rebuilt->active() );
	}

	public function test_active_falls_back_to_court_side_when_unset(): void {
		$reg = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertInstanceOf( Blueworx_Clubhouse_Court_Side::class, $reg->active() );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter FrontendRegistryTest`
Expected: FAIL — `Call to undefined method Blueworx_Clubhouse_Frontend::registry()`.

- [ ] **Step 3: Write minimal implementation**

In `includes/frontend/class-frontend.php`, add this public static method (place it just above the existing private `context()` at line 92):

```php
	/** Build a Base Look registry with all packs registered (Court Side first = fallback). */
	public static function registry( Blueworx_Clubhouse_Storage $storage ): Blueworx_Clubhouse_Base_Look_Registry {
		$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$registry->register( new Blueworx_Clubhouse_Court_Side() );
		$registry->register( new Blueworx_Clubhouse_Members_House() );
		$registry->register( new Blueworx_Clubhouse_Floodlight() );
		return $registry;
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter FrontendRegistryTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/frontend/class-frontend.php tests/php/FrontendRegistryTest.php
git commit -m "fix: register all three Base Looks via Frontend::registry"
```

---

## Task 4: `Clubhouse_Context` DTO + `context()` refactor

Replace `Frontend::context()`'s positional array (destructured with `list()` at three call sites) with a named, read-only DTO, and build it through `Frontend::registry()` so all three looks are live. Adds a sixth member — the registry itself — which Phase 2 needs.

**Files:**
- Create: `includes/frontend/class-clubhouse-context.php`
- Modify: `includes/frontend/class-frontend.php:92-136` (`context()`, `enqueue_assets()`, `render_body()`, `club_name()`)
- Modify: `blueworx-labs-clubhouse.php` (require the DTO before `class-frontend.php`)
- Modify: `tests/php/bootstrap.php` (require the DTO so tests load it)
- Create: `tests/php/ClubhouseContextTest.php`
- Modify: `tests/php/FrontendTest.php` (add a `club_name()` harness test)

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look` (nullable), `Blueworx_Clubhouse_Branding`, `Blueworx_Clubhouse_Visibility`, `Blueworx_Clubhouse_Theme_Cache`, `Blueworx_Clubhouse_Collections`, `Blueworx_Clubhouse_Base_Look_Registry`, and `Blueworx_Clubhouse_Frontend::registry()` (Task 3).
- Produces: `Blueworx_Clubhouse_Clubhouse_Context` with public readonly properties `$look` (`?Blueworx_Clubhouse_Base_Look`), `$branding`, `$visibility`, `$cache`, `$collections`, `$registry`. Consumed by the Phase 2 setup controller.

- [ ] **Step 1: Write the failing DTO test**

Create `tests/php/ClubhouseContextTest.php`:

```php
<?php
// tests/php/ClubhouseContextTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ClubhouseContextTest extends TestCase {

	public function test_holds_named_members(): void {
		$storage    = new Blueworx_Clubhouse_Fake_Storage();
		$registry   = Blueworx_Clubhouse_Frontend::registry( $storage );
		$look       = $registry->active();
		$branding   = new Blueworx_Clubhouse_Branding( $storage );
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$cache      = new Blueworx_Clubhouse_Theme_Cache( $storage );
		$collections = new Blueworx_Clubhouse_Demo_Collections();

		$ctx = new Blueworx_Clubhouse_Clubhouse_Context(
			$look, $branding, $visibility, $cache, $collections, $registry
		);

		$this->assertInstanceOf( Blueworx_Clubhouse_Court_Side::class, $ctx->look );
		$this->assertSame( $branding, $ctx->branding );
		$this->assertSame( $visibility, $ctx->visibility );
		$this->assertSame( $cache, $ctx->cache );
		$this->assertSame( $collections, $ctx->collections );
		$this->assertSame( $registry, $ctx->registry );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter ClubhouseContextTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Clubhouse_Context" not found`.

- [ ] **Step 3: Create the DTO**

Create `includes/frontend/class-clubhouse-context.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable per-request bundle of the engine collaborators the Frontend and
 * (later) the admin screens need. Replaces the old positional array so call
 * sites read by name and a new member can be added without touching every
 * destructuring site.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Clubhouse_Context {

	public function __construct(
		public readonly ?Blueworx_Clubhouse_Base_Look $look,
		public readonly Blueworx_Clubhouse_Branding $branding,
		public readonly Blueworx_Clubhouse_Visibility $visibility,
		public readonly Blueworx_Clubhouse_Theme_Cache $cache,
		public readonly Blueworx_Clubhouse_Collections $collections,
		public readonly Blueworx_Clubhouse_Base_Look_Registry $registry
	) {}
}
```

- [ ] **Step 4: Wire the DTO into both loaders**

In `blueworx-labs-clubhouse.php`, add the require **before** the `class-frontend.php` require (currently line 30):

```php
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-clubhouse-context.php';
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-frontend.php';
```

In `tests/php/bootstrap.php`, add the require **before** the `class-frontend.php` require (currently line 18):

```php
require_once dirname( __DIR__, 2 ) . '/includes/frontend/class-clubhouse-context.php';
require_once dirname( __DIR__, 2 ) . '/includes/frontend/class-frontend.php';
```

- [ ] **Step 5: Run the DTO test to verify it passes**

Run: `./vendor/bin/phpunit --filter ClubhouseContextTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit the DTO**

```bash
git add includes/frontend/class-clubhouse-context.php blueworx-labs-clubhouse.php tests/php/bootstrap.php tests/php/ClubhouseContextTest.php
git commit -m "feat: add Clubhouse_Context DTO"
```

- [ ] **Step 7: Write the failing `club_name()` harness test**

Add to `tests/php/FrontendTest.php` (inside the class). It seeds the branding option through the stubbed `get_option` store (`Options_Storage` prefixes keys with `clubhouse_`), then calls the public `club_name()`, which internally goes through `context()` → DTO → branding:

```php
	public function test_club_name_reads_branding_through_context(): void {
		wp_stub_reset();
		update_option( 'clubhouse_branding', array( 'club_name' => 'Riverside RFC' ) );
		$this->assertSame( 'Riverside RFC', Blueworx_Clubhouse_Frontend::club_name() );
	}
```

- [ ] **Step 8: Run it to verify it passes with the OLD context() (guards the refactor)**

Run: `./vendor/bin/phpunit --filter FrontendTest`
Expected: PASS — this test passes against the current positional-array `context()` too. It is a **characterization test**: it must stay green through the refactor in Step 9, proving the DTO change preserved behaviour.

- [ ] **Step 9: Refactor `context()` and its consumers to the DTO**

In `includes/frontend/class-frontend.php`, replace the `context()` method (lines 92-102) with:

```php
	private static function context(): Blueworx_Clubhouse_Clubhouse_Context {
		$storage  = new Blueworx_Clubhouse_Options_Storage();
		$registry = self::registry( $storage );
		return new Blueworx_Clubhouse_Clubhouse_Context(
			$registry->active(),
			new Blueworx_Clubhouse_Branding( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage ),
			new Blueworx_Clubhouse_Theme_Cache( $storage ),
			new Blueworx_Clubhouse_WP_Collections(),
			$registry
		);
	}
```

Replace the body of `enqueue_assets()` (lines 104-121) — swap the `list()` destructuring for named access:

```php
	public static function enqueue_assets(): void {
		if ( null === self::current_slug() ) {
			return;
		}
		$ctx = self::context();
		if ( null === $ctx->look ) {
			return;
		}
		$specs = self::enqueue_specs(
			$ctx->look,
			$ctx->cache->root_css( $ctx->look, $ctx->branding ),
			BLUEWORX_LABS_CLUBHOUSE_URL
		);
		wp_enqueue_style( 'clubhouse-fonts', $specs['fonts_url'], array(), null );
		wp_enqueue_style( 'clubhouse-look', $specs['stylesheet_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_add_inline_style( 'clubhouse-look', $specs['inline_css'] );
		wp_enqueue_script( 'clubhouse-reveal', $specs['reveal_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}
```

Replace the body of `render_body()` (lines 124-131):

```php
	public static function render_body(): string {
		$slug = self::current_slug();
		if ( null === $slug ) {
			return '';
		}
		$ctx = self::context();
		return Blueworx_Clubhouse_Page_Map::render( $slug, $ctx->branding, $ctx->visibility, $ctx->collections );
	}
```

Replace the body of `club_name()` (lines 133-136):

```php
	public static function club_name(): string {
		return self::context()->branding->get_club_name();
	}
```

- [ ] **Step 10: Run the full suite to verify the refactor preserved behaviour**

Run: `./vendor/bin/phpunit`
Expected: PASS (all tests, including the Step 7 characterization test and every prior test).

- [ ] **Step 11: Commit**

```bash
git add includes/frontend/class-frontend.php tests/php/FrontendTest.php
git commit -m "refactor: return Clubhouse_Context DTO from Frontend::context"
```

---

## Task 5: Version bump, changelog, and full verification

Phase-level wrap-up: bump the version, record the changelog, and confirm the whole suite + lint are green.

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (header `Version:` + `BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `package.json` (`version`)
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Bump the plugin version**

In `blueworx-labs-clubhouse.php`, change the header comment `Version: 0.14.0` → `Version: 0.15.0` and `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.14.0' );` → `'0.15.0'`.

- [ ] **Step 2: Bump package.json**

In `package.json`, change `"version": "0.14.0"` → `"version": "0.15.0"`.

- [ ] **Step 3: Add the changelog entry**

At the top of the entries in `CHANGELOG.md` (immediately below the header block, above `## [0.14.0]`), add:

```markdown
## [0.15.0] - 2026-07-13

### Admin foundation & engine hardening

Groundwork for the admin experience — no user-facing UI yet.

#### Changed

- **All three Base Looks are now registered at runtime.** `Frontend` previously registered only Court Side, so the stored active look could never resolve to Members' House or Floodlight on a live site. A shared `Frontend::registry()` now registers all three (Court Side first as the fallback).
- **Theme-cache signature includes look tokens + plugin version.** The composed `:root` cache was keyed only on look slug + accent, so an upgrade that changed a look's shell tokens served stale CSS. The signature now also hashes the look's token contents and the plugin version.
- **`Frontend::context()` returns a named `Clubhouse_Context` DTO** instead of a positional array, and now carries the Base Look registry for the upcoming setup screen.

#### Added

- **`Color_Engine::accent_is_legible()`** — validates that a club accent clears WCAG AA both as ink on the accent fill and as accent-text on the shell. The admin setup screen will use it to reject low-contrast accents.
```

- [ ] **Step 4: Run the full suite and lint**

Run: `./vendor/bin/phpunit`
Expected: PASS (all tests).

Run: `composer lint`
Expected: PASS (0 errors).

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.15.0 (admin foundation & engine hardening)"
```

---

## Self-Review Notes

- **Spec coverage:** All four Phase 1 items in the umbrella spec are covered — all-looks registration (Task 3), `accent_is_legible()` (Task 1), theme-cache signature (Task 2), `context()` → DTO (Task 4). Version/changelog per the spec's versioning rule (Task 5).
- **Deployment note:** Phase 1 changes runtime files, but the plugin is not being shipped standalone from this branch — the deployment zip is refreshed when the phase is merged, per the established pattern, not inside this plan.
- **Manual smoke:** Phase 1 has no UI; its correctness is fully unit-covered. The manual WordPress smoke owed for routing/CPTs remains a Phase 2–4 concern.
- **Type consistency:** `registry()` returns `Blueworx_Clubhouse_Base_Look_Registry`; the DTO's `$registry` property and `context()`'s construction use the same type. `accent_is_legible()` signature matches the spec's `( accent, shell_bg, shell_ink ): bool`. The DTO property names (`look`, `branding`, `visibility`, `cache`, `collections`, `registry`) are used identically in `enqueue_assets()`, `render_body()`, and `club_name()`.
