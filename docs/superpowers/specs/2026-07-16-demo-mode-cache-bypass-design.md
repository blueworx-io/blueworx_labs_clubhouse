# Demo-mode cache bypass — design

**Date:** 2026-07-16
**Branch:** `clear-cache-on-look-switch`

## Problem

When site-wide Demo mode is on, any visitor can switch Base Looks via the floating
switcher. `demo.js` sets the `clubhouse_demo_look` cookie and calls
`window.location.reload()` so the server re-renders with the chosen look. On an
install fronted by a **full-page cache** (a caching plugin such as WP Rocket /
W3 Total Cache / WP Super Cache, a host page cache, or a CDN), that reload is
served from the cache without re-running PHP, so the page never re-skins — the
swap only appears after a manual hard refresh. This happens for every visitor,
which is the reported symptom.

The plugin's own theme cache (`Blueworx_Clubhouse_Theme_Cache`) is **not** the
culprit: its `:root` output is signature-guarded on look slug + accent + tokens +
version, so it always re-derives the correct CSS for whichever look renders. No
change is needed there.

## Fix

While demo mode is on, mark clubhouse front-end responses as non-cacheable so
each visitor's look-cookie always forces a fresh server render:

- `nocache_headers()` — WordPress's standard `Cache-Control: no-cache, no-store,
  must-revalidate` set (covers the browser HTTP cache and CDNs that honour it).
- `define( 'DONOTCACHEPAGE', true )` — the de-facto constant honoured by WP
  Rocket, W3 Total Cache, WP Super Cache, and most host page caches to skip
  caching for the current request.

### Scope

Bypass applies **only while demo mode is on, and only on clubhouse pages** — the
same `is_on() && is_clubhouse_page()` gate the rest of demo mode's front-end
furniture already uses (`Demo_Controller::shows_furniture()`). When demo mode is
off, nothing changes and normal page caching is untouched. Demo mode is an
ephemeral, admin-triggered state used while showing looks to a prospect, so
losing page-cache during it is negligible, and bypassing whenever it is on (not
only when a demo cookie is already present) guarantees the *first* switch works
too.

`Vary: Cookie` when demo is off was considered and rejected — it would depress
cache hit rates in normal operation for no benefit.

## Components

- **`Blueworx_Clubhouse_Demo_Mode::should_bypass_cache( bool $demo_on, bool
  $is_clubhouse_page ): bool`** — pure decision, returns `$demo_on &&
  $is_clubhouse_page`. Lives on the existing pure class alongside
  `resolve_look_slug`.
- **`Blueworx_Clubhouse_Demo_Controller::maybe_bypass_cache(): void`** — glue.
  Reads `is_on()` and `Frontend::is_clubhouse_page()`, and when
  `should_bypass_cache()` is true calls `nocache_headers()` and defines
  `DONOTCACHEPAGE` (guarded by `defined()`). Registered on `template_redirect`,
  which runs after the query is resolved (so `is_clubhouse_page()` is accurate)
  and before output — early enough for page-cache plugins to see the signal.

## Testing

- Pure `should_bypass_cache` — the full truth table (on+clubhouse → true; the
  three other combinations → false).
- Glue via the WP-function shim (new `nocache_headers` recorder stub): fires
  `nocache_headers` and defines `DONOTCACHEPAGE` when on + clubhouse page; does
  **not** fire it off a clubhouse page or when demo mode is off; and the
  `template_redirect` hook is registered.

## Manual WP smoke (runtime-only, owed)

On an install with a page-cache plugin active: turn demo mode on, switch looks as
a logged-out visitor, and confirm the page re-skins on the switcher's reload with
no hard refresh; turn demo mode off and confirm normal pages are cached again.
