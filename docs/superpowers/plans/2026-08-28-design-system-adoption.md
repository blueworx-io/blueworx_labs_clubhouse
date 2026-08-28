# Design system adoption (phase 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the BlueWorx admin design system into Clubhouse, and rebuild the five admin screens that are not being replaced later on it — which also fixes the four that render unstyled today.

**Architecture:** The design system is copied, committed and hash-checked, exactly as the foundation requires. One new PHP class renders the shell every admin screen shares (full-bleed wrapper, page header) so no screen hand-rolls it. Each of the five screens then swaps its `clubhouse-*` markup for the system's `bw-*` classes and stops enqueuing the bespoke stylesheet. Setup and Club Pages are deliberately left alone — the page editor library replaces them in phases 3 and 4, so restyling them by hand now is work that gets thrown away.

**Tech Stack:** PHP 8.3, WordPress, PHPUnit 11, Playwright. No npm dependency and no build step — the design system is a stylesheet and an icon module, both copied verbatim.

**Spec:** [docs/superpowers/specs/2026-08-28-page-editor-adoption-design.md](../specs/2026-08-28-page-editor-adoption-design.md) — §6 "The club's look leaves wp-admin", §5 "The remaining screens", §8 phase 2.

**Issues:** [#282](../../../../issues/282) (the club's look leaves wp-admin — this phase does the vendoring and five screens; the last two screens follow in phases 3 and 4, so #282 closes then), [#287](../../../../issues/287) (four screens unstyled — closed by this phase).

## Global Constraints

- **The design system is copied verbatim and never edited.** `.claude/skills/blueworx-admin-design/`, `assets/blueworx-admin-design.css`, `assets/blueworx-admin-icons.js`. CI hash-checks all three against the foundation. A change you want belongs in `bluegroup_core_foundation` first.
- **Foundation ref:** `v1` — currently `18a9db1` / tag `v1.9.0`. Copy from a checkout at that ref.
- **Do NOT vendor the page editor library in this phase.** `blueworx-page-editor/` and `assets/blueworx-page-editor.js` come in phase 3. The sync check treats neither-present as "not adopted"; carrying one without the other fails.
- **Only the diff is judged by the adherence check.** An untouched legacy screen is left alone. This is what makes leaving Setup and Club Pages alone legal.
- **The only CSS this plugin may keep** is the documented full-bleed chrome overrides: selectors matching `.wrap`, `#wpcontent`, `#wpbody-content`, `#wpfooter`, `#wpadminbar`, or `body`/`html` with a class or id attached. Anything else in an admin stylesheet fails `stray-admin-css`.
- **Never write a colour, a size, a shadow or a font by hand** in a file this phase touches. Use `var(--bw-…)` tokens. `px` is allowed only inside an `@media` query.
- **Never hand-draw an SVG.** Use `<i class="bw-icon" data-lucide="name"></i>`.
- **Never use these WordPress core classes:** `button-primary`, `button-secondary`, `form-table`, `wp-list-table`, `postbox`, `nav-tab`, `notice-success`, `notice-error`, `notice-warning`, `notice-info`. Each has a design system replacement.
- **Never use a `bw-` class the system does not define** — the checker reads its vocabulary from `styles.css`.
- **Never put a `style=` attribute on an element.**
- **British English, sentence case, no emoji, no exclamation marks.** Address the site owner as "you". Buttons are verbs.
- **Version and changelog:** one minor bump for the phase, on the final task.

---

## File structure

**Copied in, never edited:**

| Path | What |
| --- | --- |
| `.claude/skills/blueworx-admin-design/` | The whole skill folder, minus nothing |
| `assets/blueworx-admin-design.css` | Verbatim copy of that folder's `styles.css` |
| `assets/blueworx-admin-icons.js` | Verbatim copy of `assets/icons/lucide-icons.js` |
| `assets/fonts/sora-400.woff2`, `sora-600.woff2`, `sora-700.woff2` | From the skill folder's `fonts/` |

Inter 400/500/600 are already in `assets/fonts/` and already byte-identical to the foundation's — verified. Do not re-copy them.

**Created:**

| Path | Responsibility |
| --- | --- |
| `includes/admin/class-admin-shell.php` | The wrapper and page header every design system screen shares. Pure — builds a string, makes no WordPress calls. |
| `includes/admin/class-admin-assets.php` | Enqueues the stylesheet, the icon module and the chrome overrides for one screen. The only place those handles exist. |
| `assets/css/admin-chrome.css` | The four documented full-bleed overrides, and nothing else. |
| `tests/php/AdminShellTest.php` | Unit tests for the shell. |
| `tests/php/DesignSystemVendoredTest.php` | Proves the shipped copies match the skill folder. |

**Modified:** `includes/admin/class-access-screen.php`, `class-access-controller.php`, `class-guide-screen.php`, `class-guide-controller.php`, `class-seo-screen.php`, `class-seo-controller.php`, `class-changelog-screen.php`, `class-changelog-controller.php`, `includes/import/class-import-screen.php`, `class-import-controller.php`, `includes/bootstrap.php`.

**Not touched, on purpose:** `includes/admin/class-setup-*.php`, `class-content-*.php`, `assets/css/admin-setup.css`, `assets/css/admin-content.css`. Setup and Club Pages keep their current skin until phases 3 and 4 replace them.

---

### Task 1: Vendor the design system

**Files:**
- Create: `.claude/skills/blueworx-admin-design/` (copied tree)
- Create: `assets/blueworx-admin-design.css`, `assets/blueworx-admin-icons.js`
- Create: `assets/fonts/sora-400.woff2`, `assets/fonts/sora-600.woff2`, `assets/fonts/sora-700.woff2`
- Test: `tests/php/DesignSystemVendoredTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: the three shipped paths every later task enqueues, and the skill folder CI hashes them against.

- [ ] **Step 1: Write the failing test**

```php
<?php

use PHPUnit\Framework\TestCase;

/**
 * The shipped copies are what a site loads; the skill folder is what CI
 * compares against the foundation. If they drift, the plugin passes its own
 * suite while shipping something the design system never approved.
 */
final class DesignSystemVendoredTest extends TestCase {

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	public function test_the_shipped_stylesheet_is_the_skill_folder_stylesheet(): void {
		$skill    = $this->root() . '/.claude/skills/blueworx-admin-design/styles.css';
		$shipped  = $this->root() . '/assets/blueworx-admin-design.css';
		$this->assertFileExists( $skill );
		$this->assertFileExists( $shipped );
		$this->assertSame( sha1_file( $skill ), sha1_file( $shipped ) );
	}

	public function test_the_shipped_icons_are_the_skill_folder_icons(): void {
		$skill   = $this->root() . '/.claude/skills/blueworx-admin-design/assets/icons/lucide-icons.js';
		$shipped = $this->root() . '/assets/blueworx-admin-icons.js';
		$this->assertFileExists( $skill );
		$this->assertFileExists( $shipped );
		$this->assertSame( sha1_file( $skill ), sha1_file( $shipped ) );
	}

	/** styles.css loads its faces from beside itself, so they must be there. */
	public function test_every_design_system_font_ships_beside_the_stylesheet(): void {
		$dir = $this->root() . '/.claude/skills/blueworx-admin-design/fonts';
		foreach ( glob( $dir . '/*.woff2' ) as $face ) {
			$shipped = $this->root() . '/assets/fonts/' . basename( $face );
			$this->assertFileExists( $shipped, basename( $face ) . ' is missing from assets/fonts' );
			$this->assertSame( sha1_file( $face ), sha1_file( $shipped ), basename( $face ) . ' differs from the design system' );
		}
	}

	/**
	 * The page editor library is phase 3. Carrying one of its two artefacts
	 * without the other fails the foundation's sync check, so neither may
	 * appear yet.
	 */
	public function test_the_page_editor_library_is_not_vendored_yet(): void {
		$this->assertDirectoryDoesNotExist( $this->root() . '/blueworx-page-editor' );
		$this->assertFileDoesNotExist( $this->root() . '/assets/blueworx-page-editor.js' );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter DesignSystemVendoredTest`
Expected: FAIL — the skill folder and the shipped files do not exist.

- [ ] **Step 3: Copy the files in**

The foundation is checked out at `../bluegroup_core_foundation`. Confirm it is at the pinned ref first, then copy. Run from the Clubhouse repo root:

```bash
FOUNDATION=../bluegroup_core_foundation
git -C "$FOUNDATION" fetch --tags --quiet
git -C "$FOUNDATION" rev-parse v1.9.0
git -C "$FOUNDATION" status --porcelain   # must be empty

SKILL="$FOUNDATION/.claude/skills/blueworx-admin-design"
mkdir -p .claude/skills
rm -rf .claude/skills/blueworx-admin-design
cp -R "$SKILL" .claude/skills/blueworx-admin-design

# The editor library is phase 3, not this one.
rm -rf .claude/skills/blueworx-admin-design/editor

cp .claude/skills/blueworx-admin-design/styles.css assets/blueworx-admin-design.css
cp .claude/skills/blueworx-admin-design/assets/icons/lucide-icons.js assets/blueworx-admin-icons.js
cp .claude/skills/blueworx-admin-design/fonts/sora-*.woff2 assets/fonts/
```

**Stop and check before continuing.** Removing `editor/` from the copied skill folder makes the tree differ from the foundation's, which is exactly what `check-design-system-sync.mjs` fails on. Run the check locally:

```bash
FOUNDATION_DIR=../bluegroup_core_foundation FOUNDATION_REF=v1.9.0 \
  node ../bluegroup_core_foundation/scripts/check-design-system-sync.mjs
```

If it reports `editor/… — missing from this plugin`, do **not** delete `editor/`. Restore it and re-run:

```bash
cp -R "$SKILL/editor" .claude/skills/blueworx-admin-design/editor
```

The skill folder is compared whole, so it must be copied whole; what phase 3 adds is the *plugin-root* `blueworx-page-editor/` and `assets/blueworx-page-editor.js`, which are separate paths and stay absent here. Update `DesignSystemVendoredTest` only if this check proves otherwise; the two assertions in Step 1 about the page editor already name the plugin-root paths, so they stand either way.

- [ ] **Step 4: Run the test and the sync check**

Run: `vendor/bin/phpunit --filter DesignSystemVendoredTest`
Expected: PASS, 4 tests.

Run: `FOUNDATION_DIR=../bluegroup_core_foundation FOUNDATION_REF=v1.9.0 node ../bluegroup_core_foundation/scripts/check-design-system-sync.mjs`
Expected: a message saying the design system is in sync, exit 0.

- [ ] **Step 5: Check the fonts are real**

A `.woff2` corrupted in transit still looks like a font. Confirm each Sora face is a real WOFF2 by its magic bytes (`wOF2`):

```bash
for f in assets/fonts/sora-400.woff2 assets/fonts/sora-600.woff2 assets/fonts/sora-700.woff2; do
  printf '%s: ' "$f"; head -c 4 "$f"; echo
done
```
Expected: each line ends `wOF2`.

- [ ] **Step 6: Commit**

```bash
git add .claude/skills/blueworx-admin-design assets/blueworx-admin-design.css assets/blueworx-admin-icons.js assets/fonts/sora-400.woff2 assets/fonts/sora-600.woff2 assets/fonts/sora-700.woff2 tests/php/DesignSystemVendoredTest.php
git commit -m "Vendor the BlueWorx admin design system"
```

---

### Task 2: The shared shell and the asset loader

Every design system screen opens with the same wrapper and page header. Building it once means the five screens that follow differ only in their panels, and none of them can get the skeleton wrong.

**Files:**
- Create: `includes/admin/class-admin-shell.php`
- Create: `includes/admin/class-admin-assets.php`
- Create: `assets/css/admin-chrome.css`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/AdminShellTest.php`

**Interfaces:**
- Consumes: the shipped paths from Task 1.
- Produces:
  - `Blueworx_Clubhouse_Admin_Shell::open( string $eyebrow, string $title, string $lede = '', string $actions = '' ): string`
  - `Blueworx_Clubhouse_Admin_Shell::close(): string`
  - `Blueworx_Clubhouse_Admin_Assets::enqueue(): void` — takes no argument; each controller keeps its own `$hook` guard and calls this once past it

Tasks 3 to 7 call exactly these three.

- [ ] **Step 1: Write the failing test**

```php
<?php

use PHPUnit\Framework\TestCase;

final class AdminShellTest extends TestCase {

	public function test_it_opens_the_documented_skeleton(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'Clubhouse · Access', 'ClubHouse users and access' );
		$this->assertStringContainsString( 'class="wrap bw-wrap"', $out );
		$this->assertStringContainsString( 'class="bw-admin bw-page"', $out );
		$this->assertStringContainsString( 'class="bw-pagehead"', $out );
		$this->assertStringContainsString( 'class="bw-pagehead__eyebrow"', $out );
		$this->assertStringContainsString( 'class="bw-pagehead__h1"', $out );
		$this->assertStringContainsString( 'ClubHouse users and access', $out );
	}

	public function test_the_lede_and_actions_are_left_out_when_empty(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'Eyebrow', 'Title' );
		$this->assertStringNotContainsString( 'bw-pagehead__lede', $out );
		$this->assertStringNotContainsString( 'bw-pagehead__actions', $out );
	}

	public function test_the_lede_and_actions_appear_when_given(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'Eyebrow', 'Title', 'One sentence.', '<a class="bw-btn bw-btn--primary" href="/x">View site</a>' );
		$this->assertStringContainsString( 'class="bw-pagehead__lede">One sentence.', $out );
		$this->assertStringContainsString( 'bw-pagehead__actions', $out );
		$this->assertStringContainsString( 'View site', $out );
	}

	public function test_it_escapes_what_a_club_typed(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'E', 'Crewe <script>alert(1)</script>' );
		$this->assertStringNotContainsString( '<script>', $out );
		$this->assertStringContainsString( '&lt;script&gt;', $out );
	}

	public function test_actions_are_trusted_markup_and_not_escaped(): void {
		// Actions are built by the screen from its own strings, never from
		// stored content, so they are markup by contract.
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'E', 'T', '', '<a class="bw-btn" href="/x">Go</a>' );
		$this->assertStringContainsString( '<a class="bw-btn" href="/x">Go</a>', $out );
	}

	public function test_close_shuts_both_wrappers(): void {
		$this->assertSame( '</div></div>', Blueworx_Clubhouse_Admin_Shell::close() );
	}

	public function test_the_body_opens_a_single_column(): void {
		$out = Blueworx_Clubhouse_Admin_Shell::open( 'E', 'T' );
		$this->assertStringContainsString( 'class="bw-page__body bw-page__body--single"', $out );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter AdminShellTest`
Expected: FAIL with `Class "Blueworx_Clubhouse_Admin_Shell" not found`.

- [ ] **Step 3: Write the shell**

`includes/admin/class-admin-shell.php`:

```php
<?php
// includes/admin/class-admin-shell.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The opening of every Clubhouse admin screen built from the BlueWorx admin
 * design system: the full-bleed wrapper, and the page header.
 *
 * The system fixes this skeleton — page header, then panels, then (on an
 * editor) a save bar. Building it in one place is what stops five screens
 * each inventing their own version of it, which is how the old bespoke
 * screens drifted apart.
 *
 * Pure: it builds a string, makes no WordPress calls and reads no request
 * data, so it is testable without WordPress loaded.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Admin_Shell {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Open a screen.
	 *
	 * @param string $eyebrow Where the reader is, e.g. "Clubhouse · Access".
	 * @param string $title   The screen's name.
	 * @param string $lede    One sentence, or ''.
	 * @param string $actions Markup for the top-right actions, or ''. Built by
	 *                        the calling screen from its own strings — never
	 *                        from anything a club typed — so it is inserted as
	 *                        markup rather than escaped.
	 */
	public static function open( string $eyebrow, string $title, string $lede = '', string $actions = '' ): string {
		$out  = '<div class="wrap bw-wrap"><div class="bw-admin bw-page">';
		$out .= '<div class="bw-pagehead"><div class="bw-pagehead__titles">';
		$out .= '<p class="bw-pagehead__eyebrow">' . self::esc( $eyebrow ) . '</p>';
		$out .= '<h1 class="bw-pagehead__h1">' . self::esc( $title ) . '</h1>';
		if ( '' !== $lede ) {
			$out .= '<p class="bw-pagehead__lede">' . self::esc( $lede ) . '</p>';
		}
		$out .= '</div>';
		if ( '' !== $actions ) {
			$out .= '<div class="bw-pagehead__actions">' . $actions . '</div>';
		}
		$out .= '</div>';
		return $out . '<div class="bw-page__body bw-page__body--single">';
	}

	/** Close the body and the wrapper opened by open(). */
	public static function close(): string {
		return '</div></div>';
	}
}
```

- [ ] **Step 4: Run the test**

Run: `vendor/bin/phpunit --filter AdminShellTest`
Expected: PASS, 7 tests.

Note the shell opens three elements (`bw-wrap`, `bw-admin bw-page`, `bw-page__body`) and `close()` returns two closers, because the page header div is already closed inside `open()`. Read the string carefully if a screen's markup comes out unbalanced.

- [ ] **Step 5: Write the chrome overrides**

`assets/css/admin-chrome.css`. This is the **only** CSS this plugin may keep, and every selector in it must be one the adherence check allows. Five screens share it, so each needs its own `body.…` line — the page hooks are `settings_page_clubhouse-access`, `clubhouse_page_clubhouse-guide`, `clubhouse_page_clubhouse-seo`, `clubhouse_page_clubhouse-changelog` and `clubhouse_page_clubhouse-import`. **Confirm each hook** by running `error_log( $hook );` at the top of the relevant `enqueue()` and loading the screen, rather than trusting this list — a wrong body class silently leaves that screen boxed in.

```css
/* The full-bleed chrome overrides the design system documents, and nothing
   else. Anything more in this file is a second design system growing beside
   the first, and CI refuses it. */
.wrap.bw-wrap { margin: 0; }

body.settings_page_clubhouse-access #wpcontent,
body.clubhouse_page_clubhouse-guide #wpcontent,
body.clubhouse_page_clubhouse-seo #wpcontent,
body.clubhouse_page_clubhouse-changelog #wpcontent,
body.clubhouse_page_clubhouse-import #wpcontent { padding-left: 0; }

body.settings_page_clubhouse-access #wpbody-content,
body.clubhouse_page_clubhouse-guide #wpbody-content,
body.clubhouse_page_clubhouse-seo #wpbody-content,
body.clubhouse_page_clubhouse-changelog #wpbody-content,
body.clubhouse_page_clubhouse-import #wpbody-content { padding-bottom: 0; }

body.settings_page_clubhouse-access #wpfooter,
body.clubhouse_page_clubhouse-guide #wpfooter,
body.clubhouse_page_clubhouse-seo #wpfooter,
body.clubhouse_page_clubhouse-changelog #wpfooter,
body.clubhouse_page_clubhouse-import #wpfooter { display: none; }
```

- [ ] **Step 6: Write the asset loader**

`includes/admin/class-admin-assets.php`:

```php
<?php
// includes/admin/class-admin-assets.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The three files a Clubhouse admin screen built from the design system needs:
 * the system's stylesheet, its icon set, and our own full-bleed chrome
 * overrides.
 *
 * One place, so a new screen cannot half-load the system — a screen with the
 * stylesheet but not the icon module draws every [data-lucide] element as an
 * empty box, which looks like a layout bug rather than a missing file.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Admin_Assets {

	public const STYLE_HANDLE  = 'blueworx-admin-design';
	public const CHROME_HANDLE = 'blueworx-admin-chrome';
	public const ICONS_HANDLE  = 'blueworx-admin-icons';

	public static function enqueue(): void {
		$url = BLUEWORX_LABS_CLUBHOUSE_URL;
		$ver = BLUEWORX_LABS_CLUBHOUSE_VERSION;

		wp_enqueue_style( self::STYLE_HANDLE, $url . 'assets/blueworx-admin-design.css', array(), $ver );
		wp_enqueue_style( self::CHROME_HANDLE, $url . 'assets/css/admin-chrome.css', array( self::STYLE_HANDLE ), $ver );

		// A module, because the icon file is one: it upgrades every
		// [data-lucide] element in place and watches for new ones.
		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( self::ICONS_HANDLE, $url . 'assets/blueworx-admin-icons.js', array(), $ver );
			return;
		}
		// WordPress below 6.5 has no module API. A plain script with type=module
		// still runs, and the filter below is the supported way to set the type.
		wp_enqueue_script( self::ICONS_HANDLE, $url . 'assets/blueworx-admin-icons.js', array(), $ver, true );
		add_filter(
			'script_loader_tag',
			static function ( $tag, $handle ) {
				return self::ICONS_HANDLE === $handle ? str_replace( '<script ', '<script type="module" ', (string) $tag ) : $tag;
			},
			10,
			2
		);
	}
}
```

- [ ] **Step 7: Register both classes**

In `includes/bootstrap.php`, find where the other `includes/admin/class-*.php` files are required and add these two beside them, in the same style the file already uses:

```php
require_once __DIR__ . '/admin/class-admin-shell.php';
require_once __DIR__ . '/admin/class-admin-assets.php';
```

Read the surrounding lines first — if the file loads classes through a list or a loop rather than one `require_once` each, add them to that list instead.

- [ ] **Step 8: Run the whole suite**

Run: `vendor/bin/phpunit`
Expected: PASS. Nothing else changed behaviour, so any new failure is a bootstrap mistake.

- [ ] **Step 9: Commit**

```bash
git add includes/admin/class-admin-shell.php includes/admin/class-admin-assets.php assets/css/admin-chrome.css includes/bootstrap.php tests/php/AdminShellTest.php
git commit -m "Add the shared admin shell and its assets"
```

---

### Tasks 3 to 7: Convert the five screens

The five conversions are the same job five times, so the recipe is written once here and each task below says only what is particular to its screen. **Read this recipe before starting any of them.**

**The recipe:**

1. **Read the screen's existing test first** (`tests/php/<Name>ScreenTest.php` where one exists). It says what the screen must still do. Those assertions stay true; only the classes change.
2. **Add assertions to that test** for the new markup — a `bw-` class that must appear, and the legacy class that must not. Run it and watch it fail.
3. **Replace the markup**, using the mapping table below.
4. **Point the controller's `enqueue()` at `Blueworx_Clubhouse_Admin_Assets::enqueue()`** and delete its `wp_enqueue_style( 'clubhouse-admin-setup', … )` or `'clubhouse-admin-content'` line. Keep the `if ( $hook !== … ) return;` guard exactly as it is.
5. **Run the screen's tests, then the whole PHP suite.**
6. **Run the adherence check** (below). Fix every finding — they are errors, not advice.
7. **Look at the screen in a browser.** `npm run wp:up`, then the screen's address. A screen that passes every check and looks wrong is still wrong.
8. **Commit.**

**The class mapping:**

| Today | Becomes |
| --- | --- |
| `wrap clubhouse-wrap` + `clubhouse-setup` / `clubhouse-import` | `Admin_Shell::open()` / `::close()` |
| `clubhouse-head`, `clubhouse-head__titles`, `clubhouse-head__h1`, `clubhouse-eyebrow` | the page header inside `Admin_Shell::open()` — delete, do not translate |
| `clubhouse-step` | `bw-card` (with `bw-card__head`, `bw-card__titles`, `bw-card__body`) |
| `clubhouse-step__k` | `bw-card__eyebrow` |
| `clubhouse-step__h` | `bw-card__title` |
| `clubhouse-step__lede`, `clubhouse-help`, `description` | `bw-card__note` for a panel's one sentence; `bw-fieldnote` for a note under a control |
| `clubhouse-table`, `widefat striped` | `bw-table` |
| `clubhouse-table__name` | `bw-table__primary` |
| `clubhouse-table__sub` | `bw-table__sub` |
| `clubhouse-table__none` | `bw-empty` (with `bw-empty__icon`, `bw-empty__title`, `bw-empty__text`) |
| `clubhouse-chips`, `clubhouse-roletags` | `bw-chips` |
| `clubhouse-roletag`, `clubhouse-roletags__k` | `bw-chip` — or `bw-badge` where it carries status rather than naming a thing |
| `button button-primary` | `bw-btn bw-btn--primary` |
| `button` | `bw-btn bw-btn--secondary` |
| `notice notice-error` | `bw-notice bw-notice--danger` (with `bw-notice__icon`, `bw-notice__text`) |
| `clubhouse-release`, `clubhouse-release__notes` | `bw-card` |
| `clubhouse-guide-entry`, `clubhouse-guide-entry__head` | `bw-accordion` (with `bw-accordion__head`, `bw-accordion__title`, `bw-accordion__body`) |
| `clubhouse-guide-steps` | `bw-steps` (with `bw-step`, `bw-step__n`) |
| `clubhouse-import__step` | `bw-step` inside `bw-steps` |
| `clubhouse-import__off` | `bw-notice bw-notice--info` |

Nothing outside this table and the class list in `assets/blueworx-admin-design.css` is available. If a screen needs a pattern with no row here, **stop and ask** — the answer is to add it to the foundation, not to invent a class.

**Icons:** `<i class="bw-icon" data-lucide="users"></i>`. Sizes come from `bw-icon--14/18/20/22/28`. Names in use across these screens: `users`, `lock`, `search`, `megaphone`, `upload`, `circle-check`, `triangle-alert`, `info`, `external-link`.

**The adherence check**, run from the Clubhouse repo root after each conversion:

```bash
BASE_REF=main FOUNDATION_DIR=../bluegroup_core_foundation \
  node ../bluegroup_core_foundation/scripts/check-admin-ui-adherence.mjs
```

Expected: exit 0. It judges only files changed against `main`, which is why the untouched Setup and Club Pages files raise nothing.

---

### Task 3: ClubHouse access

**Files:**
- Modify: `includes/admin/class-access-screen.php` (149 lines), `includes/admin/class-access-controller.php:44-49`
- Test: `tests/php/AccessScreenTest.php`, `tests/access-chips.spec.js`

**Interfaces:**
- Consumes: `Admin_Shell::open()/close()`, `Admin_Assets::enqueue()`.
- Produces: nothing other tasks read.

Particulars: this screen is a table of roles and the people in them, plus the role chips. It is the screen proved unstyled in the browser, so it is the one to do first — the improvement is visible and it validates the shell before four more screens depend on it.

The header is `Clubhouse · Site access` over `ClubHouse users and access`. The role chips (`clubhouse-roletag`) become `bw-chip`; they name a role rather than carrying status.

- [ ] **Step 1:** Read `tests/php/AccessScreenTest.php` and `tests/access-chips.spec.js` in full. Note every assertion about text and structure — those must still pass.
- [ ] **Step 2:** Add to `AccessScreenTest.php`:

```php
	public function test_the_screen_is_built_from_the_design_system(): void {
		$out = Blueworx_Clubhouse_Access_Screen::render( $this->model() );
		$this->assertStringContainsString( 'class="wrap bw-wrap"', $out );
		$this->assertStringContainsString( 'bw-pagehead', $out );
		$this->assertStringContainsString( 'bw-card', $out );
	}

	public function test_no_legacy_or_core_classes_survive(): void {
		$out = Blueworx_Clubhouse_Access_Screen::render( $this->model() );
		foreach ( array( 'clubhouse-setup', 'clubhouse-step', 'clubhouse-head', 'clubhouse-table', 'clubhouse-roletag', 'button-primary', 'widefat' ) as $gone ) {
			$this->assertStringNotContainsString( $gone, $out, $gone . ' should be gone' );
		}
	}
```

Use whatever the existing test file already uses to build a model; if it has no helper, add a private `model()` returning the same array the existing tests pass to `render()`.

- [ ] **Step 3:** Run `vendor/bin/phpunit --filter AccessScreenTest`. Expected: the two new tests FAIL, the rest pass.
- [ ] **Step 4:** Convert the markup per the recipe. Replace the opening `'<div class="wrap clubhouse-wrap"><div class="clubhouse-setup">'` with `Blueworx_Clubhouse_Admin_Shell::open( 'Clubhouse · Site access', 'ClubHouse users and access' )`, and the closing divs with `Blueworx_Clubhouse_Admin_Shell::close()`.
- [ ] **Step 5:** In `class-access-controller.php`, replace the `wp_enqueue_style( 'clubhouse-admin-setup', … )` line with `Blueworx_Clubhouse_Admin_Assets::enqueue();`.
- [ ] **Step 6:** Run `vendor/bin/phpunit --filter AccessScreenTest`, then `vendor/bin/phpunit`. Expected: PASS.
- [ ] **Step 7:** Run the adherence check. Expected: exit 0.
- [ ] **Step 8:** `npm run wp:up`, open `/wp-admin/options-general.php?page=clubhouse-access`, and confirm the panel has a white background, the Sora heading and the indigo accent. Then run `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8705 WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test tests/access-chips.spec.js --workers=1`. Expected: PASS.
- [ ] **Step 9:** Commit.

```bash
git add includes/admin/class-access-screen.php includes/admin/class-access-controller.php tests/php/AccessScreenTest.php
git commit -m "Build ClubHouse access from the design system"
```

---

### Task 4: User guide

**Files:**
- Modify: `includes/admin/class-guide-screen.php` (101 lines), `includes/admin/class-guide-controller.php:53`
- Test: `tests/php/GuideTest.php`, `tests/user-guide.spec.js`

**Interfaces:** consumes the shell and the assets, as Task 3.

Particulars: the guide is a list of entries, each with a heading and steps — `clubhouse-guide-entry` becomes `bw-accordion` and `clubhouse-guide-steps` becomes `bw-steps`. Header: `Clubhouse · User guide` over `How ClubHouse works`, lede `Built from this site as it stands, so it describes what you actually have.`

Follow the recipe. The two new tests mirror Task 3 Step 2, with `Blueworx_Clubhouse_Guide_Screen::render()` and the legacy list `clubhouse-setup`, `clubhouse-step`, `clubhouse-head`, `clubhouse-guide-entry`, `clubhouse-guide-steps`, `button-primary`.

- [ ] **Step 1:** Read `tests/php/GuideTest.php` and `tests/user-guide.spec.js`.
- [ ] **Step 2:** Add the two assertions above; run and watch them fail.
- [ ] **Step 3:** Convert the markup.
- [ ] **Step 4:** Point the controller at `Admin_Assets::enqueue()`.
- [ ] **Step 5:** `vendor/bin/phpunit`, then the adherence check, then the browser and `npx playwright test tests/user-guide.spec.js`.
- [ ] **Step 6:** Commit — `git commit -m "Build the user guide from the design system"`.

---

### Task 5: Search and sharing

**Files:**
- Modify: `includes/admin/class-seo-screen.php` (96 lines), `includes/admin/class-seo-controller.php:45`
- Test: `tests/php/SeoTest.php`, `tests/seo.spec.js`

**Interfaces:** consumes the shell and the assets, as Task 3.

Particulars: a table of pages against how each reads in search. `clubhouse-table` becomes `bw-table`, and the empty state becomes `bw-empty`. Header: `Clubhouse · Search and sharing` over `Search and sharing`, lede `How each page reads in search results and when it is shared.`

Follow the recipe; legacy list `clubhouse-setup`, `clubhouse-step`, `clubhouse-head`, `clubhouse-table`, `widefat`, `button-primary`.

- [ ] **Step 1:** Read `tests/php/SeoTest.php` and `tests/seo.spec.js`.
- [ ] **Step 2:** Add the two assertions; run and watch them fail.
- [ ] **Step 3:** Convert the markup.
- [ ] **Step 4:** Point the controller at `Admin_Assets::enqueue()`.
- [ ] **Step 5:** `vendor/bin/phpunit`, adherence check, browser, `npx playwright test tests/seo.spec.js`.
- [ ] **Step 6:** Commit — `git commit -m "Build search and sharing from the design system"`.

---

### Task 6: What's new

**Files:**
- Modify: `includes/admin/class-changelog-screen.php` (102 lines), `includes/admin/class-changelog-controller.php:48`
- Test: `tests/php/ChangelogScreenTest.php`

**Interfaces:** consumes the shell and the assets, as Task 3.

Particulars: one card per release. `clubhouse-release` becomes `bw-card` and `clubhouse-release__notes` its body. The version number is a figure, so it belongs in `bw-card__eyebrow`. Header: `Clubhouse · What's new` over `What's new`, lede `What each update changed, in plain English.`

There is no Playwright spec for this screen, so the browser check in Step 5 is the only end-to-end proof — do not skip it.

- [ ] **Step 1:** Read `tests/php/ChangelogScreenTest.php`.
- [ ] **Step 2:** Add the two assertions; legacy list `clubhouse-setup`, `clubhouse-step`, `clubhouse-head`, `clubhouse-release`, `button-primary`. Run and watch them fail.
- [ ] **Step 3:** Convert the markup.
- [ ] **Step 4:** Point the controller at `Admin_Assets::enqueue()`.
- [ ] **Step 5:** `vendor/bin/phpunit`, adherence check, then open the screen in the browser and read a release entry top to bottom.
- [ ] **Step 6:** Commit — `git commit -m "Build What's new from the design system"`.

---

### Task 7: Import

**Files:**
- Modify: `includes/import/class-import-screen.php` (200 lines), `includes/import/class-import-controller.php:66`
- Test: `tests/php/ImportScreenTest.php`

**Interfaces:** consumes the shell and the assets, as Task 3.

Particulars: the largest of the five, and the only one enqueuing `admin-content.css` rather than `admin-setup.css`. It is a stepped flow — `clubhouse-import__step` becomes `bw-step` inside `bw-steps` — with a preview table (`bw-table`) and an off state (`clubhouse-import__off` → `bw-notice bw-notice--info`). Header: `Clubhouse · Import` over `Import your content`, lede `Bring a club's existing content in, under Clubhouse.`

`admin-content.css` is still used by Club Pages after this task, so **do not delete it**.

- [ ] **Step 1:** Read `tests/php/ImportScreenTest.php` in full — it is the largest of the five test files and the flow has several states.
- [ ] **Step 2:** Add the two assertions; legacy list `clubhouse-import`, `clubhouse-step`, `clubhouse-head`, `clubhouse-table`, `widefat`, `button-primary`, `notice-error`. Run and watch them fail.
- [ ] **Step 3:** Convert the markup, one state at a time — the empty state, the preview, the applied state — running the tests between each.
- [ ] **Step 4:** Point the controller at `Admin_Assets::enqueue()`.
- [ ] **Step 5:** `vendor/bin/phpunit`, adherence check, then walk the import flow in the browser from empty to preview.
- [ ] **Step 6:** Commit — `git commit -m "Build the import screen from the design system"`.

---

### Task 8: Close the phase

**Files:**
- Modify: `blueworx-labs-clubhouse.php:6`, `blueworx-labs-clubhouse.php:24`, `package.json:3`, `CHANGELOG.md`, `docs/priorities.md`

- [ ] **Step 1: Confirm nothing was missed**

```bash
grep -rn "clubhouse-admin-setup\|clubhouse-admin-content" includes/
```
Expected: only `class-setup-controller.php` and `class-content-controller.php` — the two screens phases 3 and 4 replace. Any other hit is a screen this phase should have converted.

- [ ] **Step 2: Run everything**

```bash
vendor/bin/phpunit
composer lint
BASE_REF=main FOUNDATION_DIR=../bluegroup_core_foundation node ../bluegroup_core_foundation/scripts/check-admin-ui-adherence.mjs
FOUNDATION_DIR=../bluegroup_core_foundation FOUNDATION_REF=v1.9.0 node ../bluegroup_core_foundation/scripts/check-design-system-sync.mjs
npm run test:wp
```

Expected: all pass, **except** the eleven pre-existing failures recorded in [#288](../../../../issues/288) — the demo accent cookie tests and the hero heading line-break tests. Confirm the failures you see are exactly those eleven and no others. If a twelfth appears, it is yours.

Per the project's linting rule: run `composer lint` once, and bring any findings to Luke rather than fixing them in a loop.

- [ ] **Step 3: Bump the version**

Minor — this is new behaviour, not a fix. From 0.96.0 to 0.97.0 (confirm the current value first; #281 may not be the last thing merged).

```bash
sed -i "s/0\.96\.0/0.97.0/" blueworx-labs-clubhouse.php package.json
grep -n "0\.97\.0" blueworx-labs-clubhouse.php package.json
```
Expected: three lines — the plugin header, the version constant, and `package.json`.

- [ ] **Step 4: Write the changelog**

At the top of `CHANGELOG.md`, above the previous release. Write for a club owner, in their words — what changed for them, not what we did:

```markdown
## 0.97.0

- **The Clubhouse screens in your dashboard have a new look.** Access, the user guide, search and sharing, what's new, and import now share one design, the same on every club's site.
- **Four of those screens were unstyled and are not any more.** Access, the user guide, search and sharing and what's new were rendering as plain WordPress rather than as Clubhouse screens. They now look like the rest.
- **Your Base Look no longer changes your dashboard.** It shapes your website, as it always has; the screens you edit from are ours. Setup and Club Pages follow in the next two updates.
```

- [ ] **Step 5: Update the priority list**

`docs/priorities.md` is dated 27 August 2026 and is out of date — #261 and #268 are closed but still listed as next. Correct it as part of this phase: mark those closed, and add the six-phase editor project with phase 2 done.

- [ ] **Step 6: Commit and open the pull request**

```bash
git add -A
git commit -m "Take the design system into the admin"
git push -u origin design-system-adoption
gh pr create --title "Take the design system into the admin" --body "Vendors the BlueWorx admin design system and rebuilds the five admin screens that the page editor library will not replace: access, user guide, search and sharing, what's new, and import.

Four of those five were rendering unstyled — they load a stylesheet written entirely in look tokens that they never supplied. That is #287, and this closes it.

Setup and Club Pages are deliberately untouched. The page editor library replaces both in phases 3 and 4, so restyling them by hand now would be thrown away. #282 stays open until then.

Closes #287
Part of #282

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

---

## Self-review

**Spec coverage.** §6 (the look leaves wp-admin) — Tasks 3 to 7 for five screens, with the last two named as phases 3 and 4. §5 (the remaining screens) — Tasks 3 to 7 cover Import, Search and sharing, User guide, What's new and Access, which is the whole list. §8 phase 2 (vendor the design system) — Task 1. The spec's §2, §3, §4 and §7 belong to later phases and are correctly absent.

**One gap, named rather than hidden.** The spec says `admin-content.css` and `admin-setup.css` are deleted. This phase deletes neither, because Setup and Club Pages still load them. They go in phases 3 and 4 with the screens that use them. This is a sequencing consequence of leaving those two screens alone, not a change of intent.

**A second, smaller one.** `includes/admin/class-admin-menu-icons.php` prints an SVG on `admin_head` for the menu icon. It would fail the `hand-svg` rule if touched. It is not touched by this phase, so it is not judged — but the next phase that edits it must convert it. Recorded here so it is not a surprise.

**Placeholders.** None. Every step names its file, its command and its expected output. Tasks 4 to 7 refer to the shared recipe rather than repeating it — a deliberate exception to "repeat the code", because the recipe is eight steps of process, not code, and five copies of it would drift.

**Type consistency.** `Admin_Shell::open( string, string, string, string ): string` and `::close(): string` are declared in Task 2 and called with those signatures in Tasks 3 to 7. `Admin_Assets::enqueue(): void` takes no argument — each controller keeps its own `$hook` guard and calls it once past that.

**One thing the implementer must verify rather than trust.** The five admin page hooks in `assets/css/admin-chrome.css` are written from the plugin's menu registration, not observed. Task 2 Step 5 says to confirm each one against the running site. A wrong body class costs that screen its full-bleed layout and nothing will fail to tell you.
