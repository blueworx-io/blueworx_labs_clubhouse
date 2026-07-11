# Floodlight Base Look Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Floodlight — the third Base Look — as a bold, dark, night-match re-skin (Bricolage Grotesque + Hanken Grotesk, warm-ink canvas, bold-modern mid radii, accent spent as *glow*) that reuses the existing engine and skin-agnostic sections unchanged.

**Architecture:** A Base Look pack is a PHP class implementing `Blueworx_Clubhouse_Base_Look` (supplies shell tokens, fonts, and a stylesheet path — never content, never accent tokens) plus a stylesheet that consumes the engine's CSS custom properties. Floodlight adds `class-floodlight.php` + `assets/looks/floodlight.css`, both parallel to the Court Side pair. The preview registers Court Side + Floodlight and switches the active one by `?look=`. No section renderer, page renderer, or engine code changes — that is the whole point of the re-skin. On the dark shell all accent-coloured **text/marks/borders route through `--color-accent-deep`** (engine-guaranteed AA against `--color-bg`); raw `--color-accent` is used only for glows, washes, and large fills. The engine's `accent-ink` limitation on dark shells is *sidestepped*, never triggered.

**Tech Stack:** PHP 8.2 (strict types), PHPUnit 11, plain CSS with custom properties, Google-CDN fonts (Bricolage Grotesque + Hanken Grotesk).

## Global Constraints

- Class prefix `Blueworx_Clubhouse_`; files `declare(strict_types=1)` and guard with `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- **Skin-agnostic rule:** never modify section renderers (`includes/render/*`) or the engine (`includes/theme/*`) or page composition. All appearance comes from the look's tokens + stylesheet. If a section seems to need a change, that is a section bug, not this plan's work — stop and flag it.
- **Accent contract:** the stylesheet references the accent *only* through `var(--color-accent)`, `var(--color-accent-ink)`, `var(--color-accent-deep)`, `var(--color-accent-wash)`. No club-colour hex literal appears in the stylesheet.
- **Dark-shell accent rule:** any accent that must *read* (text, marks, borders, dots, numerals) uses `var(--color-accent-deep)`; raw `var(--color-accent)` is used only for glows (`box-shadow`), washes, and large decorative fills. Never use raw `var(--color-accent)` as text. Never rely on `var(--color-accent-ink)` for legibility (the look's glow idiom avoids solid accent fills with text).
- **Font-name trap:** fonts come only from `var(--font-display)` / `var(--font-body)`. The stylesheet must NOT contain the substrings **`Bricolage`** or **`Hanken`** anywhere — *including comments*. Use "display token" / "body token" phrasing. (The class file names the families; the CSS never does.)
- `tokens()` must NOT contain any `--color-accent*` key (accent is engine-derived per club).
- Tests run with `composer test` (PHPUnit, no WordPress runtime); all existing tests must stay green.
- Version bump is to **`0.10.0`** (Members' House holds `0.9.0` on its sibling branch; Floodlight takes the next minor so the two don't collide when both merge). Keep `blueworx-labs-clubhouse.php` (Version header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json` in sync, with a matching `CHANGELOG.md` entry.
- Indentation is tabs in PHP (match the existing files exactly).

---

### Task 1: Floodlight Base Look class

**Files:**
- Create: `includes/looks/class-floodlight.php`
- Modify: `includes/bootstrap.php` (add one `require_once` after the Court Side require)
- Test: `tests/php/FloodlightTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look` (interface), `Blueworx_Clubhouse_Base_Look_Registry`, `Blueworx_Clubhouse_Branding`, `Blueworx_Clubhouse_Theme_Css`, `Blueworx_Clubhouse_Fake_Storage`.
- Produces: `final class Blueworx_Clubhouse_Floodlight implements Blueworx_Clubhouse_Base_Look` with `slug()='floodlight'`, `name()='Floodlight'`, `description()`, `tokens()` (the fixed warm-ink dark shell), `fonts()` (Bricolage Grotesque + Hanken Grotesk), `stylesheet()='assets/looks/floodlight.css'`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/FloodlightTest.php`:

```php
<?php
// tests/php/FloodlightTest.php

use PHPUnit\Framework\TestCase;

final class FloodlightTest extends TestCase {

	public function test_identity_and_stylesheet(): void {
		$look = new Blueworx_Clubhouse_Floodlight();
		$this->assertSame( 'floodlight', $look->slug() );
		$this->assertSame( 'Floodlight', $look->name() );
		$this->assertSame( 'assets/looks/floodlight.css', $look->stylesheet() );
		$this->assertNotSame( '', $look->description() );
	}

	public function test_tokens_carry_the_fixed_dark_shell(): void {
		$t = ( new Blueworx_Clubhouse_Floodlight() )->tokens();
		$this->assertSame( '#14110b', $t['--color-bg'] );
		$this->assertSame( '#f3ede0', $t['--color-ink'] );
		$this->assertSame( '#1e1913', $t['--color-paper'] );
		$this->assertSame( '#a99f8c', $t['--color-ink-soft'] );
		$this->assertSame( '#302a20', $t['--color-line'] );
		$this->assertStringContainsString( 'Bricolage Grotesque', $t['--font-display'] );
		$this->assertStringContainsString( 'Hanken Grotesk', $t['--font-body'] );
		// Bold-modern radii — enough body to carry a glow halo.
		$this->assertSame( '16px', $t['--radius-xl'] );
		$this->assertSame( '11px', $t['--radius-lg'] );
		$this->assertSame( '7px', $t['--radius-md'] );
		// Accent is engine-derived, never baked into the look.
		$this->assertArrayNotHasKey( '--color-accent', $t );
	}

	public function test_fonts_are_bricolage_grotesque_and_hanken_grotesk(): void {
		$families = array_column( ( new Blueworx_Clubhouse_Floodlight() )->fonts(), 'family' );
		$this->assertSame( array( 'Bricolage Grotesque', 'Hanken Grotesk' ), $families );
		foreach ( ( new Blueworx_Clubhouse_Floodlight() )->fonts() as $font ) {
			$this->assertArrayHasKey( 'weights', $font );
			$this->assertArrayHasKey( 'display', $font );
			$this->assertNotEmpty( $font['weights'] );
		}
	}

	public function test_registers_and_composes_with_derived_accent(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$registry->register( new Blueworx_Clubhouse_Floodlight() );
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_accent( '#f7a70a' );

		$vars = Blueworx_Clubhouse_Theme_Css::compose( $registry->active(), $branding );
		$this->assertSame( '#14110b', $vars['--color-bg'] );
		$this->assertSame( '#f7a70a', $vars['--color-accent'] );
		$this->assertArrayHasKey( '--color-accent-ink', $vars );
		$this->assertArrayHasKey( '--color-accent-deep', $vars );

		// The load-bearing dark-shell guarantee: accent-as-text (accent-deep) clears
		// WCAG AA against the dark canvas, so every accent mark/label/numeral is legible.
		$this->assertGreaterThanOrEqual(
			4.5,
			Blueworx_Clubhouse_Color_Engine::contrast_ratio( $vars['--color-accent-deep'], $vars['--color-bg'] )
		);
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter FloodlightTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Floodlight" not found`.

- [ ] **Step 3: Write the class**

Create `includes/looks/class-floodlight.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Floodlight — the third Base Look. Bold and dark: warm-ink near-black canvas,
 * warm bone text, crisp mid radii, a contemporary grotesque display, and an accent
 * spent as glow (outline, deep-text, soft shadow, wash) rather than solid fills.
 * The accent's tokens are engine-derived, not defined here. Supplies presentation
 * only — never adds or reads content.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Floodlight implements Blueworx_Clubhouse_Base_Look {

	public function slug(): string {
		return 'floodlight';
	}

	public function name(): string {
		return 'Floodlight';
	}

	public function description(): string {
		return 'Bold and dark — warm-ink canvas, night-match energy, grotesque display, accent glows.';
	}

	/** @return array<string,string> */
	public function tokens(): array {
		return array(
			'--color-bg'       => '#14110b',
			'--color-paper'    => '#1e1913',
			'--color-ink'      => '#f3ede0',
			'--color-ink-soft' => '#a99f8c',
			'--color-line'     => '#302a20',
			'--radius-xl'      => '16px',
			'--radius-lg'      => '11px',
			'--radius-md'      => '7px',
			'--font-display'   => "'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif",
			'--font-body'      => "'Hanken Grotesk', ui-sans-serif, system-ui, sans-serif",
		);
	}

	/** @return array<int,array{family:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Bricolage Grotesque', 'weights' => array( 500, 600, 700, 800 ), 'display' => 'swap' ),
			array( 'family' => 'Hanken Grotesk', 'weights' => array( 400, 500, 600, 700 ), 'display' => 'swap' ),
		);
	}

	public function stylesheet(): string {
		return 'assets/looks/floodlight.css';
	}
}
```

- [ ] **Step 4: Register the class for loading**

In `includes/bootstrap.php`, find the `// Looks` block:

```php
// Looks
require_once __DIR__ . '/looks/class-court-side.php';
```

Add a line directly beneath the Court Side require:

```php
require_once __DIR__ . '/looks/class-floodlight.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter FloodlightTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/looks/class-floodlight.php includes/bootstrap.php tests/php/FloodlightTest.php
git commit -m "feat: add Floodlight Base Look pack (tokens, Bricolage Grotesque + body)"
```

---

### Task 2: Floodlight stylesheet

**Files:**
- Create: `assets/looks/floodlight.css`
- Test: `tests/php/FloodlightStylesheetTest.php`

**Interfaces:**
- Consumes: the engine custom properties emitted by `Theme_Css` (`--color-bg/-paper/-ink/-ink-soft/-line`, `--radius-xl/-lg/-md`, `--font-display/-body`, `--color-accent/-ink/-deep/-wash`) and the `ch-*` markup produced by `Blueworx_Clubhouse_Sections` / `Page_Renderer` (unchanged).
- Produces: `assets/looks/floodlight.css` — a complete look stylesheet in the dark accent-glow idiom, covering every `ch-*` hook Court Side covers.

- [ ] **Step 1: Write the failing test**

Create `tests/php/FloodlightStylesheetTest.php`:

```php
<?php
// tests/php/FloodlightStylesheetTest.php

use PHPUnit\Framework\TestCase;

final class FloodlightStylesheetTest extends TestCase {

	private function css(): string {
		$path = dirname( __DIR__, 2 ) . '/assets/looks/floodlight.css';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	public function test_styles_the_shell_sections(): void {
		$css = $this->css();
		foreach ( array( '.ch-nav', '.ch-hero', '.ch-stats', '.ch-footer', '.ch-btn', '.ch-faq', '.ch-tiers', '.ch-auth' ) as $sel ) {
			$this->assertStringContainsString( $sel, $css );
		}
	}

	public function test_accent_is_referenced_via_custom_property_not_literals(): void {
		$css = $this->css();
		$this->assertStringContainsString( 'var(--color-accent-deep)', $css );
		// The demo accent must never be baked in — that would break re-theming.
		$this->assertStringNotContainsString( '#f7a70a', $css );
		// Nor the other looks' demo accents.
		$this->assertStringNotContainsString( '#c6f24e', $css );
		$this->assertStringNotContainsString( '#7a2f3a', $css );
	}

	public function test_uses_the_look_fonts_only_via_tokens(): void {
		// Fonts come from --font-display/--font-body tokens; the stylesheet must not
		// name a family (that lives in the look class, not the CSS) — including in comments.
		$css = $this->css();
		$this->assertStringNotContainsString( 'Bricolage', $css );
		$this->assertStringNotContainsString( 'Hanken', $css );
		$this->assertStringContainsString( 'var(--font-display)', $css );
		$this->assertStringContainsString( 'var(--font-body)', $css );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter FloodlightStylesheetTest`
Expected: FAIL — `Failed asserting that file "…/assets/looks/floodlight.css" exists`.

- [ ] **Step 3: Write the stylesheet**

Create `assets/looks/floodlight.css` with exactly this content:

```css
/* Floodlight — look stylesheet. Bold night-match dark: warm-ink canvas, warm bone
   text, crisp mid radii, a grotesque display token, accent spent as glow. Consumes
   engine custom properties; the accent is always var(--color-accent*) so any club
   re-themes by swapping one colour. On this DARK shell, anything that must read (text,
   marks, borders, dots, numerals) uses var(--color-accent-deep) — engine-guaranteed AA
   against --color-bg — while raw var(--color-accent) is used only for glows, washes and
   large fills. The only deliberately-bright surfaces are the ink/bone punch button and
   the skip link. Parallel in coverage to court-side.css, different personality. */
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--color-bg);color:var(--color-ink);font-family:var(--font-body);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
.ch-wrap{max-width:1200px;margin-inline:auto;padding-inline:28px}
@media(max-width:520px){.ch-wrap{padding-inline:20px}}

/* Spacing scale — same rhythm engine as the other looks. */
:root{--space-2:8px;--space-3:12px;--space-4:16px;--space-6:24px;--space-8:32px;--space-10:40px;--space-12:48px;--space-16:64px;--space-20:80px;--flow-lg:88px;--flow-sm:24px}
@media(max-width:640px){:root{--flow-lg:52px;--flow-sm:20px}}
.ch-main > *{margin-block:0}
.ch-main > * + *{margin-top:var(--flow-lg)}
.ch-tiles-sec,.ch-ticker,.ch-stats,.ch-tiers-sec{margin-top:var(--flow-sm)}

/* Scroll-reveal / skip link / focus — identical progressive-enhancement mechanics to
   the other looks (no-JS and reduced-motion get fully-visible content). */
.ch-reveal{opacity:0;transform:translateY(24px);transition:opacity .7s cubic-bezier(.2,.7,.2,1),transform .7s cubic-bezier(.2,.7,.2,1)}
.ch-reveal.is-in{opacity:1;transform:none}
@media(prefers-reduced-motion:reduce){.ch-reveal{opacity:1;transform:none;transition:none}}
.ch-skip{position:fixed;left:0;top:0;z-index:100;background:var(--color-ink);color:var(--color-bg);padding:12px 20px;border-radius:0 0 var(--radius-md) 0;font-weight:700;transform:translateY(-120%);transition:transform .15s ease}
.ch-skip:focus{transform:translateY(0)}
.ch-main:focus{outline:none}
:where(a,button,summary,input,select,textarea,[tabindex]):focus-visible{outline:3px solid var(--color-accent-deep);outline-offset:2px;border-radius:4px}

/* Eyebrow — a letter-spaced label preceded by a short glowing accent rule. */
.ch-eyebrow{display:inline-flex;align-items:center;gap:12px;font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--color-ink-soft)}
.ch-eyebrow::before{content:"";width:26px;height:2px;background:var(--color-accent-deep);flex:none;box-shadow:0 0 10px color-mix(in oklab,var(--color-accent) 60%,transparent)}

/* Buttons — accent is a glowing outline (deep text + accent border + soft shadow); the
   ink/bone button is the one solid punch; ghost is a hairline. */
.ch-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5em;font-family:var(--font-body);font-weight:700;font-size:15px;padding:15px 28px;border-radius:var(--radius-md);white-space:nowrap;transition:transform .16s ease,background-color .18s ease,color .18s ease,box-shadow .2s ease,border-color .18s ease}
.ch-btn:hover{transform:translateY(-2px)}
.ch-btn:active{transform:translateY(0) scale(.98)}
.ch-btn--accent{background:color-mix(in oklab,var(--color-accent) 12%,transparent);color:var(--color-accent-deep);border:1.5px solid var(--color-accent-deep);box-shadow:0 0 22px -8px color-mix(in oklab,var(--color-accent) 75%,transparent)}
.ch-btn--accent:hover{background:color-mix(in oklab,var(--color-accent) 20%,transparent);box-shadow:0 0 32px -6px color-mix(in oklab,var(--color-accent) 90%,transparent)}
.ch-btn--ink{background:var(--color-ink);color:var(--color-bg)}
.ch-btn--ink:hover{background:color-mix(in oklab,var(--color-ink) 88%,var(--color-accent-deep));box-shadow:0 12px 30px -14px rgba(0,0,0,.6)}
.ch-btn--ghost{border:1.5px solid var(--color-line);color:var(--color-ink)}
.ch-btn--ghost:hover{border-color:var(--color-ink);background:color-mix(in oklab,var(--color-ink) 8%,transparent)}
@media(prefers-reduced-motion:reduce){.ch-btn:hover,.ch-btn:active{transform:none}}

/* Nav — dark translucent, hairline foot; glowing brand mark. */
.ch-nav{position:sticky;top:0;z-index:40;background:color-mix(in oklab,var(--color-bg) 82%,transparent);backdrop-filter:blur(12px);border-bottom:1px solid var(--color-line)}
.ch-nav__in{display:flex;align-items:center;justify-content:space-between;height:80px;gap:24px}
.ch-brand{font-family:var(--font-display);font-weight:700;font-size:25px;letter-spacing:-.02em;display:flex;align-items:center;gap:10px;flex:none}
.ch-brand__mark{width:36px;height:36px;border-radius:var(--radius-md);background:var(--color-paper);color:var(--color-accent-deep);border:1px solid var(--color-line);display:grid;place-items:center;font-family:var(--font-display);font-size:18px;font-weight:700;box-shadow:0 0 16px -4px color-mix(in oklab,var(--color-accent) 60%,transparent)}
.ch-nav__links{display:flex;gap:28px;font-size:15px;font-weight:500;color:var(--color-ink-soft)}
.ch-nav__link{display:inline-block;padding:8px 0}
.ch-nav__link:hover{color:var(--color-ink)}
@media(max-width:900px){.ch-nav__links{display:none}}

/* Mobile/tablet menu — no-JS <details> disclosure, same structure, restyled dark. */
.ch-nav__disc{display:none;position:relative}
.ch-nav__burger{list-style:none;width:44px;height:44px;display:grid;place-items:center;border:1.5px solid var(--color-line);border-radius:var(--radius-md);cursor:pointer}
.ch-nav__burger::-webkit-details-marker{display:none}
.ch-nav__burger-bars,.ch-nav__burger-bars::before,.ch-nav__burger-bars::after{content:"";display:block;width:20px;height:2px;background:var(--color-ink);border-radius:2px}
.ch-nav__burger-bars{position:relative}
.ch-nav__burger-bars::before{position:absolute;top:-6px}
.ch-nav__burger-bars::after{position:absolute;top:6px}
.ch-nav__disc[open] .ch-nav__burger{background:var(--color-paper);border-color:var(--color-accent-deep)}
.ch-nav__drawer{display:none}
.ch-nav__disc[open] .ch-nav__drawer{position:absolute;right:0;top:calc(100% + 12px);width:min(280px,80vw);background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);box-shadow:0 18px 40px -20px rgba(0,0,0,.6);padding:10px;display:flex;flex-direction:column}
.ch-nav__drawer .ch-nav__link{padding:13px 14px;border-radius:var(--radius-md);font-size:16px}
.ch-nav__drawer .ch-nav__link:hover,.ch-nav__drawer .ch-nav__link--active{background:var(--color-accent-wash)}
.ch-nav__drawer-join{width:100%;margin-bottom:8px}
.ch-nav__drawer-login{margin-top:6px;border-top:1px solid var(--color-line);padding-top:10px}
.ch-nav__drawer-join{display:none}
@media(max-width:900px){.ch-nav__disc{display:block}.ch-nav__in{gap:12px}.ch-nav__cta{gap:8px}.ch-brand{min-width:0}}
@media(max-width:520px){.ch-nav__cta .ch-btn--ink{display:none}.ch-nav__disc[open] .ch-nav__drawer-join{display:inline-flex}.ch-brand{font-size:20px}.ch-brand__mark{width:30px;height:30px;font-size:15px}}

.ch-banner{background:var(--color-paper);color:var(--color-ink);border-bottom:1px solid var(--color-line)}
.ch-banner__in{display:flex;justify-content:center;height:40px;align-items:center}
.ch-banner__link{font-size:13px;font-weight:600;text-align:center;color:var(--color-ink-soft)}
.ch-banner__link:hover{color:var(--color-accent-deep)}
.ch-nav__cta{display:flex;gap:10px;align-items:center}
/* Active link — full-contrast ink with a glowing accent underline (signal beyond colour). */
.ch-nav__links .ch-nav__link--active{color:var(--color-ink);font-weight:700;text-decoration:underline;text-decoration-color:var(--color-accent-deep);text-decoration-thickness:2px;text-underline-offset:6px}
@media(max-width:900px){.ch-nav__cta .ch-btn--ghost{display:none}}

/* Media — grayscale base + a low-opacity accent overlay = a restrained duotone that
   re-themes with the accent. Empty slots keep their own icon (a different selector,
   so no collision) over a dark gradient with an accent bloom. */
.ch-media{position:relative;overflow:hidden;background:var(--color-line);border-radius:var(--radius-lg)}
.ch-media__img{width:100%;height:100%;object-fit:cover;display:block;filter:grayscale(1) contrast(1.04)}
.ch-media:not(.ch-media--empty)::after{content:"";position:absolute;inset:0;background:linear-gradient(135deg,color-mix(in oklab,var(--color-accent) 60%,transparent),transparent 72%);mix-blend-mode:screen;opacity:.32;pointer-events:none}
.ch-media--empty{background:linear-gradient(135deg,color-mix(in oklab,var(--color-accent) 16%,var(--color-bg)),var(--color-paper) 55%,color-mix(in oklab,var(--color-accent) 9%,var(--color-bg)))}
.ch-media--empty::after{content:"";position:absolute;inset:0;background:center/54px no-repeat url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='54'%20height='54'%20viewBox='0%200%2024%2024'%20fill='none'%20stroke='%237a7263'%20stroke-width='1.3'%20stroke-linecap='round'%20stroke-linejoin='round'%3E%3Crect%20x='3'%20y='3'%20width='18'%20height='18'%20rx='2.5'/%3E%3Ccircle%20cx='8.5'%20cy='8.5'%20r='1.6'/%3E%3Cpath%20d='M21%2015l-4.5-4.5L5.5%2021'/%3E%3C/svg%3E");opacity:.7}
.ch-card:nth-child(2n) .ch-media--empty,.ch-news__card:nth-child(3n) .ch-media--empty{background:linear-gradient(150deg,color-mix(in oklab,var(--color-accent) 14%,var(--color-bg)),var(--color-paper) 60%,var(--color-bg))}
.ch-card:nth-child(3n) .ch-media--empty{background:linear-gradient(115deg,var(--color-paper),color-mix(in oklab,var(--color-accent) 12%,var(--color-bg)) 70%)}

/* Hero — dark canvas with an ambient accent glow; the highlighted word glows. */
.ch-hero{padding:var(--space-16) 0 0;background:radial-gradient(82% 62% at 88% -12%,color-mix(in oklab,var(--color-accent) 16%,transparent),transparent 60%)}
.ch-hero__title{font-family:var(--font-display);font-weight:700;font-size:clamp(40px,8.5vw,116px);letter-spacing:-.02em;line-height:.98;overflow-wrap:break-word;hyphens:auto}
.ch-hero__hl{color:var(--color-accent-deep);text-shadow:0 0 26px color-mix(in oklab,var(--color-accent) 55%,transparent);display:inline;max-width:100%}
.ch-hero__sub{display:flex;justify-content:space-between;align-items:end;gap:30px;margin-top:26px;flex-wrap:wrap}
.ch-hero__lede{max-width:40ch;font-size:19px;color:var(--color-ink-soft)}
.ch-hero__cta{display:flex;gap:12px;flex-wrap:wrap}
.ch-hero__media{position:relative;margin-top:var(--space-8);aspect-ratio:16/7;max-height:520px}
.ch-hero__media .ch-media{width:100%;height:100%;border-radius:var(--radius-xl)}
.ch-hero__pill{position:absolute;left:20px;bottom:20px;z-index:2;background:color-mix(in oklab,var(--color-paper) 82%,transparent);backdrop-filter:blur(8px);border:1px solid var(--color-line);border-radius:var(--radius-md);padding:11px 18px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:9px}
.ch-hero__pill-dot{width:9px;height:9px;border-radius:50%;background:var(--color-accent-deep);box-shadow:0 0 8px var(--color-accent-deep)}
@media(max-width:640px){.ch-hero__media{aspect-ratio:4/3}}
@keyframes ch-rise{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.ch-hero .ch-eyebrow,.ch-hero__title,.ch-hero__sub,.ch-hero__media{animation:ch-rise .7s cubic-bezier(.2,.7,.2,1) both}
.ch-hero__title{animation-delay:.07s}
.ch-hero__sub{animation-delay:.15s}
.ch-hero__media{animation-delay:.23s}
@media(prefers-reduced-motion:reduce){.ch-hero .ch-eyebrow,.ch-hero__title,.ch-hero__sub,.ch-hero__media{animation:none}}

/* Quick tiles — dark paper, hairline; hover lights the border with a glow. */
.ch-tiles-sec{padding:0}
.ch-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
.ch-tiles__tile{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);padding:18px 20px;font-weight:600;font-size:15px;transition:border-color .18s ease,box-shadow .2s ease}
.ch-tiles__tile:hover{border-color:color-mix(in oklab,var(--color-accent) 55%,var(--color-line));box-shadow:0 0 24px -10px color-mix(in oklab,var(--color-accent) 70%,transparent)}
.ch-tiles__arrow{color:var(--color-accent-deep)}

/* Ticker — dark panel; accent as the deep label + glowing dots. No-JS pause preserved. */
.ch-ticker{display:flex;align-items:center;gap:0;background:var(--color-paper);color:var(--color-ink);overflow:hidden;border:1px solid var(--color-line);border-radius:var(--radius-md)}
.ch-ticker__label{flex:none;background:color-mix(in oklab,var(--color-accent) 12%,transparent);color:var(--color-accent-deep);font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:12px 16px;border-right:1px solid var(--color-line)}
.ch-ticker__viewport{position:relative;display:flex;overflow:hidden;white-space:nowrap;flex:1}
.ch-ticker__track{display:inline-flex;align-items:center;gap:34px;padding:0 17px;animation:ch-marquee 28s linear infinite}
.ch-ticker:hover .ch-ticker__track{animation-play-state:paused}
.ch-ticker__item{display:inline-flex;align-items:center;gap:11px;font-size:14px;font-weight:500}
.ch-ticker__dot{width:6px;height:6px;border-radius:50%;background:var(--color-accent-deep);box-shadow:0 0 6px var(--color-accent-deep)}
.ch-ticker__pause-cb{position:absolute;opacity:0;width:1px;height:1px}
.ch-ticker__pause{flex:none;width:44px;height:44px;display:grid;place-items:center;cursor:pointer;color:var(--color-ink)}
.ch-ticker__pause .ch-ticker__ico-play{display:none}
.ch-ticker__pause-cb:checked ~ .ch-ticker__viewport .ch-ticker__track{animation-play-state:paused}
.ch-ticker__pause-cb:checked ~ .ch-ticker__pause .ch-ticker__ico-pause{display:none}
.ch-ticker__pause-cb:checked ~ .ch-ticker__pause .ch-ticker__ico-play{display:block}
.ch-ticker__pause-cb:focus-visible ~ .ch-ticker__pause{outline:3px solid var(--color-accent-deep);outline-offset:-3px}
@keyframes ch-marquee{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media(prefers-reduced-motion:reduce){.ch-ticker__track{animation:none}.ch-ticker__viewport{overflow-x:auto}.ch-ticker__track[aria-hidden="true"]{display:none}.ch-ticker__pause,.ch-ticker__pause-cb{display:none}}

/* Stats — dark paper cards; the featured one gets an accent top-glow and deep value. */
.ch-stats{padding:0}
.ch-stats__in{display:flex;gap:14px;flex-wrap:wrap}
.ch-stats__item{flex:1 1 200px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:26px 28px;transition:border-color .2s ease,box-shadow .2s ease}
.ch-stats__item:hover{border-color:color-mix(in oklab,var(--color-accent) 50%,var(--color-line));box-shadow:0 0 26px -12px color-mix(in oklab,var(--color-accent) 65%,transparent)}
.ch-stats__item--feature{border-color:transparent;border-top:3px solid var(--color-accent-deep);box-shadow:0 0 44px -18px color-mix(in oklab,var(--color-accent) 70%,transparent)}
.ch-stats__item--feature:hover{border-top-color:var(--color-accent-deep)}
.ch-stats__item--feature .ch-stats__value{color:var(--color-accent-deep)}
.ch-stats__value{font-family:var(--font-display);font-weight:700;font-size:44px;display:block;letter-spacing:-.02em}
.ch-stats__label{font-size:13px;font-weight:600;color:var(--color-ink-soft)}

/* Footer — hairline top, muted links, glowing accent on hover. */
.ch-footer{margin-top:var(--flow-lg);padding:66px 0 40px;border-top:1px solid var(--color-line)}
.ch-footer__grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1.4fr;gap:40px}
.ch-footer__tagline{color:var(--color-ink-soft);max-width:30ch;margin-top:12px;font-size:15px}
.ch-footer__socials{display:flex;gap:8px;margin-top:16px}
.ch-footer__social{width:44px;height:44px;border-radius:50%;border:1px solid var(--color-line);display:grid;place-items:center;font-weight:700;color:var(--color-ink)}
.ch-footer__social:hover{border-color:var(--color-accent-deep);color:var(--color-accent-deep);box-shadow:0 0 18px -6px color-mix(in oklab,var(--color-accent) 70%,transparent)}
.ch-footer__h{font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--color-ink-soft);margin-bottom:14px;font-weight:600}
.ch-footer__link{display:block;font-size:15px;color:var(--color-ink-soft);padding-block:8px}
.ch-footer__link:hover{color:var(--color-ink)}
.ch-footer__lede{font-size:14px;color:var(--color-ink-soft);margin-bottom:14px;max-width:32ch}
.ch-footer__form{display:flex;gap:8px;flex-wrap:wrap}
.ch-footer__input{flex:1 1 160px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:999px;padding:13px 18px;font-family:var(--font-body);font-size:15px;color:var(--color-ink)}
.ch-footer__legal{display:flex;gap:20px;flex-wrap:wrap;margin-top:38px;font-size:13px}
.ch-footer__legal-link{color:var(--color-ink-soft);display:inline-block;padding-block:8px}
.ch-footer__legal-link:hover{color:var(--color-ink)}
@media(max-width:820px){.ch-footer__grid{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.ch-footer__grid{grid-template-columns:1fr}}

/* Section heads — grotesque display titles at bold weight. */
.ch-sec{padding:0}
.ch-sec__head{display:flex;justify-content:space-between;align-items:end;gap:24px;margin-bottom:34px;flex-wrap:wrap}
.ch-sec__title{font-family:var(--font-display);font-weight:700;font-size:clamp(32px,5.5vw,64px);letter-spacing:-.02em;max-width:16ch;margin-top:14px;line-height:.98;overflow-wrap:break-word}
.ch-sec__title + *{margin-top:34px}
.ch-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.ch-card{position:relative;border-radius:var(--radius-lg);overflow:hidden;aspect-ratio:3/4;background:var(--color-line);color:var(--color-ink);display:flex;flex-direction:column;justify-content:end;transition:transform .2s ease,box-shadow .2s ease}
.ch-card:hover{transform:translateY(-5px);box-shadow:0 0 30px -12px color-mix(in oklab,var(--color-accent) 65%,transparent)}
.ch-card__media{position:absolute;inset:0;border-radius:0}
.ch-card__scrim{position:absolute;inset:0;background:linear-gradient(180deg,transparent 28%,color-mix(in oklab,var(--color-bg) 94%,transparent))}
.ch-card__tag{position:absolute;top:14px;left:14px;z-index:2;background:color-mix(in oklab,var(--color-bg) 68%,transparent);backdrop-filter:blur(6px);color:var(--color-accent-deep);font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:6px 11px;border-radius:var(--radius-md);border:1px solid color-mix(in oklab,var(--color-accent) 40%,transparent)}
.ch-card__body{position:relative;z-index:2;padding:20px}
.ch-card__title{font-family:var(--font-display);font-weight:700;font-size:24px}
.ch-card__sub{font-size:14px;color:color-mix(in oklab,var(--color-ink) 82%,transparent);margin-top:4px}
@media(max-width:900px){.ch-cards{grid-template-columns:1fr 1fr}}

/* Image band — dark placeholder + dark scrim keep the light heading legible. */
.ch-band-img{position:relative;overflow:hidden;min-height:420px;display:flex;align-items:end;border-radius:var(--radius-xl)}
.ch-band-img__media{position:absolute;inset:0;border-radius:0}
.ch-band-img .ch-media--empty{background:linear-gradient(135deg,var(--color-bg),color-mix(in oklab,var(--color-paper) 70%,var(--color-accent-deep)))}
.ch-band-img .ch-media--empty::after{opacity:.2}
.ch-band-img__scrim{position:absolute;inset:0;background:linear-gradient(to top,color-mix(in oklab,var(--color-bg) 88%,transparent),transparent 66%)}
.ch-band-img__in{position:relative;z-index:2;display:flex;justify-content:space-between;align-items:end;gap:24px;flex-wrap:wrap;padding:0 40px 48px;width:100%;color:var(--color-ink)}
.ch-band-img__title{font-family:var(--font-display);font-weight:700;font-size:clamp(30px,4.6vw,54px);letter-spacing:-.02em;max-width:18ch;margin-top:14px;line-height:1.03;overflow-wrap:break-word}

/* Bands — both stay in the dark register; accent is glow/wash, never a text-bearing fill. */
.ch-band-wrap{padding-top:0}
.ch-band{border-radius:var(--radius-xl);padding:64px 52px;text-align:center;border:1px solid transparent}
@media(max-width:520px){.ch-band{padding:40px 22px}}
.ch-band--accent{background:radial-gradient(120% 140% at 15% 0%,color-mix(in oklab,var(--color-accent) 18%,var(--color-bg)),var(--color-bg) 62%);color:var(--color-ink);border-color:color-mix(in oklab,var(--color-accent) 40%,var(--color-line));box-shadow:0 0 90px -34px color-mix(in oklab,var(--color-accent) 70%,transparent) inset}
.ch-band--ink{background:linear-gradient(150deg,var(--color-paper),var(--color-bg));color:var(--color-ink);border-color:var(--color-line)}
.ch-eyebrow--band::before{background:var(--color-accent-deep)}
.ch-band__title{font-family:var(--font-display);font-weight:700;font-size:clamp(32px,6vw,80px);letter-spacing:-.02em;margin-top:16px;line-height:.98;overflow-wrap:break-word}
.ch-band__lede{max-width:46ch;margin:18px auto 0;opacity:.85;font-size:19px}
.ch-band .ch-btn{margin-top:28px}

/* Tiers — dark paper cards; the popular one gets an accent-deep glow border. */
.ch-tiers{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:16px}
.ch-tier{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:28px;display:flex;flex-direction:column}
.ch-tier--pop{border-color:var(--color-accent-deep);box-shadow:0 0 40px -18px color-mix(in oklab,var(--color-accent) 70%,transparent)}
.ch-tier__k{font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--color-ink-soft)}
.ch-tier__name{font-family:var(--font-display);font-weight:700;font-size:30px;margin:6px 0 12px}
.ch-tier__amt{font-family:var(--font-display);font-weight:700;font-size:40px}
.ch-tier__amt small{font-family:var(--font-body);font-weight:500;font-size:15px;color:var(--color-ink-soft)}
.ch-tier__feats{list-style:none;margin:18px 0 22px;display:grid;gap:9px}
.ch-tier__feat{font-size:15px;color:var(--color-ink-soft);display:flex;gap:10px;align-items:flex-start}
.ch-tier__feat::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--color-accent-deep);flex:none;margin-top:8px;box-shadow:0 0 6px color-mix(in oklab,var(--color-accent) 60%,transparent)}
.ch-tier__cta{width:100%;justify-content:center;margin-top:auto}
@media(max-width:820px){.ch-tiers{grid-template-columns:1fr}}

/* Alt band + activity tabs — dark fixtures/results panels, accent-deep highlights. */
.ch-sec--alt{background:var(--color-paper);padding-block:var(--space-16);border-block:1px solid var(--color-line)}
@media(max-width:640px){.ch-sec--alt{padding-block:var(--space-10)}}
.ch-tabs__bar{display:flex;gap:8px;flex-wrap:wrap;margin:26px 0 22px}
.ch-tabs__btn{font-size:13px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;padding:10px 20px;border-radius:999px;border:1px solid var(--color-line);background:transparent;color:var(--color-ink-soft);cursor:pointer}
.ch-tabs__btn--on{background:color-mix(in oklab,var(--color-accent) 12%,transparent);color:var(--color-accent-deep);border-color:var(--color-accent-deep);box-shadow:0 0 18px -8px color-mix(in oklab,var(--color-accent) 70%,transparent)}
.ch-tabs__panel--off{display:none}
.ch-fx-list,.ch-res-list{background:var(--color-bg);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:14px;display:flex;flex-direction:column;gap:6px}
.ch-fx,.ch-res{display:flex;align-items:center;gap:16px;padding:14px 16px;border-radius:var(--radius-md);color:var(--color-ink)}
.ch-fx:hover,.ch-res:hover{background:color-mix(in oklab,var(--color-ink) 6%,transparent)}
.ch-fx__date{text-align:center;line-height:1;min-width:44px}
.ch-fx__date b{font-family:var(--font-display);font-size:22px;color:var(--color-accent-deep)}
.ch-fx__date span{font-size:11px;letter-spacing:.1em;color:var(--color-ink-soft)}
.ch-fx__body{display:flex;flex-direction:column;flex:1}
.ch-fx__comp{font-size:12px;color:var(--color-ink-soft)}
.ch-fx__match{font-weight:600}
.ch-fx__time{font-family:var(--font-display);font-weight:700}
.ch-res{gap:14px}
.ch-res__date{min-width:64px;font-size:13px;color:var(--color-ink-soft)}
.ch-res__teams{flex:1;font-weight:500}
.ch-res__score{font-family:var(--font-display);font-weight:700}
.ch-badge{min-width:26px;text-align:center;font-size:12px;font-weight:700;padding:4px 8px;border-radius:999px}
.ch-badge--w{background:color-mix(in oklab,var(--color-accent) 14%,transparent);color:var(--color-accent-deep);border:1px solid var(--color-accent-deep)}
.ch-badge--l{background:color-mix(in oklab,var(--color-ink) 12%,transparent);color:var(--color-ink-soft)}
.ch-badge--d{background:transparent;border:1px solid var(--color-line);color:var(--color-ink-soft)}
.ch-evt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:16px}
.ch-evt{background:var(--color-bg);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:22px}
.ch-evt__meta{display:flex;justify-content:space-between;margin-bottom:12px;font-size:12px}
.ch-evt__tag{color:var(--color-accent-deep);font-weight:700;text-transform:uppercase;letter-spacing:.08em}
.ch-evt__date{color:var(--color-ink-soft)}
.ch-evt__title{font-family:var(--font-display);font-weight:700;font-size:22px;margin-bottom:8px}
.ch-evt__detail{font-size:15px;color:var(--color-ink-soft)}

/* News — dark cards, grotesque titles. */
.ch-news{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:28px}
.ch-news__card{display:block;transition:transform .25s ease}
.ch-news__card:hover{transform:translateY(-4px)}
.ch-news__media{height:200px;border-radius:var(--radius-md);margin-bottom:16px}
.ch-news__meta{display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:12px}
.ch-news__tag{color:var(--color-accent-deep);font-weight:700;text-transform:uppercase;letter-spacing:.08em}
.ch-news__date{color:var(--color-ink-soft)}
.ch-news__title{font-family:var(--font-display);font-weight:700;font-size:22px;line-height:1.05}

/* Info strip — dark panel, accent-deep labels/links. */
.ch-info{background:var(--color-paper);color:var(--color-ink);padding:var(--space-12) 0;border-block:1px solid var(--color-line)}
.ch-info__in{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr));gap:32px}
.ch-info__label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--color-accent-deep);margin-bottom:12px}
.ch-info__body{display:flex;flex-direction:column;gap:2px;font-size:16px;line-height:1.5}
.ch-info__link{color:var(--color-accent-deep);font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.08em;margin-top:6px}
.ch-info__link:hover{color:var(--color-ink)}

.ch-sec__title--sm{font-size:clamp(28px,3.8vw,38px)}
.ch-link{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink);border-bottom:2px solid var(--color-accent-deep);padding-bottom:4px}
.ch-link:hover{color:var(--color-accent-deep)}
.ch-sponsors{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px}
.ch-sponsors__tile{height:88px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:var(--color-ink-soft);text-transform:uppercase;letter-spacing:.08em;text-align:center;padding:8px;transition:border-color .2s ease,color .25s ease,box-shadow .2s ease}
.ch-sponsors__tile:hover{border-color:color-mix(in oklab,var(--color-accent) 55%,var(--color-line));color:var(--color-ink);box-shadow:0 0 20px -10px color-mix(in oklab,var(--color-accent) 65%,transparent)}

/* Benefits — dark cards; a small glowing accent mark. */
.ch-benefits{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:16px}
.ch-benefit{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:28px}
.ch-benefit__dot{display:block;width:24px;height:3px;border-radius:2px;background:var(--color-accent-deep);margin-bottom:18px;box-shadow:0 0 10px color-mix(in oklab,var(--color-accent) 60%,transparent)}
.ch-benefit__title{font-family:var(--font-display);font-weight:700;font-size:21px;margin-bottom:8px}
.ch-benefit__desc{font-size:15px;color:var(--color-ink-soft)}

/* Directory — 3 across; initials avatars on a dark accent-washed tile. */
.ch-people{display:grid;grid-template-columns:repeat(3,1fr);gap:20px 28px}
@media(max-width:760px){.ch-people{grid-template-columns:1fr 1fr}}
@media(max-width:460px){.ch-people{grid-template-columns:1fr}}
.ch-person{display:flex;flex-direction:column}
.ch-person__avatar{aspect-ratio:1/1;border-radius:var(--radius-lg);margin-bottom:14px}
.ch-avatar{display:grid;place-items:center;background:linear-gradient(140deg,color-mix(in oklab,var(--color-accent) 18%,var(--color-bg)),var(--color-paper));color:var(--color-accent-deep);font-family:var(--font-display);font-weight:700;font-size:30px;letter-spacing:.02em;border:1px solid var(--color-line)}
.ch-person__role{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--color-accent-deep)}
.ch-person__name{font-family:var(--font-display);font-weight:700;font-size:20px;margin:4px 0;min-height:2.2em}
.ch-person__email{font-size:14px;color:var(--color-ink-soft);word-break:break-word}
.ch-person__email:hover{color:var(--color-accent-deep)}

/* Timeline — hairline rows, grotesque years in glowing accent. */
.ch-timeline{display:flex;flex-direction:column;border-top:1px solid var(--color-line)}
.ch-milestone{display:grid;grid-template-columns:120px 1fr;gap:24px;padding:26px 0;border-bottom:1px solid var(--color-line)}
.ch-milestone__year{font-family:var(--font-display);font-weight:700;font-size:28px;color:var(--color-accent-deep)}
.ch-milestone__title{font-family:var(--font-display);font-weight:700;font-size:22px;margin-bottom:6px}
.ch-milestone__desc{font-size:15px;color:var(--color-ink-soft);max-width:60ch}
@media(max-width:600px){.ch-milestone{grid-template-columns:1fr;gap:6px}}

/* Included / excluded / policies — fine accent-deep marks. */
.ch-splits{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:28px}
.ch-split__h{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--color-ink-soft);margin-bottom:16px}
.ch-split__list{list-style:none;display:grid;gap:11px}
.ch-split__yes,.ch-split__no{font-size:15px;display:flex;gap:10px;align-items:flex-start}
.ch-split__yes::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--color-accent-deep);flex:none;margin-top:7px;box-shadow:0 0 6px color-mix(in oklab,var(--color-accent) 55%,transparent)}
.ch-split__no{color:var(--color-ink-soft)}
.ch-split__no::before{content:"";width:14px;height:1px;background:var(--color-ink-soft);flex:none;margin-top:11px}
.ch-policies{display:grid;gap:14px}
.ch-policy__title{font-family:var(--font-display);font-weight:700;font-size:17px;margin-bottom:4px}
.ch-policy__desc{font-size:14px;color:var(--color-ink-soft)}

/* Steps — dark cards; grotesque numeral in glowing accent. */
.ch-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:16px}
.ch-step{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:26px}
.ch-step__num{font-family:var(--font-display);font-weight:700;font-size:34px;color:var(--color-accent-deep);display:block;margin-bottom:10px}
.ch-step__title{font-family:var(--font-display);font-weight:700;font-size:19px;margin-bottom:6px}
.ch-step__desc{font-size:14px;color:var(--color-ink-soft)}

/* FAQ — native <details>, hairline rows, fine accent +/- mark. Shares the 1200px column. */
.ch-faq{border-top:1px solid var(--color-line)}
.ch-faq__item{border-bottom:1px solid var(--color-line)}
.ch-faq__q{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:22px 0;font-family:var(--font-display);font-weight:700;font-size:19px}
.ch-faq__q::-webkit-details-marker{display:none}
.ch-faq__mark{position:relative;width:16px;height:16px;flex:none}
.ch-faq__mark::before,.ch-faq__mark::after{content:"";position:absolute;background:var(--color-accent-deep);inset:7px 0 auto 0;height:2px}
.ch-faq__mark::after{transform:rotate(90deg);transition:transform .2s ease}
.ch-faq__item[open] .ch-faq__mark::after{transform:rotate(0)}
.ch-faq__a{padding:0 0 22px;color:var(--color-ink-soft);max-width:64ch}

/* Contact — form + dark info panel. */
.ch-contact{display:grid;grid-template-columns:1.4fr 1fr;gap:32px;align-items:start}
.ch-contact__form{display:grid;gap:16px}
.ch-field{display:grid;gap:6px}
.ch-field__label{font-size:13px;font-weight:600;color:var(--color-ink-soft)}
.ch-field__input{font-family:var(--font-body);font-size:15px;color:var(--color-ink);background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);padding:13px 16px}
.ch-field__input:focus{outline:2px solid var(--color-accent-deep);outline-offset:1px}
.ch-contact__info{background:var(--color-paper);color:var(--color-ink);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:28px}
.ch-contact__h{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--color-accent-deep);margin-bottom:16px}
.ch-contact__map{aspect-ratio:4/3;border-radius:var(--radius-md);margin-bottom:18px}
.ch-contact__lines{display:flex;flex-direction:column;gap:2px;margin-bottom:14px}
.ch-contact__link{display:block;color:var(--color-accent-deep);font-size:15px;margin-bottom:6px}
.ch-contact__link:hover{color:var(--color-ink)}
.ch-contact__connect{display:flex;gap:8px;margin-top:14px}
.ch-contact__social{width:44px;height:44px;border-radius:50%;border:1px solid var(--color-line);display:grid;place-items:center;font-weight:700;color:var(--color-ink)}
.ch-contact__social:hover{border-color:var(--color-accent-deep);color:var(--color-accent-deep);box-shadow:0 0 18px -6px color-mix(in oklab,var(--color-accent) 70%,transparent)}
@media(max-width:760px){.ch-contact{grid-template-columns:1fr}}

/* Hover animations for the static surfaces — subtle lift + the hairline lights to accent. */
.ch-benefit,.ch-step,.ch-tier{transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}
.ch-benefit:hover,.ch-step:hover{transform:translateY(-4px);border-color:color-mix(in oklab,var(--color-accent) 55%,var(--color-line));box-shadow:0 0 30px -14px color-mix(in oklab,var(--color-accent) 65%,transparent)}
.ch-tier:hover{transform:translateY(-4px);box-shadow:0 0 34px -16px color-mix(in oklab,var(--color-accent) 65%,transparent)}
.ch-tier:hover:not(.ch-tier--pop){border-color:color-mix(in oklab,var(--color-accent) 55%,var(--color-line))}
.ch-person{transition:transform .2s ease}
.ch-person:hover{transform:translateY(-3px)}
.ch-person__avatar{transition:transform .25s ease}
.ch-person:hover .ch-person__avatar{transform:scale(1.03)}
.ch-faq__q:hover{color:var(--color-accent-deep)}
@media(prefers-reduced-motion:reduce){
	.ch-benefit:hover,.ch-step:hover,.ch-tier:hover,.ch-person:hover,.ch-person:hover .ch-person__avatar,.ch-card:hover,.ch-news__card:hover,.ch-tiles__tile:hover{transform:none}
}

/* Member sign-in card — narrow, centred, dark paper with hairline; reads as a form. */
.ch-auth-wrap{max-width:460px;padding-block:var(--space-16)}
@media(max-width:640px){.ch-auth-wrap{padding-block:var(--space-10)}}
.ch-auth{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-xl);padding:40px;box-shadow:0 0 60px -30px color-mix(in oklab,var(--color-accent) 50%,transparent)}
@media(max-width:520px){.ch-auth{padding:28px 22px}}
.ch-auth__title{font-family:var(--font-display);font-weight:700;font-size:clamp(28px,4.4vw,40px);letter-spacing:-.02em;line-height:1;margin-top:16px}
.ch-auth__lede{color:var(--color-ink-soft);font-size:15px;margin-top:10px}
.ch-auth__form{display:grid;gap:16px;margin-top:26px}
.ch-auth__row{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;font-size:14px}
.ch-auth__remember{display:flex;align-items:center;gap:8px;color:var(--color-ink-soft);cursor:pointer}
.ch-auth__forgot{color:var(--color-accent-deep);font-weight:600}
.ch-auth__forgot:hover{color:var(--color-ink)}
.ch-auth__submit{width:100%;justify-content:center;margin-top:4px}
.ch-auth__alt{text-align:center;margin-top:22px;font-size:14px;color:var(--color-ink-soft)}
.ch-auth__alt-link{color:var(--color-ink);font-weight:700;border-bottom:2px solid var(--color-accent-deep);padding-bottom:2px}
.ch-auth__alt-link:hover{color:var(--color-accent-deep)}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter FloodlightStylesheetTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add assets/looks/floodlight.css tests/php/FloodlightStylesheetTest.php
git commit -m "feat: add Floodlight look stylesheet (dark accent-glow re-skin)"
```

---

### Task 3: Preview look switch

**Files:**
- Modify: `preview/index.php` (register both looks; take the active look in the palette helper; route `?look=`; add a look toggle that cycles the registered looks)
- Test: `tests/php/PreviewRenderTest.php` (add two cases; do not change the existing two)

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look_Registry` (`register()`, `set_active()`, `has()`, `active()`), `Blueworx_Clubhouse_Court_Side`, `Blueworx_Clubhouse_Floodlight`, `Blueworx_Clubhouse_Color_Engine::derive()`, `Blueworx_Clubhouse_Page_Renderer::document()`.
- Produces: `blueworx_clubhouse_preview_document()` renders the look selected by `$_GET['look']` (default `court-side`), and `blueworx_clubhouse_preview_palettes( Blueworx_Clubhouse_Base_Look $look )` derives swatches from the passed look's shell.

- [ ] **Step 1: Write the failing tests**

Append these two methods inside the `PreviewRenderTest` class in `tests/php/PreviewRenderTest.php` (before the closing `}`):

```php
	public function test_look_param_switches_to_floodlight(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$_GET['look'] = 'floodlight';
		$html = blueworx_clubhouse_preview_document();
		unset( $_GET['look'] );

		$this->assertStringContainsString( 'floodlight.css', $html );
		$this->assertStringContainsString( 'family=Bricolage%20Grotesque', $html );
		$this->assertStringContainsString( 'family=Hanken%20Grotesk', $html );
		// Dark shell token made it into the emitted :root.
		$this->assertStringContainsString( '#14110b', $html );
	}

	public function test_default_look_is_still_court_side(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		unset( $_GET['look'] );
		$html = blueworx_clubhouse_preview_document();
		$this->assertStringContainsString( 'court-side.css', $html );
		$this->assertStringNotContainsString( 'floodlight.css', $html );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter PreviewRenderTest`
Expected: FAIL — `test_look_param_switches_to_floodlight` fails (`floodlight.css` not found; only Court Side is registered).

- [ ] **Step 3: Update the palette helper to take the active look**

In `preview/index.php`, change the signature and first line of `blueworx_clubhouse_preview_palettes()` so it derives from a passed look instead of hardcoding Court Side. Replace:

```php
function blueworx_clubhouse_preview_palettes(): array {
	$look    = new Blueworx_Clubhouse_Court_Side();
	$tokens  = $look->tokens();
```

with:

```php
function blueworx_clubhouse_preview_palettes( Blueworx_Clubhouse_Base_Look $look ): array {
	$tokens  = $look->tokens();
```

- [ ] **Step 4: Register both looks, route `?look=`, pass the active look to the palette helper, and cycle looks in the toggle**

In `preview/index.php`, inside `blueworx_clubhouse_preview_document()`, find:

```php
	$storage  = new Blueworx_Clubhouse_Preview_Storage();
	$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
	$registry->register( new Blueworx_Clubhouse_Court_Side() );
	$branding   = new Blueworx_Clubhouse_Branding( $storage );
	$visibility = new Blueworx_Clubhouse_Visibility( $storage );
```

Replace it with (registers Floodlight, resolves `?look=`, orders the look slugs for the toggle):

```php
	$storage  = new Blueworx_Clubhouse_Preview_Storage();
	$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
	$registry->register( new Blueworx_Clubhouse_Court_Side() );
	$registry->register( new Blueworx_Clubhouse_Floodlight() );
	$look_order = array( 'court-side', 'floodlight' );
	$look_slug  = isset( $_GET['look'] ) && is_string( $_GET['look'] ) ? preg_replace( '/[^a-z-]/', '', $_GET['look'] ) : 'court-side';
	if ( ! $registry->has( (string) $look_slug ) ) {
		$look_slug = 'court-side';
	}
	$registry->set_active( (string) $look_slug );
	$branding   = new Blueworx_Clubhouse_Branding( $storage );
	$visibility = new Blueworx_Clubhouse_Visibility( $storage );
```

Then find the call that builds `$palettes`:

```php
	$palettes  = blueworx_clubhouse_preview_palettes();
```

Replace it with (pass the active look):

```php
	$palettes  = blueworx_clubhouse_preview_palettes( $registry->active() );
```

Then find the block that renders the document at the end of the function:

```php
	// Served with docroot = plugin root, so the look stylesheet resolves from '/'.
	return Blueworx_Clubhouse_Page_Renderer::document(
		$registry->active(),
		$branding,
		$body . $switcher . $style,
		'/'
	);
```

Replace it with (add a look toggle that cycles to the next registered look, keeping the current page):

```php
	$idx        = array_search( (string) $look_slug, $look_order, true );
	$next       = $look_order[ ( (int) $idx + 1 ) % count( $look_order ) ];
	$next_look  = $registry->get( $next );
	$next_name  = $next_look instanceof Blueworx_Clubhouse_Base_Look ? $next_look->name() : ucwords( str_replace( '-', ' ', $next ) );
	$look_toggle = '<a class="ch-look-toggle" href="?look=' . rawurlencode( $next )
		. '&page=' . rawurlencode( (string) $page ) . '">Look: '
		. htmlspecialchars( $next_name, ENT_QUOTES, 'UTF-8' ) . ' &rarr;</a>';
	$style      .= '<style>.ch-look-toggle{position:fixed;left:16px;bottom:16px;z-index:90;background:#1e1913;color:#f3ede0;font:600 13px/1 system-ui,sans-serif;padding:12px 16px;border-radius:8px;text-decoration:none;border:1px solid #302a20}</style>';

	// Served with docroot = plugin root, so the look stylesheet resolves from '/'.
	return Blueworx_Clubhouse_Page_Renderer::document(
		$registry->active(),
		$branding,
		$body . $switcher . $look_toggle . $style,
		'/'
	);
```

> `Blueworx_Clubhouse_Base_Look_Registry::get( string $slug ): ?Blueworx_Clubhouse_Base_Look` is an existing public accessor (verified) — the `instanceof` guard satisfies strict types since `get()` is nullable, and `$next` is always a registered slug.

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter PreviewRenderTest`
Expected: PASS (4 tests — the two existing Court Side cases plus the two new ones).

- [ ] **Step 6: Run the whole suite**

Run: `composer test`
Expected: all tests green (existing + Floodlight class + stylesheet + preview).

- [ ] **Step 7: Commit**

```bash
git add preview/index.php tests/php/PreviewRenderTest.php
git commit -m "feat: register Floodlight in the preview and cycle looks via ?look="
```

---

### Task 4: Version bump + changelog

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (Version header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `package.json` (`version`)
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes: nothing.
- Produces: version `0.10.0` across the plugin header, the version constant, and `package.json`, plus a changelog entry.

- [ ] **Step 1: Bump the plugin header and constant**

In `blueworx-labs-clubhouse.php`, change the version in the header comment:

```php
 * Version:           0.8.0
```

to:

```php
 * Version:           0.10.0
```

and the constant:

```php
define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.8.0' );
```

to:

```php
define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.10.0' );
```

- [ ] **Step 2: Bump `package.json`**

In `package.json`, change:

```json
  "version": "0.8.0",
```

to:

```json
  "version": "0.10.0",
```

- [ ] **Step 3: Add the changelog entry**

In `CHANGELOG.md`, add a new entry directly beneath the top heading (above the existing `0.8.0` entry). Match the file's existing heading style; the entry text is:

```markdown
## 0.10.0

### Added
- **Floodlight — third Base Look.** A bold, dark, night-match re-skin (Bricolage Grotesque
  + Hanken Grotesk, warm-ink canvas, bold-modern 16/11/7 radii, accent spent as glow) covering every
  `ch-*` hook. Adds `includes/looks/class-floodlight.php` and `assets/looks/floodlight.css`,
  registers the look in the DB-free preview, and cycles looks via `?look=`. Pure re-skin:
  no changes to section renderers or the theme engine. On the dark shell all accent
  text/marks route through the engine's AA-guaranteed `--color-accent-deep`; the engine's
  `accent-ink`-on-dark limitation is sidestepped by the glow idiom, not triggered.

> Note: `0.9.0` is the Members' House Base Look, delivered on its own sibling branch/PR;
> Floodlight takes `0.10.0` so the two do not collide when both merge into
> `base-look-theming-design`.
```

- [ ] **Step 4: Verify the suite is still green**

Run: `composer test`
Expected: all tests green.

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump version to 0.10.0 and update changelog for Floodlight"
```

---

## Final verification (after all tasks)

- [ ] `composer test` — all tests green (existing + new).
- [ ] `composer lint` — run once as a final check; present any findings to the user (do not auto-fix in a loop).
- [ ] Start the preview: `php -S localhost:8124` from the plugin root; open `http://localhost:8124/preview/?look=floodlight`.
- [ ] Across `?page=home|about|membership|contact|login`: dark warm-ink shell, grotesque headings / clean body, bold-modern mid radii, accent glows, the bone punch button reads, no horizontal overflow from 320px to desktop.
- [ ] Click each accent swatch: the whole page re-themes; `accent-deep` text/marks stay legible on the dark shell across every preset hue; glows re-tint.
- [ ] Click the look toggle: cycles Court Side → Floodlight → Court Side; Court Side is visually unchanged (no regressions).
- [ ] Confirm no `.ch-*` hook is unstyled (visually scan each page section).
- [ ] Open PR from `floodlight-look` into `base-look-theming-design` (same target as PR #3), summarising the re-skin and the accent-on-dark decision.
```
