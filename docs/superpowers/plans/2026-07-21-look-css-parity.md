# Look CSS Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make all three looks render every page completely, from one shared set of building blocks, with a test that fails if a look ever drifts again.

**Architecture:** A new `assets/looks/base.css` holds structural rules written only against the shared design tokens, and loads before the active look's stylesheet so look rules keep winning. Six components currently styled only by Court Side are **moved** (not copied) into it. A PHPUnit test asserts the three looks have an identical set of emitted-but-unstyled classes; a Playwright spec asserts real computed styles under each look.

**Tech Stack:** PHP 8.1+, PHPUnit 9, plain CSS with custom properties, Playwright (CommonJS), WordPress plugin (no build step).

## Global Constraints

- **Specificity:** every selector in `base.css` stays at single-class specificity. No `!important`, no ID selectors. The one documented exception is `.ch-main:has(> .ch-social:last-child) + .ch-footer`, moved verbatim and safe because it wins on specificity (0,4,0) over `.ch-footer` (0,1,0) at `court-side.css:217` regardless of order.
- **No literals in `base.css`:** colours and font families come from tokens only — `var(--color-*)`, `var(--font-*)`. This mirrors the existing per-look stylesheet tests.
- **Court Side must not change visually.** It is the reviewed, shipping look.
- **Version:** bump the patch version in `blueworx-labs-clubhouse.php` (header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json`, and add a `CHANGELOG.md` entry. CI fails the PR otherwise.
- **Linting:** run `composer lint` once at the end. Do not loop lint → fix → lint.
- **Branch:** work on `look-css-parity`, branched from `main`. Never commit to `main`.

---

### Task 1: Create `base.css` and wire it into both render paths

`base.css` starts with only the `--space-*` / `--flow-*` fallbacks so the wiring can be verified before any component moves. Those scales are currently duplicated in all three looks' `:root`; putting fallbacks in the base makes it self-sufficient for a future look that omits them, while look values still win by load order.

**Files:**
- Create: `assets/looks/base.css`
- Modify: `includes/render/class-page-renderer.php:40-58` (`document()`)
- Modify: `includes/frontend/class-frontend.php:41-52` (`enqueue_specs()`), `:131-148` (`enqueue_assets()`)
- Test: `tests/php/FrontendTest.php`, `tests/php/PageRendererTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `Blueworx_Clubhouse_Frontend::BASE_STYLESHEET` (string const, value `'assets/looks/base.css'`); `enqueue_specs()` return array gains key `base_stylesheet_url` (string, absolute URL). `Page_Renderer::document()` emits a `<link>` to base before the look's.

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/FrontendTest.php`:

```php
	public function test_enqueue_specs_includes_the_base_stylesheet_before_the_look(): void {
		$look  = new Blueworx_Clubhouse_Court_Side();
		$specs = Blueworx_Clubhouse_Frontend::enqueue_specs( $look, ':root{}', 'https://club.test/plugins/clubhouse/' );

		$this->assertSame(
			'https://club.test/plugins/clubhouse/assets/looks/base.css',
			$specs['base_stylesheet_url']
		);
		// The look stylesheet is still resolved separately — base does not replace it.
		$this->assertSame(
			'https://club.test/plugins/clubhouse/assets/looks/court-side.css',
			$specs['stylesheet_url']
		);
	}

	public function test_base_stylesheet_is_the_same_for_every_look(): void {
		$urls = array();
		foreach ( array( new Blueworx_Clubhouse_Court_Side(), new Blueworx_Clubhouse_Floodlight(), new Blueworx_Clubhouse_Members_House() ) as $look ) {
			$specs  = Blueworx_Clubhouse_Frontend::enqueue_specs( $look, ':root{}', 'https://club.test/' );
			$urls[] = $specs['base_stylesheet_url'];
		}
		// Base is look-independent by design: a look cannot substitute its own.
		$this->assertSame( array( 'https://club.test/assets/looks/base.css' ), array_values( array_unique( $urls ) ) );
	}
```

Add to `tests/php/PageRendererTest.php`:

```php
	public function test_document_links_base_stylesheet_before_the_look(): void {
		$html = Blueworx_Clubhouse_Page_Renderer::document(
			new Blueworx_Clubhouse_Court_Side(),
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			'<main></main>',
			'/'
		);
		$base = strpos( $html, '/assets/looks/base.css' );
		$look = strpos( $html, '/assets/looks/court-side.css' );
		$this->assertNotFalse( $base, 'base.css must be linked' );
		$this->assertNotFalse( $look, 'the look stylesheet must be linked' );
		// Order is load-bearing: look rules must be able to override base rules.
		$this->assertLessThan( $look, $base );
	}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter 'base_stylesheet|links_base_stylesheet'`
Expected: FAIL — `Undefined array key "base_stylesheet_url"` and `base.css must be linked`.

- [ ] **Step 3: Create `assets/looks/base.css`**

```css
/*
 * Shared structural layer for every Base Look.
 *
 * Loaded BEFORE the active look's stylesheet, so a look can override anything
 * here. Rules use design tokens only — never a literal colour or font family —
 * so the same structure re-skins with the look's tokens.
 *
 * Selectors stay at single-class specificity. A base rule that out-specifies a
 * look rule would silently win, which is exactly the bug this file exists to
 * prevent.
 */

/*
 * Spacing and flow scales. Each look also declares these; because the look
 * loads later its values win. These exist so the base is self-sufficient and a
 * look that omits the scale still renders.
 */
:root{--space-2:8px;--space-3:12px;--space-4:16px;--space-6:24px;--space-8:32px;--space-10:40px;--space-12:48px;--space-16:64px;--space-20:80px;--flow-lg:88px;--flow-sm:24px}
```

- [ ] **Step 4: Add the constant and URL to `Frontend`**

In `includes/frontend/class-frontend.php`, add the constant beside `QUERY_VAR`:

```php
	public const QUERY_VAR = 'clubhouse_page';

	/**
	 * Structural rules shared by every look, loaded before the look's own
	 * stylesheet. Deliberately not a Base_Look method: a look substituting its
	 * own base is the drift this file prevents.
	 */
	public const BASE_STYLESHEET = 'assets/looks/base.css';
```

Then in `enqueue_specs()`, add the key:

```php
		return array(
			'font_face_css'       => Blueworx_Clubhouse_Page_Renderer::font_face_css( $look, $plugin_url ),
			'base_stylesheet_url' => $plugin_url . self::BASE_STYLESHEET,
			'stylesheet_url'      => $plugin_url . $look->stylesheet(),
			'inline_css'          => $root_css,
			'reveal_url'          => $plugin_url . 'assets/js/reveal.js',
		);
```

- [ ] **Step 5: Enqueue it in WordPress**

In `enqueue_assets()`, register base first and make the look depend on it, so WordPress cannot reorder them:

```php
		wp_enqueue_style( 'clubhouse-base', $specs['base_stylesheet_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_enqueue_style( 'clubhouse-look', $specs['stylesheet_url'], array( 'clubhouse-base' ), BLUEWORX_LABS_CLUBHOUSE_VERSION );
```

Leave the two `wp_add_inline_style( 'clubhouse-look', ... )` calls and the
`wp_enqueue_script` call exactly as they are.

- [ ] **Step 6: Emit the link in `document()`**

In `includes/render/class-page-renderer.php`, inside `document()`, add `$base` beside `$sheet` and emit it first:

```php
		$base     = htmlspecialchars( $plugin_url . Blueworx_Clubhouse_Frontend::BASE_STYLESHEET, ENT_QUOTES, 'UTF-8' );
		$sheet    = htmlspecialchars( $plugin_url . $look->stylesheet(), ENT_QUOTES, 'UTF-8' );
```

and in the returned markup, replace the single stylesheet link with:

```php
			. '<link rel="stylesheet" href="' . $base . '">'
			. '<link rel="stylesheet" href="' . $sheet . '">'
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `vendor/bin/phpunit`
Expected: PASS — all tests green (482+ tests; the count grows by the ones added above).

- [ ] **Step 8: Verify both harnesses actually serve it**

Run: `npm test`
Expected: `27 passed`.

Run: `npm run wp:up` then `npm run test:wp`
Expected: `27 passed`.

Run: `curl -s http://127.0.0.1:8705/ | grep -o 'looks/[a-z-]*\.css'`
Expected: `looks/base.css` printed before `looks/court-side.css`.

- [ ] **Step 9: Commit**

```bash
git add assets/looks/base.css includes/frontend/class-frontend.php includes/render/class-page-renderer.php tests/php/FrontendTest.php tests/php/PageRendererTest.php
git commit -m "feat: add shared base.css layer loaded before every look"
```

---

### Task 2: Add the look-parity guardrail

Written before the components move, so it fails and documents the gap first.

The invariant is parity, not absolute coverage: the 15 classes Court Side leaves unstyled are genuinely emitted and genuinely unstyled in every look (markup hooks like `ch-tiles__label` that inherit and render fine). Requiring every emitted class to carry a rule would mean styling hooks that need none.

**Files:**
- Create: `tests/php/LookCoverageTest.php`

**Interfaces:**
- Consumes: `assets/looks/base.css` from Task 1.
- Produces: nothing consumed by later tasks. Task 3 makes this test pass.

- [ ] **Step 1: Write the failing test**

Create `tests/php/LookCoverageTest.php`:

```php
<?php
// tests/php/LookCoverageTest.php

use PHPUnit\Framework\TestCase;

/**
 * Guards the invariant that every Base Look is built from the same building
 * blocks. Six components were once styled by court-side.css alone, so sports,
 * teams, events and calendar rendered unstyled under the other two looks for as
 * long as nothing checked.
 *
 * The assertion is PARITY, not absolute coverage. A handful of emitted classes
 * carry no rule in any look — markup hooks such as ch-tiles__label that inherit
 * and render correctly. Demanding a rule for each would mean styling hooks that
 * need none, or maintaining an ever-growing exemption list.
 */
final class LookCoverageTest extends TestCase {

	private const LOOKS = array( 'court-side', 'floodlight', 'members-house' );

	/**
	 * Emitted classes that carry no rule in ANY look, and correctly so. Pinned
	 * so that all three looks drifting *together* is still caught. Growing this
	 * list is a deliberate review decision, not a way to silence a failure.
	 */
	private const KNOWN_UNSTYLED = array(
		'ch-contact__line',
		'ch-faq-wrap',
		'ch-footer__brand-col',
		'ch-footer__col',
		'ch-footer__nl',
		'ch-info__col',
		'ch-info__line',
		'ch-milestone__body',
		'ch-policy',
		'ch-social__label',
		'ch-social__text',
		'ch-split',
		'ch-tabs',
		'ch-tiles__label',
	);

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Classes the renderer actually emits.
	 *
	 * Only whole, literal class tokens count. `class="ch-badge--<?php echo $mod; ?>"`
	 * would otherwise yield the non-class `ch-badge--`, so any token ending in a
	 * separator is a truncated interpolation and is dropped.
	 */
	private function emitted_classes(): array {
		$out = array();
		foreach ( array( 'includes/render/class-sections.php', 'includes/render/class-page-renderer.php' ) as $rel ) {
			$php = (string) file_get_contents( $this->root() . '/' . $rel );
			preg_match_all( '/class="([^"]*)"/', $php, $attrs );
			foreach ( $attrs[1] as $attr ) {
				preg_match_all( '/\bch-[a-z0-9_-]+/', $attr, $classes );
				foreach ( $classes[0] as $class ) {
					if ( str_ends_with( $class, '-' ) || str_ends_with( $class, '_' ) ) {
						continue; // Truncated by a PHP interpolation — not a real class.
					}
					$out[ $class ] = true;
				}
			}
		}
		return array_keys( $out );
	}

	/**
	 * Classes some selector in the given stylesheets targets.
	 *
	 * Matches whole class tokens anywhere in a selector, so descendant and child
	 * combinators count: `.ch-split > *` covers `ch-split`. Matching on the whole
	 * token also stops `.ch-contact__lines` from satisfying `ch-contact__line`.
	 */
	private function styled_classes( string ...$paths ): array {
		$out = array();
		foreach ( $paths as $path ) {
			$css = (string) file_get_contents( $path );
			$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css ); // Comments name classes too.
			// Blank out declaration bodies so a class named in a property value
			// (a content: string, say) is never mistaken for a selector.
			$selectors = preg_replace( '/\{[^{}]*\}/', '|', $css );
			preg_match_all( '/\.(ch-[a-z0-9_-]+)/', (string) $selectors, $found );
			foreach ( $found[1] as $class ) {
				$out[ $class ] = true;
			}
		}
		return array_keys( $out );
	}

	/** @return array<int,string> emitted classes no rule in base.css or this look targets */
	private function unstyled_for( string $look ): array {
		$styled = $this->styled_classes(
			$this->root() . '/assets/looks/base.css',
			$this->root() . '/assets/looks/' . $look . '.css'
		);
		$gap = array_values( array_diff( $this->emitted_classes(), $styled ) );
		sort( $gap );
		return $gap;
	}

	public function test_every_look_leaves_the_same_classes_unstyled(): void {
		$gaps = array();
		foreach ( self::LOOKS as $look ) {
			$gaps[ $look ] = $this->unstyled_for( $look );
		}
		$reference = $gaps[ self::LOOKS[0] ];
		foreach ( self::LOOKS as $look ) {
			$this->assertSame(
				$reference,
				$gaps[ $look ],
				sprintf(
					'%s does not use the same building blocks as %s. Only in %s: %s',
					$look,
					self::LOOKS[0],
					$look,
					implode( ', ', array_diff( $gaps[ $look ], $reference ) ) ?: '(none)'
				)
			);
		}
	}

	public function test_the_shared_unstyled_set_has_not_grown(): void {
		$expected = self::KNOWN_UNSTYLED;
		sort( $expected );
		$this->assertSame(
			$expected,
			$this->unstyled_for( 'court-side' ),
			'The set of unstyled classes changed. If a new component is genuinely '
				. 'style-free, add it to KNOWN_UNSTYLED with a reason; otherwise style it.'
		);
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter LookCoverageTest`
Expected: FAIL on `test_every_look_leaves_the_same_classes_unstyled` — floodlight and members-house each report 47 extra unstyled classes (`ch-cal*`, `ch-scard*`, `ch-event*`, `ch-archive*`, `ch-hero-f*`, `ch-filter*`, `ch-social*`).

`test_the_shared_unstyled_set_has_not_grown` should already PASS, since Court Side's set is the documented 15.

- [ ] **Step 3: Commit the failing guardrail**

Committing it red is deliberate — it records the gap the next task closes.

```bash
git add tests/php/LookCoverageTest.php
git commit -m "test: add look parity guardrail (currently failing, documents the gap)"
```

---

### Task 3: Move the six shared components into `base.css`

`court-side.css:450-531` is already fully tokenized, so this is a move, not a rewrite. Verified safe: nothing in `court-side.css:1-449` references those selectors.

**Files:**
- Modify: `assets/looks/base.css`
- Modify: `assets/looks/court-side.css:450-531` (delete)

**Interfaces:**
- Consumes: `base.css` from Task 1, `LookCoverageTest` from Task 2.
- Produces: `base.css` styling `ch-hero-f`, `ch-filter(s)`, `ch-scard(s)`, `ch-event(s)`, `ch-archive`, `ch-cal`, `ch-social`.

- [ ] **Step 1: Confirm the block boundaries before cutting**

Run: `sed -n '450p;531p' assets/looks/court-side.css`
Expected: line 450 is `/* ---- Filter hero (Sports/Teams/Events/Calendar) ---- */`, line 531 is the `@media(max-width:760px)` rule ending `.ch-social__link{flex:1 1 160px;justify-content:center}}`. 531 is the last line of the file.

Run: `head -449 assets/looks/court-side.css | grep -cE 'ch-(hero-f|filters?|scards?|events?|archive|cal|social)[^a-z-]'`
Expected: `0`. If it is not 0, stop — moving would change the cascade and the plan's safety analysis no longer holds.

- [ ] **Step 2: Append the block to `base.css`**

```bash
sed -n '450,531p' assets/looks/court-side.css >> assets/looks/base.css
```

- [ ] **Step 3: Remove the block from `court-side.css`**

```bash
sed -i '450,531d' assets/looks/court-side.css
```

- [ ] **Step 4: Add a heading comment above the moved block in `base.css`**

Insert immediately before the `/* ---- Filter hero` comment that step 2 appended:

```css
/*
 * Moved verbatim from court-side.css. These six components were styled by that
 * look alone, leaving sports, teams, events and calendar unstyled under
 * Floodlight and Members House. They were already fully tokenized, so they are
 * shared as-is rather than rewritten.
 *
 * The .ch-main:has(...) + .ch-footer rule below is the one compound selector in
 * this file. It is safe above court-side.css's own .ch-footer rule because it
 * wins on specificity (0,4,0 against 0,1,0), not on order.
 */
```

- [ ] **Step 5: Run the guardrail to verify it passes**

Run: `vendor/bin/phpunit --filter LookCoverageTest`
Expected: PASS — both tests green. All three looks now report the same 15 unstyled classes.

- [ ] **Step 6: Run the full PHP suite**

Run: `vendor/bin/phpunit`
Expected: PASS.

Note `CourtSideStylesheetTest` asserts selectors are present in `court-side.css`. If it names any of the moved selectors it will now fail — that is a real signal, not a nuisance. Move those specific assertions into a new `BaseStylesheetTest` rather than deleting them.

- [ ] **Step 7: Verify Court Side is visually unchanged**

Run: `npm run wp:up` (if not already running), then open each of `/sports/`, `/teams/`, `/events/`, `/calendar/`, `/` and `/contact/` on `http://127.0.0.1:8705` and confirm they render exactly as before the move. Court Side is the reviewed, shipping look — this is the check that the specificity constraint held.

- [ ] **Step 8: Commit**

```bash
git add assets/looks/base.css assets/looks/court-side.css tests/php/
git commit -m "refactor: move the six shared components into base.css

Sports, teams, events and calendar rendered unstyled under Floodlight and
Members House because these six components were styled by court-side.css
alone. Moved rather than copied: they were already fully tokenized, and
duplicating them would only guarantee the copies drift."
```

---

### Task 4: Assert the looks really render, in a browser

The PHPUnit guardrail is static — it cannot catch a rule that exists but never matches the emitted markup. This does.

**Files:**
- Create: `tests/look-parity.spec.js`

**Interfaces:**
- Consumes: `base.css` styling the six components (Task 3).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

Create `tests/look-parity.spec.js`:

```js
const { test, expect } = require('@playwright/test');

// Static coverage (LookCoverageTest) proves a rule exists for each class. It
// cannot prove the rule matches what the renderer emits. These assertions load
// each look in a browser and read computed styles off the six components that
// were once Court Side only.
//
// Computed values, not screenshots — there are no baselines to churn when the
// design legitimately changes.

const LOOKS = ['court-side', 'floodlight', 'members-house'];

// One marker per previously-broken component, with a property that is unset
// until the component is styled.
const CHECKS = [
  { page: 'sports',   selector: '.ch-filters',    prop: 'display',       expected: 'flex' },
  { page: 'teams',    selector: '.ch-scard',      prop: 'display',       expected: 'flex' },
  { page: 'events',   selector: '.ch-event',      prop: 'display',       expected: 'flex' },
  { page: 'calendar', selector: '.ch-cal',        prop: 'display',       expected: 'flex' },
];

// Switches look, then PROVES it switched.
//
// Without the proof this spec is dangerous: against the DB-free preview the look
// comes from ?look=, not this cookie, so every "look" would resolve to Court Side
// and all assertions below would pass while testing one look three times. A spec
// that silently tests nothing is worse than no spec.
async function useLook(page, look, slug) {
  await page.goto(`?clubhouse_page=${slug}`);
  await page.context().addCookies([
    { name: 'clubhouse_demo_look', value: look, url: new URL(page.url()).origin },
  ]);
  await page.goto(`?clubhouse_page=${slug}`);

  const family = await page.evaluate(() =>
    getComputedStyle(document.documentElement).getPropertyValue('--font-display').trim()
  );
  expect(family, `look "${look}" did not take effect — is demo mode on?`).not.toBe('');
  return family;
}

for (const look of LOOKS) {
  for (const { page: slug, selector, prop, expected } of CHECKS) {
    test(`${look}: ${selector} on ${slug} is styled`, async ({ page }) => {
      await useLook(page, look, slug);

      const el = page.locator(selector).first();
      await expect(el, `${selector} must exist on ${slug}`).toBeAttached();
      const actual = await el.evaluate((node, p) => getComputedStyle(node)[p], prop);
      expect(actual, `${selector} under ${look}`).toBe(expected);
    });
  }
}

for (const look of LOOKS) {
  test(`${look}: the social band is styled`, async ({ page }) => {
    await useLook(page, look, 'contact');
    const link = page.locator('.ch-social__link').first();
    await expect(link).toBeAttached();
    // Unstyled anchors are inline; the pill treatment makes them inline-flex.
    const display = await link.evaluate((n) => getComputedStyle(n).display);
    expect(display, `.ch-social__link under ${look}`).toBe('inline-flex');
  });
}

// The looks must actually differ. If this passes while the others fail, the
// cookie is being ignored and the suite above is testing Court Side three times.
test('the three looks resolve to three different display fonts', async ({ page }) => {
  const seen = new Set();
  for (const look of LOOKS) {
    seen.add(await useLook(page, look, 'home'));
  }
  expect(seen.size, `expected 3 distinct display fonts, saw ${[...seen].join(' | ')}`).toBe(3);
});
```

- [ ] **Step 2: Run it against the preview to check the assertions are sound**

Run: `npx playwright test tests/look-parity.spec.js --project=wordpress`
Expected: the per-look styling assertions PASS (the preview serves Court Side, where these six components have always been styled), and `the three looks resolve to three different display fonts` **FAILS** with `expected 3 distinct display fonts`.

That failure is correct and is the point: the preview resolves the look from `?look=`, not the demo cookie. Do not "fix" it here — it proves the guard works. This spec is only meaningful against real WordPress.

- [ ] **Step 3: Run it against real WordPress**

Run: `npm run wp:up` then `npm run test:wp`
Expected: PASS — the full suite, now 27 + 16 = 43 tests, including the three-distinct-fonts guard.

If that guard fails here, demo mode is off: confirm `tests/global-setup.js` printed `global-setup: demo mode on.`

- [ ] **Step 4: Prove the spec would have caught the original bug**

Temporarily comment out the `.ch-filters` rule in `assets/looks/base.css`, then:

Run: `npm run test:wp`
Expected: FAIL on `floodlight: .ch-filters on sports is styled`.

Restore the rule and re-run to confirm green. A guardrail never seen to fail is not known to work.

- [ ] **Step 5: Commit**

```bash
git add tests/look-parity.spec.js
git commit -m "test: assert every look renders the six shared components"
```

---

### Task 5: Document, version, and verify the whole slice

**Files:**
- Modify: `blueworx-labs-clubhouse.php:6` (header `Version:`), `:24` (`BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `package.json:3` (`version`)
- Modify: `CHANGELOG.md`
- Modify: `docs/testing.md`

**Interfaces:**
- Consumes: everything from Tasks 1–4.
- Produces: a releasable branch.

- [ ] **Step 1: Bump the version to 0.26.5 in all three places**

```bash
sed -i "s/ \* Version:           0.26.4/ * Version:           0.26.5/" blueworx-labs-clubhouse.php
sed -i "s/define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.26.4' );/define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.26.5' );/" blueworx-labs-clubhouse.php
sed -i "s/\"version\": \"0.26.4\",/\"version\": \"0.26.5\",/" package.json
```

Run: `grep -n "0.26.5" blueworx-labs-clubhouse.php package.json`
Expected: three matching lines.

- [ ] **Step 2: Add the changelog entry**

Insert directly above `## 0.26.4` in `CHANGELOG.md`:

```markdown
## 0.26.5

- **Fixed: the Floodlight and Members House looks now render every page.** The fixtures, teams, sports and events pages were missing their styling entirely under those two looks — filters, cards and the calendar showed as unstyled text — because six components were only ever styled for Court Side. All three looks now share one set of building blocks, so switching look re-skins the whole site rather than part of it. Court Side is unchanged.
```

- [ ] **Step 3: Document the guardrail**

Append to `docs/testing.md`:

```markdown
## Look parity

Every Base Look must render every page. Two checks enforce it, because they
fail on different things:

- `tests/php/LookCoverageTest.php` — asserts the three looks leave the *same*
  classes unstyled. Parity rather than absolute coverage: some emitted classes
  are markup hooks that need no rule, and demanding one for each would mean an
  exemption list that grows forever.
- `tests/look-parity.spec.js` — loads each look in a browser and reads computed
  styles off the six components that were once Court Side only. Catches a rule
  that exists but never matches the markup, which the static check cannot.

Structural rules belong in `assets/looks/base.css`, which loads before the look
and uses design tokens only. Selectors there stay at single-class specificity —
a base rule that out-specifies a look rule wins silently, which is the bug this
whole layer exists to prevent.
```

- [ ] **Step 4: Run every check**

```bash
vendor/bin/phpunit
npm test
npm run test:wp
composer lint
npm run build:zip
```

Expected, in order: PHPUnit PASS; `27 passed`; `40 passed`; PHPCS `0 errors`; zip verification all `ok:` lines.

Per the project's linting rule, run `composer lint` **once** here. If it reports findings, present them and wait — do not auto-fix in a loop.

- [ ] **Step 5: Commit and open the PR**

```bash
git add -A
git commit -m "docs: document look parity, bump to 0.26.5"
git push -u origin look-css-parity
gh pr create --title "Look CSS parity: all three looks render every page" --body "$(cat <<'EOF'
Sports, teams, events and calendar rendered essentially unstyled under Floodlight
and Members House: six components were styled by court-side.css alone. Found by
diffing the classes Sections emits against the selectors each look defines — 47
missing classes, an identical gap in both looks.

- `assets/looks/base.css` holds structural rules against design tokens only, and
  loads before the active look so look rules keep winning.
- The six components are **moved** into it, not copied. They were already fully
  tokenized, and duplicating them would only guarantee the copies drift.
- `LookCoverageTest` asserts the three looks leave the same classes unstyled.
- `look-parity.spec.js` asserts real computed styles under each look, catching
  rules that exist but never match.

Court Side is unchanged — verified visually and by specificity analysis.

Spec: `docs/superpowers/specs/2026-07-21-look-css-parity-design.md`
Plan: `docs/superpowers/plans/2026-07-21-look-css-parity.md`

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## What this slice deliberately does not do

The UX/UI review of 2026-07-21 is slices 1–3 and is untouched here:

1. **Cross-cutting fixes** — the footer FILCS row (five hardcoded `href="#"` letter-circles including two non-networks, while real branding URLs exist), one CTA label per destination, consistent pill treatment, dead links, body/display font pairing.
2. **Page structure and flow** — home section order, announcement/ticker merge, About timeline and Facilities placement, Membership pricing above the fold, the no-op filter pills.
3. **Style unification** — the three hero components, and the empty `.ch-media--empty` block `hero()` emits when `image_alt` is set without an image.

Migrating the *rest* of the shared rules out of the three look stylesheets into
`base.css` also stays out of scope. The guardrail makes that safe to do
incrementally, one component at a time.
