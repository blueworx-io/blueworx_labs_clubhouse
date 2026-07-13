# Admin Phase 2 — Clubhouse Setup Config Screen Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the owner-facing **Clubhouse Setup** admin screen — a standard menu-mounted WordPress admin page — that lets an owner pick a Base Look, set branding (accent with look-aware legibility rejection, club name, logo, Facebook, Instagram), toggle page/section visibility, and see a setup progress bar.

**Architecture:** Pure units do the thinking and the markup and are unit-tested WordPress-free: a look-aware accent-legibility helper on `Color_Engine`, `Setup_Progress::compute()` (the 6-item progress), `Setup_Sections::inventory()` (the declarative pages→sections map), and `Setup_Screen::render()` (a pure HTML builder mirroring `Sections`/`Page_Renderer`). A thin WordPress-coupled `Setup_Controller` registers the menu, enqueues the media picker, handles the POST (nonce + capability, sanitise, persist through the existing `Branding`/`Visibility`/`Base_Look_Registry` setters, `Theme_Cache::invalidate()`), and echoes `Setup_Screen`. Glue is tested with the `tests/php/wp-stubs.php` guarded recorders. No Dashboard takeover and no role lockdown — those are Phase 4.

**Tech Stack:** PHP 8.2, PHPUnit 11 (WP-free with guarded shims), PHP_CodeSniffer (`composer lint`). WordPress admin (Settings-API-style page, `wp.media` for the logo). Vanilla admin JS/CSS.

## Global Constraints

- PHP requirement: `Requires PHP: 8.2`. Every PHP file starts with `declare(strict_types=1);` and (runtime files) `if ( ! defined( 'ABSPATH' ) ) { exit; }` after it.
- Class naming: `Blueworx_Clubhouse_<Name>`; file naming `class-<kebab-name>.php`. New admin pure/glue classes live under `includes/admin/`.
- **Pure vs glue boundary (hard rule):** `Color_Engine`, `Setup_Progress`, `Setup_Sections`, `Setup_Screen` MUST NOT call any WordPress function. All WP calls (`add_menu_page`, `wp_nonce_field`, `current_user_can`, `wp_enqueue_*`, `sanitize_*`, `esc_*`, `check_admin_referer`, `wp_get_attachment_*`) live only in `Setup_Controller`. Pure classes receive already-safe strings (e.g. the nonce field HTML) as inputs.
- **All user-facing output is escaped** in the pure renderer with `htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' )` — the same idiom `Sections`/`Page_Renderer` use.
- **Persistence only through existing setters:** `Blueworx_Clubhouse_Branding::set_accent/set_club_name/set_logo/set_facebook_url/set_instagram_url`; `Blueworx_Clubhouse_Visibility::set_page_visible/set_section_visible`; `Blueworx_Clubhouse_Base_Look_Registry::set_active`.
- **Look-aware accent legibility (Task 1):** an accent is acceptable for the ACTIVE look iff `Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $active_look, $accent )` is true. For a look that paints text on the accent fill (`accent_bears_text() === true`: Court Side, Members' House) this requires BOTH the accent-ink and accent-deep AA checks; for a glow-only look (`false`: Floodlight) it requires only accent-deep (which `derive()` guarantees). WCAG AA threshold is contrast `>= 4.5`.
- Capability for the page this phase: `manage_options` (Phase 4 swaps it for the owner capability). Keep it in ONE named constant.
- Tests WordPress-free; run `./vendor/bin/phpunit`; lint `composer lint`. Both green before the phase is done. Output pristine (`failOnWarning`/`failOnRisky` on).
- Version bump this phase: `0.15.0` → `0.16.0` (minor). Keep `blueworx-labs-clubhouse.php` (header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json` in sync; update `CHANGELOG.md`.
- Commit after every green step (Conventional Commits).
- **Deferred to Phase 3 (do NOT build here):** front-end logo `<img>` rendering and omitting hidden pages from the header nav — both thread resolved data into the pure `shell_header`, one coherent Phase 3 task. Phase 2 STORES the logo and ENFORCES page-hiding at routing only.

---

## File Structure

- `includes/theme/interface-base-look.php` — **modify**: add `accent_bears_text(): bool`.
- `includes/theme/class-color-engine.php` — **modify**: add `accent_deep_is_legible()` + `accent_is_legible_for()`.
- `includes/looks/class-court-side.php`, `class-members-house.php`, `class-floodlight.php` — **modify**: implement `accent_bears_text()`.
- `tests/php/fakes/class-fake-base-look.php` — **modify**: implement `accent_bears_text()`.
- `tests/php/ThemeCacheTest.php` — **modify**: `Clubhouse_Test_Look` implements `accent_bears_text()`.
- `includes/admin/class-setup-progress.php`, `class-setup-sections.php`, `class-setup-screen.php`, `class-setup-controller.php` — **create**.
- `includes/bootstrap.php` — **modify**: require the three pure admin classes.
- `blueworx-labs-clubhouse.php` — **modify**: require the controller; register it on `admin_menu`/`admin_enqueue_scripts`; version bump.
- `includes/frontend/class-frontend.php` — **modify**: enforce page visibility in routing.
- `assets/js/admin-setup.js`, `assets/css/admin-setup.css` — **create**.
- `tests/php/wp-stubs.php` — **modify**: add guarded stubs.
- `tests/php/bootstrap.php` — **modify**: require the controller.
- `tests/php/ColorEngineLegibilityForTest.php`, `SetupProgressTest.php`, `SetupSectionsTest.php`, `SetupScreenTest.php`, `SetupControllerTest.php` — **create**.
- `tests/php/FrontendTest.php` — **modify**: page-visibility routing tests.
- `package.json`, `CHANGELOG.md` — **modify** (final task).

---

## Task 1: Look-aware accent legibility

Extend the Base Look contract to declare whether it paints text on the accent fill, and add a look-aware legibility check. This corrects the Phase-1 gate, which required accent-ink AA for every look and so would wrongly reject bright accents on the glow-only dark look.

**Files:**
- Modify: `includes/theme/interface-base-look.php` (add a method to the interface)
- Modify: `includes/looks/class-court-side.php`, `class-members-house.php`, `class-floodlight.php`
- Modify: `tests/php/fakes/class-fake-base-look.php`
- Modify: `tests/php/ThemeCacheTest.php` (the in-file `Clubhouse_Test_Look`)
- Modify: `includes/theme/class-color-engine.php`
- Test: `tests/php/ColorEngineLegibilityForTest.php` (create)

**Interfaces:**
- Consumes: existing `Color_Engine::accent_is_legible()`, `::derive()`, `::contrast_ratio()`, `::normalize_hex()`; `Base_Look::tokens()`.
- Produces: `Blueworx_Clubhouse_Base_Look::accent_bears_text(): bool`; `Color_Engine::accent_deep_is_legible( string $accent, string $shell_bg ): bool`; `Color_Engine::accent_is_legible_for( Blueworx_Clubhouse_Base_Look $look, string $accent ): bool`. Consumed by `Setup_Progress` (Task 2) and `Setup_Controller` (Task 5).

- [ ] **Step 1: Write the failing test**

Create `tests/php/ColorEngineLegibilityForTest.php`:

```php
<?php
// tests/php/ColorEngineLegibilityForTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ColorEngineLegibilityForTest extends TestCase {

	public function test_text_bearing_look_requires_ink_contrast(): void {
		// #7a7a7a is a mid-luminance grey: on Court Side both candidate inks
		// (near-black shell ink and white) fall just under AA on the fill, so the
		// accent cannot carry legible text -> rejected for a text-bearing look.
		$this->assertFalse(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( new Blueworx_Clubhouse_Court_Side(), '#7a7a7a' )
		);
		// A saturated accent is fine on Court Side.
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( new Blueworx_Clubhouse_Court_Side(), '#7a2f3a' )
		);
	}

	public function test_glow_only_look_accepts_bright_accent(): void {
		// Floodlight never paints text on the accent, so a bright accent that would
		// fail the ink check is still acceptable (accent-deep is AA-guaranteed).
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( new Blueworx_Clubhouse_Floodlight(), '#c6f24e' )
		);
		$this->assertTrue(
			Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( new Blueworx_Clubhouse_Floodlight(), '#f7a70a' )
		);
	}

	public function test_accent_bears_text_flags_match_stylesheets(): void {
		$this->assertTrue( ( new Blueworx_Clubhouse_Court_Side() )->accent_bears_text() );
		$this->assertTrue( ( new Blueworx_Clubhouse_Members_House() )->accent_bears_text() );
		$this->assertFalse( ( new Blueworx_Clubhouse_Floodlight() )->accent_bears_text() );
	}

	public function test_accent_deep_is_legible_true_on_any_shell(): void {
		// derive() guarantees accent-deep clears AA on any shell.
		$this->assertTrue( Blueworx_Clubhouse_Color_Engine::accent_deep_is_legible( '#c6f24e', '#14110b' ) );
		$this->assertTrue( Blueworx_Clubhouse_Color_Engine::accent_deep_is_legible( '#7a7a7a', '#faf8f3' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter ColorEngineLegibilityForTest`
Expected: FAIL — `Call to undefined method Blueworx_Clubhouse_Court_Side::accent_bears_text()` (or `::accent_is_legible_for()`).

- [ ] **Step 3: Add the interface method**

In `includes/theme/interface-base-look.php`, add to the interface (after `stylesheet()`):

```php
	/**
	 * Does this look paint text ON the accent fill (buttons, hero highlight,
	 * ticker label)? If true, an accent must clear AA as ink-on-fill to be
	 * acceptable; glow-only looks (accent spent as ambient light) return false.
	 */
	public function accent_bears_text(): bool;
```

- [ ] **Step 4: Implement it on every Base Look**

In `includes/looks/class-court-side.php` and `includes/looks/class-members-house.php`, add:

```php
	public function accent_bears_text(): bool {
		return true;
	}
```

In `includes/looks/class-floodlight.php`, add:

```php
	public function accent_bears_text(): bool {
		return false;
	}
```

In `tests/php/fakes/class-fake-base-look.php`, add a constructor-independent method (default to text-bearing so existing tests are unaffected):

```php
	public function accent_bears_text(): bool {
		return true;
	}
```

In `tests/php/ThemeCacheTest.php`, add to the in-file `Clubhouse_Test_Look` class:

```php
	public function accent_bears_text(): bool { return true; }
```

- [ ] **Step 5: Add the Color_Engine helpers**

In `includes/theme/class-color-engine.php`, add both methods inside the class (after `accent_is_legible()`):

```php
	/** Is the accent legible as text/marks ON the shell (accent-deep vs bg)? */
	public static function accent_deep_is_legible( string $accent, string $shell_bg ): bool {
		// shell_ink is irrelevant to accent-deep; pass the bg (unused for deep).
		$d = self::derive( $accent, $shell_bg, $shell_bg );
		return self::contrast_ratio( $d['--color-accent-deep'], self::normalize_hex( $shell_bg ) ) >= 4.5;
	}

	/**
	 * Look-aware acceptance: for a look that paints text on the accent fill,
	 * require full legibility (ink + deep); for a glow-only look, require only
	 * accent-deep (the accent never carries text there).
	 */
	public static function accent_is_legible_for( Blueworx_Clubhouse_Base_Look $look, string $accent ): bool {
		$t = $look->tokens();
		if ( $look->accent_bears_text() ) {
			return self::accent_is_legible( $accent, $t['--color-bg'], $t['--color-ink'] );
		}
		return self::accent_deep_is_legible( $accent, $t['--color-bg'] );
	}
```

- [ ] **Step 6: Run the focused test then the full suite**

Run: `./vendor/bin/phpunit --filter ColorEngineLegibilityForTest`
Expected: PASS (4 tests).

Run: `./vendor/bin/phpunit`
Expected: PASS (all — the interface addition is now satisfied by every implementor).

- [ ] **Step 7: Commit**

```bash
git add includes/theme/interface-base-look.php includes/theme/class-color-engine.php includes/looks/ tests/php/fakes/class-fake-base-look.php tests/php/ThemeCacheTest.php tests/php/ColorEngineLegibilityForTest.php
git commit -m "feat: look-aware accent legibility (accent_bears_text + accent_is_legible_for)"
```

---

## Task 2: `Setup_Progress::compute()` (pure)

The progress bar's data: six config items (page content is NOT counted). An item counts when the owner has moved it off its demo default (logo: any non-empty value); the accent additionally must be legible for the active look via the Task 1 helper.

**Files:**
- Create: `includes/admin/class-setup-progress.php`
- Modify: `includes/bootstrap.php` (add a require)
- Test: `tests/php/SetupProgressTest.php`

**Interfaces:**
- Consumes: `Branding` getters; `Base_Look`; `Color_Engine::accent_is_legible_for()` (Task 1).
- Produces: `Blueworx_Clubhouse_Setup_Progress::compute( Blueworx_Clubhouse_Branding $branding, Blueworx_Clubhouse_Base_Look $active_look, bool $look_chosen ): array` → `array{items:array{look:bool,accent:bool,club_name:bool,logo:bool,facebook:bool,instagram:bool},completed:int,total:int}` (total 6). Consumed by `Setup_Screen` (Task 4) and `Setup_Controller` (Task 6).

- [ ] **Step 1: Write the failing test**

Create `tests/php/SetupProgressTest.php`:

```php
<?php
// tests/php/SetupProgressTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupProgressTest extends TestCase {

	private function look(): Blueworx_Clubhouse_Base_Look {
		return new Blueworx_Clubhouse_Court_Side();
	}

	public function test_fresh_defaults_count_zero_and_look_not_chosen(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );

		$this->assertSame( 6, $p['total'] );
		$this->assertSame( 0, $p['completed'] );
		foreach ( $p['items'] as $done ) {
			$this->assertFalse( $done );
		}
	}

	public function test_configured_values_count_toward_completion(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_accent( '#7a2f3a' );                 // legible on Court Side, != default
		$branding->set_club_name( 'Riverside RFC' );
		$branding->set_logo( '42' );
		$branding->set_facebook_url( 'https://facebook.com/riverside' );
		$branding->set_instagram_url( 'https://instagram.com/riverside' );

		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), true );

		foreach ( $p['items'] as $key => $done ) {
			$this->assertTrue( $done, "expected item {$key} to be complete" );
		}
		$this->assertSame( 6, $p['completed'] );
	}

	public function test_illegible_non_default_accent_does_not_count(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_accent( '#7a7a7a' ); // != default, but illegible-as-ink on Court Side
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), true );
		$this->assertFalse( $p['items']['accent'] );
	}

	public function test_default_values_do_not_count(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_club_name( 'ClubHouse' ); // the demo default
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );
		$this->assertFalse( $p['items']['club_name'] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter SetupProgressTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Setup_Progress" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `includes/admin/class-setup-progress.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes the six setup-progress booleans for the Clubhouse Setup screen.
 * Pure. An item counts when its value differs from the plugin's demo default
 * (logo: any non-empty value); the accent must additionally be legible for the
 * active look (look-aware: text-bearing looks need ink+deep, glow-only need deep).
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Progress {

	// Mirror of Blueworx_Clubhouse_Branding::DEFAULTS (kept explicit for the check).
	private const DEMO_ACCENT    = '#c6f24e';
	private const DEMO_CLUB_NAME = 'ClubHouse';
	private const DEMO_FACEBOOK  = 'https://facebook.com/clubhouse';
	private const DEMO_INSTAGRAM = 'https://instagram.com/clubhouse';

	/**
	 * @return array{items:array{look:bool,accent:bool,club_name:bool,logo:bool,facebook:bool,instagram:bool},completed:int,total:int}
	 */
	public static function compute(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Base_Look $active_look,
		bool $look_chosen
	): array {
		$accent = $branding->get_accent();

		$items = array(
			'look'      => $look_chosen,
			'accent'    => self::DEMO_ACCENT !== $accent
				&& Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $active_look, $accent ),
			'club_name' => '' !== $branding->get_club_name() && self::DEMO_CLUB_NAME !== $branding->get_club_name(),
			'logo'      => '' !== $branding->get_logo(),
			'facebook'  => '' !== $branding->get_facebook_url() && self::DEMO_FACEBOOK !== $branding->get_facebook_url(),
			'instagram' => '' !== $branding->get_instagram_url() && self::DEMO_INSTAGRAM !== $branding->get_instagram_url(),
		);

		return array(
			'items'     => $items,
			'completed' => count( array_filter( $items ) ),
			'total'     => count( $items ),
		);
	}
}
```

Add the require in `includes/bootstrap.php` after the `class-fixture-projection.php` require (before the Collections block):

```php
// Admin (pure)
require_once __DIR__ . '/admin/class-setup-progress.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter SetupProgressTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-setup-progress.php includes/bootstrap.php tests/php/SetupProgressTest.php
git commit -m "feat: add Setup_Progress pure setup-completion calculator"
```

---

## Task 3: `Setup_Sections::inventory()` (pure)

The declarative pages→sections map the visibility UI renders. Section keys MUST match the `is_section_visible( 'page', 'section' )` keys hardcoded in `Blueworx_Clubhouse_Page_Renderer` exactly.

**Files:**
- Create: `includes/admin/class-setup-sections.php`
- Modify: `includes/bootstrap.php` (add a require)
- Test: `tests/php/SetupSectionsTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Page_Map::pages()`.
- Produces: `Blueworx_Clubhouse_Setup_Sections::inventory(): array` → `array<int, array{page:string,label:string,sections:array<int,array{key:string,label:string}>}>`. Consumed by `Setup_Screen` (Task 4) and `Setup_Controller` (Task 5).

- [ ] **Step 1: Write the failing test**

Create `tests/php/SetupSectionsTest.php`:

```php
<?php
// tests/php/SetupSectionsTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupSectionsTest extends TestCase {

	private function inventory(): array {
		return Blueworx_Clubhouse_Setup_Sections::inventory();
	}

	public function test_covers_all_nine_pages_in_page_map_order(): void {
		$pages = array_map( static fn( $p ) => $p['page'], $this->inventory() );
		$this->assertSame(
			array( 'home', 'about', 'membership', 'contact', 'login', 'sports', 'teams', 'events', 'calendar' ),
			$pages
		);
	}

	public function test_home_sections_match_renderer_keys(): void {
		$home = array_values( array_filter( $this->inventory(), static fn( $p ) => 'home' === $p['page'] ) )[0];
		$keys = array_map( static fn( $s ) => $s['key'], $home['sections'] );
		$this->assertSame(
			array( 'header', 'hero', 'quick_tiles', 'ticker', 'stats', 'sports', 'clubhouse', 'membership', 'activity', 'news', 'info', 'sponsors', 'social', 'footer' ),
			$keys
		);
	}

	public function test_every_section_has_a_nonempty_label(): void {
		foreach ( $this->inventory() as $page ) {
			$this->assertNotSame( '', $page['label'] );
			foreach ( $page['sections'] as $section ) {
				$this->assertNotSame( '', $section['label'], "empty label for {$page['page']}.{$section['key']}" );
			}
		}
	}

	public function test_total_section_count_is_45(): void {
		$total = array_sum( array_map( static fn( $p ) => count( $p['sections'] ), $this->inventory() ) );
		$this->assertSame( 45, $total );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter SetupSectionsTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Setup_Sections" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `includes/admin/class-setup-sections.php`. The `MAP` keys are copied verbatim from the `is_section_visible()` calls in `Page_Renderer`; do not invent or reorder keys.

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declarative catalogue of the visibility-toggleable sections per page, for the
 * Clubhouse Setup screen. Pure: page labels come from Page_Map; the section
 * keys are the exact keys the renderers gate on via Visibility::is_section_visible.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Sections {

	/** @var array<string, array<string,string>> page-slug => (section-key => label) */
	private const MAP = array(
		'home' => array(
			'header'      => 'Header',
			'hero'        => 'Hero',
			'quick_tiles' => 'Quick tiles',
			'ticker'      => 'Ticker',
			'stats'       => 'Stats',
			'sports'      => 'Sports grid',
			'clubhouse'   => 'Clubhouse band',
			'membership'  => 'Membership tiers',
			'activity'    => 'Activity tabs',
			'news'        => 'News',
			'info'        => 'Info strip',
			'sponsors'    => 'Sponsors',
			'social'      => 'Social',
			'footer'      => 'Footer',
		),
		'about' => array(
			'hero'       => 'Hero',
			'history'    => 'History',
			'values'     => 'Values',
			'committee'  => 'Committee',
			'facilities' => 'Facilities',
			'cta'        => 'Call to action',
		),
		'membership' => array(
			'hero'   => 'Hero',
			'why'    => 'Why join',
			'tiers'  => 'Tiers',
			'detail' => 'Included / excluded',
			'steps'  => 'How to join',
			'faq'    => 'FAQ',
			'cta'    => 'Call to action',
		),
		'contact' => array(
			'hero'      => 'Hero',
			'form'      => 'Contact form',
			'directory' => 'Directory',
			'social'    => 'Social',
		),
		'login' => array(
			'form' => 'Login form',
		),
		'sports' => array(
			'hero'      => 'Hero',
			'directory' => 'Sports directory',
			'cta'       => 'Call to action',
		),
		'teams' => array(
			'hero'      => 'Hero',
			'directory' => 'Teams directory',
			'cta'       => 'Call to action',
		),
		'events' => array(
			'hero'     => 'Hero',
			'upcoming' => 'Upcoming events',
			'past'     => 'Past events',
			'cta'      => 'Call to action',
		),
		'calendar' => array(
			'hero'     => 'Hero',
			'schedule' => 'Schedule',
			'cta'      => 'Call to action',
		),
	);

	/**
	 * @return array<int, array{page:string,label:string,sections:array<int,array{key:string,label:string}>}>
	 */
	public static function inventory(): array {
		$labels = array();
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			$slug            = '' === $page['slug'] ? 'home' : $page['slug'];
			$labels[ $slug ] = $page['label'];
		}

		$out = array();
		foreach ( self::MAP as $page => $sections ) {
			$section_list = array();
			foreach ( $sections as $key => $label ) {
				$section_list[] = array( 'key' => $key, 'label' => $label );
			}
			$out[] = array(
				'page'     => $page,
				'label'    => $labels[ $page ] ?? ucfirst( $page ),
				'sections' => $section_list,
			);
		}
		return $out;
	}
}
```

Add the require in `includes/bootstrap.php` directly after the `class-setup-progress.php` require:

```php
require_once __DIR__ . '/admin/class-setup-sections.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter SetupSectionsTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-setup-sections.php includes/bootstrap.php tests/php/SetupSectionsTest.php
git commit -m "feat: add Setup_Sections declarative visibility catalogue"
```

---

## Task 4: `Setup_Screen::render()` (pure HTML)

The whole page's markup, built purely from a model array. Emits a `<form>` with three areas (Look, Branding, Visibility) plus a progress bar. NO WordPress calls, NO persistence — the controller supplies the WP-produced nonce/action strings and the model, and processes the POST separately.

**Files:**
- Create: `includes/admin/class-setup-screen.php`
- Modify: `includes/bootstrap.php` (add a require)
- Test: `tests/php/SetupScreenTest.php`

**Interfaces:**
- Consumes: the model assembled by `Setup_Controller` (Task 6). Shape:
  ```
  array{
    nonce_field: string,   // raw HTML (echo as-is)
    action_url: string,
    notices: array<int,array{type:string,text:string}>,   // type: 'error'|'warning'|'success'
    progress: array{items:array<string,bool>,completed:int,total:int},
    looks: array<int,array{slug:string,name:string,description:string,active:bool}>,
    branding: array{accent:string,club_name:string,logo:string,logo_preview:string,facebook:string,instagram:string},
    inventory: array<int,array{page:string,label:string,sections:array<int,array{key:string,label:string}>}>,
    visibility: array{pages:array<string,bool>,sections:array<string,bool>}  // sections keyed "page.section"
  }
  ```
- Produces: `Blueworx_Clubhouse_Setup_Screen::render( array $model ): string`. Consumed by `Setup_Controller` (Task 6).

- [ ] **Step 1: Write the failing test**

Create `tests/php/SetupScreenTest.php`:

```php
<?php
// tests/php/SetupScreenTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupScreenTest extends TestCase {

	private function model(): array {
		return array(
			'nonce_field' => '<input type="hidden" name="_wpnonce" value="NONCE123">',
			'action_url'  => 'https://club.test/wp-admin/admin.php?page=clubhouse-setup',
			'notices'     => array( array( 'type' => 'error', 'text' => 'That accent is too low-contrast.' ) ),
			'progress'    => array(
				'items'     => array( 'look' => true, 'accent' => false, 'club_name' => true, 'logo' => false, 'facebook' => false, 'instagram' => false ),
				'completed' => 2,
				'total'     => 6,
			),
			'looks'       => array(
				array( 'slug' => 'court-side', 'name' => 'Court Side', 'description' => 'Bright & playful.', 'active' => true ),
				array( 'slug' => 'members-house', 'name' => "Members' House", 'description' => 'Editorial.', 'active' => false ),
				array( 'slug' => 'floodlight', 'name' => 'Floodlight', 'description' => 'Dark night-match.', 'active' => false ),
			),
			'branding'    => array(
				'accent' => '#c6f24e', 'club_name' => 'Riverside & Sons', 'logo' => '42',
				'logo_preview' => 'https://club.test/wp-content/uploads/logo.png',
				'facebook' => 'https://facebook.com/riverside', 'instagram' => '',
			),
			'inventory'   => Blueworx_Clubhouse_Setup_Sections::inventory(),
			'visibility'  => array(
				'pages'    => array( 'events' => false ),
				'sections' => array( 'home.ticker' => false ),
			),
		);
	}

	public function test_renders_nonce_and_action_and_progress(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="_wpnonce" value="NONCE123"', $html );
		$this->assertStringContainsString( 'action="https://club.test/wp-admin/admin.php?page=clubhouse-setup"', $html );
		$this->assertStringContainsString( '2 of 6', $html );
	}

	public function test_renders_a_card_per_look_with_active_marked(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertSame( 3, substr_count( $html, 'name="clubhouse_look"' ) );
		$this->assertStringContainsString( 'value="court-side" checked', $html );
	}

	public function test_renders_branding_fields_with_current_values_escaped(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="clubhouse_accent"', $html );
		$this->assertStringContainsString( 'value="Riverside &amp; Sons"', $html );
		$this->assertStringNotContainsString( 'Riverside & Sons"', $html );
		$this->assertStringContainsString( 'name="clubhouse_facebook"', $html );
		$this->assertStringContainsString( 'name="clubhouse_logo"', $html );
	}

	public function test_renders_a_checkbox_per_section_plus_per_page(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertSame( 45, substr_count( $html, 'name="clubhouse_section[' ) );
		$this->assertSame( 9, substr_count( $html, 'name="clubhouse_page[' ) );
		$this->assertStringContainsString( 'name="clubhouse_section[home.hero]" value="1" checked', $html );
		$this->assertStringContainsString( 'name="clubhouse_section[home.ticker]" value="1">', $html );
	}

	public function test_renders_error_notice(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'notice notice-error', $html );
		$this->assertStringContainsString( 'That accent is too low-contrast.', $html );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter SetupScreenTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Setup_Screen" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `includes/admin/class-setup-screen.php`. Visibility defaults to visible (checked) when a key is absent, mirroring `Visibility::is_*`.

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML builder for the Clubhouse Setup admin page. Emits the form; the
 * controller supplies the model and the WP-produced nonce/action strings, and
 * processes the POST. No WordPress calls, no persistence here.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$out  = '<div class="wrap clubhouse-setup">';
		$out .= '<h1>Clubhouse Setup</h1>';
		$out .= self::notices( $model['notices'] );
		$out .= self::progress( $model['progress'] );
		$out .= '<form method="post" action="' . self::esc( (string) $model['action_url'] ) . '">';
		$out .= $model['nonce_field'];
		$out .= self::look_area( $model['looks'] );
		$out .= self::branding_area( $model['branding'] );
		$out .= self::visibility_area( $model['inventory'], $model['visibility'] );
		$out .= '<p class="submit"><button type="submit" class="button button-primary">Save changes</button></p>';
		$out .= '</form></div>';
		return $out;
	}

	/** @param array<int,array{type:string,text:string}> $notices */
	private static function notices( array $notices ): string {
		$out = '';
		foreach ( $notices as $n ) {
			$type = in_array( $n['type'], array( 'error', 'warning', 'success' ), true ) ? $n['type'] : 'info';
			$out .= '<div class="notice notice-' . self::esc( $type ) . '"><p>' . self::esc( $n['text'] ) . '</p></div>';
		}
		return $out;
	}

	/** @param array{items:array<string,bool>,completed:int,total:int} $p */
	private static function progress( array $p ): string {
		$pct  = 0 === $p['total'] ? 0 : (int) round( 100 * $p['completed'] / $p['total'] );
		$out  = '<div class="clubhouse-progress">';
		$out .= '<p class="clubhouse-progress__label">Setup: ' . (int) $p['completed'] . ' of ' . (int) $p['total'] . ' complete</p>';
		$out .= '<div class="clubhouse-progress__track"><div class="clubhouse-progress__bar" style="width:' . $pct . '%"></div></div>';
		$out .= '</div>';
		return $out;
	}

	/** @param array<int,array{slug:string,name:string,description:string,active:bool}> $looks */
	private static function look_area( array $looks ): string {
		$out = '<h2>Base Look</h2><div class="clubhouse-looks" role="radiogroup" aria-label="Base Look">';
		foreach ( $looks as $look ) {
			$checked = $look['active'] ? ' checked' : '';
			$out .= '<label class="clubhouse-look-card">';
			$out .= '<input type="radio" name="clubhouse_look" value="' . self::esc( $look['slug'] ) . '"' . $checked . '>';
			$out .= '<span class="clubhouse-look-card__name">' . self::esc( $look['name'] ) . '</span>';
			$out .= '<span class="clubhouse-look-card__desc">' . self::esc( $look['description'] ) . '</span>';
			$out .= '</label>';
		}
		$out .= '</div>';
		return $out;
	}

	/** @param array<string,string> $b */
	private static function branding_area( array $b ): string {
		$out  = '<h2>Branding</h2><table class="form-table" role="presentation"><tbody>';
		$out .= self::text_row( 'clubhouse_accent', 'Accent colour', (string) $b['accent'], 'text', 'A hex colour, e.g. #c6f24e. Must be legible on the chosen look.' );
		$out .= self::text_row( 'clubhouse_club_name', 'Club name', (string) $b['club_name'] );
		$preview = '' !== $b['logo_preview']
			? '<img class="clubhouse-logo-preview" src="' . self::esc( (string) $b['logo_preview'] ) . '" alt="Current logo" style="max-height:60px">'
			: '<span class="clubhouse-logo-preview clubhouse-logo-preview--empty">No logo set</span>';
		$out .= '<tr><th scope="row"><label>Logo</label></th><td>';
		$out .= '<input type="hidden" name="clubhouse_logo" id="clubhouse_logo" value="' . self::esc( (string) $b['logo'] ) . '">';
		$out .= $preview;
		$out .= ' <button type="button" class="button" id="clubhouse-logo-pick">Choose logo</button>';
		$out .= ' <button type="button" class="button-link" id="clubhouse-logo-clear">Remove</button>';
		$out .= '</td></tr>';
		$out .= self::text_row( 'clubhouse_facebook', 'Facebook URL', (string) $b['facebook'], 'url' );
		$out .= self::text_row( 'clubhouse_instagram', 'Instagram URL', (string) $b['instagram'], 'url' );
		$out .= '</tbody></table>';
		return $out;
	}

	private static function text_row( string $name, string $label, string $value, string $type = 'text', string $help = '' ): string {
		$out  = '<tr><th scope="row"><label for="' . self::esc( $name ) . '">' . self::esc( $label ) . '</label></th><td>';
		$out .= '<input type="' . self::esc( $type ) . '" class="regular-text" id="' . self::esc( $name ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( $value ) . '">';
		if ( '' !== $help ) {
			$out .= '<p class="description">' . self::esc( $help ) . '</p>';
		}
		$out .= '</td></tr>';
		return $out;
	}

	/**
	 * @param array<int,array{page:string,label:string,sections:array<int,array{key:string,label:string}>}> $inventory
	 * @param array{pages:array<string,bool>,sections:array<string,bool>} $visibility
	 */
	private static function visibility_area( array $inventory, array $visibility ): string {
		$out = '<h2>Visibility</h2><p class="description">Untick to hide a page or a section. Pages and sections are shown by default.</p>';
		foreach ( $inventory as $page ) {
			$page_checked = ( $visibility['pages'][ $page['page'] ] ?? true ) ? ' checked' : '';
			$out .= '<fieldset class="clubhouse-vis-page"><legend>';
			$out .= '<label><input type="checkbox" name="clubhouse_page[' . self::esc( $page['page'] ) . ']" value="1"' . $page_checked . '> ' . self::esc( $page['label'] ) . '</label>';
			$out .= '</legend><div class="clubhouse-vis-sections">';
			foreach ( $page['sections'] as $section ) {
				$skey            = $page['page'] . '.' . $section['key'];
				$section_checked = ( $visibility['sections'][ $skey ] ?? true ) ? ' checked' : '';
				$out .= '<label class="clubhouse-vis-section"><input type="checkbox" name="clubhouse_section[' . self::esc( $skey ) . ']" value="1"' . $section_checked . '> ' . self::esc( $section['label'] ) . '</label>';
			}
			$out .= '</div></fieldset>';
		}
		return $out;
	}
}
```

> Keep the checkbox attribute order `name … value="1"` then `checked` — the section test asserts `name="clubhouse_section[home.hero]" value="1" checked`.

Add the require in `includes/bootstrap.php` after the `class-setup-sections.php` require:

```php
require_once __DIR__ . '/admin/class-setup-screen.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter SetupScreenTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-setup-screen.php includes/bootstrap.php tests/php/SetupScreenTest.php
git commit -m "feat: add Setup_Screen pure admin-page HTML builder"
```

---

## Task 5: `Setup_Controller` — POST handling & persistence (WP glue)

The save path: sanitise each field, persist through the existing setters, reject an accent that is illegible for the active look (keeping the rest), warn when the stored accent is orphaned by a look switch, and invalidate the theme cache. Builds ONLY the save logic + the shim stubs it needs; menu/enqueue/render is Task 6.

**Files:**
- Create: `includes/admin/class-setup-controller.php` (the `handle_save()` method + constants)
- Modify: `tests/php/wp-stubs.php` (add guarded stubs)
- Modify: `tests/php/bootstrap.php` (require the controller)
- Test: `tests/php/SetupControllerTest.php`

**Interfaces:**
- Consumes: `Frontend::registry()`, `Options_Storage`, `Branding`, `Visibility`, `Theme_Cache`, `Color_Engine::accent_is_legible_for()` (Task 1), `Setup_Sections::inventory()`.
- Produces: `Blueworx_Clubhouse_Setup_Controller::handle_save( array $post, Blueworx_Clubhouse_Storage $storage ): array` — applies the POST and returns `array<int,array{type:string,text:string}>` notices. Plus the constants `CAPABILITY`, `PAGE_SLUG`, `NONCE`.

- [ ] **Step 1: Add the sanitiser shim stubs**

In `tests/php/wp-stubs.php`, add (guarded; append before the file's end):

```php
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { return is_string( $str ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', preg_replace( '/<[^>]*>/', '', $str ) ) ) : ''; }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { $u = trim( (string) $url ); return preg_match( '#^https?://#i', $u ) ? $u : ''; }
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) { $c = trim( (string) $color ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? strtolower( $c ) : ''; }
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/php/SetupControllerTest.php`:

```php
<?php
// tests/php/SetupControllerTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupControllerTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	private function storage(): Blueworx_Clubhouse_Storage {
		return new Blueworx_Clubhouse_Fake_Storage();
	}

	public function test_saves_look_branding_and_visibility(): void {
		$storage = $this->storage();
		$post = array(
			'clubhouse_look'      => 'floodlight',
			'clubhouse_accent'    => '#f7a70a',            // glow-only look: accepted (deep-legible)
			'clubhouse_club_name' => 'Riverside RFC',
			'clubhouse_logo'      => '42',
			'clubhouse_facebook'  => 'https://facebook.com/riverside',
			'clubhouse_instagram' => 'https://instagram.com/riverside',
			'clubhouse_page'      => array( 'events' => '1' ),
			'clubhouse_section'   => array( 'home.hero' => '1' ),
		);
		$notices = Blueworx_Clubhouse_Setup_Controller::handle_save( $post, $storage );

		$registry = Blueworx_Clubhouse_Frontend::registry( $storage );
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$vis      = new Blueworx_Clubhouse_Visibility( $storage );

		$this->assertInstanceOf( Blueworx_Clubhouse_Floodlight::class, $registry->active() );
		$this->assertSame( '#f7a70a', $branding->get_accent() );
		$this->assertSame( 'Riverside RFC', $branding->get_club_name() );
		$this->assertSame( '42', $branding->get_logo() );
		$this->assertTrue( $vis->is_page_visible( 'events' ) );
		$this->assertFalse( $vis->is_section_visible( 'home', 'ticker' ) ); // unticked => hidden
		$this->assertTrue( $vis->is_section_visible( 'home', 'hero' ) );
		$this->assertSame( array(), array_values( array_filter( $notices, static fn( $n ) => 'error' === $n['type'] ) ) );
	}

	public function test_illegible_accent_is_rejected_but_other_fields_save(): void {
		$storage = $this->storage();
		// Court Side is text-bearing; #7a7a7a fails the ink check -> rejected.
		$post = array(
			'clubhouse_look'      => 'court-side',
			'clubhouse_accent'    => '#7a7a7a',
			'clubhouse_club_name' => 'Riverside RFC',
		);
		$notices = Blueworx_Clubhouse_Setup_Controller::handle_save( $post, $storage );

		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$this->assertSame( '#c6f24e', $branding->get_accent() ); // unchanged default (rejected, not '#7a7a7a')
		$this->assertSame( 'Riverside RFC', $branding->get_club_name() );
		$errors = array_values( array_filter( $notices, static fn( $n ) => 'error' === $n['type'] ) );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsStringIgnoringCase( 'contrast', $errors[0]['text'] );
	}

	public function test_illegible_accent_rejection_preserves_prior_accent(): void {
		$storage  = $this->storage();
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_accent( '#7a2f3a' );
		$post = array( 'clubhouse_look' => 'court-side', 'clubhouse_accent' => '#7a7a7a' );
		Blueworx_Clubhouse_Setup_Controller::handle_save( $post, $storage );
		$this->assertSame( '#7a2f3a', ( new Blueworx_Clubhouse_Branding( $storage ) )->get_accent() );
	}

	public function test_look_switch_orphaning_accent_warns(): void {
		$storage  = $this->storage();
		// A mid-grey accent stored while on the glow-only Floodlight (fine there)...
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_accent( '#7a7a7a' );
		Blueworx_Clubhouse_Frontend::registry( $storage )->set_active( 'floodlight' );
		// ...now switch to text-bearing Court Side without a new accent: orphaned.
		$post = array( 'clubhouse_look' => 'court-side' );
		$notices = Blueworx_Clubhouse_Setup_Controller::handle_save( $post, $storage );

		$warnings = array_values( array_filter( $notices, static fn( $n ) => 'warning' === $n['type'] ) );
		$this->assertNotEmpty( $warnings );
	}

	public function test_save_invalidates_theme_cache(): void {
		$storage = $this->storage();
		$cache = new Blueworx_Clubhouse_Theme_Cache( $storage );
		$cache->root_css( new Blueworx_Clubhouse_Court_Side(), new Blueworx_Clubhouse_Branding( $storage ) );
		$this->assertNotSame( '', $storage->get( 'root_css', '' ) );

		Blueworx_Clubhouse_Setup_Controller::handle_save( array( 'clubhouse_club_name' => 'X' ), $storage );
		$this->assertSame( '', $storage->get( 'root_css', '' ) );
	}
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter SetupControllerTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Setup_Controller" not found`.

- [ ] **Step 4: Write minimal implementation**

Create `includes/admin/class-setup-controller.php`:

```php
<?php
// includes/admin/class-setup-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress-coupled controller for the Clubhouse Setup admin screen: menu
 * registration, asset enqueue, and POST handling. All HTML is delegated to
 * Setup_Screen; persistence goes through the existing setters. handle_save takes
 * a Storage so it is unit-testable WP-free.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Controller {

	public const CAPABILITY = 'manage_options'; // Phase 4 swaps this for the owner cap.
	public const PAGE_SLUG  = 'clubhouse-setup';
	public const NONCE      = 'clubhouse_setup_save';

	/**
	 * Apply a setup POST to storage. Returns notices (error/warning/success).
	 *
	 * @param array<string,mixed> $post
	 * @return array<int,array{type:string,text:string}>
	 */
	public static function handle_save( array $post, Blueworx_Clubhouse_Storage $storage ): array {
		$notices  = array();
		$registry = Blueworx_Clubhouse_Frontend::registry( $storage );
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$vis      = new Blueworx_Clubhouse_Visibility( $storage );

		// 1. Look.
		if ( isset( $post['clubhouse_look'] ) ) {
			$slug = sanitize_text_field( (string) $post['clubhouse_look'] );
			if ( $registry->has( $slug ) ) {
				$registry->set_active( $slug );
			}
		}
		$active = $registry->active() ?? new Blueworx_Clubhouse_Court_Side();

		// 2. Accent — reject if illegible for the (now-active) look.
		if ( isset( $post['clubhouse_accent'] ) ) {
			$accent = sanitize_hex_color( (string) $post['clubhouse_accent'] );
			if ( '' === $accent ) {
				$notices[] = array( 'type' => 'error', 'text' => 'The accent colour must be a 6-digit hex value like #c6f24e.' );
			} elseif ( ! Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $active, $accent ) ) {
				$notices[] = array( 'type' => 'error', 'text' => 'That accent is too low in contrast for the chosen look and was not saved. Pick a stronger colour.' );
			} else {
				$branding->set_accent( $accent );
			}
		}

		// 3. Text/URL branding.
		if ( isset( $post['clubhouse_club_name'] ) ) {
			$branding->set_club_name( sanitize_text_field( (string) $post['clubhouse_club_name'] ) );
		}
		if ( isset( $post['clubhouse_logo'] ) ) {
			$branding->set_logo( sanitize_text_field( (string) $post['clubhouse_logo'] ) );
		}
		if ( isset( $post['clubhouse_facebook'] ) ) {
			$branding->set_facebook_url( esc_url_raw( (string) $post['clubhouse_facebook'] ) );
		}
		if ( isset( $post['clubhouse_instagram'] ) ) {
			$branding->set_instagram_url( esc_url_raw( (string) $post['clubhouse_instagram'] ) );
		}

		// 4. Visibility — a checkbox is present only when ticked; absence = hidden.
		$pages    = isset( $post['clubhouse_page'] ) && is_array( $post['clubhouse_page'] ) ? $post['clubhouse_page'] : array();
		$sections = isset( $post['clubhouse_section'] ) && is_array( $post['clubhouse_section'] ) ? $post['clubhouse_section'] : array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $page ) {
			$vis->set_page_visible( $page['page'], isset( $pages[ $page['page'] ] ) );
			foreach ( $page['sections'] as $section ) {
				$skey = $page['page'] . '.' . $section['key'];
				$vis->set_section_visible( $page['page'], $section['key'], isset( $sections[ $skey ] ) );
			}
		}

		// 5. Warn if the stored accent is now illegible for the active look.
		if ( ! Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $active, $branding->get_accent() ) ) {
			$notices[] = array( 'type' => 'warning', 'text' => 'Your saved accent colour is low-contrast on the selected look. Choose a new accent for best legibility.' );
		}

		// 6. Bust the composed :root cache so the new look/accent take effect.
		( new Blueworx_Clubhouse_Theme_Cache( $storage ) )->invalidate();

		return $notices;
	}
}
```

In `tests/php/bootstrap.php`, add after the `class-frontend.php` require:

```php
require_once dirname( __DIR__, 2 ) . '/includes/admin/class-setup-controller.php';
```

- [ ] **Step 5: Run test then the full suite**

Run: `./vendor/bin/phpunit --filter SetupControllerTest`
Expected: PASS (5 tests).

Run: `./vendor/bin/phpunit`
Expected: PASS (all).

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-setup-controller.php tests/php/wp-stubs.php tests/php/bootstrap.php tests/php/SetupControllerTest.php
git commit -m "feat: add Setup_Controller save handler with look-aware accent rejection"
```

---

## Task 6: `Setup_Controller` — menu, render, and asset enqueue (WP glue)

Wire the screen into wp-admin: register a top-level "Clubhouse" menu, gate + process the POST, render `Setup_Screen`, enqueue the media picker + admin CSS, and assemble the `Setup_Screen` model from live state.

**Files:**
- Modify: `includes/admin/class-setup-controller.php` (add `register`, `add_menu`, `enqueue`, `render_page`, `build_model`)
- Modify: `blueworx-labs-clubhouse.php` (require controller; register on admin hooks)
- Create: `assets/js/admin-setup.js`, `assets/css/admin-setup.css`
- Modify: `tests/php/wp-stubs.php` (add guarded stubs)
- Test: `tests/php/SetupControllerTest.php` (add hook + model tests)

**Interfaces:**
- Consumes: `Setup_Progress::compute()`, `Setup_Sections::inventory()`, `Setup_Screen::render()`, `Frontend::registry()`, `Branding`, `Visibility`.
- Produces: `Setup_Controller::register()`, `Setup_Controller::build_model( Storage, array $notices, string $nonce_field, string $action_url ): array`, `Setup_Controller::render_page()`, `Setup_Controller::add_menu()`, `Setup_Controller::enqueue( string $hook )`.

- [ ] **Step 1: Add shim stubs**

In `tests/php/wp-stubs.php`, add (guarded):

```php
if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( ...$a ) { wp_stub_record( 'add_menu_page', $a ); return 'toplevel_page_' . ( $a[3] ?? '' ); }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( ...$a ) { wp_stub_record( 'current_user_can', $a ); return true; }
}
if ( ! function_exists( 'wp_enqueue_media' ) ) {
	function wp_enqueue_media( ...$a ) { wp_stub_record( 'wp_enqueue_media', $a ); }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://club.test/wp-admin/' . ltrim( (string) $path, '/' ); }
}
if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return $id ? 'https://club.test/wp-content/uploads/att-' . (int) $id . '.png' : false; }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( ...$a ) { wp_stub_record( 'wp_nonce_field', $a ); return '<input type="hidden" name="_wpnonce" value="stub-nonce">'; }
}
if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( ...$a ) { wp_stub_record( 'check_admin_referer', $a ); return true; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) { return $v; }
}
```

- [ ] **Step 2: Write the failing tests**

Add to `tests/php/SetupControllerTest.php`:

```php
	public function test_register_adds_admin_menu_and_enqueue_hooks(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Setup_Controller::register();
		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$this->assertContains( 'admin_menu', $actions );
		$this->assertContains( 'admin_enqueue_scripts', $actions );
	}

	public function test_build_model_reflects_live_state(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Frontend::registry( $storage )->set_active( 'floodlight' );
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_club_name( 'Riverside RFC' );

		$model = Blueworx_Clubhouse_Setup_Controller::build_model( $storage, array(), '<nonce>', 'https://club.test/x' );

		$this->assertSame( '<nonce>', $model['nonce_field'] );
		$this->assertSame( 'Riverside RFC', $model['branding']['club_name'] );
		$active = array_values( array_filter( $model['looks'], static fn( $l ) => $l['active'] ) );
		$this->assertSame( 'floodlight', $active[0]['slug'] );
		$this->assertCount( 3, $model['looks'] );
		$this->assertSame( 6, $model['progress']['total'] );
	}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter SetupControllerTest`
Expected: FAIL — `Call to undefined method …::register()` / `::build_model()`.

- [ ] **Step 4: Implement the wiring**

Add these methods to `Blueworx_Clubhouse_Setup_Controller`:

```php
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_menu(): void {
		add_menu_page(
			'Clubhouse Setup',
			'Clubhouse',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' ),
			'dashicons-megaphone',
			3
		);
	}

	public static function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'clubhouse-admin-setup', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/admin-setup.css', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_enqueue_script( 'clubhouse-admin-setup', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/admin-setup.js', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$storage = new Blueworx_Clubhouse_Options_Storage();
		$notices = array();
		if ( isset( $_POST['clubhouse_setup_submit'] ) ) {
			check_admin_referer( self::NONCE );
			$notices = self::handle_save( wp_unslash( $_POST ), $storage );
		}
		$nonce_field = wp_nonce_field( self::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="clubhouse_setup_submit" value="1">';
		$action_url  = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		echo Blueworx_Clubhouse_Setup_Screen::render( self::build_model( $storage, $notices, $nonce_field, $action_url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array<int,array{type:string,text:string}> $notices
	 * @return array<string,mixed>
	 */
	public static function build_model( Blueworx_Clubhouse_Storage $storage, array $notices, string $nonce_field, string $action_url ): array {
		$registry    = Blueworx_Clubhouse_Frontend::registry( $storage );
		$branding    = new Blueworx_Clubhouse_Branding( $storage );
		$vis         = new Blueworx_Clubhouse_Visibility( $storage );
		$active_slug = (string) $storage->get( 'active_base_look', '' );
		$active_look = $registry->active();

		$looks = array();
		foreach ( $registry->all() as $look ) {
			$looks[] = array(
				'slug'        => $look->slug(),
				'name'        => $look->name(),
				'description' => $look->description(),
				'active'      => null !== $active_look && $look->slug() === $active_look->slug(),
			);
		}

		$logo         = $branding->get_logo();
		$logo_preview = '';
		if ( '' !== $logo ) {
			$logo_preview = ctype_digit( $logo ) ? (string) wp_get_attachment_image_url( (int) $logo, 'medium' ) : $logo;
		}

		$pages_state    = array();
		$sections_state = array();
		foreach ( Blueworx_Clubhouse_Setup_Sections::inventory() as $page ) {
			$pages_state[ $page['page'] ] = $vis->is_page_visible( $page['page'] );
			foreach ( $page['sections'] as $section ) {
				$sections_state[ $page['page'] . '.' . $section['key'] ] = $vis->is_section_visible( $page['page'], $section['key'] );
			}
		}

		return array(
			'nonce_field' => $nonce_field,
			'action_url'  => $action_url,
			'notices'     => $notices,
			'progress'    => Blueworx_Clubhouse_Setup_Progress::compute( $branding, $active_look ?? new Blueworx_Clubhouse_Court_Side(), '' !== $active_slug ),
			'looks'       => $looks,
			'branding'    => array(
				'accent'       => $branding->get_accent(),
				'club_name'    => $branding->get_club_name(),
				'logo'         => $logo,
				'logo_preview' => $logo_preview,
				'facebook'     => $branding->get_facebook_url(),
				'instagram'    => $branding->get_instagram_url(),
			),
			'inventory'   => Blueworx_Clubhouse_Setup_Sections::inventory(),
			'visibility'  => array( 'pages' => $pages_state, 'sections' => $sections_state ),
		);
	}
```

Create `assets/js/admin-setup.js`:

```javascript
/* Clubhouse Setup — logo media picker (progressive enhancement). */
( function () {
	'use strict';
	var pick = document.getElementById( 'clubhouse-logo-pick' );
	var clear = document.getElementById( 'clubhouse-logo-clear' );
	var field = document.getElementById( 'clubhouse_logo' );
	if ( ! pick || ! field || ! window.wp || ! window.wp.media ) {
		return;
	}
	var frame;
	pick.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		if ( ! frame ) {
			frame = window.wp.media( { title: 'Select a logo', button: { text: 'Use this logo' }, multiple: false } );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				field.value = String( att.id );
				var img = document.querySelector( '.clubhouse-logo-preview' );
				if ( img && img.tagName === 'IMG' ) {
					img.src = att.url;
				}
			} );
		}
		frame.open();
	} );
	if ( clear ) {
		clear.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			field.value = '';
		} );
	}
}() );
```

Create `assets/css/admin-setup.css`:

```css
.clubhouse-setup .clubhouse-progress { max-width: 640px; margin: 16px 0 24px; }
.clubhouse-progress__track { background: #e2e4e7; border-radius: 999px; height: 10px; overflow: hidden; }
.clubhouse-progress__bar { background: #2271b1; height: 100%; transition: width .3s ease; }
.clubhouse-looks { display: flex; flex-wrap: wrap; gap: 12px; margin: 12px 0 24px; }
.clubhouse-look-card { display: block; border: 2px solid #dcdcde; border-radius: 10px; padding: 14px 16px; cursor: pointer; max-width: 220px; }
.clubhouse-look-card:has( input:checked ) { border-color: #2271b1; background: #f0f6fc; }
.clubhouse-look-card__name { display: block; font-weight: 600; margin: 6px 0 4px; }
.clubhouse-look-card__desc { display: block; color: #50575e; font-size: 13px; }
.clubhouse-vis-page { border: 1px solid #dcdcde; border-radius: 8px; padding: 10px 14px; margin: 0 0 12px; }
.clubhouse-vis-sections { display: flex; flex-wrap: wrap; gap: 6px 20px; margin-top: 8px; }
.clubhouse-vis-section { display: block; min-width: 160px; }
```

In `blueworx-labs-clubhouse.php`, require the controller after the `class-frontend.php` require:

```php
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-setup-controller.php';
```

and inside `blueworx_labs_clubhouse_init()` (alongside `Blueworx_Clubhouse_Frontend::register();`):

```php
	if ( is_admin() ) {
		Blueworx_Clubhouse_Setup_Controller::register();
	}
```

- [ ] **Step 5: Run tests then the full suite**

Run: `./vendor/bin/phpunit --filter SetupControllerTest`
Expected: PASS (all `SetupControllerTest` tests).

Run: `./vendor/bin/phpunit`
Expected: PASS (all).

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-setup-controller.php blueworx-labs-clubhouse.php assets/js/admin-setup.js assets/css/admin-setup.css tests/php/wp-stubs.php tests/php/SetupControllerTest.php
git commit -m "feat: mount Clubhouse Setup admin page (menu, render, media picker)"
```

---

## Task 7: Enforce page visibility at routing

`Visibility::is_page_visible()` is a dead API — nothing consults it, so a page toggle would be a no-op. Make the router treat a hidden page as not-routed (WordPress then serves its normal 404). (Nav-link omission is deferred to Phase 3 with the logo render.)

**Files:**
- Modify: `includes/frontend/class-frontend.php` (`resolve_slug` gains a visibility check; `current_slug` passes it)
- Test: `tests/php/FrontendTest.php` (add tests)

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Visibility::is_page_visible()`, the DTO from `context()`.
- Produces: `Blueworx_Clubhouse_Frontend::resolve_slug( bool $is_front_page, mixed $query_var, ?Blueworx_Clubhouse_Visibility $visibility = null ): ?string` — returns `null` when the resolved page is hidden. The optional third parameter keeps the existing pure tests valid.

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/FrontendTest.php`:

```php
	public function test_resolve_slug_hidden_page_is_null(): void {
		$vis = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$vis->set_page_visible( 'about', false );
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( false, 'about', $vis ) );
	}

	public function test_resolve_slug_visible_page_still_resolves(): void {
		$vis = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertSame( 'about', Blueworx_Clubhouse_Frontend::resolve_slug( false, 'about', $vis ) );
	}

	public function test_resolve_slug_hidden_home_is_null(): void {
		$vis = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$vis->set_page_visible( 'home', false );
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( true, null, $vis ) );
	}

	public function test_resolve_slug_without_visibility_unchanged(): void {
		$this->assertSame( 'about', Blueworx_Clubhouse_Frontend::resolve_slug( false, 'about' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::resolve_slug( true, null ) );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter FrontendTest`
Expected: FAIL — `test_resolve_slug_hidden_page_is_null` fails (current two-arg `resolve_slug` ignores visibility).

- [ ] **Step 3: Implement the visibility check**

In `includes/frontend/class-frontend.php`, replace `resolve_slug()` (lines 21-26) with:

```php
	public static function resolve_slug( bool $is_front_page, mixed $query_var, ?Blueworx_Clubhouse_Visibility $visibility = null ): ?string {
		$slug = null;
		if ( is_string( $query_var ) && '' !== $query_var && Blueworx_Clubhouse_Page_Map::has( $query_var ) ) {
			$slug = $query_var;
		} elseif ( $is_front_page ) {
			$slug = '';
		}
		if ( null === $slug ) {
			return null;
		}
		$page = '' === $slug ? 'home' : $slug;
		if ( null !== $visibility && ! $visibility->is_page_visible( $page ) ) {
			return null;
		}
		return $slug;
	}
```

Update `current_slug()` (lines 78-82) to pass the visibility:

```php
	private static function current_slug(): ?string {
		$is_front = function_exists( 'is_front_page' ) ? is_front_page() : false;
		$qv       = function_exists( 'get_query_var' ) ? get_query_var( self::QUERY_VAR ) : '';
		return self::resolve_slug( (bool) $is_front, $qv, self::context()->visibility );
	}
```

- [ ] **Step 4: Run tests then the full suite**

Run: `./vendor/bin/phpunit --filter FrontendTest`
Expected: PASS (all `FrontendTest` tests, old and new).

Run: `./vendor/bin/phpunit`
Expected: PASS (all).

- [ ] **Step 5: Commit**

```bash
git add includes/frontend/class-frontend.php tests/php/FrontendTest.php
git commit -m "feat: enforce page visibility at routing (hidden page 404s)"
```

---

## Task 8: Version bump, changelog, and full verification

**Files:**
- Modify: `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md`

- [ ] **Step 1: Bump the plugin version**

In `blueworx-labs-clubhouse.php`: header `Version: 0.15.0` → `0.16.0`; `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.15.0' )` → `'0.16.0'`.

- [ ] **Step 2: Bump package.json**

`"version": "0.15.0"` → `"0.16.0"`.

- [ ] **Step 3: Add the changelog entry**

At the top of the entries in `CHANGELOG.md` (below the header block, above `## [0.15.0]`):

```markdown
## [0.16.0] - 2026-07-13

### Clubhouse Setup screen

The first owner-facing admin surface — a standard WordPress admin page under a new **Clubhouse** menu.

#### Added

- **Base Look picker.** Choose Court Side, Members' House, or Floodlight; the choice becomes the active look.
- **Branding controls.** Accent colour (rejected on save if it is too low-contrast for the chosen look — the check is look-aware: text-bearing looks need higher contrast than the glow-only dark look), club name, logo (via the media library), and Facebook / Instagram URLs.
- **Visibility controls.** Show or hide any page and any of its sections. Hidden pages now return a 404 on the front end.
- **Setup progress bar.** Tracks the six branding/look configuration items (page content is not counted).
- **Look-aware accent legibility.** Base Looks now declare whether they paint text on the accent fill, so accent acceptance matches how each look actually uses the colour.

#### Notes

- The screen is a standard admin page for now (capability `manage_options`); the locked-down Clubhouse Owner role and Dashboard takeover arrive in a later phase.
- The logo is stored here; rendering it in the site header (and omitting hidden pages from the nav) lands in the next phase.
```

- [ ] **Step 4: Run the full suite and lint**

Run: `./vendor/bin/phpunit`
Expected: PASS (all).

Run: `composer lint`
Expected: PASS (0 errors). If PHPCS flags the intentional un-escaped `echo` in `render_page()`, confirm the `phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped` comment is present (the markup is escaped inside the pure `Setup_Screen`).

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.16.0 (Clubhouse Setup screen)"
```

---

## Self-Review Notes

- **Spec coverage (Phase 2 of the umbrella spec):** look-aware accent legibility (correcting the too-strict Phase-1 gate) → Task 1. Look picker → Tasks 4 + 5. Branding incl. accent rejection → Tasks 4 + 5. Logo stored (rendered later, per the approved decision) → Tasks 5–6. Visibility page + section toggles → Tasks 3 + 4 + 5 + 7. Progress bar (6 items) → Tasks 2 + 4. `Theme_Cache::invalidate()` on save → Task 5. Look-switch orphan warning → Task 5. Standard admin page, capability in one constant for Phase 4 → Task 6.
- **Deferred (recorded in changelog + Global Constraints):** front-end logo `<img>` and hidden-page nav omission → Phase 3 (both thread data into the pure `shell_header`).
- **Placeholder scan:** none — every step carries full code and exact commands.
- **Type consistency:** `accent_bears_text(): bool`, `accent_is_legible_for( Base_Look, string ): bool`, `accent_deep_is_legible( string, string ): bool`, `compute( Branding, Base_Look, bool ): array`, `inventory(): array`, `render( array ): string`, `handle_save( array, Storage ): array`, `build_model( Storage, array, string, string ): array`, `resolve_slug( bool, mixed, ?Visibility ): ?string` are consistent across tasks. The `Setup_Screen` model shape (Task 4) matches `build_model`'s output (Task 6).
- **Interface-change safety:** Task 1 adds `accent_bears_text()` to `Base_Look`; every implementor (Court Side, Members' House, Floodlight, `Fake_Look`, and the in-file `Clubhouse_Test_Look` in ThemeCacheTest) is updated in the same task, so no fatal from an unimplemented interface method.
- **Deployment:** first real owner UI. Refresh the deployment zip on merge; first phase owing a **manual WP smoke** (activate → Clubhouse → Setup: pick a look + accent, confirm a low-contrast accent is rejected on a light look but a bright one is accepted on Floodlight, toggle a page hidden and confirm it 404s).
```
