# Site-wide Demo Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn Demo mode from a private per-admin cookie preview into a stored, site-wide state that only admins can flip on/off, but that every visitor sees and can explore (switching looks per-browser), plus a backend on/off control on the Clubhouse → Setup screen.

**Architecture:** A new Storage-backed `Demo_State` holds the site-wide on/off flag. The pure `Demo_Mode` loses its capability-based `is_active`/`COOKIE_FLAG` (activation is now stored state, not a cap+cookie). The glue `Demo_Controller` reads `Demo_State` for everyone, flips it through a cap+nonce `admin-post` handler (the admin-bar toggle becomes a nonce'd link that works front-end and in wp-admin), and shows the switcher to all viewers with an admin-only "Turn off demo mode" control. The Setup screen gains a matching on/off control.

**Tech Stack:** PHP 8.2, WordPress plugin (`admin_bar_menu`, `wp_footer`, `wp_enqueue_scripts`, `admin_post_{action}`), PHPUnit 9 with the repo's WP-function shim (`tests/php/wp-stubs.php`), vanilla JS. No new dependencies.

## Global Constraints

- **PHP:** `declare(strict_types=1);` + `if ( ! defined( 'ABSPATH' ) ) { exit; }` at the top of every PHP file. PHP 8.2. Tab indentation. `Blueworx_Clubhouse_` class prefix; files `class-<kebab>.php`.
- **Pure vs glue:** pure logic (`Demo_Mode`, `Demo_State`) makes NO WordPress calls and escapes its own output with `htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' )` (see `Setup_Screen::esc`). WordPress-coupled glue lives in `includes/admin/`.
- **Activation is admin-only (security-critical):** only `current_user_can('manage_options')` may change `Demo_State`. The `admin_post` handler checks capability + `check_admin_referer` nonce; the Setup save is already cap + nonce gated. No non-admin path may flip the state.
- **Non-persistence:** the demo path NEVER writes the club's saved look/accent/content (no `Base_Look_Registry::set_active()`). The only new writes are the `clubhouse_demo_active` flag.
- **Public when on:** while `Demo_State` is on, the render override, the switcher, and the demo assets apply to EVERY viewer (logged out, member, admin) — gate these on `is_on()`, not on capability. Only the "Turn off demo mode" control and the admin-bar toggle are capability-gated.
- **Look selection is per-viewer:** anyone may set the `clubhouse_demo_look` cookie; it is a preview only and is never persisted to settings.
- **Registry-driven:** looks enumerated from `registry->all()` / `array_keys( registry->all() )` — never a hardcoded list.
- **Runtime loader:** new runtime classes require'd in `includes/bootstrap.php` (pure) AND `tests/php/bootstrap.php`, in dependency order.
- **Versioning:** minor bump to `0.18.0` across the plugin header, `BLUEWORX_LABS_CLUBHOUSE_VERSION`, and `package.json`, with a matching `CHANGELOG.md` entry.
- **Test command:** `composer test` (PHPUnit). **Lint:** `composer lint` (PHPCS). Both green before the final commit. Every task ends with the FULL suite green (no intermediate red left between tasks).

---

## File Structure

- Create `includes/theme/class-demo-state.php` — Storage-backed on/off flag.
- Modify `includes/frontend/class-demo-mode.php` — remove `is_active`/`COOKIE_FLAG`; `switcher_html` gains an optional deactivate-URL.
- Modify `includes/admin/class-demo-controller.php` — read `Demo_State`; `apply_toggle`/`handle_toggle`; nonce'd admin-bar link; gate on `is_on()`.
- Modify `assets/js/demo.js` — keep only the look-cookie set+reload.
- Modify `includes/admin/class-setup-screen.php` + `class-setup-controller.php` — Demo mode on/off control on the Setup form.
- Modify `tests/php/wp-stubs.php` — add `wp_nonce_url` (guarded).
- Modify/replace tests: `DemoModeTest`, `DemoModeSwitcherTest`, `DemoControllerTest`, `FrontendTest`, `SetupScreenTest`, `SetupControllerTest`; create `DemoStateTest`.
- Modify `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md` — version + changelog.

---

## Task 1: `Demo_State` — the site-wide on/off flag

**Files:**
- Create: `includes/theme/class-demo-state.php`
- Modify: `includes/bootstrap.php`, `tests/php/bootstrap.php` (requires)
- Test: `tests/php/DemoStateTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Storage` (existing).
- Produces:
  - `Blueworx_Clubhouse_Demo_State::__construct( Blueworx_Clubhouse_Storage $storage )`
  - `Blueworx_Clubhouse_Demo_State::is_on(): bool`
  - `Blueworx_Clubhouse_Demo_State::set( bool $on ): void`
  - Option key constant `Blueworx_Clubhouse_Demo_State::KEY = 'demo_active'`.

- [ ] **Step 1: Add the require lines**

In `includes/bootstrap.php`, in the `// Theme` block (after `require_once __DIR__ . '/theme/class-theme-cache.php';`), add:

```php
require_once __DIR__ . '/theme/class-demo-state.php';
```

In `tests/php/bootstrap.php` the theme classes load via `includes/bootstrap.php` already, so no separate line is needed there — but confirm the suite sees the class (Step 3 proves it).

- [ ] **Step 2: Write the failing test**

Create `tests/php/DemoStateTest.php`:

```php
<?php
// tests/php/DemoStateTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoStateTest extends TestCase {

	public function test_defaults_to_off(): void {
		$state = new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertFalse( $state->is_on() );
	}

	public function test_set_true_then_read_on(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Demo_State( $storage ) )->set( true );
		$this->assertTrue( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}

	public function test_set_false_reads_off(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$state   = new Blueworx_Clubhouse_Demo_State( $storage );
		$state->set( true );
		$state->set( false );
		$this->assertFalse( $state->is_on() );
	}

	public function test_non_bool_stored_value_is_coerced(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$storage->set( Blueworx_Clubhouse_Demo_State::KEY, '1' );
		$this->assertTrue( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `composer test -- --filter DemoStateTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Demo_State" not found`.

- [ ] **Step 4: Write minimal implementation**

Create `includes/theme/class-demo-state.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide Demo-mode on/off flag, persisted via the storage abstraction. The
 * single source of truth for "is demo mode on" — read by the front end for
 * every visitor, written only by capability-gated admin controls.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_State {

	public const KEY = 'demo_active';

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	public function is_on(): bool {
		return (bool) $this->storage->get( self::KEY, false );
	}

	public function set( bool $on ): void {
		$this->storage->set( self::KEY, $on );
	}
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter DemoStateTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Run the full suite**

Run: `composer test`
Expected: PASS — all existing tests still green (this task is additive).

- [ ] **Step 7: Commit**

```bash
git add includes/theme/class-demo-state.php includes/bootstrap.php tests/php/DemoStateTest.php
git commit -m "feat: Demo_State site-wide on/off flag (storage-backed)"
```

---

## Task 2: Rework `Demo_Mode`, `Demo_Controller`, and `demo.js` to the site-wide model

This task changes tightly-coupled pieces together so the suite is never left red: `Demo_Mode` loses `is_active`/`COOKIE_FLAG`; `Demo_Controller` reads `Demo_State` and gains the toggle handler; `demo.js` drops the flag/exit cookie logic; and the tests for all three (plus `FrontendTest`) move to the new model.

**Files:**
- Modify: `includes/frontend/class-demo-mode.php`
- Modify: `includes/admin/class-demo-controller.php`
- Modify: `assets/js/demo.js`
- Modify: `tests/php/wp-stubs.php` (add `wp_nonce_url`)
- Test: `tests/php/DemoModeTest.php`, `tests/php/DemoModeSwitcherTest.php`, `tests/php/DemoControllerTest.php`, `tests/php/FrontendTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Demo_State` (Task 1), `Blueworx_Clubhouse_Base_Look_Registry`, `Blueworx_Clubhouse_Options_Storage`.
- Produces:
  - `Demo_Mode::COOKIE_LOOK = 'clubhouse_demo_look'` (unchanged). `COOKIE_FLAG` and `is_active` REMOVED.
  - `Demo_Mode::resolve_look_slug( bool $demo_on, ?string $look_cookie, array $available_slugs ): ?string` (same signature/logic; first param renamed for clarity).
  - `Demo_Mode::switcher_html( array $looks, ?string $current_slug, ?string $deactivate_url ): string` — deactivate control rendered only when `$deactivate_url` is non-null.
  - `Demo_Controller::is_on(): bool`, `::look_slug( Base_Look_Registry ): ?string`, `::apply_toggle( Storage ): bool`, `::handle_toggle(): void`, `::TOGGLE_ACTION = 'clubhouse_demo_toggle'`, `::NONCE = 'clubhouse_demo_toggle'`.

- [ ] **Step 1: Update the pure Demo_Mode tests (red)**

Replace `tests/php/DemoModeTest.php` with (drops the `is_active`/`COOKIE_FLAG` cases, keeps look resolution):

```php
<?php
// tests/php/DemoModeTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoModeTest extends TestCase {

	public function test_look_cookie_constant(): void {
		$this->assertSame( 'clubhouse_demo_look', Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK );
	}

	public function test_resolve_returns_null_when_demo_off(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( false, 'floodlight', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_returns_null_without_look_cookie(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, null, array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_returns_known_slug_when_on(): void {
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, 'floodlight', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_unknown_slug_falls_through(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, 'retired', array( 'court-side', 'floodlight' ) ) );
	}
}
```

Replace `tests/php/DemoModeSwitcherTest.php` with (adds the third arg + deactivate-control cases):

```php
<?php
// tests/php/DemoModeSwitcherTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoModeSwitcherTest extends TestCase {

	/** @return array<int,array{slug:string,name:string}> */
	private function looks(): array {
		return array(
			array( 'slug' => 'court-side', 'name' => 'Court Side' ),
			array( 'slug' => 'members-house', 'name' => "Members' House" ),
			array( 'slug' => 'floodlight', 'name' => 'Floodlight' ),
		);
	}

	public function test_renders_one_control_per_look_for_everyone(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', null );
		$this->assertSame( 3, substr_count( $html, 'data-clubhouse-look="' ) );
		$this->assertStringContainsString( 'data-clubhouse-look="floodlight"', $html );
	}

	public function test_current_look_is_flagged(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'floodlight', null );
		$this->assertMatchesRegularExpression( '/data-clubhouse-look="floodlight"[^>]*aria-pressed="true"/', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-pressed="true"' ) );
	}

	public function test_no_deactivate_control_for_non_admin(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', null );
		$this->assertStringNotContainsString( 'clubhouse-demo__exit', $html );
	}

	public function test_deactivate_control_present_when_url_given(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', 'https://club.test/toggle' );
		$this->assertStringContainsString( 'class="clubhouse-demo__exit"', $html );
		$this->assertStringContainsString( 'href="https://club.test/toggle"', $html );
		$this->assertStringContainsString( 'Turn off demo mode', $html );
	}

	public function test_escapes_dynamic_text(): void {
		$looks = array( array( 'slug' => 'x"y', 'name' => '<b>Hack</b>' ) );
		$html  = Blueworx_Clubhouse_Demo_Mode::switcher_html( $looks, null, null );
		$this->assertStringNotContainsString( '<b>Hack</b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;Hack&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'x&quot;y', $html );
	}

	public function test_skin_agnostic_no_colour_literals(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', 'https://club.test/t' );
		$this->assertDoesNotMatchRegularExpression( '/(?<!&)#[0-9a-fA-F]{3,6}\b/', $html, 'switcher must not hardcode colours' );
		$this->assertStringNotContainsString( 'var(--color-accent', $html );
	}
}
```

- [ ] **Step 2: Run these to verify they fail**

Run: `composer test -- --filter "DemoModeTest|DemoModeSwitcherTest"`
Expected: FAIL — `switcher_html` arity / removed constant references.

- [ ] **Step 3: Rework `Demo_Mode`**

Edit `includes/frontend/class-demo-mode.php`. Update the docblock, remove `COOKIE_FLAG` and `is_active`, keep `COOKIE_LOOK` and `resolve_look_slug` (rename the first param to `$demo_on`), and change `switcher_html`:

Replace the class body between the opening `{` and the closing `}` with:

```php
	public const COOKIE_LOOK = 'clubhouse_demo_look';

	/**
	 * The Base Look slug to render in place of the saved active look, or null to
	 * fall through to the saved look. Unknown/stale slugs fall through (never fatal).
	 * Capability is NOT a factor here — while demo mode is on, any viewer's cookie
	 * selects their own preview look.
	 *
	 * @param array<int,string> $available_slugs
	 */
	public static function resolve_look_slug( bool $demo_on, ?string $look_cookie, array $available_slugs ): ?string {
		if ( ! $demo_on || null === $look_cookie ) {
			return null;
		}
		return in_array( $look_cookie, $available_slugs, true ) ? $look_cookie : null;
	}

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Floating switcher bar, shown to every viewer while demo mode is on. Neutral
	 * chrome — styled by demo.css, no colour literals, no accent tokens. Each look
	 * control carries the slug for demo.js; the current look is flagged. The
	 * "Turn off demo mode" control is emitted only when $deactivate_url is given
	 * (admins only); non-admins get the look controls without it.
	 *
	 * @param array<int,array{slug:string,name:string}> $looks
	 */
	public static function switcher_html( array $looks, ?string $current_slug, ?string $deactivate_url ): string {
		$out  = '<div id="clubhouse-demo" class="clubhouse-demo" role="region" aria-label="Demo mode look switcher">';
		$out .= '<span class="clubhouse-demo__title">Demo mode</span>';
		$out .= '<div class="clubhouse-demo__looks" role="group" aria-label="Choose a Base Look">';
		foreach ( $looks as $look ) {
			$is_current = $look['slug'] === $current_slug;
			$class      = 'clubhouse-demo__look' . ( $is_current ? ' is-current' : '' );
			$pressed    = $is_current ? 'true' : 'false';
			$out       .= '<button type="button" class="' . self::esc( $class ) . '"'
				. ' data-clubhouse-look="' . self::esc( $look['slug'] ) . '"'
				. ' aria-pressed="' . $pressed . '">'
				. self::esc( $look['name'] ) . '</button>';
		}
		$out .= '</div>';
		if ( null !== $deactivate_url ) {
			$out .= '<a class="clubhouse-demo__exit" href="' . self::esc( $deactivate_url ) . '">Turn off demo mode</a>';
		}
		$out .= '</div>';
		return $out;
	}
```

Also update the class-level docblock (lines 8–17) to describe the site-wide model (no "admin-only", no cookie flag): e.g. "Pure decisions and markup for site-wide Demo mode: which registered Base Look a viewer's cookie selects while demo is on, and the floating switcher bar's HTML (with an optional admin-only deactivate control). No WordPress calls; output escaped with htmlspecialchars."

- [ ] **Step 4: Run the pure tests to verify they pass**

Run: `composer test -- --filter "DemoModeTest|DemoModeSwitcherTest"`
Expected: PASS (5 + 6).

- [ ] **Step 5: Update the controller tests (red)**

Replace `tests/php/DemoControllerTest.php` with:

```php
<?php
// tests/php/DemoControllerTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
	}

	protected function tearDown(): void {
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
	}

	public function test_register_hooks_include_admin_post_toggle(): void {
		Blueworx_Clubhouse_Demo_Controller::register();
		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'admin_bar_menu', $actions );
		$this->assertContains( 'wp_footer', $actions );
		$this->assertContains( 'wp_enqueue_scripts', $actions );
		$this->assertContains( 'admin_post_' . Blueworx_Clubhouse_Demo_Controller::TOGGLE_ACTION, $actions );
	}

	public function test_is_on_reflects_stored_state(): void {
		$this->assertFalse( Blueworx_Clubhouse_Demo_Controller::is_on() );
		( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->set( true );
		$this->assertTrue( Blueworx_Clubhouse_Demo_Controller::is_on() );
	}

	public function test_look_slug_uses_cookie_only_when_on(): void {
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'floodlight';
		$this->assertNull( Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ), 'off = no override' );
		( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->set( true );
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ) );
	}

	public function test_look_slug_null_for_unknown_look_when_on(): void {
		( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->set( true );
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'not-a-look';
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		$this->assertNull( Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ) );
	}

	public function test_apply_toggle_flips_state(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$this->assertTrue( Blueworx_Clubhouse_Demo_Controller::apply_toggle( $storage ), 'off -> on' );
		$this->assertTrue( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Controller::apply_toggle( $storage ), 'on -> off' );
		$this->assertFalse( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}

	public function test_enqueue_serves_assets_when_on_regardless_of_cap(): void {
		( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->set( true );
		Blueworx_Clubhouse_Demo_Controller::enqueue();
		$styles = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'wp_enqueue_style' ) );
		$this->assertContains( 'clubhouse-demo', $styles );
	}

	public function test_enqueue_serves_nothing_when_off(): void {
		Blueworx_Clubhouse_Demo_Controller::enqueue();
		$this->assertSame( array(), wp_stub_calls( 'wp_enqueue_style' ) );
	}
}
```

- [ ] **Step 6: Run to verify they fail**

Run: `composer test -- --filter DemoControllerTest`
Expected: FAIL — `TOGGLE_ACTION`/`is_on`/`apply_toggle` undefined.

- [ ] **Step 7: Add the `wp_nonce_url` stub**

In `tests/php/wp-stubs.php`, after the `home_url` stub, add:

```php
if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) { return (string) $url . ( str_contains( (string) $url, '?' ) ? '&' : '?' ) . $name . '=stubnonce'; }
}
```

- [ ] **Step 8: Rework the controller**

Replace `includes/admin/class-demo-controller.php` with:

```php
<?php
// includes/admin/class-demo-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress-coupled glue for site-wide Demo mode. Reads the stored on/off flag
 * (Demo_State) for every visitor and exposes the per-viewer demo look to
 * Frontend::context(); renders the admin-bar toggle (a nonce'd link, works
 * front-end and in wp-admin) and the floating switcher. Only capability-gated
 * admins may flip the flag; the switcher and demo assets are shown to all
 * viewers while on. Never writes the club's saved look.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Controller {

	public const CAPABILITY     = 'manage_options';
	public const TOGGLE_ACTION  = 'clubhouse_demo_toggle';
	public const NONCE          = 'clubhouse_demo_toggle';

	public static function register(): void {
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar_node' ), 100 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_footer', array( self::class, 'render_switcher' ) );
		add_action( 'admin_post_' . self::TOGGLE_ACTION, array( self::class, 'handle_toggle' ) );
	}

	private static function can_manage(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( self::CAPABILITY );
	}

	private static function cookie( string $name ): ?string {
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return null;
		}
		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.NoNonce -- read-only per-viewer preview preference; changes nothing server-side.
	}

	public static function is_on(): bool {
		return ( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->is_on();
	}

	public static function look_slug( Blueworx_Clubhouse_Base_Look_Registry $registry ): ?string {
		return Blueworx_Clubhouse_Demo_Mode::resolve_look_slug(
			self::is_on(),
			self::cookie( Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ),
			array_keys( $registry->all() )
		);
	}

	/** Flip the stored flag; returns the new state. Pure glue — unit-testable with any Storage. */
	public static function apply_toggle( Blueworx_Clubhouse_Storage $storage ): bool {
		$state = new Blueworx_Clubhouse_Demo_State( $storage );
		$next  = ! $state->is_on();
		$state->set( $next );
		return $next;
	}

	public static function handle_toggle(): void {
		if ( ! self::can_manage() ) {
			return;
		}
		check_admin_referer( self::NONCE );
		self::apply_toggle( new Blueworx_Clubhouse_Options_Storage() );
		$back = wp_get_referer();
		wp_safe_redirect( false !== $back ? $back : home_url( '/' ) );
		exit;
	}

	private static function toggle_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TOGGLE_ACTION ), self::NONCE );
	}

	public static function enqueue(): void {
		if ( ! self::is_on() ) {
			return;
		}
		wp_enqueue_style( 'clubhouse-demo', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/demo.css', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_enqueue_script( 'clubhouse-demo', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/demo.js', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	/** @param mixed $wp_admin_bar The WP_Admin_Bar instance. */
	public static function admin_bar_node( $wp_admin_bar ): void {
		if ( ! self::can_manage() || ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'add_node' ) ) {
			return;
		}
		$on = self::is_on();
		$wp_admin_bar->add_node( array(
			'id'    => 'clubhouse-demo-toggle',
			'title' => '⚡ ' . ( $on ? 'Demo mode: On' : 'Demo mode: Off' ),
			'href'  => self::toggle_url(),
			'meta'  => array( 'class' => $on ? 'clubhouse-demo-on' : 'clubhouse-demo-off' ),
		) );
	}

	public static function render_switcher(): void {
		if ( ! self::is_on() ) {
			return;
		}
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		$current  = self::look_slug( $registry ) ?? ( $registry->active() ? $registry->active()->slug() : null );
		$looks    = array();
		foreach ( $registry->all() as $look ) {
			$looks[] = array( 'slug' => $look->slug(), 'name' => $look->name() );
		}
		$deactivate = self::can_manage() ? self::toggle_url() : null;
		echo Blueworx_Clubhouse_Demo_Mode::switcher_html( $looks, $current, $deactivate ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Demo_Mode escapes all dynamic text.
	}
}
```

- [ ] **Step 9: Run controller tests to verify they pass**

Run: `composer test -- --filter DemoControllerTest`
Expected: PASS (8 tests).

- [ ] **Step 10: Rework `demo.js`**

Replace `assets/js/demo.js` with (activation is now a server link; only look-switching stays JS):

```js
/* Blueworx Clubhouse — Demo mode look switcher. Site-wide demo state is toggled
 * by admins via a server link (admin-post); this file only handles the per-viewer
 * look choice: set a cookie and reload so the server re-renders in that look.
 * No dependencies. */
( function () {
	'use strict';

	var LOOK = 'clubhouse_demo_look';

	document.addEventListener( 'click', function ( e ) {
		var look = e.target.closest( '[data-clubhouse-look]' );
		if ( ! look ) {
			return;
		}
		e.preventDefault();
		document.cookie = LOOK + '=' + encodeURIComponent( look.getAttribute( 'data-clubhouse-look' ) ) + '; path=/; SameSite=Lax';
		window.location.reload();
	} );
}() );
```

- [ ] **Step 11: Update `FrontendTest` for the new model**

In `tests/php/FrontendTest.php`: the `tearDown` and the demo-override tests currently reference `Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG` (removed). Update:

Change `tearDown` to clear only the look cookie:

```php
	protected function tearDown(): void {
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
	}
```

Replace `test_active_look_slug_reflects_demo_override_for_admin` with a state-driven version:

```php
	public function test_active_look_slug_reflects_demo_override_when_on(): void {
		wp_stub_reset();
		( new Blueworx_Clubhouse_Demo_State( new Blueworx_Clubhouse_Options_Storage() ) )->set( true );
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'floodlight';
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Frontend::active_look_slug() );
	}
```

`test_active_look_slug_is_saved_look_without_demo` stays valid (demo off by default after `wp_stub_reset()`), but ensure it does not set `COOKIE_FLAG`. If it references `COOKIE_FLAG`, drop that line.

- [ ] **Step 12: Run the full suite + lint**

Run: `composer test`
Expected: PASS — all tests green (no red left from the removed symbols).

Run: `composer lint`
Expected: PASS — 0 errors.

- [ ] **Step 13: Commit**

```bash
git add includes/frontend/class-demo-mode.php includes/admin/class-demo-controller.php assets/js/demo.js tests/php/wp-stubs.php tests/php/DemoModeTest.php tests/php/DemoModeSwitcherTest.php tests/php/DemoControllerTest.php tests/php/FrontendTest.php
git commit -m "feat: site-wide Demo mode — stored state, admin-only toggle, public switcher"
```

---

## Task 3: Demo mode control on the Setup screen

**Files:**
- Modify: `includes/admin/class-setup-screen.php` (render the control + model key)
- Modify: `includes/admin/class-setup-controller.php` (`handle_save` + `build_model`)
- Test: `tests/php/SetupScreenTest.php`, `tests/php/SetupControllerTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Demo_State` (Task 1).
- Produces: Setup model gains a `demo_active` boolean; POST field `clubhouse_demo_active`.

- [ ] **Step 1: Write failing tests**

Add to `tests/php/SetupScreenTest.php` a case asserting the control renders. `SetupScreenTest` has an instance helper `$this->model()` returning the model array — reuse it and add the `demo_active` key:

```php
	public function test_render_includes_demo_mode_toggle(): void {
		$model = $this->model();
		$model['demo_active'] = false;
		$html = Blueworx_Clubhouse_Setup_Screen::render( $model );
		$this->assertStringContainsString( 'name="clubhouse_demo_active"', $html );
		$this->assertStringContainsString( 'Demo mode', $html );
	}

	public function test_render_checks_demo_toggle_when_active(): void {
		$model = $this->model();
		$model['demo_active'] = true;
		$html = Blueworx_Clubhouse_Setup_Screen::render( $model );
		$this->assertMatchesRegularExpression( '/name="clubhouse_demo_active"[^>]*checked/', $html );
	}
```

Note: `Setup_Screen::render` reads `$model['demo_active']` via `?? false` (Step 3), so the existing `SetupScreenTest` cases that build a model without that key keep passing.

Add to `tests/php/SetupControllerTest.php`:

```php
	public function test_handle_save_enables_demo_mode_when_checked(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Setup_Controller::handle_save( array( 'clubhouse_demo_active' => '1' ), $storage );
		$this->assertTrue( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}

	public function test_handle_save_disables_demo_mode_when_absent(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		( new Blueworx_Clubhouse_Demo_State( $storage ) )->set( true );
		Blueworx_Clubhouse_Setup_Controller::handle_save( array(), $storage );
		$this->assertFalse( ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on() );
	}
```

- [ ] **Step 2: Run to verify they fail**

Run: `composer test -- --filter "SetupScreenTest|SetupControllerTest"`
Expected: FAIL — no `clubhouse_demo_active` in output; state not set.

- [ ] **Step 3: Render the control in `Setup_Screen`**

In `includes/admin/class-setup-screen.php`, add a private method and call it from `render()` just before the submit button. In `render()`, change:

```php
		$out .= self::visibility_area( $model['inventory'], $model['visibility'] );
		$out .= '<p class="submit">'
```
to:
```php
		$out .= self::visibility_area( $model['inventory'], $model['visibility'] );
		$out .= self::demo_area( (bool) ( $model['demo_active'] ?? false ) );
		$out .= '<p class="submit">'
```

Add the method (near `visibility_area`):

```php
	private static function demo_area( bool $active ): string {
		$checked = $active ? ' checked' : '';
		$out  = '<h2>Demo mode</h2>';
		$out .= '<p class="description">When on, every visitor sees a floating switcher to preview the Base Looks, and the site renders in a demo look. Your saved look is not changed. Only administrators can turn this on or off.</p>';
		$out .= '<label><input type="checkbox" name="clubhouse_demo_active" value="1"' . $checked . '> Enable demo mode for all visitors</label>';
		return $out;
	}
```

- [ ] **Step 4: Persist + expose in `Setup_Controller`**

In `includes/admin/class-setup-controller.php`, inside `handle_save`, before the `Theme_Cache` invalidate (step 6 there), add:

```php
		// Demo mode (site-wide) — checkbox present = on.
		( new Blueworx_Clubhouse_Demo_State( $storage ) )->set( isset( $post['clubhouse_demo_active'] ) );
```

In `build_model`, add the key to the returned array:

```php
			'demo_active' => ( new Blueworx_Clubhouse_Demo_State( $storage ) )->is_on(),
```

- [ ] **Step 5: Run to verify they pass**

Run: `composer test -- --filter "SetupScreenTest|SetupControllerTest"`
Expected: PASS.

- [ ] **Step 6: Full suite + lint**

Run: `composer test`
Expected: PASS.
Run: `composer lint`
Expected: PASS — 0 errors.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/class-setup-screen.php includes/admin/class-setup-controller.php tests/php/SetupScreenTest.php tests/php/SetupControllerTest.php
git commit -m "feat: Demo mode on/off control on the Clubhouse Setup screen"
```

---

## Task 4: Version bump, changelog, deployment zip

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (header `Version:` + `BLUEWORX_LABS_CLUBHOUSE_VERSION`), `package.json`, `CHANGELOG.md`

**Interfaces:** none.

- [ ] **Step 1: Bump the version**

In `blueworx-labs-clubhouse.php`, change the header `Version:` from `0.17.0` to `0.18.0`, and `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.17.0' )` → `'0.18.0'`. In `package.json`, set `"version": "0.18.0"`.

- [ ] **Step 2: Changelog entry**

Prepend to `CHANGELOG.md` (above `## [0.17.0]`):

```markdown
## [0.18.0] - 2026-07-13

### Changed

- **Demo mode is now site-wide.** Instead of a private per-admin preview, an administrator turns Demo mode on or off for the whole site (from the ⚡ admin-bar toggle — which now works in the front end and in wp-admin — or from a new control on **Clubhouse → Setup**). While it is on, every visitor sees the floating look switcher and can click through the Base Looks themselves (their own choice, held in their browser); the club's saved look is never changed. Only administrators can turn it on or off.
```

- [ ] **Step 3: Verify version consistency**

Run: `git grep -n "0\.17\.0" blueworx-labs-clubhouse.php package.json`
Expected: no matches.

- [ ] **Step 4: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.18.0 (site-wide Demo mode)"
```

- [ ] **Step 5: Rebuild the deployment zip (bsdtar, forward slashes)**

Stage the runtime files (`blueworx-labs-clubhouse.php`, `includes/`, `templates/`, `assets/`) to `dist/blueworx-labs-clubhouse/`, remove the old parent-folder zip, and build:

```bash
cd "c:/Users/LukeMcfarland/Documents/GitHub/blueworx_labs_clubhouse"
SLUG=blueworx-labs-clubhouse
rm -rf dist && mkdir -p "dist/$SLUG"
cp "$SLUG.php" "dist/$SLUG/" && cp -r includes templates assets "dist/$SLUG/"
rm -f "../$SLUG.zip"
/c/Windows/System32/tar.exe -a -c -f "../$SLUG.zip" -C dist "$SLUG"
```

- [ ] **Step 6: Verify the zip**

Run: `unzip -l "../blueworx-labs-clubhouse.zip" | grep -E "class-demo-state.php|/blueworx-labs-clubhouse.php$"` and `unzip -l "../blueworx-labs-clubhouse.zip" | grep '\\\\' || echo forward-slashes-OK`
Expected: `class-demo-state.php` present, entry point at `blueworx-labs-clubhouse/blueworx-labs-clubhouse.php`, all forward slashes. Then `rm -rf dist`.

---

## Manual WP smoke (runtime-only — owed, not unit-testable)

1. As an admin on the front end: admin bar shows **⚡ Demo mode: Off** → click → it flips to **On** and the switcher appears for everyone.
2. In wp-admin: the same admin-bar toggle flips the state and redirects back to the admin page (verifies the backend toggle).
3. **Clubhouse → Setup**: the Demo mode checkbox reflects and sets the same state on save.
4. Open the site logged out (or as a subscriber): while on, the switcher is visible and clicking a look re-skins that browser only; there is **no** "Turn off demo mode" control for them, and hitting `admin-post.php?action=clubhouse_demo_toggle` without a valid nonce/cap does nothing.
5. Turn demo off (admin bar, switcher control, or Setup) → every viewer returns to the saved look; switcher gone.
6. Confirm the club's saved Base Look in Setup is unchanged throughout.

---

## Self-Review

**Spec coverage:**
- Site-wide stored state, admin-only writes → Task 1 (`Demo_State`) + Task 2 (`apply_toggle`/`handle_toggle` cap+nonce) + Task 3 (Setup save). ✓
- Everyone sees switcher + re-skin when on → Task 2 (`enqueue`/`render_switcher`/`look_slug` gate on `is_on()`). ✓
- Anyone switches looks (per-viewer cookie) → Task 2 (`resolve_look_slug` cap-free; `demo.js` look cookie). ✓
- Deactivate admin-only → Task 2 (`switcher_html` deactivate URL only when `can_manage`). ✓
- Admin-bar toggle works front-end + backend → Task 2 (nonce'd `admin-post` link). ✓
- Setup screen control → Task 3. ✓
- Remove old `is_active`/`COOKIE_FLAG` → Task 2. ✓
- 0.18.0 + changelog + zip → Task 4. ✓

**Placeholder scan:** none; every code step shows complete code. Two steps ask the implementer to read an existing test's model-construction pattern before adding a case (Task 3 Step 1) — that is a deliberate instruction to match the existing shape, not a placeholder.

**Type consistency:** `switcher_html( array, ?string, ?string )` used consistently across Task 2 producer + tests. `Demo_State::KEY`/`is_on`/`set`, `Demo_Controller::TOGGLE_ACTION`/`is_on`/`look_slug`/`apply_toggle` match across tasks. `demo_active` model key + `clubhouse_demo_active` POST field consistent across Task 3. Removed symbols (`COOKIE_FLAG`, `is_active`) have no remaining references after Task 2 (FrontendTest updated in the same task).
