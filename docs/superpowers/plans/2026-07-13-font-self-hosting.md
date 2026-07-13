# Font Self-Hosting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Serve every Base Look's fonts from files bundled in the plugin instead of the Google Fonts CDN, with no visible change.

**Architecture:** Each Base Look's `fonts()` metadata gains a filename `stem`; a new pure `Page_Renderer::font_face_css()` generates `@font-face` rules pointing at bundled `assets/fonts/{stem}-{weight}.woff2`, replacing `google_fonts_url()`. `Page_Renderer::document()` and `Frontend` stop emitting the Google stylesheet link and `preconnect` hints and inject the generated CSS inline. The DB-free preview inherits the change through the shared `document()` caller.

**Tech Stack:** PHP 8.2 (strict types), PHPUnit 11, Playwright (`@playwright/test`), plain CSS `@font-face`, woff2 font files (latin subset, static weights).

## Global Constraints

- PHP 8.2, `declare(strict_types=1)` in every PHP file — copied from existing files.
- No new npm or Composer dependencies (`approved-deps.json`) — fonts are fetched at build time with `curl`/`unzip`, not added as packages.
- Fonts: **woff2 only, latin subset, exactly the declared weights**. No woff/ttf, no variable fonts, no non-latin subsets.
- Outcome invariant: after this work, a grep for `googleapis` or `gstatic` across `includes/` returns nothing.
- All six families are **SIL OFL 1.1**; each family's `OFL.txt` must be bundled.
- Version bump on the PR (minor: **0.20.0 → 0.21.0**) with a matching `CHANGELOG.md` entry.
- Run PHP tests with `composer test` (`phpunit`); run browser tests with `npx playwright test`.
- File path convention: `assets/fonts/{stem}-{weight}.woff2` (e.g. `assets/fonts/syne-700.woff2`).
- Base-URL convention: callers pass a base **with a trailing slash** (matches the existing `$plugin_url . $look->stylesheet()` pattern); `font_face_css()` concatenates `$base_url . 'assets/fonts/…'` — do NOT add a second slash.

### Font manifest (single source of truth for the whole plan)

| Look | Family | stem | weights |
|------|--------|------|---------|
| Court Side | Syne | `syne` | 600, 700, 800 |
| Court Side | Inter | `inter` | 400, 500, 600 |
| Members' House | Fraunces | `fraunces` | 400, 500, 600, 700 |
| Members' House | Mulish | `mulish` | 400, 500, 600, 700 |
| Floodlight | Bricolage Grotesque | `bricolage-grotesque` | 500, 600, 700, 800 |
| Floodlight | Hanken Grotesk | `hanken-grotesk` | 400, 500, 600, 700 |

22 woff2 files total.

---

### Task 1: Extend `fonts()` metadata with a filename `stem`

Add a `stem` to each font entry so file paths derive deterministically, and update the interface contract and the test fake to match. No behaviour changes yet.

**Files:**
- Modify: `includes/theme/interface-base-look.php:32-37` (docblock)
- Modify: `includes/looks/class-court-side.php:47-51`
- Modify: `includes/looks/class-members-house.php:48-52`
- Modify: `includes/looks/class-floodlight.php:48-52`
- Modify: `tests/php/fakes/class-fake-base-look.php:37-43`
- Test: `tests/php/BaseLookContractTest.php:24-29`

**Interfaces:**
- Consumes: nothing.
- Produces: each `fonts()` entry now has shape `array{family:string, stem:string, weights:array<int,int>, display:string}`. Task 2 reads `stem` and `weights`.

- [ ] **Step 1: Add the failing assertion**

In `tests/php/BaseLookContractTest.php`, extend `test_fonts_and_stylesheet` (line 24) to require the new key:

```php
	public function test_fonts_and_stylesheet(): void {
		$look = new Blueworx_Clubhouse_Fake_Look();
		$this->assertNotEmpty( $look->fonts() );
		$this->assertArrayHasKey( 'family', $look->fonts()[0] );
		$this->assertArrayHasKey( 'stem', $look->fonts()[0] );
		$this->assertStringEndsWith( '.css', $look->stylesheet() );
	}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `composer test -- --filter test_fonts_and_stylesheet`
Expected: FAIL — `Failed asserting that an array has the key 'stem'`.

- [ ] **Step 3: Add `stem` to the fake and the interface docblock**

In `tests/php/fakes/class-fake-base-look.php`, update the docblock (line 37) and `fonts()` (lines 38-43):

```php
	/** @return array<int,array{family:string,stem:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Syne', 'stem' => 'syne', 'weights' => array( 600, 700, 800 ), 'display' => 'swap' ),
			array( 'family' => 'Inter', 'stem' => 'inter', 'weights' => array( 400, 500, 600 ), 'display' => 'swap' ),
		);
	}
```

In `includes/theme/interface-base-look.php`, update the `fonts()` docblock (lines 32-37):

```php
	/**
	 * Loadable font assets for this look.
	 *
	 * @return array<int, array{family:string, stem:string, weights:array<int,int>, display:string}>
	 */
	public function fonts(): array;
```

- [ ] **Step 4: Add `stem` to the three real looks**

`includes/looks/class-court-side.php` (lines 47-51):

```php
	/** @return array<int,array{family:string,stem:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Syne', 'stem' => 'syne', 'weights' => array( 600, 700, 800 ), 'display' => 'swap' ),
			array( 'family' => 'Inter', 'stem' => 'inter', 'weights' => array( 400, 500, 600 ), 'display' => 'swap' ),
		);
	}
```

`includes/looks/class-members-house.php` (lines 48-52):

```php
	/** @return array<int,array{family:string,stem:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Fraunces', 'stem' => 'fraunces', 'weights' => array( 400, 500, 600, 700 ), 'display' => 'swap' ),
			array( 'family' => 'Mulish', 'stem' => 'mulish', 'weights' => array( 400, 500, 600, 700 ), 'display' => 'swap' ),
		);
	}
```

`includes/looks/class-floodlight.php` (lines 48-52):

```php
	/** @return array<int,array{family:string,stem:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Bricolage Grotesque', 'stem' => 'bricolage-grotesque', 'weights' => array( 500, 600, 700, 800 ), 'display' => 'swap' ),
			array( 'family' => 'Hanken Grotesk', 'stem' => 'hanken-grotesk', 'weights' => array( 400, 500, 600, 700 ), 'display' => 'swap' ),
		);
	}
```

- [ ] **Step 5: Run the whole PHP suite**

Run: `composer test`
Expected: PASS (all existing tests plus the new assertion).

- [ ] **Step 6: Commit**

```bash
git add includes/theme/interface-base-look.php includes/looks/class-court-side.php includes/looks/class-members-house.php includes/looks/class-floodlight.php tests/php/fakes/class-fake-base-look.php tests/php/BaseLookContractTest.php
git commit -m "feat: add filename stem to Base Look fonts() metadata"
```

---

### Task 2: `Page_Renderer::font_face_css()` + migrate `document()`

Add the pure `@font-face` generator, switch `document()` to it, and drop the Google `<link>`/`preconnect` tags from the document head. **Leave `google_fonts_url()` defined for now** — `Frontend::enqueue_specs()` still calls it until Task 3, so removing it here would red the suite. Task 3 deletes it. The DB-free preview already calls `document(... , '/')`, so it inherits the head change automatically — its font assertions are updated in this task.

**Files:**
- Modify: `includes/render/class-page-renderer.php:19-26` (add `font_face_css` — keep `google_fonts_url`)
- Modify: `includes/render/class-page-renderer.php:28-48` (`document()` head)
- Test: `tests/php/PageRendererTest.php:15-21` (replace the URL test)
- Test: `tests/php/PageRendererTest.php:23-36` (update the document-head test)
- Test: `tests/php/PreviewRenderTest.php:53-55,66-68` (swap the `family=` assertions for self-hosted paths)

**Interfaces:**
- Consumes: `fonts()` entries with `stem` + `weights` (Task 1).
- Produces: `Page_Renderer::font_face_css( Blueworx_Clubhouse_Base_Look $look, string $base_url ): string` — returns one `@font-face{…}` rule per declared weight, no wrapping `<style>`. Task 3 (Frontend) calls it.

- [ ] **Step 1: Replace the failing unit test**

In `tests/php/PageRendererTest.php`, replace `test_google_fonts_url_lists_both_families` (lines 15-21) with:

```php
	public function test_font_face_css_emits_a_rule_per_weight(): void {
		$css = Blueworx_Clubhouse_Page_Renderer::font_face_css(
			new Blueworx_Clubhouse_Court_Side(),
			'/wp-content/plugins/clubhouse/'
		);
		// One @font-face per declared weight: Syne 600/700/800 + Inter 400/500/600 = 6.
		$this->assertSame( 6, substr_count( $css, '@font-face' ) );
		$this->assertStringContainsString( "font-family:'Syne'", $css );
		$this->assertStringContainsString( 'font-weight:700', $css );
		$this->assertStringContainsString( 'font-display:swap', $css );
		$this->assertStringContainsString(
			"src:url(/wp-content/plugins/clubhouse/assets/fonts/syne-700.woff2) format('woff2')",
			$css
		);
		$this->assertStringContainsString(
			"src:url(/wp-content/plugins/clubhouse/assets/fonts/inter-400.woff2) format('woff2')",
			$css
		);
		$this->assertStringNotContainsString( 'googleapis', $css );
	}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `composer test -- --filter test_font_face_css_emits_a_rule_per_weight`
Expected: FAIL — `Call to undefined method …::font_face_css()`.

- [ ] **Step 3: Implement `font_face_css()` (keep `google_fonts_url()`)**

In `includes/render/class-page-renderer.php`, add this method immediately **after** the existing `google_fonts_url()` method (do NOT delete `google_fonts_url()` — Task 3 removes it once `Frontend` no longer calls it):

```php
	public static function font_face_css( Blueworx_Clubhouse_Base_Look $look, string $base_url ): string {
		$css = '';
		foreach ( $look->fonts() as $font ) {
			$stem    = $font['stem'];
			$display = $font['display'];
			foreach ( $font['weights'] as $weight ) {
				$css .= "@font-face{font-family:'" . $font['family'] . "';"
					. 'font-style:normal;'
					. 'font-weight:' . (int) $weight . ';'
					. 'font-display:' . $display . ';'
					. 'src:url(' . $base_url . 'assets/fonts/' . $stem . '-' . $weight . '.woff2) format(\'woff2\')}';
			}
		}
		return $css;
	}
```

- [ ] **Step 4: Migrate `document()` head**

In `includes/render/class-page-renderer.php`, update `document()` (lines 34-47). Replace the `$font = …google_fonts_url…` line and the three font `<link>`/`preconnect` lines. New body:

```php
		$vars     = Blueworx_Clubhouse_Theme_Css::compose( $look, $branding );
		$css      = Blueworx_Clubhouse_Theme_Css::to_css( $vars );
		$faces    = self::font_face_css( $look, $plugin_url );
		$sheet    = htmlspecialchars( $plugin_url . $look->stylesheet(), ENT_QUOTES, 'UTF-8' );

		return '<!doctype html><html lang="en"><head>'
			. '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . htmlspecialchars( $branding->get_club_name(), ENT_QUOTES, 'UTF-8' ) . '</title>'
			. '<style>' . $faces . '</style>'
			. '<link rel="stylesheet" href="' . $sheet . '">'
			. '<style>' . $css . '</style>'
			. '</head><body>' . $body . self::reveal_script() . '</body></html>';
```

(The `$plugin_url` parameter is unescaped in the `src:url(...)` — it is a server-provided plugin/preview root, not user input, exactly as the existing `$sheet` concatenation trusts it. It is placed inside a `<style>` block, and `font_face_css` emits only the manifest's fixed family names and integer weights.)

- [ ] **Step 5: Update the document-head test**

In `tests/php/PageRendererTest.php`, update `test_document_head_carries_tokens_fonts_and_stylesheet` (line 33). Replace:

```php
		$this->assertStringContainsString( 'fonts.googleapis.com', $doc );
```

with:

```php
		$this->assertStringContainsString( '@font-face', $doc );
		$this->assertStringContainsString(
			"src:url(/wp-content/plugins/clubhouse/assets/fonts/syne-700.woff2) format('woff2')",
			$doc
		);
		$this->assertStringNotContainsString( 'googleapis', $doc );
```

- [ ] **Step 6: Update the preview render test**

`tests/php/PreviewRenderTest.php` asserts each look's Google `family=` query string appears in the preview document — proving the correct look's fonts are emitted. Now that the preview emits self-hosted `@font-face` `src` URLs (base `/`), update those assertions.

Replace lines 54-55 (in `test_look_param_switches_to_floodlight`):

```php
		$this->assertStringContainsString( '/assets/fonts/bricolage-grotesque-', $html );
		$this->assertStringContainsString( '/assets/fonts/hanken-grotesk-', $html );
```

Replace lines 67-68 (in `test_look_param_switches_to_members_house`):

```php
		$this->assertStringContainsString( '/assets/fonts/fraunces-', $html );
		$this->assertStringContainsString( '/assets/fonts/mulish-', $html );
```

- [ ] **Step 7: Run the PHP suite**

Run: `composer test`
Expected: PASS (the full suite). `google_fonts_url()` is still defined and still called by `Frontend`, so `FrontendTest` stays green; Task 3 removes it.

- [ ] **Step 8: Commit**

```bash
git add includes/render/class-page-renderer.php tests/php/PageRendererTest.php tests/php/PreviewRenderTest.php
git commit -m "feat: generate self-hosted @font-face CSS; drop Google Fonts link from document()"
```

---

### Task 3: Wire `Frontend` enqueue and drop the Google preconnect hints

Switch the WordPress enqueue path to inline the generated `@font-face` CSS and stop advertising the Google origins.

**Files:**
- Modify: `includes/frontend/class-frontend.php:38-52` (`enqueue_specs` + its docblock)
- Modify: `includes/frontend/class-frontend.php:62-72` (`resource_hints`)
- Modify: `includes/frontend/class-frontend.php:132-149` (`enqueue_assets`)
- Modify: `includes/render/class-page-renderer.php` (delete the now-unused `google_fonts_url()`)
- Test: `tests/php/FrontendTest.php:30-40` (`test_register_registers_expected_hooks` — drop the `wp_resource_hints` assertion)
- Test: `tests/php/FrontendTest.php:76-84` (`test_enqueue_specs_shape`)

**Interfaces:**
- Consumes: `Page_Renderer::font_face_css()` (Task 2).
- Produces: `enqueue_specs()` returns `array{font_face_css:string, stylesheet_url:string, inline_css:string, reveal_url:string}` (was `fonts_url`).

- [ ] **Step 1: Update the failing test**

In `tests/php/FrontendTest.php`, replace `test_enqueue_specs_shape` (lines 76-84):

```php
	public function test_enqueue_specs_shape(): void {
		$look  = new Blueworx_Clubhouse_Court_Side();
		$specs = Blueworx_Clubhouse_Frontend::enqueue_specs( $look, ':root{--x:1}', 'https://club.test/wp-content/plugins/clubhouse/' );

		$this->assertStringContainsString( '@font-face', $specs['font_face_css'] );
		$this->assertStringContainsString(
			"src:url(https://club.test/wp-content/plugins/clubhouse/assets/fonts/syne-700.woff2) format('woff2')",
			$specs['font_face_css']
		);
		$this->assertStringNotContainsString( 'googleapis', $specs['font_face_css'] );
		$this->assertSame( 'https://club.test/wp-content/plugins/clubhouse/assets/looks/court-side.css', $specs['stylesheet_url'] );
		$this->assertSame( ':root{--x:1}', $specs['inline_css'] );
		$this->assertSame( 'https://club.test/wp-content/plugins/clubhouse/assets/js/reveal.js', $specs['reveal_url'] );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter test_enqueue_specs_shape`
Expected: FAIL — `Undefined array key "font_face_css"`.

- [ ] **Step 3: Update `enqueue_specs`**

In `includes/frontend/class-frontend.php`, update the docblock (line 39) and return array (lines 46-51):

```php
	/**
	 * @return array{font_face_css:string,stylesheet_url:string,inline_css:string,reveal_url:string}
	 */
	public static function enqueue_specs(
		Blueworx_Clubhouse_Base_Look $look,
		string $root_css,
		string $plugin_url
	): array {
		return array(
			'font_face_css'  => Blueworx_Clubhouse_Page_Renderer::font_face_css( $look, $plugin_url ),
			'stylesheet_url' => $plugin_url . $look->stylesheet(),
			'inline_css'     => $root_css,
			'reveal_url'     => $plugin_url . 'assets/js/reveal.js',
		);
	}
```

- [ ] **Step 4: Drop the Google preconnect hints**

In `includes/frontend/class-frontend.php`, replace `resource_hints` (lines 66-72) — remove the whole `googleapis`/`gstatic` branch. Also remove its registration on line 59.

Delete line 59 (`add_filter( 'wp_resource_hints', … )`) from `register()`, and delete the `resource_hints` method entirely (lines 62-72). Self-hosted fonts share the site origin, so no cross-origin preconnect is warranted.

Because the `wp_resource_hints` filter is no longer registered, update `test_register_registers_expected_hooks` in `tests/php/FrontendTest.php` (line 39) by **removing** this now-false assertion:

```php
		$this->assertContains( 'wp_resource_hints', $filters );
```

The remaining assertions in that test (`init`, `wp_enqueue_scripts`, `template_include`) stay.

- [ ] **Step 5: Update `enqueue_assets`**

In `includes/frontend/class-frontend.php`, replace the enqueue block (lines 145-148):

```php
		wp_enqueue_style( 'clubhouse-look', $specs['stylesheet_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_add_inline_style( 'clubhouse-look', $specs['font_face_css'], 'before' );
		wp_add_inline_style( 'clubhouse-look', $specs['inline_css'] );
		wp_enqueue_script( 'clubhouse-reveal', $specs['reveal_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
```

(The `clubhouse-fonts` Google stylesheet enqueue on line 145 is removed; the `@font-face` rules are attached `'before'` the look CSS on the same handle.)

- [ ] **Step 6: Add a regression test for the dropped hints**

In `tests/php/FrontendTest.php`, add:

```php
	public function test_no_google_font_origins_are_referenced(): void {
		$look  = new Blueworx_Clubhouse_Court_Side();
		$specs = Blueworx_Clubhouse_Frontend::enqueue_specs( $look, ':root{}', 'https://club.test/wp/' );
		$this->assertStringNotContainsString( 'gstatic', $specs['font_face_css'] );
		$this->assertFalse( method_exists( Blueworx_Clubhouse_Frontend::class, 'resource_hints' ) );
	}
```

- [ ] **Step 7: Delete the now-unused `google_fonts_url()`**

`Frontend` no longer calls it and `document()` was migrated in Task 2, so `google_fonts_url()` is now dead code. In `includes/render/class-page-renderer.php`, delete the entire `google_fonts_url()` method (the `public static function google_fonts_url( … ) { … }` block). This also clears the last `googleapis` reference from `includes/`.

- [ ] **Step 8: Run the PHP suite**

Run: `composer test`
Expected: PASS. (No test should reference `google_fonts_url` any longer — Task 2 replaced the only direct test, and this task drops the `fonts.googleapis.com` assertion in `test_enqueue_specs_shape`.)

- [ ] **Step 9: Commit**

```bash
git add includes/frontend/class-frontend.php includes/render/class-page-renderer.php tests/php/FrontendTest.php
git commit -m "feat: inline self-hosted @font-face in enqueue; drop Google preconnect hints and google_fonts_url"
```

---

### Task 4: Acquire and bundle the 22 woff2 files + OFL licenses

Fetch the latin-subset static cuts from google-webfonts-helper and the licenses from the Google Fonts repo, into `assets/fonts/`. A PHPUnit guard ties the manifest to the files on disk.

**Files:**
- Create: `assets/fonts/{stem}-{weight}.woff2` × 22 (per the manifest table)
- Create: `assets/fonts/licenses/{family}-OFL.txt` × 6
- Create: `tests/php/FontAssetsTest.php`

**Interfaces:**
- Consumes: the `fonts()` manifest (Tasks 1).
- Produces: the physical font files Task 5's Playwright test loads.

- [ ] **Step 1: Write the failing asset-existence guard**

Create `tests/php/FontAssetsTest.php`:

```php
<?php
// tests/php/FontAssetsTest.php

use PHPUnit\Framework\TestCase;

final class FontAssetsTest extends TestCase {

	/** @return array<int,Blueworx_Clubhouse_Base_Look> */
	private function looks(): array {
		return array(
			new Blueworx_Clubhouse_Court_Side(),
			new Blueworx_Clubhouse_Members_House(),
			new Blueworx_Clubhouse_Floodlight(),
		);
	}

	public function test_every_declared_weight_has_a_bundled_woff2(): void {
		$root = dirname( __DIR__, 2 );
		foreach ( $this->looks() as $look ) {
			foreach ( $look->fonts() as $font ) {
				foreach ( $font['weights'] as $weight ) {
					$path = $root . '/assets/fonts/' . $font['stem'] . '-' . $weight . '.woff2';
					$this->assertFileExists( $path );
					$this->assertGreaterThan( 1000, (int) filesize( $path ), "$path looks empty" );
				}
			}
		}
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter test_every_declared_weight_has_a_bundled_woff2`
Expected: FAIL — `Failed asserting that file "…/assets/fonts/syne-600.woff2" exists`.

- [ ] **Step 3: Download and rename the woff2 files**

From the plugin root, run this script. It pulls each family's latin woff2 cuts as a zip from google-webfonts-helper, then renames to the `{stem}-{weight}.woff2` convention (gwfh names weight 400 `regular`).

```bash
set -euo pipefail
mkdir -p assets/fonts/licenses tmp-fonts
cd tmp-fonts

# id|stem|variants (gwfh uses "regular" for 400)
FONTS="
syne|syne|600,700,800
inter|inter|regular,500,600
fraunces|fraunces|regular,500,600,700
mulish|mulish|regular,500,600,700
bricolage-grotesque|bricolage-grotesque|500,600,700,800
hanken-grotesk|hanken-grotesk|regular,500,600,700
"

echo "$FONTS" | while IFS='|' read -r id stem variants; do
  [ -z "$id" ] && continue
  curl -fSL "https://gwfh.mranftl.com/api/fonts/${id}?download=zip&subsets=latin&variants=${variants}&formats=woff2" -o "${id}.zip"
  rm -rf "${id}-x" && mkdir "${id}-x"
  unzip -o "${id}.zip" -d "${id}-x" >/dev/null
  for v in ${variants//,/ }; do
    w="$v"; [ "$v" = "regular" ] && w=400
    src=$(ls "${id}-x"/*-latin-"${v}".woff2)
    cp "$src" "../assets/fonts/${stem}-${w}.woff2"
  done
done
cd ..
rm -rf tmp-fonts
ls assets/fonts/*.woff2 | wc -l   # expect 22
```

Expected final line: `22`.

- [ ] **Step 4: Download the OFL licenses**

```bash
set -euo pipefail
base=https://raw.githubusercontent.com/google/fonts/main/ofl
curl -fSL "$base/syne/OFL.txt"              -o assets/fonts/licenses/syne-OFL.txt
curl -fSL "$base/inter/OFL.txt"             -o assets/fonts/licenses/inter-OFL.txt
curl -fSL "$base/fraunces/OFL.txt"          -o assets/fonts/licenses/fraunces-OFL.txt
curl -fSL "$base/mulish/OFL.txt"            -o assets/fonts/licenses/mulish-OFL.txt
curl -fSL "$base/bricolagegrotesque/OFL.txt" -o assets/fonts/licenses/bricolage-grotesque-OFL.txt
curl -fSL "$base/hankengrotesk/OFL.txt"     -o assets/fonts/licenses/hanken-grotesk-OFL.txt
ls assets/fonts/licenses/*.txt | wc -l   # expect 6
```

Expected final line: `6`.

- [ ] **Step 5: Run the guard**

Run: `composer test -- --filter test_every_declared_weight_has_a_bundled_woff2`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add assets/fonts tests/php/FontAssetsTest.php
git commit -m "feat: bundle self-hosted woff2 fonts (latin, exact weights) + OFL licenses"
```

---

### Task 5: Playwright proof, version bump, and changelog

Prove at the browser level that no third-party font request is made and a bundled woff2 loads, then bump the version and record the change.

**Files:**
- Create: `tests/fonts.spec.js`
- Modify: `blueworx-labs-clubhouse.php:6` (`Version:` header)
- Modify: `blueworx-labs-clubhouse.php:24` (`BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `CHANGELOG.md:8` (new entry above the top one)

**Interfaces:**
- Consumes: the running preview (booted by `playwright.config.js` `webServer`), the bundled fonts (Task 4), and the CDN-free `document()` (Task 2).

- [ ] **Step 1: Write the browser test**

Create `tests/fonts.spec.js`:

```js
const { test, expect } = require('@playwright/test');

// Proves Phase 5: the rendered page makes zero third-party font requests and
// loads its fonts from the plugin's own /assets/fonts/ directory.
test('fonts are self-hosted, no Google CDN requests', async ({ page }) => {
  const thirdParty = [];
  const fontHits = [];
  page.on('request', (req) => {
    const url = req.url();
    if (url.includes('fonts.googleapis.com') || url.includes('fonts.gstatic.com')) {
      thirdParty.push(url);
    }
  });
  page.on('response', (res) => {
    const url = res.url();
    if (url.includes('/assets/fonts/') && url.endsWith('.woff2')) {
      fontHits.push({ url, status: res.status() });
    }
  });

  await page.goto('?page=home');
  await expect(page.locator('#ch-main')).toBeVisible();
  // Let font requests settle.
  await page.waitForLoadState('networkidle');

  expect(thirdParty, `unexpected third-party font requests: ${thirdParty.join(', ')}`).toHaveLength(0);
  expect(fontHits.length, 'expected at least one self-hosted woff2 request').toBeGreaterThan(0);
  for (const hit of fontHits) {
    expect(hit.status, `status for ${hit.url}`).toBe(200);
  }
});
```

- [ ] **Step 2: Run the browser test**

Run: `npx playwright test tests/fonts.spec.js`
Expected: PASS (1 passed). If Playwright browsers are not installed, run `npx playwright install chromium` first.

- [ ] **Step 3: Bump the version**

In `blueworx-labs-clubhouse.php`, line 6:

```php
 * Version:           0.21.0
```

Line 24:

```php
define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.21.0' );
```

- [ ] **Step 4: Add the changelog entry**

In `CHANGELOG.md`, insert above the current top entry (line 8):

```markdown
## [0.21.0] - 2026-07-13

### Phase 5 — Font self-hosting

#### Changed

- **Fonts are now self-hosted.** Every Base Look's typefaces (Syne, Inter, Fraunces, Mulish, Bricolage Grotesque, Hanken Grotesk) are served from woff2 files bundled in the plugin instead of the Google Fonts CDN. No visible change — same families, weights, and `font-display: swap` — but the front end now makes **zero third-party font requests** (no `fonts.googleapis.com`/`fonts.gstatic.com`), which is faster and more private. Each family's SIL OFL 1.1 licence is bundled under `assets/fonts/licenses/`.

```

- [ ] **Step 5: Final CDN-free verification**

Run: `grep -rEn "googleapis|gstatic" includes/`
Expected: no output (exit code 1). If anything prints, remove it before committing.

Run the full suites once more:
Run: `composer test && npx playwright test`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add blueworx-labs-clubhouse.php CHANGELOG.md tests/fonts.spec.js
git commit -m "test: Playwright proof of self-hosted fonts; bump to 0.21.0"
```

---

## Self-Review

**Spec coverage:**
- Extend `fonts()` with `stem` → Task 1. ✅
- Replace `google_fonts_url()` with `font_face_css()` generator → Task 2. ✅
- `document()` drops preconnect + Google `<link>`, injects `@font-face` → Task 2. ✅
- `Frontend` drops `clubhouse-fonts` enqueue + preconnect hints, inlines `@font-face` → Task 3. ✅
- Preview parity → inherited via `document(... , '/')`, noted in Task 2; exercised by Task 5's Playwright run (which targets the preview). ✅
- 22 woff2 files (latin, exact weights) + per-family OFL under `assets/fonts/licenses/` → Task 4. ✅
- Unit tests for the generator; updated tests that asserted `google_fonts_url` → Tasks 2, 3. ✅
- Playwright test: no `googleapis`/`gstatic`, woff2 200 → Task 5. ✅
- Minor bump 0.20.0 → 0.21.0 + changelog → Task 5. ✅
- "no `googleapis`/`gstatic` in `includes/`" invariant → verified in Task 5 Step 5. ✅

**Placeholder scan:** No TBD/TODO; every code and command step is concrete. ✅

**Type consistency:** `font_face_css( Base_Look $look, string $base_url ): string` is defined in Task 2 and consumed with that exact signature in Task 3. The `fonts()` entry shape `{family, stem, weights, display}` is introduced in Task 1 and read identically in Task 2's generator and Task 4's guard. `enqueue_specs` key renamed `fonts_url` → `font_face_css` consistently across Task 3's implementation and both its tests. ✅

**Non-goals honoured:** no preload links, no woff/ttf, no variable fonts, no demo-mode font broadening. ✅
