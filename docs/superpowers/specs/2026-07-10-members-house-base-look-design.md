# Members' House — second Base Look (design)

**Date:** 2026-07-10
**Branch:** `members-house-look` (off `base-look-theming-design`)
**Status:** Approved, ready for implementation plan

## Summary

Members' House is the **second Base Look** for the Sports Club Template — a refined,
editorial re-skin. It reuses the existing engine, the skin-agnostic `ch-*` section
renderers, and all page content unchanged; it adds only a new `Base_Look` pack (a PHP
class + a full stylesheet) that restyles every `ch-*` hook in an establishment
members'-club idiom.

This is the first exercise of the project's "re-skin is a first-class goal" principle:
if a section or renderer needs to change to make this look work, that is a bug in the
section (a skin-specific assumption leaked into structure), not a task for this plan.

**Court Side is loud** (Syne, near-white, big 32px radii, pill buttons, bold accent
blocks, a rotated hero highlight). **Members' House is quiet** (Fraunces + Mulish, warm
parchment, small crisp radii, hairline rules, accent spent sparingly).

## Non-goals

- No changes to `Blueworx_Clubhouse_Sections`, `Page_Renderer`, `Color_Engine`,
  `Branding`, `Theme_Css`, `Visibility`, or any page composition.
- No admin UI for choosing the look (that belongs to the later admin-setup plan). The
  preview gets a throwaway switch only.
- No new runtime dependency. Fonts load from the same Google CDN path Court Side uses.
- Not the third look (Floodlight) — that is a later re-skin.

## Architecture

A Base Look pack implements `Blueworx_Clubhouse_Base_Look`
(`includes/theme/interface-base-look.php`): `slug()`, `name()`, `description()`,
`tokens()`, `fonts()`, `stylesheet()`. It supplies **presentation only** — never adds
or reads content, never emits accent tokens (those are engine-derived per club at
save-time and merged in by `Theme_Css::compose()`).

`tokens()` MUST include `--color-bg` and `--color-ink`. To satisfy the shared stylesheet
and section markup, Members' House defines the same token set Court Side does:
`--color-bg`, `--color-paper`, `--color-ink`, `--color-ink-soft`, `--color-line`,
`--radius-xl`, `--radius-lg`, `--radius-md`, `--font-display`, `--font-body`.

The stylesheet consumes those custom properties plus the engine's
`--color-accent`/`-ink`/`-deep`/`-wash`. The accent is *always* referenced through
`var(--color-accent*)` so a club re-themes by swapping one colour — identical contract
to `court-side.css`.

## Type

- **Display — Fraunces** (serif): hero title, section titles, card/tier/step/benefit
  titles, stat values, milestone years, band titles, brand wordmark, auth title.
  Editorial register — weights 400–600, tighter tracking, higher optical contrast than
  Court Side's Syne.
- **Body/UI — Mulish** (warm humanist sans): body copy, ledes, nav links, form fields,
  labels, badges, meta, buttons.
- `fonts()` returns Fraunces then Mulish, each with the weights actually used and
  `display: swap`.

## Neutral shell tokens

| Token | Value | Note |
|---|---|---|
| `--color-bg` | `#f2ece0` | warm parchment (deeper/warmer than Court Side `#faf8f3`) |
| `--color-paper` | `#fbf7ef` | card / raised surface |
| `--color-ink` | `#201c15` | warm near-black (never pure `#000`) |
| `--color-ink-soft` | `#6b6154` | taupe secondary text |
| `--color-line` | `#e0d8c7` | warm hairline |
| `--radius-xl` | `10px` | deliberately small — crisp, editorial |
| `--radius-lg` | `7px` | |
| `--radius-md` | `4px` | |
| `--font-display` | `'Fraunces', ui-serif, Georgia, serif` | |
| `--font-body` | `'Mulish', ui-sans-serif, sans-serif` | |

No accent tokens appear in `tokens()` — asserted by test.

## Component idiom (restrained editorial)

The accent is spent on fine detail, never bulk fills (except the primary CTA). Concrete
per-hook treatment, all achievable in CSS against the existing markup:

- **Eyebrow** (`.ch-eyebrow`): drop the accent-filled pill. Render as a letter-spaced
  uppercase Mulish label in ink, preceded by a short accent rule/dash.
- **Buttons** (`.ch-btn`): rectangular, `--radius-md`. `--accent` = accent fill with
  engine `--color-accent-ink` text (AA-guaranteed by the engine); `--ink` = ink fill;
  `--ghost` = hairline border. Hover is subtle — a small lift (≤2px) and a slight
  darken; `prefers-reduced-motion` disables transforms.
- **Hero highlight** (`.ch-hero__hl`): no rotation, no filled block. The emphasised
  word gets a fine accent underline (thick text-decoration or a bottom border).
- **Cards** (`.ch-benefit`, `.ch-tier`, `.ch-step`, `.ch-stats__item`,
  `.ch-sponsors__tile`, `.ch-news__card`, `.ch-evt`): flat `--color-paper`, hairline
  `--color-line` border, small radius, Fraunces titles, generous padding. Hover warms
  the border toward the accent and lifts 2–3px (reduced-motion safe).
- **Featured stat / bands** (`.ch-stats__item--feature`, `.ch-band--accent`,
  `.ch-band--ink`, `.ch-info`, `.ch-contact__info`): ink fields or a *quiet* accent
  wash with a fine accent rule — not saturated radial blocks. Keep white text on ink
  legible.
- **Overlay sport cards** (`.ch-card`): scrim + Fraunces title, near-square corners.
- **Rule-based sections** (`.ch-timeline`, `.ch-faq`, `.ch-split__*`,
  `.ch-milestone`): hairline-driven already — leaned into. Marks (FAQ +/-, split
  yes/no, tier feature dots) become finer/smaller accent details. This is where the
  editorial idiom reads strongest.
- **Nav / footer / ticker**: hairline separators, Mulish links, ink ticker with the
  accent as a small dot/label only.

### Preserved behaviour (carried by the shared section markup, must still work)

- Skip link, `<main>` landmark, one branded `:focus-visible` indicator.
- Mobile/tablet `<details>` nav drawer; no-JS operation throughout.
- Ticker CSS marquee + no-JS pause control; `prefers-reduced-motion` fallbacks.
- Scroll-reveal / hero entrance (content fully visible without JS and under reduced
  motion).
- `role="list"`/`listitem` grid semantics.
- Gradient/initials placeholders for empty image slots (re-toned to the parchment shell
  and accent).

## Files

1. **`includes/looks/class-members-house.php`** — `Blueworx_Clubhouse_Members_House`
   implementing the interface. Slug `members-house`, name `Members' House`, a one-line
   description, the tokens table above, the fonts array, stylesheet path
   `assets/looks/members-house.css`. Mirrors `class-court-side.php` in shape.
2. **`assets/looks/members-house.css`** — the full look stylesheet, parallel in coverage
   to `court-side.css` (every `ch-*` hook, the spacing/flow scale, reveal/skip/focus
   rules, all media queries), authored in the refined-editorial idiom above. Same
   accent contract (`var(--color-accent*)` only; no hardcoded club colours).
3. **`preview/index.php`** — register both looks on the registry; read `?look=` (sanitised
   to `[a-z-]`), `set_active()` it (default `court-side` when unset/unknown); add a small
   look toggle next to the accent switcher. Palette derivation uses the active look's
   `--color-bg`/`--color-ink` so the demo swatches stay AA-correct per look. Members'
   House demo default accent: deep claret `#7a2f3a` (client-swappable; engine handles AA).
4. **`includes/bootstrap.php`** — add a `require_once` for `looks/class-members-house.php`
   alongside the existing Court Side require (bootstrap only loads the look classes; no
   live registry is wired at runtime yet — instantiation/registration happens in the
   preview today, and the WP-runtime registration is a later plan).
5. **Version + changelog** — minor bump (new feature) in `blueworx-labs-clubhouse.php`
   (Version header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json`, with a
   matching `CHANGELOG.md` entry.

## Testing

Mirror the Court Side tests (`CourtSideTest`, `CourtSideStylesheetTest`,
`PreviewRenderTest`), TDD:

- **Identity:** slug `members-house`, name `Members' House`, stylesheet path.
- **Tokens:** `--color-bg` = `#f2ece0`, `--color-ink` = `#201c15`; `--font-display`
  contains `Fraunces`, `--font-body` contains `Mulish`; the full required key set is
  present; **no `--color-accent*` key** appears in `tokens()`.
- **Fonts:** families are exactly `['Fraunces', 'Mulish']`; each entry has
  `family`/`weights`/`display`.
- **Compose:** registering the look and setting an accent yields a `Theme_Css::compose`
  result carrying the shell tokens plus derived `--color-accent*`.
- **Stylesheet hygiene** (mirror `CourtSideStylesheetTest`): the stylesheet exists;
  references the accent only via `var(--color-accent*)`; contains no raw club-colour hex
  outside the neutral shell (align the assertion to whatever the Court Side test checks).
- **Preview render:** `?look=members-house` produces a document that links
  `assets/looks/members-house.css` and Fraunces/Mulish; the default (no `?look=`) still
  renders Court Side.

All existing tests must stay green (no section/renderer changes).

## Verification

- `composer test` green (existing + new).
- `php -S localhost:8124` from plugin root; visit `/preview/?look=members-house` across
  `?page=home|about|membership|contact|login`: parchment shell, Fraunces headings /
  Mulish body, crisp small radii, restrained accent, no horizontal overflow 320px→desktop.
- Toggle the accent switcher: whole page re-themes; derived `accent-ink` stays AA on the
  claret and each preset.
- Confirm Court Side is visually unchanged (default look, no regressions).

## Open follow-ups (out of scope here)

- Runtime WP registration + look choice in admin (admin-setup / WP-render plans).
- Font self-hosting (shared with Court Side; later).
- Floodlight (third look).
