# Members' House Base Look Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Members' House — a second Base Look — as a refined-editorial re-skin (Fraunces + Mulish, warm parchment, small crisp radii, restrained accent) that reuses the existing engine and skin-agnostic sections unchanged.

**Architecture:** A Base Look pack is a PHP class implementing `Blueworx_Clubhouse_Base_Look` (supplies shell tokens, fonts, and a stylesheet path — never content, never accent tokens) plus a stylesheet that consumes the engine's CSS custom properties. Members' House adds `class-members-house.php` + `assets/looks/members-house.css`, both parallel to the Court Side pair. The preview registers both looks and switches the active one by `?look=`. No section renderer, page renderer, or engine code changes — that is the whole point of the re-skin.

**Tech Stack:** PHP 8.2 (strict types), PHPUnit 11, plain CSS with custom properties, Google-CDN fonts (Fraunces + Mulish).

## Global Constraints

- Class prefix `Blueworx_Clubhouse_`; files `declare(strict_types=1)` and guard with `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- **Skin-agnostic rule:** never modify section renderers or page composition. All appearance comes from the look's tokens + stylesheet. If a section seems to need a change, that is a section bug, not this plan's work — stop and flag it.
- **Accent contract:** the stylesheet references the accent *only* through `var(--color-accent)`, `var(--color-accent-ink)`, `var(--color-accent-deep)`, `var(--color-accent-wash)`. No club-colour hex literal appears in the stylesheet.
- `tokens()` must NOT contain any `--color-accent*` key (accent is engine-derived per club).
- Tests run with `composer test` (PHPUnit, no WordPress runtime); all existing tests must stay green.
- Version bump is a **minor** bump (new feature). Keep `blueworx-labs-clubhouse.php` (Version header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json` in sync, with a matching `CHANGELOG.md` entry.
- Indentation is tabs (match the existing files exactly, incl. `phpcs.xml.dist`).

---

### Task 1: Members' House Base Look class

**Files:**
- Create: `includes/looks/class-members-house.php`
- Modify: `includes/bootstrap.php` (add one `require_once` after the Court Side require, ~line 31)
- Test: `tests/php/MembersHouseTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look` (interface), `Blueworx_Clubhouse_Base_Look_Registry`, `Blueworx_Clubhouse_Branding`, `Blueworx_Clubhouse_Theme_Css`, `Blueworx_Clubhouse_Fake_Storage`.
- Produces: `final class Blueworx_Clubhouse_Members_House implements Blueworx_Clubhouse_Base_Look` with `slug()='members-house'`, `name()="Members' House"`, `description()`, `tokens()` (the fixed parchment shell), `fonts()` (Fraunces+Mulish), `stylesheet()='assets/looks/members-house.css'`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/MembersHouseTest.php`:

```php
<?php
// tests/php/MembersHouseTest.php

use PHPUnit\Framework\TestCase;

final class MembersHouseTest extends TestCase {

	public function test_identity_and_stylesheet(): void {
		$look = new Blueworx_Clubhouse_Members_House();
		$this->assertSame( 'members-house', $look->slug() );
		$this->assertSame( "Members' House", $look->name() );
		$this->assertSame( 'assets/looks/members-house.css', $look->stylesheet() );
		$this->assertNotSame( '', $look->description() );
	}

	public function test_tokens_carry_the_fixed_parchment_shell(): void {
		$t = ( new Blueworx_Clubhouse_Members_House() )->tokens();
		$this->assertSame( '#f2ece0', $t['--color-bg'] );
		$this->assertSame( '#201c15', $t['--color-ink'] );
		$this->assertSame( '#fbf7ef', $t['--color-paper'] );
		$this->assertSame( '#6b6154', $t['--color-ink-soft'] );
		$this->assertSame( '#e0d8c7', $t['--color-line'] );
		$this->assertStringContainsString( 'Fraunces', $t['--font-display'] );
		$this->assertStringContainsString( 'Mulish', $t['--font-body'] );
		// Small crisp radii — the editorial signature.
		$this->assertSame( '10px', $t['--radius-xl'] );
		$this->assertSame( '7px', $t['--radius-lg'] );
		$this->assertSame( '4px', $t['--radius-md'] );
		// Accent is engine-derived, never baked into the look.
		$this->assertArrayNotHasKey( '--color-accent', $t );
	}

	public function test_fonts_are_fraunces_and_mulish(): void {
		$families = array_column( ( new Blueworx_Clubhouse_Members_House() )->fonts(), 'family' );
		$this->assertSame( array( 'Fraunces', 'Mulish' ), $families );
		foreach ( ( new Blueworx_Clubhouse_Members_House() )->fonts() as $font ) {
			$this->assertArrayHasKey( 'weights', $font );
			$this->assertArrayHasKey( 'display', $font );
			$this->assertNotEmpty( $font['weights'] );
		}
	}

	public function test_registers_and_composes_with_derived_accent(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$registry->register( new Blueworx_Clubhouse_Members_House() );
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_accent( '#7a2f3a' );

		$vars = Blueworx_Clubhouse_Theme_Css::compose( $registry->active(), $branding );
		$this->assertSame( '#f2ece0', $vars['--color-bg'] );
		$this->assertSame( '#7a2f3a', $vars['--color-accent'] );
		$this->assertArrayHasKey( '--color-accent-ink', $vars );
		$this->assertArrayHasKey( '--color-accent-deep', $vars );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter MembersHouseTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Members_House" not found`.

- [ ] **Step 3: Write the class**

Create `includes/looks/class-members-house.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Members' House — the second Base Look. Refined and editorial: warm parchment
 * canvas, warm near-black ink, small crisp radii, hairline rules, Fraunces + Mulish.
 * The accent is spent sparingly by the stylesheet; its tokens are engine-derived,
 * not defined here. Supplies presentation only — never adds or reads content.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Members_House implements Blueworx_Clubhouse_Base_Look {

	public function slug(): string {
		return 'members-house';
	}

	public function name(): string {
		return "Members' House";
	}

	public function description(): string {
		return 'Refined, editorial — warm parchment, hairline rules, Fraunces display, restrained accent.';
	}

	/** @return array<string,string> */
	public function tokens(): array {
		return array(
			'--color-bg'       => '#f2ece0',
			'--color-paper'    => '#fbf7ef',
			'--color-ink'      => '#201c15',
			'--color-ink-soft' => '#6b6154',
			'--color-line'     => '#e0d8c7',
			'--radius-xl'      => '10px',
			'--radius-lg'      => '7px',
			'--radius-md'      => '4px',
			'--font-display'   => "'Fraunces', ui-serif, Georgia, serif",
			'--font-body'      => "'Mulish', ui-sans-serif, sans-serif",
		);
	}

	/** @return array<int,array{family:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Fraunces', 'weights' => array( 400, 500, 600, 700 ), 'display' => 'swap' ),
			array( 'family' => 'Mulish', 'weights' => array( 400, 500, 600, 700 ), 'display' => 'swap' ),
		);
	}

	public function stylesheet(): string {
		return 'assets/looks/members-house.css';
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
require_once __DIR__ . '/looks/class-members-house.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter MembersHouseTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/looks/class-members-house.php includes/bootstrap.php tests/php/MembersHouseTest.php
git commit -m "feat: add Members' House Base Look pack (tokens, Fraunces+Mulish)"
```

---

### Task 2: Members' House stylesheet

**Files:**
- Create: `assets/looks/members-house.css`
- Test: `tests/php/MembersHouseStylesheetTest.php`

**Interfaces:**
- Consumes: the engine custom properties emitted by `Theme_Css` (`--color-bg/-paper/-ink/-ink-soft/-line`, `--radius-xl/-lg/-md`, `--font-display/-body`, `--color-accent/-ink/-deep/-wash`) and the `ch-*` markup produced by `Blueworx_Clubhouse_Sections` / `Page_Renderer` (unchanged).
- Produces: `assets/looks/members-house.css` — a complete look stylesheet in the refined-editorial idiom, covering every `ch-*` hook Court Side covers.

- [ ] **Step 1: Write the failing test**

Create `tests/php/MembersHouseStylesheetTest.php`:

```php
<?php
// tests/php/MembersHouseStylesheetTest.php

use PHPUnit\Framework\TestCase;

final class MembersHouseStylesheetTest extends TestCase {

	private function css(): string {
		$path = dirname( __DIR__, 2 ) . '/assets/looks/members-house.css';
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
		$this->assertStringContainsString( 'var(--color-accent)', $css );
		// The demo accent must never be baked in — that would break re-theming.
		$this->assertStringNotContainsString( '#7a2f3a', $css );
		// Nor Court Side's accent.
		$this->assertStringNotContainsString( '#c6f24e', $css );
	}

	public function test_uses_the_look_fonts_only_via_tokens(): void {
		// Fonts come from --font-display/--font-body tokens; the stylesheet must not
		// hardcode a family name (that lives in the look class, not the CSS).
		$css = $this->css();
		$this->assertStringNotContainsString( 'Fraunces', $css );
		$this->assertStringNotContainsString( 'Mulish', $css );
		$this->assertStringContainsString( 'var(--font-display)', $css );
		$this->assertStringContainsString( 'var(--font-body)', $css );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter MembersHouseStylesheetTest`
Expected: FAIL — `Failed asserting that file "…/assets/looks/members-house.css" exists`.

- [ ] **Step 3: Write the stylesheet**

Create `assets/looks/members-house.css` with exactly this content:

```css
/* Members' House — look stylesheet. Refined editorial: warm parchment, Fraunces +
   Mulish, small crisp radii, hairline rules, accent spent sparingly. Consumes engine
   custom properties; the accent is always var(--color-accent*) so any club re-themes
   by swapping one colour. Parallel in coverage to court-side.css, different personality. */
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--color-bg);color:var(--color-ink);font-family:var(--font-body);font-size:17px;line-height:1.65;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
.ch-wrap{max-width:1200px;margin-inline:auto;padding-inline:28px}
@media(max-width:520px){.ch-wrap{padding-inline:20px}}

/* Spacing scale — same rhythm engine as Court Side; a touch more air for editorial calm. */
:root{--space-2:8px;--space-3:12px;--space-4:16px;--space-6:24px;--space-8:32px;--space-10:40px;--space-12:48px;--space-16:64px;--space-20:80px;--flow-lg:96px;--flow-sm:24px}
@media(max-width:640px){:root{--flow-lg:56px;--flow-sm:20px}}
.ch-main > *{margin-block:0}
.ch-main > * + *{margin-top:var(--flow-lg)}
.ch-tiles-sec,.ch-ticker,.ch-stats,.ch-tiers-sec{margin-top:var(--flow-sm)}

/* Scroll-reveal / skip link / focus — identical progressive-enhancement mechanics to
   Court Side (no-JS and reduced-motion get fully-visible content). */
.ch-reveal{opacity:0;transform:translateY(20px);transition:opacity .7s cubic-bezier(.2,.7,.2,1),transform .7s cubic-bezier(.2,.7,.2,1)}
.ch-reveal.is-in{opacity:1;transform:none}
@media(prefers-reduced-motion:reduce){.ch-reveal{opacity:1;transform:none;transition:none}}
.ch-skip{position:fixed;left:0;top:0;z-index:100;background:var(--color-ink);color:var(--color-bg);padding:12px 20px;border-radius:0 0 var(--radius-md) 0;font-weight:700;transform:translateY(-120%);transition:transform .15s ease}
.ch-skip:focus{transform:translateY(0)}
.ch-main:focus{outline:none}
:where(a,button,summary,input,select,textarea,[tabindex]):focus-visible{outline:2px solid var(--color-accent-deep);outline-offset:2px;border-radius:2px}

/* Eyebrow — no filled pill; a letter-spaced label preceded by a short accent rule. */
.ch-eyebrow{display:inline-flex;align-items:center;gap:12px;font-family:var(--font-body);font-size:12px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--color-ink-soft)}
.ch-eyebrow::before{content:"";width:26px;height:2px;background:var(--color-accent-deep);flex:none}

/* Buttons — rectangular, small radius; fills are the only bold accent use. */
.ch-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5em;font-family:var(--font-body);font-weight:700;font-size:15px;letter-spacing:.01em;padding:15px 26px;border-radius:var(--radius-md);white-space:nowrap;transition:transform .16s ease,background-color .18s ease,color .18s ease,box-shadow .2s ease,border-color .18s ease}
.ch-btn:hover{transform:translateY(-2px)}
.ch-btn:active{transform:translateY(0) scale(.98)}
.ch-btn--accent{background:var(--color-accent);color:var(--color-accent-ink)}
.ch-btn--accent:hover{background:color-mix(in oklab,var(--color-accent) 84%,var(--color-ink));box-shadow:0 12px 24px -16px color-mix(in oklab,var(--color-accent-deep) 80%,transparent)}
.ch-btn--ink{background:var(--color-ink);color:var(--color-bg)}
.ch-btn--ink:hover{background:color-mix(in oklab,var(--color-ink) 86%,var(--color-accent-deep));box-shadow:0 12px 24px -16px rgba(0,0,0,.4)}
.ch-btn--ghost{border:1px solid var(--color-ink);color:var(--color-ink)}
.ch-btn--ghost:hover{background:var(--color-ink);color:var(--color-bg)}
@media(prefers-reduced-motion:reduce){.ch-btn:hover,.ch-btn:active{transform:none}}

/* Nav — hairline foot, Mulish links, Fraunces wordmark, ink brand mark (not accent). */
.ch-nav{position:sticky;top:0;z-index:40;background:color-mix(in oklab,var(--color-bg) 88%,transparent);backdrop-filter:blur(10px);border-bottom:1px solid var(--color-line)}
.ch-nav__in{display:flex;align-items:center;justify-content:space-between;height:78px;gap:24px}
.ch-brand{font-family:var(--font-display);font-weight:600;font-size:26px;letter-spacing:-.01em;display:flex;align-items:center;gap:10px;flex:none}
.ch-brand__mark{width:36px;height:36px;border-radius:var(--radius-md);background:var(--color-ink);color:var(--color-bg);display:grid;place-items:center;font-family:var(--font-display);font-size:18px;font-weight:600}
.ch-nav__links{display:flex;gap:28px;font-size:15px;font-weight:600;color:var(--color-ink-soft)}
.ch-nav__link{display:inline-block;padding:8px 0}
.ch-nav__link:hover{color:var(--color-ink)}
@media(max-width:900px){.ch-nav__links{display:none}}

/* Mobile/tablet menu — no-JS <details> disclosure, same structure as Court Side,
   restyled to the crisp idiom. */
.ch-nav__disc{display:none;position:relative}
.ch-nav__burger{list-style:none;width:44px;height:44px;display:grid;place-items:center;border:1px solid var(--color-ink);border-radius:var(--radius-md);cursor:pointer}
.ch-nav__burger::-webkit-details-marker{display:none}
.ch-nav__burger-bars,.ch-nav__burger-bars::before,.ch-nav__burger-bars::after{content:"";display:block;width:20px;height:2px;background:var(--color-ink);border-radius:2px}
.ch-nav__burger-bars{position:relative}
.ch-nav__burger-bars::before{position:absolute;top:-6px}
.ch-nav__burger-bars::after{position:absolute;top:6px}
.ch-nav__disc[open] .ch-nav__burger{background:var(--color-ink)}
.ch-nav__disc[open] .ch-nav__burger-bars,.ch-nav__disc[open] .ch-nav__burger-bars::before,.ch-nav__disc[open] .ch-nav__burger-bars::after{background:var(--color-bg)}
.ch-nav__drawer{display:none}
.ch-nav__disc[open] .ch-nav__drawer{position:absolute;right:0;top:calc(100% + 12px);width:min(280px,80vw);background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);box-shadow:0 18px 40px -22px rgba(0,0,0,.3);padding:10px;display:flex;flex-direction:column}
.ch-nav__drawer .ch-nav__link{padding:13px 14px;border-radius:var(--radius-md);font-size:16px}
.ch-nav__drawer .ch-nav__link:hover,.ch-nav__drawer .ch-nav__link--active{background:var(--color-accent-wash)}
.ch-nav__drawer-join{width:100%;margin-bottom:8px}
.ch-nav__drawer-login{margin-top:6px;border-top:1px solid var(--color-line);padding-top:10px}
.ch-nav__drawer-join{display:none}
@media(max-width:900px){.ch-nav__disc{display:block}.ch-nav__in{gap:12px}.ch-nav__cta{gap:8px}.ch-brand{min-width:0}}
@media(max-width:520px){.ch-nav__cta .ch-btn--ink{display:none}.ch-nav__disc[open] .ch-nav__drawer-join{display:inline-flex}.ch-brand{font-size:22px}.ch-brand__mark{width:30px;height:30px;font-size:15px}}

.ch-banner{background:var(--color-ink);color:var(--color-bg)}
.ch-banner__in{display:flex;justify-content:center;height:40px;align-items:center}
.ch-banner__link{font-size:13px;font-weight:600;text-align:center}
.ch-banner__link:hover{color:var(--color-accent)}
.ch-nav__cta{display:flex;gap:10px;align-items:center}
/* Active link — full-contrast ink with a fine accent underline (signal beyond colour). */
.ch-nav__links .ch-nav__link--active{color:var(--color-ink);font-weight:700;text-decoration:underline;text-decoration-color:var(--color-accent-deep);text-decoration-thickness:2px;text-underline-offset:6px}
@media(max-width:900px){.ch-nav__cta .ch-btn--ghost{display:none}}

/* Media + empty placeholders — parchment-toned gradients, engine-derived so they
   re-theme with the accent; quiet photo icon. */
.ch-media{position:relative;overflow:hidden;background:var(--color-line);border-radius:var(--radius-lg)}
.ch-media__img{width:100%;height:100%;object-fit:cover;display:block}
.ch-media--empty{background:linear-gradient(135deg,var(--color-accent-wash),var(--color-paper) 54%,color-mix(in oklab,var(--color-accent) 20%,var(--color-paper)))}
.ch-media--empty::after{content:"";position:absolute;inset:0;background:center/50px no-repeat url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='50'%20height='50'%20viewBox='0%200%2024%2024'%20fill='none'%20stroke='%239b9384'%20stroke-width='1.2'%20stroke-linecap='round'%20stroke-linejoin='round'%3E%3Crect%20x='3'%20y='3'%20width='18'%20height='18'%20rx='1.5'/%3E%3Ccircle%20cx='8.5'%20cy='8.5'%20r='1.6'/%3E%3Cpath%20d='M21%2015l-4.5-4.5L5.5%2021'/%3E%3C/svg%3E");opacity:.65}
.ch-card:nth-child(2n) .ch-media--empty,.ch-news__card:nth-child(3n) .ch-media--empty{background:linear-gradient(150deg,color-mix(in oklab,var(--color-accent) 18%,var(--color-paper)),var(--color-paper) 62%,var(--color-accent-wash))}
.ch-card:nth-child(3n) .ch-media--empty{background:linear-gradient(115deg,var(--color-paper),var(--color-accent-wash) 72%)}

/* Hero — quiet parchment; a fine accent underline on the highlighted word, no rotated
   filled block. Ambient wash kept very soft. */
.ch-hero{padding:var(--space-16) 0 0;background:radial-gradient(80% 60% at 90% -10%,var(--color-accent-wash),transparent 60%)}
.ch-hero__title{font-family:var(--font-display);font-weight:500;font-size:clamp(38px,8vw,104px);letter-spacing:-.02em;line-height:1;overflow-wrap:break-word;hyphens:auto}
.ch-hero__hl{color:var(--color-ink);box-shadow:inset 0 -.09em 0 var(--color-accent);display:inline;max-width:100%}
.ch-hero__sub{display:flex;justify-content:space-between;align-items:end;gap:30px;margin-top:28px;flex-wrap:wrap}
.ch-hero__lede{max-width:42ch;font-size:19px;color:var(--color-ink-soft)}
.ch-hero__cta{display:flex;gap:12px;flex-wrap:wrap}
.ch-hero__media{position:relative;margin-top:var(--space-10);aspect-ratio:16/7;max-height:520px}
.ch-hero__media .ch-media{width:100%;height:100%;border-radius:var(--radius-xl)}
.ch-hero__pill{position:absolute;left:20px;bottom:20px;z-index:2;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);padding:11px 18px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:9px}
.ch-hero__pill-dot{width:9px;height:9px;border-radius:50%;background:var(--color-accent)}
@media(max-width:640px){.ch-hero__media{aspect-ratio:4/3}}
@keyframes ch-rise{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.ch-hero .ch-eyebrow,.ch-hero__title,.ch-hero__sub,.ch-hero__media{animation:ch-rise .7s cubic-bezier(.2,.7,.2,1) both}
.ch-hero__title{animation-delay:.07s}
.ch-hero__sub{animation-delay:.15s}
.ch-hero__media{animation-delay:.23s}
@media(prefers-reduced-motion:reduce){.ch-hero .ch-eyebrow,.ch-hero__title,.ch-hero__sub,.ch-hero__media{animation:none}}

/* Quick tiles — flat paper, hairline, small radius; hover inverts to ink. */
.ch-tiles-sec{padding:0}
.ch-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(160px,100%),1fr));gap:12px}
.ch-tiles__tile{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);padding:18px 20px;font-weight:700;font-size:15px;transition:background .18s ease,color .18s ease,border-color .18s ease}
.ch-tiles__tile:hover{background:var(--color-ink);color:var(--color-bg);border-color:var(--color-ink)}
.ch-tiles__arrow{color:var(--color-accent-deep)}
.ch-tiles__tile:hover .ch-tiles__arrow{color:var(--color-accent)}

/* Ticker — ink field, accent only as the small label + dots. No-JS pause preserved. */
.ch-ticker{display:flex;align-items:center;gap:0;background:var(--color-ink);color:var(--color-bg);overflow:hidden;border-radius:var(--radius-md)}
.ch-ticker__label{flex:none;background:var(--color-accent);color:var(--color-accent-ink);font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;padding:12px 16px}
.ch-ticker__viewport{position:relative;display:flex;overflow:hidden;white-space:nowrap;flex:1}
.ch-ticker__track{display:inline-flex;align-items:center;gap:34px;padding:0 17px;animation:ch-marquee 30s linear infinite}
.ch-ticker:hover .ch-ticker__track{animation-play-state:paused}
.ch-ticker__item{display:inline-flex;align-items:center;gap:11px;font-size:14px;font-weight:500}
.ch-ticker__dot{width:6px;height:6px;border-radius:50%;background:var(--color-accent)}
.ch-ticker__pause-cb{position:absolute;opacity:0;width:1px;height:1px}
.ch-ticker__pause{flex:none;width:44px;height:44px;display:grid;place-items:center;cursor:pointer;color:var(--color-bg)}
.ch-ticker__pause .ch-ticker__ico-play{display:none}
.ch-ticker__pause-cb:checked ~ .ch-ticker__viewport .ch-ticker__track{animation-play-state:paused}
.ch-ticker__pause-cb:checked ~ .ch-ticker__pause .ch-ticker__ico-pause{display:none}
.ch-ticker__pause-cb:checked ~ .ch-ticker__pause .ch-ticker__ico-play{display:block}
.ch-ticker__pause-cb:focus-visible ~ .ch-ticker__pause{outline:2px solid var(--color-accent);outline-offset:-3px}
@keyframes ch-marquee{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media(prefers-reduced-motion:reduce){.ch-ticker__track{animation:none}.ch-ticker__viewport{overflow-x:auto}.ch-ticker__track[aria-hidden="true"]{display:none}.ch-ticker__pause,.ch-ticker__pause-cb{display:none}}

/* Stats — flat paper cards; the featured one is an ink field with a fine accent rule
   (chosen by data, not DOM position). */
.ch-stats{padding:0}
.ch-stats__in{display:flex;gap:14px;flex-wrap:wrap}
.ch-stats__item{flex:1 1 200px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:26px 28px;transition:border-color .2s ease}
.ch-stats__item:hover{border-color:color-mix(in oklab,var(--color-accent) 50%,var(--color-line))}
.ch-stats__item--feature{background:var(--color-ink);color:var(--color-bg);border-color:transparent;border-top:3px solid var(--color-accent)}
.ch-stats__item--feature:hover{border-color:transparent;border-top-color:var(--color-accent)}
.ch-stats__item--feature .ch-stats__label{color:color-mix(in oklab,var(--color-bg) 72%,transparent)}
.ch-stats__value{font-family:var(--font-display);font-weight:600;font-size:46px;display:block;letter-spacing:-.02em}
.ch-stats__label{font-size:13px;font-weight:600;color:var(--color-ink-soft)}

/* Footer — hairline top, Mulish links, Fraunces nothing-loud. */
.ch-footer{margin-top:var(--flow-lg);padding:66px 0 40px;border-top:1px solid var(--color-line)}
.ch-footer__grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1.4fr;gap:40px}
.ch-footer__tagline{color:var(--color-ink-soft);max-width:30ch;margin-top:12px;font-size:15px}
.ch-footer__socials{display:flex;gap:8px;margin-top:16px}
.ch-footer__social{width:44px;height:44px;border-radius:var(--radius-md);border:1px solid var(--color-line);display:grid;place-items:center;font-weight:700}
.ch-footer__social:hover{background:var(--color-accent);color:var(--color-accent-ink);border-color:var(--color-accent)}
.ch-footer__h{font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--color-ink-soft);margin-bottom:14px;font-weight:700}
.ch-footer__link{display:block;font-size:15px;color:var(--color-ink-soft);padding-block:8px}
.ch-footer__link:hover{color:var(--color-ink)}
.ch-footer__lede{font-size:14px;color:var(--color-ink-soft);margin-bottom:14px;max-width:32ch}
.ch-footer__form{display:flex;gap:8px;flex-wrap:wrap}
.ch-footer__input{flex:1 1 160px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);padding:13px 18px;font-family:var(--font-body);font-size:15px;color:var(--color-ink)}
.ch-footer__legal{display:flex;gap:20px;flex-wrap:wrap;margin-top:38px;font-size:13px}
.ch-footer__legal-link{color:var(--color-ink-soft);display:inline-block;padding-block:8px}
.ch-footer__legal-link:hover{color:var(--color-ink)}
@media(max-width:820px){.ch-footer__grid{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.ch-footer__grid{grid-template-columns:1fr}}

/* Section heads — Fraunces titles at editorial weight. */
.ch-sec{padding:0}
.ch-sec__head{display:flex;justify-content:space-between;align-items:end;gap:24px;margin-bottom:34px;flex-wrap:wrap}
.ch-sec__title{font-family:var(--font-display);font-weight:500;font-size:clamp(30px,5vw,58px);letter-spacing:-.02em;max-width:18ch;margin-top:14px;line-height:1.02;overflow-wrap:break-word}
.ch-sec__title + *{margin-top:34px}
.ch-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.ch-card{position:relative;border-radius:var(--radius-lg);overflow:hidden;aspect-ratio:3/4;background:var(--color-line);color:var(--color-bg);display:flex;flex-direction:column;justify-content:end;transition:transform .2s ease}
.ch-card:hover{transform:translateY(-4px)}
.ch-card__media{position:absolute;inset:0;border-radius:0}
.ch-card__scrim{position:absolute;inset:0;background:linear-gradient(180deg,transparent 32%,color-mix(in oklab,var(--color-ink) 84%,transparent))}
.ch-card__tag{position:absolute;top:14px;left:14px;z-index:2;background:var(--color-accent);color:var(--color-accent-ink);font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:6px 11px;border-radius:var(--radius-md)}
.ch-card__body{position:relative;z-index:2;padding:20px}
.ch-card__title{font-family:var(--font-display);font-weight:600;font-size:24px}
.ch-card__sub{font-size:14px;color:color-mix(in oklab,var(--color-bg) 88%,transparent);margin-top:4px}
@media(max-width:900px){.ch-cards{grid-template-columns:1fr 1fr}}

/* Image band — dark placeholder keeps white heading legible. */
.ch-band-img{position:relative;overflow:hidden;min-height:420px;display:flex;align-items:end;border-radius:var(--radius-xl)}
.ch-band-img__media{position:absolute;inset:0;border-radius:0}
.ch-band-img .ch-media--empty{background:linear-gradient(135deg,var(--color-ink),color-mix(in oklab,var(--color-ink) 62%,var(--color-accent-deep)))}
.ch-band-img .ch-media--empty::after{opacity:.2;filter:invert(1)}
.ch-band-img__scrim{position:absolute;inset:0;background:linear-gradient(to top,color-mix(in oklab,var(--color-ink) 82%,transparent),transparent 66%)}
.ch-band-img__in{position:relative;z-index:2;display:flex;justify-content:space-between;align-items:end;gap:24px;flex-wrap:wrap;padding:0 40px 48px;width:100%;color:var(--color-bg)}
.ch-band-img__title{font-family:var(--font-display);font-weight:500;font-size:clamp(30px,4.6vw,52px);letter-spacing:-.02em;max-width:18ch;margin-top:14px;line-height:1.04;overflow-wrap:break-word}

/* Bands — quiet accent wash / ink field with a fine rule, not saturated blocks. */
.ch-band-wrap{padding-top:0}
.ch-band{border-radius:var(--radius-xl);padding:64px 52px;text-align:center;border:1px solid transparent}
@media(max-width:520px){.ch-band{padding:40px 22px}}
.ch-band--accent{background:var(--color-accent-wash);color:var(--color-ink);border-color:color-mix(in oklab,var(--color-accent) 40%,var(--color-line))}
.ch-band--ink{background:var(--color-ink);color:var(--color-bg)}
.ch-eyebrow--band::before{background:var(--color-accent-deep)}
.ch-band--ink .ch-eyebrow{color:color-mix(in oklab,var(--color-bg) 78%,transparent)}
.ch-band--ink .ch-eyebrow--band::before{background:var(--color-accent)}
.ch-band__title{font-family:var(--font-display);font-weight:500;font-size:clamp(30px,5.5vw,68px);letter-spacing:-.02em;margin-top:16px;line-height:1.02;overflow-wrap:break-word}
.ch-band__lede{max-width:46ch;margin:18px auto 0;opacity:.85;font-size:19px}
.ch-band .ch-btn{margin-top:28px}

/* Tiers — flat paper cards, hairline; the popular one gets an ink border + accent rule. */
.ch-tiers{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:16px}
.ch-tier{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:28px;display:flex;flex-direction:column}
.ch-tier--pop{border-color:var(--color-ink);border-top:3px solid var(--color-accent)}
.ch-tier__k{font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--color-ink-soft)}
.ch-tier__name{font-family:var(--font-display);font-weight:600;font-size:30px;margin:6px 0 12px}
.ch-tier__amt{font-family:var(--font-display);font-weight:600;font-size:40px}
.ch-tier__amt small{font-family:var(--font-body);font-weight:500;font-size:15px;color:var(--color-ink-soft)}
.ch-tier__feats{list-style:none;margin:18px 0 22px;display:grid;gap:9px}
.ch-tier__feat{font-size:15px;color:var(--color-ink-soft);display:flex;gap:10px;align-items:flex-start}
.ch-tier__feat::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--color-accent-deep);flex:none;margin-top:8px}
.ch-tier__cta{width:100%;justify-content:center;margin-top:auto}
@media(max-width:820px){.ch-tiers{grid-template-columns:1fr}}

/* Alt band + activity tabs. */
.ch-sec--alt{background:var(--color-paper);padding-block:var(--space-16);border-block:1px solid var(--color-line)}
@media(max-width:640px){.ch-sec--alt{padding-block:var(--space-10)}}
.ch-tabs__bar{display:flex;gap:8px;flex-wrap:wrap;margin:26px 0 22px}
.ch-tabs__btn{font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:10px 18px;border-radius:var(--radius-md);border:1px solid var(--color-line);background:transparent;color:var(--color-ink-soft);cursor:pointer}
.ch-tabs__btn--on{background:var(--color-ink);color:var(--color-bg);border-color:var(--color-ink)}
.ch-tabs__panel--off{display:none}
.ch-fx-list,.ch-res-list{background:var(--color-ink);border-radius:var(--radius-lg);padding:14px;display:flex;flex-direction:column;gap:6px}
.ch-fx,.ch-res{display:flex;align-items:center;gap:16px;padding:14px 16px;border-radius:var(--radius-md);color:var(--color-bg)}
.ch-fx:hover,.ch-res:hover{background:color-mix(in oklab,var(--color-bg) 8%,transparent)}
.ch-fx__date{text-align:center;line-height:1;min-width:44px}
.ch-fx__date b{font-family:var(--font-display);font-size:22px;color:var(--color-accent)}
.ch-fx__date span{font-size:11px;letter-spacing:.1em}
.ch-fx__body{display:flex;flex-direction:column;flex:1}
.ch-fx__comp{font-size:12px;color:color-mix(in oklab,var(--color-bg) 70%,transparent)}
.ch-fx__match{font-weight:600}
.ch-fx__time{font-family:var(--font-display);font-weight:600}
.ch-res{gap:14px}
.ch-res__date{min-width:64px;font-size:13px;color:color-mix(in oklab,var(--color-bg) 70%,transparent)}
.ch-res__teams{flex:1;font-weight:500}
.ch-res__score{font-family:var(--font-display);font-weight:600}
.ch-badge{min-width:26px;text-align:center;font-size:12px;font-weight:700;padding:4px 8px;border-radius:var(--radius-md)}
.ch-badge--w{background:var(--color-accent);color:var(--color-accent-ink)}
.ch-badge--l{background:color-mix(in oklab,var(--color-bg) 18%,transparent);color:var(--color-bg)}
.ch-badge--d{background:transparent;border:1px solid color-mix(in oklab,var(--color-bg) 40%,transparent);color:var(--color-bg)}
.ch-evt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:16px}
.ch-evt{background:var(--color-bg);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:22px}
.ch-evt__meta{display:flex;justify-content:space-between;margin-bottom:12px;font-size:12px}
.ch-evt__tag{color:var(--color-accent-deep);font-weight:700;text-transform:uppercase;letter-spacing:.08em}
.ch-evt__date{color:var(--color-ink-soft)}
.ch-evt__title{font-family:var(--font-display);font-weight:600;font-size:22px;margin-bottom:8px}
.ch-evt__detail{font-size:15px;color:var(--color-ink-soft)}

/* News — flat cards, Fraunces titles. */
.ch-news{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:28px}
.ch-news__card{display:block;transition:transform .25s ease}
.ch-news__card:hover{transform:translateY(-4px)}
.ch-news__media{height:200px;border-radius:var(--radius-md);margin-bottom:16px}
.ch-news__meta{display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:12px}
.ch-news__tag{color:var(--color-accent-deep);font-weight:700;text-transform:uppercase;letter-spacing:.08em}
.ch-news__date{color:var(--color-ink-soft)}
.ch-news__title{font-family:var(--font-display);font-weight:600;font-size:22px;line-height:1.08}

/* Info strip — ink field, accent as fine labels/links. */
.ch-info{background:var(--color-ink);color:var(--color-bg);padding:var(--space-12) 0;border-radius:var(--radius-xl)}
.ch-info__in{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr));gap:32px}
.ch-info__label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--color-accent);margin-bottom:12px}
.ch-info__body{display:flex;flex-direction:column;gap:2px;font-size:16px;line-height:1.5}
.ch-info__link{color:var(--color-accent);font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.08em;margin-top:6px}
.ch-info__link:hover{color:var(--color-bg)}

.ch-sec__title--sm{font-size:clamp(26px,3.6vw,36px)}
.ch-link{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink);border-bottom:2px solid var(--color-accent);padding-bottom:4px}
.ch-link:hover{color:var(--color-accent-deep)}
.ch-sponsors{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px}
.ch-sponsors__tile{height:88px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:var(--color-ink-soft);text-transform:uppercase;letter-spacing:.08em;text-align:center;padding:8px;transition:background .25s ease,color .25s ease,border-color .2s ease}
.ch-sponsors__tile:hover{background:var(--color-ink);color:var(--color-bg);border-color:var(--color-ink)}

/* Benefits — flat cards; a small accent mark, not a big dot. */
.ch-benefits{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:16px}
.ch-benefit{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:28px}
.ch-benefit__dot{display:block;width:24px;height:3px;border-radius:2px;background:var(--color-accent-deep);margin-bottom:18px}
.ch-benefit__title{font-family:var(--font-display);font-weight:600;font-size:21px;margin-bottom:8px}
.ch-benefit__desc{font-size:15px;color:var(--color-ink-soft)}

/* Directory — 3 across; initials avatars in a quiet parchment tile. */
.ch-people{display:grid;grid-template-columns:repeat(3,1fr);gap:20px 28px}
@media(max-width:760px){.ch-people{grid-template-columns:1fr 1fr}}
@media(max-width:460px){.ch-people{grid-template-columns:1fr}}
.ch-person{display:flex;flex-direction:column}
.ch-person__avatar{aspect-ratio:1/1;border-radius:var(--radius-lg);margin-bottom:14px}
.ch-avatar{display:grid;place-items:center;background:linear-gradient(140deg,var(--color-accent-wash),color-mix(in oklab,var(--color-accent) 26%,var(--color-paper)));color:var(--color-accent-deep);font-family:var(--font-display);font-weight:600;font-size:30px;letter-spacing:.02em}
.ch-person__role{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--color-accent-deep)}
.ch-person__name{font-family:var(--font-display);font-weight:600;font-size:20px;margin:4px 0;min-height:2.2em}
.ch-person__email{font-size:14px;color:var(--color-ink-soft);word-break:break-word}
.ch-person__email:hover{color:var(--color-accent-deep)}

/* Timeline — hairline rows, Fraunces years. This is the editorial idiom at its strongest. */
.ch-timeline{display:flex;flex-direction:column;border-top:1px solid var(--color-line)}
.ch-milestone{display:grid;grid-template-columns:120px 1fr;gap:24px;padding:26px 0;border-bottom:1px solid var(--color-line)}
.ch-milestone__year{font-family:var(--font-display);font-weight:600;font-size:28px;color:var(--color-accent-deep)}
.ch-milestone__title{font-family:var(--font-display);font-weight:600;font-size:22px;margin-bottom:6px}
.ch-milestone__desc{font-size:15px;color:var(--color-ink-soft);max-width:60ch}
@media(max-width:600px){.ch-milestone{grid-template-columns:1fr;gap:6px}}

/* Included / excluded / policies — fine marks. */
.ch-splits{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:28px}
.ch-split__h{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--color-ink-soft);margin-bottom:16px}
.ch-split__list{list-style:none;display:grid;gap:11px}
.ch-split__yes,.ch-split__no{font-size:15px;display:flex;gap:10px;align-items:flex-start}
.ch-split__yes::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--color-accent-deep);flex:none;margin-top:7px}
.ch-split__no{color:var(--color-ink-soft)}
.ch-split__no::before{content:"";width:14px;height:1px;background:var(--color-ink-soft);flex:none;margin-top:11px}
.ch-policies{display:grid;gap:14px}
.ch-policy__title{font-family:var(--font-display);font-weight:600;font-size:17px;margin-bottom:4px}
.ch-policy__desc{font-size:14px;color:var(--color-ink-soft)}

/* Steps — flat cards; Fraunces numeral, accent-deep. */
.ch-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:16px}
.ch-step{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:26px}
.ch-step__num{font-family:var(--font-display);font-weight:600;font-size:34px;color:var(--color-accent-deep);display:block;margin-bottom:10px}
.ch-step__title{font-family:var(--font-display);font-weight:600;font-size:19px;margin-bottom:6px}
.ch-step__desc{font-size:14px;color:var(--color-ink-soft)}

/* FAQ — native <details>, hairline rows, fine +/- mark. Shares the 1200px column. */
.ch-faq{border-top:1px solid var(--color-line)}
.ch-faq__item{border-bottom:1px solid var(--color-line)}
.ch-faq__q{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:22px 0;font-family:var(--font-display);font-weight:600;font-size:19px}
.ch-faq__q::-webkit-details-marker{display:none}
.ch-faq__mark{position:relative;width:16px;height:16px;flex:none}
.ch-faq__mark::before,.ch-faq__mark::after{content:"";position:absolute;background:var(--color-accent-deep);inset:7px 0 auto 0;height:2px}
.ch-faq__mark::after{transform:rotate(90deg);transition:transform .2s ease}
.ch-faq__item[open] .ch-faq__mark::after{transform:rotate(0)}
.ch-faq__a{padding:0 0 22px;color:var(--color-ink-soft);max-width:64ch}

/* Contact — form + ink info panel. */
.ch-contact{display:grid;grid-template-columns:1.4fr 1fr;gap:32px;align-items:start}
.ch-contact__form{display:grid;gap:16px}
.ch-field{display:grid;gap:6px}
.ch-field__label{font-size:13px;font-weight:700;color:var(--color-ink-soft)}
.ch-field__input{font-family:var(--font-body);font-size:15px;color:var(--color-ink);background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);padding:13px 16px}
.ch-field__input:focus{outline:2px solid var(--color-accent-deep);outline-offset:1px}
.ch-contact__info{background:var(--color-ink);color:var(--color-bg);border-radius:var(--radius-lg);padding:28px}
.ch-contact__h{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--color-accent);margin-bottom:16px}
.ch-contact__map{aspect-ratio:4/3;border-radius:var(--radius-md);margin-bottom:18px}
.ch-contact__lines{display:flex;flex-direction:column;gap:2px;margin-bottom:14px}
.ch-contact__link{display:block;color:var(--color-accent);font-size:15px;margin-bottom:6px}
.ch-contact__link:hover{color:var(--color-bg)}
.ch-contact__connect{display:flex;gap:8px;margin-top:14px}
.ch-contact__social{width:44px;height:44px;border-radius:var(--radius-md);border:1px solid color-mix(in oklab,var(--color-bg) 30%,transparent);display:grid;place-items:center;font-weight:700}
.ch-contact__social:hover{background:var(--color-accent);color:var(--color-accent-ink);border-color:var(--color-accent)}
@media(max-width:760px){.ch-contact{grid-template-columns:1fr}}

/* Hover animations for the static surfaces — subtle lift + hairline warms to accent. */
.ch-benefit,.ch-step,.ch-tier{transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}
.ch-benefit:hover,.ch-step:hover{transform:translateY(-3px);border-color:color-mix(in oklab,var(--color-accent) 50%,var(--color-line));box-shadow:0 16px 34px -28px rgba(0,0,0,.35)}
.ch-tier:hover{transform:translateY(-3px);box-shadow:0 18px 38px -30px rgba(0,0,0,.4)}
.ch-tier:hover:not(.ch-tier--pop){border-color:color-mix(in oklab,var(--color-accent) 50%,var(--color-line))}
.ch-person{transition:transform .2s ease}
.ch-person:hover{transform:translateY(-3px)}
.ch-person__avatar{transition:transform .25s ease}
.ch-person:hover .ch-person__avatar{transform:scale(1.02)}
.ch-faq__q:hover{color:var(--color-accent-deep)}
@media(prefers-reduced-motion:reduce){
	.ch-benefit:hover,.ch-step:hover,.ch-tier:hover,.ch-person:hover,.ch-person:hover .ch-person__avatar,.ch-card:hover,.ch-news__card:hover,.ch-tiles__tile:hover{transform:none}
}

/* Member sign-in card — narrow, centred, paper with hairline; reads as a form. */
.ch-auth-wrap{max-width:460px;padding-block:var(--space-16)}
@media(max-width:640px){.ch-auth-wrap{padding-block:var(--space-10)}}
.ch-auth{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-xl);padding:40px}
@media(max-width:520px){.ch-auth{padding:28px 22px}}
.ch-auth__title{font-family:var(--font-display);font-weight:500;font-size:clamp(28px,4.4vw,40px);letter-spacing:-.02em;line-height:1.02;margin-top:16px}
.ch-auth__lede{color:var(--color-ink-soft);font-size:15px;margin-top:10px}
.ch-auth__form{display:grid;gap:16px;margin-top:26px}
.ch-auth__row{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;font-size:14px}
.ch-auth__remember{display:flex;align-items:center;gap:8px;color:var(--color-ink-soft);cursor:pointer}
.ch-auth__forgot{color:var(--color-accent-deep);font-weight:700}
.ch-auth__forgot:hover{color:var(--color-ink)}
.ch-auth__submit{width:100%;justify-content:center;margin-top:4px}
.ch-auth__alt{text-align:center;margin-top:22px;font-size:14px;color:var(--color-ink-soft)}
.ch-auth__alt-link{color:var(--color-ink);font-weight:700;border-bottom:2px solid var(--color-accent);padding-bottom:2px}
.ch-auth__alt-link:hover{color:var(--color-accent-deep)}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter MembersHouseStylesheetTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add assets/looks/members-house.css tests/php/MembersHouseStylesheetTest.php
git commit -m "feat: add Members' House look stylesheet (refined editorial re-skin)"
```

---

### Task 3: Preview look switch

**Files:**
- Modify: `preview/index.php` (register both looks; route `?look=`; derive palettes from the active look; add a look toggle)
- Test: `tests/php/PreviewRenderTest.php` (add two cases; do not change the existing two)

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look_Registry` (`register()`, `set_active()`, `active()`), `Blueworx_Clubhouse_Court_Side`, `Blueworx_Clubhouse_Members_House`, `Blueworx_Clubhouse_Color_Engine::derive()`, `Blueworx_Clubhouse_Page_Renderer::document()`.
- Produces: `blueworx_clubhouse_preview_document()` renders the look selected by `$_GET['look']` (default `court-side`), and `blueworx_clubhouse_preview_palettes( Blueworx_Clubhouse_Base_Look $look )` derives swatches from the passed look's shell.

- [ ] **Step 1: Write the failing tests**

Append these two methods inside the `PreviewRenderTest` class in `tests/php/PreviewRenderTest.php`:

```php
	public function test_look_param_switches_to_members_house(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$_GET['look'] = 'members-house';
		$html = blueworx_clubhouse_preview_document();
		unset( $_GET['look'] );

		$this->assertStringContainsString( 'members-house.css', $html );
		$this->assertStringContainsString( 'family=Fraunces', $html );
		$this->assertStringContainsString( 'family=Mulish', $html );
		// Parchment shell token made it into the emitted :root.
		$this->assertStringContainsString( '#f2ece0', $html );
	}

	public function test_default_look_is_still_court_side(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		unset( $_GET['look'] );
		$html = blueworx_clubhouse_preview_document();
		$this->assertStringContainsString( 'court-side.css', $html );
		$this->assertStringNotContainsString( 'members-house.css', $html );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter PreviewRenderTest`
Expected: FAIL — `test_look_param_switches_to_members_house` fails (`members-house.css` not found; only Court Side is registered).

- [ ] **Step 3: Update the palette helper to take the active look**

In `preview/index.php`, change the signature and body of `blueworx_clubhouse_preview_palettes()` so it derives from a passed look instead of hardcoding Court Side. Replace:

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

- [ ] **Step 4: Register both looks, route `?look=`, and pass the active look to the palette helper**

In `blueworx_clubhouse_preview_document()`, replace this block:

```php
	$storage  = new Blueworx_Clubhouse_Preview_Storage();
	$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
	$registry->register( new Blueworx_Clubhouse_Court_Side() );
	$branding   = new Blueworx_Clubhouse_Branding( $storage );
	$visibility = new Blueworx_Clubhouse_Visibility( $storage );

	$page      = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? preg_replace( '/[^a-z]/', '', $_GET['page'] ) : 'home';
	$body      = blueworx_clubhouse_preview_body( (string) $page, $branding, $visibility );
	$palettes  = blueworx_clubhouse_preview_palettes();
```

with:

```php
	$storage  = new Blueworx_Clubhouse_Preview_Storage();
	$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
	$registry->register( new Blueworx_Clubhouse_Court_Side() );
	$registry->register( new Blueworx_Clubhouse_Members_House() );
	$look_slug = isset( $_GET['look'] ) && is_string( $_GET['look'] ) ? preg_replace( '/[^a-z-]/', '', $_GET['look'] ) : 'court-side';
	if ( ! $registry->has( (string) $look_slug ) ) {
		$look_slug = 'court-side';
	}
	$registry->set_active( (string) $look_slug );
	$branding   = new Blueworx_Clubhouse_Branding( $storage );
	$visibility = new Blueworx_Clubhouse_Visibility( $storage );

	$page      = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? preg_replace( '/[^a-z]/', '', $_GET['page'] ) : 'home';
	$body      = blueworx_clubhouse_preview_body( (string) $page, $branding, $visibility );
	$palettes  = blueworx_clubhouse_preview_palettes( $registry->active() );
```

- [ ] **Step 5: Add a look toggle to the preview UI**

Still in `blueworx_clubhouse_preview_document()`, find the `$style` assignment (the `.ch-switcher` CSS). Directly after it, add a look toggle that links to the same page with the other look. Insert:

```php
	$other      = 'court-side' === $look_slug ? 'members-house' : 'court-side';
	$other_name = 'court-side' === $look_slug ? "Members' House" : 'Court Side';
	$look_toggle = '<a class="ch-look-toggle" href="?look=' . rawurlencode( $other ) . '&page=' . rawurlencode( (string) $page ) . '">Look: ' . htmlspecialchars( $other_name, ENT_QUOTES, 'UTF-8' ) . ' &rarr;</a>';
	$style      .= '<style>.ch-look-toggle{position:fixed;left:16px;bottom:16px;z-index:90;background:#201c15;color:#fff;font:600 13px/1 system-ui,sans-serif;padding:12px 16px;border-radius:8px;text-decoration:none}</style>';
```

Then change the final return so the toggle is appended to the body. Replace:

```php
		$body . $switcher . $style,
```

with:

```php
		$body . $switcher . $look_toggle . $style,
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter PreviewRenderTest`
Expected: PASS (4 tests — the original two plus the two new ones).

- [ ] **Step 7: Visually verify both looks in the preview**

Run from the plugin root: `php -S localhost:8124`
Then in a browser (or via curl for status):

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8124/assets/looks/members-house.css   # expect 200
curl -s "http://localhost:8124/preview/?look=members-house" | grep -c "members-house.css"          # expect 1
```

In a browser, walk `http://localhost:8124/preview/?look=members-house&page=home` and `&page=about|membership|contact|login`: parchment shell, Fraunces headings / Mulish body, small crisp radii, restrained accent, no horizontal overflow from 320px to desktop; the accent switcher re-themes the whole page with AA-legible derived ink; the look toggle flips back to Court Side. Confirm Court Side (default, no `?look=`) is visually unchanged. Stop the server afterward.

- [ ] **Step 8: Commit**

```bash
git add preview/index.php tests/php/PreviewRenderTest.php
git commit -m "feat: add ?look= switch and Members' House to the preview"
```

---

### Task 4: Version bump, changelog, full suite

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (Version header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `package.json` (`version`)
- Modify: `CHANGELOG.md` (new `0.9.0` entry)

- [ ] **Step 1: Bump the plugin version**

In `blueworx-labs-clubhouse.php`, change `Version:           0.8.0` to `Version:           0.9.0`, and `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.8.0' );` to `'0.9.0'`.

- [ ] **Step 2: Bump package.json**

In `package.json`, change `"version": "0.8.0"` to `"version": "0.9.0"`.

- [ ] **Step 3: Add the changelog entry**

In `CHANGELOG.md`, add directly under the intro block (above the `## [0.8.0]` entry), matching the file's existing format:

```markdown
## [0.9.0] - 2026-07-10

### Members' House — second Base Look

The first re-skin. A refined, editorial Base Look that reuses the engine and every
skin-agnostic section unchanged — swapping the active look changes only the tokens,
fonts, and stylesheet.

- **New Base Look `members-house`** (`Blueworx_Clubhouse_Members_House`): warm parchment
  shell, warm near-black ink, small crisp radii (10/7/4px), Fraunces (display) + Mulish
  (body). Accent stays engine-derived — the look defines no accent tokens.
- **Refined-editorial stylesheet** (`assets/looks/members-house.css`): every `ch-*` hook
  restyled in a restrained idiom — hairline rules, rectangular buttons, an accent
  underline on the hero highlight (no rotated block), quiet accent-wash bands, and fine
  accent marks. Accent is referenced only via `var(--color-accent*)`, so a club still
  re-themes by swapping one colour. All accessibility and motion behaviour (skip link,
  focus indicator, no-JS nav drawer, ticker pause, scroll-reveal, reduced-motion) is
  preserved through the shared section markup.
- **Preview look switch**: `preview/index.php` registers both looks and takes `?look=`
  (default Court Side), with a toggle to flip between them; the accent swatches derive
  from the active look's shell so they stay AA-correct per look.
```

- [ ] **Step 4: Run the full suite**

Run: `composer test`
Expected: PASS — all existing tests plus the new Members' House class, stylesheet, and preview tests (green, no deprecations).

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump version to 0.9.0 and update changelog for Members' House look"
```

---

## Self-Review

**Spec coverage:**
- §Type (Fraunces + Mulish) → Task 1 `fonts()`/`tokens()`, Task 2 uses `var(--font-*)`. ✅
- §Neutral shell tokens → Task 1 `tokens()` + `MembersHouseTest`. ✅
- §Component idiom (eyebrow, buttons, hero highlight, cards, bands, rule-based, nav/footer/ticker) → Task 2 stylesheet, each hook covered. ✅
- §Preserved behaviour (skip link, focus, nav drawer, ticker pause, reveal, reduced-motion, list semantics, placeholders) → Task 2 carries every hook; semantics live in the unchanged sections. ✅
- §Files 1–5 → Task 1 (class + bootstrap), Task 2 (stylesheet), Task 3 (preview), Task 4 (version/changelog). ✅
- §Testing (identity, tokens incl. no-accent, fonts, compose, stylesheet hygiene, preview render) → Tasks 1–3 tests. ✅

**Placeholder scan:** No TBD/TODO; all code shown in full; no "add error handling"–style hand-waves. ✅

**Type consistency:** `slug='members-house'`, class `Blueworx_Clubhouse_Members_House`, stylesheet path `assets/looks/members-house.css`, and `blueworx_clubhouse_preview_palettes( Blueworx_Clubhouse_Base_Look $look )` are used identically across Tasks 1–4. ✅

## Notes for the implementer

- The stylesheet in Task 2 Step 3 is a complete, coherent starting point authored to mirror Court Side's coverage. Step 7 of Task 3 is where you *see* it — treat any visual issue found there (contrast on the claret demo accent, overflow, a hook that reads wrong in the editorial idiom) as expected polish and fix it in the same task before committing, keeping the accent-only-via-`var()` rule intact.
- Do not touch `includes/render/*` or `includes/theme/*`. If the look appears to need a section change, that is a skin-agnostic leak — stop and raise it rather than editing a section.
- `phpcs.xml.dist` lints PHP with tabs; run `composer lint` once at the end and report findings (don't loop-fix).
