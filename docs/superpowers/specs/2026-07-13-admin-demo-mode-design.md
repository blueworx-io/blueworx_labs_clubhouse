# Admin Demo Mode — Design

**Date:** 2026-07-13
**Branch:** `admin-demo-mode` (off `main`)
**Target version:** 0.17.0 (new feature)

## Purpose

Give an admin a per-admin, ephemeral way to walk a prospective club owner through
every Base Look on the **real live site**, so the owner can pick one — without
changing the club's saved look and without any public visitor ever seeing the
demo.

This is the front-end counterpart to the `preview/` page's look switcher, but
gated to logged-in admins on the live WordPress site and driven by the real
render path.

## Behaviour

1. **Admin-bar toggle.** A "⚡ Demo mode" node appears in the front-end
   WordPress admin bar for users who can `manage_options`. Clicking it sets a
   per-admin cookie and reloads the page → Demo mode ON. Clicking again clears
   the cookie → OFF.
2. **Floating switcher.** While Demo mode is ON and the viewer is a logged-in
   admin on a Clubhouse-rendered page, a small fixed-position bar appears
   listing **every registered Base Look** (from `registry->all()`, so it grows
   past three automatically as looks are added). The currently-shown look is
   highlighted. The bar includes an "Exit demo" control.
3. **Switching.** Clicking a look sets a second cookie holding that look's slug
   and reloads. The server renders that look's stylesheet + derived `:root`.
4. **Non-persistence.** The club's saved `active_base_look` option is **never
   written** during a demo. Accent stays the club's saved accent. Exiting demo
   (or the cookies expiring) returns the site to the saved look with no side
   effects.
5. **Isolation.** The override is honoured **only** when
   `current_user_can('manage_options')`. Public visitors — and any forged
   cookie sent by a non-admin — always get the club's saved look.

## Approach

**Cookie-based, per-admin, resolved server-side, reload-to-switch.**

Rejected alternatives:

- *Client-side live re-skin* (swap stylesheet `href` + `:root` in JS, like the
  preview page). Each look has its own CSS file and a PHP-derived `:root`, so
  this means pre-emitting every look's stylesheet and token block into the page
  and duplicating the switch logic in JS. Heavier and more fragile than a
  reload.
- *Dedicated wp-admin demo page with iframes.* Keeps the demo off the
  front-end, but you can't full-screen-walk someone through the actual site.
  Worse for the stated purpose.

Reload-to-switch reuses the entire existing render path (`Frontend::context()`
→ enqueue + `Page_Map::render`), so a demo page is byte-for-byte what the owner
would get if they chose that look.

## Components

### Pure core — `Blueworx_Clubhouse_Demo_Mode`

New class `includes/frontend/class-demo-mode.php`, pure and unit-tested,
mirroring the `Setup_Progress` / `Setup_Screen` split (pure logic in
`frontend/`, WP glue in `admin/`).

- `is_active( bool $can_manage, ?string $flag_cookie ): bool`
  Demo is active only when the viewer can manage the site **and** the flag
  cookie is set (truthy).
- `resolve_look_slug( bool $active, ?string $look_cookie, array $available_slugs ): ?string`
  Returns the demo look slug to render, or `null` to fall through to the saved
  active look. Returns `null` when demo is inactive, the look cookie is
  missing, or the cookie slug is not in `$available_slugs` (unknown/stale slug
  → fall through, never a fatal).
- `switcher_html( array $looks, ?string $current_slug ): string`
  Returns the escaped fixed-bar markup: one control per registered look
  (label + slug), the current one flagged, plus an "Exit demo" control. Pure
  string builder — no WP calls, all output escaped. Skin-agnostic (no look-
  specific colour/style literals; styled by `demo.css`).

Cookie name constants live on this class:
`clubhouse_demo` (flag) and `clubhouse_demo_look` (slug).

### Render integration — `Frontend::context()`

`context()` gains a single branch: build the registry as today, then if
`Demo_Mode::is_active()` for this request and
`Demo_Mode::resolve_look_slug()` yields a known slug, use
`$registry->get( $demo_slug )` in place of `$registry->active()` for the
context's `look`. Everything else in the context is unchanged. Because both
`enqueue_assets()` and `render_body()` read the same `context()`, the whole
page (fonts, look CSS, inline `:root`, and body) stays consistent in the demo
look.

`context()` reads the cap and cookies through small `function_exists`-guarded
seams so the branch is exercisable with the WP shim.

### Thin WP glue — `Blueworx_Clubhouse_Demo_Controller`

New class `includes/admin/class-demo-controller.php`, the only WP-coupled
piece, hooked from `Frontend::register()`:

- `admin_bar_menu` → add the "⚡ Demo mode: ON/OFF" node when
  `current_user_can('manage_options')`.
- `wp_footer` → echo `Demo_Mode::switcher_html(...)` when demo is active and the
  request is a Clubhouse-rendered page.
- `wp_enqueue_scripts` (or inline in footer) → enqueue `assets/js/demo.js` and
  `assets/css/demo.css` for admins, gated the same way.

### Front-end assets

- `assets/js/demo.js` — sets/clears the flag cookie (toggle), sets the look
  cookie (switch), and reloads. Vanilla JS, no dependencies. Progressive: the
  admin-bar node can also be a plain link fallback if JS is off (nice-to-have,
  not required).
- `assets/css/demo.css` — styles the fixed switcher bar. Neutral chrome
  (does not consume the club's accent tokens) so it reads as tooling, not part
  of the site.

## Data flow

```
admin-bar click
  → demo.js sets `clubhouse_demo` cookie
  → reload
  → Frontend::context() reads cap + cookies
  → Demo_Mode::is_active() = true
  → Demo_Mode::resolve_look_slug() → demo slug (or null → saved look)
  → context.look = registry.get(demo slug)
  → enqueue_assets() + render_body() render that look
  → wp_footer echoes switcher, current look highlighted
switch look
  → demo.js sets `clubhouse_demo_look` cookie → reload → (same path, new look)
exit demo
  → demo.js clears both cookies → reload → saved look
```

## State

Cookies only. No new WordPress option, nothing persisted to the club's
settings, nothing to accidentally leave switched on site-wide. Cookies are
per-browser and only honoured for admins.

## Testing

- **Pure PHPUnit — `Demo_Mode`:**
  - `is_active`: false when not `can_manage` even with the flag set; false when
    flag absent; true only with both.
  - `resolve_look_slug`: returns the cookie slug when known; `null` when
    inactive, cookie missing, or slug unknown/stale.
  - `switcher_html`: one control per look; current look flagged; "Exit demo"
    present; all dynamic text escaped; no accent/colour literals (skin-agnostic
    guard).
- **WP-shim glue** (existing `tests/php/wp-stubs.php` pattern): admin-bar node
  added only when `can_manage`; footer switcher emitted only on a Clubhouse
  page when active; assets enqueued only for admins.
- **Manual WP smoke** (cookie + admin bar are runtime-only): log in as admin →
  visit a Clubhouse page → admin bar shows "Demo mode: OFF" → click → switcher
  appears → flip through every look (page re-skins each time) → open Clubhouse →
  Setup and confirm the saved look is unchanged → "Exit demo" returns to saved
  look → open the site logged out (or as a non-admin) and confirm the saved
  look and no switcher.

## Scope / non-goals

- No accent switching in the demo (accent stays the club's saved accent) —
  confirmed with the user; may revisit later.
- No public/shareable demo link — admins only.
- No stored "demo mode" site setting — per-admin cookie only.
- Switcher enumerates looks from the registry; it is **not** hardcoded to three.

## Deployment

New feature → minor bump to **0.17.0**, changelog updated alongside. Rebuild the
deployment zip (bsdtar, forward-slash entries) at
`C:\Users\LukeMcfarland\Documents\GitHub\blueworx-labs-clubhouse.zip` so it can
be uploaded and tested.
