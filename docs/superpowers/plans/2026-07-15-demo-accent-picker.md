# Demo mode accent picker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add five live accent swatches to the site-wide Demo mode bar, so a prospective club can tour the real site in their own brand colour.

**Architecture:** The client applies the accent; the server never renders a demo one. An inline `wp_head` script carries the server-derived palettes and re-applies the cookie'd choice before first paint; `demo.js` handles clicks. `Theme_Cache` is a single shared site-wide slot, so a per-viewer accent rendered through it would leak one visitor's colour to real public traffic — keeping it client-side makes that structurally impossible.

**Tech Stack:** PHP 8.3 (WP-free pure units + a thin WP glue class), PHPUnit 10, vanilla JS (no dependencies), Playwright against the DB-free `preview/index.php`.

**Spec:** `docs/superpowers/specs/2026-07-15-demo-accent-picker-design.md`

## Global Constraints

- **Branch:** `demo-accent-picker` (already checked out, based on `main`). Never commit to `main`.
- **The five swatches, exact:** Volt Lime `#c6f24e`, Signal Orange `#ff5b23`, Court Teal `#12c3b0`, Cobalt `#3b5bdb`, Berry `#c2337a`. Slugs: `volt-lime`, `signal-orange`, `court-teal`, `cobalt`, `berry`.
- **Cookie:** `clubhouse_demo_accent`, storing the swatch **slug** (never a hex), with `path=/; SameSite=Lax` — matching the existing `clubhouse_demo_look` cookie exactly.
- **`switcher_html()` must stay free of colour literals.** `DemoModeSwitcherTest::test_skin_agnostic_no_colour_literals` asserts no `#rrggbb` and no `var(--color-accent` appears in its output, and that test must keep passing **unchanged**. Swatch buttons therefore carry only `data-clubhouse-accent="<slug>"`; `demo.js` paints their colour from the palettes global. **This is why the spec's "emit palettes in a `data-clubhouse-palettes` attribute" is NOT what we build** — it would put five hexes straight into that output.
- **Never write the club's saved settings.** Demo is per-viewer and read-only — the same contract `Demo_Controller` already documents ("Never writes the club's saved look").
- **`Demo_Mode` is WP-free.** No WordPress calls in it; escape with `htmlspecialchars`. All WP coupling lives in `Demo_Controller`.
- **Version:** patch bump to `0.24.3` in **all three** places — `blueworx-labs-clubhouse.php` (the `Version:` header **and** the `BLUEWORX_LABS_CLUBHOUSE_VERSION` constant) and `package.json` — plus a `CHANGELOG.md` entry. Required by CI.
- **Lint policy:** run `composer lint` once at the end. Never lint → fix → re-lint in a loop. Report findings; do not action them without approval.
- **Do not touch:** `Theme_Cache`, `Theme_Css`, `Color_Engine`, `Branding`, the admin Setup screen, or the club's saved accent.

---

### Task 1: Derive the palettes (`Demo_Mode::palettes()`)

**Files:**
- Modify: `includes/frontend/class-demo-mode.php`
- Test: `tests/php/DemoModeTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Color_Engine::derive( string $accent, string $shell_bg, string $shell_ink ): array` and `Blueworx_Clubhouse_Base_Look::tokens(): array`.
- Produces:
  - `Blueworx_Clubhouse_Demo_Mode::COOKIE_ACCENT` = `'clubhouse_demo_accent'`
  - `Blueworx_Clubhouse_Demo_Mode::SWATCHES` — `array<string,array{name:string,hex:string}>` keyed by slug, in display order.
  - `Blueworx_Clubhouse_Demo_Mode::palettes( Blueworx_Clubhouse_Base_Look $look ): array<string,array{name:string,hex:string,tokens:array<string,string>}>` keyed by slug. Tasks 2-4 consume this.

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/DemoModeTest.php` (the class exists; add these methods):

```php
	public function test_accent_cookie_constant(): void {
		$this->assertSame( 'clubhouse_demo_accent', Blueworx_Clubhouse_Demo_Mode::COOKIE_ACCENT );
	}

	public function test_palettes_cover_the_five_swatches_in_order(): void {
		$p = Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Court_Side() );
		$this->assertSame(
			array( 'volt-lime', 'signal-orange', 'court-teal', 'cobalt', 'berry' ),
			array_keys( $p )
		);
		$this->assertSame( 'Volt Lime', $p['volt-lime']['name'] );
		$this->assertSame( '#c6f24e', $p['volt-lime']['hex'] );
	}

	/** Every palette carries the full derived token set the client will apply. */
	public function test_each_palette_carries_all_engine_tokens(): void {
		$p = Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Court_Side() );
		foreach ( $p as $slug => $entry ) {
			$this->assertSame(
				array( '--color-accent', '--color-accent-ink', '--color-accent-deep', '--color-accent-wash', '--color-accent-block' ),
				array_keys( $entry['tokens'] ),
				"palette {$slug} is missing engine tokens"
			);
		}
	}

	/** Tokens come from the real engine, for THIS look's shell. */
	public function test_palettes_match_the_engine_for_the_given_look(): void {
		$look = new Blueworx_Clubhouse_Court_Side();
		$t    = $look->tokens();
		$this->assertSame(
			Blueworx_Clubhouse_Color_Engine::derive( '#c6f24e', $t['--color-bg'], $t['--color-ink'] ),
			Blueworx_Clubhouse_Demo_Mode::palettes( $look )['volt-lime']['tokens']
		);
	}

	/**
	 * The contract that makes storing a SLUG (not a hex) the right call: the same
	 * swatch derives different tokens per look, because each look has its own shell.
	 * Court Side is light, Floodlight dark — so lime resolves to a dark olive block
	 * on one and a pale cream block on the other.
	 */
	public function test_the_same_swatch_derives_differently_per_look(): void {
		$light = Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Court_Side() )['volt-lime']['tokens'];
		$dark  = Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Floodlight() )['volt-lime']['tokens'];
		$this->assertSame( '#c6f24e', $light['--color-accent'], 'the raw accent is the same' );
		$this->assertSame( '#c6f24e', $dark['--color-accent'] );
		$this->assertNotSame(
			$light['--color-accent-block'],
			$dark['--color-accent-block'],
			'derived tokens MUST differ per shell — this is why the cookie stores a slug'
		);
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter DemoModeTest`
Expected: FAIL — `Undefined constant ... COOKIE_ACCENT` / `Call to undefined method ... palettes()`.

- [ ] **Step 3: Implement**

In `includes/frontend/class-demo-mode.php`, add below the existing `COOKIE_LOOK` constant:

```php
	public const COOKIE_ACCENT = 'clubhouse_demo_accent';

	/**
	 * The demo swatch set, in display order. Curated rather than a free colour
	 * input: an arbitrary accent can fail the engine's legibility gate (the admin
	 * Setup screen already rejects some), which would need a warning UI. These five
	 * are known-good on every shipped look.
	 *
	 * @var array<string,array{name:string,hex:string}>
	 */
	public const SWATCHES = array(
		'volt-lime'     => array( 'name' => 'Volt Lime', 'hex' => '#c6f24e' ),
		'signal-orange' => array( 'name' => 'Signal Orange', 'hex' => '#ff5b23' ),
		'court-teal'    => array( 'name' => 'Court Teal', 'hex' => '#12c3b0' ),
		'cobalt'        => array( 'name' => 'Cobalt', 'hex' => '#3b5bdb' ),
		'berry'         => array( 'name' => 'Berry', 'hex' => '#c2337a' ),
	);
```

And add this method to the class:

```php
	/**
	 * Derive each swatch's accent token set for a look, through the real engine.
	 * Keyed by slug so the client can look up a cookie'd choice directly.
	 *
	 * Derivation depends on the look's shell, so these must be recomputed per look —
	 * which is exactly why the cookie stores a slug and not a hex: on a look switch
	 * the server re-derives for the new shell instead of the client replaying a
	 * colour computed for the old one.
	 *
	 * @return array<string,array{name:string,hex:string,tokens:array<string,string>}>
	 */
	public static function palettes( Blueworx_Clubhouse_Base_Look $look ): array {
		$shell = $look->tokens();
		$out   = array();
		foreach ( self::SWATCHES as $slug => $swatch ) {
			$out[ $slug ] = array(
				'name'   => $swatch['name'],
				'hex'    => $swatch['hex'],
				'tokens' => Blueworx_Clubhouse_Color_Engine::derive(
					$swatch['hex'],
					$shell['--color-bg'],
					$shell['--color-ink']
				),
			);
		}
		return $out;
	}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter DemoModeTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/frontend/class-demo-mode.php tests/php/DemoModeTest.php
git commit -m "feat: derive the demo accent palettes per look"
```

---

### Task 2: Swatch buttons + the pre-paint head script

**Files:**
- Modify: `includes/frontend/class-demo-mode.php`
- Test: `tests/php/DemoModeSwitcherTest.php`

**Interfaces:**
- Consumes: `Demo_Mode::SWATCHES`, `Demo_Mode::COOKIE_ACCENT`, and `Demo_Mode::palettes()` output from Task 1.
- Produces:
  - `Demo_Mode::switcher_html()` — unchanged signature `( array $looks, ?string $current_slug, ?string $deactivate_url ): string`; now also renders a swatch group. Each swatch is `<button class="clubhouse-demo__swatch" data-clubhouse-accent="<slug>" title="<name>" aria-label="Accent: <name>">`, carrying **no colour**.
  - `Demo_Mode::head_script( array $palettes ): string` — pure; returns the inline JS (no `<script>` tags) that Task 3 echoes in `wp_head`. It defines `window.clubhouseDemoPalettes` and applies the cookie'd accent.

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/DemoModeSwitcherTest.php`:

```php
	public function test_renders_one_swatch_per_accent(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', null );
		$this->assertSame( 5, substr_count( $html, 'data-clubhouse-accent="' ) );
		$this->assertStringContainsString( 'data-clubhouse-accent="berry"', $html );
		$this->assertStringContainsString( 'aria-label="Accent: Volt Lime"', $html );
	}

	/**
	 * The swatch markup carries no colour: demo.css is neutral tooling chrome and
	 * test_skin_agnostic_no_colour_literals pins that. demo.js paints each swatch
	 * from window.clubhouseDemoPalettes instead.
	 */
	public function test_swatches_carry_no_colour(): void {
		$html = Blueworx_Clubhouse_Demo_Mode::switcher_html( $this->looks(), 'court-side', null );
		$this->assertDoesNotMatchRegularExpression( '/(?<!&)#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_head_script_defines_the_palettes_and_reads_the_cookie(): void {
		$js = Blueworx_Clubhouse_Demo_Mode::head_script(
			Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Court_Side() )
		);
		$this->assertStringContainsString( 'window.clubhouseDemoPalettes', $js );
		$this->assertStringContainsString( 'clubhouse_demo_accent', $js );
		$this->assertStringContainsString( '#c6f24e', $js, 'palettes must reach the client' );
		$this->assertStringNotContainsString( '</script>', $js, 'must not be able to break out of its script tag' );
	}

	/** The JSON must be embeddable in an inline script without breaking out of it. */
	public function test_head_script_json_is_tag_safe(): void {
		$js = Blueworx_Clubhouse_Demo_Mode::head_script(
			Blueworx_Clubhouse_Demo_Mode::palettes( new Blueworx_Clubhouse_Floodlight() )
		);
		$this->assertStringNotContainsString( '<\/script', $js );
		$this->assertStringNotContainsString( '<script', $js );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter DemoModeSwitcherTest`
Expected: FAIL — the swatch assertions find 0 swatches; `head_script()` is undefined.

Note `test_skin_agnostic_no_colour_literals` must still PASS at this step and after — if it fails, the swatches leaked a colour.

- [ ] **Step 3: Implement**

In `switcher_html()`, insert this immediately **after** the looks `</div>` and **before** the `$deactivate_url` block:

```php
		$out .= '<div class="clubhouse-demo__accents" role="group" aria-label="Try an accent colour">';
		foreach ( self::SWATCHES as $slug => $swatch ) {
			// No colour here by design: demo.js paints the swatch from the palettes
			// global, so this markup stays free of colour literals.
			$out .= '<button type="button" class="clubhouse-demo__swatch"'
				. ' data-clubhouse-accent="' . self::esc( $slug ) . '"'
				. ' title="' . self::esc( $swatch['name'] ) . '"'
				. ' aria-label="Accent: ' . self::esc( $swatch['name'] ) . '"></button>';
		}
		$out .= '</div>';
```

Add this method to the class:

```php
	/**
	 * Inline JS for wp_head: publishes the palettes and applies the viewer's cookie'd
	 * accent BEFORE first paint. This must run in the head — demo.js is a footer
	 * script, so applying there would flash the club's saved colour first.
	 *
	 * JSON_HEX_TAG makes the payload safe to inline inside a <script> element.
	 *
	 * @param array<string,array{name:string,hex:string,tokens:array<string,string>}> $palettes
	 */
	public static function head_script( array $palettes ): string {
		$json = json_encode( $palettes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		return '(function(){var P=' . ( false === $json ? '{}' : $json ) . ';'
			. 'window.clubhouseDemoPalettes=P;'
			. 'var m=document.cookie.match(/(?:^|;\s*)' . self::COOKIE_ACCENT . '=([^;]*)/);'
			. 'if(!m){return;}'
			. 'var p=P[decodeURIComponent(m[1])];'
			. 'if(!p){return;}'
			. 'var r=document.documentElement.style;'
			. 'for(var k in p.tokens){r.setProperty(k,p.tokens[k]);}'
			. '})();';
	}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter DemoModeSwitcherTest`
Expected: PASS — including the pre-existing `test_skin_agnostic_no_colour_literals` and `test_renders_one_control_per_look_for_everyone` (which counts `data-clubhouse-look="`, unaffected by the new `data-clubhouse-accent="` buttons).

- [ ] **Step 5: Commit**

```bash
git add includes/frontend/class-demo-mode.php tests/php/DemoModeSwitcherTest.php
git commit -m "feat: add demo accent swatches and the pre-paint head script"
```

---

### Task 3: Wire it up — controller, JS, CSS

**Files:**
- Modify: `includes/admin/class-demo-controller.php`
- Modify: `assets/js/demo.js`
- Modify: `assets/css/demo.css`

**Interfaces:**
- Consumes: `Demo_Mode::palettes()`, `Demo_Mode::head_script()`, `Demo_Mode::COOKIE_ACCENT`, and the `.clubhouse-demo__swatch` / `data-clubhouse-accent` markup from Tasks 1-2. Reuses the existing `Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() )` pattern already used by `render_switcher()`.
- Produces: no PHP interface. Task 4 drives the result in a browser.

- [ ] **Step 1: Add the head script hook to the controller**

In `includes/admin/class-demo-controller.php`, register the hook in `register()`, immediately after the `wp_enqueue_scripts` line:

```php
		add_action( 'wp_head', array( self::class, 'render_head_script' ), 1 );
```

And add this method to the class:

```php
	/**
	 * Publish the palettes and re-apply the viewer's accent before first paint.
	 * Priority 1 on wp_head, not the footer bundle: demo.js is a footer script, so
	 * applying there would flash the club's saved colour before the demo one.
	 */
	public static function render_head_script(): void {
		if ( ! self::is_on() ) {
			return;
		}
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		// Resolve the look the VIEWER is seeing, not the club's saved one. The demo
		// look never becomes the registry's active look — Frontend::context() keeps
		// them apart the same way, and render_switcher() below already resolves it
		// like this. Deriving from active() here would emit palettes for the wrong
		// shell whenever a viewer is demoing a look.
		$slug = self::look_slug( $registry );
		$look = null !== $slug ? $registry->get( $slug ) : $registry->active();
		if ( ! $look instanceof Blueworx_Clubhouse_Base_Look ) {
			return;
		}
		echo '<script id="clubhouse-demo-accent">'
			. Blueworx_Clubhouse_Demo_Mode::head_script( Blueworx_Clubhouse_Demo_Mode::palettes( $look ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- head_script JSON-encodes with JSON_HEX_TAG; asserted tag-safe by DemoModeSwitcherTest.
			. '</script>';
	}
```

**Do not use `$registry->active()` on its own here.** `Frontend::context()` resolves the demo look as
`$demo_slug !== null ? $registry->get( $demo_slug ) : $registry->active()` — it never sets the
demo look *as* active. So `active()` returns the club's **saved** look. Deriving palettes from it
would hand the client tokens computed for a different shell than the page is rendering.

- [ ] **Step 2: Add the click handler to demo.js**

Replace the whole of `assets/js/demo.js` with:

```js
/* Blueworx Clubhouse — Demo mode switcher. Site-wide demo state is toggled
 * by admins via a server link (admin-post); this file only handles the per-viewer
 * choices: the look (cookie + reload, so the server re-renders) and the accent
 * (applied live, no reload). No dependencies. */
( function () {
	'use strict';

	var LOOK = 'clubhouse_demo_look';
	var ACCENT = 'clubhouse_demo_accent';

	function setCookie( name, value ) {
		document.cookie = name + '=' + encodeURIComponent( value ) + '; path=/; SameSite=Lax';
	}

	function palettes() {
		return window.clubhouseDemoPalettes || {};
	}

	// The swatch's own colour comes from the palettes global, so the server markup
	// stays free of colour literals.
	function paintSwatches() {
		var all = palettes();
		var nodes = document.querySelectorAll( '[data-clubhouse-accent]' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			var p = all[ node.getAttribute( 'data-clubhouse-accent' ) ];
			if ( p ) {
				node.style.background = p.hex;
			}
		} );
	}

	function applyAccent( slug ) {
		var p = palettes()[ slug ];
		if ( ! p ) {
			return;
		}
		var root = document.documentElement.style;
		Object.keys( p.tokens ).forEach( function ( token ) {
			root.setProperty( token, p.tokens[ token ] );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var look = e.target.closest( '[data-clubhouse-look]' );
		if ( look ) {
			e.preventDefault();
			setCookie( LOOK, look.getAttribute( 'data-clubhouse-look' ) );
			window.location.reload();
			return;
		}
		var accent = e.target.closest( '[data-clubhouse-accent]' );
		if ( accent ) {
			e.preventDefault();
			var slug = accent.getAttribute( 'data-clubhouse-accent' );
			// Live: no reload. The cookie only makes it survive navigation — the head
			// script re-applies it on the next page, re-derived for that look.
			applyAccent( slug );
			setCookie( ACCENT, slug );
		}
	} );

	paintSwatches();
}() );
```

- [ ] **Step 3: Add the swatch styling to demo.css**

Append to `assets/css/demo.css`:

```css
.clubhouse-demo__accents {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}
.clubhouse-demo__swatch {
	appearance: none;
	cursor: pointer;
	width: 24px;
	height: 24px;
	min-height: 24px;
	padding: 0;
	border: 1px solid rgba( 255, 255, 255, 0.4 );
	border-radius: 50%;
	background: transparent;
}
.clubhouse-demo__swatch:focus-visible {
	outline: 2px solid #fff;
	outline-offset: 2px;
}
```

Also correct the file's opening comment — it currently says "Admin-only, never served to visitors", which has been false since demo mode became site-wide (the switcher is shown to every viewer while demo is on). Replace that sentence with: `Shown to every viewer while demo mode is on.`

- [ ] **Step 4: Verify nothing regressed**

Run: `./vendor/bin/phpunit`
Expected: PASS (no new PHP behaviour here, but the controller must still parse and the suite must stay green).

Run: `node --check assets/js/demo.js`
Expected: no output (valid syntax).

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-demo-controller.php assets/js/demo.js assets/css/demo.css
git commit -m "feat: wire the demo accent picker into the front end"
```

---

### Task 4: Mount it in the preview harness and drive it in a browser

**Files:**
- Modify: `preview/index.php`
- Test: `tests/demo-accent.spec.js` (create)

**Interfaces:**
- Consumes: `Demo_Mode::switcher_html()`, `Demo_Mode::head_script()`, `Demo_Mode::palettes()` from Tasks 1-2, and `demo.js` / `demo.css` from Task 3.
- Produces: `preview/index.php?demo=1` renders the real Demo mode bar.

**Why this task exists:** the spec calls for Playwright coverage, but Demo mode is normally rendered by `Demo_Controller`, which is WordPress-coupled and cannot run in the DB-free preview. `Demo_Mode` itself is pure and `demo.js` is plain JS, so mounting them in the harness exercises the real picker end-to-end — everything except the WP glue.

**Do NOT replace the preview's existing `.ch-switcher`.** It is pinned by `tests/php/PreviewRenderTest.php` (asserts `data-ch-palettes`) and driven by `tests/accent-block.spec.js` (`.ch-switcher button`). The demo bar is additive, behind `?demo=1`.

- [ ] **Step 1: Write the failing test**

Create `tests/demo-accent.spec.js`:

```js
const { test, expect } = require('@playwright/test');

// The real Demo mode bar, mounted in the DB-free preview via ?demo=1.
// Court Side is the preview default; its derived tokens are the engine's own:
// Berry -> accent #c2337a, block #4e2235. Signal Orange -> block #602e1b.
const BERRY_BLOCK = 'rgb(78, 34, 53)';

const rootToken = (page, name) =>
  page.evaluate((n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim(), name);

test('demo bar renders five swatches, painted from the palettes', async ({ page }) => {
  await page.goto('?demo=1');
  const swatches = page.locator('.clubhouse-demo__swatch');
  await expect(swatches).toHaveCount(5);
  // demo.js paints them; the server markup carries no colour.
  const bg = await swatches.first().evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(bg).toBe('rgb(198, 242, 78)'); // Volt Lime #c6f24e
});

test('clicking a swatch recolours the page live, with no reload', async ({ page }) => {
  await page.goto('?demo=1');
  // A reload wipes this flag — a more reliable "did not navigate" probe than
  // listening for framenavigated, which does not fire when nothing navigates.
  await page.evaluate(() => { window.__stillHere = true; });
  await page.locator('[data-clubhouse-accent="berry"]').click();
  expect(await page.evaluate(() => window.__stillHere), 'page must not reload').toBe(true);
  expect(await rootToken(page, '--color-accent')).toBe('#c2337a');
  const ticker = await page.locator('.ch-ticker').evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(ticker).toBe(BERRY_BLOCK);
});

test('the accent survives navigation via the cookie', async ({ page }) => {
  await page.goto('?demo=1');
  await page.locator('[data-clubhouse-accent="berry"]').click();
  await page.goto('?demo=1&page=about');
  // Re-applied by the head script, before paint.
  expect(await rootToken(page, '--color-accent')).toBe('#c2337a');
});

test('the same swatch re-derives for a different look', async ({ page }) => {
  await page.goto('?demo=1');
  await page.locator('[data-clubhouse-accent="berry"]').click();
  const light = await rootToken(page, '--color-accent-block');
  await page.goto('?demo=1&look=floodlight');
  const dark = await rootToken(page, '--color-accent-block');
  expect(await rootToken(page, '--color-accent')).toBe('#c2337a'); // same swatch
  expect(dark).not.toBe(light); // re-derived for the dark shell
});

test('no demo bar without ?demo=1', async ({ page }) => {
  await page.goto('?page=home');
  await expect(page.locator('.clubhouse-demo')).toHaveCount(0);
  await expect(page.locator('.clubhouse-demo__swatch')).toHaveCount(0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx playwright test tests/demo-accent.spec.js`
Expected: FAIL — `?demo=1` renders nothing, so `.clubhouse-demo__swatch` has count 0.

- [ ] **Step 3: Mount the demo bar in the preview**

In `preview/index.php`, inside `blueworx_clubhouse_preview_document()`, add this immediately **before** the `return Blueworx_Clubhouse_Page_Renderer::document(` line:

```php
	// Preview-only: mount the REAL Demo mode bar (Demo_Mode is WP-free, demo.js is
	// plain JS) so its picker can be driven in a browser. Demo_Controller itself is
	// WordPress-coupled and cannot run here. Additive and opt-in — the preview's own
	// .ch-switcher is unaffected.
	$demo = '';
	if ( isset( $_GET['demo'] ) && '1' === $_GET['demo'] ) {
		$demo_looks = array();
		foreach ( $registry->all() as $demo_look ) {
			$demo_looks[] = array( 'slug' => $demo_look->slug(), 'name' => $demo_look->name() );
		}
		$demo = '<link rel="stylesheet" href="/assets/css/demo.css">'
			. '<script>' . Blueworx_Clubhouse_Demo_Mode::head_script(
				Blueworx_Clubhouse_Demo_Mode::palettes( $registry->active() )
			) . '</script>'
			. Blueworx_Clubhouse_Demo_Mode::switcher_html( $demo_looks, (string) $look_slug, null )
			. '<script src="/assets/js/demo.js"></script>';
	}
```

Then add `$demo` to the body string passed to the renderer — change:

```php
		$body . $switcher . $look_toggle . $look_persist . $style,
```

to:

```php
		$body . $switcher . $look_toggle . $look_persist . $style . $demo,
```

Note the head script runs late here (it is in the body, not `wp_head`) — acceptable for the harness, whose job is to exercise the picker's behaviour. The real pre-paint placement is `Demo_Controller::render_head_script()` on `wp_head` priority 1, covered by Task 3.

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx playwright test tests/demo-accent.spec.js`
Expected: PASS (5 tests).

Then the whole suite: `npx playwright test`
Expected: PASS — 20 tests (15 existing + 5 new). `tests/accent-block.spec.js` must still pass, proving the preview's own `.ch-switcher` is untouched.

- [ ] **Step 5: Commit**

```bash
git add preview/index.php tests/demo-accent.spec.js
git commit -m "test: mount the demo bar in the preview and drive the accent picker"
```

---

### Task 5: Version, changelog, and full verification

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (`Version:` header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `package.json`
- Modify: `CHANGELOG.md`

**Interfaces:** none.

- [ ] **Step 1: Bump the version in all three places**

`blueworx-labs-clubhouse.php`:

```php
 * Version:           0.24.3
```

```php
define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.24.3' );
```

`package.json`:

```json
  "version": "0.24.3",
```

- [ ] **Step 2: Add the changelog entry**

Insert directly above the `## 0.24.2` heading in `CHANGELOG.md`:

```markdown
## 0.24.3

- **Demo mode now lets you try colours, not just looks.** While demo mode is on, the bar gains five brand colours — click one and the whole site recolours instantly, no page reload. Your choice follows you as you browse, and switching Base Look keeps your colour while re-fitting it to that look's style. It only ever changes what *you* see: the club's saved colour is untouched, and other visitors are unaffected.
```

- [ ] **Step 3: Run the full verification sweep**

```bash
./vendor/bin/phpunit          # expect: OK (466+ tests)
npx playwright test           # expect: 20 passed
composer lint                 # expect: clean
```

Expected: all three green. Per the lint policy, report any PHPCS findings rather than fixing them in a loop.

- [ ] **Step 4: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.24.3 for the demo accent picker"
```
