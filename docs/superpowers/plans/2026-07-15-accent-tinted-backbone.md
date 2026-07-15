# Accent-tinted backbone blocks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fill the banner, Home hero and ticker on light Base Looks with the look's own ink pulled 30% toward the club's accent, so each club's site is tinted with its own brand instead of sharing one near-black backbone.

**Architecture:** One new token, `--color-accent-block`, derived in `Color_Engine::derive()` from the look's **own** `$shell_ink` — never a hardcoded colour. That single fact makes it a system-wide rule rather than per-look configuration: every look inherits it, and Floodlight opts out by itself because it fills those blocks with `--color-paper`, not ink. `Theme_Css::compose()` already `array_merge`s `derive()`'s output, so the token reaches `:root` with no registration step.

**Tech Stack:** PHP 8.3 (no framework, WP-free unit tests), PHPUnit 10 with `#[DataProvider]` attributes, PHPCS (WordPress standard), Playwright against the DB-free `preview/index.php`.

**Spec:** `docs/superpowers/specs/2026-07-15-accent-tinted-backbone-design.md`

## Global Constraints

- **Branch:** `accent-tinted-backbone` (already checked out, based on `main` @ `191363c`). Never commit to `main`.
- **Blend constant:** 30%, expressed as `$i / 20` with `$i` starting at `6`. Stepping in twentieths matches `accent-deep`'s existing grid.
- **`mix( $a, $b, $weight_a )` weights toward its FIRST argument.** `mix( $accent, $shell_ink, 0.30 )` is 30% accent / 70% ink. Swapping them makes the tint silently vanish (falls through to the ink floor) — the `#4f5c28` anchor in Task 1 is the only thing that catches it.
- **`normalize_hex()` is `protected`** — callable inside the engine, not from tests.
- **AA threshold:** `4.5`, matching the rest of the engine.
- **Out of scope, do not touch:** Floodlight's stylesheet; `.ch-home-hero__scrim` in any look; the other 12 ink-filled surfaces on Court Side (CTA pill, hovers, fixtures/results lists, contact info panel, eyebrow band, active tab, skip link, hamburger bars); `--color-accent-deep`.
- **Version:** patch bump to `0.24.2` in **both** `blueworx-labs-clubhouse.php` (the `Version:` header **and** the `BLUEWORX_LABS_CLUBHOUSE_VERSION` constant) and `package.json`, with a matching `CHANGELOG.md` entry. Required by CI.
- **Rebase note:** PR #21 (`home-visual-fixes`, v0.24.1) also edits `court-side.css` and `members-house.css`. If it merges first, rebase and re-run everything. Its `.ch-ticker{margin-top:0}` rule is a different declaration from the `background` this plan changes, so a textual conflict is unlikely but the version number will need re-basing to `0.24.2`.
- **Lint policy:** run PHPCS once at the end. Do not lint-fix in a loop. Report findings; do not action them without approval.

---

### Task 1: Derive the `--color-accent-block` token

**Files:**
- Modify: `includes/theme/class-color-engine.php` (the `derive()` method, ~lines 73–116)
- Test: `tests/php/ColorEngineDeriveTest.php`

**Interfaces:**
- Consumes: existing `Color_Engine::mix()`, `::contrast_ratio()`, `::normalize_hex()` (protected).
- Produces: `Color_Engine::derive( string $accent, string $shell_bg, string $shell_ink ): array` gains a fifth key, `'--color-accent-block'`, appended **after** `'--color-accent-wash'`. Task 2 consumes it as the CSS custom property `var(--color-accent-block)`; Task 3 reads it from `derive()`'s return array.

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/ColorEngineDeriveTest.php`. Note the class already defines `LIGHT_BG`, `LIGHT_INK`, `DARK_BG`, `DARK_INK`, `MID_BG`, `MID_INK`, the `derive()` helper and the `hues()` provider — reuse them; do not redefine.

```php
	/**
	 * The block fill is the look's OWN ink pulled 30% toward the accent. This exact
	 * value is the regression anchor: mix() weights toward its FIRST argument, and
	 * swapping the arguments yields #93b23e (2.28 contrast), which fails the guard
	 * at every step and silently falls through to plain ink — i.e. the tint just
	 * disappears rather than erroring. Only this assertion catches that.
	 */
	public function test_accent_block_is_the_ink_tinted_toward_the_accent(): void {
		$this->assertSame( '#4f5c28', $this->derive( '#c6f24e' )['--color-accent-block'] );
	}

	/**
	 * The block is painted `background:var(--color-accent-block); color:var(--color-bg)`,
	 * so this one ratio guarantees BOTH that the block reads against the page and
	 * that the bg-coloured text on it is legible.
	 */
	#[DataProvider('hues')]
	public function test_accent_block_clears_AA_on_light_shell( string $accent ): void {
		$t = $this->derive( $accent );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-block'], self::LIGHT_BG ),
			"accent-block for {$accent} fails AA on the light shell"
		);
	}

	/** Same guarantee on a dark shell — the token must follow the look's polarity. */
	#[DataProvider('hues')]
	public function test_accent_block_clears_AA_on_dark_shell( string $accent ): void {
		$t = $this->derive( $accent, true );
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $t['--color-accent-block'], self::DARK_BG ),
			"accent-block for {$accent} fails AA on the dark shell"
		);
	}

	/**
	 * The guard steps back when the full 30% tint would fail, and still lands on a
	 * TINTED value rather than collapsing to ink. Needs a synthetic shell: the guard
	 * never fires on the shipped light shells at 30% (verified across every hue in
	 * hues() plus #ffffff and #ffff00). Here ink #444444 clears AA at 9.18, the 30%
	 * lime tint measures 4.49 and fails, so the guard steps back one notch to
	 * #657047 at 4.99.
	 */
	public function test_guard_steps_back_but_keeps_a_tint(): void {
		$block = Blueworx_Clubhouse_Color_Engine::derive( '#c6f24e', self::LIGHT_BG, '#444444' )['--color-accent-block'];
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $block, self::LIGHT_BG ),
			'guard must land on a value that clears AA'
		);
		$this->assertNotSame( '#444444', $block, 'guard must keep a tint, not collapse to ink' );
	}

	/**
	 * The floor is the look's ink. On a shell whose ink cannot itself clear AA
	 * (#ffffff on #808080 measures 3.95), no tint can either, so the token degrades
	 * to exactly the ink — never worse than today's background:var(--color-ink).
	 * AA is unreachable on such a shell and is not claimed.
	 */
	public function test_accent_block_falls_back_to_ink_when_no_tint_clears(): void {
		$t = Blueworx_Clubhouse_Color_Engine::derive( '#c6f24e', self::MID_BG, self::MID_INK );
		$this->assertSame( self::MID_INK, $t['--color-accent-block'] );
	}
```

And update the existing key-order test in the same file — it uses `assertSame` on `array_keys`, so a new key breaks it:

```php
	public function test_returns_expected_keys(): void {
		$t = $this->derive( '#c6f24e' );
		$this->assertSame(
			array( '--color-accent', '--color-accent-ink', '--color-accent-deep', '--color-accent-wash', '--color-accent-block' ),
			array_keys( $t )
		);
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter ColorEngineDeriveTest`
Expected: FAIL. `test_returns_expected_keys` fails on the missing 5th key; the new tests fail with `Undefined array key "--color-accent-block"`.

- [ ] **Step 3: Implement the token**

In `includes/theme/class-color-engine.php`, insert this **immediately before** the `return array(` at the end of `derive()` (i.e. after the `$deep` for-loop):

```php
		// The fill for large inverted blocks (banner, Home hero, ticker): the look's
		// OWN ink pulled up to 30% toward the accent, so each club's site is tinted
		// with its brand while keeping the look's weight and polarity. Deriving from
		// $shell_ink rather than a fixed colour is what makes this a system-wide rule
		// instead of per-look config — any look inherits it, and a look that fills
		// those blocks with --color-paper (Floodlight) simply never references it.
		//
		// One constraint does double duty: these blocks are painted
		// `background:var(--color-accent-block); color:var(--color-bg)`, so
		// contrast(block, shell_bg) >= 4.5 guarantees BOTH that the block reads
		// against the page AND that the bg-coloured text on it is legible.
		//
		// Floor is plain ink, so the token is never worse than the untinted block it
		// replaces; on a shell whose ink cannot itself clear AA, nothing ink-derived
		// can, and we degrade to exactly ink rather than returning a worse value.
		$block = self::normalize_hex( $shell_ink );
		for ( $i = 6; $i >= 0; $i-- ) { // 6/20 = the 0.30 ceiling; twentieths match accent-deep's grid.
			$candidate = self::mix( $accent, self::normalize_hex( $shell_ink ), $i / 20 );
			if ( self::contrast_ratio( $candidate, $shell_bg ) >= 4.5 ) {
				$block = $candidate;
				break;
			}
		}
```

Then add the key to the returned array, **after** `'--color-accent-wash'`:

```php
		return array(
			'--color-accent'       => $accent,
			'--color-accent-ink'   => $ink,
			'--color-accent-deep'  => $deep,
			'--color-accent-wash'  => self::mix( $accent, self::normalize_hex( $shell_bg ), 0.12 ),
			'--color-accent-block' => $block,
		);
```

And update the method's `@return` docblock shape (currently ~line 76):

```php
	 * @return array{'--color-accent':string,'--color-accent-ink':string,'--color-accent-deep':string,'--color-accent-wash':string,'--color-accent-block':string}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter ColorEngineDeriveTest`
Expected: PASS (all tests green, including the 7-hue providers).

- [ ] **Step 5: Run the full PHP suite for regressions**

Run: `./vendor/bin/phpunit`
Expected: PASS. `ThemeCssTest` uses `assertArrayHasKey`, so the extra key does not break it. If any test asserts an exact `:root` string, update it — the new token is now emitted.

- [ ] **Step 6: Commit**

```bash
git add includes/theme/class-color-engine.php tests/php/ColorEngineDeriveTest.php
git commit -m "feat: derive --color-accent-block, the look's ink tinted 30% toward the accent"
```

---

### Task 2: Swap the backbone fills on the two light looks

**Files:**
- Modify: `assets/looks/court-side.css` (4 declarations)
- Modify: `assets/looks/members-house.css` (4 declarations)
- Test: `tests/php/CourtSideStylesheetTest.php`, `tests/php/MembersHouseStylesheetTest.php`, `tests/php/FloodlightStylesheetTest.php`

**Interfaces:**
- Consumes: `var(--color-accent-block)` from Task 1.
- Produces: no PHP interface. Task 3 asserts the rendered result in a browser.

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/CourtSideStylesheetTest.php` (the class already has a private `css()` helper — reuse it):

```php
	/**
	 * The backbone blocks carry the club's tint, not flat near-black ink. The scrim
	 * is deliberately excluded: it darkens a club's hero PHOTOGRAPH, and tinting it
	 * would put a duotone wash over club photography.
	 */
	public function test_backbone_blocks_use_the_tinted_block_token(): void {
		$css = $this->css();
		$this->assertStringContainsString( '.ch-banner{background:var(--color-accent-block)', $css );
		$this->assertStringContainsString( '.ch-ticker{display:flex;align-items:center;gap:0;background:var(--color-accent-block)', $css );
		$this->assertStringContainsString( '.ch-home-hero__bg{position:absolute;inset:0;z-index:-2;background:var(--color-accent-block)}', $css );
		$this->assertStringContainsString( 'transparent 55%),var(--color-accent-block)}', $css ); // __bg--empty
	}

	public function test_hero_scrim_stays_neutral_ink(): void {
		$this->assertStringContainsString(
			'.ch-home-hero__scrim{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,color-mix(in oklab,var(--color-ink) 26%,transparent)',
			$this->css()
		);
	}
```

Add to `tests/php/MembersHouseStylesheetTest.php` (confirm the helper's name in that file and match it):

```php
	public function test_backbone_blocks_use_the_tinted_block_token(): void {
		$css = $this->css();
		$this->assertStringContainsString( '.ch-banner{background:var(--color-accent-block)', $css );
		$this->assertStringContainsString( '.ch-ticker{display:flex;align-items:center;gap:0;background:var(--color-accent-block)', $css );
		$this->assertStringContainsString( '.ch-home-hero__bg{position:absolute;inset:0;z-index:-2;background:var(--color-accent-block)}', $css );
		$this->assertStringContainsString( 'transparent 60%),var(--color-accent-block)}', $css ); // __bg--empty
	}

	public function test_hero_scrim_stays_neutral_ink(): void {
		$this->assertStringContainsString(
			'.ch-home-hero__scrim{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,color-mix(in oklab,var(--color-ink) 22%,transparent)',
			$this->css()
		);
	}
```

Add to `tests/php/FloodlightStylesheetTest.php` — this is the test that pins the "no per-look exception" property:

```php
	/**
	 * Floodlight fills its banner/hero/ticker with --color-paper and uses ink as
	 * their TEXT colour, so the ink-as-fill rule never reaches it. This is an
	 * emergent property of the rule, not an exception carved out for this look:
	 * if this assertion ever fails, the rule has been applied by hand somewhere.
	 */
	public function test_does_not_use_the_tinted_block_token(): void {
		$this->assertStringNotContainsString( '--color-accent-block', $this->css() );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter "StylesheetTest"`
Expected: the two `test_backbone_blocks_use_the_tinted_block_token` tests FAIL (CSS still says `var(--color-ink)`). The Floodlight and scrim tests should already PASS — they assert the status quo and are regression guards.

- [ ] **Step 3: Make the swaps**

In `assets/looks/court-side.css`, change exactly these four declarations (`--color-ink` → `--color-accent-block`). Match on the strings, not line numbers, as PR #21 may shift them:

```css
.ch-banner{background:var(--color-accent-block);color:var(--color-bg)}
.ch-home-hero__bg{position:absolute;inset:0;z-index:-2;background:var(--color-accent-block)}
.ch-home-hero__bg--empty{background:radial-gradient(96% 82% at 82% -12%,var(--color-accent-wash),transparent 55%),var(--color-accent-block)}
.ch-ticker{display:flex;align-items:center;gap:0;background:var(--color-accent-block);color:var(--color-bg);overflow:hidden}
```

In `assets/looks/members-house.css`, the same four (note the different gradient stops and the ticker's trailing `border-radius`):

```css
.ch-banner{background:var(--color-accent-block);color:var(--color-bg)}
.ch-home-hero__bg{position:absolute;inset:0;z-index:-2;background:var(--color-accent-block)}
.ch-home-hero__bg--empty{background:radial-gradient(88% 72% at 86% -10%,var(--color-accent-wash),transparent 60%),var(--color-accent-block)}
.ch-ticker{display:flex;align-items:center;gap:0;background:var(--color-accent-block);color:var(--color-bg);overflow:hidden;border-radius:var(--radius-md)}
```

**Do not** change `.ch-home-hero__scrim` in either file, and do not touch `assets/looks/floodlight.css` at all.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter "StylesheetTest"`
Expected: PASS.

Then confirm only the intended declarations moved. Each file must reference the new
token exactly 4 times (banner, `__bg`, `__bg--empty`, ticker):

```bash
grep -c -- "--color-accent-block" assets/looks/court-side.css    # expect 4
grep -c -- "--color-accent-block" assets/looks/members-house.css # expect 4
```

Only **3** of the 4 swapped declarations match the literal `background:var(--color-ink)`
— `__bg--empty` begins `background:radial-gradient(...)` and carries the ink as its
final layer — so the remaining ink-fill counts drop by 3, not 4:

```bash
grep -c "background:var(--color-ink)" assets/looks/court-side.css    # expect 12 (was 15)
grep -c "background:var(--color-ink)" assets/looks/members-house.css # expect 15 (was 18)
```

Those remaining fills are the deliberately out-of-scope surfaces (CTA pill, hovers,
lists, contact panel, eyebrow band, active tab, skip link, hamburger). Finally,
Floodlight must be untouched and the scrim must still hold ink:

```bash
git diff --numstat assets/looks/floodlight.css   # expect NO output
grep -c -- "--color-ink" assets/looks/court-side.css | grep -q 0 && echo "ERROR: ink gone entirely"
git diff --stat
```

- [ ] **Step 5: Commit**

```bash
git add assets/looks/court-side.css assets/looks/members-house.css tests/php/CourtSideStylesheetTest.php tests/php/MembersHouseStylesheetTest.php tests/php/FloodlightStylesheetTest.php
git commit -m "feat: tint the banner, home hero and ticker with the club's accent on light looks"
```

---

### Task 3: Carry the token through the preview switcher and verify in a browser

**Files:**
- Modify: `preview/index.php` (`blueworx_clubhouse_preview_palettes()` ~lines 47–56; the switcher `<script>` ~lines 84–95)
- Test: `tests/accent-block.spec.js` (create)

**Interfaces:**
- Consumes: `--color-accent-block` from Task 1; the tinted CSS from Task 2.
- Produces: each palette entry in `data-ch-palettes` gains a `block` key, and the swatch click handler sets `--color-accent-block`.

- [ ] **Step 1: Write the failing test**

Create `tests/accent-block.spec.js`:

```js
const { test, expect } = require('@playwright/test');

// The backbone blocks carry the club's tint, derived by the real colour engine.
// Court Side is the preview default; its Volt Lime accent resolves to #4f5c28
// (rgb(79, 92, 40)) — the same anchor the PHP engine test pins.
const TINT = 'rgb(79, 92, 40)';
const INK = 'rgb(28, 27, 24)';

test('the banner, home hero and ticker carry the club tint, not flat ink', async ({ page }) => {
  await page.goto('?page=home');
  for (const sel of ['.ch-banner', '.ch-home-hero__bg', '.ch-ticker']) {
    const bg = await page.locator(sel).evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(bg, `${sel} background`).toBe(TINT);
  }
});

// The scrim is built with color-mix(in oklab, ...), which Chrome serialises as
// `linear-gradient(oklab(0.222095 ...  / 0.26), ...)` — NOT rgb — so do not assert
// on a hex or rgb triple here. Asserting that the accent cannot move it expresses
// the actual intent (photos must not be colour-cast) and survives any serialisation.
test('the hero scrim stays neutral so club photos are not colour-cast', async ({ page }) => {
  await page.goto('?page=home');
  const scrim = page.locator('.ch-home-hero__scrim');
  const before = await scrim.evaluate((el) => getComputedStyle(el).backgroundImage);
  await page.locator('.ch-switcher button').nth(1).click();
  const after = await scrim.evaluate((el) => getComputedStyle(el).backgroundImage);
  expect(after, 'the scrim must not follow the club accent').toBe(before);
});

test('switching the accent re-themes the backbone through the real engine', async ({ page }) => {
  await page.goto('?page=home');
  const before = await page.locator('.ch-ticker').evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(before).toBe(TINT);
  // Signal Orange is the 2nd swatch; Court Side + #ff5b23 derives to #602e1b.
  await page.locator('.ch-switcher button').nth(1).click();
  const after = await page.locator('.ch-ticker').evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(after).toBe('rgb(96, 46, 27)');
  expect(after).not.toBe(INK);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx playwright test tests/accent-block.spec.js`
Expected: the first two tests PASS already (Task 2 landed the CSS and the token is emitted by `Theme_Css`). The **third** FAILS — the switcher does not set `--color-accent-block`, so the ticker keeps the previous tint after clicking.

- [ ] **Step 3: Wire the switcher**

In `preview/index.php`, add `block` to the palette array built in `blueworx_clubhouse_preview_palettes()`:

```php
		$out[] = array(
			'name'  => $name,
			'c'     => $d['--color-accent'],
			'ink'   => $d['--color-accent-ink'],
			'deep'  => $d['--color-accent-deep'],
			'wash'  => $d['--color-accent-wash'],
			'block' => $d['--color-accent-block'],
		);
```

Then extend the click handler in the switcher `<script>` so the swatches re-theme the backbone too:

```php
		. 's.onclick=function(){var r=document.documentElement.style;'
		. 'r.setProperty("--color-accent",p.c);r.setProperty("--color-accent-ink",p.ink);'
		. 'r.setProperty("--color-accent-deep",p.deep);r.setProperty("--color-accent-wash",p.wash);'
		. 'r.setProperty("--color-accent-block",p.block);};'
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx playwright test tests/accent-block.spec.js`
Expected: PASS (3 tests).

- [ ] **Step 5: Look at it**

Run: `php -S 127.0.0.1:8124` (docroot = plugin root) and open `http://127.0.0.1:8124/preview/`.

Click through all five swatches on `?look=court-side` and `?look=members-house`, and confirm `?look=floodlight` is visually unchanged. This is the review gate for the 30% constant — the number is a one-line change in Task 1 if it reads wrong.

Specifically check the **`CLUB NEWS` ticker label**: it is full `--color-accent` on a now-tinted ticker, so it is a tonal pairing rather than a contrasting one. Legibility is unaffected (its text is `--color-accent-ink` on `--color-accent`, an untouched pair), but if it reads flat, note it for a follow-up — do **not** fix it in this plan.

- [ ] **Step 6: Commit**

```bash
git add preview/index.php tests/accent-block.spec.js
git commit -m "feat: carry --color-accent-block through the preview switcher"
```

---

### Task 4: Version, changelog, and full verification

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (`Version:` header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `package.json`
- Modify: `CHANGELOG.md`

**Interfaces:** none.

- [ ] **Step 1: Bump the version in all three places**

`blueworx-labs-clubhouse.php`:

```php
 * Version:           0.24.2
```

```php
define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.24.2' );
```

`package.json`:

```json
  "version": "0.24.2",
```

- [ ] **Step 2: Add the changelog entry**

Insert directly above the most recent heading in `CHANGELOG.md`:

```markdown
## 0.24.2

- **Change:** the top banner, home hero and news ticker now carry your club's colour instead of near-black. Each is filled with the Base Look's own ink pulled 30% toward your accent, so the site reads as tinted with your brand while keeping the same weight and readability. Every club colour is handled automatically — no setting to configure, and contrast is guaranteed by the colour engine. Applies to Court Side and Members' House; Floodlight is unchanged, as its blocks were never near-black. Hero photographs are unaffected: the scrim over them stays neutral so your images keep their true colours.
```

- [ ] **Step 3: Run the full verification sweep**

```bash
./vendor/bin/phpunit          # expect: OK (429+ tests)
npx playwright test           # expect: 15 passed (12 existing + 3 new)
composer lint                 # expect: clean
```

Expected: all three green. Per the lint policy, report any PHPCS findings rather than fixing them in a loop.

- [ ] **Step 4: Commit and raise the PR**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.24.2 for accent-tinted backbone blocks"
git push -u origin accent-tinted-backbone
```

Then open a PR against `main` describing: the new token and why it is derived from each look's own ink (system-wide rule, not per-look config); that Floodlight is untouched by emergence rather than exception; that the scrim stays neutral; and the `CLUB NEWS` label observation from Task 3 Step 5.
