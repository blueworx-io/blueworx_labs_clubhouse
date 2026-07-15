# Manual WP Smoke Test — Clubhouse

A live-install checklist for behaviours that **cannot** be covered by unit tests
(real WordPress, multiple browsers, capability/nonce probing, admin UI). Run it on
a fresh WordPress install after any release that touches the admin, roles, routing,
or demo mode. Unit-tested logic is intentionally excluded.

Current scope: **v0.20.0** — Admin Phases 1–4 plus the site-wide Demo mode runtime.

---

## Setup

- [ ] Fresh WP install, activate the plugin.
- [ ] Demo content seeds on activation and Home renders at the site root.

## Phase 1 — Engine hardening (0.15.0)

- [ ] Set active look to **Members' House** → the front end actually re-skins (not stuck on Court Side).
- [ ] Set active look to **Floodlight** → renders correctly.
- [ ] Change a look's accent, reload → tokens update (no stale `:root` CSS served after the change).

## Phase 2 — Setup screen (0.16.0)

- [ ] **Clubhouse → Setup** loads; the Base Look picker switches the active look.
- [ ] Enter a **low-contrast accent** → save is rejected with the look-aware message; a legible accent saves.
- [ ] Set club name, pick a **logo** via the media library, add Facebook + Instagram URLs → all persist.
- [ ] Hide a **sub-page** → it 404s on the front end.
- [ ] Hide the **home page** → falls back to WP's front-page setting (no fatal).
- [ ] Toggle a section's visibility → it disappears from the page.
- [ ] Progress bar tracks the six branding/look items.

## Phase 3 — Collection editing + header/nav (0.18.0)

- [ ] Edit each of the six CPTs (fixtures, teams, people, sponsors, sports, events) → typed meta inputs (date/time/select/email/url) save and escape correctly.
- [ ] Image picker (`wp.media`) attaches an image to a collection item.
- [ ] Admin **list columns** show the high-signal fields (e.g. a fixture's date/teams/result).
- [ ] Front-end header renders the **logo** beside the club name.
- [ ] Hidden pages are **omitted from the header nav and footer** links.
- [ ] Calendar groups fixtures by **year-and-month** (two Januaries in different years don't merge); an **undated fixture shows "Date TBC"** (not "now").

## Phase 4 — Clubhouse Owner role (0.19.0)

- [ ] Create a user with the **Clubhouse Owner** role; log in as them → lands directly on **Setup** (dashboard replaced).
- [ ] Admin menu limited to **Setup, Content, Media, Posts, Comments, Users, Profile**.
- [ ] **Appearance, Plugins, Tools, Settings, Pages** are hidden *and* return access-denied if the URL is hit directly (capability-denied, not merely hidden).
- [ ] The six collection CPTs appear grouped under one **Content** menu.
- [ ] Owner can **view** the Users list but cannot create/edit/delete users; can edit collections + the blog, upload media, moderate comments.
- [ ] Setup screen is gated by `manage_clubhouse` (an owner reaches it; a subscriber does not).

## Demo mode runtime (0.20.0)

- [ ] As **admin**: the ⚡ toggle appears in the admin bar **front-end and in wp-admin**; flipping it turns demo on/off.
- [ ] The Setup screen's demo control mirrors the toggle state.
- [ ] While **on**: every visitor (log out / incognito) sees the floating switcher and can click through the Base Looks.
- [ ] Clicking a look in the switcher re-skins **that viewer only** (browser cookie) — a second browser is unaffected.
- [ ] The club's **saved look is never changed** — turn demo off, confirm the saved look returns.
- [ ] A **non-admin** viewer sees the look controls but **no "Turn off demo mode"** control.
- [ ] The toggle is **nonce + capability gated**: a non-admin (or a forged/stale nonce) hitting `admin-post.php?action=clubhouse_demo_toggle` cannot flip the flag.

## Content editor (v0.24.0)

Runtime-only — the Site Content screen is a wp-admin screen, so the DB-free preview
cannot render it. These must be checked on a real WordPress install.

- [ ] Activate → a **Site Content** screen appears under Clubhouse (owner + admin). Guards the init wiring (a past merge silently dropped such wiring).
- [ ] The screen is skinned to the saved Base Look **including fonts on first paint**; switching look in Setup re-skins it.
- [ ] Edit Global → Hero heading, Save → the front-end Home hero shows the new heading, and every field you did **not** edit is unchanged.
- [ ] Add two FAQ items on Membership → they render on `/membership/`; remove one → it's gone.
- [ ] Toggle a section **Hidden** here → it disappears from the front end **and** shows unticked in Setup → Visibility (shared store). Check the **Global tab's Header/Footer** specifically — they store under `global` but hide under `home`, which is exactly where this silently failed in review.
- [ ] A link-out (e.g. Teams directory) opens the native `clubhouse_team` list screen; the **"Edit tiers"** link-out (Global → Membership tiers) stays on the Site Content screen rather than landing on a WordPress error page.
- [ ] An Image field opens `wp.media`, stores the attachment, and the image renders on the front end. Two image fields on one screen must not cross-wire.
- [ ] Everything reachable and submittable **JS-off**: tabs via links, Save via submit, **and Add/Remove via their submit buttons** (these were silent no-ops until review caught it — worth an explicit JS-off check).
- [ ] Content editor styles do **not** bleed into the rest of wp-admin (scoped to the page body class).
- [ ] Home hero **quick tiles**: edit a tile's label/link and pick an Icon → the icon card at the foot of the Home hero updates. A tile saved with "No icon" renders without a glyph (not a broken/blank icon).
