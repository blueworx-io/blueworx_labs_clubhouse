# Full-bleed Home Hero Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reshape the Home page hero into a full-bleed background composition with overlaid content and integrated icon quick-links, adopted by all three Base Looks, without touching the shared `hero()` renderer or any copy.

**Architecture:** Add a Home-only `home_hero()` section renderer (skin-agnostic markup) alongside the untouched `hero()`. Wire `Page_Renderer::home()` to it and fold the four existing quick-links into the hero as an icon-card foot (removing the separate `quick_tiles` section on Home). Each of the three look stylesheets gains a `.ch-home-hero` block styling the full-bleed frame, legibility scrim, overlaid light text, and tile row in that look's character. A graceful no-image fallback panel keeps the DB-free preview correct.

**Tech Stack:** PHP 8.2+ (WordPress plugin, PHPCS-linted), plain CSS per Base Look, Playwright (JS) against the DB-free PHP preview.

## Global Constraints

- Requires PHP: 8.2 — keep `declare(strict_types=1)` and existing escaping discipline (`self::e()` on every interpolated string).
- Sections stay **skin-agnostic**: markup uses only `ch-*` classes — no colours, fonts, radii, or look slugs. All styling lives in the three `assets/looks/*.css` files.
- **No copy changes:** hero eyebrow/title/lede and CTA labels, and the four quick-link labels + hrefs, stay exactly as they are today.
- **No new dependencies** (`approved-deps.json` unchanged).
- Do **not** modify the shared `hero()` renderer or the About/Membership/Sports/Events pages that call it.
- Version: minor bump `0.22.1 → 0.23.0`, kept in sync across the plugin header, the `BLUEWORX_LABS_CLUBHOUSE_VERSION` constant, and `package.json`; changelog updated alongside.
- Title case is left unchanged (mixed case as today). Uppercase is a type-styling choice, not part of this layout change — do not add `text-transform`.
- PHPCS run once as a final check; findings presented to the user, not auto-fixed in a loop.

---

### Task 1: `home_hero()` renderer + Home wiring (structural)

Adds the new markup and points Home at it. Test asserts structure only (look-agnostic), so it passes regardless of CSS.

**Files:**
- Modify: `includes/render/class-sections.php` (add icon constants + `home_hero()` method)
- Modify: `includes/render/class-page-renderer.php:163-186` (Home hero + quick_tiles block)
- Test: `tests/home-hero.spec.js` (create)

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Links::url()` (existing), `self::e()` (existing private escaper).
- Produces: `Blueworx_Clubhouse_Sections::home_hero( array $data ): string` where
  `$data = { eyebrow, title_lead, title_highlight, lede, cta_primary, cta_primary_href, cta_secondary, cta_secondary_href, image, image_alt, tiles }`
  and `tiles` is an ordered array of `{ label:string, href:string, icon:string }`
  with `icon` in `{ 'join','tour','fixtures','contact' }`. Emits a
  `<section class="ch-home-hero">` containing `.ch-home-hero__bg`
  (with `--empty` modifier when `image === ''`), `.ch-home-hero__scrim`,
  `.ch-home-hero__in` (also `.ch-wrap`) holding `.ch-eyebrow`,
  `h1.ch-home-hero__title` (+ `.ch-home-hero__hl`), `.ch-home-hero__lede`,
  `.ch-home-hero__cta`, and `.ch-home-hero__foot` of `.ch-home-hero__tile`
  links (`.ch-home-hero__tile-ico` / `-label` / `-arrow`).

- [ ] **Step 1: Write the failing test**

Create `tests/home-hero.spec.js`:

```javascript
const { test, expect } = require('@playwright/test');

// The full-bleed Home hero (home_hero) replaces the shared hero() on Home and
// folds the quick-links into its foot, so the ticker follows the hero directly.
// Structural assertions only — look-agnostic (markup is identical across looks).

test('home renders the full-bleed hero, not the shared hero', async ({ page }) => {
  const response = await page.goto('?page=home');
  expect(response?.status(), 'HTTP status for home').toBe(200);
  await expect(page).toHaveTitle(/.+/);
  await expect(page.locator('#ch-main')).toBeVisible();
  await expect(page.locator('.ch-home-hero')).toHaveCount(1);
  await expect(page.locator('.ch-hero')).toHaveCount(0);
});

test('home hero contains the four icon quick-links with their labels', async ({ page }) => {
  await page.goto('?page=home');
  const tiles = page.locator('.ch-home-hero .ch-home-hero__tile');
  await expect(tiles).toHaveCount(4);
  await expect(tiles.filter({ hasText: 'Join the club' })).toHaveCount(1);
  await expect(tiles.filter({ hasText: 'Take a tour' })).toHaveCount(1);
  await expect(tiles.filter({ hasText: 'See fixtures' })).toHaveCount(1);
  await expect(tiles.filter({ hasText: 'Get in touch' })).toHaveCount(1);
});

test('home no longer emits a separate quick_tiles section', async ({ page }) => {
  await page.goto('?page=home');
  await expect(page.locator('.ch-tiles-sec')).toHaveCount(0);
});

test('the ticker immediately follows the home hero', async ({ page }) => {
  await page.goto('?page=home');
  const nextTag = await page.evaluate(() => {
    const hero = document.querySelector('.ch-home-hero');
    return hero?.nextElementSibling?.className || '';
  });
  expect(nextTag).toContain('ch-ticker');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx playwright test tests/home-hero.spec.js`
Expected: FAIL — `.ch-home-hero` count is 0 (Home still renders `.ch-hero` and a separate `.ch-tiles-sec`).

- [ ] **Step 3: Add tile icon constants to `class-sections.php`**

Add these constants inside `final class Blueworx_Clubhouse_Sections`, next to the existing `FACEBOOK_ICON` group (self-hosted inline SVG, `currentColor`, no icon font):

```php
	/** Task-tile icons — inline SVG, inherit colour via currentColor. */
	private const TILE_ICONS = array(
		'join'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>',
		'tour'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m16.2 7.8-2.9 6.3-6.3 2.9 2.9-6.3 6.3-2.9z"/></svg>',
		'fixtures' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
		'contact'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>',
	);
```

- [ ] **Step 4: Add the `home_hero()` method to `class-sections.php`**

Add immediately after the existing `hero()` method (after its closing `}` around line 114):

```php
	/**
	 * Home-only full-bleed hero: a background image (or toned fallback panel) with
	 * the hero content and an integrated icon quick-link row overlaid. Distinct from
	 * hero() so the four other pages that use hero() are unaffected.
	 *
	 * @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,
	 *   cta_primary:string,cta_primary_href:string,cta_secondary:string,cta_secondary_href:string,
	 *   image:string,image_alt:string,
	 *   tiles:array<int,array{label:string,href:string,icon:string}>} $data
	 */
	public static function home_hero( array $data ): string {
		$has_img = '' !== $data['image'];
		$bg      = '<div class="ch-home-hero__bg' . ( $has_img ? '' : ' ch-home-hero__bg--empty' ) . '">'
			. ( $has_img ? '<img class="ch-home-hero__img" src="' . self::e( $data['image'] ) . '" alt="' . self::e( $data['image_alt'] ) . '">' : '' )
			. '</div>';
		$tiles = '';
		foreach ( $data['tiles'] as $t ) {
			$svg = self::TILE_ICONS[ $t['icon'] ] ?? '';
			$ico = '' !== $svg ? '<span class="ch-home-hero__tile-ico" aria-hidden="true">' . $svg . '</span>' : '';
			$tiles .= '<a class="ch-home-hero__tile" role="listitem" href="' . self::e( $t['href'] ) . '">'
				. $ico
				. '<span class="ch-home-hero__tile-label">' . self::e( $t['label'] ) . '</span>'
				. '<span class="ch-home-hero__tile-arrow" aria-hidden="true">→</span></a>';
		}
		return '<section class="ch-home-hero">'
			. $bg
			. '<div class="ch-home-hero__scrim" aria-hidden="true"></div>'
			. '<div class="ch-wrap ch-home-hero__in">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h1 class="ch-home-hero__title">' . self::e( $data['title_lead'] )
			. '<span class="ch-home-hero__hl">' . self::e( $data['title_highlight'] ) . '</span></h1>'
			. '<p class="ch-home-hero__lede">' . self::e( $data['lede'] ) . '</p>'
			. '<div class="ch-home-hero__cta">'
			. '<a class="ch-btn ch-btn--accent" href="' . self::e( $data['cta_primary_href'] ) . '">' . self::e( $data['cta_primary'] ) . '</a>'
			. '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['cta_secondary_href'] ) . '">' . self::e( $data['cta_secondary'] ) . '</a>'
			. '</div>'
			. '<div class="ch-home-hero__foot" role="list">' . $tiles . '</div>'
			. '</div></section>';
	}
```

- [ ] **Step 5: Wire `Page_Renderer::home()` to `home_hero()` and drop the separate quick_tiles**

In `includes/render/class-page-renderer.php`, replace the current hero block **and** the quick_tiles block (lines 163–186) with:

```php
		if ( $visibility->is_section_visible( 'home', 'hero' ) ) {
			// Home uses the full-bleed home_hero() (not the shared hero()); the
			// quick-links live in its foot, so no separate quick_tiles section here.
			$out .= Blueworx_Clubhouse_Sections::home_hero( array(
				'eyebrow'            => 'Est. 1974 · Marlow, UK',
				'title_lead'         => 'Every sport. Every age. ',
				'title_highlight'    => 'One community.',
				'lede'               => "Nine sports, twenty-four teams, and a clubhouse that's always open. Come for the game — stay for the people.",
				'cta_primary'        => 'Explore membership',
				'cta_primary_href'   => Blueworx_Clubhouse_Links::url( 'membership' ),
				'cta_secondary'      => 'Take a tour →',
				'cta_secondary_href' => Blueworx_Clubhouse_Links::url( 'about' ),
				'image'              => '',
				'image_alt'          => 'ClubHouse floodlit pitch on a Saturday',
				'tiles'              => array(
					array( 'label' => 'Join the club', 'href' => Blueworx_Clubhouse_Links::url( 'membership' ), 'icon' => 'join' ),
					array( 'label' => 'Take a tour', 'href' => Blueworx_Clubhouse_Links::url( 'about' ), 'icon' => 'tour' ),
					array( 'label' => 'See fixtures', 'href' => Blueworx_Clubhouse_Links::url( 'calendar' ), 'icon' => 'fixtures' ),
					array( 'label' => 'Get in touch', 'href' => Blueworx_Clubhouse_Links::url( 'contact' ), 'icon' => 'contact' ),
				),
			) );
		}
```

Leave the `ticker`, `stat_strip`, and everything after unchanged.

- [ ] **Step 6: Run the test to verify it passes**

Run: `npx playwright test tests/home-hero.spec.js`
Expected: PASS (4 tests).

- [ ] **Step 7: Run the full smoke suite to confirm no regressions**

Run: `npx playwright test`
Expected: PASS — existing `tests/smoke.spec.js` still green (Home marker is `.ch-cards`, which is unchanged; other pages untouched).

- [ ] **Step 8: Commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php tests/home-hero.spec.js
git commit -m "feat: full-bleed home_hero renderer with integrated icon quick-links"
```

---

### Task 2: Court Side `.ch-home-hero` styling

**Files:**
- Modify: `assets/looks/court-side.css` (add a `.ch-home-hero` block near the existing `.ch-hero` rules, ~line 133)

**Interfaces:**
- Consumes: markup/classes from Task 1; existing tokens (`--color-ink`, `--color-bg`, `--color-paper`, `--color-accent`, `--color-accent-ink`, `--color-accent-deep`, `--color-accent-wash`, `--font-display`, `--radius-md`, `--space-8/12/16`) and the `ch-rise` keyframe already defined in this file.
- Produces: no downstream interface.

- [ ] **Step 1: Add the Court Side full-bleed hero block**

Append after the existing hero animation rules (around line 133 in `assets/looks/court-side.css`):

```css
/* Full-bleed Home hero (home_hero) — Court Side: punchy, high-contrast. */
.ch-home-hero{position:relative;isolation:isolate;overflow:hidden;min-height:clamp(560px,84vh,820px);display:flex;align-items:flex-end;padding-top:var(--space-16)}
.ch-home-hero__bg{position:absolute;inset:0;z-index:-2;background:var(--color-ink)}
.ch-home-hero__bg--empty{background:radial-gradient(96% 82% at 82% -12%,var(--color-accent-wash),transparent 55%),var(--color-ink)}
.ch-home-hero__img{width:100%;height:100%;object-fit:cover;display:block}
.ch-home-hero__scrim{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,color-mix(in oklab,var(--color-ink) 26%,transparent),color-mix(in oklab,var(--color-ink) 80%,transparent))}
.ch-home-hero__in{width:100%;padding-top:var(--space-12);padding-bottom:var(--space-8);color:var(--color-bg)}
.ch-home-hero .ch-eyebrow{color:var(--color-accent)}
.ch-home-hero__title{font-family:var(--font-display);font-weight:800;font-size:clamp(40px,8.5vw,116px);letter-spacing:-.03em;line-height:.96;color:var(--color-bg);overflow-wrap:break-word;hyphens:auto}
.ch-home-hero__hl{background:var(--color-accent);color:var(--color-accent-ink);padding:0 .12em;border-radius:14px;display:inline-block;max-width:100%;transform:rotate(-1.5deg)}
.ch-home-hero__lede{max-width:44ch;font-size:19px;margin-top:22px;color:color-mix(in oklab,var(--color-bg) 88%,transparent)}
.ch-home-hero__cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:26px}
.ch-home-hero .ch-btn--ghost{background:transparent;color:var(--color-bg);border:1px solid color-mix(in oklab,var(--color-bg) 50%,transparent)}
.ch-home-hero .ch-btn--ghost:hover{background:var(--color-bg);color:var(--color-ink);border-color:var(--color-bg)}
.ch-home-hero__foot{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-top:var(--space-8)}
.ch-home-hero__tile{display:flex;align-items:center;gap:12px;background:color-mix(in oklab,var(--color-paper) 92%,transparent);color:var(--color-ink);border-radius:var(--radius-md);padding:16px 18px;font-weight:600;font-size:15px;backdrop-filter:blur(6px);transition:background .18s ease,color .18s ease}
.ch-home-hero__tile:hover{background:var(--color-ink);color:var(--color-bg)}
.ch-home-hero__tile-ico{display:flex;flex:none;color:var(--color-accent-deep)}
.ch-home-hero__tile:hover .ch-home-hero__tile-ico{color:var(--color-accent)}
.ch-home-hero__tile-label{flex:1}
.ch-home-hero__tile-arrow{flex:none;color:var(--color-accent-deep)}
.ch-home-hero__tile:hover .ch-home-hero__tile-arrow{color:var(--color-accent)}
@media(prefers-reduced-motion:no-preference){.ch-home-hero .ch-eyebrow,.ch-home-hero__title,.ch-home-hero__lede,.ch-home-hero__cta,.ch-home-hero__foot{animation:ch-rise .7s cubic-bezier(.2,.7,.2,1) both}.ch-home-hero__title{animation-delay:.07s}.ch-home-hero__lede{animation-delay:.13s}.ch-home-hero__cta{animation-delay:.19s}.ch-home-hero__foot{animation-delay:.25s}}
@media(max-width:640px){.ch-home-hero{min-height:auto}.ch-home-hero__foot{grid-template-columns:1fr}}
```

- [ ] **Step 2: Verify in the preview (Court Side)**

Ensure the preview server is running (`php -S 127.0.0.1:8124` from the plugin root). Navigate to `http://127.0.0.1:8124/preview/?look=court-side&page=home`, screenshot the hero, and confirm: full-bleed toned panel (no image), light eyebrow/title with the lime highlight block, both CTAs legible, four icon tiles tucked at the base, ticker directly below. Check narrow width (resize to ~390px): tiles stack, text stays legible.

- [ ] **Step 3: Commit**

```bash
git add assets/looks/court-side.css
git commit -m "feat: Court Side full-bleed home hero styling"
```

---

### Task 3: Floodlight `.ch-home-hero` styling

**Files:**
- Modify: `assets/looks/floodlight.css` (add a `.ch-home-hero` block near the existing `.ch-hero` rules, ~line 116)

**Interfaces:**
- Consumes: markup from Task 1; Floodlight tokens and its `ch-rise` keyframe. Mirrors Task 2 but with Floodlight's glow character on the highlight and scrim.
- Produces: no downstream interface.

- [ ] **Step 1: Add the Floodlight full-bleed hero block**

Append after the existing hero rules (around line 116 in `assets/looks/floodlight.css`). Identical to the Court Side block in Step 1 of Task 2 **except** these look-specific rules replace their Court Side equivalents:

```css
/* Full-bleed Home hero (home_hero) — Floodlight: dark, accent glow. */
.ch-home-hero__bg--empty{background:radial-gradient(90% 70% at 84% -12%,color-mix(in oklab,var(--color-accent) 22%,transparent),transparent 60%),var(--color-ink)}
.ch-home-hero__scrim{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,color-mix(in oklab,var(--color-ink) 34%,transparent),color-mix(in oklab,var(--color-ink) 82%,transparent))}
.ch-home-hero__title{font-family:var(--font-display);font-weight:700;font-size:clamp(40px,8.5vw,116px);letter-spacing:-.02em;line-height:.98;color:var(--color-bg);overflow-wrap:break-word;hyphens:auto}
.ch-home-hero__hl{color:var(--color-accent);text-shadow:0 0 30px color-mix(in oklab,var(--color-accent) 65%,transparent);display:inline;max-width:100%}
.ch-home-hero__tile{display:flex;align-items:center;gap:12px;background:color-mix(in oklab,var(--color-paper) 14%,transparent);color:var(--color-bg);border:1px solid color-mix(in oklab,var(--color-bg) 22%,transparent);border-radius:var(--radius-md);padding:16px 18px;font-weight:600;font-size:15px;backdrop-filter:blur(8px);transition:border-color .18s ease,box-shadow .2s ease}
.ch-home-hero__tile:hover{border-color:color-mix(in oklab,var(--color-accent) 60%,transparent);box-shadow:0 0 24px -8px color-mix(in oklab,var(--color-accent) 70%,transparent)}
.ch-home-hero__tile-ico{display:flex;flex:none;color:var(--color-accent)}
.ch-home-hero__tile-arrow{flex:none;color:var(--color-accent)}
```

And include (unchanged from Task 2) the shared rules: `.ch-home-hero`, `.ch-home-hero__bg`, `.ch-home-hero__img`, `.ch-home-hero__in`, `.ch-home-hero .ch-eyebrow`, `.ch-home-hero__lede`, `.ch-home-hero__cta`, `.ch-home-hero .ch-btn--ghost` (+`:hover`), `.ch-home-hero__foot`, `.ch-home-hero__tile-label`, and both `@media` blocks. Full block to paste:

```css
/* Full-bleed Home hero (home_hero) — Floodlight: dark, accent glow. */
.ch-home-hero{position:relative;isolation:isolate;overflow:hidden;min-height:clamp(560px,84vh,820px);display:flex;align-items:flex-end;padding-top:var(--space-16)}
.ch-home-hero__bg{position:absolute;inset:0;z-index:-2;background:var(--color-ink)}
.ch-home-hero__bg--empty{background:radial-gradient(90% 70% at 84% -12%,color-mix(in oklab,var(--color-accent) 22%,transparent),transparent 60%),var(--color-ink)}
.ch-home-hero__img{width:100%;height:100%;object-fit:cover;display:block}
.ch-home-hero__scrim{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,color-mix(in oklab,var(--color-ink) 34%,transparent),color-mix(in oklab,var(--color-ink) 82%,transparent))}
.ch-home-hero__in{width:100%;padding-top:var(--space-12);padding-bottom:var(--space-8);color:var(--color-bg)}
.ch-home-hero .ch-eyebrow{color:var(--color-accent)}
.ch-home-hero__title{font-family:var(--font-display);font-weight:700;font-size:clamp(40px,8.5vw,116px);letter-spacing:-.02em;line-height:.98;color:var(--color-bg);overflow-wrap:break-word;hyphens:auto}
.ch-home-hero__hl{color:var(--color-accent);text-shadow:0 0 30px color-mix(in oklab,var(--color-accent) 65%,transparent);display:inline;max-width:100%}
.ch-home-hero__lede{max-width:44ch;font-size:19px;margin-top:22px;color:color-mix(in oklab,var(--color-bg) 88%,transparent)}
.ch-home-hero__cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:26px}
.ch-home-hero .ch-btn--ghost{background:transparent;color:var(--color-bg);border:1px solid color-mix(in oklab,var(--color-bg) 50%,transparent)}
.ch-home-hero .ch-btn--ghost:hover{background:var(--color-bg);color:var(--color-ink);border-color:var(--color-bg)}
.ch-home-hero__foot{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-top:var(--space-8)}
.ch-home-hero__tile{display:flex;align-items:center;gap:12px;background:color-mix(in oklab,var(--color-paper) 14%,transparent);color:var(--color-bg);border:1px solid color-mix(in oklab,var(--color-bg) 22%,transparent);border-radius:var(--radius-md);padding:16px 18px;font-weight:600;font-size:15px;backdrop-filter:blur(8px);transition:border-color .18s ease,box-shadow .2s ease}
.ch-home-hero__tile:hover{border-color:color-mix(in oklab,var(--color-accent) 60%,transparent);box-shadow:0 0 24px -8px color-mix(in oklab,var(--color-accent) 70%,transparent)}
.ch-home-hero__tile-ico{display:flex;flex:none;color:var(--color-accent)}
.ch-home-hero__tile-label{flex:1}
.ch-home-hero__tile-arrow{flex:none;color:var(--color-accent)}
@media(prefers-reduced-motion:no-preference){.ch-home-hero .ch-eyebrow,.ch-home-hero__title,.ch-home-hero__lede,.ch-home-hero__cta,.ch-home-hero__foot{animation:ch-rise .7s cubic-bezier(.2,.7,.2,1) both}.ch-home-hero__title{animation-delay:.07s}.ch-home-hero__lede{animation-delay:.13s}.ch-home-hero__cta{animation-delay:.19s}.ch-home-hero__foot{animation-delay:.25s}}
@media(max-width:640px){.ch-home-hero{min-height:auto}.ch-home-hero__foot{grid-template-columns:1fr}}
```

- [ ] **Step 2: Verify in the preview (Floodlight)**

Navigate to `http://127.0.0.1:8124/preview/?look=floodlight&page=home`, screenshot, and confirm the dark glow treatment: accent-glow highlight, translucent bordered tiles that glow on hover, legible CTAs, ticker directly below. Check ~390px width.

- [ ] **Step 3: Commit**

```bash
git add assets/looks/floodlight.css
git commit -m "feat: Floodlight full-bleed home hero styling"
```

---

### Task 4: Members' House `.ch-home-hero` styling

**Files:**
- Modify: `assets/looks/members-house.css` (add a `.ch-home-hero` block near the existing `.ch-hero` rules, ~line 112)

**Interfaces:**
- Consumes: markup from Task 1; Members' House tokens and its `ch-rise` keyframe. Mirrors Task 2 with Members' House's refined character (underline highlight, softer scrim).
- Produces: no downstream interface.

- [ ] **Step 1: Add the Members' House full-bleed hero block**

Append after the existing hero rules (around line 112 in `assets/looks/members-house.css`). Full block to paste:

```css
/* Full-bleed Home hero (home_hero) — Members' House: refined, restrained. */
.ch-home-hero{position:relative;isolation:isolate;overflow:hidden;min-height:clamp(540px,82vh,800px);display:flex;align-items:flex-end;padding-top:var(--space-16)}
.ch-home-hero__bg{position:absolute;inset:0;z-index:-2;background:var(--color-ink)}
.ch-home-hero__bg--empty{background:radial-gradient(88% 72% at 86% -10%,var(--color-accent-wash),transparent 60%),var(--color-ink)}
.ch-home-hero__img{width:100%;height:100%;object-fit:cover;display:block}
.ch-home-hero__scrim{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,color-mix(in oklab,var(--color-ink) 22%,transparent),color-mix(in oklab,var(--color-ink) 74%,transparent))}
.ch-home-hero__in{width:100%;padding-top:var(--space-12);padding-bottom:var(--space-10);color:var(--color-bg)}
.ch-home-hero .ch-eyebrow{color:color-mix(in oklab,var(--color-bg) 82%,transparent)}
.ch-home-hero__title{font-family:var(--font-display);font-weight:500;font-size:clamp(38px,8vw,104px);letter-spacing:-.02em;line-height:1;color:var(--color-bg);overflow-wrap:break-word;hyphens:auto}
.ch-home-hero__hl{color:var(--color-bg);box-shadow:inset 0 -.09em 0 var(--color-accent);display:inline;max-width:100%}
.ch-home-hero__lede{max-width:46ch;font-size:19px;margin-top:24px;color:color-mix(in oklab,var(--color-bg) 86%,transparent)}
.ch-home-hero__cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:26px}
.ch-home-hero .ch-btn--ghost{background:transparent;color:var(--color-bg);border:1px solid color-mix(in oklab,var(--color-bg) 50%,transparent)}
.ch-home-hero .ch-btn--ghost:hover{background:var(--color-bg);color:var(--color-ink);border-color:var(--color-bg)}
.ch-home-hero__foot{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(160px,100%),1fr));gap:12px;margin-top:var(--space-10)}
.ch-home-hero__tile{display:flex;align-items:center;gap:12px;background:color-mix(in oklab,var(--color-paper) 92%,transparent);color:var(--color-ink);border:1px solid color-mix(in oklab,var(--color-ink) 8%,transparent);border-radius:var(--radius-md);padding:16px 18px;font-weight:700;font-size:15px;backdrop-filter:blur(6px);transition:background .18s ease,color .18s ease,border-color .18s ease}
.ch-home-hero__tile:hover{background:var(--color-ink);color:var(--color-bg);border-color:var(--color-ink)}
.ch-home-hero__tile-ico{display:flex;flex:none;color:var(--color-accent-deep)}
.ch-home-hero__tile:hover .ch-home-hero__tile-ico{color:var(--color-accent)}
.ch-home-hero__tile-label{flex:1}
.ch-home-hero__tile-arrow{flex:none;color:var(--color-accent-deep)}
.ch-home-hero__tile:hover .ch-home-hero__tile-arrow{color:var(--color-accent)}
@media(prefers-reduced-motion:no-preference){.ch-home-hero .ch-eyebrow,.ch-home-hero__title,.ch-home-hero__lede,.ch-home-hero__cta,.ch-home-hero__foot{animation:ch-rise .7s cubic-bezier(.2,.7,.2,1) both}.ch-home-hero__title{animation-delay:.07s}.ch-home-hero__lede{animation-delay:.13s}.ch-home-hero__cta{animation-delay:.19s}.ch-home-hero__foot{animation-delay:.25s}}
@media(max-width:640px){.ch-home-hero{min-height:auto}.ch-home-hero__foot{grid-template-columns:1fr}}
```

- [ ] **Step 2: Verify in the preview (Members' House)**

Navigate to `http://127.0.0.1:8124/preview/?look=members-house&page=home`, screenshot, and confirm the refined treatment: underline-accent highlight, soft paper tiles, legible CTAs, ticker directly below. Check ~390px width.

- [ ] **Step 3: Commit**

```bash
git add assets/looks/members-house.css
git commit -m "feat: Members' House full-bleed home hero styling"
```

---

### Task 5: Version bump, changelog, final lint

**Files:**
- Modify: `blueworx-labs-clubhouse.php:6` (plugin header `Version:`) and `:24` (`BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `package.json:3` (`"version"`)
- Modify: `CHANGELOG.md` (new entry at the top)

**Interfaces:** none.

- [ ] **Step 1: Bump the version in all three places to `0.23.0`**

In `blueworx-labs-clubhouse.php` change ` * Version:           0.22.1` → ` * Version:           0.23.0` and `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.22.1' );` → `'0.23.0'`. In `package.json` change `"version": "0.22.1"` → `"version": "0.23.0"`.

- [ ] **Step 2: Add the changelog entry**

Add at the top of `CHANGELOG.md` (match the file's existing entry format):

```markdown
## 0.23.0

### Added
- Full-bleed Home hero: background image (with a graceful toned fallback when no
  photo is set) and overlaid content, with the quick-links folded in as an icon
  card row and the news ticker directly beneath. Applied across all three Base
  Looks (Court Side, Floodlight, Members' House). Copy unchanged; the shared
  hero used by other pages is untouched.
```

- [ ] **Step 3: Run the full test suite**

Run: `npx playwright test`
Expected: PASS — `tests/home-hero.spec.js` (4) and `tests/smoke.spec.js` all green.

- [ ] **Step 4: Run PHPCS once (final check)**

Run: `composer lint`
Expected: no errors on `includes/render/class-sections.php` or `class-page-renderer.php`. Present any findings to the user for a decision — do not auto-fix in a loop.

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.23.0 for full-bleed home hero"
```

---

## Self-Review

**Spec coverage:**
- New Home-only `home_hero()` renderer → Task 1 (Steps 3–4). ✓
- Quick-links move into hero foot with icons; Home drops `quick_tiles` → Task 1 (Steps 3–5) + test Step 1 (`.ch-tiles-sec` count 0). ✓
- `.ch-home-hero` CSS in all three looks → Tasks 2, 3, 4. ✓
- Graceful no-image fallback → `ch-home-hero__bg--empty` in Task 1 markup + `--empty` rule in each look CSS. ✓
- Ticker directly beneath → test Step 1 (`nextElementSibling` contains `ch-ticker`) + wiring Step 5. ✓
- Accessibility (h1 kept, decorative icons `aria-hidden`, scrim for contrast, focusable link tiles) → Task 1 markup + look scrims. ✓
- Version bump + changelog + Playwright test → Task 5 + Task 1 test file. ✓
- Shared `hero()` / other pages untouched → test Step 1 asserts `.ch-hero` count 0 on Home; no edits to hero() or other page methods. ✓

**Placeholder scan:** No TBD/TODO/"handle edge cases"; every code step shows full code; the Floodlight task repeats the complete block rather than referencing Task 2. ✓

**Type consistency:** `home_hero()` `$data` keys and the `tiles` item shape match between the renderer (Task 1 Step 4) and the caller (Task 1 Step 5). Class names used in the test (Step 1), markup (Step 4), and all three CSS blocks agree: `ch-home-hero`, `__bg`(`--empty`), `__img`, `__scrim`, `__in`, `__title`, `__hl`, `__lede`, `__cta`, `__foot`, `__tile`(`-ico`/`-label`/`-arrow`). ✓
