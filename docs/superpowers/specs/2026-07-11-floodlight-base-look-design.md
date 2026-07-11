# Floodlight — third Base Look (design)

**Date:** 2026-07-11
**Branch:** `floodlight-look` (off `base-look-theming-design`)
**Status:** Approved, ready for implementation plan

## Summary

Floodlight is the **third and final planned Base Look** for the Sports Club Template — a
bold, night-match re-skin and the **dark counterpart** to the two light looks (Court Side,
Members' House). It reuses the existing engine, the skin-agnostic `ch-*` section renderers,
and all page content unchanged; it adds only a new `Base_Look` pack (a PHP class + a full
stylesheet) that restyles every `ch-*` hook in a dark, lit-edge, accent-**glow** idiom.

This is the project's third exercise of the "re-skin is a first-class goal" principle, and
the first on a **dark shell**. If a section or renderer needs to change to make this look
work, that is a bug in the section (a skin-specific assumption leaked into structure), not
a task for this plan.

**Court Side is loud** (Syne, near-white, big 32px radii, bold accent blocks).
**Members' House is quiet** (Fraunces + Mulish, warm parchment, hairline rules).
**Floodlight is dark and lit** (Bricolage Grotesque + Hanken Grotesk, warm-ink canvas,
bold-modern 16px radii, the accent spent as *light* — lit edges, glow halos, deep-text,
ambient wash — never as a solid fill you place text on).

## Branch, version, and relationship to Members' House

Floodlight branches off `base-look-theming-design` (which is at **v0.8.0** and contains
only Court Side), exactly mirroring how Members' House (PR #3) was built. It is therefore a
**sibling** of Members' House, not stacked on it — Members' House lives on its own unmerged
branch (`members-house-look`, v0.9.0). Consequences, all deliberate:

- **Version bumps to `0.10.0`** (a minor bump). 0.9.0 is already claimed by the Members'
  House branch, so Floodlight skips to 0.10.0 and the two sibling PRs don't collide on the
  version when both merge into `base-look-theming-design`.
- The Floodlight **preview registers Court Side + Floodlight only**. Members' House is not
  present on this branch; it arrives via its own PR. (When both PRs are merged, a later
  commit or the merge resolution wires all three into the preview.)
- The two PRs **will conflict** in `preview/index.php` and `includes/bootstrap.php` (both
  add a look require + registration). The human resolves that at merge — expected, not a
  defect.
- PR target: `base-look-theming-design` (same as PR #3), still gated from `main` by the CI
  staging-URL guardrail.

## Non-goals

- No changes to `Blueworx_Clubhouse_Sections`, `Page_Renderer`, `Color_Engine`,
  `Branding`, `Theme_Css`, `Visibility`, or any page composition.
- **No engine change.** `Color_Engine::derive()` is untouched. Floodlight *sidesteps* the
  known dark-shell `accent-ink` limitation (see the accent decision below) rather than
  triggering or fixing it.
- No admin UI for choosing the look (later admin-setup plan). The preview gets a throwaway
  switch only.
- No new runtime dependency. Fonts load from the same Google-CDN path the other looks use.
- This is the last of the three planned looks; no fourth look is in scope.

## The accent-on-dark decision (why no engine change, and why accent is never a text-bearing fill)

The engine derives four accent tokens from a club's single accent against the look's shell
(`--color-bg`, `--color-ink`), via `Theme_Css::compose()` →
`Color_Engine::derive($accent, $shell_bg, $shell_ink)`. On Floodlight's **dark** shell,
two facts drive the whole idiom:

1. **`--color-accent-deep` (accent-as-text) is AA-guaranteed on dark — use it for all
   accent text.** `derive()` blends the accent toward whichever pole contrasts more with
   `--color-bg`; on a dark shell that pole is white, so `accent-deep` is the club accent
   lightened toward white until it clears WCAG AA (4.5:1) against `--color-bg`. Floodlight
   routes **all** accent-coloured text, marks, rules, numerals and active-underlines
   through `accent-deep`. This also disposes of the deferred "raw `var(--color-accent)`
   used as text is low-contrast" hazard: Floodlight never uses raw accent as text.

2. **`--color-accent-ink` (text placed *on* an accent fill) is handicapped on dark — so
   the accent is never a text-bearing fill.** `derive()` computes `accent-ink` as the
   better of *shell-ink vs white*. On the dark shell, shell-ink is a light bone, so **both
   candidates are light** — a bright "glow" accent fill (exactly Floodlight's signature)
   would get illegible light text. A solid, text-bearing accent CTA is therefore impossible
   on the dark shell without changing the engine.

**Decision — glow-only accent, zero engine change.** Floodlight uses the accent *only* as:
outline, `accent-deep` text, soft box-shadow / text-shadow glow, ambient radial glow, and
`accent-wash` tint — **never** as a solid fill it places text on. `--color-accent-ink` is
simply never leaned on for legibility. This keeps Floodlight a pure re-skin (the whole
point of the third look), matches the "accent glows" brief literally, and carries the least
risk (Court Side and Members' House colour output stay untouched). A dull client accent
degrades gracefully — its literal glows are faint, but all accent *text* stays legible via
`accent-deep`.

The dark shell also hands us a **solid punch for free**: because `--color-ink` is a light
warm bone, `.ch-btn--ink` becomes a bright bone button with dark (`--color-bg`) text —
fully AA-legible. So Floodlight has both a solid punch (bone button) and a glow (accent
button). The engine's `accent-ink`-on-dark limitation stays documented as deferred;
Floodlight avoids it rather than resolving it.

## Hard authoring rules (the contract that keeps this legible AND a pure re-skin)

- Accent as **text / mark / rule** → *always* `var(--color-accent-deep)`. Never raw
  `var(--color-accent)` as load-bearing text.
- Accent as a **fill** → decorative only, and **never** bears text. No
  `background:var(--color-accent)` with text on top anywhere (buttons, tags, ticker label,
  win badge, tier dots-with-text, etc. all restyle to the glow idiom).
- Raw `var(--color-accent)` → **light effects only**: glow box-shadows, ambient radial
  gradients, dot indicators, hairline rules, duotone image overlays.
- **No `background:var(--color-ink)` panels.** The light looks paint their "feature" fields
  (ticker, info strip, contact panel, fixtures list, featured stat, band--ink) with
  `--color-ink`; on the dark shell `--color-ink` is bone (light), which would invert those
  into light boxes. Floodlight paints them as dark `--color-paper` surfaces with light ink
  text and a lit accent rule/halo instead — keeping the whole page in the night register.

## Architecture

A Base Look pack implements `Blueworx_Clubhouse_Base_Look`
(`includes/theme/interface-base-look.php`): `slug()`, `name()`, `description()`,
`tokens()`, `fonts()`, `stylesheet()`. It supplies **presentation only** — never adds or
reads content, never emits accent tokens (those are engine-derived per club at save-time
and merged in by `Theme_Css::compose()`).

`tokens()` MUST include `--color-bg` and `--color-ink`, and to satisfy the shared section
markup defines the same token set the other looks do: `--color-bg`, `--color-paper`,
`--color-ink`, `--color-ink-soft`, `--color-line`, `--radius-xl`, `--radius-lg`,
`--radius-md`, `--font-display`, `--font-body`.

The stylesheet consumes those custom properties plus the engine's
`--color-accent`/`-ink`/`-deep`/`-wash`. The accent is *always* referenced through
`var(--color-accent*)` so a club re-themes by swapping one colour — identical contract to
`court-side.css` / `members-house.css`.

## Type

- **Display — Bricolage Grotesque** (characterful contemporary grotesque, athletic display
  register): hero title, section titles, card/tier/step/benefit titles, stat values,
  milestone years, band titles, brand wordmark, auth title, fixture times/scores. Weights
  500–800 for bold night-match energy.
- **Body/UI — Hanken Grotesk** (warm humanist grotesque, superb small-size legibility on
  dark): body copy, ledes, nav links, form fields, labels, badges, meta, buttons. Weights
  400–700. A third distinct body across the three looks (vs Inter / Mulish).
- `fonts()` returns Bricolage Grotesque then Hanken Grotesk, each with the weights actually
  used and `display: swap`.
- **Stylesheet-hygiene trap:** the stylesheet must NOT contain either font-family name —
  not even in a comment. Fonts come only via `var(--font-display)` / `var(--font-body)`.
  Use "display token" / "body token" phrasing in comments (the hygiene test asserts the
  substrings `Bricolage` and `Hanken` are absent from the CSS).

## Neutral shell tokens (dark, warm — never pure black/white)

| Token | Value | Note |
|---|---|---|
| `--color-bg` | `#14110b` | warm ink — the night canvas |
| `--color-paper` | `#1e1913` | raised dark surface — cards sit *above* the dark and catch light |
| `--color-ink` | `#f3ede0` | warm bone — light text colour *and* the solid "punch" button fill (~16:1 on bg) |
| `--color-ink-soft` | `#a99f8c` | warm taupe secondary text (~7:1 on bg — AA) |
| `--color-line` | `#302a20` | warm dark hairline, visible on the canvas |
| `--radius-xl` | `16px` | bold-modern — enough body to carry a glow halo (Court Side 32/24/16, MH 10/7/4) |
| `--radius-lg` | `11px` | |
| `--radius-md` | `7px` | |
| `--font-display` | `'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif` | |
| `--font-body` | `'Hanken Grotesk', ui-sans-serif, system-ui, sans-serif` | |

No accent tokens appear in `tokens()` — asserted by test.

## Component idiom (lit edges + ambient glow; all accent text via `accent-deep`)

The accent behaves like light, never a solid text-bearing fill. Concrete per-hook
treatment, all achievable in CSS against the existing markup:

- **Eyebrow** (`.ch-eyebrow`): a letter-spaced uppercase body-token label in `accent-deep`,
  preceded by a short glowing `accent-deep` rule (accent bar + soft accent box-shadow). No
  filled pill.
- **Buttons** (`.ch-btn`): `--radius-md`. `--accent` = transparent/wash fill + solid
  `var(--color-accent)` border + `accent-deep` text + a soft outer glow (`box-shadow` in
  accent); hover intensifies the glow and lifts ≤2px. `--ink` = bright bone fill
  (`--color-ink`) with dark (`--color-bg`) text — the solid punch. `--ghost` = hairline
  border, ink text, lights toward the accent on hover. `prefers-reduced-motion` disables
  transforms (static box-shadow glows stay).
- **Hero** (`.ch-hero`, `.ch-hero__hl`): a large low-opacity radial accent glow bleeds from
  a top corner; the emphasised word **glows** — `accent-deep` text with a soft accent
  `text-shadow`. No rotation, no filled block. Staggered rise-in preserved.
- **Cards / tiers / stats / benefits / steps / events / sponsors** (`.ch-card`,
  `.ch-tier`, `.ch-stats__item`, `.ch-benefit`, `.ch-step`, `.ch-evt`,
  `.ch-sponsors__tile`, `.ch-news__card`): flat `--color-paper` panels, `--color-line`
  hairline, bold-modern radius, display-token titles. Hover lights the border toward the
  accent and raises a soft accent halo box-shadow (reduced-motion safe). The featured stat
  (`.ch-stats__item--feature`) and popular tier (`.ch-tier--pop`) get a lit `accent-deep`
  top-rule + a persistent low halo.
- **Feature fields** (`.ch-ticker`, `.ch-info`, `.ch-contact__info`, `.ch-fx-list` /
  `.ch-res-list`, `.ch-banner`): authored as **dark raised panels** (`--color-paper` or a
  shade near it) with light ink text, `accent-deep` labels/links, and a subtle inset/edge
  accent glow — *not* the light looks' bright `--color-ink` fill. White/bone text on these
  stays legible.
- **Overlay sport cards** (`.ch-card`) & **image bands** (`.ch-band-img`): photo + dark
  scrim (already dark) + light display title; the accent tag is `accent-deep` text on a
  dark chip (not an accent fill).
- **Bands** (`.ch-band--accent`, `.ch-band--ink`): `--accent` = an `accent-wash` field with
  a glow ring and `accent-deep` heading; `--ink` = a deep `--color-paper` field with a lit
  edge. Both keep bone text legible; neither is a saturated accent fill.
- **Rule-based sections** (`.ch-timeline`, `.ch-faq`, `.ch-splits`, `.ch-milestone`):
  hairline-driven; marks (FAQ +/–, split yes/no, tier feature dots, step numerals, years)
  are fine `accent-deep` details, some with a faint glow. This is where the lit-hairline
  idiom reads strongest.
- **Imagery — grayscale→accent duotone hint:** `.ch-media__img` is desaturated
  (`filter: grayscale(1) contrast(~1.04)`) and `.ch-media:not(.ch-media--empty)::after`
  lays a low-opacity accent-tinted overlay (`mix-blend-mode`), giving photos a restrained
  duotone character that re-themes with the accent. Empty placeholders keep their own
  `.ch-media--empty::after` icon (different selector, no collision) over a dark gradient
  with an `accent-wash` bloom.
- **Nav / footer / ticker chrome**: hairline separators, body-token links, `accent-deep`
  active-link underline that glows, ink/bone brand mark; ambient dark at the top of the
  page.
- **Media placeholders** (`.ch-media--empty`, `.ch-avatar`): dark accent-tinted gradients
  re-toned for the dark shell, with the quiet photo icon; image-band placeholders stay dark
  so light headings remain legible.

### Preserved behaviour (carried by the shared section markup, must still work)

- Skip link, `<main>` landmark, one branded `:focus-visible` indicator
  (`var(--color-accent-deep)` — AA on the dark shell).
- Mobile/tablet `<details>` nav drawer; no-JS operation throughout.
- Ticker CSS marquee + no-JS pause control; `prefers-reduced-motion` fallbacks.
- Scroll-reveal / hero entrance (content fully visible without JS and under reduced
  motion). Any *pulsing* glow is gated by `prefers-reduced-motion`; static box-shadow glows
  stay.
- `role="list"`/`listitem` grid semantics.
- Gradient/initials placeholders for empty image/avatar slots, re-toned to the dark shell
  and accent.

## Files

1. **`includes/looks/class-floodlight.php`** — `Blueworx_Clubhouse_Floodlight` implementing
   the interface. Slug `floodlight`, name `Floodlight`, a one-line description, the tokens
   table above, the fonts array, stylesheet path `assets/looks/floodlight.css`. Mirrors
   `class-court-side.php` in shape.
2. **`assets/looks/floodlight.css`** — the full look stylesheet, parallel in coverage to
   `court-side.css` (every `ch-*` hook, the spacing/flow scale, reveal/skip/focus rules,
   all media queries), authored in the dark lit-edge/glow idiom above. Same accent contract
   (`var(--color-accent*)` only; no hardcoded club colours; no font-family names).
3. **`preview/index.php`** — register `court-side` + `floodlight` on the registry; change
   `blueworx_clubhouse_preview_palettes()` to take the active look and derive swatches from
   its shell (if not already so on this branch); read `?look=` (sanitised to `[a-z-]`,
   default/fallback `court-side`), `set_active()` it; add a small look toggle covering the
   registered looks. (Only Court Side and Floodlight exist on this branch; Members' House
   arrives via its own sibling PR.)
4. **`includes/bootstrap.php`** — add a `require_once` for `looks/class-floodlight.php`
   alongside the existing Court Side require.
5. **Version + changelog** — bump to **`0.10.0`** in `blueworx-labs-clubhouse.php` (Version
   header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json`, with a matching
   `CHANGELOG.md` entry.

## Testing

Mirror the Court Side / Members' House tests (`CourtSideTest`, `*StylesheetTest`,
`PreviewRenderTest`), TDD:

- **Identity:** slug `floodlight`, name `Floodlight`, stylesheet path
  `assets/looks/floodlight.css`, non-empty description.
- **Tokens:** `--color-bg` = `#14110b`, `--color-ink` = `#f3ede0`, `--color-paper` =
  `#1e1913`, `--color-ink-soft` = `#a99f8c`, `--color-line` = `#302a20`; `--font-display`
  contains `Bricolage Grotesque`, `--font-body` contains `Hanken Grotesk`; radii `16px` /
  `11px` / `7px`; the full required key set is present; **no `--color-accent*` key** appears
  in `tokens()`.
- **Fonts:** families are exactly `['Bricolage Grotesque', 'Hanken Grotesk']`; each entry
  has `family`/`weights`/`display`, weights non-empty.
- **Compose:** registering the look and setting an accent yields a `Theme_Css::compose`
  result carrying the dark shell tokens plus derived `--color-accent*` (incl. `-ink`,
  `-deep`), and the derived `--color-accent-deep` clears AA (≥4.5:1) against `--color-bg` —
  the load-bearing legibility guarantee, asserted directly.
- **Stylesheet hygiene** (mirror `CourtSideStylesheetTest` / `MembersHouseStylesheetTest`):
  the stylesheet exists; styles the shell sections (`.ch-nav`, `.ch-hero`, `.ch-stats`,
  `.ch-footer`, `.ch-btn`, `.ch-faq`, `.ch-tiers`, `.ch-auth`); references the accent
  **only** via `var(--color-accent*)` — asserts the demo default accent `#f7a70a` and the
  other looks' accents (`#c6f24e`, `#7a2f3a`) are **absent**; uses fonts **only** via
  `var(--font-display)` / `var(--font-body)`, and the substrings **`Bricolage`** and
  **`Hanken`** must not appear anywhere in the stylesheet, *including comments*.
- **Preview render:** `?look=floodlight` produces a document that links
  `assets/looks/floodlight.css` and both font families (`family=Bricolage%20Grotesque`,
  `family=Hanken%20Grotesk`) and carries the dark shell token `#14110b` in the emitted
  `:root`; the default (no `?look=`) still renders Court Side and **not** Floodlight.

All existing tests must stay green (no section/renderer changes).

## Verification

- `composer test` green (existing + new).
- `php -S localhost:8124` from plugin root; visit `/preview/?look=floodlight` across
  `?page=home|about|membership|contact|login`: warm-ink dark shell, display-token headings
  / body-token copy, bold-modern radii, lit-edge accents and ambient glows, solid bone
  punch button, no horizontal overflow 320px→desktop.
- Toggle the accent switcher: whole page re-themes; `accent-deep` text/marks stay AA on the
  dark shell across every preset hue; glows re-tint.
- Confirm Court Side is visually unchanged (default look, no regressions).
- Confirm no `.ch-*` hook is left unstyled (the Floodlight selector set matches Court
  Side's).

## Open follow-ups (out of scope here)

- Runtime WP registration + look choice in admin (admin-setup / WP-render plans).
- Font self-hosting (shared across all three looks; later).
- The engine `accent-ink`-on-dark limitation stays **deferred**: if a future look wants a
  *solid* accent fill with legible text on a dark shell, `Color_Engine::derive()` should
  compute `accent-ink` from the true black/white poles rather than shell-ink vs white.
  Floodlight does not need it (glow idiom), so it is not done here.
- Wiring all three looks into a single preview happens when the sibling PRs merge into
  `base-look-theming-design` (merge resolution or a follow-up commit).
