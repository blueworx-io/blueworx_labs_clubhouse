# Site-wide Demo Mode — Design

**Date:** 2026-07-13
**Branch:** `admin-demo-mode` (extends the shipped per-admin Demo mode; PR #13)
**Target version:** 0.18.0 (behavioural change + new admin surface)

## Purpose

Evolve Demo mode from a **private, per-admin preview** into a **site-wide,
admin-controlled** demo that every visitor can see and explore — so an admin can
turn it on, hand a prospective club owner a link (or their own device), and let
them click through the Base Looks themselves, then turn it off.

Supersedes the v0.17.0 model (`is_active = current_user_can && flag cookie`;
switcher + re-skin visible to admins only).

## Behaviour

1. **Activation is a stored, site-wide state.** A single option
   (`clubhouse_demo_active`, boolean) is the source of truth for "demo mode is
   on". Only users who can `manage_options` may change it (nonce-protected).
2. **When ON, everyone sees it.** Every visitor — logged out, member, admin —
   sees the floating look switcher, and the site renders in a demo look. The
   demo assets (`demo.css` / `demo.js`) are enqueued for all viewers while on.
3. **Look selection is per-viewer and open to anyone.** Clicking a look sets the
   viewer's own browser cookie (`clubhouse_demo_look`) and reloads; the server
   renders that look for that viewer only. Default (no cookie) = the club's
   saved active look. **Nothing is persisted to the club's settings** — the
   saved look, accent, and content are never written by the demo path.
4. **Only admins can deactivate.** The switcher's "Turn off demo mode" control
   renders **only** for users who can `manage_options`; non-admins get the look
   buttons but no on/off control.
5. **When OFF, normal.** No switcher, saved look, no demo assets — for everyone.

## Surfaces

- **Admin-bar toggle** — the `⚡ Demo mode: On/Off` node (shown to admins in both
  the front-end AND wp-admin) is a **nonce'd link** to an `admin-post.php`
  handler that flips `clubhouse_demo_active` and redirects back. No JS required;
  works identically in front-end and backend.
- **Clubhouse → Setup screen** — a **Demo mode ON/OFF** control on the existing
  Setup form, persisted through the same option on save (behind the screen's
  existing capability + nonce). Discoverable backend control.
- **Front-end floating switcher** — shown to everyone while on; look buttons for
  all, deactivate control for admins only.

## Components

### `Blueworx_Clubhouse_Demo_State` (new, Storage-backed, unit-tested)

The single source of truth. Constructed with a `Storage`; testable with
`Fake_Storage`.

- `is_on(): bool` — reads the `clubhouse_demo_active` option (default `false`).
- `set( bool $on ): void` — writes it.

Option key constant: `clubhouse_demo_active`.

### `Blueworx_Clubhouse_Demo_Mode` (pure, reworked)

Capability is no longer part of look resolution (activation is now a stored
flag, not a cap+cookie computation). Removes the old `is_active(bool,?string)`.

- `resolve_look_slug( bool $demo_on, ?string $look_cookie, array $available_slugs ): ?string`
  — when demo is on and the cookie is a known slug, return it; otherwise `null`
  (fall through to the saved active look). Unknown/stale slug → `null`.
- `switcher_html( array $looks, ?string $current_slug, bool $can_deactivate ): string`
  — one control per look (all viewers); the "Turn off demo mode" control is
  emitted only when `$can_deactivate` is true. Pure, escaped, skin-agnostic.
- Keeps `COOKIE_LOOK = 'clubhouse_demo_look'`. **Removes** `COOKIE_FLAG` (the
  on/off flag is now server state, not a cookie).

### `Blueworx_Clubhouse_Demo_Controller` (glue, reworked)

- `is_on(): bool` — `Demo_State` from `Options_Storage`.
- `look_slug( Base_Look_Registry $registry ): ?string` —
  `Demo_Mode::resolve_look_slug( self::is_on(), cookie(COOKIE_LOOK), array_keys( $registry->all() ) )`.
  Drives the render override for **everyone** (no cap).
- `register()` — hooks `admin_bar_menu`, `wp_enqueue_scripts`, `wp_footer`, and
  `admin_post_{ACTION}` (the toggle handler).
- `admin_bar_node( $wp_admin_bar )` — admin-only; renders the toggle as a
  `wp_nonce_url( admin_url('admin-post.php?action=clubhouse_demo_toggle') )`
  link labelled with the current on/off state.
- `handle_toggle()` — the `admin_post` callback: `current_user_can` + nonce
  check, flip `Demo_State`, `wp_safe_redirect( wp_get_referer() ?: home_url('/') )`.
- `enqueue()` — enqueues `demo.css` / `demo.js` when `is_on()` (all viewers).
- `render_switcher()` — on `wp_footer`, when `is_on()`, echoes
  `Demo_Mode::switcher_html( $looks, $current, current_user_can('manage_options') )`.
  Looks enumerated from `registry->all()`.

Capability constant stays `CAPABILITY = 'manage_options'`; nonce action
`clubhouse_demo_toggle`.

### `Frontend::context()`

Unchanged in shape: `$demo_slug = Demo_Controller::look_slug( $registry )` then
`$look = null !== $demo_slug ? $registry->get($demo_slug) : $registry->active()`.
Because `look_slug` now keys off `Demo_State` (public) rather than a cap, the
override applies for every viewer while demo is on.

### Setup screen (`Setup_Screen` + `Setup_Controller`)

- `Setup_Screen::render` gains a **Demo mode** ON/OFF checkbox (POST field
  `clubhouse_demo_active`, matching the option key) in a small section;
  `build_model` supplies its current state from `Demo_State`.
- `Setup_Controller::handle_save` reads the checkbox and calls
  `Demo_State::set()` (checkbox present = on, absent = off), inside the existing
  cap + nonce path. No change to accent/branding/visibility handling.

### Assets

- `assets/js/demo.js` — reworked: keeps only the **look-cookie** set + reload for
  `[data-clubhouse-look]`. Activation/deactivation are now server links, so the
  admin-bar toggle and the deactivate control are plain `<a href>`s (no JS).
  Remove the flag-cookie toggle logic.
- `assets/css/demo.css` — unchanged (neutral chrome).

## Data flow

```
Admin clicks ⚡ Demo mode (admin bar, front or back)
  → nonce'd admin-post.php?action=clubhouse_demo_toggle
  → handle_toggle: cap + nonce OK → Demo_State flips → redirect back
Any visitor loads a page while demo is ON
  → Frontend::context() → Demo_Controller::look_slug (is_on() true)
  → per-viewer cookie look, or saved look → page renders that look
  → wp_footer: switcher shown to all (deactivate control admins only)
Visitor clicks a look
  → demo.js sets clubhouse_demo_look cookie → reload → their view re-skins
Admin turns it off (admin-bar toggle, switcher control, or Setup)
  → Demo_State off → everyone back to saved look, switcher gone
```

## State

- `clubhouse_demo_active` — server option, admin-only writes. Site-wide.
- `clubhouse_demo_look` — per-browser cookie, anyone. Never persisted to settings.

## Security

- Only `manage_options` users can flip the state: the `admin_post` handler
  checks capability + nonce; the Setup save is already cap + nonce gated. No
  non-admin path activates or deactivates.
- Look-switching is public but harmless — a per-browser cookie preview that
  never writes club settings.
- The render override is now intentionally public (that is the requested
  behaviour), gated on the stored flag, not the viewer.

## Caveats (noted, not blockers)

1. **Public exposure while on.** When on, real public visitors see the demo
   switcher and demo look. This is intended; the admin-bar node reads
   "Demo mode: On" as a persistent reminder to turn it off.
2. **Full-page caches.** Per-viewer look cookies can be mis-served by a page
   cache that doesn't vary on the cookie; the no-cookie default (saved look) is
   safe. Acceptable for a short demo session; documented for smoke.

## Testing

- **Pure** `Demo_Mode`: `resolve_look_slug` (on/off, known/unknown/absent cookie,
  no cap involved); `switcher_html` (look controls for all; deactivate control
  present only when `$can_deactivate`; escaping; skin-agnostic).
- **`Demo_State`** with `Fake_Storage`: default off; set true/false round-trips.
- **Glue** `Demo_Controller` (WP shim): `register` hooks include
  `admin_post_clubhouse_demo_toggle`; `is_on`/`look_slug` read state + cookie;
  `handle_toggle` flips state (nonce/cap verified via shim); enqueue + switcher
  gate on `is_on()` not cap.
- **Setup**: `handle_save` sets `Demo_State` from the checkbox (present=on,
  absent=off); `Setup_Screen::render` emits the control; `build_model` reflects
  state.
- **Manual WP smoke** (runtime-only): admin toggles on from admin bar (front +
  wp-admin) and from Setup; a logged-out visitor sees the switcher + can flip
  looks; a non-admin cannot see a deactivate control and cannot hit the toggle
  endpoint (nonce/cap); turning off returns everyone to the saved look; saved
  look in Setup is unchanged throughout.

## Migration / cleanup

- Remove `Demo_Mode::is_active` and `COOKIE_FLAG` and their tests; update
  `Demo_Controller` and `FrontendTest` accordingly (the v0.17.0 per-admin cap+
  cookie activation is fully replaced).

## Deployment

Minor bump to **0.18.0**, changelog updated. Rebuild the deployment zip. Folded
into the current `admin-demo-mode` branch / PR #13.
