# Floodlight — third Base Look (design)

**Date:** 2026-07-11
**Branch:** `floodlight-look` (off `base-look-theming-design`)
**Status:** Approved, ready for implementation plan

## Summary

Floodlight is the **third and final planned Base Look** for the Sports Club Template —
a bold, night-match re-skin and the **dark counterpart** to the two light looks (Court
Side, Members' House). It reuses the existing engine, the skin-agnostic `ch-*` section
renderers, and all page content unchanged; it adds only a new `Base_Look` pack (a PHP
class + a full stylesheet) that restyles every `ch-*` hook in a dark, athletic,
accent-**glow** idiom.

This is the project's third exercise of the "re-skin is a first-class goal" principle,
and the first on a **dark shell**. If a section or renderer needs to change to make this
look work, that is a bug in the section (a skin-specific assumption leaked into
structure), not a task for this plan.

**Court Side is loud** (Syne, near-white, big 32px radii, pill buttons, bold accent
blocks). **Members' House is quiet** (Fraunces + Mulish, warm parchment, hairline rules,
restrained accent). **Floodlight is dark** (Bricolage Grotesque + Inter, warm-ink
canvas, crisp mid radii, accent spent as *glow* — outline, deep-text, soft shadow, wash
— never as a solid fill you place text on).

## Non-goals

- No changes to `Blueworx_Clubhouse_Sections`, `Page_Renderer`, `Color_Engine`,
  `Branding`, `Theme_Css`, `Visibility`, or any page composition.
- **No engine change.** In particular, `Color_Engine::derive()` is untouched (see the
  accent-legibility decision below — Floodlight *sidesteps* the known dark-shell
  `accent-ink` limitation rather than triggering it).
- No admin UI for choosing the look (later admin-setup plan). The preview gets a
  throwaway switch only.
- No new runtime dependency. Fonts load from the same Google-CDN path the other looks use.
- Not a stack on top of Members' House. Floodlight is a **sibling** re-skin branched off
  `base-look-theming-design`, opened as a stacked PR into `base-look-theming-design`
  (same target as PR #3). The two PRs will conflict in `preview/index.php` and
  `includes/bootstrap.php`; the human resolves that at merge.

## The accent-legibility decision (dark shell)

The engine derives four accent tokens from a club's single accent against the look's
shell (`--color-bg`, `--color-ink`). On Floodlight's **dark** shell:

- `--color-accent-deep` — accent-as-text — is **AA-guaranteed against `--color-bg`** by
  the engine (it blends the accent *toward white* until legible). Floodlight routes
  **all** accent-coloured text, marks, rules and numerals through `accent-deep`. This
  also disposes of the deferred "raw `var(--color-accent)` used as text is low-contrast"
  bug: Floodlight never uses raw accent as text.
- `--color-accent` (fills / glows) and `--color-accent-wash` (tints) are fine on dark.
- `--color-accent-ink` — text placed **on top of** an accent fill — is **handicapped on a
  dark shell**. `derive()` computes it as the better of *shell-ink vs white*; because
  Floodlight's shell-ink is **light**, both candidates are light, so a bright "glow"
  accent fill (exactly Floodlight's signature) would get illegible light text. A solid,
  punchy accent CTA with legible text is therefore impossible on the dark shell without
  changing the engine.

**Decision — glow idiom, zero engine change.** Floodlight uses the accent *only* as
outline, `accent-deep` text, soft box-shadow glow, and wash — never as a solid fill it
places text on — so `--color-accent-ink` is simply never leaned on for legibility. This
keeps Floodlight a pure re-skin (the whole point of the third look), matches the "accent
glows" brief literally, and carries the least risk (Court Side and Members' House colour
output stay untouched).

The dark shell also hands us a **solid punch for free**: because `--color-ink` is now a
light warm bone, `.ch-btn--ink` becomes a bright bone button with dark (`--color-bg`)
text — fully AA-legible. So Floodlight has both a solid punch (ink/bone button) and a
glow (accent button). The engine's `accent-ink` limitation remains documented as
deferred; Floodlight does not resolve it, it avoids it.

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

- **Display — Bricolage Grotesque** (contemporary grotesque, athletic display register):
  hero title, section titles, card/tier/step/benefit titles, stat values, milestone
  years, band titles, brand wordmark, auth title, fixture times/scores. Weights 500–800
  for bold night-match energy.
- **Body/UI — Inter** (clean, highly legible on dark): body copy, ledes, nav links, form
  fields, labels, badges, meta, buttons. Weights 400–700.
- `fonts()` returns Bricolage Grotesque then Inter, each with the weights actually used
  and `display: swap`. (From the phase-2 direction: "Bricolage Grotesque + Inter".)

## Neutral shell tokens (dark, warm — never pure black)

| Token | Value | Note |
|---|---|---|
| `--color-bg` | `#16120c` | warm near-black canvas |
| `--color-paper` | `#211a12` | raised dark card / panel surface |
| `--color-ink` | `#f4ede0` | warm bone — the light text colour *and* the solid "punch" button fill |
| `--color-ink-soft` | `#a99f8c` | warm taupe secondary text (~7:1 on bg — AA) |
| `--color-line` | `#3a3020` | warm hairline, visible on the dark canvas |
| `--radius-xl` | `16px` | crisp athletic mid-point (Court Side 32/24/16, MH 10/7/4) |
| `--radius-lg` | `12px` | |
| `--radius-md` | `8px` | |
| `--font-display` | `'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif` | |
| `--font-body` | `'Inter', ui-sans-serif, system-ui, sans-serif` | |

No accent tokens appear in `tokens()` — asserted by test.

## Component idiom (glow-first, all accent text via `accent-deep`)

The accent is a glow, never a solid text-bearing fill. Concrete per-hook treatment, all
achievable in CSS against the existing markup:

- **Eyebrow** (`.ch-eyebrow`): a letter-spaced uppercase Inter label in soft ink,
  preceded by a short `accent-deep` rule (no filled pill).
- **Buttons** (`.ch-btn`): crisp `--radius-md`. `--accent` = transparent/wash fill +
  solid `var(--color-accent)` border + `accent-deep` text + a soft outer glow
  (`box-shadow` in accent); hover intensifies the glow and lifts ≤2px. `--ink` = bright
  bone fill (`--color-ink`) with dark (`--color-bg`) text — the solid punch. `--ghost` =
  hairline border, ink text. `prefers-reduced-motion` disables transforms (glows are
  static box-shadows and stay).
- **Hero highlight** (`.ch-hero__hl`): the emphasised word **glows** — `accent-deep` text
  with a soft accent `text-shadow`. No rotation, no filled block.
- **Ink-field panels** (`.ch-ticker`, `.ch-info`, `.ch-fx-list`/`.ch-res-list`,
  `.ch-contact__info`, `.ch-stats__item--feature`, `.ch-banner`): Floodlight authors
  these as **dark raised panels** (`--color-paper` or a shade near it) with light ink
  text, `accent-deep` highlights, and a subtle inset/edge accent glow — *not* the light
  looks' "bright ink fill". The whole page stays in the night register. (The look
  stylesheet fully authors each selector, so this is a re-style, not a section change.)
- **Cards** (`.ch-benefit`, `.ch-tier`, `.ch-step`, `.ch-stats__item`,
  `.ch-sponsors__tile`, `.ch-news__card`, `.ch-evt`): flat `--color-paper`, hairline
  `--color-line` border, crisp radius, Bricolage-Grotesque titles. Hover lights the
  border to the accent + a soft glow and lifts 2–3px (reduced-motion safe).
- **Overlay sport cards** (`.ch-card`): photo + dark scrim (already dark) + light display
  title + an `accent-deep` tag chip on a dark ground.
- **Bands** (`.ch-band--accent`, `.ch-band--ink`): `--accent` = an accent-wash field with
  a glow ring and `accent-deep` heading; `--ink` = a bright bone field with dark text —
  the two bands read as glowing-dark vs bright-bone.
- **Rule-based sections** (`.ch-timeline`, `.ch-faq`, `.ch-splits`, `.ch-milestone`):
  hairline-driven; marks (FAQ +/–, split yes/no, tier feature dots, step numerals) are
  fine `accent-deep` details, some with a faint glow.
- **Imagery — grayscale→accent duotone hint:** `.ch-media__img` is desaturated
  (`filter: grayscale(1) contrast(~1.04)`) and `.ch-media:not(.ch-media--empty)::after`
  lays a low-opacity accent-tinted overlay (`mix-blend-mode`), giving photos a restrained
  duotone character that re-themes with the accent. Empty placeholders keep their own
  `.ch-media--empty::after` icon (different selector, no collision) over a dark gradient
  with an accent-wash bloom.
- **Nav / footer / ticker**: hairline separators, Inter links, `accent-deep` active-link
  underline, ink brand mark; ticker is a dark panel with `accent-deep` label + accent
  dots.

### Preserved behaviour (carried by the shared section markup, must still work)

- Skip link, `<main>` landmark, one branded `:focus-visible` indicator
  (`var(--color-accent-deep)` — AA on the dark shell).
- Mobile/tablet `<details>` nav drawer; no-JS operation throughout.
- Ticker CSS marquee + no-JS pause control; `prefers-reduced-motion` fallbacks.
- Scroll-reveal / hero entrance (content fully visible without JS and under reduced
  motion). Any *pulsing* glow is gated by `prefers-reduced-motion`; static box-shadow
  glows stay.
- `role="list"`/`listitem` grid semantics.
- Gradient/initials placeholders for empty image/avatar slots, re-toned to the dark shell
  and accent.

## Files

1. **`includes/looks/class-floodlight.php`** — `Blueworx_Clubhouse_Floodlight`
   implementing the interface. Slug `floodlight`, name `Floodlight`, a one-line
   description, the tokens table above, the fonts array, stylesheet path
   `assets/looks/floodlight.css`. Mirrors `class-court-side.php` in shape.
2. **`assets/looks/floodlight.css`** — the full look stylesheet, parallel in coverage to
   `court-side.css` (every `ch-*` hook, the spacing/flow scale, reveal/skip/focus rules,
   all media queries), authored in the dark accent-glow idiom above. Same accent contract
   (`var(--color-accent*)` only; no hardcoded club colours).
3. **`preview/index.php`** — register `court-side` + `floodlight` on the registry; change
   `blueworx_clubhouse_preview_palettes()` to take the active look and derive swatches
   from its shell; read `?look=` (sanitised to `[a-z-]`, default/fallback `court-side`),
   `set_active()` it; add a small look toggle that **cycles** the registered looks. (Only
   Court Side and Floodlight exist on this branch; Members' House arrives via its own
   sibling PR.)
4. **`includes/bootstrap.php`** — add a `require_once` for `looks/class-floodlight.php`
   alongside the existing Court Side require.
5. **Version + changelog** — bump to **`0.10.0`** in `blueworx-labs-clubhouse.php`
   (Version header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json`, with a
   matching `CHANGELOG.md` entry. (`0.9.0` is Members' House on its sibling branch/PR #3;
   Floodlight takes `0.10.0` so the two don't collide when both merge into
   `base-look-theming-design`.)

## Testing

Mirror the Court Side / Members' House tests, TDD:

- **Identity:** slug `floodlight`, name `Floodlight`, stylesheet path.
- **Tokens:** `--color-bg` = `#16120c`, `--color-ink` = `#f4ede0`, `--color-paper` =
  `#211a12`, `--color-ink-soft` = `#a99f8c`, `--color-line` = `#3a3020`; `--font-display`
  contains `Bricolage Grotesque`, `--font-body` contains `Inter`; radii `16px`/`12px`/`8px`;
  the full required key set is present; **no `--color-accent*` key** appears in `tokens()`.
- **Fonts:** families are exactly `['Bricolage Grotesque', 'Inter']`; each entry has
  `family`/`weights`/`display`, weights non-empty.
- **Compose:** registering the look and setting an accent yields a `Theme_Css::compose`
  result carrying the shell tokens plus derived `--color-accent*` (incl. `-ink`, `-deep`).
- **Stylesheet hygiene** (mirror `CourtSideStylesheetTest`): the stylesheet exists;
  styles the shell sections (`.ch-nav`, `.ch-hero`, `.ch-stats`, `.ch-footer`, `.ch-btn`,
  `.ch-faq`, `.ch-tiers`, `.ch-auth`); references the accent **only** via
  `var(--color-accent*)` — asserts the demo default accent and the other looks' accents
  are **absent**; uses fonts **only** via `var(--font-display)` / `var(--font-body)` and
  the font-family **names are absent from the CSS** — the substrings **`Bricolage`** and
  **`Inter`** must not appear anywhere in the stylesheet, *including comments* (use
  "display token" / "body token" phrasing).
- **Preview render:** `?look=floodlight` produces a document that links
  `assets/looks/floodlight.css` and both font families (`family=Bricolage%20Grotesque`,
  `family=Inter`) and carries the dark shell token `#16120c` in the emitted `:root`; the
  default (no `?look=`) still renders Court Side and **not** Floodlight.

All existing tests must stay green (no section/renderer changes).

## Verification

- `composer test` green (existing + new).
- `php -S localhost:8124` from plugin root; visit `/preview/?look=floodlight` across
  `?page=home|about|membership|contact|login`: dark warm-ink shell, Bricolage-Grotesque
  headings / Inter body, crisp mid radii, accent glows, solid bone punch button, no
  horizontal overflow 320px→desktop.
- Toggle the accent switcher: whole page re-themes; `accent-deep` text/marks stay AA on
  the dark shell across every preset hue; glows re-tint.
- Confirm Court Side is visually unchanged (default look, no regressions).
- Confirm no `.ch-*` hook is left unstyled (the Floodlight selector set matches Court
  Side's).

## Open follow-ups (out of scope here)

- Runtime WP registration + look choice in admin (admin-setup / WP-render plans).
- Font self-hosting (shared with the other looks; later).
- The engine `accent-ink`-on-dark limitation stays **deferred**: if a future look wants a
  *solid* accent fill with legible text on a dark shell, `Color_Engine::derive()` should
  compute `accent-ink` from the true black/white poles rather than shell-ink vs white.
  Floodlight does not need it (glow idiom), so it is not done here.
