# Admin & Clubhouse Owner Experience — Design

**Date:** 2026-07-13
**Status:** Approved (brainstorm), pending implementation plans
**Branch:** `admin-clubhouse-owner-experience`

## Purpose

The plugin currently renders an eight-page site from stored options and six
collection CPTs, but there is **no owner-facing way to configure it**. Everything
(active Base Look, branding, visibility, social links) is only settable in code,
and collection content is only seeded as demo data. This is the last major build:
give a non-technical club owner a safe, curated WordPress back-end to set up and
run their site, without touching code or the parts of WordPress that could break it.

It also closes a set of engine/robustness items deferred from earlier plans, all of
which have a natural home in this work.

## Scope

Delivered as **one umbrella spec, decomposed into five sequential implementation
plans** (the repo's established pattern). Each phase is independently shippable and
must pass CI.

In scope:

1. A bespoke **Clubhouse Setup** surface for configuration (Base Look, branding,
   visibility) with a completion **progress bar**.
2. **Native CPT editing** for the six collections via custom meta-boxes + admin
   list columns.
3. A new **`clubhouse_owner` role** with curated capabilities, a locked-down admin
   menu, and a **Dashboard takeover** so login lands straight on the setup screen.
4. **Font self-hosting** (drop the Google Fonts CDN dependency).
5. Deferred engine/robustness fixes folded into the phases that touch them.

Out of scope (explicitly): a first-run wizard (rejected — login goes straight to
`/wp-admin/index.php`); bespoke CRUD forms for collections (rejected in favour of
native CPT screens); multi-accent / secondary brand colours; native WordPress Pages
for owners; any third-party plugin integrations (SureCart etc. remain architected-only).

## Architecture

Two owner surfaces, each using the right tool for its shape of data:

- **Config is bespoke.** Branding, look choice and visibility are not post-like, so
  they live in a custom screen backed by the existing `Branding` / `Visibility` /
  `Base_Look_Registry` option stores.
- **Content is native.** Each collection is genuinely a post with meta (a fixture is
  a date + two teams + a result). WordPress already provides list/search/pagination/
  revisions/trash, so content editing stays on the native CPT screens, enhanced with
  meta-boxes rather than replaced.
- **The role ties it together.** `clubhouse_owner` gets exactly the capabilities for
  those two surfaces plus the essentials, and nothing else.

### Component boundaries

Pure, WP-free, unit-tested units (the majority of the logic):

- `Color_Engine::accent_is_legible( accent, shell_bg, shell_ink ): bool` — the single
  home for accent-contrast validation. True iff **both** the derived `accent-ink`
  (text on the accent fill) **and** `accent-deep` (accent-as-text on the shell) clear
  WCAG AA (≥ 4.5). Reuses the existing `derive()` / `contrast_ratio()` math.
- `Setup_Progress::compute( Branding, active_look_slug ): array` — returns the six
  config booleans + a completed/total count. No WordPress.
- `Setup_Screen::render( … ): string` — pure HTML builder for the setup surface
  (mirrors `Sections` / `Page_Renderer`): progress bar + Look/Branding/Visibility
  areas. Emits a form; does not process it.
- `Owner_Capabilities` — a pure map: the capability list granted to `clubhouse_owner`
  and the menu-slug allowlist. Consumed by the WP glue; asserted directly in tests.
- `Collection_Meta` — pure field definitions + sanitisation per CPT (which meta keys,
  their types, how each is sanitised/escaped). The meta-box glue renders and saves
  through it.
- `Clubhouse_Context` — a named DTO replacing `Frontend::context()`'s positional
  array (`look`, `branding`, `visibility`, `cache`, `collections`, `registry`).

Thin WP-coupled glue (kept minimal, tested via the `tests/php/wp-stubs.php`
`function_exists`-guarded recorders + a manual smoke):

- `Setup_Controller` — registers the admin page, handles the POST (nonce + capability
  check, sanitise via the pure units, persist, `Theme_Cache::invalidate()`), and
  renders `Setup_Screen`.
- `Collection_Meta_Boxes` — `add_meta_box` / `save_post` wiring through `Collection_Meta`.
- `Owner_Role` — registers/updates the role on activation; the Dashboard takeover,
  menu hiding, and chrome removal hooks.
- `Font_Assets` — enqueues self-hosted `@font-face` CSS instead of the CDN.

## Phases

### Phase 1 — Foundation & engine hardening (no UI)

Makes a look picker actually take effect and closes deferred engine gaps. Pure,
low-risk, unblocks Phase 2.

- **Register all three looks** in `Frontend::context()` (currently only Court Side is
  registered, so the active-look selection can never resolve to Members' House or
  Floodlight in production). Register Court Side + Members' House + Floodlight; the
  registry resolves the active one from stored state with the existing first-registered
  fallback.
- **`Color_Engine::accent_is_legible()`** validator (as above).
- **`Theme_Cache` signature** currently `md5( look->slug() . '|' . accent )`. Extend it
  to include a hash of the look's **shell token contents** and the **plugin version**,
  so a plugin upgrade that changes a look's tokens (without changing slug or accent)
  no longer serves stale `:root` CSS.
- **`context()` → `Clubhouse_Context` DTO.** Replace the positional array + `list()`
  destructuring at all call sites (`enqueue_assets`, `render_body`, `club_name`) with
  the named DTO. Adds the sixth slot (`registry`, needed by Phase 2) cleanly.

Tests: legibility validator across saturated + desaturated hues and light/dark shells;
signature changes when tokens or version change and is stable otherwise; DTO round-trip.

### Phase 2 — Clubhouse Setup screen (config UI + progress bar)

The setup surface as a **standard admin page** first (menu-mounted, usable by full
admins), so it can be built and tested before the role/Dashboard-takeover work.

- **Look area:** the three looks as selectable cards (name + short description); saving
  calls `Base_Look_Registry::set_active()`.
- **Branding area:** accent colour input with a live-derived preview and **legibility
  rejection** — on save, an accent where `accent_is_legible()` is false against the
  active look's shell is refused with an inline error and not persisted; club name;
  logo via the WordPress media modal (stores attachment ID); Facebook + Instagram URLs.
- **Visibility area:** page + section toggles from the `Visibility` store (shown, but
  not counted toward progress — all-visible is a valid finished state).
- **Progress bar:** `Setup_Progress::compute()` → six items (look, legible accent,
  name, logo, Facebook, Instagram) as an `X of 6` bar.
- On any successful save, call `Theme_Cache::invalidate()`.
- **Look-switch re-validation:** if the stored accent becomes illegible under a newly
  chosen look, surface a warning prompting a new accent (the site still renders — the
  engine's `accent-deep` guarantee holds — but `accent-ink` fills may be weak).
- Confirm the header renderer consumes `Branding::get_logo()`; wire it if it does not.

Tests: progress computation; save rejects illegible accent and accepts legible;
invalidation called on save (shim). Playwright: the setup screen renders in the preview
harness where feasible, otherwise manual smoke.

### Phase 3 — Collection editing (CPT meta-boxes)

Make the six collections editable, so owners replace demo data with their own.

- **Meta-boxes** for each CPT's fields via `Collection_Meta` (e.g. fixture:
  `home_team`, `away_team`, `match_date`, `result`; person: `committee_role`,
  `directory_role`, `email`; sponsor: `url`; team/sport/event fields per the existing
  seeder/mapper contract). Fields sanitised on save through the pure definitions.
- **Admin list columns** for the high-signal fields (e.g. fixture date + teams) so the
  list screens are usable.
- **`Fixture_Projection` robustness** (deferred items that live with fixtures): group
  the calendar by `Y-m` rather than month-name (correct across multiple years), and
  guard against empty/malformed `match_date` (`new DateTimeImmutable('')` currently
  resolves to "now").
- The seed→register→read meta-key contract must stay intact end-to-end (the meta keys
  the boxes write are exactly those the mappers read and the seeder seeds).

Tests: meta sanitisation per field; projection Y-m grouping across a two-year fixture
set; malformed-date guard; save round-trip via shim. Manual smoke: edit a fixture,
confirm `/calendar` reflects it.

### Phase 4 — Clubhouse Owner role & admin lockdown

Wrap the two surfaces in a curated role.

- **Register `clubhouse_owner`** on activation via `Owner_Role`, granting the
  `Owner_Capabilities` map: read/edit/publish/delete for the six collection CPTs and
  native Posts; `upload_files` (Media); `list_users` + user management (per decision);
  edit-own-profile; the custom setup capability. **No** `edit_theme_options`,
  `activate_plugins`, `manage_options`, `edit_pages`, `install_*`, `update_*`.
- **Dashboard takeover:** for `clubhouse_owner` only, remove the default dashboard
  widgets and render `Setup_Screen` as the body of `index.php`, so login lands directly
  on setup. Full admins' dashboard is untouched.
- **Menu lockdown:** an allowlist — Dashboard (=setup), the six collections (grouped
  under a single "Content" parent for tidiness), Media, Posts, Comments, Users, Profile,
  and any plugin-specific menu. Everything else (`Appearance`, `Plugins`, `Tools`,
  `Settings`, native `Pages`) removed for the role. Enforced at both the menu level
  (hide) and the capability level (so direct URL access is also denied).
- Role is created on activation and cleaned up predictably (kept on deactivate, or
  removed — decide in the plan; default: keep so owner assignments survive a reactivate).

Tests: the capability map and allowlist asserted directly (pure); menu-removal and
dashboard-takeover hooks via shim recorders. Manual smoke: log in as an owner, confirm
the locked-down menu, the setup dashboard, and that hidden areas 403 on direct URL.

### Phase 5 — Font self-hosting

Remove the third-party CDN dependency.

- Bundle the required font files (the weights each look actually uses) under
  `assets/fonts/`, emit `@font-face` from a generated stylesheet, and enqueue that
  instead of `Page_Renderer::google_fonts_url()`.
- Drop the `googleapis`/`gstatic` preconnect resource hints.
- Keep the preview harness in sync (it currently uses CDN fonts).

Tests: enqueue spec points at local fonts, not the CDN; no `googleapis` reference in
the emitted head. Licensing note: confirm each font's licence permits self-hosting
before bundling (all are open-licence Google Fonts; verify per family).

## Data & storage

No new option schema beyond what exists. Setup writes through `Branding`,
`Visibility`, and `Base_Look_Registry` (all already single-option stores via the
`Storage` abstraction). Collection meta uses the existing registered meta keys.
The role and a `setup_complete`-style flag (if needed for the progress bar's "done"
state) are the only additions, and the progress bar can be computed purely from
existing state without a persisted flag — preferred.

## Error handling

- **Illegible accent:** rejected on save with an inline message; prior value retained.
- **Look switch invalidating the accent:** non-blocking warning; site still renders.
- **Capability/nonce failures:** standard `wp_die`/`check_admin_referer`; owners can
  never reach a screen their caps don't allow (menu hidden *and* capability denied).
- **Malformed fixture dates:** guarded in the projection; a bad date renders as
  "date TBC" rather than "now" or a fatal.
- **Missing/deleted collection content:** renderers already handle empty sets; unchanged.

## Testing strategy

Consistent with the codebase: pure logic (colour legibility, cache signature, progress,
capability map, meta sanitisation, projection) is unit-tested WP-free; thin WP glue is
exercised through the `tests/php/wp-stubs.php` `function_exists`-guarded recorders; the
front end stays covered by the Playwright preview smoke. Each phase additionally owes a
**manual WordPress smoke** on a real install — which also finally discharges the
outstanding #7 (routing/`is_front_page`) and #8 (CPT seed/round-trip) manual smokes.

## Versioning & deployment

Minor bump per phase (0.15.0 → ~0.19.0), changelog updated alongside each. The
deployment zip is refreshed whenever a phase changes runtime files (Phases 2–5 do;
Phase 1 changes runtime too). House WordPress-plugin zip rule applies from Phase 1
onward since the plugin is now WP-integrated.

## Risks & open items

- **Dashboard takeover** is the least-conventional piece; it must not leak into the
  full-admin experience. Gate every hook on the current user's role.
- **Font self-hosting** requires committing binary font files — confirm licences and
  keep the set minimal (only weights in use).
- **Collection meta breadth:** six CPTs with several fields each is the largest surface;
  `Collection_Meta` must stay the single source of truth shared by seeder, mapper, and
  meta-boxes to avoid the key-mismatch class of bug seen in the collections build.
