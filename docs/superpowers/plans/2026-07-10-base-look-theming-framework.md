# Base Look Theming Framework Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the pure-PHP theming substrate that lets one codebase render many clubs — a registry of pluggable "Base Look" packs, a single-accent colour engine that derives legible tokens for any client colour, and composition of the final `:root` CSS custom-property map.

**Architecture:** Presentation is separated from structure. A **Base Look** supplies only a fixed neutral-shell token set, a font set, and a stylesheet path. A club supplies one **accent**; the **colour engine** derives `accent-ink`/`accent-deep`/`accent-wash` guaranteed legible against that look's shell (works for light *and* dark looks, so re-skin stays safe). **Theme CSS composition** merges active-look tokens + derived accent tokens into an ordered `:root` map. Everything is storage-abstracted and unit-tested with no WordPress runtime, matching the phase-1 engine. WP enqueue/`wp_head` emission and result caching are a later plan.

**Tech Stack:** PHP 8.2+ (`declare(strict_types=1)`), PHPUnit 11, WordPress-plugin conventions (`Blueworx_Clubhouse_*` class prefix, `class-*.php`/`interface-*.php` filenames, ABSPATH guard, tab indentation, `( $spaced )` parens). No new runtime dependencies.

## Global Constraints

- PHP floor: `>=8.2`; every new `.php` file starts with `declare(strict_types=1);` then the `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard.
- Class prefix: `Blueworx_Clubhouse_`. Filenames: `class-<name>.php` / `interface-<name>.php` under `includes/theme/`.
- No runtime Composer/vendor: runtime classes are loaded by explicit `require_once` in `includes/bootstrap.php`, in dependency order.
- No new third-party dependency (would need `approved-deps.json` approval). Colour math is hand-rolled.
- Storage goes through the `Blueworx_Clubhouse_Storage` interface only — never `get_option()` directly in these classes — so tests use `Blueworx_Clubhouse_Fake_Storage`.
- Copy/brand: default club name is `ClubHouse`; default accent is Court Side lime `#c6f24e`.
- Tests live in `tests/php/*Test.php`, extend `PHPUnit\Framework\TestCase`, no namespace. Test doubles go in `tests/php/fakes/` (auto-loaded by the PHPUnit bootstrap glob).
- Run the suite with `vendor/bin/phpunit` (or `composer test`) from the plugin root.

---

### Task 1: Base Look contract + test double

**Files:**
- Create: `includes/theme/interface-base-look.php`
- Modify: `includes/bootstrap.php` (add loader line)
- Create: `tests/php/fakes/class-fake-base-look.php`
- Test: `tests/php/BaseLookContractTest.php`

**Interfaces:**
- Consumes: nothing (leaf contract).
- Produces: `interface Blueworx_Clubhouse_Base_Look` with:
  - `slug(): string`
  - `name(): string`
  - `description(): string`
  - `tokens(): array<string,string>` — CSS custom-property name ⇒ value for the fixed shell. **MUST include the keys `--color-bg` and `--color-ink`** (the colour engine targets them). Excludes all accent tokens.
  - `fonts(): array<int,array{family:string,weights:array<int,int>,display:string}>` — loadable font assets.
  - `stylesheet(): string` — plugin-root-relative path to the look's CSS file.
  - Test double `Blueworx_Clubhouse_Fake_Look` implementing the interface with constructor-injected values (defaults provided).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/BaseLookContractTest.php

use PHPUnit\Framework\TestCase;

final class BaseLookContractTest extends TestCase {
	public function test_fake_look_satisfies_contract(): void {
		$look = new Blueworx_Clubhouse_Fake_Look();
		$this->assertInstanceOf( Blueworx_Clubhouse_Base_Look::class, $look );
	}

	public function test_reports_identity(): void {
		$look = new Blueworx_Clubhouse_Fake_Look( 'court-side', 'Court Side', 'Bright & playful.' );
		$this->assertSame( 'court-side', $look->slug() );
		$this->assertSame( 'Court Side', $look->name() );
		$this->assertSame( 'Bright & playful.', $look->description() );
	}

	public function test_tokens_include_shell_bg_and_ink(): void {
		$tokens = ( new Blueworx_Clubhouse_Fake_Look() )->tokens();
		$this->assertArrayHasKey( '--color-bg', $tokens );
		$this->assertArrayHasKey( '--color-ink', $tokens );
	}

	public function test_fonts_and_stylesheet(): void {
		$look = new Blueworx_Clubhouse_Fake_Look();
		$this->assertNotEmpty( $look->fonts() );
		$this->assertArrayHasKey( 'family', $look->fonts()[0] );
		$this->assertStringEndsWith( '.css', $look->stylesheet() );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter BaseLookContractTest`
Expected: FAIL — `Error: Interface "Blueworx_Clubhouse_Base_Look" not found` (and Fake_Look undefined).

- [ ] **Step 3: Create the interface**

```php
<?php
// includes/theme/interface-base-look.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A Base Look pack: supplies presentation only (shell tokens, fonts, stylesheet).
 * It never adds/removes sections or reads content. Swapping the active look
 * changes only which tokens/fonts/stylesheet are emitted, so re-skinning a live
 * site is a setting change with zero content re-entry.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Base_Look {

	public function slug(): string;

	public function name(): string;

	public function description(): string;

	/**
	 * Fixed neutral-shell CSS custom properties (no accent tokens).
	 * MUST include '--color-bg' and '--color-ink'.
	 *
	 * @return array<string, string>
	 */
	public function tokens(): array;

	/**
	 * Loadable font assets for this look.
	 *
	 * @return array<int, array{family:string, weights:array<int,int>, display:string}>
	 */
	public function fonts(): array;

	/** Plugin-root-relative path to the look's stylesheet. */
	public function stylesheet(): string;
}
```

- [ ] **Step 4: Create the test double**

```php
<?php
// tests/php/fakes/class-fake-base-look.php
declare(strict_types=1);

final class Blueworx_Clubhouse_Fake_Look implements Blueworx_Clubhouse_Base_Look {

	private string $slug;
	private string $name;
	private string $description;
	/** @var array<string,string> */
	private array $tokens;

	/** @param array<string,string>|null $tokens */
	public function __construct(
		string $slug = 'fake',
		string $name = 'Fake Look',
		string $description = 'A test look.',
		?array $tokens = null
	) {
		$this->slug        = $slug;
		$this->name        = $name;
		$this->description = $description;
		$this->tokens      = $tokens ?? array(
			'--color-bg'   => '#faf8f3',
			'--color-ink'  => '#1c1b18',
			'--radius-lg'  => '24px',
			'--font-display' => 'Syne, sans-serif',
		);
	}

	public function slug(): string { return $this->slug; }
	public function name(): string { return $this->name; }
	public function description(): string { return $this->description; }

	/** @return array<string,string> */
	public function tokens(): array { return $this->tokens; }

	/** @return array<int,array{family:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Syne', 'weights' => array( 600, 700, 800 ), 'display' => 'swap' ),
			array( 'family' => 'Inter', 'weights' => array( 400, 500, 600 ), 'display' => 'swap' ),
		);
	}

	public function stylesheet(): string {
		return 'assets/looks/' . $this->slug . '.css';
	}
}
```

- [ ] **Step 5: Register the interface in the runtime loader**

In `includes/bootstrap.php`, after the `// Content` block, add:

```php

// Theme
require_once __DIR__ . '/theme/interface-base-look.php';
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter BaseLookContractTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add includes/theme/interface-base-look.php includes/bootstrap.php tests/php/fakes/class-fake-base-look.php tests/php/BaseLookContractTest.php
git commit -m "feat: add Base Look contract and test double"
```

---

### Task 2: Base Look registry + active-look resolution

**Files:**
- Create: `includes/theme/class-base-look-registry.php`
- Modify: `includes/bootstrap.php` (add loader line)
- Test: `tests/php/BaseLookRegistryTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look` (Task 1); `Blueworx_Clubhouse_Registry` and `Blueworx_Clubhouse_Storage` (phase 1); `Blueworx_Clubhouse_Fake_Storage`, `Blueworx_Clubhouse_Fake_Look` (test doubles).
- Produces: `class Blueworx_Clubhouse_Base_Look_Registry`:
  - `__construct( Blueworx_Clubhouse_Storage $storage )`
  - `register( Blueworx_Clubhouse_Base_Look $look ): void`
  - `has( string $slug ): bool`
  - `get( string $slug ): ?Blueworx_Clubhouse_Base_Look`
  - `all(): array<string, Blueworx_Clubhouse_Base_Look>` — in registration order
  - `active(): ?Blueworx_Clubhouse_Base_Look` — stored active slug if registered, else first registered, else null
  - `set_active( string $slug ): void` — persists the active slug

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/BaseLookRegistryTest.php

use PHPUnit\Framework\TestCase;

final class BaseLookRegistryTest extends TestCase {

	private function registry(): Blueworx_Clubhouse_Base_Look_Registry {
		return new Blueworx_Clubhouse_Base_Look_Registry( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_register_and_get(): void {
		$r = $this->registry();
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'court-side', 'Court Side' ) );
		$this->assertTrue( $r->has( 'court-side' ) );
		$this->assertSame( 'Court Side', $r->get( 'court-side' )->name() );
		$this->assertNull( $r->get( 'nope' ) );
	}

	public function test_all_preserves_registration_order(): void {
		$r = $this->registry();
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'c', 'C' ) );
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'a', 'A' ) );
		$this->assertSame( array( 'c', 'a' ), array_keys( $r->all() ) );
	}

	public function test_active_defaults_to_first_registered(): void {
		$r = $this->registry();
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'court-side', 'Court Side' ) );
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'floodlight', 'Floodlight' ) );
		$this->assertSame( 'court-side', $r->active()->slug() );
	}

	public function test_set_active_persists_and_wins(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$r1      = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$r1->register( new Blueworx_Clubhouse_Fake_Look( 'court-side' ) );
		$r1->register( new Blueworx_Clubhouse_Fake_Look( 'floodlight' ) );
		$r1->set_active( 'floodlight' );

		// New registry over the SAME storage sees the persisted choice.
		$r2 = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$r2->register( new Blueworx_Clubhouse_Fake_Look( 'court-side' ) );
		$r2->register( new Blueworx_Clubhouse_Fake_Look( 'floodlight' ) );
		$this->assertSame( 'floodlight', $r2->active()->slug() );
	}

	public function test_active_ignores_unregistered_stored_slug(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$storage->set( 'active_base_look', 'ghost' );
		$r = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$r->register( new Blueworx_Clubhouse_Fake_Look( 'court-side' ) );
		$this->assertSame( 'court-side', $r->active()->slug() );
	}

	public function test_active_is_null_when_empty(): void {
		$this->assertNull( $this->registry()->active() );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter BaseLookRegistryTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Base_Look_Registry" not found`.

- [ ] **Step 3: Write the registry**

```php
<?php
// includes/theme/class-base-look-registry.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Base Look packs and resolves the active one. The active slug is
 * persisted via storage; when unset or stale it falls back to the first
 * registered look so a fresh install always renders.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Base_Look_Registry {

	private const ACTIVE_KEY = 'active_base_look';

	private Blueworx_Clubhouse_Registry $looks;
	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
		$this->looks   = new Blueworx_Clubhouse_Registry();
	}

	public function register( Blueworx_Clubhouse_Base_Look $look ): void {
		$this->looks->register( $look->slug(), $look );
	}

	public function has( string $slug ): bool {
		return $this->looks->has( $slug );
	}

	public function get( string $slug ): ?Blueworx_Clubhouse_Base_Look {
		$look = $this->looks->get( $slug );
		return $look instanceof Blueworx_Clubhouse_Base_Look ? $look : null;
	}

	/** @return array<string, Blueworx_Clubhouse_Base_Look> */
	public function all(): array {
		return $this->looks->all();
	}

	public function active(): ?Blueworx_Clubhouse_Base_Look {
		$slug = $this->storage->get( self::ACTIVE_KEY, '' );
		if ( is_string( $slug ) && $this->has( $slug ) ) {
			return $this->get( $slug );
		}
		$keys = $this->looks->keys();
		return $keys === array() ? null : $this->get( $keys[0] );
	}

	public function set_active( string $slug ): void {
		$this->storage->set( self::ACTIVE_KEY, $slug );
	}
}
```

- [ ] **Step 4: Register in the runtime loader**

In `includes/bootstrap.php`, under the `// Theme` block, after the interface line, add:

```php
require_once __DIR__ . '/theme/class-base-look-registry.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter BaseLookRegistryTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/theme/class-base-look-registry.php includes/bootstrap.php tests/php/BaseLookRegistryTest.php
git commit -m "feat: add Base Look registry with active-look resolution"
```

---

### Task 3: Colour engine — luminance & contrast primitives

**Files:**
- Create: `includes/theme/class-color-engine.php`
- Modify: `includes/bootstrap.php` (add loader line)
- Test: `tests/php/ColorEngineMathTest.php`

**Interfaces:**
- Consumes: nothing (pure static math).
- Produces: `class Blueworx_Clubhouse_Color_Engine` (partial — extended in Task 4):
  - `public static function relative_luminance( string $hex ): float` — WCAG relative luminance 0.0–1.0
  - `public static function contrast_ratio( string $a, string $b ): float` — WCAG contrast 1.0–21.0
  - `public static function mix( string $a, string $b, float $weight_a ): string` — blend two hexes, `weight_a` in 0.0–1.0, returns lowercase `#rrggbb`
  - (private helpers `normalize_hex`, `to_rgb`, `to_hex` as needed)

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/ColorEngineMathTest.php

use PHPUnit\Framework\TestCase;

final class ColorEngineMathTest extends TestCase {

	public function test_luminance_extremes(): void {
		$this->assertEqualsWithDelta( 0.0, Blueworx_Clubhouse_Color_Engine::relative_luminance( '#000000' ), 0.001 );
		$this->assertEqualsWithDelta( 1.0, Blueworx_Clubhouse_Color_Engine::relative_luminance( '#ffffff' ), 0.001 );
	}

	public function test_contrast_black_on_white_is_21(): void {
		$this->assertEqualsWithDelta( 21.0, Blueworx_Clubhouse_Color_Engine::contrast_ratio( '#000000', '#ffffff' ), 0.05 );
	}

	public function test_contrast_is_symmetric(): void {
		$ab = Blueworx_Clubhouse_Color_Engine::contrast_ratio( '#c6f24e', '#1c1b18' );
		$ba = Blueworx_Clubhouse_Color_Engine::contrast_ratio( '#1c1b18', '#c6f24e' );
		$this->assertEqualsWithDelta( $ab, $ba, 0.0001 );
	}

	public function test_hex_normalisation_accepts_shorthand_and_no_hash(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Color_Engine::relative_luminance( '#ffffff' ),
			Blueworx_Clubhouse_Color_Engine::relative_luminance( 'fff' )
		);
	}

	public function test_mix_endpoints_and_midpoint(): void {
		$this->assertSame( '#ffffff', Blueworx_Clubhouse_Color_Engine::mix( '#ffffff', '#000000', 1.0 ) );
		$this->assertSame( '#000000', Blueworx_Clubhouse_Color_Engine::mix( '#ffffff', '#000000', 0.0 ) );
		$this->assertSame( '#808080', Blueworx_Clubhouse_Color_Engine::mix( '#ffffff', '#000000', 0.5 ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter ColorEngineMathTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Color_Engine" not found`.

- [ ] **Step 3: Write the colour engine (math primitives)**

```php
<?php
// includes/theme/class-color-engine.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure colour math. Turns one club accent into a set of legible, derived
 * tokens (see Task 4). No WordPress, no storage — deterministic functions so
 * the multi-client colour guarantees are unit-tested.
 *
 * @package BlueworxLabsClubhouse
 */
class Blueworx_Clubhouse_Color_Engine {

	/** Normalise '#rgb' / 'rgb' / '#rrggbb' / 'rrggbb' to lowercase '#rrggbb'. */
	protected static function normalize_hex( string $hex ): string {
		$hex = strtolower( ltrim( trim( $hex ), '#' ) );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/', $hex ) ) {
			$hex = '000000';
		}
		return '#' . $hex;
	}

	/** @return array{0:int,1:int,2:int} */
	protected static function to_rgb( string $hex ): array {
		$hex = ltrim( self::normalize_hex( $hex ), '#' );
		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	protected static function to_hex( int $r, int $g, int $b ): string {
		$clamp = static fn ( int $v ): int => max( 0, min( 255, $v ) );
		return sprintf( '#%02x%02x%02x', $clamp( $r ), $clamp( $g ), $clamp( $b ) );
	}

	public static function relative_luminance( string $hex ): float {
		$lin = static function ( int $c ): float {
			$s = $c / 255;
			return $s <= 0.03928 ? $s / 12.92 : ( ( $s + 0.055 ) / 1.055 ) ** 2.4;
		};
		[ $r, $g, $b ] = self::to_rgb( $hex );
		return 0.2126 * $lin( $r ) + 0.7152 * $lin( $g ) + 0.0722 * $lin( $b );
	}

	public static function contrast_ratio( string $a, string $b ): float {
		$la = self::relative_luminance( $a );
		$lb = self::relative_luminance( $b );
		$hi = max( $la, $lb );
		$lo = min( $la, $lb );
		return ( $hi + 0.05 ) / ( $lo + 0.05 );
	}

	public static function mix( string $a, string $b, float $weight_a ): string {
		$weight_a = max( 0.0, min( 1.0, $weight_a ) );
		[ $ar, $ag, $ab ] = self::to_rgb( $a );
		[ $br, $bg, $bb ] = self::to_rgb( $b );
		$blend = static fn ( int $x, int $y ): int => (int) round( $x * $weight_a + $y * ( 1 - $weight_a ) );
		return self::to_hex( $blend( $ar, $br ), $blend( $ag, $bg ), $blend( $ab, $bb ) );
	}
}
```

- [ ] **Step 4: Register in the runtime loader**

In `includes/bootstrap.php`, under `// Theme`, after the registry line, add:

```php
require_once __DIR__ . '/theme/class-color-engine.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter ColorEngineMathTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/theme/class-color-engine.php includes/bootstrap.php tests/php/ColorEngineMathTest.php
git commit -m "feat: add colour engine luminance/contrast/mix primitives"
```

---

### Task 4: Colour engine — derive accent tokens (the multi-client guarantee)

**Files:**
- Modify: `includes/theme/class-color-engine.php` (add `derive`)
- Test: `tests/php/ColorEngineDeriveTest.php`

**Interfaces:**
- Consumes: the Task 3 static primitives on `Blueworx_Clubhouse_Color_Engine`.
- Produces: `public static function derive( string $accent, string $shell_bg, string $shell_ink ): array` returning exactly these keys (lowercase `#rrggbb` values):
  - `--color-accent` — normalised accent
  - `--color-accent-ink` — text/glyphs on the accent; whichever of `$shell_ink` or white contrasts better with the accent
  - `--color-accent-deep` — accent used *as text* on `$shell_bg`; blended toward black (light shell) or white (dark shell) until it reaches AA (≥4.5) against the shell
  - `--color-accent-wash` — a faint shell-tinted accent for subtle zones (12% accent / 88% shell_bg)

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/ColorEngineDeriveTest.php

use PHPUnit\Framework\TestCase;

final class ColorEngineDeriveTest extends TestCase {

	private const LIGHT_BG  = '#faf8f3';
	private const LIGHT_INK = '#1c1b18';
	private const DARK_BG   = '#17181a';
	private const DARK_INK  = '#f4f2ec';
	private const MID_BG    = '#808080'; // mid grey — luminance ~0.22, the AA danger band
	private const MID_INK   = '#ffffff';

	/** @return array<string,string> */
	private function derive( string $accent, bool $dark = false ): array {
		return $dark
			? Blueworx_Clubhouse_Color_Engine::derive( $accent, self::DARK_BG, self::DARK_INK )
			: Blueworx_Clubhouse_Color_Engine::derive( $accent, self::LIGHT_BG, self::LIGHT_INK );
	}

	public function test_returns_expected_keys(): void {
		$t = $this->derive( '#c6f24e' );
		$this->assertSame(
			array( '--color-accent', '--color-accent-ink', '--color-accent-deep', '--color-accent-wash' ),
			array_keys( $t )
		);
	}

	public function test_accent_is_normalised(): void {
		$this->assertSame( '#c6f24e', $this->derive( 'C6F24E' )['--color-accent'] );
	}

	/** A pale accent takes DARK ink; the ink must clear AA on the accent. */
	public function test_pale_accent_gets_dark_ink(): void {
		$t = $this->derive( '#c6f24e' ); // volt lime
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-ink'], '#c6f24e' )
		);
		$this->assertLessThan(
			0.5,
			Blueworx_Clubhouse_Color_Engine::relative_luminance( $t['--color-accent-ink'] )
		);
	}

	/** A dark accent takes LIGHT ink. */
	public function test_dark_accent_gets_light_ink(): void {
		$t = $this->derive( '#1f7a4d' ); // racing green
		$this->assertGreaterThan(
			0.5,
			Blueworx_Clubhouse_Color_Engine::relative_luminance( $t['--color-accent-ink'] )
		);
	}

	/**
	 * accent-deep must be legible AS TEXT on the shell for the full hue range —
	 * this is the core multi-client guarantee.
	 *
	 * @dataProvider hues
	 */
	public function test_accent_deep_clears_AA_on_light_shell( string $accent ): void {
		$t = $this->derive( $accent );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-deep'], self::LIGHT_BG ),
			"accent-deep for {$accent} fails AA on the light shell"
		);
	}

	/**
	 * Same guarantee on a DARK look (re-skin safety), full hue range.
	 *
	 * @dataProvider hues
	 */
	public function test_accent_deep_clears_AA_on_dark_shell( string $accent ): void {
		$t = $this->derive( $accent, true );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-deep'], self::DARK_BG ),
			"accent-deep for {$accent} fails AA on the dark shell"
		);
	}

	/**
	 * Hardest case: a MID-TONE shell (luminance in the band where a pure white
	 * text pole is not trivially safe) must still yield AA-legible accent-deep
	 * for every hue — regression guard for pole selection.
	 *
	 * @dataProvider hues
	 */
	public function test_accent_deep_clears_AA_on_mid_tone_shell( string $accent ): void {
		$t = Blueworx_Clubhouse_Color_Engine::derive( $accent, self::MID_BG, self::MID_INK );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-deep'], self::MID_BG ),
			"accent-deep for {$accent} fails AA on the mid-tone shell"
		);
	}

	public function test_wash_is_close_to_the_shell(): void {
		$t = $this->derive( '#c6f24e' );
		// A faint tint sits nearer the shell than the raw accent.
		$to_shell = Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-wash'], self::LIGHT_BG );
		$this->assertLessThan( 1.6, $to_shell );
	}

	/** @return array<string,array{0:string}> */
	public static function hues(): array {
		return array(
			'lime'   => array( '#c6f24e' ),
			'orange' => array( '#ff5b23' ),
			'teal'   => array( '#0bb3a2' ),
			'green'  => array( '#1f7a4d' ),
			'cobalt' => array( '#3b5bdb' ),
			'berry'  => array( '#c2337a' ),
			'yellow' => array( '#ffd23f' ),
		);
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter ColorEngineDeriveTest`
Expected: FAIL — `Error: Call to undefined method Blueworx_Clubhouse_Color_Engine::derive()`.

- [ ] **Step 3: Add `derive()` to the colour engine**

Insert this method into `class Blueworx_Clubhouse_Color_Engine` (before the closing brace):

```php
	/**
	 * Derive the legible accent token set for a look's shell.
	 *
	 * @return array{'--color-accent':string,'--color-accent-ink':string,'--color-accent-deep':string,'--color-accent-wash':string}
	 */
	public static function derive( string $accent, string $shell_bg, string $shell_ink ): array {
		$accent = self::normalize_hex( $accent );

		// Ink on the accent: better-contrasting of the look ink vs white.
		$ink = self::contrast_ratio( $shell_ink, $accent ) >= self::contrast_ratio( '#ffffff', $accent )
			? self::normalize_hex( $shell_ink )
			: '#ffffff';

		// Accent-as-text on the shell: blend toward whichever pole (black or
		// white) contrasts MORE with the shell. For any shell luminance at least
		// one pole clears AA (worst case ~4.58 at L≈0.179), so the loop always
		// ends on a legible value. Integer stepping guarantees the pure pole
		// (i = 0) is actually evaluated; break on first pass keeps the deep colour
		// as close to the brand accent as legibility allows.
		$pole = self::contrast_ratio( '#000000', $shell_bg ) >= self::contrast_ratio( '#ffffff', $shell_bg )
			? '#000000'
			: '#ffffff';
		$deep = $pole;
		for ( $i = 20; $i >= 0; $i-- ) {
			$candidate = self::mix( $accent, $pole, $i / 20 );
			if ( self::contrast_ratio( $candidate, $shell_bg ) >= 4.5 ) {
				$deep = $candidate;
				break;
			}
		}

		return array(
			'--color-accent'      => $accent,
			'--color-accent-ink'  => $ink,
			'--color-accent-deep' => $deep,
			'--color-accent-wash' => self::mix( $accent, self::normalize_hex( $shell_bg ), 0.12 ),
		);
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter ColorEngineDeriveTest`
Expected: PASS (all cases, including the 7-hue `accent-deep` data provider).

- [ ] **Step 5: Commit**

```bash
git add includes/theme/class-color-engine.php tests/php/ColorEngineDeriveTest.php
git commit -m "feat: derive legible accent tokens for any client colour"
```

---

### Task 5: Branding store (owner inputs)

**Files:**
- Create: `includes/theme/class-branding.php`
- Modify: `includes/bootstrap.php` (add loader line)
- Test: `tests/php/BrandingTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Storage`. Branding does NOT depend on the colour engine: it only trims/lowercases the accent and ensures a leading `#`; full hex normalisation happens later, at `Color_Engine::derive()` time.
- Produces: `class Blueworx_Clubhouse_Branding`:
  - `__construct( Blueworx_Clubhouse_Storage $storage )`
  - `get_accent(): string` (default `#c6f24e`)
  - `set_accent( string $hex ): void`
  - `get_club_name(): string` (default `ClubHouse`)
  - `set_club_name( string $name ): void`
  - `get_logo(): string` (default `''`)
  - `set_logo( string $url_or_id ): void`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/BrandingTest.php

use PHPUnit\Framework\TestCase;

final class BrandingTest extends TestCase {

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_defaults(): void {
		$b = $this->branding();
		$this->assertSame( '#c6f24e', $b->get_accent() );
		$this->assertSame( 'ClubHouse', $b->get_club_name() );
		$this->assertSame( '', $b->get_logo() );
	}

	public function test_accent_persists_and_is_lowercased_with_hash(): void {
		$b = $this->branding();
		$b->set_accent( 'FF5B23' );
		$this->assertSame( '#ff5b23', $b->get_accent() );
	}

	public function test_name_and_logo_persist(): void {
		$b = $this->branding();
		$b->set_club_name( 'Marlow Rugby' );
		$b->set_logo( 'https://x/logo.png' );
		$this->assertSame( 'Marlow Rugby', $b->get_club_name() );
		$this->assertSame( 'https://x/logo.png', $b->get_logo() );
	}

	public function test_survives_a_new_instance_over_same_storage(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_accent( '#3b5bdb' );
		$this->assertSame( '#3b5bdb', ( new Blueworx_Clubhouse_Branding( $storage ) )->get_accent() );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter BrandingTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Branding" not found`.

- [ ] **Step 3: Write the branding store**

```php
<?php
// includes/theme/class-branding.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owner-supplied brand inputs: one accent, club name, logo. Stored as a single
 * autoloaded option (via the storage abstraction). Colour derivation lives in
 * the colour engine — this class only holds the raw inputs.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Branding {

	private const KEY = 'branding';

	private const DEFAULTS = array(
		'accent'    => '#c6f24e',
		'club_name' => 'ClubHouse',
		'logo'      => '',
	);

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/** @return array<string,mixed> */
	private function data(): array {
		$data = $this->storage->get( self::KEY, array() );
		return is_array( $data ) ? $data : array();
	}

	private function value( string $field ): mixed {
		$data = $this->data();
		return array_key_exists( $field, $data ) ? $data[ $field ] : self::DEFAULTS[ $field ];
	}

	private function put( string $field, mixed $value ): void {
		$data            = $this->data();
		$data[ $field ]  = $value;
		$this->storage->set( self::KEY, $data );
	}

	public function get_accent(): string {
		return (string) $this->value( 'accent' );
	}

	public function set_accent( string $hex ): void {
		$this->put( 'accent', '#' . strtolower( ltrim( trim( $hex ), '#' ) ) );
	}

	public function get_club_name(): string {
		return (string) $this->value( 'club_name' );
	}

	public function set_club_name( string $name ): void {
		$this->put( 'club_name', $name );
	}

	public function get_logo(): string {
		return (string) $this->value( 'logo' );
	}

	public function set_logo( string $url_or_id ): void {
		$this->put( 'logo', $url_or_id );
	}
}
```

- [ ] **Step 4: Register in the runtime loader**

In `includes/bootstrap.php`, under `// Theme`, after the colour-engine line, add:

```php
require_once __DIR__ . '/theme/class-branding.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter BrandingTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/theme/class-branding.php includes/bootstrap.php tests/php/BrandingTest.php
git commit -m "feat: add branding store for accent, club name and logo"
```

---

### Task 6: Theme CSS composition (the `:root` map)

**Files:**
- Create: `includes/theme/class-theme-css.php`
- Modify: `includes/bootstrap.php` (add loader line)
- Test: `tests/php/ThemeCssTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look` (`tokens()`, must include `--color-bg`/`--color-ink`), `Blueworx_Clubhouse_Branding` (`get_accent()`), `Blueworx_Clubhouse_Color_Engine::derive()`.
- Produces: `class Blueworx_Clubhouse_Theme_Css`:
  - `public static function compose( Blueworx_Clubhouse_Base_Look $look, Blueworx_Clubhouse_Branding $branding ): array` — ordered `array<string,string>`: the look's shell tokens first, then the four derived accent tokens (accent tokens win on key collision).
  - `public static function to_css( array $vars ): string` — a single-line `:root{--k:v;...}` string.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/ThemeCssTest.php

use PHPUnit\Framework\TestCase;

final class ThemeCssTest extends TestCase {

	private function branding( string $accent ): Blueworx_Clubhouse_Branding {
		$s = new Blueworx_Clubhouse_Fake_Storage();
		$b = new Blueworx_Clubhouse_Branding( $s );
		$b->set_accent( $accent );
		return $b;
	}

	public function test_includes_shell_tokens(): void {
		$vars = Blueworx_Clubhouse_Theme_Css::compose(
			new Blueworx_Clubhouse_Fake_Look(),
			$this->branding( '#c6f24e' )
		);
		$this->assertSame( '#faf8f3', $vars['--color-bg'] );
		$this->assertSame( '#1c1b18', $vars['--color-ink'] );
		$this->assertSame( 'Syne, sans-serif', $vars['--font-display'] );
	}

	public function test_includes_derived_accent_tokens(): void {
		$vars = Blueworx_Clubhouse_Theme_Css::compose(
			new Blueworx_Clubhouse_Fake_Look(),
			$this->branding( '#ff5b23' )
		);
		$this->assertSame( '#ff5b23', $vars['--color-accent'] );
		$this->assertArrayHasKey( '--color-accent-ink', $vars );
		$this->assertArrayHasKey( '--color-accent-deep', $vars );
		$this->assertArrayHasKey( '--color-accent-wash', $vars );
	}

	public function test_accent_token_overrides_a_shell_collision(): void {
		// A look that (wrongly) defines --color-accent must lose to the derived value.
		$look = new Blueworx_Clubhouse_Fake_Look(
			'x', 'X', 'x',
			array( '--color-bg' => '#faf8f3', '--color-ink' => '#1c1b18', '--color-accent' => '#000000' )
		);
		$vars = Blueworx_Clubhouse_Theme_Css::compose( $look, $this->branding( '#3b5bdb' ) );
		$this->assertSame( '#3b5bdb', $vars['--color-accent'] );
	}

	public function test_to_css_emits_root_block(): void {
		$css = Blueworx_Clubhouse_Theme_Css::to_css( array( '--color-bg' => '#fff', '--x' => '1px' ) );
		$this->assertSame( ':root{--color-bg:#fff;--x:1px;}', $css );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter ThemeCssTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Theme_Css" not found`.

- [ ] **Step 3: Write the composer**

```php
<?php
// includes/theme/class-theme-css.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composes the final :root custom-property map for the active look + branding:
 * fixed shell tokens, then the derived accent tokens (which win any collision).
 * Pure — the WP wrapper (later plan) caches to_css() output and inlines it in
 * wp_head, so there is no per-request colour math.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Theme_Css {

	/** @return array<string,string> */
	public static function compose(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding
	): array {
		$shell  = $look->tokens();
		$accent = Blueworx_Clubhouse_Color_Engine::derive(
			$branding->get_accent(),
			$shell['--color-bg'],
			$shell['--color-ink']
		);
		return array_merge( $shell, $accent );
	}

	/** @param array<string,string> $vars */
	public static function to_css( array $vars ): string {
		$decls = '';
		foreach ( $vars as $name => $value ) {
			$decls .= $name . ':' . $value . ';';
		}
		return ':root{' . $decls . '}';
	}
}
```

- [ ] **Step 4: Register in the runtime loader**

In `includes/bootstrap.php`, under `// Theme`, after the branding line, add:

```php
require_once __DIR__ . '/theme/class-theme-css.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter ThemeCssTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Run the FULL suite (no regressions) and bump the version**

Run: `vendor/bin/phpunit`
Expected: PASS — the phase-1 tests plus all new theming tests, green.

Then bump the minor version (new feature) and update the changelog:
- `blueworx-labs-clubhouse.php`: header `Version:` and `BLUEWORX_LABS_CLUBHOUSE_VERSION` → `0.3.0`.
- `CHANGELOG.md`: add a `0.3.0` entry — "Base Look theming framework: pluggable look registry, single-accent colour engine with derived legible tokens, branding store, :root CSS composition."

- [ ] **Step 7: Commit**

```bash
git add includes/theme/class-theme-css.php includes/bootstrap.php tests/php/ThemeCssTest.php blueworx-labs-clubhouse.php CHANGELOG.md
git commit -m "feat: compose :root theme CSS from active look and branding; bump 0.3.0"
```

---

## Self-Review

**1. Spec coverage (against `2026-07-10-base-look-theming-and-site-design.md`):**
- §2 separable structure/skin, Base Look owns fonts, re-skin-safe → Tasks 1–2 (look contract supplies presentation only; registry swaps active look). ✅
- §3 Base Look pack = identity + tokens + fonts + stylesheet + imagery treatment → Task 1 interface (imagery treatment is expressed via the look's tokens/stylesheet, so no separate method is needed for the framework). ✅
- §4 single accent, derived `accent-ink`/`accent-deep`/`accent-wash`, luminance-based ink, AA guarantee across hues, light **and** dark shells → Tasks 3–4 (dark-shell case explicitly tested for re-skin safety). ✅
- §4 "derive once, no runtime math" → Task 6 note: `to_css()` output is cached by the later WP wrapper; composition stays pure here. Documented deviation (cache the composed string rather than store per-token), same intent. ✅
- §6 fonts owned by look → Task 1 `fonts()`. ✅
- §7 inline `:root` tokens, cache-friendly → Task 6 `to_css()`. ✅
- **Out of this plan (later plans):** §5 concrete pages/sections/collections; §5 the three real packs' visuals (Court Side CSS/fonts assets); §6 imagery media slot & real treatments; §8 local WP preview harness; §9 Playwright; §6 font self-hosting. These are explicitly deferred — this plan is the substrate only.

**2. Placeholder scan:** No TBD/TODO/"handle edge cases"/"similar to". Every code step shows complete code; every test step shows real assertions. ✅

**3. Type consistency:** `derive()` signature `(accent, shell_bg, shell_ink)` and its four return keys are identical in Tasks 4 and 6. `Base_Look::tokens()` guarantees `--color-bg`/`--color-ink`, which Task 6 consumes. Registry `active()` returns `?Blueworx_Clubhouse_Base_Look`, matching Task 1's type. Branding `get_accent()` returns `string`, consumed by Task 6. `Fake_Look` constructor `(slug, name, description, ?tokens)` is used consistently in Tasks 1, 2, and 6. ✅

**4. Loader ordering:** interface → registry → color-engine → branding → theme-css. Registry needs `Blueworx_Clubhouse_Registry` (loaded earlier under core); theme-css needs interface + color-engine + branding (all loaded before it). ✅

## Downstream plans (not in this plan)

2. **Court Side pack** — real tokens, Syne+Inter assets (self-hosted), stylesheet, imagery treatment; registered look.
3. **Concrete sections & pages** — §5 inventory as skin-agnostic registered sections/pages with semantic markup.
4. **Collections** — the six CPTs + admin.
5. **Admin setup flow** — Base Look picker → accent/branding → content (bespoke UI, no ACF).
6. **WP render/enqueue + caching** — `template_include`, `wp_head` inline `:root` (cached), per-look font + stylesheet enqueue.
7. **Local WP preview harness + Playwright wiring**; then **A (Members' House)** and **B (Floodlight)** packs as re-skins.
