# Demo mode accent picker — design spec

**Date:** 2026-07-15
**Branch:** `demo-accent-picker`
**Status:** Approved for planning

## Goal

Site-wide Demo mode already lets a viewer switch **Base Look**. It cannot switch
**accent** — so a prospective club can see the three looks, but only ever in the
site's saved brand colour.

Add the DB-free preview's live accent picker (`preview/index.php`'s five swatches)
to the Demo mode bar, so a prospect can try brand colours on the real site and tour
the whole thing in their own colour.

## Decisions taken (asked and answered)

- **Five fixed swatches**, the same set the preview ships: Volt Lime `#c6f24e`,
  Signal Orange `#ff5b23`, Court Teal `#12c3b0`, Cobalt `#3b5bdb`, Berry `#c2337a`.
  Not a free colour input — arbitrary accents can fail the engine's legibility gate
  (the admin Setup screen already rejects some), which would need a warning UI and a
  fallback. Out of scope.
- **Instant, and sticks across pages.** Clicking a swatch recolours the page with no
  reload, and the choice survives navigation — matching the look switch, which
  already persists.

## Architecture — the client applies it; the server never renders a demo accent

The picker sets the accent custom properties directly on `document.documentElement`,
exactly as `preview/index.php`'s switcher does, and records the choice in a
`clubhouse_demo_accent` cookie. On every page load a small **inline head script**
reads that cookie and re-applies the properties **before first paint**, so the colour
persists across navigation without a flash of the saved colour.

The server always renders the club's **saved** accent. It never renders a demo one.

### Why — this is the load-bearing decision

`Theme_Cache` (`includes/theme/class-theme-cache.php`) is a **single shared slot** —
one `root_css` string plus one `root_css_sig`, in an autoloaded option, for the
entire site. `Frontend` emits it at `class-frontend.php:131`.

If a per-viewer demo accent were rendered server-side through that path, one
visitor's colour would be written into shared site storage and served to every other
visitor — including real public traffic. Keeping the accent client-side makes that
class of bug **structurally impossible** rather than merely avoided, and it is why
this design does not simply mirror the cookie-and-reload mechanism the look switch
uses.

### Data flow

1. `Demo_Controller::render_switcher()` (already on `wp_footer`) derives the five
   palettes for the **currently rendered look** via `Color_Engine::derive()` and
   passes them to `Demo_Mode::switcher_html()`.
2. `switcher_html()` emits them in a `data-clubhouse-palettes` attribute — the same
   JSON-in-a-data-attribute pattern `preview/index.php` already uses — and renders a
   swatch button per palette.
3. `demo.js` handles clicks: set the four custom properties on `documentElement`,
   write the cookie. No reload.
4. A new inline head script (registered early on `wp_head`, demo-mode only) reads the
   cookie and applies the same properties pre-paint.

### Interaction with the look switch

Switching look still reloads (unchanged). The accent cookie survives that reload, and
the palettes are re-derived server-side **for the new look** — so a prospect who
picks Berry and then switches to Floodlight stays in Berry, correctly re-derived for
that look's dark shell. The cookie stores the **swatch slug** (e.g. `berry`), never a
raw hex, precisely so the engine re-derives per look rather than the client
replaying a colour that was computed for a different shell.

## Scope

**In scope**

- `Demo_Mode::palettes()` — pure, WP-free: given a look + the swatch list, return the
  derived token sets. Mirrors `blueworx_clubhouse_preview_palettes()`.
- `Demo_Mode::switcher_html()` gains the swatch group + palettes data attribute.
- `Demo_Controller` — derive palettes for the rendered look; register the inline head
  script (demo-mode only).
- `demo.js` — swatch click handler.
- `demo.css` — swatch styling (neutral chrome, no accent tokens — matching the file's
  existing rule).
- Cookie `clubhouse_demo_accent`, per-viewer, alongside `COOKIE_LOOK`.
- Tests (below), version bump, changelog.

**Out of scope**

- A free/custom colour input, and the legibility-warning UI it would require.
- Any change to `Theme_Cache`, `Theme_Css`, `Color_Engine`, or `Branding`.
- Any change to the club's **saved** accent. Demo is per-viewer and read-only, the
  same contract the look switch already honours ("Never writes the club's saved
  look").
- The admin Setup screen's accent control.
- The pre-existing `Theme_Cache` write-per-request issue (see Risks).

## Files touched

| File | Change |
| --- | --- |
| `includes/frontend/class-demo-mode.php` | Add `COOKIE_ACCENT`, `SWATCHES`, `palettes()`; extend `switcher_html()`. |
| `includes/admin/class-demo-controller.php` | Derive palettes for the rendered look; add the inline head script on `wp_head`, demo-only. |
| `assets/js/demo.js` | Swatch click handler. |
| `assets/css/demo.css` | Swatch styling. |
| `tests/php/DemoModeSwitcherTest.php` | Swatch markup + palettes attribute. |
| `tests/php/DemoModeTest.php` | `palettes()` derivation per look. |
| `tests/demo-accent.spec.js` (new) | Browser behaviour. |

## Testing

**PHP (WP-free, matching the existing Demo_Mode tests)**

- `palettes()` returns all five swatches with the four accent tokens, derived through
  the real `Color_Engine` for the given look.
- The same swatch derives **different** tokens on a light look vs Floodlight —
  pinning the "re-derive per look" contract.
- `switcher_html()` renders five swatch buttons and a valid-JSON palettes attribute,
  and still renders the look buttons and the admin-only exit link.
- The cookie stores a swatch **slug**, not a hex.

**Browser (Playwright, against the DB-free preview harness)**

- Click a swatch → `--color-accent*` on `documentElement` change to the derived
  values, with no reload.
- The choice survives navigation (cookie re-applied).
- Switching look keeps the accent and re-derives it for the new look.
- Demo off → no swatches rendered.

## Risks

- **Flash of saved colour.** Mitigated by applying the cookie in an inline head
  script before paint rather than in the deferred `demo.js`. If the inline script is
  ever moved into the footer bundle, the flash returns — the test should assert the
  script is emitted in `wp_head`.
- **Pre-existing, not fixed here:** with demo mode on and viewers on different looks,
  `Theme_Cache`'s signature never matches the cached one, so **every page view
  recomputes the CSS and writes two autoloaded options** — a DB write per request.
  Correctness is unaffected (a mismatched signature always recomputes), and this
  predates this work. This spec's client-side approach adds no further pressure on
  it, but it deserves its own issue.
- **Cookie longevity/consent.** The accent cookie is a per-viewer UI preference with
  no personal data, the same category as the existing `clubhouse_demo_look`. It
  should match that cookie's flags (`path=/; SameSite=Lax`) so it is not a new
  consent consideration.
