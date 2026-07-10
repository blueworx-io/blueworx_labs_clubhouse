# ClubHouse — Base Look Theming & Concrete Site Design

**Date:** 2026-07-10
**Status:** Approved (design direction), pending implementation plans
**Supersedes for phase 2:** the "concrete pages/sections come in a second spec" placeholder in
`2026-07-09-sports-club-template-engine-design.md`. This spec is the second spec.
**Builds on:** the engine core (`Registry`, `Storage`/`Options_Storage`, `Content_Store`,
`Visibility`) delivered by `2026-07-09-engine-core-content-foundation.md`.

---

## 1. Purpose & context

`blueworx_labs_clubhouse` is a **reusable Sports Club Template Site** WordPress plugin: one
codebase, many separate WordPress installs (one per club, agency model — not multisite). The
engine (phase 1) is built. This spec covers **phase 2**: the concrete site and the design system
that lets every club look distinct and premium without forking code.

The design direction was chosen from three live landing-page mockups (`Members' House`,
`Floodlight`, `Court Side`) reviewed against five reference sites the client admires
(perk.com, padlx.com.au, a golf-app site, sohohouse.com, psasquashtour.com). The through-line
across every reference: a **premium neutral shell + one punchy accent**, photography-led,
generous whitespace, strategic uppercase labels for athletic authority.

### Design goals (client-stated)
- Feel **premium and fun**, not "sports-club generic."
- Move away from the retired v1 look (navy `#041632` + orange, Archivo Black condensed caps).
- **Not "black-focused"** — colour must work across many clients running different palettes.
- Owner must be able to **visually track progress on localhost**.
- Everything renamed to **ClubHouse** (was "Marlow Community SC").
- **Upgrade the stock/demo imagery.**

---

## 2. Core architecture: structure and skin are separable

The three mockups share **identical bones** — the same nav, hero, stat strip, sports grid,
membership band, footer. Only a **presentation layer** differs: fonts, the neutral shell, corner
radii, component personality, imagery treatment, layout rhythm. Content and page/section
*structure* are the same across all three.

Therefore we build **one engine, one content model, one set of pages/sections**, plus swappable
**Base Look packs** that sit on top.

```
Base Look & Feel   → owner picks A / B / C     (sets fonts, shell, radii, personality, imagery treatment)
Accent + branding  → owner sets ONE brand accent, logo, club name
Content            → owner fills pages / sections / collections
```

Each layer is independent of the layers below it. Three consequences, all confirmed with the
client:

1. **Base Look owns its fonts and shape language.** Owners pick a look + colour + logo — not
   fonts. Every club is a complete, art-directed identity, never a half-configured template.
   (This replaces the earlier "owner picks 1 of 5 font presets" idea with something stronger.)
2. **Re-skin is a first-class goal.** Because rendering is skin-agnostic, switching a live site
   from A→B is a setting change with **zero content re-entry**. The discipline this imposes:
   *no section or template may hard-code look-specific assumptions* (fonts, radii, colour
   literals, imagery treatment) — those come only from Base Look tokens.
3. **Ship one look first.** The engine supports N looks from day one, but **Court Side (C)** is
   built end-to-end (pages, admin, every section, imagery) as the reference look. **Members'
   House (A)** and **Floodlight (B)** follow as re-skins once the engine is proven.

### Non-goals (v1)
- No second brand accent per club (see §4). No multisite. No page-builder editing. No
  JS-framework hydration. No per-section arbitrary font overrides.

---

## 3. Base Look pack — the unit of theming

A Base Look pack is a **registered module** (mirrors the existing registry pattern) that provides:

| Part | What it is |
|---|---|
| **Identity** | slug (`court-side`), display name, description, preview thumbnail |
| **Token set** | CSS custom properties for the fixed neutral shell, type scale, radii, spacing |
| **Fonts** | the pack's font pairing + how they load (see §6) |
| **Stylesheet** | one look-specific CSS file consuming the tokens + shared structural CSS |
| **Imagery treatment** | how media is rendered (natural / duotone / scrim) as CSS, not baked into content |

Contract: a Base Look pack **only** supplies presentation. It may not add or remove sections,
change content shape, or read content it isn't given. Swapping the active pack changes only which
token set + stylesheet + fonts are emitted.

Rendering model: shared **structural CSS** (layout, grid, component skeletons using semantic
custom properties like `--color-accent`, `--radius-lg`, `--font-display`) + the **active pack's
stylesheet** (which sets those properties and adds personality). Sections output semantic markup
and semantic classes; they never reference a pack directly.

### The three packs
- **C · Court Side (reference, built first)** — bright playful-premium. Syne + Inter. Near-white
  warm canvas `#faf8f3`, soft warm ink `#1c1b18`. Rounded (pills, 16–32px). Accent used **boldly
  on large blocks**. Natural bright imagery.
- **A · Members' House** — warm editorial (Soho House). Fraunces serif + Inter. Warm bone canvas.
  Soft radii. Accent as **quiet punctuation**. Imagery-led, natural warm.
- **B · Floodlight** — dark athletic prestige (PSA squash). Bricolage Grotesque + Inter. Rich
  **warm-ink** canvas (not pure black); accent **glows**. Grayscale→accent duotone imagery.

---

## 4. Colour model (the multi-client answer)

The single hardest requirement — arbitrary client colours must always look intentional and stay
legible. Solution: **one brand accent per club; everything else derived.**

- The **neutral shell is fixed per Base Look** (Court Side = warm near-white + soft ink). Clubs
  never touch it. This is why nothing is "black-focused": the base is light and warm.
- The club sets **one accent** (`--color-accent`). From it the engine derives:
  - **`--color-accent-ink`** — text/glyphs placed *on* the accent fill. The **mathematically best
    case**: the better-contrasting of dark-ink vs white (black/white are the contrast extremes, so
    nothing beats them on a fixed fill). AA-guaranteed for saturated brand colours; a *desaturated
    mid-luminance* accent (e.g. `#767676`) cannot reach AA with any text colour, so such accents
    are **rejected at accent-selection time in the admin UI** (see §10 admin flow) rather than
    silently shipped illegible. This boundary is tested (AA asserted across the saturated hues).
  - **`--color-accent-deep`** — accent-*as-text* on the shell: blended toward whichever pole
    (black/white) contrasts more with the shell until it clears AA. **Guaranteed** for the full
    hue range on light, dark, and mid-tone shells (re-skin safety) — worst-case pole contrast
    ≈4.58 ≥ 4.5.
  - **`--color-accent-wash`** — a faint tint for subtle highlight zones.
- Derivation is **pure**; the composed `:root` string is cached by the WP wrapper (later plan) and
  inlined, so there is no per-request colour math — cache-friendly.
- **Guardrail:** the derivation must produce AA-contrast pairings for the full hue range. Tested
  units: `accent-deep` AA across light/dark/mid-tone shells × 7 hues; `accent-ink` AA across the
  saturated hues; ink dark/light selection by luminance.

The "second colour" every club effectively gets is the shell's warm ink — deliberate, not a
limitation. A true second brand accent is a **future** enhancement, explicitly out of v1 scope.

---

## 5. Concrete site — pages, sections, collections

Derived from the client's mockup (`Home/About/Sports/Teams/Membership/Events/Calendar/Contact`
plus `SiteHeader`/`SiteFooter`). Renamed throughout to **ClubHouse**.

### Pages & their sections
| Page | Sections (in order) |
|---|---|
| **Home** | Hero · stat strip · quick-access tiles · sports grid · membership band · what's-on (events) · from-the-clubhouse (blog) · sponsors · footer CTA |
| **About** | Story ("from one pitch to nine sports") · values · committee · get-involved CTA |
| **Sports** | Section directory · "not sure which section?" helper |
| **Teams** | Teams grouped by sport · "pull on the badge" join CTA |
| **Membership** | Tiers · what's included · what's not · trials/juniors/family · how to join · FAQ · CTA |
| **Events** | Upcoming / recent events · newsletter signup |
| **Calendar** | Month view of fixtures & results |
| **Contact** | Contact directory (who to contact) · contact form |

### Data model
- **Collections → custom post types:** Sports/Sections, Teams, Fixtures/Results, Events,
  Sponsors, Committee members.
- **Singular content → autoloaded WP options** (via the existing `Content_Store`): hero copy,
  stats, values, membership tiers, FAQ, contact details, about story, newsletter/CTA copy.
- **Blog** → native WordPress posts ("from the clubhouse").
- **Show/hide** of any page or section → the existing `Visibility` registry option.
- **Header/Footer** → shared shell partials, skin-agnostic, driven by branding + nav options.

All content is stored **raw** (`mixed`); sanitisation/escaping lives in the admin (input) and
render (output) paths, consistent with the phase-1 decision.

---

## 6. Fonts & imagery

**Fonts.** Each Base Look owns its pairing. Court Side = Syne (display) + Inter (body); A =
Fraunces + Inter; B = Bricolage Grotesque + Inter. Loading strategy to be finalised in the plan,
default assumption: **self-host/vendor the fonts locally** for speed and privacy (the mockups use
the Google CDN for convenience only). Fonts load per active pack, not all at once.

**Imagery.** A defined **media slot** in the content model, never hard-coded. Owners upload real
club photography; until then, tasteful placeholders stand in. The **Base Look owns the treatment**
(Court Side natural/bright; Floodlight grayscale→accent duotone; Members' House warm natural), so
the same uploaded photo takes on each look's character. Per-section conditional asset loading
keeps pages fast.

---

## 7. Performance & engine mapping (unchanged principles)

- Server-rendered PHP, **autoloaded options**, inline `:root` tokens, per-section conditional
  asset loading, cache-friendly, no JS-framework hydration. Frontend must be **fast**.
- Base Look packs register via the existing registry pattern; branding + accent + look selection
  are autoloaded options resolved to inline CSS variables.
- Integration seam for third-party plugins (SureCart/SureDash/SureForms/SureRank/SureDonation/
  SureCookie/LatePoint) remains architected-but-unscoped; detected present/absent gracefully.

---

## 8. Local preview / progress tracking

The client must watch progress on localhost. The static mockups already run under `php -S`. For
the plugin itself, the plan will stand up a **repeatable local WordPress preview** (documented
one-command start) so any in-progress look/section is viewable live. This standing preview also
becomes the basis for resolving the outstanding CI **staging-URL blocker** (branch protection's
Playwright step still points at a placeholder).

---

## 9. Testing strategy

- **PHPUnit (existing harness):** Base Look registry (register/list/resolve active), colour
  derivation (accent → ink/deep/wash within AA contrast across the hue range), content model
  read/write via `Content_Store`, visibility resolution, CPT registration.
- **Playwright (CI guardrail):** render Home under Court Side; assert structure, that the active
  pack's tokens/fonts are emitted, and that swapping accent re-themes without layout change.
  Re-skin test: same content renders under two packs.
- Skin-agnostic discipline is itself testable: a render under pack A and pack B must produce
  identical DOM structure, differing only in emitted tokens/stylesheet/fonts.

---

## 10. Build sequencing (feeds writing-plans)

Indicative decomposition (finalised by the planning step):

1. **Base Look framework** — pack interface, registry, active-look resolution, token/stylesheet
   emission, skin-agnostic render contract. (No visuals yet.)
2. **Colour engine** — accent → derived tokens, save-time derivation + storage, contrast tests.
3. **Court Side pack** — tokens, Syne+Inter, stylesheet, imagery treatment.
4. **Concrete sections & pages** — the §5 inventory as registered sections/pages, semantic markup.
5. **Collections** — the six CPTs + their admin.
6. **Admin setup flow** — Base Look picker → accent/branding → content, bespoke UI (no ACF).
   Includes **accent-contrast validation**: warn/reject an accent when neither black nor white
   text clears AA on the fill (a desaturated mid-luminance colour), so `accent-ink` is never
   silently illegible (see §4). Also validates the accent is a real hex (the engine falls back to
   `#000000` on malformed input — the UI should catch it first).
7. **Local preview harness** + Playwright wiring; then A & B packs as re-skins.

> **Status — framework built (0.3.0).** Sequencing steps 1–2 are implemented on branch
> `base-look-theming-framework` (plan `docs/superpowers/plans/2026-07-10-base-look-theming-framework.md`):
> Base Look interface + registry, colour engine (`derive()` with AA-guaranteed `accent-deep` on
> light/dark/mid shells and best-effort `accent-ink`), branding store, and `:root` composition —
> 76 PHPUnit tests, no WP runtime. Steps 3–7 remain.

---

## 11. Open items (tracked, not blocking)

- **Autoloader vs `require_once`** (carried from phase 1): decide before adding the pack/section
  base classes whether to adopt convention-based `spl_autoload_register` (no runtime Composer).
- **CI staging-URL blocker:** no branch merges to `main` until the guardrails Playwright step has
  a real preview URL. §8 preview harness is the intended resolution.
- **Second brand accent:** future enhancement, out of v1 scope.
- **Font hosting:** confirm self-host vs CDN in the framework plan.
