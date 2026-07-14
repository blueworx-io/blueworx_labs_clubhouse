# Setup screen redesign — design

**Date:** 2026-07-14
**Branch:** `setup-screen-redesign` (off `main` @ v0.21.0)
**Status:** approved (brainstorm), ready for implementation plan

## Problem

The Clubhouse **Setup** admin screen (owner-facing, the Dashboard takeover for the
`clubhouse_owner` role) currently renders as a plain WordPress `form-table`: a flat
list of look radios, branding text fields, and a wall of visibility checkboxes. The
owner's first impression of the product is generic wp-admin chrome.

The client supplied a bespoke redesign (three annotated screenshots) and three
requirements:

1. **The setup page should inherit the style of the selected/active base look** —
   fonts, shell colours, radii and accent.
2. Add a **Favicon** option alongside the existing Logo.
3. Add **LinkedIn** as a social option.

## Goals

- Rebuild the Setup screen to the supplied tabbed, stepped layout.
- Make the admin page **re-skin to the chosen Base Look, live** as the owner selects
  a look card.
- Add Favicon and LinkedIn as first-class branding inputs, **stored and rendered on
  the live site** (favicon in the browser tab; LinkedIn in the social block + footer).
- Recompute setup progress by **main section**, nothing compulsory, fully resumable.

## Non-goals

- No change to the frontend look packs (`assets/looks/*.css`), section renderers
  (`includes/render/*`), or the theming engine (`includes/theme/*`), beyond the two
  narrow frontend additions in §5 (favicon `<link>` and the LinkedIn social link).
- No JS port of the PHP `Color_Engine` (accent derivation stays server-side — see §3).
- No change to the six CPTs, collections, or content editing.

## Architecture

The existing pure/glue split is preserved:

| Unit | Role | Change |
|---|---|---|
| `Blueworx_Clubhouse_Setup_Screen` | pure HTML builder | **rewritten** to the tabbed structure |
| `Blueworx_Clubhouse_Setup_Controller` | WP glue: menu, enqueue, POST | supplies the new model incl. per-look tokens; sanitises 2 new fields; injects the look `:root` + `@font-face` |
| `Blueworx_Clubhouse_Setup_Progress` | pure progress calc | **regrouped** (see §4) |
| `Blueworx_Clubhouse_Branding` | raw brand inputs | **+`favicon`, +`linkedin`** get/set + defaults |
| `assets/css/admin-setup.css` | admin styling | **rewritten** entirely in look tokens |
| `assets/js/admin-setup.js` | progressive enhancement | tabs, live progress, live re-skin, dual media pickers |

Front-end (narrow additions only):

| Unit | Change |
|---|---|
| `Blueworx_Clubhouse_Frontend` (or `Page_Renderer` head) | emit `<link rel="icon">` from the favicon attachment |
| `Blueworx_Clubhouse_Sections::social()` | add LinkedIn link (inline `currentColor` SVG) |
| footer socials (`Sections`) | add LinkedIn alongside FB/IG |
| `Blueworx_Clubhouse_Demo_Content` | add a LinkedIn demo default so preview/tests exercise it |

## §3 — Look inheritance (the live re-skin)

**Server side.** The controller composes, for **every registered look** at the
**current saved accent**, its full derived token set via the existing
`Color_Engine::derive( accent, shell_bg, shell_ink )` + look `tokens()`. It emits:

- a scoped custom-property block on the page container —
  `.clubhouse-setup { --color-bg: …; --color-ink: …; --color-accent-deep: …;
  --font-display: …; --radius-lg: …; … }` — seeded with the **active** look, and
- `@font-face` declarations for each look's self-hosted `.woff2` (already bundled
  under `assets/fonts/`), so every look's fonts are available for the live swap, and
- a JSON `slug → { token: value }` map (inline, escaped) for the JS to swap between.

**Admin stylesheet.** `admin-setup.css` is rewritten to reference **only**
`var(--color-*)` / `var(--font-*)` / `var(--radius-*)` — the same discipline the
frontend look packs follow. It is scoped under `.clubhouse-setup` and neutralises the
surrounding wp-admin chrome *within the page container only* (background, typography,
button styling); the wp-admin menu sidebar is untouched.

**Client side.** Clicking a look card writes that slug's tokens onto the container's
inline style (custom properties), instantly re-skinning fonts, shell, radii and
accent. This is a genuine preview of the visitor experience.

**Accent live-preview caveat (deliberate).** Typing a new hex updates the swatch and
the raw `--color-accent` live, but the **derived** tokens (`--color-accent-ink`,
`-deep`, `-wash`) require the PHP `Color_Engine` and refresh on **Save**, where the
existing look-aware legibility validation already runs
(`Color_Engine::accent_is_legible_for`). We do not port the colour engine to JS.

## §4 — Progress (recalculated by section)

Progress counts the **main setup sections**, each marked done when the owner has
personalised it. **Nothing is compulsory**; **Save is always enabled**; state
persists so the owner can return and finish later.

| # | Group | "Done" when |
|---|---|---|
| 1 | Base look | a look has been explicitly saved (`active_base_look` set) |
| 2 | Accent colour | differs from the `#c6f24e` default **and** legible for the active look |
| 3 | Club name | non-empty and differs from the `ClubHouse` placeholder |
| 4 | Logo & favicon | at least one of logo / favicon is set |
| 5 | Social media | at least one of Facebook / Instagram / LinkedIn is set (differs from demo default) |

Bar reads **"N of 5."** Favicon folds into the Logo group; LinkedIn folds into Social
— consistent with "social media is one section, logos/favicons are one section, some
sites have none."

**Save is never gated.** The mockup's disabled-Save + "Add a base look, accent colour
and club name to save" is replaced by an always-active Save and a friendly nudge
("3 of 5 done — save now, finish later"). This is an intentional deviation from the
mockup, driven by the "nothing compulsory, resumable" requirement.

## §5 — New fields and frontend wiring

**Favicon**
- `Branding::get_favicon()/set_favicon()`, default `''`. Stored as an attachment id
  (like the logo), for stability + security (`absint`-able, not an attacker string).
- Setup: a media picker in the Logo & Favicon group; the JS generalised to drive both
  the logo and favicon pickers (one small, id-parameterised helper).
- Frontend: emit `<link rel="icon" href="…">` in `wp_head` from the resolved
  attachment URL. No favicon set → emit nothing (WordPress/site default applies).

**LinkedIn**
- `Branding::get_linkedin_url()/set_linkedin_url()`, demo default
  `https://linkedin.com/company/clubhouse`.
- Setup: a URL field in the Social group. Controller sanitises with `esc_url_raw`.
- Frontend: added to `Sections::social()` (the "Follow us" block) **and** the footer
  socials, as a third link with an inline `currentColor` LinkedIn SVG (no hex, matching
  the FB/IG icon convention). Threaded through the `social()` data array and the
  page-renderer/footer callers.

## §6 — Layout (from the mockup)

- **Header:** eyebrow ("CLUBHOUSE · SITE SETUP") · "CLUBHOUSE SETUP" title ·
  right-aligned `%` + "N of 5 complete" + progress bar.
- **Tabs:** Base Look & Branding · Visibility · Demo Mode.
- **Tab 1 — Base Look & Branding:**
  - *Step 1 · Foundation:* three look cards, each with a **mini-preview thumbnail**
    built from that look's own tokens (header bar + accent block + text lines), radio,
    name, description. Active card highlighted.
  - *Step 2 · Branding:* accent swatch + hex input; club name; **Logo & Favicon**
    (two media pickers with previews + remove); **Social** (Facebook, Instagram,
    LinkedIn URL fields).
- **Tab 2 — Visibility:** per-page sub-tabs (Master 14/14, About 6/6, Membership 7/7,
  …) with **toggle switches** per section and a "Page shown" master toggle. Counts
  reflect live state. (Existing page/section keys from `Setup_Sections::inventory()`.)
- **Tab 3 — Demo Mode:** the demo-mode toggle card (existing `Demo_State` wiring).
- **Sticky save bar:** contextual progress nudge + always-active Save button.

**No-JS fallback (house convention).** All tabs and sub-panels render **stacked and
fully usable** server-side; JS *enhances* them into tabbed navigation. Toggle switches
are CSS-styled native checkboxes (work JS-off). Media pickers already require JS
(`wp.media`) — unchanged. Live progress and live re-skin are enhancements; the saved
values and server-computed progress are correct without JS.

## §7 — Testing

Mirrors the existing admin test patterns (PHPUnit + `wp-stubs.php`, no WP runtime):

- **Branding:** favicon + linkedin get/set/round-trip + defaults.
- **Setup_Progress:** the 5 grouped booleans; Logo-or-favicon OR-logic; Social
  any-of-three OR-logic; accent legibility still gates group 2; `total === 5`.
- **Setup_Screen:** tabbed structure present (3 tabs, step labels, sub-tabs); toggle
  switches; favicon picker + LinkedIn field present; everything escaped; Save button
  not disabled.
- **Setup_Controller:** favicon sanitised (attachment id), linkedin `esc_url_raw`d,
  persisted via setters; existing accent/visibility/demo behaviour intact.
- **social():** includes a LinkedIn link; skin-agnostic (no hex, `currentColor` only);
  escaping intact.
- **Favicon head:** `<link rel="icon">` emitted when set, absent when empty.
- **admin-setup.css hygiene:** accent referenced only via `var(--color-accent*)`;
  fonts only via `var(--font-*)`; the other looks' literal accents absent — same bar
  as the look-pack stylesheet tests.
- **Preview/Playwright:** LinkedIn link renders in the social block in the DB-free
  preview (LinkedIn demo default).

**Manual WP smoke (owed, runtime-only — admin can't run in preview):** activate →
Clubhouse Setup; page is skinned to the saved look; clicking each look card re-skins
the whole page live; logo AND favicon pickers both open and preview; favicon appears
in the browser tab on the live site; LinkedIn shows in the front-end social block +
footer; tabs/sub-tabs work with JS and everything is reachable with JS off. Added to
the project's existing owed-smoke list.

## §8 — Delivery

- Minor version bump **v0.21.0 → v0.22.0** (header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`
  const + `package.json`) with a matching `CHANGELOG.md` entry.
- No new npm dependency (`approved-deps.json` untouched).
- Rebuild the plugin zip at session end via System32 bsdtar (forward-slash entries) —
  owner admin surface changed.

## Risks / notes

- **wp-admin chrome bleed.** Scoping all admin styles under `.clubhouse-setup` and
  only neutralising chrome within the container keeps the wp-admin menu intact; verify
  no global overrides leak. Manual smoke covers this.
- **Font loading for all looks.** The live swap needs every look's fonts available on
  the admin page (extra `@font-face` on one admin screen only — acceptable).
- **Owner dashboard reuse.** `Setup_Controller::screen_html()` is reused by the owner
  Dashboard takeover; the redesign must render correctly in both the standalone page
  and `index.php` (enqueue already covers both hooks).
