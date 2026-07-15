# Base Look authoring brief — the site structure for a new "look"

**Purpose:** everything you need to design and build a **4th Base Look** for the
Sports Club Template plugin, without touching the engine, the sections, or any page
content. Feed the "Design prompt" at the bottom into Claude Design to mock a new
personality; use the rest as the build spec.

There are currently **three** looks — this brief is look-agnostic and describes the
shared structure all four share:

| Look | Slug | Register | Personality |
|---|---|---|---|
| Court Side (reference) | `court-side` | C, light | Bright playful-premium — near-white warm canvas, big 32px radii, bold accent **blocks**, Syne + Inter |
| Members' House | `members-house` | A, light | Quiet editorial — warm parchment `#f2ece0`, hairline rules, small crisp radii (10/7/4), accent as an **underline**, Fraunces + Mulish |
| Floodlight | `floodlight` | B, dark | Night-match — warm-ink `#14110b`, 16/11/7 radii, accent spent as **glow** (never a text-bearing fill), Bricolage Grotesque + Hanken Grotesk |

Your 4th look is a **new personality across the same structure** — pick a distinct
register on each of the four axes below (type / shell / radii / accent idiom) so it
doesn't read as a variant of an existing one.

---

## 1. The re-skin contract (the one hard rule)

A Base Look supplies **presentation only**. To add a look you create exactly **two
files** (+ fonts + wiring + tests):

- `includes/looks/class-<slug>.php` — a PHP class implementing `Blueworx_Clubhouse_Base_Look`
- `assets/looks/<slug>.css` — a stylesheet that restyles **every** `.ch-*` hook

**Zero changes** to `includes/render/*` (the section renderers), `includes/theme/*`
(the engine), page composition, or content. If a section *needs* to change to make
your look work, that is a bug in the section (a skin assumption leaked into
structure) — not part of this work. The three existing looks prove this: their
`.ch-*` selector sets are byte-for-byte identical (255 selectors, verified).

The stylesheet references the accent **only** through `var(--color-accent*)` and
fonts **only** through `var(--font-*)` — so a club re-themes by swapping one colour,
and the look works for any accent.

---

## 2. The theming contract

### `tokens()` — the neutral "shell" (fixed per look, no accent)

`tokens()` MUST return these ten keys (and `--color-bg` + `--color-ink` are
mandatory). **No `--color-accent*` key may appear here** — accents are derived per
club by the engine, not the look. (Asserted by test.)

| Token | Role |
|---|---|
| `--color-bg` | page canvas |
| `--color-paper` | raised surface (cards sit above the canvas) |
| `--color-ink` | primary text colour **and** the solid "punch" button fill |
| `--color-ink-soft` | secondary text (must clear AA on `--color-bg`) |
| `--color-line` | hairline rules / borders |
| `--radius-xl` / `--radius-lg` / `--radius-md` | the radius scale (your look's "shape") |
| `--font-display` | headings, stat values, brand, titles |
| `--font-body` | body copy, nav, forms, labels, badges, buttons |

Keep the shell warm and never pure `#000`/`#fff` (house convention). Choose light or
dark deliberately — a light look is the easy path; a **second dark** look (different
from Floodlight) is a strong differentiator if you want one.

### The accent — one colour in, five derived out

The club owner sets **one** accent. At save-time the engine
(`Color_Engine::derive(accent, shell_bg, shell_ink)`) produces five CSS custom
properties your stylesheet consumes:

- `--color-accent` — the raw club accent. Decorative/light effects only.
- `--color-accent-ink` — text placed **on** an accent fill. *Best-effort* (better of
  black/white). On a **dark** shell this is handicapped — see below.
- `--color-accent-deep` — accent used **as text** on the shell. **AA-guaranteed**
  (≥4.5:1 vs `--color-bg`). Route all accent-coloured text/marks/rules through this.
- `--color-accent-wash` — a soft accent tint for fields/washes.
- `--color-accent-block` — the fill for large inverted blocks (banner, Home hero,
  ticker). It is your look's **own** `--color-ink` pulled 30% toward the accent, so it
  keeps your look's weight and polarity while carrying the club's colour, and it is
  guaranteed ≥4.5:1 against `--color-bg` — which means `--color-bg` is always a legible
  mark **on** it. Use it instead of `--color-ink` for any large block you would
  otherwise fill with flat ink. **Any mark on this block that carries meaning, indicates
  state, or must stay legible — text, focus rings, icons you rely on — must not use
  `--color-accent` or `--color-accent-deep`**: both derive from the same accent as the
  field, so they converge with it (a focus ring measured 1.48:1 before this rule
  existed). Route those through `--color-bg`. Purely decorative marks whose meaning is
  carried elsewhere may still use the raw accent — the ticker's separator dots do. A
  look opts in by referencing this token: Floodlight does not, because it fills those
  blocks with `--color-paper` instead.

### `accent_bears_text()` — the legibility switch

Return `true` if your look paints **text on a solid accent fill** (buttons, hero
highlight block, ticker label, win badge). Court Side + Members' House do. The admin
Setup screen then *rejects* a club accent that can't clear AA as ink-on-fill.

Return `false` for a **glow/outline-only** idiom where the accent never carries text
(Floodlight). Then the admin only requires `accent-deep` to be legible, which is
always achievable — so any accent hue is accepted. This is the correct choice for a
dark shell, where `--color-accent-ink` derives light-on-light and a solid accent CTA
would be illegible.

**Decide this early — it shapes your whole component idiom.**

### Fonts are self-hosted

The plugin no longer uses a font CDN. `fonts()` declares each family; the renderer
emits `@font-face` pointing at `assets/fonts/<stem>-<weight>.woff2`. For any font your
look introduces you must add the `.woff2` weight files under `assets/fonts/` plus an
OFL/license file under `assets/fonts/licenses/`. Existing self-hosted families you can
reuse for free: **Syne, Inter, Fraunces, Mulish, Bricolage Grotesque, Hanken
Grotesk**. Pick a genuinely different pairing to make the 4th look feel new.

```php
public function fonts(): array {
    return array(
        array( 'family' => 'YourDisplay', 'stem' => 'yourdisplay', 'weights' => array( 600, 700 ), 'display' => 'swap' ),
        array( 'family' => 'YourBody',    'stem' => 'yourbody',    'weights' => array( 400, 500, 600 ), 'display' => 'swap' ),
    );
}
```

**Hygiene trap (tested):** the *stylesheet* must not contain the font-family names
anywhere — not even in comments. Refer to them only as `var(--font-display)` /
`var(--font-body)`; write "display token" / "body token" in comments.

---

## 3. Site structure — 9 pages, 45 section slots

Every page is server-rendered PHP assembled from skin-agnostic `ch-*` section
renderers, each wrapped in a visibility toggle (`page/section`). Your look styles the
*hooks*; it never decides which sections appear. Full map:

| Page (slug) | Section slots (in order) |
|---|---|
| **home** | header · hero · quick_tiles · ticker · stats · sports · clubhouse · membership · activity · news · info · sponsors · social · footer |
| **about** | hero · history · values · committee · facilities · cta |
| **membership** | hero · why · tiers · detail · steps · faq · cta |
| **contact** | hero · form · directory · social |
| **login** | form |
| **sports** | hero · directory · cta |
| **teams** | hero · directory · cta |
| **events** | hero · upcoming · past · cta |
| **calendar** | hero · schedule · cta |

Collections behind the content: 6 CPTs — **sport, team, fixture, event, sponsor,
person** — feed the sports/teams/events/calendar/committee/sponsors sections. Your
look never reads them; it just styles the cards they render into.

Shared chrome on every page: a banner + nav header (`.ch-banner` / `.ch-nav`) with
Login/Join CTAs and a no-JS `<details>` mobile drawer, and a 4-column footer
(`.ch-footer`) with socials + newsletter + legal.

---

## 4. The `.ch-*` component inventory (style ALL of these)

Your stylesheet must cover every hook below — grouped by role. This is the complete
Court Side selector set (255 selectors); the Members' House and Floodlight sets match
it exactly, so parity is the acceptance bar.

**Shell / chrome**
`ch-skip` · `ch-main` · `ch-wrap` · `ch-sec` (+`--alt`) · `ch-sec__head/__title` (+`--sm`) ·
`ch-eyebrow` (+`--band`) · `ch-banner` (+`__in/__link`) ·
`ch-nav` (+`__in/__links/__link` (+`--active`) `/__cta/__burger/__burger-bars/__drawer` (+`-join/-login`) `/__disc`) ·
`ch-brand` (+`__mark/__logo`) · `ch-footer` (+`__grid/__h/__lede/__tagline/__form/__input/__social(s)/__link/__legal` (+`-link`)) ·
`ch-btn` (+`--accent/--ink/--ghost`) · `ch-link` · `ch-reveal`

**Hero family**
`ch-hero` (+`__title/__hl/__sub/__lede/__cta/__media/__pill/__pill-dot`) ·
`ch-hero-f` (filter hero: +`__title/__hl/__lede`) · `ch-filters` · `ch-filter` (+`--on`)

**Stats / tiles / ticker**
`ch-stats` (+`__in/__item` (+`--feature`) `/__value/__label`) ·
`ch-tiles(-sec)` (+`__tile/__arrow`) ·
`ch-ticker` (+`__viewport/__track/__item/__label/__dot/__pause` (+`-cb`) `/__ico-play/__ico-pause`)

**Cards / grids**
`ch-cards` · `ch-card` (+`__media/__scrim/__tag/__title/__sub/__body`) ·
`ch-scards` · `ch-scard` (stat card: +`__media/__chip/__title/__desc/__stats/__stat` (+`-l/-v`)) ·
`ch-news` (+`__card/__media/__meta/__tag/__date/__title`) ·
`ch-media` (+`--empty/__img`) · `ch-avatar`

**Membership / benefits / steps**
`ch-tiers(-sec)` · `ch-tier` (+`--pop/__name/__amt/__k/__cta/__feats/__feat`) ·
`ch-benefits` · `ch-benefit` (+`__dot/__title/__desc`) ·
`ch-steps` · `ch-step` (+`__num/__title/__desc`) ·
`ch-splits` · `ch-split__h/__list/__yes/__no` · `ch-policies` · `ch-policy__title/__desc`

**Bands / info / social / sponsors**
`ch-band(-wrap)` (+`--accent/--ink/__title/__lede`) ·
`ch-band-img` (+`__in/__media/__scrim/__title`) · `ch-image` band via `ch-band-img` ·
`ch-info` (+`__in/__body/__label/__link`) ·
`ch-social` (+`__in/__title/__lede/__links/__link/__icon`) ·
`ch-sponsors` (+`__tile`)

**People / timeline / FAQ**
`ch-people` · `ch-person` (+`__avatar/__name/__role/__email`) ·
`ch-timeline` · `ch-milestone` (+`__year/__title/__desc`) ·
`ch-faq` (+`__item/__q/__a/__mark`) · `ch-eyebrow`

**Fixtures / results / events / calendar**
`ch-fx(-list)` (+`__body/__match/__comp/__date/__time`) ·
`ch-res(-list)` (+`__teams/__score/__date`) ·
`ch-badge` (+`--w/--l/--d`) — win/loss/draw (watch light-vs-dark legibility!) ·
`ch-tabs__bar/__btn` (+`--on`) `/__panel--off` (activity tabs, PE-JS) ·
`ch-events` · `ch-event` (+`__date/__tag/__title/__meta/__detail/__cta`) ·
`ch-evt(-grid)` (+`__date/__tag/__title/__meta/__detail`) ·
`ch-archive` (+`__row/__date/__tag/__title`) ·
`ch-cal` (+`__month/__rows/__row/__body/__date/__match/__comp/__mlabel/__detail/__soon`)

**Forms / auth / contact**
`ch-field` (+`__label/__input`) ·
`ch-auth(-wrap)` (+`__title/__lede/__form/__row/__remember/__forgot/__submit/__alt` (+`-link`)) ·
`ch-contact` (+`__h/__info/__lines/__link/__connect/__social/__form/__map`)

**Behaviour you must preserve** (carried by shared markup — style, don't break):
skip link → `<main>`, `:focus-visible` ring (use `var(--color-accent-deep)`), no-JS
`<details>` nav drawer, CSS-marquee ticker + no-JS pause, scroll-reveal + hero
entrance (content fully visible with **no JS** and under `prefers-reduced-motion`),
`role="list"` grid semantics, gradient/initials placeholders for empty image/avatar
slots, and **no horizontal overflow from 320px up**.

---

## 5. Files to create + wiring + tests

1. **`includes/looks/class-<slug>.php`** — implement all 7 interface methods (mirror
   `class-court-side.php`): `slug/name/description/tokens/fonts/stylesheet/accent_bears_text`.
2. **`assets/looks/<slug>.css`** — full coverage of the inventory above; accent only
   via `var(--color-accent*)`, fonts only via `var(--font-*)`.
3. **`assets/fonts/`** — add any new `<stem>-<weight>.woff2` files + a license under
   `assets/fonts/licenses/` (skip if you reuse existing families).
4. **Register the look** in two places: `includes/frontend/class-frontend.php`
   (`$registry->register( new Blueworx_Clubhouse_<Slug>() )`) and
   `preview/index.php`; add a `require_once` in `includes/bootstrap.php`.
5. **Tests** (TDD, mirror `CourtSideTest` / `*StylesheetTest` / `PreviewRenderTest`):
   identity (slug/name/stylesheet path), token values + required-key-set + **no
   accent key**, fonts array shape, a **compose** test asserting `accent-deep` clears
   AA vs `--color-bg`, stylesheet hygiene (styles the core hooks; accent only via
   `var(--color-accent*)` — assert the other looks' accents `#c6f24e`/`#7a2f3a`/`#f7a70a`
   are absent; fonts only via `var(--font-*)`; font-family names absent from the CSS),
   and a preview-render test for `?look=<slug>`.
6. **Version + changelog** — minor bump in `blueworx-labs-clubhouse.php` (header +
   `BLUEWORX_LABS_CLUBHOUSE_VERSION` const) and `package.json`; matching
   `CHANGELOG.md` entry. (Repo is at **v0.21.0** — a 4th look would be **v0.22.0**.)

**Verify:** `composer test` green; `php -S localhost:8124` from the plugin root, visit
`/preview/?look=<slug>` across `?page=home|about|membership|contact|login|sports|teams|events|calendar`;
toggle the accent switcher (whole page re-themes, accent-deep stays AA on every hue);
confirm no `.ch-*` hook is left unstyled and no horizontal overflow 320px→desktop.

---

## 6. Design prompt (paste into Claude Design / a design tool)

> Design a **4th visual "look"** for a reusable Sports Club website template. It must
> keep the **same structure** as the existing looks — same pages and sections, same
> components — but a **distinct personality**. Only the styling changes.
>
> **Pages & sections:** Home (banner+nav header, hero with highlighted word, quick
> tiles, scrolling ticker, stat row, sports card grid, clubhouse image band,
> membership tier row, fixtures/results/events tabs, news cards, info strip, sponsor
> logos, "follow us" social block, 4-col footer), About (hero, history timeline,
> values grid, committee people grid, facilities, CTA band), Membership (hero,
> benefits, pricing tiers with one "popular", included/excluded detail, join steps,
> FAQ accordion, CTA), Contact (hero, form, directory, socials), Login, Sports
> (filter hero, sport cards), Teams (team stat cards), Events (upcoming + past grids),
> Calendar (month-grouped fixtures).
>
> **Theming rules:** the club owner picks ONE accent colour — everything else is a
> fixed neutral "shell" (background, paper/surface, primary + soft text, hairline,
> three corner-radii, a display font + a body font). Never pure black or white; keep
> it warm. The accent must stay legible as text on the shell.
>
> **Make it distinct from these three** — differ on type pairing, light/dark shell,
> radius scale, and how the accent is used:
> - Court Side: bright near-white, big rounded 32px shapes, bold accent blocks, Syne.
> - Members' House: warm parchment, tiny crisp radii, hairline rules, accent as a thin
>   underline, Fraunces serif.
> - Floodlight: dark warm-ink night look, 16px radii, accent spent as glow/light.
>
> Pick a fresh register (e.g. a light look with sharp 0–4px radii and a mono/grotesk
> pairing; or a second dark look with a completely different accent idiom). Show the
> Home page and one inner page (Membership or About), light and dark states as
> relevant, at desktop and mobile.

---

*This brief mirrors the shipped specs `docs/superpowers/specs/2026-07-10-members-house-base-look-design.md`
and `2026-07-11-floodlight-base-look-design.md` — read either for a worked example of a
completed re-skin.*
