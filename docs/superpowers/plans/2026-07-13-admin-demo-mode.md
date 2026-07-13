# Admin Demo Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give a logged-in admin a per-admin, ephemeral front-end switcher that re-skins the live site through every registered Base Look — so a prospective club owner can pick one — without changing the club's saved look or affecting any public visitor.

**Architecture:** A pure decision/HTML class (`Demo_Mode`) mirrors the existing `Setup_Progress`/`Setup_Screen` pure-vs-glue split. A thin WP-coupled controller (`Demo_Controller`) reads a per-admin cookie + capability, hooks the admin bar / footer / enqueue, and feeds an override slug to `Frontend::context()`. Switching is reload-to-re-render: cookie set in JS → server picks the look → the whole existing render path emits that look's CSS + `:root` + body. Cookies are the only state; nothing is persisted to the club's settings.

**Tech Stack:** PHP 8.2, WordPress plugin (hooks: `admin_bar_menu`, `wp_footer`, `wp_enqueue_scripts`), PHPUnit 9 with the repo's dependency-free WP-function shim (`tests/php/wp-stubs.php`), vanilla JS, hand-written CSS. No new dependencies.

## Global Constraints

- **PHP floor:** `declare(strict_types=1);` and `if ( ! defined( 'ABSPATH' ) ) { exit; }` at the top of every PHP file. Requires PHP 8.2.
- **Class prefix:** every class is `Blueworx_Clubhouse_*`; file names are `class-<kebab>.php` / `interface-<kebab>.php`.
- **Indentation:** tabs, not spaces (matches `phpcs.xml.dist`).
- **Pure vs glue separation:** pure logic lives in `includes/frontend/` (no WordPress calls); WordPress-coupled glue lives in `includes/admin/`. Pure classes escape their own output with `htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' )` (see `Setup_Screen::esc`), NOT WP `esc_*`.
- **Cap gate:** the override and all demo UI are honoured ONLY when `current_user_can( 'manage_options' )`. A forged cookie from a non-admin must do nothing.
- **Non-persistence:** never call `Base_Look_Registry::set_active()` from the demo path. The club's saved `active_base_look` option must be untouched.
- **Registry-driven:** the switcher enumerates looks from `registry->all()` / `array_keys( registry->all() )` — never a hardcoded list of three.
- **Runtime loader:** new runtime classes must be `require_once`'d in BOTH `includes/bootstrap.php` (pure classes) or the main plugin file (glue), AND `tests/php/bootstrap.php`, in dependency order.
- **Versioning:** minor bump to `0.17.0` in `blueworx-labs-clubhouse.php` (header `Version:` + the `BLUEWORX_LABS_CLUBHOUSE_VERSION` constant if present) and `package.json`, with a matching `CHANGELOG.md` entry.
- **Test command:** `composer test` (PHPUnit). **Lint command:** `composer lint` (PHPCS). Both must be green before the final commit.

---

## File Structure

- Create `includes/frontend/class-demo-mode.php` — pure decisions + switcher HTML.
- Create `includes/admin/class-demo-controller.php` — WP glue: cookie/cap reads, hooks, admin-bar node, footer switcher, enqueue.
- Create `assets/js/demo.js` — sets/clears cookies + reloads.
- Create `assets/css/demo.css` — fixed switcher bar styling (neutral chrome, does not consume accent tokens).
- Create `tests/php/DemoModeTest.php` — pure tests for decisions.
- Create `tests/php/DemoModeSwitcherTest.php` — pure tests for switcher HTML.
- Create `tests/php/DemoControllerTest.php` — shim tests for the glue.
- Modify `includes/frontend/class-frontend.php` — `context()` applies the demo override; `Frontend::register()` also wires `Setup_Controller::register()` and `Demo_Controller::register()`.
- Modify `includes/bootstrap.php` — require `class-demo-mode.php` (pure).
- Modify `blueworx-labs-clubhouse.php` — require `class-demo-controller.php`; bump version.
- Modify `tests/php/bootstrap.php` — require both new runtime classes.
- Modify `package.json` + `CHANGELOG.md` — version + changelog.

> **Note (in-scope fix):** `Blueworx_Clubhouse_Setup_Controller::register()` is defined but never called anywhere in the runtime today, so the Clubhouse → Setup menu never mounts (a latent Phase 2 wiring gap; its "manual WP smoke owed" note means it was never caught). Task 4 wires it up alongside `Demo_Controller::register()` in the same spot, so the admin surfaces this feature depends on actually load.

---

## Task 1: `Demo_Mode` decisions (cap gate + look resolution)

**Files:**
- Create: `includes/frontend/class-demo-mode.php`
- Modify: `includes/bootstrap.php` (add require), `tests/php/bootstrap.php` (add require)
- Test: `tests/php/DemoModeTest.php`

**Interfaces:**
- Consumes: nothing (pure).
- Produces:
  - `Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG` = `'clubhouse_demo'`
  - `Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK` = `'clubhouse_demo_look'`
  - `Blueworx_Clubhouse_Demo_Mode::is_active( bool $can_manage, ?string $flag_cookie ): bool`
  - `Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( bool $active, ?string $look_cookie, array $available_slugs ): ?string`

- [ ] **Step 1: Add the require lines so the new class loads**

In `includes/bootstrap.php`, immediately after the `require_once __DIR__ . '/frontend/...'` block is NOT present there (frontend classes are required from the main file / test bootstrap). Instead add to `includes/bootstrap.php` at the end of the file (pure classes only):

```php
// Frontend (pure)
require_once __DIR__ . '/frontend/class-demo-mode.php';
```

In `tests/php/bootstrap.php`, after the existing `require_once ... /includes/frontend/class-frontend.php';` line, add:

```php
require_once dirname( __DIR__, 2 ) . '/includes/frontend/class-demo-mode.php';
```

(The class-demo-mode require in `bootstrap.php` already covers the runtime; this test-bootstrap line is belt-and-braces and harmless because the class file is `require_once`.)

- [ ] **Step 2: Write the failing test**

Create `tests/php/DemoModeTest.php`:

```php
<?php
// tests/php/DemoModeTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoModeTest extends TestCase {

	public function test_cookie_name_constants(): void {
		$this->assertSame( 'clubhouse_demo', Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG );
		$this->assertSame( 'clubhouse_demo_look', Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK );
	}

	public function test_is_active_requires_both_capability_and_flag(): void {
		$this->assertTrue( Blueworx_Clubhouse_Demo_Mode::is_active( true, '1' ) );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::is_active( false, '1' ), 'non-admin cookie must not activate' );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::is_active( true, null ), 'no flag cookie = inactive' );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::is_active( true, '0' ), 'flag must be "1"' );
		$this->assertFalse( Blueworx_Clubhouse_Demo_Mode::is_active( true, 'yes' ) );
	}

	public function test_resolve_returns_null_when_inactive(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( false, 'floodlight', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_returns_null_without_look_cookie(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, null, array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_returns_known_slug(): void {
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, 'floodlight', array( 'court-side', 'floodlight' ) ) );
	}

	public function test_resolve_unknown_or_stale_slug_falls_through_to_null(): void {
		$this->assertNull( Blueworx_Clubhouse_Demo_Mode::resolve_look_slug( true, 'retired-look', array( 'court-side', 'floodlight' ) ) );
	}
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `composer test -- --filter DemoModeTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Demo_Mode" not found`.

- [ ] **Step 4: Write minimal implementation**

Create `includes/frontend/class-demo-mode.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure decisions and markup for admin Demo mode: whether the current request is
 * a live demo, which registered Base Look to render, and the floating switcher
 * bar's HTML. No WordPress calls, no persistence, no cookie reads — the
 * controller supplies the capability flag and cookie values. Output is escaped
 * with htmlspecialchars (matches Setup_Screen), so this stays skin-agnostic and
 * WP-free.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Mode {

	public const COOKIE_FLAG = 'clubhouse_demo';
	public const COOKIE_LOOK = 'clubhouse_demo_look';

	/** Demo is live only for a capable admin whose on/off cookie is set. */
	public static function is_active( bool $can_manage, ?string $flag_cookie ): bool {
		return $can_manage && '1' === $flag_cookie;
	}

	/**
	 * The Base Look slug to render in place of the saved active look, or null to
	 * fall through to the saved look. Unknown/stale slugs fall through (never fatal).
	 *
	 * @param array<int,string> $available_slugs
	 */
	public static function resolve_look_slug( bool $active, ?string $look_cookie, array $available_slugs ): ?string {
		if ( ! $active || null === $look_cookie ) {
			return null;
		}
		return in_array( $look_cookie, $available_slugs, true ) ? $look_cookie : null;
	}
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter DemoModeTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/frontend/class-demo-mode.php includes/bootstrap.php tests/php/bootstrap.php tests/php/DemoModeTest.php
git commit -m "feat: Demo_Mode cap-gate + look resolution (pure)"
```

---

## Task 2: `Demo_Mode::switcher_html` (floating bar markup)

**Files:**
- Modify: `includes/frontend/class-demo-mode.php`
- Test: `tests/php/DemoModeSwitcherTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Demo_Mode` from Task 1.
- Produces: `Blueworx_Clubhouse_Demo_Mode::switcher_html( array $looks, ?string $current_slug ): string` where each `$looks` entry is `array{slug:string,name:string}`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/DemoModeSwitcherTest.php`:

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

	public function test_renders_one_control_per_look(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side' );
		$this->assertSame( 3, substr_count( $html, 'data-clubhouse-look="' ) );
		$this->assertStringContainsString( 'data-clubhouse-look="floodlight"', $html );
	}

	public function test_current_look_is_flagged(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'floodlight' );
		// The current control carries is-current + aria-pressed="true".
		$this->assertMatchesRegularExpression(
			'/data-clubhouse-look="floodlight"[^>]*aria-pressed="true"/',
			$html
		);
		$this->assertSame( 1, substr_count( $html, 'aria-pressed="true"' ), 'exactly one current control' );
	}

	public function test_includes_exit_control(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side' );
		$this->assertStringContainsString( 'data-clubhouse-demo-exit', $html );
	}

	public function test_escapes_dynamic_text(): void {
		$looks = array( array( 'slug' => 'x"y', 'name' => '<b>Hack</b>' ) );
		$html  = Blueworx_Clubhouse_Demo_Mode::switcher_html( $looks, null );
		$this->assertStringNotContainsString( '<b>Hack</b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;Hack&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'x&quot;y', $html );
	}

	public function test_skin_agnostic_no_colour_literals(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side' );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html, 'switcher must not hardcode colours' );
		$this->assertStringNotContainsString( 'var(--color-accent', $html, 'chrome must not consume accent tokens' );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter DemoModeSwitcherTest`
Expected: FAIL — `Error: Call to undefined method ...::switcher_html()`.

- [ ] **Step 3: Add the implementation**

In `includes/frontend/class-demo-mode.php`, add a private `esc` helper and the `switcher_html` method inside the class (after `resolve_look_slug`):

```php
	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Floating admin-only switcher bar. Neutral chrome — styled by demo.css, no
	 * colour literals, no accent tokens. Each control carries the look slug for
	 * demo.js; the current look is flagged for both styling and a11y.
	 *
	 * @param array<int,array{slug:string,name:string}> $looks
	 */
	public static function switcher_html( array $looks, ?string $current_slug ): string {
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
		$out .= '<button type="button" class="clubhouse-demo__exit" data-clubhouse-demo-exit>Exit demo</button>';
		$out .= '</div>';
		return $out;
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter DemoModeSwitcherTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/frontend/class-demo-mode.php tests/php/DemoModeSwitcherTest.php
git commit -m "feat: Demo_Mode switcher bar HTML (pure, escaped, skin-agnostic)"
```

---

## Task 3: `Demo_Controller` glue + front-end assets

**Files:**
- Create: `includes/admin/class-demo-controller.php`
- Create: `assets/js/demo.js`
- Create: `assets/css/demo.css`
- Modify: `blueworx-labs-clubhouse.php` (require the controller), `tests/php/bootstrap.php` (require the controller)
- Test: `tests/php/DemoControllerTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Demo_Mode` (Task 1/2), `Blueworx_Clubhouse_Base_Look_Registry`.
- Produces:
  - `Blueworx_Clubhouse_Demo_Controller::CAPABILITY` = `'manage_options'`
  - `Blueworx_Clubhouse_Demo_Controller::register(): void`
  - `Blueworx_Clubhouse_Demo_Controller::is_active(): bool`
  - `Blueworx_Clubhouse_Demo_Controller::look_slug( Blueworx_Clubhouse_Base_Look_Registry $registry ): ?string`
  - `Blueworx_Clubhouse_Demo_Controller::enqueue(): void`
  - `Blueworx_Clubhouse_Demo_Controller::admin_bar_node( $wp_admin_bar ): void`
  - `Blueworx_Clubhouse_Demo_Controller::render_switcher(): void`

- [ ] **Step 1: Add the require lines**

In `blueworx-labs-clubhouse.php`, immediately after the existing `require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-setup-controller.php';` line, add:

```php
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-demo-controller.php';
```

In `tests/php/bootstrap.php`, immediately after the existing `require_once dirname( __DIR__, 2 ) . '/includes/admin/class-setup-controller.php';` line, add:

```php
require_once dirname( __DIR__, 2 ) . '/includes/admin/class-demo-controller.php';
```

- [ ] **Step 2: Write the failing test**

Create `tests/php/DemoControllerTest.php`:

```php
<?php
// tests/php/DemoControllerTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] );
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
	}

	protected function tearDown(): void {
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] );
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
	}

	public function test_register_hooks_admin_bar_footer_and_enqueue(): void {
		Blueworx_Clubhouse_Demo_Controller::register();
		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'admin_bar_menu', $actions );
		$this->assertContains( 'wp_footer', $actions );
		$this->assertContains( 'wp_enqueue_scripts', $actions );
	}

	public function test_is_active_false_without_flag_cookie(): void {
		// current_user_can stub returns true (admin), but no flag cookie is set.
		$this->assertFalse( Blueworx_Clubhouse_Demo_Controller::is_active() );
	}

	public function test_is_active_true_for_admin_with_flag_cookie(): void {
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] = '1';
		$this->assertTrue( Blueworx_Clubhouse_Demo_Controller::is_active() );
	}

	public function test_look_slug_returns_known_cookie_look_when_active(): void {
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] = '1';
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'floodlight';
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ) );
	}

	public function test_look_slug_null_when_flag_absent(): void {
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'floodlight';
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertNull( Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ) );
	}

	public function test_look_slug_null_for_unknown_look(): void {
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] = '1';
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'not-a-look';
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertNull( Blueworx_Clubhouse_Demo_Controller::look_slug( $registry ) );
	}

	public function test_enqueue_registers_demo_assets_for_admin(): void {
		Blueworx_Clubhouse_Demo_Controller::enqueue();
		$styles  = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'wp_enqueue_style' ) );
		$scripts = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'wp_enqueue_script' ) );
		$this->assertContains( 'clubhouse-demo', $styles );
		$this->assertContains( 'clubhouse-demo', $scripts );
	}
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `composer test -- --filter DemoControllerTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Demo_Controller" not found`.

- [ ] **Step 4: Write minimal implementation**

Create `includes/admin/class-demo-controller.php`:

```php
<?php
// includes/admin/class-demo-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress-coupled glue for admin Demo mode. Reads the per-admin cookie +
 * capability, exposes the effective demo look slug to Frontend::context(), and
 * renders the admin-bar toggle + floating switcher on the front end. All
 * decisions and markup live in the pure Blueworx_Clubhouse_Demo_Mode; this class
 * only touches WordPress. Never persists — the club's saved look is untouched.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Controller {

	public const CAPABILITY = 'manage_options';

	public static function register(): void {
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar_node' ), 100 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_footer', array( self::class, 'render_switcher' ) );
	}

	private static function can_manage(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( self::CAPABILITY );
	}

	private static function cookie( string $name ): ?string {
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return null;
		}
		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.NoNonce -- read-only per-admin UI preference, cap-gated below.
	}

	public static function is_active(): bool {
		return Blueworx_Clubhouse_Demo_Mode::is_active( self::can_manage(), self::cookie( Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ) );
	}

	public static function look_slug( Blueworx_Clubhouse_Base_Look_Registry $registry ): ?string {
		return Blueworx_Clubhouse_Demo_Mode::resolve_look_slug(
			self::is_active(),
			self::cookie( Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ),
			array_keys( $registry->all() )
		);
	}

	public static function enqueue(): void {
		if ( ! self::can_manage() ) {
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
		$on    = self::is_active();
		$label = $on ? 'Demo mode: On' : 'Demo mode: Off';
		$wp_admin_bar->add_node( array(
			'id'    => 'clubhouse-demo-toggle',
			'title' => '⚡ ' . $label,
			'href'  => '#',
			'meta'  => array( 'class' => $on ? 'clubhouse-demo-on' : 'clubhouse-demo-off' ),
		) );
	}

	public static function render_switcher(): void {
		if ( ! self::is_active() ) {
			return;
		}
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		$current  = self::look_slug( $registry ) ?? ( $registry->active() ? $registry->active()->slug() : null );
		$looks    = array();
		foreach ( $registry->all() as $look ) {
			$looks[] = array( 'slug' => $look->slug(), 'name' => $look->name() );
		}
		echo Blueworx_Clubhouse_Demo_Mode::switcher_html( $looks, $current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Demo_Mode escapes all dynamic text.
	}
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter DemoControllerTest`
Expected: PASS (7 tests).

- [ ] **Step 6: Create the front-end assets**

Create `assets/js/demo.js`:

```js
/* Blueworx Clubhouse — admin Demo mode. Per-admin, cookie-driven, reload to
 * re-render. No dependencies. Server honours cookies only for capable admins. */
( function () {
	'use strict';

	var FLAG = 'clubhouse_demo';
	var LOOK = 'clubhouse_demo_look';

	function setCookie( name, value ) {
		document.cookie = name + '=' + encodeURIComponent( value ) + '; path=/; SameSite=Lax';
	}
	function clearCookie( name ) {
		document.cookie = name + '=; path=/; Max-Age=0; SameSite=Lax';
	}
	function getCookie( name ) {
		var m = document.cookie.match( '(?:^|; )' + name + '=([^;]*)' );
		return m ? decodeURIComponent( m[ 1 ] ) : '';
	}

	// Admin-bar toggle: flip the on/off flag and reload.
	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest( '#wp-admin-bar-clubhouse-demo-toggle a, #wp-admin-bar-clubhouse-demo-toggle' );
		if ( toggle ) {
			e.preventDefault();
			if ( '1' === getCookie( FLAG ) ) {
				clearCookie( FLAG );
			} else {
				setCookie( FLAG, '1' );
			}
			window.location.reload();
			return;
		}

		// Switcher: choose a look.
		var look = e.target.closest( '[data-clubhouse-look]' );
		if ( look ) {
			e.preventDefault();
			setCookie( LOOK, look.getAttribute( 'data-clubhouse-look' ) );
			window.location.reload();
			return;
		}

		// Switcher: exit demo (clear both cookies).
		var exit = e.target.closest( '[data-clubhouse-demo-exit]' );
		if ( exit ) {
			e.preventDefault();
			clearCookie( FLAG );
			clearCookie( LOOK );
			window.location.reload();
		}
	} );
}() );
```

Create `assets/css/demo.css`:

```css
/* Blueworx Clubhouse — admin Demo mode switcher. Neutral tooling chrome:
   deliberately does NOT consume the club's accent/design tokens so it reads as
   an overlay, not part of the site. Admin-only, never served to visitors. */
.clubhouse-demo {
	position: fixed;
	left: 50%;
	bottom: 16px;
	transform: translateX( -50% );
	z-index: 99999;
	display: flex;
	align-items: center;
	gap: 10px;
	max-width: calc( 100vw - 24px );
	flex-wrap: wrap;
	justify-content: center;
	padding: 8px 12px;
	border-radius: 10px;
	background: #1e1e1e;
	color: #fff;
	box-shadow: 0 6px 24px rgba( 0, 0, 0, 0.35 );
	font: 500 13px/1.2 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
.clubhouse-demo__title {
	font-weight: 700;
	opacity: 0.8;
	white-space: nowrap;
}
.clubhouse-demo__looks {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}
.clubhouse-demo__look,
.clubhouse-demo__exit {
	appearance: none;
	cursor: pointer;
	border: 1px solid rgba( 255, 255, 255, 0.25 );
	border-radius: 999px;
	padding: 6px 12px;
	min-height: 32px;
	background: transparent;
	color: inherit;
	font: inherit;
}
.clubhouse-demo__look.is-current {
	background: #fff;
	color: #1e1e1e;
	border-color: #fff;
}
.clubhouse-demo__exit {
	margin-left: 4px;
	opacity: 0.8;
}
.clubhouse-demo__look:focus-visible,
.clubhouse-demo__exit:focus-visible {
	outline: 2px solid #fff;
	outline-offset: 2px;
}
```

- [ ] **Step 7: Commit**

```bash
git add includes/admin/class-demo-controller.php assets/js/demo.js assets/css/demo.css blueworx-labs-clubhouse.php tests/php/bootstrap.php tests/php/DemoControllerTest.php
git commit -m "feat: Demo_Controller glue + switcher assets"
```

---

## Task 4: Wire the demo override into rendering + fix admin controller wiring

**Files:**
- Modify: `includes/frontend/class-frontend.php` (`context()` override + `register()` wires both controllers)
- Test: `tests/php/FrontendTest.php` (add demo-override tests)

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Demo_Controller::look_slug()` (Task 3), `Blueworx_Clubhouse_Base_Look_Registry` (existing).
- Produces: no new public surface — `Frontend::context()->look` now reflects the demo override when active.

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/FrontendTest.php` (new methods inside the existing class). These drive `context()` indirectly through the public `enqueue_specs`/registry path is not enough — instead assert on the resolved look via a new tiny public accessor. Add the accessor test first:

```php
	public function test_register_also_wires_setup_and_demo_controllers(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Frontend::register();
		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'admin_menu', $actions, 'Setup menu must be wired' );
		$this->assertContains( 'admin_bar_menu', $actions, 'Demo admin-bar toggle must be wired' );
		$this->assertContains( 'wp_footer', $actions, 'Demo switcher must be wired' );
	}

	public function test_active_look_slug_reflects_demo_override_for_admin(): void {
		wp_stub_reset();
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ] = '1';
		$_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] = 'floodlight';
		$this->assertSame( 'floodlight', Blueworx_Clubhouse_Frontend::active_look_slug() );
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ], $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
	}

	public function test_active_look_slug_is_saved_look_without_demo(): void {
		wp_stub_reset();
		unset( $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_FLAG ], $_COOKIE[ Blueworx_Clubhouse_Demo_Mode::COOKIE_LOOK ] );
		// No saved look → registry falls back to first registered (Court Side).
		$this->assertSame( 'court-side', Blueworx_Clubhouse_Frontend::active_look_slug() );
	}
```

> Note: `active_look_slug()` is a new public accessor added in Step 3 so the override is testable without `template_include` — `context()` itself instantiates `Options_Storage` and is exercised transitively.

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test -- --filter FrontendTest`
Expected: FAIL — `test_register_also_wires...` fails (`admin_bar_menu` not present) and `active_look_slug` is undefined.

- [ ] **Step 3: Apply the implementation**

In `includes/frontend/class-frontend.php`, update `register()` to also wire both controllers:

```php
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_rewrites' ) );
		add_action( 'init', array( Blueworx_Clubhouse_Collection_Types::class, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_filter( 'template_include', array( self::class, 'filter_template' ) );
		add_filter( 'wp_resource_hints', array( self::class, 'resource_hints' ), 10, 2 );
		Blueworx_Clubhouse_Setup_Controller::register();
		Blueworx_Clubhouse_Demo_Controller::register();
	}
```

Update `context()` so the look honours the demo override:

```php
	private static function context(): Blueworx_Clubhouse_Clubhouse_Context {
		$storage    = new Blueworx_Clubhouse_Options_Storage();
		$registry   = self::registry( $storage );
		$demo_slug  = Blueworx_Clubhouse_Demo_Controller::look_slug( $registry );
		$look       = null !== $demo_slug ? $registry->get( $demo_slug ) : $registry->active();
		return new Blueworx_Clubhouse_Clubhouse_Context(
			$look,
			new Blueworx_Clubhouse_Branding( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage ),
			new Blueworx_Clubhouse_Theme_Cache( $storage ),
			new Blueworx_Clubhouse_WP_Collections(),
			$registry
		);
	}
```

Add the public accessor after `context()`:

```php
	/** The Base Look slug this request will render (demo override or saved active). */
	public static function active_look_slug(): ?string {
		$look = self::context()->look;
		return null === $look ? null : $look->slug();
	}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer test -- --filter FrontendTest`
Expected: PASS (existing + 3 new).

- [ ] **Step 5: Run the full suite + lint**

Run: `composer test`
Expected: PASS — all tests green (existing 238 + new).

Run: `composer lint`
Expected: PASS — 0 errors. (If PHPCS flags the `$_COOKIE` reads, confirm the `phpcs:ignore` comment from Task 3 is on the same line as the access; do not broaden the ignore.)

- [ ] **Step 6: Commit**

```bash
git add includes/frontend/class-frontend.php tests/php/FrontendTest.php
git commit -m "feat: apply demo look override in context; wire Setup + Demo controllers"
```

---

## Task 5: Version bump, changelog, deployment zip

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (header `Version:` + version constant), `package.json`, `CHANGELOG.md`

**Interfaces:** none.

- [ ] **Step 1: Bump the plugin version**

In `blueworx-labs-clubhouse.php`, change the header `Version:` from `0.16.1` to `0.17.0`, and update the `BLUEWORX_LABS_CLUBHOUSE_VERSION` constant to `'0.17.0'` if it is defined in that file. In `package.json`, set `"version": "0.17.0"`.

- [ ] **Step 2: Add the changelog entry**

Prepend a new entry to `CHANGELOG.md` (match the existing format/heading style already in the file):

```markdown
## 0.17.0 — 2026-07-13

### Added
- **Admin Demo mode.** A per-admin "⚡ Demo mode" toggle in the front-end admin bar reveals a floating look switcher listing every registered Base Look, so an admin can walk a prospective club owner through each look live on the real site. Switching is ephemeral (per-admin cookie) — the club's saved look, accent, and content are never changed, and public visitors always see the saved look.

### Fixed
- The **Clubhouse → Setup** admin menu is now actually mounted (`Setup_Controller::register()` was defined but never called from the plugin's init).
```

- [ ] **Step 3: Verify the version is consistent**

Run: `git grep -n "0.16.1"`
Expected: no matches in `blueworx-labs-clubhouse.php` / `package.json` (changelog history may still reference older versions — that's fine).

- [ ] **Step 4: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.17.0 (admin Demo mode)"
```

- [ ] **Step 5: Build the deployment zip (bsdtar, forward slashes)**

Stage the runtime files to a `dist/blueworx-labs-clubhouse/` folder (mirror the existing v0.16.1 zip's runtime contents: `blueworx-labs-clubhouse.php`, `includes/`, `templates/`, `assets/` — including the new `assets/js/demo.js` and `assets/css/demo.css` — plus `readme`/license as the prior zip included; EXCLUDE `tests/`, `docs/`, `preview/`, `vendor/`, `node_modules/`, `composer.*`, `phpcs.xml.dist`, `phpunit.xml.dist`, `playwright.config.js`, `.github/`). Remove the older parent-folder zip first, then build with the System32 bsdtar:

```bash
rm -f "/c/Users/LukeMcfarland/Documents/GitHub/blueworx-labs-clubhouse.zip"
/c/Windows/System32/tar.exe -a -c -f "../blueworx-labs-clubhouse.zip" -C dist blueworx-labs-clubhouse
```

- [ ] **Step 6: Verify the zip entries use forward slashes, one level deep**

Run: `unzip -l "/c/Users/LukeMcfarland/Documents/GitHub/blueworx-labs-clubhouse.zip"`
Expected: every entry reads `blueworx-labs-clubhouse/...` with `/` separators (never `\`), the main `.php` at `blueworx-labs-clubhouse/blueworx-labs-clubhouse.php`, and `assets/js/demo.js` + `assets/css/demo.css` present. If any `\` appears, rebuild with bsdtar — never `Compress-Archive`.

---

## Manual WP smoke (runtime-only — owed, not unit-testable)

Cookies, the admin bar, and the live 404/enqueue path can't be driven by PHPUnit. After deploying the zip to a test WordPress install:

1. Log in as an admin, visit the Clubhouse home page → the admin bar shows **⚡ Demo mode: Off**.
2. Click it → page reloads, the floating switcher appears at the bottom, the current look is highlighted, and the admin bar reads **Demo mode: On**.
3. Click each look in turn → the whole page re-skins to that look (fonts, colours, component style) on reload; navigate to another page (e.g. `/about/`) → the demo look persists.
4. Open **Clubhouse → Setup** → confirm the saved Base Look is **unchanged** (still whatever it was before the demo).
5. Click **Exit demo** (or toggle Demo mode Off) → the site returns to the saved look; the switcher disappears.
6. Open the site in a private window (logged out) or as a non-admin user → the saved look renders, no switcher, and manually setting a `clubhouse_demo=1` cookie has no effect.
7. Confirm **Clubhouse → Setup** now appears in the admin menu at all (verifies the wiring fix).

---

## Self-Review

**Spec coverage:**
- Admin-bar toggle, per-admin → Task 3 (`admin_bar_node`, `demo.js` toggle) + Task 4 (wiring). ✓
- Floating switcher listing every registered look → Task 2 (`switcher_html`) + Task 3 (`render_switcher` enumerates `registry->all()`). ✓
- Ephemeral, non-persistent switching → Task 3/4 (cookie-only, `context()` override, no `set_active`). ✓
- Admin-only isolation → Task 1 (`is_active` cap gate), Task 3 (`can_manage` on every entry). ✓
- Registry-driven, not hardcoded to three → Task 3 (`array_keys( registry->all() )`, `foreach registry->all()`). ✓
- Pure core + WP-shim glue tests + manual smoke → Tasks 1–4 + smoke section. ✓
- 0.17.0 bump + changelog + zip → Task 5. ✓

**Placeholder scan:** no TBD/TODO; every code step shows complete code. ✓

**Type consistency:** `COOKIE_FLAG`/`COOKIE_LOOK`, `is_active(bool,?string)`, `resolve_look_slug(bool,?string,array)`, `switcher_html(array,?string)`, `look_slug(Base_Look_Registry)` used identically across Tasks 1–4. Switcher entries are `{slug,name}` in both producer (Task 3) and consumer (Task 2 test). ✓
